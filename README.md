# Panel ARJ — arjunanstore

Panel operasional pengiriman & keuangan **arjunanstore**: master resi (platform
`mengantar` / `lincah`), biaya, aturan komisi CS/ADV, laporan iklan Meta per
kampanye, ekspor laporan, serta worklist **Data Error** untuk data yang perlu
perbaikan manusia.

> **Status: Tahap 1–6 selesai.** Seluruh data kelima workbook sudah dimigrasikan
> (52.962 resi, master, laporan kampanye, rekonsiliasi) dan panel beroperasi
> **tanpa workbook** — impor harian lewat panel/CSV. Runbook:
> [docs/MIGRASI.md](docs/MIGRASI.md), laporan [docs/REKONSILIASI.md](docs/REKONSILIASI.md),
> audit sumber [docs/STAGE1_AUDIT_SUMBER.md](docs/STAGE1_AUDIT_SUMBER.md),
> verifikasi penerimaan UI React [docs/VERIFIKASI_UI_REACT.md](docs/VERIFIKASI_UI_REACT.md).

**Stack:** PHP 8.3+ (dev 8.3 WSL, Docker 8.4) · Laravel 13 · MySQL 8.0 · Redis —
UI **Ant Design 5** via **Inertia 2 + React 18 + Vite** (SPA render server-side;
build npm wajib, hasilnya di `public/build`).

## Struktur repositori

Repo root **adalah** aplikasi Laravel (struktur standar, tanpa generate/overlay):

| Path | Isi |
| --- | --- |
| `app/`, `bootstrap/`, `config/`, `routes/` | Kode aplikasi: model, service, controller, middleware, bootstrap & konfigurasi. |
| `database/` | Migrasi + seeder (skema bisnis `2026_01_01_0001xx_*` di atas skema bawaan). |
| `resources/js/` | UI panel: halaman React/antd di `Pages/`, kerangka `Layouts/AppLayout.jsx`, label `lib/constants.js` (entry `app.jsx`). |
| `resources/views/` | Hanya `app.blade.php` — root Inertia (HTML shell). |
| `tests/` | 55 tes / 394 assertion (Feature + Unit, DB `arj_test`). |
| `public/`, `artisan` | Entry web & CLI. |
| `composer.json`, `composer.lock`, `.env.example`, `phpunit.xml` | Manifest dependensi (sumber kebenaran), env contoh, konfigurasi tes. |
| `entrypoint.sh` | Boot container Docker: `.env` + APP_KEY + tunggu MySQL + migrasi/seed + serve. |
| `docker/`, `docker-compose.yml` | Deployment (app, worker, scheduler, mysql 8.0, redis 7). |
| `tools/` | Skrip operasional: bootstrap lokal WSL, service WSL, `xlsx-to-csv.ps1`, cek HTTP. |
| `docs/` | Runbook migrasi, rekonsiliasi, audit sumber. |
| `out/` | Artefak: `out/audit/` (dump audit Tahap 1), `out/migrasi/` (CSV + skrip sekali pakai Tahap 6). |
| `*.xlsx`, `prompt.md` | Workbook sumber & spesifikasi — jejak sejarah, bukan alat operasional. |

## Menjalankan — Docker (disarankan)

```bash
# Di direktori repo (host apa pun dengan Docker + Compose v2):
docker compose up -d --build
# Aplikasi: http://localhost:8000  (WEBPORT/APP_PORT utk ganti port → .env / env vars)
```

Image dibangun dari repo root (`COPY . .`; `vendor/` dipasang dari
`composer.lock` saat build, aset frontend dibangun di tahap `node:22-alpine`
(`npm ci && npm run build` → `public/build`), `storage/` diganti volume
`app_data`). Boot pertama `entrypoint.sh` menjalankan `migrate:fresh --seed`;
boot berikutnya hanya `migrate --force` (ditandai `storage/.arj-installed` di
volume).

## Menjalankan — WSL2 tanpa Docker (dev/test)

