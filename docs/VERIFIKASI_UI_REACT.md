# Verifikasi Penerimaan — UI React/Ant Design (pasca-migrasi Inertia)

Tanggal: 25 Sep 2026 · Lingkungan: WSL2 lokal (`http://localhost:8000`, Vite dev :5173) ·
Login: `superadmin@arj.test` · Dasar: `prompt.md` §13 (Kriteria penerimaan).

Dokumen ini merekam hasil verifikasi **tahap demi tahap** (Tahap 3–5) terhadap UI
baru (Ant Design 5 / Inertia 2 / React 18) setelah seluruh halaman Blade
dikonversi ke React, beserta cacat yang ditemukan dan perbaikannya.

## Metode

1. **Uji browser interaktif** (sub-agen browser): login superadmin, lalu jalankan
   alur nyata per tahap — input order, impor CSV, pratinjau, pemetaan status,
   data error, rekap, dashboard, komisi. Setiap cacat dicatat + bukti konsol.
2. **Pengukuran server-side** (curl + `tools/time-heavy-pages.sh`): waktu respons
   halaman berat tanpa overhead browser/dev-server.
3. **Audit impor JSX** (skrip ad-hoc): bandingkan komponen antd yang dipakai
   dengan yang diimpor di tiap berkas `resources/js/Pages/**`.
4. **Profil komponen** (`tools/profile-rekap.php`): pecah waktu `/rekap-adv`
   menjadi `rangeShipments` / `rows` / `unmatchedShipments`.

## Hasil per tahap

### Tahap 3 — Alur order (input, klasifikasi, daftar, ekspor) — LULUS

| Cek | Hasil |
| --- | --- |
| Input order baru (`/orders/baru`) | LULUS — order tersimpan, klasifikasi Positif muncul. |
| Kartu "Hasil Klasifikasi" setelah submit | LULUS — kartu ber-borders hijau/oranye/merah + alasan + ringkasan by-WA/last-order/retur. |
| Daftar order + filter + agregator | LULUS — tabel render, chips Positif/Negatif/Perlu Ditinjau sesuai. |
| Halaman Siap Ekspor | LULUS — render tanpa error. |

### Tahap 4 — Pengiriman (impor CSV, pratinjau, resi, pemetaan status, data error) — LULUS

| Cek | Hasil |
| --- | --- |
| Impor `tools/sample-mengantar.csv` | LULUS — pratinjau 5 baris (Baru 4, Duplikat 0, Error 1), konfirmasi idempoten ("duplikat: 4" pada impor ulang — tidak menggandakan). |
| Detail resi | LULUS — `/resi/52966`: penerima, ekspedisi, Order ID, COD, kode ADV/CS/Produk, linimasa status (event impor → Packing). |
| Pemetaan status edit + kembalikan | LULUS — ubah `General / AT COUNTER` dikirim→undel→dikirim; kedua simpan notif sukses & persisten. |
| Data Error | LULUS — 54 terbuka (required missing, status/remark tak terpetakan, metrik ternormalisasi, kampanye tak terpetakan) dengan aksi Selesai/Abaikan. |

### Tahap 5 — Marketing + keuangan (impor kampanye, rekap, dashboard, komisi) — LULUS

| Cek | Hasil |
| --- | --- |
| Impor `tools/sample-marketing.csv` | LULUS — pratinjau 4 baris, hasil Baru 4 / Error 0. |
| Rekap ADV (`/rekap-adv`) | LULUS — **layout dimodernisasi**: kartu Total Rentang (ubin statistik + Profit berwarna), Tabs (Rincian/ADV Tanpa Resi/Resi Tak Tercocokkan), preset Segmented. Kode ADV/CS/Produk terisi benar; 302 baris rincian + footer ringkasan. |
| Dashboard + filter tanggal | LULUS — filter 26/08–25/09 mengubah kartu (Total Resi 9.582 → 11.965 dst.) tanpa error. |
| Komisi + Aturan Komisi | LULUS — form periode CS 16–15 & ADV kalender; 9 aturan aktif dengan parameter & tanggal efektif. |

## Cacat ditemukan & perbaikan

