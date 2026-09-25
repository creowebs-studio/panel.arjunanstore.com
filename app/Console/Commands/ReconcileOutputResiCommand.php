<?php

namespace App\Console\Commands;

use App\Models\Shipment;
use App\Services\CommissionCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Rekonsiliasi Tahap 6: bandingkan hasil website dengan workbook.
 *
 * Sumber pembanding: ekspor tab `OutputResi` (Master ARJ.xlsx) — kolom nilai TERSIMPAN
 * (cache) hasil rantai rumus P..AM per resi. Untuk sampel resi yang sama, website
 * menghitung ulang lewat `CommissionCalculator` (replikasi rantai yang sama) dan
 * setiap selisih dilaporkan beserta dugaan penyebabnya.
 *
 * Kolom acuan OutputResi (audit STAGE1 §9.2, out/audit/_outputresi_f.txt):
 *   A no AWB · E Kode ADVS · F Kode CS · G Kode Produk · I Create Date · AN Remark
 *   AD COGS Total · V Ongkir(dgn diskon) · AG Komisi Order · AJ Komisi Ongkir
 *   AK Total Komisi CS · AM Laba Kotor Eksplisit · AP Aggregator
 */
class ReconcileOutputResiCommand extends Command
{
    protected $signature = 'arj:reconcile
        {--file=out/migrasi/outputresi.csv : CSV tab OutputResi (tools/xlsx-to-csv.ps1 -StartRow 3 -DateTimeCols I,J)}
        {--limit=50 : Jumlah resi sampel}
        {--offset=0 : Mulai dari baris data ke-N (0-based)}
        {--out=docs/REKONSILIASI.md : Berkas laporan markdown}';

    protected $description = 'Bandingkan OutputResi workbook vs hitungan website per resi, tulis laporan selisih.';

    /** Kolom OutputResi yang dibandingkan → field hasil CommissionCalculator. */
    private const COMPARE = [
        'AD' => 'ad_cogs_total',
        'V'  => 'v',
        'AG' => 'ag_komisi_order',
        'AJ' => 'aj_komisi_ongkir',
        'AK' => 'ak_komisi_total',
        'AM' => 'am_laba_eksplisit',
    ];