```bash
wsl -d Ubuntu-24.04 -u root bash /mnt/c/.../panel.arjunanstore.com/tools/local-bootstrap.sh
wsl -d Ubuntu-24.04 -u root bash /mnt/c/.../panel.arjunanstore.com/tools/wsl-start-services.sh   # setelah reboot WSL
wsl -d Ubuntu-24.04 -u root bash -lc 'cd /mnt/c/.../panel.arjunanstore.com && php artisan serve --host=0.0.0.0 --port=8000'
# Terminal kedua (dev server Vite + HMR):
wsl bash -lc 'cd /mnt/c/.../panel.arjunanstore.com && npm run dev'
```

Bootstrap memasang PHP 8.3+ (apt) + MySQL 8.0 + Redis (bila belum ada), membuat
DB `arj`, `composer install`, `.env`, lalu migrasi + seed. Dependensi frontend
(`node_modules`) dipasang otomatis di **EXT4** (`/opt/arj_node_modules`, lihat
kendala U4) dan di-symlink ke repo. Aplikasi berjalan **langsung dari repo
root**. Untuk test diperlukan database kedua `arj_test` (lihat
`tools/create-test-db.sql`). Verifikasi cepat seluruh halaman panel:
`bash tools/verify-inertia.sh`.

## Environment penting

| Variabel | Arti | Default dev |
| --- | --- | --- |
| `APP_ENV` / `APP_DEBUG` | mode aplikasi (produksi: `production` / `false`) | `local` / `true` |
| `APP_URL` | URL publik (dipakai ekspor tautan) | `http://localhost:8000` |
| `DB_*` | Koneksi MySQL (docker: `DB_HOST=mysql` otomatis via entrypoint) | `arj` / `arj` / `secret` |
| `QUEUE_CONNECTION` | `sync` (dev) atau `redis` (docker/produksi) | `sync` |
| `CACHE_STORE`, `SESSION_DRIVER` | redis di docker; file/array di dev | `redis` / `file` |
| `WEBPORT`, `APP_PORT` | port app di docker compose | `8000` |
| `DB_EXPOSE_PORT`, `REDIS_EXPOSE_PORT` | port host utk MySQL/Redis | `13306`, `16379` |
| `ARJ_PHONE_RULE_VERSION` | versi aturan klasifikasi telepon | `v1` |

## Login awal

| Peran | Email | Sandi |
| --- | --- | --- |
| Superadmin | `superadmin@arj.test` | `change-me-please` |

Ganti sandi lewat seeder/DB sebelum dipakai produksi. Peran lain (finance_owner,
marketing_adv, admin_order, admin_pengiriman) dibuat oleh seeder.

## Migrasi basis data

```bash
php artisan migrate --force          # instalasi berjalan (incremental)
php artisan migrate:fresh --seed     # reset penuh + data referensi (dev)
```

Migrasi **data awal dari workbook** (masters, resi 52.962 baris, laporan
kampanye, rekonsiliasi) dijalankan dengan perintah `arj:*` — runbook lengkap:
**[docs/MIGRASI.md](docs/MIGRASI.md)**.

## Queue worker

- Docker: service `worker` sudah berjalan (`php artisan queue:work redis --tries=3`).
- Manual/VPS bare-metal: `php artisan queue:work redis --tries=3 --sleep=3` di bawah
  supervisor/systemd; restart saat deploy.
- Dev: `QUEUE_CONNECTION=sync` — tanpa worker terpisah.

## Scheduler

- Docker: service `scheduler` menjalankan `php artisan schedule:work`.
- Bare-metal/VPS: satu entri cron:
  `* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1`
- Task terjadwal didefinisikan di `routes/console.php`.

## Backup & pemulihan

Backup wajib: **database** + volume `app_data` (berkas `storage/app` — unggahan
impor). Contoh backup harian terjadwal di host VPS:

```bash
mkdir -p /opt/arj/backup
# crontab host: dump harian 02:00 + retensi 30 hari
0 2 * * * cd /opt/arj && docker compose exec -T mysql sh -c \
  'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip > /opt/arj/backup/arj-$(date +\%F).sql.gz && \
  find /opt/arj/backup -name 'arj-*.sql.gz' -mtime +30 -delete
```

