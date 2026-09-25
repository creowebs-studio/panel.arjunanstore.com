# TAHAP 1 — AUDIT SUMBER (WORKBOOK → WEBSITE ARJ)

Dokumen ini adalah hasil **Tahap 1** pada `prompt.md`. Tujuannya: memetakan lima workbook
referensi menjadi spesifikasi aturan bisnis, format data, alur, dan **bagian yang belum dapat
dipastikan** sebelum fondasi aplikasi dibangun.

## 0. Metode & catatan kepercayaan (cache vs rumus)

- Ekstraksi dilakukan langsung dari XML `.xlsx` (ZIP) — membaca `xl/workbook.xml`,
  `xl/sharedStrings.xml`, dan XML tiap sheet — sehingga **rumus sel (`<f>`) ikut terbaca**, bukan
  hanya nilai cache. Script: `tools/extract-formulas.ps1`; dump mentah sebagian di `out/`.
- File ini adalah **export dari Google Sheets** yang memakai Apps Script. Ciri: fungsi dibungkus
  `__xludf.DUMMYFUNCTION("<rumus Sheets asli>", <nilai cache Excel>)`. Artinya:
  - **Nilai yang tampil di Excel = cache** (bisa basi). **Rumus asli = string di dalam
    `DUMMYFUNCTION`**. Untuk logika, yang dipakai adalah string rumus, bukan angka cache.
  - Sebagian besar rumus kunci memakai `IMPORTRANGE(...)` ke file/master lain. **Angka cache tidak
    membuktikan rumus berjalan hari ini.** Ini ditegaskan sesuai peringatan `prompt.md` §12.
- Konvensi tanggal: workbook memakai **serial Excel** (mis. `46112`, `46127`). `46112 ≈ 2026‑04‑...`;
  rentang `CS MEI` = `B1=46127`..`C1=46158` (jendela 31 hari) → menegaskan periode CS berbasis
  tanggal operasional, bukan bulan kalender (lihat §9).

## 1. Peta workbook → peran → alur

| # | File | Peran | Tab kunci | Alur |
|---|------|-------|----------|------|
| 1 | `ID 01 Admin.xlsx` | Input order + validasi + ekspor awal | `Input`, `OutputPositif-Mengantar`, `OutputPositif-Lincah`, `OutputNegatif`, `Data Kode Pos`, `DB_Agg_Eks`, `DB ADVS&CS`, `DB PRODUK`, `DataOrder`, `Perform by wa`, `List 1`(hidden) | A, B |
| 2 | `Upload Mengantar 2026.xlsx` | Impor hasil platform + master resi + status | `PasteMengantar`, `DBMengantar`, `Status (Agregator)`, `Data Error`, `ADV_CS`(imp), `Produk`(imp,hidden) | C, D |
| 3 | `ARJ Input Data Marketing 2026.xlsx` | Data kampanye iklan (Meta Ads export) | `AM01..AM05`, `Rekap`, `Cek`(hidden) | E |
| 4 | `Master ARJ.xlsx` | Rekap ADV↔resi, dashboard, setup komisi | `Dashboard`, `OutputResi`, `RekapADV (All)`, `RekapADVtoResi (All)`, `Datable`, `RekapADV Monthly`, `Agress`, `ADV`, `ADV_CS`, `Produk`, `Setup Komisi CS`, `LINK`, `Data Error` | D, E, F |
| 5 | `Komisi CS & ADV New.xlsx` | Laporan komisi & pembayaran per periode | `CS <BULAN>`, `ADV <BULAN>`, `OutputResi`(hidden), `RekapADV (All)`(hidden), `LINK`(hidden) | F |

> `prompt.md` menyebut `ARJ Input Data Marketing 2026(1).xlsx`; file aktual `...Marketing 2026.xlsx`.
> Dianggap sama.

## 2. File/master EKSTERNAL yang direferensikan (dependencies)

Hasil audit `LINK` (Master ARJ) + `IMPORTRANGE` menunjukkan operasional **tidak** berdiri pada 5
file saja:

