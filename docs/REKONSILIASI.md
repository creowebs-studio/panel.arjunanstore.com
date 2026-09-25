# Rekonsiliasi Tahap 6 — OutputResi workbook vs Website

- Waktu: 2026-09-24 14:37
- Berkas: `outputresi.csv` (ekspor tab OutputResi Master ARJ.xlsx, nilai cache rumus)
- Sampel: baris ke-0 s/d 300 dari 300 baris ber-no AWB
- Pembanding website: `CommissionCalculator` (replikasi rantai P..AM audit §9.2)

## Ringkasan

| Metrik | Nilai |
| --- | --- |
| Sampel diperiksa | 300 |
| Ditemukan di website | 300 |
| **Cocok persis (AD, V, AG, AJ, AK, AM)** | **186** |
| Selisih | 114 |
| Tidak ditemukan (belum termigrasi/luar sampel impor) | 0 |
| Tanpa status internal (Data Error status) | 0 |

| Agregat (baris ditemukan) | Buku (Rp) | Website (Rp) | Δ (Rp) |
| --- | --- | --- | --- |
| Σ Total Komisi CS (AK) | 1.157.012,93 | 1.343.457,87 | 186.444,94 |
| Σ Laba Kotor Eksplisit (AM) | 20.901.711,18 | 20.715.266,23 | -186.444,95 |

## Selisih per resi

| No AWB | AK buku | AM buku | Penyebab (dugaan) |
| --- | --- | --- | --- |
| IDE7003321084022 | 8.292,63 | 70.377,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AM Δ-0,01 |
| IDE7001058986588 | 8.292,63 | 70.377,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AM Δ-0,01 |
| JO0322075496 | 3.500,00 | 132.000,00 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.600,00; AM Δ-13.600,00 |
| JO0322402343 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322466182 | 6.542,63 | 65.127,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322082564 | 6.342,83 | 64.528,48 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322203224 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0321098350 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322421769 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322518316 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0321966904 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322288142 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322415015 | 6.575,93 | 65.227,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322496278 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0321748456 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0321620052 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| 137812600608263 | 6.626,13 | 65.378,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322416479 | 6.476,03 | 64.928,08 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322551162 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322231178 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322130352 | 6.426,08 | 64.778,23 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| IDE7001497247510 | 17.843,30 | 119.029,90 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.737,32; AM Δ-13.737,32 |
| JO0322571964 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322530925 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322589189 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322562996 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321822005 | 6.542,63 | 65.127,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322539956 | 20.884,95 | 128.154,85 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ14.953,98; AM Δ-14.953,98 |
| IDE7000860881747 | 19.568,35 | 124.205,05 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ14.427,34; AM Δ-14.427,34 |
| IDE7001967258633 | 6.017,63 | 63.552,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AK Δ0,01 |
| IDE7002377391901 | 7.517,63 | 68.052,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01; AM Δ-0,01 |
| IDE7001407167167 | 3.750,98 | 56.752,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ0,01 |
| IDE7002634898029 | 5.800,93 | 62.902,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ0,01 |
| JO0322373606 | 8.092,58 | 52.777,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| IDE7001426382440 | 8.292,63 | 70.377,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AM Δ-0,01 |
| JO0322445351 | 8.542,63 | 54.127,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01 |
| JO0322461885 | 5.709,38 | 45.628,13 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AK Δ0,01 |
| JO0322163801 | 6.426,08 | 64.778,23 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322548765 | 6.376,13 | 64.628,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322453390 | 6.376,13 | 64.628,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321018270 | 6.492,68 | 64.978,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321981209 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322391548 | 7.325,93 | 67.477,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321672793 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322578050 | 6.342,83 | 64.528,48 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0321219574 | 6.376,13 | 64.628,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322153972 | 6.492,68 | 64.978,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321907053 | 7.325,93 | 67.477,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322152452 | 7.325,93 | 67.477,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322557904 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321982092 | 7.075,93 | 66.727,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| 137812600620433 | 6.626,13 | 65.378,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322144904 | 6.792,63 | 65.877,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01; AM Δ-0,01 |
| JO0322198488 | 6.492,68 | 64.978,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322276264 | 7.075,93 | 66.727,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322532823 | 7.075,93 | 66.727,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322552386 | 7.009,33 | 66.527,98 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0321824763 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322508890 | 6.659,43 | 65.478,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321560500 | 6.659,43 | 65.478,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322002305 | 5.976,03 | 46.428,08 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| 317982600383647 | -257,33 | 27.728,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ0,01 |
| JO0322441085 | 7.992,68 | 52.478,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322131229 | 4.709,13 | 42.627,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AK Δ-0,01 |
| JO0321659862 | 6.342,83 | 64.528,48 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322593069 | -1.248,50 | 121.754,50 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ12.100,60; AM Δ-12.100,60 |
| JO0322516318 | 6.792,63 | 65.877,88 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01; AM Δ-0,01 |
| JO0322647850 | 6.592,58 | 65.277,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322390443 | 6.376,13 | 64.628,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322537654 | 6.259,33 | 64.277,98 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322412895 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322482694 | 6.726,03 | 65.678,08 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322588807 | 835,00 | 128.005,00 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ12.934,00; AM Δ-12.934,00 |
| 137812600630028 | 5.659,43 | 62.478,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ0,01 |
| 137812600630010 | 7.376,13 | 67.628,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01; AM Δ-0,01 |
| JO0320990405 | 4.642,53 | 42.427,58 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| IDE7002352595825 | 12.742,68 | 66.728,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322166329 | 4.959,13 | 43.377,38 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ-0,01; AK Δ-0,01 |
| JO0322669227 | 6.859,48 | 66.078,43 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322621783 | 6.659,43 | 65.478,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322664566 | 6.825,93 | 65.977,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322145148 | 6.525,98 | 65.077,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322539903 | 6.492,68 | 64.978,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322653750 | 6.176,33 | 64.028,98 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322694713 | 6.759,33 | 65.777,98 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322645070 | 6.742,68 | 65.728,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322243856 | 7.259,33 | 67.277,98 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322663689 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322669436 | 6.775,98 | 65.827,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322531635 | 6.659,43 | 65.478,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322699768 | 1.168,25 | 129.004,75 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.067,30; AM Δ-13.067,30 |
| JO0322687258 | 6.742,68 | 65.728,03 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322537488 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322530903 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322498356 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322272574 | 6.692,73 | 65.578,18 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322300620 | 1.026,73 | 128.580,18 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AJ Δ0,01; AK Δ13.010,70; AM Δ-13.010,70 |
| JO0320144004 | 6.692,73 | 65.578,18 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322425925 | 1.068,35 | 128.705,05 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.027,34; AM Δ-13.027,34 |
| JO0320940369 | 6.775,98 | 65.827,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322539082 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322446304 | 6.775,98 | 65.827,93 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322441036 | 5.825,93 | 62.977,78 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ0,01 |
| JO0322580406 | 6.692,73 | 65.578,18 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0321729868 | 6.692,73 | 65.578,18 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322229131 | 1.409,93 | 129.729,78 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.163,98; AM Δ-13.163,98 |
| JO0321116682 | 1.168,25 | 129.004,75 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.067,30; AM Δ-13.067,30 |
| JO0322037483 | 6.609,48 | 65.328,43 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| JO0322495950 | 3.750,00 | 132.750,00 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.700,00; AM Δ-13.700,00 |
| JO0322584123 | 6.842,58 | 66.027,73 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ-0,01 |
| JO0322368299 | 1.168,25 | 129.004,75 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ13.067,30; AM Δ-13.067,30 |
| IDE7001528520376 | 5.634,43 | 62.403,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AM Δ0,01 |
| IDE7003454828919 | 7.634,43 | 68.403,28 | pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — AJ Δ0,01; AK Δ0,01 |
| IDE7000160005384 | -31,57 | 125.405,28 | sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — AK Δ12.587,38; AM Δ-12.587,38 |

