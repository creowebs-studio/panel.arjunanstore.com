#!/usr/bin/env bash
#
# LOCAL dev bootstrap for WSL2 (Ubuntu) — the no-Docker path.
# Aplikasi berjalan LANGSUNG dari repo root (struktur Laravel standar, tanpa
# generate/overlay): install stack, pastikan MySQL+Redis jalan, composer install,
# .env, migrasi + seed. Run INSIDE WSL as root:
#
#   wsl -d Ubuntu-24.04 -u root bash /mnt/c/.../panel.arjunanstore.com/tools/local-bootstrap.sh
#
# Idempoten: apt hanya bila paket belum ada; migrate:fresh --seed hanya saat
# pemasangan pertama (ditandai storage/.arj-installed).
set -uo pipefail

PROJ="$(cd "$(dirname "$0")/.." && pwd)"     # .../panel.arjunanstore.com (mounted /mnt/c path)
DB_NAME="${DB_NAME:-arj}"
DB_USER="${DB_USER:-arj}"
DB_PASS="${DB_PASS:-secret}"

log() { echo "[local-bootstrap] $*"; }

# 1) Install stack (only what's missing).
need_pkgs=()
command -v php            >/dev/null || need_pkgs+=(php-cli php-mbstring php-xml php-curl php-mysql php-zip php-intl php-bcmath php-gd php-redis php-sqlite3 unzip git)
command -v composer       >/dev/null || need_pkgs+=(composer)
# Server DB: MySQL 8 (sesuai prompt.md). MariaDB juga jalan bila sudah terpasang.
command -v mysqld         >/dev/null 2>&1 || command -v mariadbd >/dev/null 2>&1 || need_pkgs+=(mysql-server mysql-client)
command -v redis-server   >/dev/null || need_pkgs+=(redis-server)

if [ "${#need_pkgs[@]}" -gt 0 ]; then
  log "apt-get update + install: ${need_pkgs[*]}"
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -y
  apt-get install -y "${need_pkgs[@]}"
else
  log "All system packages already present — skipping apt."
fi

# 2) Ensure DB + Redis are running (systemd if available, else manual).
#    PENTING (MySQL 8 di WSL): /run/mysqld & /var/lib/mysql-files hilang setiap reboot
#    distro dan harus dibuat sebelum server start, jika tidak mysqld abort.
running() { pgrep -x "$1" >/dev/null 2>&1; }
mkdir -p /run/mysqld /var/lib/mysql-files
chown mysql:mysql /run/mysqld /var/lib/mysql-files 2>/dev/null || true
chmod 750 /var/lib/mysql-files 2>/dev/null || true
if pid1="$(ps -p 1 -o comm= 2>/dev/null)" && [ "$pid1" = "systemd" ]; then
  log "systemd detected — using systemctl"
  systemctl enable --now mysql 2>/dev/null || systemctl enable --now mariadb 2>/dev/null \
    || service mysql start 2>/dev/null || service mariadb start 2>/dev/null || true
  systemctl enable --now redis-server 2>/dev/null || service redis-server start || true
fi
if ! running mysqld && ! running mariadbd; then
  log "starting DB server directly"
  command -v mysqld >/dev/null && (nohup mysqld --user=mysql >/tmp/mysqld.out 2>&1 &) \
    || (nohup mariadbd --user=mysql >/tmp/mariadb.log 2>&1 &)
fi
running redis-server || (redis-server --daemonize yes --save '' --appendonly no) 2>/dev/null || true

# Wait for MySQL/MariaDB socket
for i in $(seq 1 30); do
  mysql -uroot -e "SELECT 1" >/dev/null 2>&1 && break
  sleep 1
done

# Ensure DB + user exist
mysqlroot() { mysql -uroot "$@"; }
log "Ensuring database '$DB_NAME' and user '$DB_USER'"
mysqlroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

# 3) Dependencies + environment (repo root = aplikasi).
cd "$PROJ"
log "composer install (dependensi dari composer.lock repo)"
composer install --no-interaction --prefer-dist 2>/dev/null || composer update --no-interaction --prefer-dist
[ -f .env ] || cp .env.example .env
set_env() { grep -q "^$1=" .env && sed -i "s|^$1=.*|$1=$2|" .env || echo "$1=$2" >> .env; }
set_env APP_URL "http://localhost:8000"
set_env DB_CONNECTION mysql
set_env DB_HOST 127.0.0.1
set_env DB_PORT 3306
set_env DB_DATABASE "$DB_NAME"
set_env DB_USERNAME "$DB_USER"
set_env DB_PASSWORD "$DB_PASS"
set_env CACHE_STORE redis
set_env QUEUE_CONNECTION sync
set_env SESSION_DRIVER file

grep -qE '^APP_KEY=base64:' .env || php artisan key:generate --force

# 4) Writable storage (mounted NTFS can drop perms)
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# 5) Migrate + seed (fresh saat pemasangan pertama; incremental setelahnya).
log "php artisan migrate (fresh+seed saat pemasangan pertama)"
php artisan config:clear
if [ ! -f storage/.arj-installed ]; then
  php artisan migrate:fresh --seed --force
  touch storage/.arj-installed
else
  php artisan migrate --force
fi

# 6) Frontend (Inertia + React + Ant Design, Vite). node_modules TIDAK boleh
#    dipasang di /mnt/c: drvfs/NTFS merusak binari esbuild (vite gagal start).
#    Install di EXT4 (/opt/arj_node_modules) lalu symlink ke repo.
if command -v node >/dev/null && command -v npm >/dev/null; then
  NM_DIR="${NODE_MODULES_DIR:-/opt/arj_node_modules}"
  log "npm install di $NM_DIR (EXT4) — NTFS merusak binari esbuild"
  mkdir -p "$NM_DIR"
  cp "$PROJ/package.json" "$NM_DIR/package.json"
  [ -f "$PROJ/package-lock.json" ] && cp "$PROJ/package-lock.json" "$NM_DIR/package-lock.json"
  (cd "$NM_DIR" && npm install --no-audit --no-fund)
  chmod -R a+rX "$NM_DIR" 2>/dev/null || true
  ln -sfn "$NM_DIR/node_modules" "$PROJ/node_modules"
  log "Frontend siap: npm run build (produksi) / npm run dev (dev server)"
else
  log "node/npm tidak ditemukan — lewati langkah frontend (Node >= 18 wajib untuk Vite)"
fi

log "DONE. Serve with:"
log "  wsl -d Ubuntu-24.04 -u $(id -un) bash -lc 'cd \"$PROJ\" && php artisan serve --host=0.0.0.0 --port=8000'"
log "  (di terminal lain) wsl bash -lc 'cd \"$PROJ\" && npm run dev'"
