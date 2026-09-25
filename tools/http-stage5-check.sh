#!/usr/bin/env bash
# Bukti hidup Tahap 5 (Alur E/F + Dashboard §9):
#   1. Impor laporan kampanye marketing (sample-marketing.csv) + worklist kode tak dikenal
#   2. Rekap ADV↔resi (RekapADVtoResi): baris ter-match resi + laba/komisi/profit
#   3. Komisi CS periode 16–15 (buka + hitung + catat pembayaran)
#   4. Komisi ADV periode bulan kalender
#   5. Halaman inti + filter dashboard
# Jalankan di WSL sebagai root: bash tools/http-stage5-check.sh
B="${1:-http://127.0.0.1:8000}"
DIR=/mnt/c/Users/baiqs/Videos/panel.arjunanstore.com
MCSV="$DIR/tools/sample-marketing.csv"
SCSV="$DIR/tools/sample-mengantar.csv"
J="$(mktemp)"
q() { MYSQL_PWD=secret mysql -h127.0.0.1 -uarj -N -e "$1" 2>/dev/null; } # TCP: socket /run/mysqld sering hilang di WSL
token() { grep -oE 'name="_token"[^>]*' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/'; }

T="$(curl -s -c "$J" "$B/login" | token)"
curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/login" \
  --data "_token=$T" --data "email=superadmin@arj.test" --data "password=change-me-please"
echo "== login superadmin OK =="

# ---------- 0) Resi Tahap 4 sebagai bahan pencocokan rekap ----------
if [ "$(q 'SELECT COUNT(*) FROM arj.shipments')" = "0" ]; then
  echo "== impor sample-mengantar.csv dulu =="
  T="$(curl -s -b "$J" "$B/impor" | token)"
  LOC="$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{redirect_url}' -X POST "$B/impor" \
    -F "_token=$T" -F "platform=mengantar" -F "file=@$SCSV;type=text/csv")"
  RID="$(printf '%s' "$LOC" | grep -oE '[0-9]+$')"
  T="$(curl -s -b "$J" "$B/impor/$RID" | token)"
  curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/impor/$RID/proses" --data "_token=$T"
  echo "batch resi #$RID diproses; shipments=$(q 'SELECT COUNT(*) FROM arj.shipments')"
fi

# ---------- 1) Impor kampanye marketing ----------
echo "== GET /marketing =="
curl -s -b "$J" -o /tmp/mk.html -w "http=%{http_code}\n" "$B/marketing"
grep -oE 'Kampanye &amp; Laporan Iklan|Impor Laporan Kampanye' /tmp/mk.html | sort -u
T="$(token < /tmp/mk.html)"
LOC="$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{redirect_url}' -X POST "$B/marketing/impor" \
  -F "_token=$T" -F "file=@$MCSV;type=text/csv")"
MB="$(printf '%s' "$LOC" | grep -oE '[0-9]+$')"
echo "batch marketing #$MB (pratinjau http=$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$B/marketing/impor/$MB"))"
T="$(curl -s -b "$J" "$B/marketing/impor/$MB" | token)"
curl -s -b "$J" -c "$J" -o /dev/null -X POST "$B/marketing/impor/$MB/proses" --data "_token=$T"
q "SELECT CONCAT('batch #',id,' total=',total_rows,' baru=',new_rows,' dup=',duplicate_rows,' error=',error_rows) FROM arj.import_batches WHERE id=$MB"
echo "-- kampanye --"
q "SELECT CONCAT(name,' mapped=',is_mapped) FROM arj.campaigns ORDER BY name"
echo "-- worklist campaign_unmapped (tidak disembunyikan) --"
q "SELECT CONCAT(c.name,' -> ',IFNULL(CONCAT(i.status,' (',LEFT(i.message,40),')'),'TANPA ISSUE')) FROM arj.campaigns c LEFT JOIN arj.data_issues i ON i.campaign_id=c.id AND i.type='campaign_unmapped' WHERE c.is_mapped=0"

