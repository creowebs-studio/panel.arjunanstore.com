#!/usr/bin/env bash
# Bukti hidup fitur perbaikan Tahap 3-4:
#   1. Halaman detail order (linimasa validasi/resi/ekspor/audit)
#   2. Koreksi klasifikasi ber-audit (orders.override → audit_logs)
#   3. Halaman & aksi pemetaan status (permission carriers.mapping.manage)
#   4. Proses ulang baris error impor (impor.reprocess)
# Jalankan di WSL sebagai root: bash tools/http-stage-check.sh
B="${1:-http://127.0.0.1:8000}"
J="$(mktemp)"
q() { MYSQL_PWD=secret mysql -h127.0.0.1 -uarj -N -e "$1" 2>/dev/null; } # TCP: socket /run/mysqld sering hilang di WSL

T="$(curl -s -c "$J" "$B/login" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/login" \
  --data "_token=$T" --data "email=superadmin@arj.test" --data "password=change-me-please"
echo "== login superadmin OK =="

# ---------- 1) Detail order + 2) Koreksi ber-audit ----------
OID="$(q "SELECT id FROM arj.orders ORDER BY id DESC LIMIT 1")"
if [ -z "$OID" ]; then
  echo "(belum ada order — jalankan tools/http-e2e.sh dulu)"; else
  echo "== /orders/$OID (detail) =="
  curl -s -b "$J" -o /tmp/detail.html -w "http=%{http_code}\n" "$B/orders/$OID"
  grep -oE 'Riwayat Validasi Nomor|Linimasa Ekspor|Resi &amp; Riwayat Status' /tmp/detail.html | sort -u

  T2="$(curl -s -b "$J" -c "$J" "$B/orders/$OID" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
  echo "== POST /orders/$OID/koreksi -> negatif =="
  curl -s -b "$J" -c "$J" -o /dev/null -w "http=%{http_code} redirect=%{redirect_url}\n" \
    -X POST "$B/orders/$OID/koreksi" \
    --data "_token=$T2" --data "classification=negatif" --data "reason=Bukti hidup: pelanggan membatalkan"
  echo "klasifikasi sekarang: $(q "SELECT classification FROM arj.orders WHERE id=$OID")"
  echo "audit_logs: $(q "SELECT CONCAT(action,' oleh user ',user_id) FROM arj.audit_logs WHERE auditable_id=$OID ORDER BY id DESC LIMIT 1")"
  echo "order_validations manual: $(q "SELECT COUNT(*) FROM arj.order_validations WHERE order_id=$OID AND JSON_EXTRACT(evidence,'\$.manual')=TRUE")"
fi

# ---------- 3) Pemetaan status ----------
echo "== /pemetaan-status =="
curl -s -b "$J" -o /tmp/map.html -w "http=%{http_code}\n" "$B/pemetaan-status"
grep -oE 'Pemetaan Status Agregator|Status Belum Terpetakan' /tmp/map.html | sort -u

T3="$(grep -oE 'name="_token"[^>]*' /tmp/map.html | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
echo "== POST /pemetaan-status (CANCELLED -> undel) =="
curl -s -b "$J" -c "$J" -o /dev/null -w "http=%{http_code}\n" \
  -X POST "$B/pemetaan-status" \
  --data "_token=$T3" --data "platform=general" --data "status_system=CANCELLED" \
  --data "status_internal=undel" --data "keterangan=Bukti hidup"
q "SELECT CONCAT(platform,' / ',status_system,' -> ',status_internal) FROM arj.carrier_status_mappings WHERE status_system='CANCELLED'"

echo "== POST /pemetaan-status/sinkron (terapkan ke resi lama) =="
TS="$(curl -s -b "$J" -c "$J" "$B/pemetaan-status" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
curl -s -b "$J" -c "$J" -o /dev/null -w "http=%{http_code}\n" -X POST "$B/pemetaan-status/sinkron" --data "_token=$TS"
echo "MR9002 sekarang: $(q "SELECT IFNULL(status_internal,'MASIH NULL') FROM arj.shipments WHERE tracking_id='MR9002'")"
echo "issue status_unmapped: open=$(q "SELECT COUNT(*) FROM arj.data_issues WHERE type='status_unmapped' AND status='open'") resolved=$(q "SELECT COUNT(*) FROM arj.data_issues WHERE type='status_unmapped' AND status='resolved'") "

# ---------- 4) Proses ulang baris error ----------
BATCH="$(q "SELECT id FROM arj.import_batches WHERE error_rows > 0 ORDER BY id DESC LIMIT 1")"
if [ -z "$BATCH" ]; then
  echo "(tidak ada batch ber-error — jalankan tools/http-import.sh dulu)"; else
  echo "== POST /impor/$BATCH/proses-ulang =="
  T4="$(curl -s -b "$J" -c "$J" "$B/impor/$BATCH" | grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')"
  curl -s -b "$J" -c "$J" -o /dev/null -w "http=%{http_code} redirect=%{redirect_url}\n" \
    -X POST "$B/impor/$BATCH/proses-ulang" --data "_token=$T4"
  q "SELECT CONCAT('batch #',id,' status=',status,' baru=',new_rows,' update=',updated_rows,' dup=',duplicate_rows,' error=',error_rows) FROM arj.import_batches WHERE id=$BATCH"
  echo "data_issues terbaru:"
  q "SELECT CONCAT(type,' | ',LEFT(message,60)) FROM arj.data_issues ORDER BY id DESC LIMIT 5"
fi

rm -f "$J"
echo "== selesai =="
