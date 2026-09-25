<?php

namespace App\Console\Commands;

use App\Models\Advertiser;
use App\Models\CsAgent;
use App\Models\Product;
use App\Services\Import\DbMengantarRowMapper;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Migrasi master referensi dari workbook (Tahap 6; audit STAGE1 §3):
 *  - `ADV_CS`  → advertisers + cs_agents (lookup_key = kolom B, mis. GL01/AR01)
 *  - `Produk`  → products (code = kolom D numerik, resi_code = kolom C, HPP/packing/OPS,
 *                harga jual minimum per qty kolom L..U)
 *
 * CSV dihasilkan `tools/xlsx-to-csv.ps1` (lihat docs/MIGRASI.md). Idempoten: dijalankan
 * berulang tidak menggandakan baris (upsert).
 */
class MigrateMastersCommand extends Command
{
    protected $signature = 'arj:migrate-masters
        {--advcs=out/migrasi/adv_cs.csv : CSV sheet ADV_CS (tools/xlsx-to-csv.ps1 -HeaderRow 2 -StartRow 3)}
        {--produk=out/migrasi/produk.csv : CSV sheet Produk (tools/xlsx-to-csv.ps1 -HeaderRow 1 -StartRow 3)}';

    protected $description = 'Impor master advertisers/cs_agents/products dari workbook (idempoten).';

    public function handle(): int
    {
        try {
            $advRows = $this->readCsv($this->absolutePath($this->option('advcs')));
            $prodRows = $this->readCsv($this->absolutePath($this->option('produk')));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! str_contains(implode('|', $advRows[0] ?? []), 'Kode ADV')) {
            $this->error('Header ADV_CS tidak dikenali. Ekspor ulang: tools/xlsx-to-csv.ps1 -Path "Upload Mengantar 2026.xlsx" -Sheet ADV_CS -HeaderRow 2 -StartRow 3 -OutFile out\migrasi\adv_cs.csv');

            return self::FAILURE;
        }

        $stats = ['adv_c' => 0, 'adv_u' => 0, 'cs_c' => 0, 'cs_u' => 0, 'prod_c' => 0, 'prod_u' => 0, 'prod_skip' => 0];

        // --- ADV_CS → advertisers + cs_agents -----------------------------------
        foreach (array_slice($advRows, 1) as $row) {
            $kodeAdv = trim((string) ($row[4] ?? ''));
            if ($kodeAdv === '') {
                continue;
            }

            $adv = Advertiser::where('code', $kodeAdv)->first() ?? new Advertiser(['code' => $kodeAdv]);
            $adv->wasRecentlyCreated ? $stats['adv_c']++ : $stats['adv_u']++;
            $adv->fill(['name' => trim((string) ($row[5] ?? '')) ?: $kodeAdv, 'is_active' => true])->save();

            $kodeCs = trim((string) ($row[1] ?? ''));
            if ($kodeCs === '') {
                continue;
            }
            $cs = CsAgent::where('lookup_key', $kodeCs)->orWhere('code', $kodeCs)->first() ?? new CsAgent();
            $cs->exists ? $stats['cs_u']++ : $stats['cs_c']++;
            $cs->fill([
                'code'          => $kodeCs,
                'lookup_key'    => $kodeCs,
                'name'          => trim((string) ($row[2] ?? '')) ?: $kodeCs,
                'resi_cs_code'  => $kodeCs,
                'advertiser_id' => $adv->id,
                'is_active'     => true,
            ])->save();
        }

        // --- Produk → products --------------------------------------------------
        foreach (array_slice($prodRows, 1) as $row) {
            $resiCode = trim((string) ($row[2] ?? ''));                          // C "Kode Produk"
            $kode = DbMengantarRowMapper::normalizeToken((string) ($row[3] ?? '')); // D "Kode Resi Produk" (numerik)

            $prod = $resiCode !== '' ? Product::where('resi_code', $resiCode)->first() : null;
            $prod ??= $kode !== '' ? Product::where('code', $kode)->first() : null;

            if (! $prod && ($resiCode === '' || $kode === '')) {
                $stats['prod_skip']++;

                continue;
            }
            $prod ??= new Product();
            $prod->exists ? $stats['prod_u']++ : $stats['prod_c']++;

            $tiers = [];
            for ($i = 0; $i < 10; $i++) {                       // L..U = harga jual min qty 1..10
                $v = trim((string) ($row[11 + $i] ?? ''));
                if ($v !== '' && (float) $v > 0) {
                    $tiers[(string) ($i + 1)] = (float) $v;
                }
            }

            $payload = [
                'category'         => trim((string) ($row[1] ?? '')) ?: null,
                'name'             => trim((string) ($row[4] ?? '')) ?: $resiCode,
                'hpp'              => (float) ($row[5] ?? 0),
                'packing_cost'     => (float) ($row[6] ?? 0),
                'ops_cost'         => (float) ($row[7] ?? 0),
                'komisi_cs_input'  => (float) ($row[8] ?? 0),
                'cogs'             => (float) ($row[9] ?? 0),
                'qty_per_paket'    => max(1, (int) ((float) ($row[10] ?? 1))),
                'min_price_by_qty' => $tiers ?: null,
                'is_active'        => true,
            ];
            if ($resiCode !== '') {
                $payload['resi_code'] = $resiCode;
            }
            if ($kode !== '') {
                $payload['code'] = $kode;
            }
            $prod->fill($payload)->save();
        }

        $this->table(
            ['Entitas', 'Baru', 'Diperbarui'],
            [
                ['advertisers', $stats['adv_c'], $stats['adv_u']],
                ['cs_agents', $stats['cs_c'], $stats['cs_u']],
                ['products', $stats['prod_c'], $stats['prod_u'] . ($stats['prod_skip'] ? " ({$stats['prod_skip']} dilewati)" : '')],
            ]
        );
        $this->info('Migrasi master selesai.');

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Path CSV kosong.');
        }

        return preg_match('~^(/|[A-Za-z]:)~', $path) === 1 ? $path : base_path($path);
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new RuntimeException("File CSV tidak dapat dibuka: {$path}");
        }
        $rows = [];
        while (($cells = fgetcsv($fh)) !== false) {
            $rows[] = array_map(fn ($c) => (string) $c, $cells);
        }
        fclose($fh);

        return $rows;
    }
}
