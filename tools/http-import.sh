#!/usr/bin/env bash
# Alur hidup Tahap 4: unggah CSV hasil → pratinjau → proses → cek master resi & Data Error,
# lalu impor ulang file SAMA untuk membuktikan idempotensi.
B="${1:-http://localhost:8000}"
CSV="/mnt/c/Users/baiqs/Videos/panel.arjunanstore.com/tools/sample-mengantar.csv"
J="$(mktemp)"
EMAIL="${ARJ_EMAIL:-superadmin@arj.test}"
PASS="${ARJ_PASS:-change-me-please}"

token() { grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }

# 1) login
curl -s -c "$J" "$B/login" | token | { read -r t; curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/login" --data "_token=$t" --data "email=$EMAIL" --data "password=$PASS"; }
echo "logged in."

upload_and_process() {
  local label="$1"
  local t loc id
  t="$(curl -s -b "$J" "$B/impor" | token)"
  loc="$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{redirect_url}' -X POST "$B/impor" -F "_token=$t" -F "platform=mengantar" -F "file=@$CSV;type=text/csv")"
  id="$(printf '%s' "$loc" | grep -oE '[0-9]+$')"
  echo "[$label] batch id=$id"
  t="$(curl -s -b "$J" "$B/impor/$id" | token)"
  curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/impor/$id/proses" --data "_token=$t"
  echo "[$label] diproses."
}

q() { mysql -uroot -N -e "$1" 2>/dev/null; }

echo "== UPLOAD + PROSES #1 =="
upload_and_process "run1"
echo "-- counter batch $ (import_batches terbaru) --"
q "SELECT id,total_rows,new_rows,updated_rows,duplicate_rows,error_rows FROM arj.import_batches ORDER BY id DESC LIMIT 1;"
echo "-- shipments master resi --"
q "SELECT tracking_id,status_internal,customer_phone FROM arj.shipments ORDER BY tracking_id;"
echo "-- data_issues --"
q "SELECT type,COUNT(*) FROM arj.data_issues GROUP BY type;"
echo "-- event status --"
q "SELECT COUNT(*) FROM arj.shipment_status_events;"

echo "== UPLOAD + PROSES #2 (file SAMA → idempoten) =="
upload_and_process "run2"
echo "-- total shipment setelah impor ulang (harus sama) --"
q "SELECT COUNT(*) FROM arj.shipments;"
echo "-- batch run2 counters (harus: duplicate=... new=0) --"
q "SELECT id,total_rows,new_rows,updated_rows,duplicate_rows,error_rows FROM arj.import_batches ORDER BY id DESC LIMIT 1;"

echo "== cek halaman HTTP (auth) =="
for p in impor resi data-error; do
  printf "GET /%s -> %s\n" "$p" "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$B/$p")"
done
rm -f "$J"