| Sumber eksternal | Isi | Dipakai di | Status |
|------------------|-----|-----------|--------|
| `LINK!C2..C6` → "ARJ Input Data Marketing 2026" `Rekap!A3:V` | Metrik iklan per kampanye | RekapADV, RekapADVtoResi | ✅ tersedia |
| `LINK!G2` → "Upload Mengantar 2026" `DBMengantar` | Resi Mengantar | OutputResi, RekapADVtoResi | ✅ tersedia |
| **`LINK!K2` → "Upload Lincah 2026" `DBLincah`** | **Resi Lincah** | OutputResi, RekapADVtoResi | ❌ **TIDAK ADA di workspace** |
| `IMPORTRANGE(...docs/1fqri3h0...)` `ADV_CS!A1:I100` | Master ADV & CS | Upload Mengantar `ADV_CS`, Input | ⚠️ file master ke‑6, tidak dilampirkan |
| `IMPORTRANGE(...docs/1fqri3h0...)` `Produk!A1:AE200` | Master Produk | Upload Mengantar `Produk` | ⚠️ file master ke‑6, tidak dilampirkan |
| `IMPORTRANGE('Perform by wa'!$B$1,"Closed!...")` | Riwayat order "Closed" per WA | Input!AK, AL | ⚠️ URL di sel `Perform by wa!B1`, data riwayat eksternal |

**Dampak:** Master `ADV_CS` & `Produk` "sejati" adalah file Google Sheet lain (`1fqri3h0…`); salinan
setempat (`DB ADVS&CS`, `DB PRODUK`, `ADV`) hanya sebagian. Untuk **seed data awal** website,
ketiga file eksternal ini (Lincah, Master ADV_CS/Produk, sumber "Closed") **harus diminta** sebelum
migrasi (Tahap 6). Lihat §10 keputusan.

## 3. Master data & referensi

### 3.1 `DB PRODUK` (ID 01 Admin) — master produk + biaya
Kolom: `B NO`, `C Kategori`, `D Kode`, `E Nama Produk`, `F Harga Jual (Pc)`, `G HPP`,
`H Packing`, `I Qty/Paket`, `J Total HPP`, `K Biaya Operasional`, `L Komisi CS & Input`, `M COGS`,
`N Harga Jual/Paket`, `O Margin/Produk`, `P Kode Produk` (kode resi 2‑char).

### 3.2 `DB ADVS&CS` (ID 01 Admin) — pemetaan CS↔ADV
`B No`, `C Kode CS`, `D Nama CS`, `E Kode Nama CS` (kunci lookup), `F Kode ADVS`, `G Nama ADVS`,
`H Kode Resi`(ADV), `I` (kode CS utk resi), `J Kode CS`.

### 3.3 `ADV` (Master ARJ) — master advertiser
`A No`, `B Kode ADVS` (`AM01`,`AM02`,…), `C Nama ADVS` (`GILANG`,`ARIF`,…), `D Email`.

### 3.4 `DB_Agg_Eks` (ID 01 Admin) — agregator & ekspedisi
`B2 = Mengantar`, `B3 = Lincah`; blok ekspedisi: `B6 = JNE`, … (dipakai filter ekspor §5/§6).

### 3.5 `Data Kode Pos` (ID 01 Admin)
Lookup `kelurahan & "," & kecamatan → kode pos` (range `A4:B…`). Mengisi `Input!I` (kode pos).

### 3.6 `Perform by wa` (ID 01 Admin) — mesin riwayat per nomor WA
Blok bersebelahan (agregat dari data pengiriman):
- `A POSITIF`: `B WA Customer`, `C Diterima` (jumlah diterima)
- `E NEGATIF`: `F WA Customer`, `G Retur` (jumlah retur)
- `I TOTAL TRANSAKSI`: `J WA`, `K Jumlah`
- `M ORDER ON PROGRESS`: `N No resi`, `O WA`, `P Status`
Inilah tabel yang di‑lookup oleh logika positif/negatif (§4.3). **Merupakan hasil turunan, bukan
input** — di website harus menjadi **view/materialization** dari riwayat `shipment_status_events`.

## 4. ALUR A — Input & validasi order (`ID 01 Admin.xlsx` → `Input`)