Pemulihan (uji berkala di server terpisah!):

```bash
zcat /opt/arj/backup/arj-2026-09-01.sql.gz | \
  docker compose exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
# berkas unggahan (data_issues terlampir/CSV sumber):
docker run --rm -v arj_app_data:/data -v /opt/arj/backup:/b alpine \
  tar czf /b/app_data-$(date +%F).tgz -C /data .
```

## Deployment VPS (produksi)

1. **Prasyarat:** VPS (2 vCPU/4 GB cukup), Docker Engine + Compose v2, Git.
2. Ambil kode:
   `git clone <repo> /opt/arj && cd /opt/arj`.
3. **Ganti rahasia** (jangan pakai default): buat `.env` di samping compose:
   `DB_PASSWORD=<kuat>`, `WEBPORT=8000`, dan bila port host MySQL/Redis tidak
   perlu publik, ubah binding compose ke `127.0.0.1:13306:3306` /
   `127.0.0.1:16379:6379`.
4. Jalankan: `docker compose up -d --build`. Boot pertama menjalankan
   `migrate:fresh --seed` (kosong — aman), lalu aplikasi hidup di `:8000`.
5. **Produksi:** set `APP_ENV=production`, `APP_DEBUG=false` (ubah baris
   `set_env APP_ENV/APP_DEBUG` di `entrypoint.sh`, atau sesuaikan `.env`
   container + restart), lalu `php artisan config:clear` di dalam container.
6. **Reverse proxy + TLS:** taruh Nginx/Caddy di depan `127.0.0.1:8000`
   (contoh Nginx: `proxy_pass http://127.0.0.1:8000;` + `certbot --nginx`).
7. **Impor data awal** (sekali): salin CSV ke container lalu jalankan perintah
   `arj:*` sesuai `docs/MIGRASI.md`:
   ```bash
   docker compose cp out/migrasi arj_app:/tmp/migrasi
   docker compose exec app php artisan arj:migrate-masters --file=/tmp/migrasi/...
   ```
8. **Deploy pembaruan:** `git pull` → `docker compose up -d --build` →
   `docker compose exec app php artisan migrate --force` →
   `docker compose restart worker scheduler`.
9. Cek kesehatan: `docker compose ps`, log `docker compose logs -f app worker`,
   halaman panel bisa dibuka, antrean impor terproses.

## Fitur & cakupan pengujian

- **Alur order** (Tahap 3): input order, klasifikasi telepon otomatis
  (riwayat pengiriman), koreksi/override berizin + audit, ekspor platform.
- **Master resi** (Tahap 3): impor DBMengantar idempoten, worklist status/remark
  tak terpetakan, pemetaan status agregator (dapat ditinjau admin).
- **Marketing** (Tahap 4): impor laporan kampanye Meta, pemetaan kampanye,
  rekap ADV vs resi aktual.
- **Komisi** (Tahap 5): kalkulator outputresi (COD diterima, retur, transfer),
  periode CS 16–15 & ADV kalender, tutup periode, aturan versi per baris.
- **Dashboard** (Tahap 5): grid KPI + progress status + Tabs + preset rentang
  (Ant Design, dimodernisasi pasca-migrasi).
- **Migrasi & rekonsiliasi** (Tahap 6): perintah `arj:*` idempoten.

Pengujian: `php artisan test` — **55 tes / 394 assertion** (butuh DB `arj_test`,
MySQL karena view & enum).

## Perintah operasional (`php artisan`)

| Perintah | Guna |
| --- | --- |
| `arj:migrate-masters` | Impor master advertisers/CS/produk dari CSV (idempoten). |
| `arj:migrate-campaigns` | Impor laporan kampanye Meta (tab `Rekap`) + normalisasi metrik rusak. |
| `arj:migrate-shipments` | Impor resi DBMengantar melalui mesin impor produksi (idempoten). |
| `arj:reconcile` | Bandingkan OutputResi workbook vs hitungan website → `docs/REKONSILIASI.md`. |

## Tools

