#!/usr/bin/env bash
# Verifikasi alur HTTP Tahap 3 (login -> daftar order -> form -> ekspor -> dashboard).
# Pemakaian: bash tools/http-flow.sh [BASE_URL]
B="${1:-http://localhost:8000}"
J="$(mktemp)"
EMAIL="${ARJ_EMAIL:-superadmin@arj.test}"
PASS="${ARJ_PASS:-change-me-please}"

code() { curl -s -o /dev/null -w "%{http_code}" "$@"; }

echo "== GET / (guest)          -> $(code "$B/") (expect 302) =="
echo "== GET /orders (guest)     -> $(code "$B/orders") (expect 302 to login) =="
echo "== GET /login (guest)      -> $(code "$B/login") (expect 200) =="

# Ambil token CSRF + simpan cookie sesi.
HTML="$(curl -s -c "$J" "$B/login")"
TOKEN="$(printf '%s' "$HTML" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
echo "== CSRF token            -> ${TOKEN:0:12}... (len ${#TOKEN}) =="

LOGIN="$(curl -s -b "$J" -c "$J" -o /dev/null -w "%{http_code} redirect=%{redirect_url}" \
  -X POST "$B/login" \
  --data "_token=$TOKEN" --data "email=$EMAIL" --data "password=$PASS")"
echo "== POST /login           -> $LOGIN (expect 302) =="

echo "== GET /orders (auth)     -> $(code -b "$J" "$B/orders") (expect 200) =="
echo "== GET /orders/baru       -> $(code -b "$J" "$B/orders/baru") (expect 200) =="
echo "== GET /ekspor            -> $(code -b "$J" "$B/ekspor") (expect 200) =="
echo "== GET /dashboard         -> $(code -b "$J" "$B/dashboard") (expect 200) =="
echo "== GET /ekspor/mengantar  -> $(code -b "$J" "$B/ekspor/mengantar") (expect 200 CSV / 404 empty) =="

rm -f "$J"
