# Runbook Migrasi Tahap 6 — dari Workbook ke Panel

Dokumen ini adalah prosedur **satu kali** untuk memindahkan seluruh data
operasional dari kelima workbook Excel ke basis data panel. Setelah selesai,
workbook hanya menjadi jejak sejarah (syarat penerimaan #11).

Prinsip yang dipegang:

1. **Data nyata apa adanya.** Baris yang bermasalah (status tak terpetakan,
   remark `XX`, sel rusak) tetap diimpor dan/atau dicatat ke worklist — tidak
   ada data yang dibuang diam-diam.
2. **Idempoten.** Semua perintah aman diulang; baris identik dikenali sebagai
   *duplicate*, bukan digandakan.
3. **Mesin produksi.** Migrasi memakai rantai impor yang sama dengan impor
   harian (ImportBatch → ImportRow → ShipmentImporter / MarketingImporter),
   jadi perilakunya identik dengan operasi sehari-hari.
4. **Setiap selisih dilaporkan** beserta dugaan penyebab (`arj:reconcile`).

## Alur

| # | Langkah | Perintah |
| --- | --- | --- |
| 1 | Ekspor CSV dari workbook | `tools/xlsx-to-csv.ps1` (PowerShell) |
| 2 | Reset + data referensi | `php artisan migrate:fresh --seed --force` |
| 3 | Master ADV/CS/Produk | `php artisan arj:migrate-masters` |
| 4 | Laporan kampanye Meta | `php artisan arj:migrate-campaigns` |
| 5 | Resi mengantar (±52.962) | `php artisan arj:migrate-shipments --limit=0` |
| 6 | Rekonsiliasi | `php artisan arj:reconcile` |

Semua perintah `php artisan arj:*` dijalankan dari direktori aplikasi
(repo root di WSL, atau container `app` di Docker). Opsi `--file` menerima
path absolut maupun relatif terhadap direktori aplikasi.

## 1. Ekspor CSV (di Windows, dari root repo)

```powershell
& tools\xlsx-to-csv.ps1 -Path 'Upload Mengantar 2026.xlsx' -Sheet 'ADV_CS' -HeaderRow 2 -StartRow 3 -OutFile 'out\migrasi\adv_cs.csv'
& tools\xlsx-to-csv.ps1 -Path 'Upload Mengantar 2026.xlsx' -Sheet 'Produk' -HeaderRow 1 -StartRow 3 -OutFile 'out\migrasi\produk.csv'
& tools\xlsx-to-csv.ps1 -Path 'Upload Mengantar 2026.xlsx' -Sheet 'DBMengantar' -StartRow 2 -DateTimeCols 'P,Q' -OutFile 'out\migrasi\dbmengantar.csv'
& tools\xlsx-to-csv.ps1 -Path 'ARJ Input Data Marketing 2026.xlsx' -Sheet 'Rekap' -HeaderRow 2 -StartRow 3 -DateCols 'A,B,N' -OutFile 'out\migrasi\rekap_marketing.csv'
& tools\xlsx-to-csv.ps1 -Path 'Master ARJ.xlsx' -Sheet 'OutputResi' -StartRow 3 -DateTimeCols 'I,J' -OutFile 'out\migrasi\outputresi.csv'
```

Catatan:

- Eksporter membaca **nilai sel tersimpan (cache rumus) apa adanya** — bukan
  menghitung ulang formula; serial tanggal Excel dikonversi deterministik.
- Header CSV: nama kolom bila `-HeaderRow` diisi; huruf kolom (A, B, …) bila
  tidak — `OutputResi` memang dibaca per huruf kolom oleh `arj:reconcile`.

## 2–3. Reset + master

```bash
php artisan migrate:fresh --seed --force
php artisan arj:migrate-masters --advcs=out/migrasi/adv_cs.csv --produk=out/migrasi/produk.csv
```

Keluaran berupa tabel *Baru / Diperbarui* per entitas (`advertisers`,
`cs_agents`, `products`). Idempoten: jalankan kedua kali → semua *Diperbarui*.

## 4. Laporan kampanye Meta (tab `Rekap`)

```bash
php artisan arj:migrate-campaigns --file=out/migrasi/rekap_marketing.csv --user=superadmin@arj.test
```

Keluaran: jumlah baris `new / update / duplicate / error`. Yang perlu dibaca:

- **update** besar = file berisi beberapa baris untuk kombinasi
  kampanye+tanggal yang sama (baris terakhir menang) — normal untuk Rekap.
- **Worklist `campaign_unmapped`** = kode ADV/CS/Produk pada nama kampanye
  tidak ada di master; kampanye tetap tersimpan dengan `is_mapped=false`.
- **Worklist `metric_normalized`** = sel turunan workbook rusak/kosong —
  lihat bagian *Normalisasi metrik* di bawah.

## 5. Resi DBMengantar (seluruh isi file)

```bash
php artisan arj:migrate-shipments \
  --file=out/migrasi/dbmengantar.csv \
  --advcs=out/migrasi/adv_cs.csv \
  --produk=out/migrasi/produk.csv \
  --limit=0 --user=superadmin@arj.test
```

- `--limit=0` = semua baris. Jalankan di background untuk volume penuh; pantau
  lewat `SELECT COUNT(*) FROM shipments;`.
- Atribusi ADV/CS/Produk: remark numerik 15 digit `AB` dipetakan
  `MID(AB,7,3)`→ADV/CS dan `MID(AB,10,2)`→Produk lewat peta dari CSV `ADV_CS`
  dan `Produk`; format lama (huruf pada remark) tetap didukung.
- Baris dengan status/remark tak terpetakan → Data Error (`status_unmapped`,
  `remark_unmapped`, `double_resi`, `required_missing`) dan tetap tersimpan
  jejaknya; perbaiki dari menu **Data Error** lalu *Proses ulang*.
- **Pemetaan status mengikuti tab `Status (Agregator)`** (56 baris workbook,
  diselaraskan di `CarrierStatusMappingSeeder`). Setelah pemetaan ditambah/diubah,
  buka **Pemetaan Status** → *Sinkronkan ke resi lama*: resi yang status internalnya
  kosong (dan riwayatnya) ikut diperbaiki, worklist `status_unmapped` yang
  bersangkutan otomatis *resolved*.
- Idempoten: impor ulang file yang sama menghasilkan *duplicate*, bukan
  penggandaan (kunci: platform + tracking + status mentah + waktu update).

## 6. Rekonsiliasi OutputResi workbook vs website

```bash
php artisan arj:reconcile --file=out/migrasi/outputresi.csv --limit=300 \
  --out=docs/REKONSILIASI.md
```

- Membandingkan **per resi** enam kolom rantai komisi (AD, V, AG, AJ, AK, AM)
  antara nilai cache workbook dan hasil `CommissionCalculator` website, plus
  **agregat** ΣAK/ΣAM; laporan markdown ditulis ke `--out`.
- Naikkan `--limit`/geser `--offset` untuk memperluas sampel.
- Baris “tidak ditemukan” umumnya berasal dari platform DBLincah yang tidak
  ada salinan workbook-nya (blokir U1 pada audit) — dicatat transparan.

## Normalisasi metrik (mengapa ada worklist `metric_normalized`)

Sebagian sel turunan pada tab `Rekap` **rusak sejak di workbook**: angka format
US yang ditempel ke Excel locale id-ID kehilangan titik desimal
(`26225.64891` tersimpan `2622564891`, rasio ≈ 10^k). Karena itu:

- `MarketingRowMapper` memverifikasi tiap kolom turunan terhadap hitungan dari
  **kolom dasar** (Belanja, Impresi, Jangkauan, Hasil, Klik Tautan, Klik
  Semua) — semua kolom turunan adalah fungsi eksak dari kolom dasar.
- Sel yang menyimpang > 0,1% atau kosong digantikan hasil hitungan
  deterministik; labelnya dicatat di satu worklist `metric_normalized` per
  batch (jumlah baris + rincian per metrik), bukan disembunyikan.
- Baris yang sehat tidak diubah sama sekali (nilai workbook dipertahankan).

## Verifikasi cepat setelah migrasi

```sql
SELECT (SELECT COUNT(*) FROM advertisers) adv,
       (SELECT COUNT(*) FROM cs_agents) cs,
       (SELECT COUNT(*) FROM products) produk,
       (SELECT COUNT(*) FROM campaigns) kampanye,
       (SELECT COUNT(*) FROM campaigns WHERE is_mapped = 1) kampanye_mapped,
       (SELECT COUNT(*) FROM marketing_daily_reports) laporan,
       (SELECT COUNT(*) FROM shipments) resi;
SELECT type, status, COUNT(*) FROM data_issues GROUP BY type, status;
```

## Pasca-migrasi (operasi harian tanpa workbook)

- **Resi harian** → menu **Impor**: unggah CSV DBMengantar (format sama seperti
  ekspor) — mesin impor yang sama dengan migrasi ini.
- **Laporan kampanye Meta** → menu **Marketing**: unggah ekspor tab `Rekap`.
- **Data bermasalah** → menu **Data Error**: periksa, perbaiki, *Proses ulang
  baris error* (tanpa mengulang baris yang sudah sukses).
- **Komisi/fee** → menu **Setup Komisi** (aturan berversi per tanggal) dan
  **Komisi** — perubahan aturan tidak mengubah riwayat yang sudah dihitung.
- Workbook tidak lagi dibutuhkan; simpan sebagai arsip read-only.

## Riwayat eksekusi nyata

| Tanggal | Langkah | Hasil |
| --- | --- | --- |
| 2026-09-24 | `migrate:fresh --seed` | skema + referensi (channel, status, aturan komisi) siap |
| 2026-09-24 | `arj:migrate-masters` | 3 advertisers · 36 cs_agents · 18 products |
| 2026-09-24 | `arj:migrate-campaigns` | 134 kampanye · 2.355 laporan · 0 error |
| 2026-09-24 | `arj:migrate-shipments --limit=0` | **52.962 resi baru · 0 error** · 40.123 resi tertaut kampanye |
| 2026-09-24 | Seeder pemetaan diselaraskan ke workbook (56 status) + *Sinkronkan ke resi lama* | 677 resi otomatis terpetakan (worklist → *resolved*); sisa 9 (CANCELLED, ON TRANSIT — tidak ada di workbook, menunggu keputusan admin) |
| 2026-09-24 | Worklist akhir pasca-migrasi | `status_unmapped` 9 · `campaign_unmapped` 29 · `remark_unmapped` 11 · `metric_normalized` 1 batch (3.904 baris diselamatkan) |
| 2026-09-24 | `arj:reconcile --limit=300` | 186/300 cocok persis · 114 selisih, semua berpola: pembulatan ±0,01 Rp & sel AG kosong di workbook — laporan: `docs/REKONSILIASI.md` |
| 2026-09-24 | Impor **ulang** `dbmengantar.csv` yang sama (bukti idempotensi) | 52.962 baris → **duplicate 52.962 · baru 0 · error 0**; jumlah resi di basis data tetap 52.962 |
