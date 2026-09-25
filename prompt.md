# PRD DAN INSTRUKSI CODING AGENT — WEBSITE OPERASIONAL ARJ

Anda adalah coding agent yang bertugas **membangun aplikasi website ARJ dari awal sampai dapat dijalankan dan diuji**. Jangan berhenti pada analisis, wireframe, pseudocode, atau struktur database saja. Implementasikan backend, frontend, migrasi database, proses impor/ekspor, perhitungan, pengujian, dan dokumentasi menjalankan aplikasi.

## 1. Tujuan produk

Gantikan alur kerja lima workbook berikut dengan satu website dan satu database utama:

1. `ID 01 Admin.xlsx`
2. `Upload Mengantar 2026.xlsx`
3. `ARJ Input Data Marketing 2026(1).xlsx`
4. `Master ARJ.xlsx`
5. `Komisi CS & ADV New.xlsx`

Kelima workbook adalah **referensi aturan bisnis, format data, data awal, dan pembanding hasil**. Setelah website berjalan, operasional tidak boleh bergantung pada Google Sheets, Apps Script, `IMPORTRANGE`, atau file Excel sebagai database.

Website harus membuat alur kerja lebih singkat:

**Input order → deteksi otomatis nomor positif/negatif → unduh order positif untuk platform pengiriman → impor hasil pengiriman → perbarui resi dan status → rekap ADV → dashboard → hitung dan catat komisi.**

Mengantar dan Lincah adalah platform di luar website. Selama belum tersedia API yang disetujui, pertukaran data dengan kedua platform dilakukan lewat file ekspor dan impor.

## 2. Stack teknologi

Gunakan:

* **Backend:** PHP 8.4 dan Laravel 13.
* **Database:** MYSQL AJA.
* **Panel operasional:** Pakai Ant Design
* **Pekerjaan latar belakang:** Laravel Queue dengan Redis untuk impor file besar dan perhitungan ulang.
* **File:** penyimpanan privat untuk file impor asli, hasil ekspor, dan laporan error.
* **Deployment:** Nginx, PHP-FPM, PostgreSQL, Redis, queue worker, dan scheduler pada VPS Linux.
* **Pengujian:** Pest atau PHPUnit untuk aturan bisnis dan alur penting.

Pastikan versi paket yang dipasang saling kompatibel berdasarkan dokumentasi resminya. Gunakan satu aplikasi Laravel; jangan menambah frontend terpisah atau microservice jika kebutuhan ini dapat ditangani dengan baik oleh stack di atas.

## 3. Pengguna dan hak akses

Sediakan login serta hak akses sekurang-kurangnya untuk:

* **Superadmin:** seluruh modul, pengaturan, pengguna, koreksi data, dan audit.
* **Admin Order:** input order, melihat hasil validasi, ekspor order positif.
* **Admin Pengiriman:** impor data Mengantar/Lincah, melihat dan menindaklanjuti error pengiriman.
* **Marketing/ADV:** input atau impor data kampanye dan melihat rekap sesuai akses.
* **Finance/Owner:** dashboard keuangan, aturan komisi, persetujuan koreksi, pembayaran komisi, dan laporan.

Hak akses harus ditegakkan di backend, termasuk untuk ekspor file dan data finansial.

## 4. Alur A — Input dan validasi order

Bangun formulir order berdasarkan kolom operasional dalam `ID 01 Admin.xlsx`, termasuk tanggal order, kode CS, identitas pelanggan, nomor telepon, alamat, kelurahan/kecamatan/kota/provinsi/kode pos, produk, jumlah, jenis pembayaran, harga, berat, agregator, dan ekspedisi jika diperlukan.

Ketika nomor telepon dimasukkan atau order disimpan:

1. Normalisasi nomor secara konsisten tanpa menghilangkan identitas aslinya.
2. Cari riwayat nomor pada database website.
3. Terapkan **aturan positif/negatif yang benar-benar digunakan dalam workbook**, termasuk pengaruh riwayat diterima, retur, dan order yang masih diproses.
4. Tampilkan hasil **Positif**, **Negatif**, atau **Perlu Ditinjau**, dengan alasan dan data riwayat yang mendasarinya.
5. Simpan hasil validasi beserta versi aturan yang digunakan dan waktu pemeriksaan.
6. Order negatif tidak masuk antrean ekspor positif. Sediakan tindakan koreksi atau peninjauan yang tercatat dalam audit log.

Jangan membuat aturan sederhana seperti “pernah retur berarti selalu negatif” sebelum membuktikannya dari rumus dan contoh data workbook.

## 5. Alur B — Ekspor order positif

Buat halaman **Siap Ekspor** dengan filter tanggal, agregator, CS, produk, dan status ekspor.

* Hanya order yang memenuhi validasi positif dan memiliki data wajib lengkap yang dapat diekspor.
* Sediakan unduhan sesuai format aktual **Mengantar** dan **Lincah** berdasarkan tab `OutputPositif-Mengantar` dan `OutputPositif-Lincah`.
* Simpan `export_batch_id`, platform tujuan, daftar order, pengguna, waktu, nama file, dan status ekspor.
* Ekspor ulang harus dimungkinkan oleh pengguna berwenang, tetapi ditandai jelas agar order tidak tidak sengaja diproses dua kali.
* Sediakan pratinjau jumlah order dan validasi kolom sebelum file diunduh.

