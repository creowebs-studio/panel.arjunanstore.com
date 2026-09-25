#!/usr/bin/env bash
#
# Nyalakan ulang service lokal (MySQL + Redis) di WSL2 — dipakai setelah
# WSL/distro dimatikan lalu dinyalakan lagi (service tidak auto-start).
#
#   wsl -d Ubuntu-24.04 -u root bash /mnt/c/Users/baiqs/Videos/panel.arjunanstore.com/tools/wsl-start-services.sh
#
# Menangani dua hal yang berulang di WSL:
#  1. /run/mysqld & /var/lib/mysql-files hilang setelah reboot → mysqld abort.
#  2. systemd kadang gagal start mysql.service → fallback jalankan mysqld langsung.
set -uo pipefail

running() { pgrep -x "$1" >/dev/null 2>&1; }
log() { echo "[services] $*"; }

mkdir -p /run/mysqld /var/lib/mysql-files
chown mysql:mysql /run/mysqld /var/lib/mysql-files 2>/dev/null || true
chmod 750 /var/lib/mysql-files 2>/dev/null || true

if pid1="$(ps -p 1 -o comm= 2>/dev/null)" && [ "$pid1" = "systemd" ]; then
  systemctl start mysql 2>/dev/null || systemctl start mariadb 2>/dev/null || true
fi

if ! running mysqld && ! running mariadbd; then
  log "systemd tidak berhasil — menjalankan server DB langsung"
  if command -v mysqld >/dev/null; then
    (nohup mysqld --user=mysql >/tmp/mysqld.out 2>&1 &)
  else
    (nohup mariadbd --user=mysql >/tmp/mariadb.log 2>&1 &)
  fi
fi

running redis-server || (redis-server --daemonize yes --save '' --appendonly no) 2>/dev/null || true

for i in $(seq 1 30); do
  mysql -uroot -e "SELECT 1" >/dev/null 2>&1 && break
  sleep 1
done

log "versi DB: $(mysql -uroot -N -e 'SELECT VERSION()' 2>/dev/null || echo TIDAK-JALAN)"
log "redis  : $(redis-cli ping 2>/dev/null || echo TIDAK-JALAN)"
log "Siap. Jika DB masih kosong: cd /mnt/c/Users/baiqs/Videos/panel.arjunanstore.com && php artisan migrate:fresh --seed && php artisan serve --host=0.0.0.0 --port=8000"
