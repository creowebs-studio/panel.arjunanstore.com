#!/usr/bin/env bash
# Jalankan @ant-design/cli (butuh Node >=20) dari instal lokal ~/.antd-cli memakai Node 22
# via nvm — TANPA mengubah default Node proyek. Instal sekali, lalu pakai bin-nya.
# Pakai: wsl bash /path/antd-run.sh <subcommand ...>
set -eo pipefail
cd /mnt/c/Users/baiqs/Videos/panel.arjunanstore.com || exit 1
export NVM_DIR="$HOME/.nvm"
# shellcheck disable=SC1091
. "$NVM_DIR/nvm.sh" >/dev/null 2>&1
nvm use 22 >/dev/null 2>&1 || nvm use default >/dev/null 2>&1 || true

CLI_HOME="$HOME/.antd-cli"
if [ ! -x "$CLI_HOME/node_modules/.bin/antd" ]; then
  echo "[antd-run] instal @ant-design/cli ke $CLI_HOME (sekali) ..."
  mkdir -p "$CLI_HOME"
  printf '{"name":"antd-cli-host","private":true}\n' > "$CLI_HOME/package.json"
  ( cd "$CLI_HOME" && npm install --include=optional @ant-design/cli@latest )
fi

exec "$CLI_HOME/node_modules/.bin/antd" "$@"