## 6. Alur C — Impor hasil Mengantar dan Lincah

Buat halaman impor dengan tahap **unggah → baca file → validasi → pratinjau → konfirmasi → proses → laporan hasil**.

Acuan Mengantar adalah `PasteMengantar` dan `DBMengantar` dalam `Upload Mengantar 2026.xlsx`. Pelajari pula format Lincah yang tersedia pada workbook. Jika format ekspor Lincah lengkap tidak ada dalam lampiran, buat adapter yang siap diisi dan tandai secara jelas bahwa contoh file asli diperlukan untuk finalisasi pemetaan.

Untuk setiap baris, bedakan:

* Order/resi baru.
* Pembaruan order/resi yang sudah ada.
* Baris identik yang pernah diimpor.
* Resi ganda atau identitas bertentangan.
* Order yang tidak ditemukan.
* Kolom wajib kosong atau format tidak valid.
* Status platform yang belum mempunyai pemetaan internal.

Impor file yang sama dua kali **tidak boleh** menggandakan order, resi, biaya, atau komisi. Simpan file sumber, identitas batch, nomor baris, data asli, hasil pemetaan, dan alasan error. Baris error bisa diperbaiki dan diproses ulang tanpa mengulang baris yang sudah berhasil.

Pertahankan riwayat status, tanggal status, biaya ongkir, diskon ongkir, COD fee, return fee, dan informasi relevan lain. Jangan menimpa riwayat lama hanya untuk menampilkan status terkini.

## 7. Alur D — Master resi dan status

Website harus memiliki **master order dan resi** yang menggantikan pekerjaan `DBMengantar` dan `OutputResi`.

* Simpan Order ID internal, Order ID platform, nomor tracking/resi, nomor resi retur bila ada, platform, ekspedisi, pelanggan, nomor telepon, CS, ADV, produk, nilai order, dan biaya.
* Simpan status asli platform serta status internal yang digunakan laporan.
* Pemetaan status Mengantar/Lincah ke **Packing, Dikirim, Undel, Diterima, Retur** harus mengikuti workbook dan dapat ditinjau oleh admin berwenang.
* Tentukan status terkini dari riwayat berdasarkan aturan waktu dan prioritas yang didokumentasikan; impor data lama tidak boleh sembarangan membalik status baru.
* Nomor telepon, Order ID, dan resi disimpan sebagai string, bukan angka. Pertahankan nol di awal dan jangan mengubah digit panjang.
* Tampilkan halaman detail order dengan linimasa ekspor, impor, perubahan status, koreksi, dan sumber setiap nilai.

## 8. Alur E — Data marketing dan rekap ADV

Bangun impor laporan kampanye berdasarkan `ARJ Input Data Marketing 2026(1).xlsx`.

Sediakan master ADV, CS, produk, kampanye, serta pemetaan kode dari nama kampanye. Masukkan data biaya iklan dan metrik yang dipakai oleh `RekapADV (All)` serta `RekapADVtoResi (All)` pada `Master ARJ.xlsx`.

Website harus menampilkan:

* Kampanye yang berhasil dipetakan ke ADV, CS, dan produk.
* Kampanye dengan kode tidak dikenal.
* Data ADV tanpa resi terkait.
* Resi tanpa data kampanye/ADV yang diperlukan.
* Rekap menurut tanggal, periode, ADV, CS, produk, dan status.

Jangan menyembunyikan data yang gagal dicocokkan sebagai angka nol yang terlihat valid.

## 9. Alur F — Dashboard dan komisi

Dashboard mengambil data dari database dan logika perhitungan yang sama dengan halaman detail. Sediakan filter tanggal, ADV, CS, produk, agregator, ekspedisi, dan status. Kartu ringkasan serta tabel detail harus saling cocok.

Minimal tampilkan jumlah order positif/negatif, order per status, diterima, retur, spend iklan, biaya pengiriman, nilai penjualan, dan metrik laba yang memang dapat direplikasi dari workbook.

Bangun laporan **komisi CS dan ADV** berdasarkan `Setup Komisi CS` dalam `Master ARJ.xlsx` serta `Komisi CS & ADV New.xlsx`:

* Jumlah order dan nilai komisi menurut Packing, Dikirim, Undel, Diterima, dan Retur.
* Komponen komisi order, transfer, ongkir, dan potongan sesuai rumus sumber.
* Komisi on progress, komisi closed/payable, saldo periode sebelumnya, pembayaran, dan sisa belum dibayar.
* Pencatatan tanggal, jumlah, dan referensi pembayaran.
* Riwayat perubahan tarif komisi dengan tanggal berlaku agar perubahan tarif tidak diam-diam mengubah periode yang sudah ditutup.

**Periode CS dan ADV tidak selalu sama.** Contoh workbook menunjukkan periode CS tanggal 16–15, sedangkan laporan ADV dapat memakai bulan kalender. Tampilkan periode dan definisinya secara eksplisit pada setiap laporan.