| # | Cacat (gejala) | Akar masalah | Perbaikan |
| --- | --- | --- | --- |
| A | **Layout hilang di seluruh aplikasi** (sidebar/header/toast tak pernah render). | `app.jsx` memakai opsi `defaultLayout` yang **tidak didukung** `@inertiajs/react` 2.3.x (hanya `Component.layout`). | Pasang layout di `resolve` (`page.layout ??= (el) => <AppLayout>{el}</AppLayout>`). |
| B | `/orders` blank setelah submit order (kartu Hasil Klasifikasi). | `<Space>` dipakai tanpa impor di `Orders/Index.jsx` → ReferenceError. | Tambah impor `Space`. |
| C | `/marketing` blank total. | Sama — `<Space>` tanpa impor di `Marketing/Index.jsx`. | Tambah impor `Space`. |
| D | Tabel detail finansial dashboard menampilkan "Rp 0". | `nilai` dibungkus JSX `<b>` di dataSource lalu dioper ke `rupiah()` → NaN. | dataSource mentah + `render: (v, r) => r.bold ? <b>{rupiah(v)}</b> : rupiah(v)`. |
| E | Menu **Pemetaan Status** hilang untuk superadmin. | (1) Seeder menyinkronkan permission superadmin **sebelum** permission dibuat (tabel masih kosong pada DB segar); (2) `AppLayout.can()` tanpa bypass superadmin (backend `User::hasPermission()` punya). | Seeder: buat semua permission dulu baru sinkron peran; `can()` + bypass `roles.includes('superadmin')`; DB lokal di-seed ulang (27 permission terpasang). |

Verifikasi ulang pasca-perbaikan: semua 4 cek sebelumnya GAGAL kini LULUS,
termasuk 12 item menu sidebar dan navigasi Pemetaan Status.

## Performa `/rekap-adv` (sebelumnya ±1 menit)

Perjalanan perbaikan (waktu server-side, curl, periode September):

| Versi | Waktu | Catatan |
| --- | --- | --- |
| Awal (sebelum indeks) | ~69 s | 402 baris × query resi per baris + tanpa indeks kolom `create_date`/kode resi. |
| Indeks (migrasi `add_rekap_indexes_to_shipments`) + satu pass `rows()` | ~7 s | 5 indeks baru + `withoutResi` diturunkan dari `rows()`. |
| Batch resi (1 query rentang + indeks memori) | **~5,6 s** | `rangeShipments()` dibagi ke `rows()` & `unmatchedShipments()`; pencocokan per baris via indeks `byCampaign`/`dimMap` + int `Ymd` (tanpa Carbon per elemen); himpunan tuple untuk `unmatchedShipments` (1,1 s → 0,07 s). |

Profil final (`tools/profile-rekap.php`, 9.582 resi bulan berjalan):
`rangeShipments` 0,97 s · `rows` 2,80 s (302 baris, 5.675 resi tercocok) ·
`unmatchedShipments` 0,07 s. Di browser ±6–8 s (termasuk muatan ±0,33 MB setelah
perampingan payload & render 302 baris). Dashboard stabil ±6 s.

> Catatan lingkungan: dev server Vite di `/mnt/c` (drvfs) tidak memancarkan
> event watcher → `server.watch.usePolling: true` wajib. Polling agresif
> (interval kecil + tree besar `vendor/`) membuat VM WSL melambat drastis
> (rekap naik ke ~23 s, pgrep ikut macet) → interval 2000 ms + `ignored`
> `vendor/`, `storage/`, `node_modules/`, `tools/`, `out/`.

## Pemetaan kriteria penerimaan (§13)

| # | Kriteria | Status | Bukti |
| --- | --- | --- | --- |
| 1 | Input order → klasifikasi + alasan tertelusur | ✅ | Kartu Hasil Klasifikasi (Tahap 3) + tes klasifikasi. |
| 2 | Hanya order positif & lengkap masuk ekspor | ✅ | Diverifikasi di sesi sebelumnya (alur ekspor) + tes ekspor. |
| 3 | Impor ulang tanpa duplikasi | ✅ | "duplikat: 4" pada impor ulang + tes idempotensi. |
| 4 | Resi baru & perubahan status memperbarui master + riwayat | ✅ | Detail resi menampilkan linimasa; pemetaan status persisten. |
| 5 | Baris bermasalah muncul di Data Error & dapat diperbaiki | ✅ | 54 baris + aksi Selesai/Abaikan. |
| 6 | Rekap ADV, dashboard, komisi memakai data yang sama & dapat direkonsiliasi | ✅ | Kartu finansial memakai rantai `OutputResi` yang sama (catatan dashboard) + `docs/REKONSILIASI.md`. |
| 7 | Periode komisi CS & ADV tidak tercampur | ✅ | Form terpisah (CS 16–15 vs ADV kalender) + tes batas periode. |
| 8 | Akses finansial & koreksi data sesuai peran | ✅ | Middleware/policy backend + menu difilter peran (Cacat E diperbaiki). |
| 9 | Pengujian otomatis klasifikasi, impor berulang, pemetaan status, batas periode, komisi | ✅ | 55 tes / 394 assertion. |
| 10 | Repositori berisi instruksi instalasi, konfigurasi, migrasi, queue, scheduler, backup, deploy | ✅ | `README.md` (§ Docker/WSL/env/queue/scheduler/backup/VPS). |
| 11 | Tanpa ketergantungan operasional pada lima workbook | ✅ | Operasional lewat panel/CSV (`README.md` "Setelah migrasi"). |

