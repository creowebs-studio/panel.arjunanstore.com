#!/usr/bin/env bash
# Verifikasi cepat: login superadmin → halaman Inertia utama termuat (komponen + props).
set -uo pipefail
BASE="${BASE:-http://localhost:8000}"
JAR="$(mktemp)"
trap 'rm -f "$JAR"' EXIT

echo "== GET /login (ambil csrf) =="
HTML="$(curl -s -c "$JAR" "$BASE/login")"
TOKEN="$(printf '%s' "$HTML" | sed -n 's/.*name="csrf-token" content="\([^"]*\)".*/\1/p' | head -1)"
[ -n "$TOKEN" ] && echo "csrf: OK" || echo "csrf: TIDAK ADA (cek session) "

echo "== POST /login =="
curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/login" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "email=superadmin@arj.test" \
  --data-urlencode "password=change-me-please" \
  -o /dev/null -w "login-post: %{http_code} -> %{redirect_url}\n"

for path in dashboard orders resi impor pemetaan-status data-error ekspor marketing rekap-adv komisi aturan-komisi; do
  CODE="$(curl -s -b "$JAR" -o /dev/null -w '%{http_code} %{time_total}' "$BASE/$path")"
  printf '%-18s %s\n' "/$path" "$CODE"
done
