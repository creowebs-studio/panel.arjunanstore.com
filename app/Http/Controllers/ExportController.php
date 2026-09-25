<?php

namespace App\Http\Controllers;

use App\Models\ExportBatch;
use App\Models\Order;
use App\Services\OrderExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Alur B — ekspor order POSITIF yang lengkap datanya ke format Mengantar/Lincah (prompt.md §5).
 * Order negatif/tidak lengkap tidak pernah ikut. Ekspor ulang ditandai is_reexport.
 */
class ExportController extends Controller
{
    public function __construct(private readonly OrderExporter $exporter)
    {
    }

    public function index(Request $request)
    {
        return Inertia::render('Exports/Index', [
            'mengantar' => $this->complete($this->exportableQuery($request)->where('aggregator', 'mengantar')->get()),
            'lincah'    => $this->complete($this->exportableQuery($request)->where('aggregator', 'lincah')->get()),
            'batches'   => ExportBatch::with('user')->latest('id')->limit(10)->get(),
            'filters'   => [
                'from' => $request->date('from')?->format('Y-m-d'),
                'to'   => $request->date('to')?->format('Y-m-d'),
            ],
        ]);
    }

    public function download(Request $request, string $platform)
    {
        abort_unless(in_array($platform, ['mengantar', 'lincah'], true), 404);

        $orders = $this->complete($this->exportableQuery($request)
            ->where('aggregator', $platform)
            ->where('classification', 'positif')
            ->with('customer')
            ->get());

        abort_if($orders->isEmpty(), 404, 'Tidak ada order positif lengkap untuk diekspor.');

        $alreadyExported = $this->alreadyExportedPlatforms($orders, $platform);
        $batch = ExportBatch::create([
            'batch_uuid'  => (string) Str::uuid(),
            'platform'    => $platform,
            'user_id'     => $request->user()->id,
            'filename'    => null,
            'status'      => 'downloaded',
            'order_count' => $orders->count(),
            'is_reexport' => $alreadyExported > 0,
            'notes'       => $alreadyExported > 0 ? "{$alreadyExported} order pernah diekspor sebelumnya" : null,
        ]);
        foreach ($orders as $o) {
            $batch->items()->create(['order_id' => $o->id]);
        }
        $filename = "OutputPositif-" . ucfirst($platform) . "-" . now()->format('Ymd-His') . ".csv";
        $batch->update(['filename' => $filename]);

        $csv = $this->exporter->toCsv($platform, $orders);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function exportableQuery(Request $request)
    {
        $q = Order::query()->where('classification', 'positif')->with(['customer', 'product']);

        if ($from = $request->date('from')) {
            $q->whereDate('order_date', '>=', $from);
        }
        if ($to = $request->date('to')) {
            $q->whereDate('order_date', '<=', $to);
        }
        if ($cs = $request->integer('cs_agent_id')) {
            $q->where('cs_agent_id', $cs);
        }
        if ($prod = $request->integer('product_id')) {
            $q->where('product_id', $prod);
        }

        // "data wajib lengkap" (Order::isExportable) tidak dapat ekspresi SQL penuh → filter in-memory.
        return $q;
    }

    /** Hanya order positif DENGAN data wajib lengkap (Order::isExportable) yang boleh diekspor. */
    private function complete($orders)
    {
        return $orders->filter(fn (Order $o) => $o->isExportable())->values();
    }

    private function alreadyExportedPlatforms($orders, string $platform): int
    {
        $ids = $orders->pluck('id')->all();

        return \App\Models\ExportBatchItem::whereIn('order_id', $ids)
            ->whereHas('exportBatch', fn ($q) => $q->where('platform', $platform))
            ->distinct()
            ->count('order_id');
    }
}