    public function handle(CommissionCalculator $calculator): int
    {
        try {
            $path = $this->absolutePath((string) $this->option('file'));
            if (! is_file($path)) {
                throw new RuntimeException("File CSV tidak ditemukan: {$path}");
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));

        $fh = fopen($path, 'r');
        $header = array_map(fn ($h) => trim((string) $h), fgetcsv($fh) ?: []);
        if (! in_array('A', $header, true) || ! in_array('AK', $header, true)) {
            $this->error('Header huruf kolom tidak ditemukan — ekspor ulang dengan tools/xlsx-to-csv.ps1 tanpa -HeaderRow.');

            return self::FAILURE;
        }

        $totalRows = 0;
        $sampled = [];
        while (($cells = fgetcsv($fh)) !== false) {
            $row = [];
            foreach ($header as $i => $letter) {
                $row[$letter] = (string) ($cells[$i] ?? '');
            }
            if (trim($row['A'] ?? '') === '') {
                continue; // baris kosong / tanpa no AWB
            }
            $totalRows++;
            if ($totalRows > $offset) {
                $sampled[] = $row;
            }
            if (count($sampled) >= $limit) {
                break;
            }
        }
        fclose($fh);

        $found = $matched = $diffs = $missing = $noCalc = 0;
        $sumBookAk = $sumWebAk = $sumBookAm = $sumWebAm = 0.0;
        $diffRows = [];
        $missingRows = [];

        foreach ($sampled as $row) {
            $tracking = trim($row['A']);
            $book = [];
            foreach (array_keys(self::COMPARE) as $letter) {
                $book[$letter] = $this->num($row[$letter] ?? null);
            }

            $shipment = Shipment::where('tracking_id', $tracking)
                ->whereIn('platform', ['mengantar', 'lincah'])
                ->first();

            if (! $shipment) {
                $missing++;
                $missingRows[] = [$tracking, $row['AN'] ?? '', $row['E'] ?? ''];

                continue;
            }
            $found++;

            $calc = $calculator->compute($shipment);
            if ($calc === null) {
                $noCalc++;
                $diffRows[] = [$tracking, 'status internal kosong — tidak dapat dihitung', $book['AK'], $book['AM']];

                continue;
            }

            $sumBookAk += (float) ($book['AK'] ?? 0);
            $sumWebAk += (float) $calc['ak_komisi_total'];
            $sumBookAm += (float) ($book['AM'] ?? 0);
            $sumWebAm += (float) $calc['am_laba_eksplisit'];

            $rowDiffs = [];
            foreach (self::COMPARE as $letter => $field) {
                $b = $book[$letter];
                if ($b === null || $calc[$field] === null) {
                    continue;
                }
                $delta = round((float) $calc[$field] - (float) $b, 2);
                if (abs($delta) > 0.009) {
                    $rowDiffs[$letter] = ['book' => (float) $b, 'web' => (float) $calc[$field], 'delta' => $delta];
                }
            }

            if ($rowDiffs === []) {
                $matched++;
            } else {
                $diffs++;
                $diffRows[] = [$tracking, $this->cause($shipment, $rowDiffs, $row), $book['AK'], $book['AM'], $rowDiffs];
            }
        }

        $report = $this->buildReport($path, $totalRows, $offset, $limit, [
            'sampled' => count($sampled), 'found' => $found, 'matched' => $matched,
            'diffs' => $diffs, 'missing' => $missing, 'no_calc' => $noCalc,
            'sum_book_ak' => $sumBookAk, 'sum_web_ak' => $sumWebAk,
            'sum_book_am' => $sumBookAm, 'sum_web_am' => $sumWebAm,
        ], $diffRows, $missingRows);

        $out = $this->absolutePath((string) $this->option('out'));
        File::ensureDirectoryExists(dirname($out));
        File::put($out, $report);

        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Baris berkas OutputResi', $totalRows],
                ['Sampel diperiksa', count($sampled)],
                ['Ditemukan di website', $found],
                ['Cocok persis (6 kolom)', $matched],
                ['Selisih', $diffs],
                ['Tidak ditemukan', $missing],
                ['Tanpa status internal', $noCalc],
            ]
        );
        $this->line(sprintf('ΣAK  buku=Rp %s  web=Rp %s  Δ=Rp %s', number_format($sumBookAk, 2, ',', '.'), number_format($sumWebAk, 2, ',', '.'), number_format($sumWebAk - $sumBookAk, 2, ',', '.')));
        $this->line(sprintf('ΣAM  buku=Rp %s  web=Rp %s  Δ=Rp %s', number_format($sumBookAm, 2, ',', '.'), number_format($sumWebAm, 2, ',', '.'), number_format($sumWebAm - $sumBookAm, 2, ',', '.')));
        $this->info("Laporan ditulis: {$out}");

        return self::SUCCESS;
    }

    /** Dugaan penyebab selisih + ringkasannya (pola nyata audit Tahap 6). */
    private function cause(Shipment $shipment, array $rowDiffs, array $row): string
    {
        $missing = [];
        $maxAbs = 0.0;
        foreach (['AD', 'V', 'AG', 'AJ', 'AK', 'AM'] as $letter) {
            if (! isset($rowDiffs[$letter])) {
                continue;
            }
            $missing[] = $letter . ' Δ' . number_format($rowDiffs[$letter]['delta'], 2, ',', '.');
            $maxAbs = max($maxAbs, abs((float) $rowDiffs[$letter]['delta']));
        }
        $notes = implode('; ', $missing);

        // Selisih murni pembulatan: buku menyimpan nilai berdesimal (mis. AJ = 3.292,625)
        // dan membulatkan hanya saat tampil; website membulatkan 2 desimal per komponen.
        if ($maxAbs <= 0.0101) {
            return 'pembulatan: buku menyimpan hingga 3 desimal, website membulatkan 2 desimal per komponen — ' . $notes;
        }

        // Sel AG (Komisi CS Order) kosong di workbook — rumus tier tidak menghasilkan nilai;
        // website menghitung tier dari AF sehingga AK naik dan AM turun persis sebesar itu.
        if (trim((string) ($row['AG'] ?? '')) === '' && isset($rowDiffs['AK']) && abs((float) $rowDiffs['AK']['delta']) > 1) {
            return 'sel AG (Komisi CS Order) kosong di workbook — tier komisi dihitung website — ' . $notes;
        }

        if (! $shipment->product_id && ! $shipment->product_resi_code) {
            return 'produk resi kosong — ' . $notes;
        }
        $product = \App\Models\Product::where('resi_code', $shipment->product_resi_code)
            ->orWhere('code', (string) $shipment->product_resi_code)->first();
        if (! $product) {
            return "produk resi \"{$shipment->product_resi_code}\" tak ada di master — {$notes}";
        }
        if ($product->minPriceForQty(max(1, (int) $shipment->quantity)) === null) {
            return 'harga jual minimum qty belum termigrasi — ' . $notes;
        }

        return 'periksa input per resi — ' . $notes;
    }

    private function buildReport(string $file, int $total, int $offset, int $limit, array $s, array $diffRows, array $missingRows): string
    {
        $ts = now()->format('Y-m-d H:i');
        $lines = [];
        $lines[] = '# Rekonsiliasi Tahap 6 — OutputResi workbook vs Website';
        $lines[] = '';
        $lines[] = "- Waktu: {$ts}";
        $lines[] = '- Berkas: `'.basename($file).'` (ekspor tab OutputResi Master ARJ.xlsx, nilai cache rumus)';
        $lines[] = "- Sampel: baris ke-{$offset} s/d ".($offset + $limit)." dari {$total} baris ber-no AWB";
        $lines[] = '- Pembanding website: `CommissionCalculator` (replikasi rantai P..AM audit §9.2)';
        $lines[] = '';
        $lines[] = '## Ringkasan';
        $lines[] = '';
        $lines[] = '| Metrik | Nilai |';
        $lines[] = '| --- | --- |';
        $lines[] = '| Sampel diperiksa | '.$s['sampled'].' |';
        $lines[] = '| Ditemukan di website | '.$s['found'].' |';
        $lines[] = '| **Cocok persis (AD, V, AG, AJ, AK, AM)** | **'.$s['matched'].'** |';
        $lines[] = '| Selisih | '.$s['diffs'].' |';
        $lines[] = '| Tidak ditemukan (belum termigrasi/luar sampel impor) | '.$s['missing'].' |';
        $lines[] = '| Tanpa status internal (Data Error status) | '.$s['no_calc'].' |';
        $lines[] = '';
        $lines[] = '| Agregat (baris ditemukan) | Buku (Rp) | Website (Rp) | Δ (Rp) |';
        $lines[] = '| --- | --- | --- | --- |';
        $lines[] = '| Σ Total Komisi CS (AK) | '.$this->fmt($s['sum_book_ak']).' | '.$this->fmt($s['sum_web_ak']).' | '.$this->fmt($s['sum_web_ak'] - $s['sum_book_ak']).' |';
        $lines[] = '| Σ Laba Kotor Eksplisit (AM) | '.$this->fmt($s['sum_book_am']).' | '.$this->fmt($s['sum_web_am']).' | '.$this->fmt($s['sum_web_am'] - $s['sum_book_am']).' |';
        $lines[] = '';

        if ($diffRows !== []) {
            $lines[] = '## Selisih per resi';
            $lines[] = '';
            $lines[] = '| No AWB | AK buku | AM buku | Penyebab (dugaan) |';
            $lines[] = '| --- | --- | --- | --- |';
            foreach ($diffRows as $d) {
                $lines[] = '| '.$d[0].' | '.$this->fmt((float) $d[2]).' | '.$this->fmt((float) $d[3]).' | '.$d[1].' |';
            }
            $lines[] = '';
        }

        if ($missingRows !== []) {
            $lines[] = '## Tidak ditemukan di website (maks. 20 contoh)';
            $lines[] = '';
            $lines[] = '| No AWB | Remark (AN) | Kode ADVS buku |';
            $lines[] = '| --- | --- | --- |';
            foreach (array_slice($missingRows, 0, 20) as $m) {
                $lines[] = '| '.$m[0].' | '.$m[1].' | '.$m[2].' |';
            }
            $lines[] = '';
        }

        $lines[] = '## Catatan penyebab & lingkup';
        $lines[] = '';
        $lines[] = '- **Aturan rumus disalin apa adanya** dari workbook, termasuk `V = S−U−Y` dan `AM = (P+Q)−R−(T+U)−AD−AK−AL` (audit §9.2).';
        $lines[] = '- Baris dengan `Kode ADVS/CS = "XXXX"` di workbook (remark tak dikenal) juga menjadi Data Error di website — bukan selisih.';
        $lines[] = '- Lima workbook tidak memuat salinan **DBLincah** (blokir U1 audit): baris OutputResi yang berasal dari DBLincah tidak dapat dibandingkan dan tidak ikut dimigrasi — ditandai "tidak ditemukan".';
        $lines[] = '- Master **CS/ADV** pada \'ADV_CS\' dan **Produk** termigrasi dari workbook yang sama (peta token `MID(AB,7,3)`/`MID(AB,10,2)`), sehingga atribusi ADV/CS/Produk dapat dibandingkan dengan kolom E/F/G buku.';
        $lines[] = '- Bila muncul selisih pada AG/AJ: periksa `min_price_by_qty` produk dan tarif `commission_rules` (Setup Komisi CS) pada tanggal resi.';
        $lines[] = '- **Sel AG (Komisi CS Order) kosong di workbook**: rumus tier tidak menyimpan nilai pada sebagian baris; website menghitungnya dari AF sehingga AK naik dan AM turun persis sebesar itu (bukan salah data).';
        $lines[] = '- **Selisih ±0,01** murni kebijakan pembulatan: workbook menyimpan nilai berdesimal (mis. AJ = 3.292,625) dan membulatkan hanya saat tampil; website membulatkan tiap komponen ke 2 desimal.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function num(?string $v): ?float
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }

        return (float) str_replace(',', '.', $v);
    }

    private function fmt(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Path kosong.');
        }

        return preg_match('~^(/|[A-Za-z]:)~', $path) === 1 ? $path : base_path($path);
    }
}
