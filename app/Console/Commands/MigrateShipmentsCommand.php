<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Import\DbMengantarRowMapper;
use App\Services\Import\ShipmentImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Migrasi master resi dari tab `DBMengantar` (Tahap 6, audit STAGE1 §7.1).
 *
 * Alur: CSV (tools/xlsx-to-csv.ps1) → `ImportBatch` + `import_rows` (raw + mapped)
 * → `ShipmentImporter` (idempoten: impor ulang file sama → `duplicate`, bukan gandakan;
 * riwayat status via `shipment_status_events`; masalah → Data Error).
 *
 * Peta kode ADV/CS/Produk dibangun dari CSV pendamping `adv_cs.csv` dan `produk.csv`
 * (hasil `xlsx-to-csv.ps1`) sehingga token remark numerik `MID(AB,7,3)` / `MID(AB,10,2)`
 * di-resolve persis rumus workbook.
 */
class MigrateShipmentsCommand extends Command
{
    protected $signature = 'arj:migrate-shipments
        {--file=out/migrasi/dbmengantar.csv : CSV sheet DBMengantar (tools/xlsx-to-csv.ps1 -StartRow 2 -DateTimeCols P,Q)}
        {--advcs=out/migrasi/adv_cs.csv : CSV ADV_CS pendamping untuk peta token}
        {--produk=out/migrasi/produk.csv : CSV Produk pendamping untuk peta token}
        {--limit=0 : Batasi jumlah baris (0 = semua)}
        {--user= : Email user pencatat batch (default: user pertama)}';

    protected $description = 'Impor master resi DBMengantar dari workbook lewat mesin impor (idempoten).';

    public function handle(ShipmentImporter $importer): int
    {
        try {
            $file = $this->absolutePath($this->option('file'));
            $this->assertFile($file);
            $user = $this->resolveUser((string) $this->option('user'));
            $advCsMap = $this->buildAdvCsMap($this->absolutePath($this->option('advcs')));
            $productMap = $this->buildProductMap($this->absolutePath($this->option('produk')));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));

        $batch = ImportBatch::create([
            'batch_uuid'      => (string) Str::uuid(),
            'platform'        => 'mengantar',
            'source_type'      => 'migration',
            'source_filename' => basename($file),
            'checksum'        => hash_file('sha256', $file) ?: null,
            'uploaded_by'     => $user->id,
            'status'          => 'reading',
        ]);

        $mapper = new DbMengantarRowMapper(app(\App\Services\PhoneNormalizer::class), $advCsMap, $productMap);

        $fh = fopen($file, 'r');
        $header = fgetcsv($fh) ?: [];
        $header = array_map(fn ($h) => trim((string) $h), $header);

        $n = 0;
        $buffer = [];
        $bar = $this->output->createProgressBar();
        while (($cells = fgetcsv($fh)) !== false) {
            if ($limit > 0 && $n >= $limit) {
                break;
            }
            $n++;

            $raw = [];
            foreach ($header as $i => $letter) {
                $raw[$letter] = (string) ($cells[$i] ?? '');
            }
            $mapped = $mapper->map($raw);

            $buffer[] = [
                'import_batch_id'   => $batch->id,
                'row_number'        => $n,
                'tracking_id'       => $mapped['tracking_id'],
                'platform_order_id' => $mapped['platform_order_id'],
                'raw_data'          => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'mapped_data'       => json_encode($mapped, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'result'            => ! empty($mapped['_missing_key']) ? 'error' : 'new',
                'error_reason'      => ! empty($mapped['_missing_key']) ? 'Tracking ID / Order ID kosong.' : null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            if (count($buffer) >= 500) {
                DB::table('import_rows')->insert($buffer);
                $buffer = [];
                $bar->advance(500);
            }
        }
        if ($buffer !== []) {
            DB::table('import_rows')->insert($buffer);
            $bar->advance(count($buffer));
        }
        fclose($fh);
        $bar->finish();
        $this->newLine(2);

        $batch->update(['total_rows' => $n]);

        $this->info("Memproses {$n} baris lewat ShipmentImporter...");
        $batch = $importer->process($batch);

        $linked = DB::table('shipments as s')
            ->join('campaigns as c', 'c.name', '=', 's.campaign_name')
            ->whereNull('s.campaign_id')
            ->update(['s.campaign_id' => DB::raw('c.id')]);

        $this->table(
            ['Hasil', 'Jumlah'],
            [
                ['new', $batch->new_rows],
                ['update', $batch->updated_rows],
                ['duplicate', $batch->duplicate_rows],
                ['error', $batch->error_rows],
                ['shipment tertaut kampanye', $linked],
                ['total shipment (mengantar)', Shipment::where('platform', 'mengantar')->count()],
            ]
        );
        $this->info("Batch #{$batch->id} selesai. Jalankan ulang file yang sama untuk membuktikan idempotensi (semua baris → duplicate).");

        return self::SUCCESS;
    }

    /** @return array<string, array{adv:string, cs:string}> */
    private function buildAdvCsMap(string $path): array
    {
        $this->assertFile($path);
        $fh = fopen($path, 'r');
        fgetcsv($fh); // header
        $map = [];
        while (($row = fgetcsv($fh)) !== false) {
            $token = DbMengantarRowMapper::normalizeToken((string) ($row[8] ?? '')); // I "Kode CS" numerik (101…)
            $cs = trim((string) ($row[1] ?? ''));                                     // B "Kode CS" (GL01/AR01)
            $adv = trim((string) ($row[4] ?? ''));                                    // E "Kode ADV" (AM01…)
            if ($token === '' || $cs === '' || $adv === '') {
                continue;
            }
            $map[$token] = ['adv' => $adv, 'cs' => $cs];
        }
        fclose($fh);

        return $map;
    }

    /** @return array<string, string> token kode resi produk → kode produk */
    private function buildProductMap(string $path): array
    {
        $this->assertFile($path);
        $fh = fopen($path, 'r');
        fgetcsv($fh); // header
        $map = [];
        while (($row = fgetcsv($fh)) !== false) {
            $token = DbMengantarRowMapper::normalizeToken((string) ($row[3] ?? '')); // D "Kode Resi Produk" numerik
            $kode = trim((string) ($row[2] ?? ''));                                   // C "Kode Produk" (PR/PG…)
            if ($token === '' || $kode === '') {
                continue;
            }
            $map[$token] = $kode;
        }
        fclose($fh);

        return $map;
    }

    private function resolveUser(string $email): User
    {
        $user = $email !== ''
            ? User::where('email', $email)->first()
            : User::orderBy('id')->first();
        if (! $user) {
            throw new RuntimeException($email !== ''
                ? "User dengan email {$email} tidak ditemukan."
                : 'Tidak ada user di basis data — jalankan seeder admin terlebih dahulu.');
        }

        return $user;
    }

    private function absolutePath(string $path): string
    {
        if ($path === '') {
            throw new RuntimeException('Path CSV kosong.');
        }

        return preg_match('~^(/|[A-Za-z]:)~', $path) === 1 ? $path : base_path($path);
    }

    private function assertFile(string $path): void
    {
        if (! is_file($path)) {
            throw new RuntimeException("File CSV tidak ditemukan: {$path}");
        }
    }
}
