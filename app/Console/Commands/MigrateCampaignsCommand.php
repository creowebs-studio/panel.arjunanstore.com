<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Import\MarketingImporter;
use App\Services\Import\MarketingPreparer;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Migrasi laporan kampanye marketing dari tab `Rekap` (Tahap 6; audit STAGE1 §8).
 * Melewati jalur impor resmi yang sama dengan unggahan web (MarketingPreparer →
 * MarketingImporter) sehingga idempotensi, pemetaan kampanye, dan worklist kode
 * tak dikenal berperilaku identik.
 */
class MigrateCampaignsCommand extends Command
{
    protected $signature = 'arj:migrate-campaigns
        {--file=out/migrasi/rekap_marketing.csv : CSV tab Rekap (tools/xlsx-to-csv.ps1 -HeaderRow 2 -StartRow 3 -DateCols A,B,N)}
        {--user= : Email user pencatat batch (default: user pertama)}';

    protected $description = 'Impor laporan kampanye (Rekap) dari workbook lewat mesin impor (idempoten).';

    public function handle(MarketingPreparer $preparer, MarketingImporter $importer): int
    {
        try {
            $file = $this->option('file');
            $path = preg_match('~^(/|[A-Za-z]:)~', (string) $file) === 1 ? (string) $file : base_path((string) $file);
            if (! is_file($path)) {
                throw new RuntimeException("File CSV tidak ditemukan: {$path}");
            }

            $email = (string) $this->option('user');
            $user = $email !== '' ? User::where('email', $email)->first() : User::orderBy('id')->first();
            if (! $user) {
                throw new RuntimeException($email !== ''
                    ? "User dengan email {$email} tidak ditemukan."
                    : 'Tidak ada user di basis data — jalankan seeder admin terlebih dahulu.');
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Membaca & memetakan '.basename($path).' ...');
        $upload = new UploadedFile($path, basename($path), 'text/csv', null, true); // test-mode: tak dipindah
        $batch = $preparer->prepare($upload, $user);
        $batch = $importer->process($batch);

        $this->table(
            ['Hasil', 'Jumlah'],
            [
                ['new', $batch->new_rows],
                ['update', $batch->updated_rows],
                ['duplicate', $batch->duplicate_rows],
                ['error', $batch->error_rows],
                ['total baris', $batch->total_rows],
            ]
        );
        $this->info("Batch #{$batch->id} selesai. Kampanye tanpa kode dikenal tetap tersimpan (is_mapped=false) dan muncul di worklist Data Error.");

        return self::SUCCESS;
    }
}