Jika “komisi EDC” adalah perhitungan terpisah, telusuri sumber dan rumusnya pada file serta dokumentasi yang tersedia. Jangan membuat nominal atau formula EDC berdasarkan tebakan; tampilkan sebagai item aturan yang memerlukan verifikasi apabila sumbernya tidak ditemukan.

## 10. Rancangan database minimum

Rancang relasi dan migrasi untuk entitas berikut, lalu sesuaikan berdasarkan analisis data nyata:

`users`, `roles/permissions`, `customers`, `orders`, `order_items`, `products`, `cs_agents`, `advertisers`, `campaigns`, `marketing_daily_reports`, `shipments`, `shipment_status_events`, `carrier_status_mappings`, `export_batches`, `export_batch_items`, `import_batches`, `import_rows`, `data_issues`, `commission_rules`, `commission_periods`, `commission_entries`, `commission_payments`, dan `audit_logs`.

Gunakan indeks untuk nomor telepon ternormalisasi, Order ID, nomor resi, tanggal, kode CS, kode ADV, serta kunci yang digunakan dalam pencocokan. Terapkan batas unik sesuai makna datanya; jangan berasumsi satu nomor telepon hanya boleh memiliki satu order.

## 11. UI dan pengalaman penggunaan

Buat UI berbahasa Indonesia, responsif, cepat, dan mudah dipakai staf operasional. Prioritaskan:

* Navigasi jelas: Dashboard, Order, Siap Ekspor, Impor Pengiriman, Master Resi, Data Error, Marketing, Komisi, dan Pengaturan.
* Pencarian berdasarkan nomor telepon, Order ID, dan resi.
* Indikator positif/negatif dengan alasan yang mudah dibaca.
* Pratinjau dan ringkasan hasil setiap impor.
* Tabel yang dapat difilter tanpa memuat seluruh data sekaligus.
* Format rupiah dan tanggal Indonesia.
* Konfirmasi untuk koreksi yang berdampak pada laporan atau komisi.

## 12. Tahapan wajib untuk coding agent

**Tahap 1 — Audit sumber.** Baca lima workbook. Buat dokumen ringkas yang memetakan tab, kolom, rumus penting, kode, status, alur data, dan bagian yang belum dapat dipastikan dari salinan Excel. Jangan menganggap hasil cache formula Excel sebagai bukti rumus pasti berjalan.

**Tahap 2 — Fondasi aplikasi.** Buat proyek Laravel, migrasi, model, autentikasi, hak akses, seed data referensi, dan UI dasar.

**Tahap 3 — Alur order.** Selesaikan input, pemeriksaan nomor, klasifikasi, daftar positif/negatif, dan ekspor Mengantar/Lincah.

**Tahap 4 — Pengiriman.** Selesaikan impor bertahap, pencocokan, pemutakhiran master resi, status, riwayat, serta Data Error.

**Tahap 5 — Marketing dan laporan.** Selesaikan impor kampanye, rekap ADV ke resi, dashboard, komisi, dan pembayaran.

**Tahap 6 — Migrasi dan verifikasi.** Siapkan skrip migrasi data awal dari workbook. Uji sejumlah contoh nyata dari setiap cabang proses dan bandingkan hasil website dengan workbook. Laporkan setiap selisih beserta penyebabnya.

Pada akhir setiap tahap, jalankan aplikasi, tunjukkan halaman yang sudah bekerja, dan lanjutkan tahap berikutnya. Jangan berhenti setelah membuat scaffolding atau halaman demo dengan data statis.

## 13. Kriteria penerimaan

Pekerjaan dinyatakan selesai jika:

1. Admin dapat input order dan langsung melihat klasifikasi nomor beserta alasan yang dapat ditelusuri.
2. Hanya order positif dan lengkap yang masuk ekspor Mengantar/Lincah.
3. File hasil platform dapat diimpor ulang tanpa duplikasi.
4. Resi baru dan perubahan status memperbarui master serta riwayat dengan benar.
5. Baris bermasalah muncul di Data Error dan dapat diperbaiki.
6. Rekap ADV, dashboard, dan komisi mengambil data operasional yang sama dan totalnya dapat direkonsiliasi.
7. Periode komisi CS dan periode ADV tidak tercampur.
8. Akses finansial dan koreksi data dibatasi sesuai peran.
9. Ada pengujian otomatis untuk aturan klasifikasi nomor, impor berulang, pemetaan status, batas periode, serta perhitungan komisi.
10. Repository berisi instruksi instalasi, konfigurasi environment, migrasi, queue worker, scheduler, backup, dan langkah deployment ke VPS.
11. Tidak ada ketergantungan operasional pada lima workbook setelah migrasi selesai.

Jika ada rumus atau aturan yang tidak dapat dipastikan dari file, dokumentasikan **lokasi sumber, contoh data, dampak pada hasil, dan keputusan yang diperlukan**. Implementasikan bagian lain yang sudah jelas sambil menjaga aturan yang belum terverifikasi agar tidak menghasilkan angka finansial yang keliru.
