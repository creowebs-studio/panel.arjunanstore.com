#!/usr/bin/env bash
#
# Entrypoint container Docker: siapkan .env, kunci aplikasi, tunggu MySQL,
# migrasi + seed data referensi, lalu jalankan aplikasi.
#
# Kode aplikasi sudah ADA di dalam image (COPY saat build dari repo root) —
# tidak ada langkah create-project/overlay. Boot pertama: migrate:fresh --seed
# (ditandai storage/.arj-installed, tersimpan di volume app_data); boot
# berikutnya hanya migrate --force.
#
set -euo pipefail

APPDIR=/var/www/html

cd "$APPDIR"

log() { echo "[arj-entrypoint] $*"; }

# 1) Environment file.
if [ ! -f "$APPDIR/.env" ]; then
  log "Writing .env from .env.example…"
  cp "$APPDIR/.env.example" "$APPDIR/.env"
fi

# Host layanan di dalam jaringan compose: mysql + redis.
set_env() { grep -q "^$1=" "$APPDIR/.env" && sed -i "s|^$1=.*|$1=$2|" "$APPDIR/.env" || echo "$1=$2" >> "$APPDIR/.env"; }
set_env APP_ENV local
set_env APP_DEBUG true
set_env APP_URL "http://localhost:8000"
set_env DB_CONNECTION mysql
set_env DB_HOST mysql
set_env DB_PORT 3306
set_env DB_DATABASE "${DB_DATABASE:-arj}"
set_env DB_USERNAME "${DB_USERNAME:-arj}"
set_env DB_PASSWORD "${DB_PASSWORD:-secret}"
set_env QUEUE_CONNECTION redis
set_env CACHE_STORE redis
set_env SESSION_DRIVER redis
set_env REDIS_HOST redis
set_env REDIS_PORT 6379

# 2) App key.
if ! grep -qE '^APP_KEY=base64:' "$APPDIR/.env"; then
  log "Generating application key…"
  php artisan key:generate --force
fi

# 3) Tunggu MySQL siap menerima koneksi.
log "Waiting for MySQL…"
for i in $(seq 1 60); do
  if php -r "new PDO('mysql:host=mysql;port=3306;dbname=${DB_DATABASE:-arj};charset=utf8mb4','${DB_USERNAME:-arj}','${DB_PASSWORD:-secret}');" 2>/dev/null; then
    break
  fi
  sleep 2
done

# 4) Migrasi + seed data referensi.
INSTALLED_FLAG="$APPDIR/storage/.arj-installed"
php artisan config:clear
if [ ! -f "$INSTALLED_FLAG" ]; then
  log "Running migrate:fresh --seed (first install)…"
  php artisan migrate:fresh --seed --force
  touch "$INSTALLED_FLAG"
else
  log "Running migrate --force…"
  php artisan migrate --force
fi

# 5) Jalankan aplikasi (dev).
log "Starting php artisan serve on 0.0.0.0:${WEBPORT:-8000}…"
exec php artisan serve --host=0.0.0.0 --port="${WEBPORT:-8000}"