## Batasan yang diketahui (tetap berlaku)

- **U3** klasifikasi telepon: heuristik v1 — perlu tinjauan berkala data nyata.
- **U6** rumus "Komisi CS >1 Paket" belum tertangkap dari sumber — dipertahankan 0
  (bukan tebakan), lihat `docs/STAGE1_AUDIT_SUMBER.md` §10.

## Modernisasi UI/UX (Dashboard, Rekap, Order, Marketing, Komisi) — pasca-migrasi

Permintaan pengguna: dashboard "kurang modern dan rapi" (tetap Ant Design) + lanjut
item coding berikutnya. Semua perubahan tetap antd 5, lolos build + 55/55 tes, dan
diverifikasi browser tanpa error konsol.

**Dashboard (`resources/js/Pages/Dashboard.jsx`)** — ditulis ulang: kartu header
berjudul + Segmented preset (7/30/Bulan Ini) terkontrol & sinkron dengan RangePicker
(menyorot preset aktif dari rentang terpilih, termasuk default awal–akhir bulan),
6 kartu KPI, kartu "Status Resi" (Progress lingkaran close-rate + 5 bar),
"Klasifikasi Order", dan "Finansial" (ubin Statistic + tabel detail), plus Tabs untuk
resi terbaru / laporan / status.

**Rekap ADV** — dua perubahan:

1. **Perampingan payload** (`RekapAdvService::buildRow`): tiap baris semula menyematkan
   **model utuh** `MarketingDailyReport` + `Campaign` (beserta relasi
   advertiser/csAgent/product yang terduplikasi per baris). Diganti array presentasional
   minimal (`report.{id,date_start,date_end}`, `campaign.{id,name,is_mapped,
   advertiser.code/name, cs_agent.code, product.code}`) + buang `spend_raw` tak dipakai.
   Efek: muatan HTML `/rekap-adv` **±2 MB → ±328 KB** (335.617 B terukur
   `tools/time-heavy-pages.sh`), ~6× lebih kecil. Perbaikan bonus: kolom kode
   ADV/CS/Produk kini tampil benar (kunci `cs_agent` snake_case eksplisit).
2. **Layout modern** (`Marketing/Rekap.jsx`): header + Segmented preset, kartu Total
   Rentang jadi ubin statistik responsif (Profit hijau/merah), dan tiga tabel
   digabung ke **Tabs** (Rincian Laporan / ADV Tanpa Resi / Resi Tak Tercocokkan) dengan
   footer ringkasan tabel — memangkas scroll vertikal.

### Lanjutan — konsistensi halaman daftar (Order, Marketing, Komisi)

Pola Dashboard/Rekap dibawa ke tiga halaman operasional utama agar sepanel seragam.
Semua lolos build + 55/55 tes dan diverifikasi browser (viewport ±1280px) tanpa error
konsol.

- **Komponen bersama baru** `resources/js/components/StatCard.jsx` — ubin statistik
  borderless berlatar lembut (`#fafafa`) + ikon prefix, dipakai ulang di semua halaman
  daftar (menggantikan `<Statistic>` telanjang).
- **Order** (`Orders/Index.jsx`): kartu header (judul + deskripsi + tombol aksi
  "Input Order Baru" → `/orders/baru`), 3 tile StatCard klasik (Positif/Negatif/Perlu
  Ditinjau, ikon + warna).
- **Marketing** (`Marketing/Index.jsx`): header + tombol hantu "Rekap ADV", 5 tile
  StatCard, dan **filter status 3 tombol → Segmented** (Semua / Terpetakan / Kode tak
  dikenal) yang menyetel `?status=` dan memicu ulang tabel.
- **Komisi** (`Komisi/Index.jsx`): header + tombol hantu "Aturan Komisi", dan
  jendela periode CS (16–15) vs ADV (kalender) ditampilkan sebagai dua tile StatCard
  berdampingan.

> Catatan antd: pada header dengan deskripsi panjang, aksi top-right sempat melorot ke
> baris kedua karena `Row wrap` memotong baris **sebelum** penyusutan flex (`Col
> flex="auto"` → basis = max-content). Solusi: `wrap={false}` + `Col flex={1}
> style={{ minWidth: 0 }}` sehingga deskripsi wrap internal dan tombol tetap rata kanan.
> Pola ini kini dipakai seragam di **semua** header halaman hasil poles di bawah.

## Adopsi alur kerja agen Ant Design (`antd` CLI) + poles seluruh halaman

