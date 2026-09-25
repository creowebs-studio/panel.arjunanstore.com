#!/usr/bin/env bash
# Jalankan suite PHPUnit proyek (expect 55 passed). wsl bash /path/test.sh
set -eo pipefail
cd /mnt/c/Users/baiqs/Videos/panel.arjunanstore.com || exit 1
php artisan test
