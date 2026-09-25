#!/usr/bin/env bash
# Bukti end-to-end hidup: login -> input 1 order -> daftar -> unduh ekspor CSV.
B="${1:-http://localhost:8000}"
J="$(mktemp)"
EMAIL="${ARJ_EMAIL:-superadmin@arj.test}"
PASS="${ARJ_PASS:-change-me-please}"

login() {
  local t
  t="$(curl -s -c "$J" "$B/login" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
  curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/login" \
    --data "_token=$t" --data "email=$EMAIL" --data "password=$PASS"
}
login
echo "logged in."

# Token + product_id dari form input.
FORM="$(curl -s -b "$J" -c "$J" "$B/orders/baru")"
TOKEN="$(printf '%s' "$FORM" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
PID="$(mysql -uroot -N -e "SELECT id FROM arj.products WHERE code='P001' LIMIT 1" 2>/dev/null)"
echo "form token=${TOKEN:0:8}.. product_id=$PID"

PHONE="0812$(printf '%08d' $((RANDOM % 100000000)))"
NORM="62812$(printf '%08d' $((RANDOM % 100000000)))"

POST="$(curl -s -b "$J" -c "$J" -o /dev/null -w "%{http_code} redirect=%{redirect_url}" \
  -X POST "$B/orders" \
  --data "_token=$TOKEN" \
  --data "order_date=2026-09-24" \
  --data "cs_agent_id=" \
  --data "customer_name=Bukti Hidup" \
  --data "phone=$PHONE" \
  --data "address_detail=Jl. Contoh No. 1" \
  --data "kelurahan=Mekarjaya" \
  --data "kecamatan=Sukmajaya" \
  --data "kabupaten=Depok" \
  --data "kota=Depok" \
  --data "provinsi=Jawa Barat" \
  --data "zip_code=16411" \
  --data "product_id=$PID" \
  --data "product_detail=Sepatu Running" \
  --data "qty=1" \
  --data "payment_method=COD" \
  --data "price=159000" \
  --data "weight=1" \
  --data "aggregator=mengantar" \
  --data "expedition=")"
echo "POST /orders -> $POST"

echo "== daftar /orders?q=$PHONE =="
curl -s -b "$J" "$B/orders?q=$PHONE" | grep -oE 'positif|negatif|perlu_ditinjau|Bukti Hidup' | sort -u | tr '\n' ' '; echo

echo "== unduh /ekspor/mengantar =="
curl -s -b "$J" -o /tmp/export.csv -w "http=%{http_code} type=%{content_type}\n" "$B/ekspor/mengantar"
echo "--- isi CSV (5 baris pertama) ---"
head -5 /tmp/export.csv
rm -f "$J"
