<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\Import\ImportPreparer;
use App\Services\Import\ShipmentImporter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Alur C — impor bertahap hasil Mengantar/Lincah (prompt.md §6):
 * unggah → baca → validasi → pratinjau → konfirmasi → proses → laporan.
 * Khusus Admin Pengiriman (+superadmin) — ditegakkan via middleware role di rute.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly ImportPreparer $preparer,
        private readonly ShipmentImporter $importer,
    ) {
    }

    public function index()
    {
        return Inertia::render('Imports/Index', [
            'batches' => ImportBatch::with('uploader')->latest('id')->limit(25)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform' => ['required', 'in:mengantar,lincah'],
            'file'     => ['required', 'file', 'max:5120'], // 5 MB
        ]);

        $batch = $this->preparer->prepare($data['file'], $data['platform'], $request->user());

        return redirect()->route('imports.preview', $batch)
            ->with('flash', "File terbaca: {$batch->total_rows} baris. Periksa pratinjau lalu konfirmasi.");
    }

    public function preview(ImportBatch $batch)
    {
        $rows = $batch->rows()->orderBy('row_number')->limit(100)->get();
        $summary = $batch->rows()
            ->selectRaw('result, COUNT(*) c')->groupBy('result')->pluck('c', 'result')->all();

        return Inertia::render('Imports/Preview', compact('batch', 'rows', 'summary'));
    }

    public function process(ImportBatch $batch)
    {
        abort_if(in_array($batch->status, ['done', 'processing'], true), 409, 'Batch ini sudah diproses.');

        $batch->update(['status' => 'processing']);
        $batch = $this->importer->process($batch);

        return redirect()->route('imports.preview', $batch)->with('flash',
            "Impor selesai — baru: {$batch->new_rows}, diperbarui: {$batch->updated_rows}, "
            . "duplikat: {$batch->duplicate_rows}, error: {$batch->error_rows}.");
    }

    /**
     * Proses ulang baris error saja (prompt.md §6) — baris yang sudah berhasil
     * tidak diulang. Berguna setelah admin memperbaiki pemetaan status.
     */
    public function reprocess(ImportBatch $batch)
    {
        abort_unless($batch->status === 'done', 409, 'Batch ini belum diproses.');
        abort_unless($batch->rows()->where('result', 'error')->exists(), 404, 'Tidak ada baris error pada batch ini.');

        $batch = $this->importer->reprocessErrors($batch);

        return redirect()->route('imports.preview', $batch)->with('flash',
            "Proses ulang baris error selesai — baru: {$batch->new_rows}, diperbarui: {$batch->updated_rows}, "
            . "duplikat: {$batch->duplicate_rows}, error: {$batch->error_rows}.");
    }
}
