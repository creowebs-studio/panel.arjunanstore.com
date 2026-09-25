<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Tahap awal impor laporan kampanye marketing (Alur E, prompt.md §8):
 * unggah → baca → petakan → batch "preview" (belum menyentuh campaigns/reports).
 * Pemrosesan nyata dilakukan `MarketingImporter` setelah konfirmasi.
 */
class MarketingPreparer
{
    public function __construct(
        private readonly CsvImportReader $reader,
        private readonly MarketingRowMapper $mapper,
    ) {
    }

    public function prepare(UploadedFile $file, User $user): ImportBatch
    {
        $checksum = hash_file('sha256', (string) $file->getRealPath());

        $stored = $file->storeAs(
            'imports/marketing',
            now()->format('Ymd-His') . '-' . Str::random(6) . '-' . Str::slug($file->getClientOriginalName()) . '.csv',
            'local'
        );

        $batch = ImportBatch::create([
            'batch_uuid'      => (string) Str::uuid(),
            'platform'        => 'marketing',
            'source_type'     => 'file',
            'source_filename' => $file->getClientOriginalName(),
            'stored_path'     => $stored,
            'checksum'        => $checksum,
            'uploaded_by'     => $user->id,
            'status'          => 'preview',
        ]);

        $rows = $this->reader->read($file);
        $batch->update(['total_rows' => count($rows)]);

        $n = 0;
        foreach ($rows as $raw) {
            $n++;
            $mapped = $this->mapper->map($raw);
            ImportRow::create([
                'import_batch_id' => $batch->id,
                'row_number'      => $n,
                'tracking_id'     => null,
                'platform_order_id' => null,
                'raw_data'        => $raw,
                'mapped_data'     => $mapped,
                'result'          => $mapped['_missing_key'] ? 'error' : 'new',
                'error_reason'    => $mapped['_missing_key'] ? 'Nama kampanye / tanggal pelaporan kosong' : null,
            ]);
        }

        return $batch;
    }
}