| Skrip | Guna |
| --- | --- |
| `tools/local-bootstrap.sh` | Setup stack + aplikasi di WSL (idempoten, termasuk `npm install` di EXT4). |
| `tools/wsl-start-services.sh` | Nyalakan MySQL+Redis setelah reboot WSL. |
| `tools/verify-inertia.sh` | Login superadmin + cek semua halaman panel (komponen Inertia). |
| `tools/time-heavy-pages.sh`, `tools/time-light-pages.sh` | Ukur waktu server-side halaman berat/ringan (curl). |
| `tools/profile-rekap.php` | Profil komponen `/rekap-adv` (rangeShipments/rows/unmatched). |
| `tools/create-test-db.sql` | Buat DB `arj` + `arj_test` + user. |
| `tools/xlsx-to-csv.ps1` | Konversi workbook → CSV (Tahap 6). |
| `tools/dump-sheet.ps1`, `extract-formulas.ps1` | Dump/audit workbook (Tahap 1). |
| `tools/http-flow.sh` (dan `http-*.sh`) | Cek alur HTTP (login → order → ekspor). |
| `tools/build.sh`, `tools/test.sh` | Build Vite / PHPUnit di WSL dari path repo (hindari kuoting PowerShell). |
| `tools/antd-run.sh` | Jalankan `@ant-design/cli` (`lint`, info API) memakai Node 22 user-local — **tanpa** mengubah Node proyek. Contoh: `wsl bash tools/antd-run.sh lint ./resources/js --format json`. |
| `tools/setup-node22.sh` | Pasang Node 22 via nvm (user-local, khusus CLI antd). |

## Kendala yang diketahui

- **U1 — MySQL 8 di WSL:** `/run/mysqld` & `/var/lib/mysql-files` hilang setiap
  reboot distro → jalankan `tools/wsl-start-services.sh` (membuatnya + start).
- **U4 — npm di NTFS:** instal `node_modules` langsung di `/mnt/c` merusak
  binari esbuild (vite gagal start) → node_modules dipasang di EXT4
  (`/opt/arj_node_modules`) dan di-symlink; ditangani otomatis oleh
  `tools/local-bootstrap.sh`.
- **U5 — Vite watcher di drvfs:** `/mnt/c` tidak memancarkan event watcher →
  `server.watch.usePolling: true` wajib (sudah di `vite.config.js`). Jangan
  polling agresif/tanpa `ignored` — menstat tree `vendor/` membuat VM WSL
  melambat drastis. Perf: `/rekap-adv` ±6 s (dulu ±1 menit) dan payload HTML
  menyusut ke ±328 KB (dulu ±2 MB, berkat serialisasi ringkas `buildRow`); detail
  `docs/VERIFIKASI_UI_REACT.md`.
- **U2 — Master ADV/CS/Produk:** ✅ SELESAI (Tahap 6) — diimpor dari tab `ADV_CS`
  & `Produk` workbook (3 advertisers, 36 CS, 18 produk) lewat `arj:migrate-masters`.
- **U3 — Klasifikasi telepon:** heuristik riwayat pengiriman
  (`ARJ_PHONE_RULE_VERSION=v1`) — perlu tinjauan berkala terhadap data nyata.
- **U7 — `@ant-design/cli` butuh Node ≥20:** dependensinya `oxc-parser` memakai
  binding native yang ditolak Node sistem proyek (18.19.1). Solusi: Node 22
  user-local via nvm (`tools/setup-node22.sh`) yang hanya diaktifkan
  `tools/antd-run.sh` — **build & tes tetap memakai Node 18** default proyek.
  Lint antd saat ini **0 issues**; umpan-balik statis `message`/`Modal.confirm`
  sudah dimigrasikan ke hook `App.useApp()` (root dibungkus `<App>`).

## Setelah migrasi: tanpa workbook

Seluruh data operasional sudah berada di database; workbook **hanya jejak
sejarah**. Operasi harian (impor DBMengantar, unggah laporan kampanye Meta,
perbaikan worklist) dilakukan lewat menu **Impor**, **Data Error**, dan
**Marketing** di panel — lihat `docs/MIGRASI.md` bagian *Pasca-migrasi*.