### 4.1 Spesifikasi kolom & sumber (baris 2 = Auto/Manual)
Manual (diisi operator): `B Tgl Order`, `C Kode CS`, `D Nama Customer`, `E Telephone`,
`F Detail Alamat`, `G Kecamatan,Kabupaten`, `H Kelurahan`, `J Jenis Pesanan`, `K Detail Pesanan`,
`L Qty`, `M Jenis Pembayaran` (COD / NON COD), `N Harga`, `O Berat Paket`, `P Agregator`,
`Q Ekspedisi`.
Auto (dihitung): `A No`(SEQUENCE), `I Kode Pos`(VLOOKUP §3.5), `S Alamat Lengkap`,
`T,U,V,W,X` (urai alamat/kel/kec/kota/prov), `Z Koreksi Format No Telp`, `AB..AH` kode resi,
`AJ No Telp ternormalisasi`, `AK..AQ` validasi.

### 4.2 Normalisasi & pembentukan kode
- **No HP ternormalisasi (`AJ`)**: jika awal `"62"` dipertahankan; jika awal bukan `0` dan bukan
  `62` → `"62"&E`; jika awal `0` → `"62"&MID(E,2,15)`. Lalu `-` dan spasi dihapus. **Simpan sbg
  string**, jangan angka.
- `Z`: format tampil `"0"&…` (xxx‑xxxx‑xxxx) dari `AJ`.
- **Kode resi/remark (`AH`)** = `AB`(`TEXT(tgl,"DDMM")`) + `AC`(`TEXT($AI$1,"00")`, "Kode Admin
  Input", default `AI1=1`) + `AD`(Kode ADV) + `AE`(Kode CS) + `AF`(Kode Produk) + `AG`(`NO` →"0000").
  - `AD` = `XLOOKUP(C, 'DB ADVS&CS'!E:H)`; `AE` = `XLOOKUP(C,…!E:I)`; `AF` = `XLOOKUP(J,'DB PRODUK'!E:P)`.
  - Kode 14‑char inilah yang nanti di‑parse balik pada resi (§7) untuk atribusi ADV/CS/Produk.

### 4.3 ⭐ Aturan positif/negatif yang SEBENARNYA (jangan disederhanakan)
Kolom perantara:
- `AK` (Last Order): cari riwayat nomor via "Closed"; urutkan tgl desc; **jika status order
  TERAKHIR = "RETUR" → "Negatif"**, selain itu "Positif"; tanpa riwayat → "Positif".
- `AM` (No resi masih diproses): `XLOOKUP(AJ → 'Perform by wa'!O:N)` — terisi bila ada order **dalam
  proses** untuk nomor itu.
- `AN` (JML Retur): `XLOOKUP(AJ → 'Perform by wa'!F:G)`.
- `AO` (JML Diterima): `XLOOKUP(AJ → 'Perform by wa'!B:C)`.
- `AP` (OUTPUT By WA):
  ```
  if F="" → ""
  elif AM<>""        → "Negatif"   (ada order masih diproses)
  elif AN>0          → "Negatif"   (pernah retur)
  elif AO>0          → "Positif"   (pernah diterima, tanpa retur/proses)
  else               → "Data Belum Tersedia"
  ```
- `AQ` (OUTPUT AKHIR — penentu ekspor):
  ```
  if F="" → ""
  elif AK=="Negatif"   → "Output Data Negatif"   (order terakhir retur)
  elif AP=="Negatif"   → "Output Data Negatif"
  elif AP=="Positif"   → "Output Data Negatif"
  else (AP="Data Belum Tersedia")                → "Output Data Positif"
  ```
**Kesimpulan penting (berlawanan dengan intuisi):** order menjadi **Positif hanya bila nomor itu
BELUM PERNAH punya riwayat sama sekali** (bukan "pernah retur berarti negatif"). Nomor yang
pernah **diterima** (`AP="Positif"`) justru menjadi **Negatif** di `AQ` (tidak dikirim ulang),
begitu pula yang pernah retur atau sedang diproses. Ini aturan anti‑duplikasi pengiriman ke
pelanggan lama. **Wajib direplikasi persis.** Karena `AP` bergantung `AM/AN/AO` dari `Perform by wa`
(agregat global per nomor), implementasi website butuh **agregat riwayat per WA ternormalisasi**.
Ada pula override manual di blok `AJ/AQ` baris judul (`"Data Positif"/"Data Negatif"`) — perlu
dikonfirmasi sebagai fitur "tandai manual" (§10).

### 4.4 Hasil turunan
- `OutputNegatif`: filter `AQ="Output Data Negatif"`.
- Export positif per agregator (§5).
- `C1` (kolom `AP`/ringkasan) membuat teks "(Total N Data)" — informatif.

## 5. ALUR B — Ekspor order positif

### 5.1 `OutputPositif-Mengantar` (13 kolom, format Mengantar)
Nama Penerima ← `Input!D`; Alamat ← `Input!S`; No Telp ← `Input!Z`; District(Kec) ← `Input!V`;
Subdistrict(Kel) ← `Input!U`; Kode Pos ← `Input!I`; Berat ← 1 (konstan); Harga NON‑COD ←
`Input!N` bila `M="NON COD"`; Nilai COD ← `Input!N` bila `M="COD"`; Isi Paket ←
`UPPER(SUBSTITUTE(Input!K,"PCS ","")&" "&Input!L&" PCS")`; Remark1 ← `Input!AH` (kode resi);
Instruksi ← teks tetap: **"MOHON MAAF TANPA VIDEO UNBOXING KOMPLAIN KERUSAKAN/KEKURANGAN BARANG
TIDAK DITERIMA!!!"**; Provinsi ← `Input!X`.
**Filter:** `AQ="Output Data Positif"` **DAN** `Input!P = DB_Agg_Eks!B2` ("Mengantar"). Semua teks
`UPPER()`.

### 5.2 `OutputPositif-Lincah` (13 kolom, format berbeda)
Kolom: Nama, Alamat, No Telp, Kode Pos, **Kurir**←`Input!Q`, **Tipe Pengiriman**←`Input!M`, Berat,
Harga Non‑COD, Nilai COD, Isi Paket←`Input!K`, **Jumlah Barang**←`Input!L`, Remark1←`AH`,
Instruksi(teks sama unboxing). **Filter:** `AQ="Output Data Positif" DAN P=DB_Agg_Eks!B3` ("Lincah").
⚠️ **Anomali sumber:** beberapa kolom Lincah memakai offset baris `M8/N8/K8/L8` (bukan `M5`),
diduga bug geser 3 baris pada sheet asal. Saat implementasi gunakan **satu baris selaraskan**
dan tandai untuk verifikasi (§10).

## 6. ALUR C — Impor hasil Mengantar/Lincah (`Upload Mengantar` → `PasteMengantar`)

- `PasteMengantar` = area tempel mentah export platform (kolom A..AJ). Kolom `AS..` menyusun blok
  "Orderan baru" yang disalin ke `DBMengantar`.
- **Dedup (`Cek`, kolom `AQ`)**: `COUNTIF(DBMengantar!B, barisIni)` → `0` = baru, `>0` = sudah ada.
  Blok baru diambil hanya bila `AQ=0` (`FILTER(...,AQ=0)`).
- `AS1!AT = COUNTA(DBMengantar!B)` penunjuk baris; panduan manual di baris 1‑3.
- **Impor file yang sama 2× tidak menambah duplikat** karena gerbang `AQ=0` — pola ini yang harus
  dipetakan ke website: unique `order_id`/`tracking_id` per batch, baris identik → "sudah pernah
  diimpor", bukan insert baru.

## 7. ALUR D — Master resi & status (`DBMengantar` + `Status (Agregator)` + `OutputResi`)

### 7.1 `DBMengantar` (master resi Mengantar) — kolom
A Expedition, B Order ID, C Tracking ID, D Resi Forward/R, E Customer Name, F Phone, G Address,
H Province, I City, J District, K ZIP, L COD, M Product Value, N Goods Desc, O Qty, P Create Date,
Q Last Update, R Last Status, S Last POD Status, T Shipping Fee, U Shipping Discount, V COD Fee
(Inc VAT), W Return Fee, X Remark1, Y Subdistrict, **AB Remark Corrected**, **AC Status Internal**,
**AD Non COD**, **AE KodeADV**, **AF Kode CS**, **AG Kode Produk**, **AH Nama Kampanye**, **AI Cek
double**.
- `AB`: jika Remark 14‑char → diberi awalan "0" (`text @`). Nomor disimpan string, nol depan terjaga.
- `AC`: `VLOOKUP(R → 'Status (Agregator)'!C:D)` → status internal (DITERIMA/DIKIRIM/…). Bila kosong
  ⇒ **status tak terpetakan** → muncul di `Data Error`.
- `AD Non COD` = `if COD>0 → 0 else M`.
- `AE/AF/AG`: parse `MID(AB,7,3)`→ADV_CS, `MID(AB,10,2)`→Produk (sama pola §4.2). `AH` gabung
  `KodeADV-KodeCS-KodeProduk`. `AI Cek double` = hitung kemunculan (deteksi resi/ID ganda).

### 7.2 Tabel pemetaan status (`Status (Agregator)` — `C Status System` → `D Status Internal`)
`prompt.md` minta 5 state. Realita sumber **sudah 5**, tetapi **UNDEL menaungi banyak kode**:
- → **DIKIRIM**: INCOMING, MANIFEST OUTGOING, MISSROUTE, ON DELIVERY, OUTGOING, OUTGOING SMU,
  REDELIVERY, TAKE SELF, FORWARDED, TRANSIT CITY, SORTING CENTER, WAREHOUSE, NEED REDELIVERY,
  SHIPMENT ISSUE, WEEKEND OR HOLIDAY, PICKED UP, INBOUND STATION, ORIGIN GATEWAY, ON PROCESS,
  DELIVERY COURIER.
- → **UNDEL**: UNDELIVERED, RETURN IN PROGRESS*, BAD ADDRESS, CLOSED OR NOT AVAILABLE, RECEIVER
  ISSUE, PARCEL LOST, SHIPMENT DAMAGE, RECEIVER RESIGNED, REJECTED.
- → **DITERIMA**: DELIVERED, DELIVERED (Pending).
- → **PACKING**: PENDING PICKUP.
- → **RETUR**: DELIVERY RETURN, RTS, RETURN PROCESS.
⚠️ `RETURN IN PROGRESS` dipetakan ke **UNDEL** (dengan Keterangan "Pengembalian sedang diproses")
bukan RETUR — keputusan bisnis asli, pertahankan & dokumentasikan di UI pemetaan. Status tak ada di
tabel → `AC` kosong → `Data Error` (mis. `CANCELLED` terlihat pada contoh). Perlu entri mapping.

### 7.3 `Data Error` (Upload Mengantar) — 3 laporan
1. **Status Internal kosong** (`DBMengantar AC=""`) → tampilkan Tgl, No AWB, Status Agregator.
2. **Error Remark** (`AH` mengandung "XX") → kampanye tak terpetakan; tampilkan Tgl, AWB, Remark,
   KodeADV/CS/Produk.
3. **Error Double Resi** → AWB yang muncul >1× (jumlah kemunculan).

### 7.4 `OutputResi` (Master ARJ) — master resi **tergabung** Mengantar + Lincah
Merges `DBMengantar` (LINK!G2) + `DBLincah` (LINK!K2) via `IMPORTRANGE`, di‑SORT by Create Date.
Kolom hasil (baris 2): A no AWB, B Ekspedisi, C ZIP, D No WA, E Kode ADVS, F Kode CS, G Kode Produk
(parse `AN`Remark), H Periode=`TEXT(I,"YYMM")`, I Create Date, J Date Updated, K Nama Produk,
L Harga Jual Min (INDEX Produk!L:U by qty), M Quantity, **N Status Sistem**, **O Status Internal**,
P Pembayaran Transfer, Q Pembayaran COD, R Biaya Ongkir, S Diskon Ongkir, T Biaya COD, U PPn COD,
V Biaya Ekspedisi (dgn diskon), W (tanpa diskon), X Perubahan Kas COD, Y Return Fee, Z..AD COGS,
AE..AF Laba Kotor, AG..AK Komisi CS, AL Komisi Admin, AM Laba Kotor Eksplisit, AN Remark,
AO Kota, AP Aggregator (`Mengantar`/`Silincah`).
**`O="9 ERROR"`**: penghitung status internal kosong ⇒ resi tanpa status final (bahan Data Error).

## 8. ALUR E — Marketing & rekap ADV (`Marketing` → `Rekap` → `RekapADV (All)`)

### 8.1 `Rekap` (per file Marketing, AM01..AM05)
Export Meta Ads per kampanye: `A Awal Pelaporan`, `B Akhir Pelaporan`, `C Nama Kampanye`
(format **`AMxx-ARyy-PZ`**), `D Penayangan`, `E Atribusi`, `F Hasil`, `G Indikator Hasil`,
`H Jangkauan`, `I Frekuensi`, `J Biaya/Hasil`, `K Anggaran Set Iklan`, `L Jenis`, `M Jumlah
Dibelanjakan (IDR)` (= spend), `N Berakhir`, `O Impressi`, `P CPM`, `Q Klik Tautan`, `R CPC`,
`S CTR`, `T Klik Semua`, `U CTR Semua`, `V CPC Semua`. `A1=LEFT(C3,4)` (kode ADV), D1 "Last Update".

### 8.2 `RekapADV (All)` (Master ARJ)
Men‑VSTACK+QUERY kelima `Rekap!A3:V` (LINK!C2..C6). Kolom: `A Periode`=`TEXT(tgl,"YYMM")`,
`B Nama Kampanye`+`C Tanggal`, **`D Kode ADVS`**=`LEFT(B,5)&VLOOKUP(LEFT(B,4),ADV!B:C,2)` (fallback
"XXXX"), **`E Kode CS`**=`VLOOKUP(REGEXEXTRACT(B,"-(.*?)-"),ADV_CS!B:I,3)` (fallback "XXXX"),
**`F Kode Produk`**=`VLOOKUP(RIGHT(B,2),Produk!C:C,1,0)` (fallback "XX").
Metrik iklan: `G Anggaran Set Iklan`, **`H Spend+PPN` = Col13 × 1.12** (PPN 12% atas spend), I
Jangkauan, J Impresi, K Klik, … (dipilih dari kolom Rekap).

### 8.3 `RekapADVtoResi (All)` (Master ARJ) — jembatan ADV↔Resi (Rekap Sales)
Union kampanye + `DBMengantar` + `DBLincah` resi. Metrik per kampanye×ADV×CS×Produk×tanggal
(semua `COUNTIFS/SUMIFS` ke `OutputResi` difilter `I` create‑date dalam hari itu):
`G Packing`, `H Dikirim`, `I Undel`, `J Diterima`, `K Retur`, `L Total`, `M Create Today`=today‑tgl,
`N %Close`=(Diterima+Retur)/Total, `O Resiko Return`=EstReturn/Total,
`P Est Return`= if(age≥10→100% else age/10) × (Total − %Close·Total) + Retur, `Q Est Diterima`,
`S/T Laba Kotor`=`SUMIFS(OutputResi!AM)`, `U Komisi CS`=`SUMIFS(OutputResi!AK)`,
**`V Profit` = LabaKotor − KomisiCS − SpendIklan**.

## 9. ALUR F — Komisi & dashboard (`Setup Komisi CS` + `OutputResi` + `Komisi CS & ADV New`)

### 9.1 `Setup Komisi CS` (Master ARJ) — tarif dasar
Tier komisi order berdasar **Laba Kotor Tanpa‑Diskon (AF)**:
```
AF < 26.000                → 0
26.000 ≤ AF < 89.000        → 5.000
AF ≥ 99.001                 → %Margin × AF   (G6 = persentase, mis. 10%)
[89.000 .. 99.001)          → 0  (gap pada sumber — lihat §10)
```
Per status: **PACKING → 0**; **RETUR → −(0.2 × ongkir + potongan)** (`K8=0.2`, `L8=0`).
Catatan teks: "CS jual produk di bawah setup DB → komisi dipotong 5% margin"; "Komisi CS 10% dari
margin produk" (contoh per produk: Sepatu 1.000/5.000, dsb).

### 9.2 Rantai komisi/profit per resi (`OutputResi`) — definisi persis
```
T  Biaya COD       = if(O=RETUR,0, Q × 3%)
U  PPn COD         = if(O=RETUR,0, T × 11%)
V  Ongkir(dgn dk)  = R − S + T + U      W (tanpa dk) = R + T + U
X  Perubahan KasCOD= RETUR→ −V ; DITERIMA→ P+Q−V ; else ""
Z  COGS Produk     = VLOOKUP(G→Produk!.4) × M       AA COGS Packing = Produk!.5
AB COGS HPP+Packing= Z + AA              AC COGS Ops = Produk!.6
AD COGS Total HPP  = AB + AC
AE LabaKotor(dgn dk)= RETUR → −((R−S)+(T+U)+AA)
                     else   → (P+Q) − (R−S) − (T+U) − AD
AF LabaKotor(tanpa dk, Implisit)= RETUR → −(R+(T+U)+AA) else → (P+Q)−R−(T+U)−AD
AG Komisi CS Order = RETUR → −(K8·R + L8)
                     else tier pada AF (§9.1: <F4→G4; <F5→G5; ≥F6→G6·AF; else 0)
AH Komisi CS Transfer = if(P>0 and O=DITERIMA, 1000, 0)
AI Komisi CS >1 Paket = (kolom ada; rumus per‑paket perlu verifikasi sel — §10)
AJ Komisi CS Ongkir   = if(RETUR,0, ((P+Q) − W − L) × 25%)
AK TOTAL Komisi CS    = AG + AH + AI + AJ
AL Komisi Admin Input = 500 (flat per resi yang punya status)
AM LabaKotor(tanpa dk, Eksplisit) = (P+Q) − R − (T+U) − AD − AK − AL
```
`RekapADVtoResi.V Profit` memakai **AM** (sudah net komisi) lalu kurangi spend iklan.

### 9.3 Laporan bulanan (`Komisi CS & ADV New`) — `CS <BULAN>` & `ADV <BULAN>`
Per baris CS/ADV, difilter jendela periode (`B1`..`C1` pada `OutputResi!I` create date):
Quantity per status (On Progress vs Closed, `D..I`), `%Undel`(J), `%Retur`(K); Komponen Komisi
(`S` Order, `T` Transfer, `U` Ongkir, `V` Total = S+T+U) via `SUMIFS OutputResi!AK/AH/AJ`;
`X Komisi On Progress`, `Y Komisi Closed (Payable)`, **`Z Saldo Komisi Periode Lalu`**,
`AA Total Belum Dibayar`, `AB Payment ACC (Manual)`, `AC Komisi Dibayar`. Blok profit: `AY
Penghasilan`, `AZ HPP`, `BG Laba Kotor`, `BH Komisi CS`, `BO Laba setelah komisi CS`, `BP Spend
Marketing (alokasi)`, `BW Laba setelah spend`, `BX Diskon Ongkir`, `CE Laba setelah diskon ongkir`.

### 9.4 ⭐ Periode CS ≠ Periode ADV (konfirmasi bukti)
- **CS** (`CS MEI`): `B1=46127`,`C1=46158` → jendela **tanggal operasional** (pola 16→15).
- **ADV/Dashboard** (`Dashboard`): deret **bulan kalender** via `EOMONTH` (`J3=EOMONTH(Q3,-1)`, dst.),
  `Datable` menyaring `RekapADVtoResi` per‑hari pada rentang `AP1..AY1` ("Periode : N Hari").
⇒ Website **wajib** menyimpan **definisi periode terpisah** untuk CS vs ADV (§10 DB, §11 UI).

### 9.5 Dashboard & Datable
`Dashboard` (Marketing): kartu `Laba Kotor (Eksplisit)`, `Laba Kotor (Implisit)`, `Spend Positif`,
dst. per bucket bulan. `Datable`: deret harian (agregat `RekapADVtoResi` per ADV/CS/tanggal) sebagai
sumber grafik; rentang dari sel `Dashboard!AP1/AY1`. Semua **harus** memakai rumus §9.2 agar kartu
ringkasan = tabel detail (kriteria penerimaan #6).

## 10. Belum dapat dipastikan / keputusan yang diperlukan

| # | Item | Lokasi sumber | Dampak | Keputusan |
|---|------|---------------|--------|-----------|
| U1 | **`Upload Lincah 2026` (DBLincah) hilang** | Master `LINK!K2`; OutputResi/RekapADVtoResi | Seluruh baris resi Lincah tak bisa dimigrasi/dihitung | Minta file tsb **atau** kunci format lewat contoh export Lincah sebelum Tahap 4/6 |
| U2 | **Master `ADV_CS` & `Produk` eksternal** | `IMPORTRANGE docs/1fqri3h0…` | Kode/kategori/COGS & tarif bisa berbeda dari salinan lokal | Minta file master untuk seed & rekonsiliasi |
| U3 | Sumber riwayat "Closed" (Input!AK/AL) | `IMPORTRANGE 'Perform by wa'!B1,"Closed!…"` | Klasifikasi Last‑Order bergantung file luar | Konfirmasi: di website, "last order status" = turunan `shipment_status_events` lokal |
| U4 | **Komisi EDC** | *Ditelusuri: TIDAK ADA* (hanya substring dlm kode resi) | – | **Jangan** buat formula EDC; konfirmasi ke owner apakah istilah lain |
| U5 | Gap tarif [89.000, 99.001) → 0 | `Setup Komisi CS` F4/F5/F6 | Order margin di celah dapat komisi 0 | Konfirmasi ambang & tutup gap |
| U6 | `AI Komisi CS >1 Paket` | OutputResi AI | Komponen total komisi | Butuh rumus sel persis (belum tertangkap) — verifikasi saat build |
| U7 | Anomali offset baris Lincah (`M8`) | `OutputPositif-Lincah` | Ekspor Lincah bisa bergeser | Samakan basis baris; verifikasi contoh |
| U8 | Override manual "Data Positif/Negatif" | `Input` blok AJ/AQ header | Koreksi klasifikasi | Jadikan aksi "tandai manual + alasan" ber‑audit |
| U9 | `AI1 Kode Admin Input` (=1) | `Input!AC` | Prefiks kode resi per admin input | Jadikan atribusi user; pastikan unik |
| U10 | Nilai persentase G6 (§9.1) | `Setup Komisi CS` cache | Besaran komisi tier tinggi | Ambil angka final dari master/owner |
| U11 | Saldo periode lalu (Z) lintas‑bulan | `Komisi CS & ADV` | Rollover pembayaran | Definisikan rantai antar‑periode di skema |

## 11. Traceability: konsep sumber → entitas DB (`prompt.md` §10)

| Wilayah sumber | Entitas target |
|----------------|----------------|
| `Input` manual + computed | `orders`, `customers`, `order_items`, `products`, `cs_agents` |
| `AH` kode resi + normalisasi `AJ` | kolom `orders.reference_code`, `customers.phone_normalized` |
| Positif/Negatif `AP/AQ` + `Perform by wa` | `phone_history_view`, rule version → `orders.validation_result`, `rule_version`, `validated_at` |
| `OutputPositif-*` | `export_batches`, `export_batch_items` (+template platform) |
| `PasteMengantar`/`DBMengantar` + `Cek` | `import_batches`, `import_rows`, `shipments` (unique order/tracking) |
| `Status (Agregator)` | `carrier_status_mappings` |
| `OutputResi` (per‑resi terhitung) | view/materialization dari `shipments` + `shipment_status_events` + produk |
| `Data Error` | `data_issues` |
| `RekapADV*`/`Rekap`/`ADV` | `advertisers`, `campaigns`, `marketing_daily_reports` |
| `Setup Komisi CS`+tarif | `commission_rules` (effective‑dated) |
| `CS/ADV <BULAN>` + periode | `commission_periods` (tipe CS vs ADV), `commission_entries`, `commission_payments` |
| Override/koreksi | `audit_logs` |

## 12. Rekomendasi langkah berikutnya (Tahap 2)
1. Kunci stack & konfigurasi: **Docker** (PHP 8.4‑FPM, Composer, Node untuk **Ant Design via
   Inertia+React**, **MySQL 8**, Redis + queue worker, scheduler). Standarkan DB → MySQL (hapus
   PostgreSQL di deployment `prompt.md`). Pin Laravel ke rilis stabil terbaru yang kompatibel PHP 8.4.
2. Minta file/keputusan blocker: **U1** (Lincah), **U2** (master ADV_CS/Produk), **U4** (EDC),
   **U5/U6/U7/U10** (rincian komisi).
3. Bangun migrasi + model sesuai §11; **`commission_rules` & `carrier_status_mappings`** harus
   *reviewable/editioned* (tanggal berlaku) agar periode tertutup tak berubah.
4. Implementasi §4.3 persis + test kasus: baru, pernah‑diterima, pernah‑retur, diproses, retur‑terakhir.

---
*Rujukan ekstraksi: `out/audit/*.txt` (ID 01 Admin & Upload Mengantar), `out/audit/_outputresi_f.txt`
(OutputResi), dan `tools/extract-formulas.ps1` (untuk tab Master/Marketing/Komisi).*
