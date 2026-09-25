#!/usr/bin/env bash
# Pasang Node 22 via nvm (user-local, TIDAK mengubah default proyek). Hanya dipakai
# untuk menjalankan @ant-design/cli (butuh Node >=20). Proyek tetap build dengan Node 18.
set -eo pipefail
export NVM_DIR="$HOME/.nvm"

if [ ! -s "$NVM_DIR/nvm.sh" ]; then
  echo "[setup] meng-clone nvm v0.40.1 ke $NVM_DIR ..."
  rm -rf "$NVM_DIR"
  git clone --depth 1 --branch v0.40.1 https://github.com/nvm-sh/nvm.git "$NVM_DIR"
fi

# shellcheck disable=SC1091
. "$NVM_DIR/nvm.sh"

if ! nvm version 22 | grep -qv 'N/A'; then
  echo "[setup] nvm install 22 ..."
  nvm install 22
fi

nvm use 22 >/dev/null
echo "[setup] node: $(node --version)"
echo "[setup] npm : $(npm --version)"
echo "[setup] SELESAI"