## Catatan penyebab & lingkup

- **Aturan rumus disalin apa adanya** dari workbook, termasuk `V = S−U−Y` dan `AM = (P+Q)−R−(T+U)−AD−AK−AL` (audit §9.2).
- Baris dengan `Kode ADVS/CS = "XXXX"` di workbook (remark tak dikenal) juga menjadi Data Error di website — bukan selisih.
- Lima workbook tidak memuat salinan **DBLincah** (blokir U1 audit): baris OutputResi yang berasal dari DBLincah tidak dapat dibandingkan dan tidak ikut dimigrasi — ditandai "tidak ditemukan".
- Master **CS/ADV** pada 'ADV_CS' dan **Produk** termigrasi dari workbook yang sama (peta token `MID(AB,7,3)`/`MID(AB,10,2)`), sehingga atribusi ADV/CS/Produk dapat dibandingkan dengan kolom E/F/G buku.
- Bila muncul selisih pada AG/AJ: periksa `min_price_by_qty` produk dan tarif `commission_rules` (Setup Komisi CS) pada tanggal resi.
- **Sel AG (Komisi CS Order) kosong di workbook**: rumus tier tidak menyimpan nilai pada sebagian baris; website menghitungnya dari AF sehingga AK naik dan AM turun persis sebesar itu (bukan salah data).
- **Selisih ±0,01** murni kebijakan pembulatan: workbook menyimpan nilai berdesimal (mis. AJ = 3.292,625) dan membulatkan hanya saat tampil; website membulatkan tiap komponen ke 2 desimal.