# ---------- 2) Rekap ADV↔resi ----------
echo "== GET /rekap-adv?from=2026-06-01&to=2026-06-30 =="
curl -s -b "$J" -o /tmp/rk.html -w "http=%{http_code}\n" "$B/rekap-adv?from=2026-06-01&to=2026-06-30"
grep -oE 'Rekap ADV ↔ Resi|Worklist — Resi Tanpa Data|Total Rentang|Profit' /tmp/rk.html | sort -u
echo "kemunculan baris kampanye AM01-GL01-PG di halaman: $(grep -c 'AM01-GL01-PG' /tmp/rk.html)"

# ---------- 3) Komisi CS (jendela 16–15) ----------
CS_ID="$(q "SELECT id FROM arj.cs_agents WHERE lookup_key='GL01' LIMIT 1")"
echo "== POST /komisi/periode CS (GL01, Juni 2026 = 16/05–15/06) =="
T="$(curl -s -b "$J" "$B/komisi" | token)"
LOC="$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{redirect_url}' -X POST "$B/komisi/periode" \
  --data "_token=$T" --data "owner_type=cs" --data "owner_id=$CS_ID" --data "year=2026" --data "month=6")"
PID="$(printf '%s' "$LOC" | grep -oE '[0-9]+$')"
q "SELECT CONCAT('periode #',id,' ',label) FROM arj.commission_periods WHERE id=$PID"
q "SELECT CONCAT('entri=',COUNT(*),' payable=',SUM(is_payable=1),' total=Rp',ROUND(SUM(amount),2)) FROM arj.commission_entries WHERE commission_period_id=$PID"
echo "== GET /komisi/periode/$PID =="
curl -s -b "$J" -o /tmp/km.html -w "http=%{http_code}\n" "$B/komisi/periode/$PID"
grep -oE 'Ringkasan|Entri Komisi|Pembayaran|Catat Pembayaran' /tmp/km.html | sort -u
echo "== POST bayar Rp 1.000 =="
T="$(token < /tmp/km.html)"
curl -s -b "$J" -c "$J" -o /dev/null -w "http=%{http_code}\n" -X POST "$B/komisi/periode/$PID/bayar" \
  --data "_token=$T" --data "paid_date=2026-06-20" --data "amount=1000" --data "reference=BUKTI-HIDUP"
q "SELECT CONCAT('dibayar=Rp',SUM(amount)) FROM arj.commission_payments WHERE commission_period_id=$PID"

# ---------- 4) Komisi ADV (bulan kalender) ----------
ADV_ID="$(q "SELECT id FROM arj.advertisers WHERE code='AM01' LIMIT 1")"
echo "== POST /komisi/periode ADV (AM01, Juni 2026 = 01/06–30/06) =="
T="$(curl -s -b "$J" "$B/komisi?owner_type=adv" | token)"
LOC="$(curl -s -b "$J" -c "$J" -o /dev/null -w '%{redirect_url}' -X POST "$B/komisi/periode" \
  --data "_token=$T" --data "owner_type=adv" --data "owner_id=$ADV_ID" --data "year=2026" --data "month=6")"
AID="$(printf '%s' "$LOC" | grep -oE '[0-9]+$')"
q "SELECT CONCAT('periode #',id,' ',label,' | entri=',(SELECT COUNT(*) FROM arj.commission_entries e WHERE e.commission_period_id=$AID)) FROM arj.commission_periods WHERE id=$AID"

# ---------- 5) Halaman inti + filter ----------
echo "== halaman inti Tahap 5 =="
for P in "marketing" "rekap-adv?from=2026-06-01&to=2026-06-30" "komisi?owner_type=cs&year=2026&month=6" "komisi?owner_type=adv&year=2026&month=6" "aturan-komisi" "dashboard?from=2026-06-01&to=2026-06-30" "dashboard?from=2026-06-01&to=2026-06-30&status=diterima" "dashboard?from=2026-06-01&to=2026-06-30&platform=mengantar&expedition=JNE"; do
  printf "GET /%-58s -> %s\n" "$P" "$(curl -s -b "$J" -o /dev/null -w '%{http_code}' "$B/$P")"
done
curl -s -b "$J" -o /tmp/db.html "$B/dashboard?from=2026-06-01&to=2026-06-30"
grep -oE 'Finansial — Kartu vs Detail|Resi — Kartu Status|Profit = Laba Kotor − Komisi CS − Spend|Spend Iklan' /tmp/db.html | sort -u
rm -f "$J"
echo "== selesai =="