Permintaan pengguna: ikuti prosedur "for agents" antd — baca
`docs/react/for-agents.md` + `SKILL.md`, perhatikan deprecation, dan pakai
`@ant-design/cli` untuk memverifikasi terhadap versi antd **nyata** proyek.

**Versi terpasang (terverifikasi, bukan asumsi):** `antd 5.29.3` (deklarasi
`package.json` `^5.21.0` resolve lebih tinggi), `react 18.3.1`,
`@ant-design/icons 5.6.1`, `dayjs 1.11.23`. Ini mengesahkan pemakaian API modern:
`Card variant="borderless"` (≥5.24, ganti `bordered`), `Card styles={{ body }}`
(≥5.14, ganti `bodyStyle`/`headStyle`), `Tabs items` (bukan `TabPane`) — **nol
prop deprecated** di seluruh `resources/js`.

**Menjalankan CLI:** `@ant-design/cli` butuh **Node ≥20** (dependensi `oxc-parser`
memakai binding native `@oxc-parser/binding-linux-x64-gnu`; Node sistem proyek
18.19.1 menolaknya). Solusi non-invasif: Node 22.23.3 dipasang **user-local via
nvm** (`tools/setup-node22.sh`) khusus untuk CLI — **default Node proyek tetap 18**
sehingga build/tes tidak berubah. `tools/antd-run.sh` me-`nvm use 22`, memasang
`@ant-design/cli` sekali ke `~/.antd-cli` (`--include=optional` agar binding native
ikut), lalu mengeksekusi bin-nya. Panggil:
`wsl bash tools/antd-run.sh lint ./resources/js --format json`.

**Baseline `antd lint`:** awalnya 8 peringatan *usage* (0 deprecated) — semuanya
API umpan-balik statis (`message.success/error` di `AppLayout`, `Modal.confirm` di
4 halaman) yang **tidak membaca konteks `ConfigProvider`**. Diperbaiki sesuai
rekomendasi resmi: root dibungkus `<App>` antd (`app.jsx`: `ConfigProvider` →
`AntdApp` → Inertia) dan tiap pemakaian dipindah ke hook **`App.useApp()`**
(`const { message } = App.useApp()` di `AppLayout`; `const { modal } =
App.useApp()` → `modal.confirm(...)` di `Marketing/Preview`, `Imports/Preview`,
`CarrierMappings/Index`, `Komisi/Show`). Hasil: **`antd lint` = 0 issues**
(total/deprecated/usage/a11y/performance semua 0).

**Poles halaman tersisa** (pola header kartu + `StatCard` + aksi/kanan konsisten
dibawa ke seluruh halaman operasional agar seragam dengan Dashboard/Rekap/Order/
Marketing/Komisi):

- **Shipments**: `Shipments/Index.jsx` (header + tile "Total Resi"),
  `Shipments/Show.jsx` (header + tombol "Kembali ke Master Resi" + 4 tile uang:
  Ongkir net / Return Fee / COD / Nilai Produk; field duplikat di grid info dibuang).
- **Issues/Data Error** (`Issues/Index.jsx`): header + deskripsi + tile "Terbuka"
  (merah) & "Total"; filter di bawah dalam kartu.
- **Exports** (`Exports/Index.jsx`): header + tile "Siap Mengantar" / "Siap Lincah"
  / "Riwayat Batch"; filter tanggal di bawah.
- **Imports**: `Imports/Index.jsx` (header + tile Total Batch / Baru Diproses /
  Baris Error), `Imports/Preview.jsx` (header + "Kembali ke Impor"; 3 `<Statistic>`
  → `StatCard` berwarna).
- **Marketing/Preview.jsx**: header + "Kembali ke Marketing"; 4 `<Statistic>` →
  `StatCard`; tombol "Kembali" redundan di cabang done dibuang (primary → "Lihat
  Rekap ADV").
- **CarrierMappings/Index.jsx**: header + tile "Total Pemetaan" & "Belum
  Terpetakan"; daftar pemetaan dipisah ke kartu "Daftar Pemetaan" sendiri.
- **Orders Create/Show**: header + tombol kanan "Kembali ke Daftar Order".

**Verifikasi akhir:** `npm run build` hijau (3799 modul), `antd lint` 0 issues,
`php artisan test` **55 passed / 394 assertion**, dan sub-agen browser
(viewport 1280px) mengonfirmasi semua header halaman poles render satu baris
(judul kiri, tile/aksi kanan) tanpa wrap janggal dan **tanpa error konsol** di
`/resi`, `/resi/{id}`, `/data-error`, `/ekspor`, `/impor`, `/pemetaan-status`,
`/orders/baru`, `/orders`, `/orders/{id}`.
