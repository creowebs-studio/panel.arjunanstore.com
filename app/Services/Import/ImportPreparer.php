<?php

namespace App\Services\Import;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Tahap awal impor: unggah → baca → petakan → simpan sebagai batch "preview"
 * (belum menyentuh master resi). Pemrosesan nyata dilakukan `ShipmentImporter` setelah konfirmasi.
 */
class ImportPreparer
{
    public function __construct(
        private readonly CsvImportReader $reader,
        private readonly MengantarRowMapper $mapper,
    ) {
    }

    public function prepare(UploadedFile $file, string $platform, User $user): ImportBatch
    {
        $checksum = hash_file('sha256', (string) $file->getRealPath());

        // Simpan file asli ke penyimpanan privat (prompt.md §2 "file: penyimpanan privat").
        $stored = $file->storeAs(
            'imports/' . $platform,
            now()->format('Ymd-His') . '-' . Str::random(6) . '-' . Str::slug($file->getClientOriginalName()) . '.csv',
            'local'
        );

        $batch = ImportBatch::create([
            'batch_uuid'      => (string) Str::uuid(),
            'platform'        => $platform,
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
            $mapped = $this->mapper->map($raw, $platform);
            ImportRow::create([
                'import_batch_id'   => $batch->id,
                'row_number'        => $n,
                'tracking_id'       => $mapped['tracking_id'],
                'platform_order_id' => $mapped['platform_order_id'],
                'raw_data'          => $raw,
                'mapped_data'       => $mapped,
                'result'            => $mapped['_missing_key'] ? 'error' : 'new',
                'error_reason'      => $mapped['_missing_key'] ? 'Tracking/Order ID kosong' : null,
            ]);
        }

        return $batch;
    }
}
