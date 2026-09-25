#!/usr/bin/env bash
# Build frontend produksi (Vite) memakai Node sistem proyek (18) — node_modules
# disymlink ke /opt/arj_node_modules (EXT4). Panggil: wsl bash /path/build.sh
set -eo pipefail
cd /mnt/c/Users/baiqs/Videos/panel.arjunanstore.com || exit 1
npm run build
