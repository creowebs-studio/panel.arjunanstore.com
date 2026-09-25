<?php

namespace App\Http\Controllers;

use App\Models\Advertiser;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\CsAgent;
use App\Models\DataIssue;
use App\Models\ImportBatch;
use App\Models\MarketingDailyReport;
use App\Models\Product;
use App\Services\Import\MarketingImporter;
use App\Services\Import\MarketingPreparer;
use App\Services\Marketing\RekapAdvService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Alur E — data marketing & rekap ADV (prompt.md §8; audit §8).
 * Impor laporan kampanye Meta Ads + worklist kode tak dikenal + Rekap ADV↔resi.
 */
class MarketingController extends Controller
{
    public function __construct(
        private readonly MarketingPreparer $preparer,
        private readonly MarketingImporter $importer,
        private readonly RekapAdvService $rekap,
    ) {
    }

    public function index(Request $request)
    {
        $status = $request->input('status');

        $campaigns = Campaign::with(['advertiser', 'csAgent', 'product'])
            ->withCount('dailyReports')
            ->when($status === 'mapped', fn ($q) => $q->where('is_mapped', true))
            ->when($status === 'unmapped', fn ($q) => $q->where('is_mapped', false))
            ->orderBy('is_mapped')->orderBy('name')
            ->paginate(25)->withQueryString();

        return Inertia::render('Marketing/Index', [
            'campaigns' => $campaigns,
            'status'    => $status,
            'stats'     => [
                'total'    => Campaign::count(),
                'mapped'   => Campaign::where('is_mapped', true)->count(),
                'unmapped' => Campaign::where('is_mapped', false)->count(),
                'reports'  => MarketingDailyReport::count(),
                'spend'    => (float) MarketingDailyReport::sum('spend_ppn'),
            ],
            'batches'  => ImportBatch::where('platform', 'marketing')->with('uploader')->latest('id')->limit(10)->get(),
            'unmapped' => Campaign::where('is_mapped', false)->orderBy('name')->limit(50)->get(),
            'advs'     => Advertiser::orderBy('name')->get(),
            'css'      => CsAgent::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5 MB
        ]);

        $batch = $this->preparer->prepare($data['file'], $request->user());

        return redirect()->route('marketing.preview', $batch)
            ->with('flash', "File terbaca: {$batch->total_rows} baris laporan kampanye. Periksa pratinjau lalu konfirmasi.");
    }

    public function preview(ImportBatch $batch)
    {
        abort_unless($batch->platform === 'marketing', 404);

        $rows = $batch->rows()->orderBy('row_number')->limit(100)->get();
        $summary = $batch->rows()
            ->selectRaw('result, COUNT(*) c')->groupBy('result')->pluck('c', 'result')->all();

        return Inertia::render('Marketing/Preview', compact('batch', 'rows', 'summary'));
    }

    public function process(ImportBatch $batch)
    {
        abort_unless($batch->platform === 'marketing', 404);
        abort_if(in_array($batch->status, ['done', 'processing'], true), 409, 'Batch ini sudah diproses.');

        $batch->update(['status' => 'processing']);
        $batch = $this->importer->process($batch);

        return redirect()->route('marketing.preview', $batch)->with('flash',
            "Impor kampanye selesai — baru: {$batch->new_rows}, diperbarui: {$batch->updated_rows}, "
            . "duplikat: {$batch->duplicate_rows}, error: {$batch->error_rows}. "
            . 'Kampanye dengan kode tak dikenal tampil di worklist untuk dipetakan manual.');
    }

    /** Koreksi pemetaan kampanye manual — ber-audit (prompt.md §8: kode tak dikenal ditindaklanjuti). */
    public function updateCampaign(Request $request, Campaign $campaign)
    {
        $data = $request->validate([
            'advertiser_id' => ['nullable', 'exists:advertisers,id'],
            'cs_agent_id'   => ['nullable', 'exists:cs_agents,id'],
            'product_id'    => ['nullable', 'exists:products,id'],
            'note'          => ['nullable', 'string', 'max:255'],
        ]);

        $before = [
            'advertiser_id' => $campaign->advertiser_id,
            'cs_agent_id'   => $campaign->cs_agent_id,
            'product_id'    => $campaign->product_id,
            'is_mapped'     => $campaign->is_mapped,
        ];

        $advertiserId = $data['advertiser_id'] ?? null;
        $csAgentId = $data['cs_agent_id'] ?? null;
        $productId = $data['product_id'] ?? null;
        $mapped = $advertiserId && $csAgentId && $productId;

        $campaign->update([
            'advertiser_id' => $advertiserId,
            'cs_agent_id'   => $csAgentId,
            'product_id'    => $productId,
            'is_mapped'     => (bool) $mapped,
        ]);

        if ($mapped) {
            DataIssue::where('campaign_id', $campaign->id)
                ->where('type', 'campaign_unmapped')
                ->where('status', 'open')
                ->update([
                    'status'      => 'resolved',
                    'resolved_by' => $request->user()->id,
                    'resolved_at' => now(),
                    'message'     => \Illuminate\Support\Facades\DB::raw("CONCAT(message, ' — dipetakan manual pada halaman marketing')"),
                ]);
        }

        AuditLog::record('campaign.mapping_update', $campaign, $before, [
            'advertiser_id' => $advertiserId,
            'cs_agent_id'   => $csAgentId,
            'product_id'    => $productId,
            'is_mapped'     => (bool) $mapped,
        ], $data['note'] ?? 'Koreksi pemetaan kampanye manual');

        return back()->with('flash', 'Pemetaan kampanye ' . $campaign->name . ' diperbarui'
            . ($mapped ? ' — kampanye kini terpetakan penuh.' : ' — masih ada dimensi yang kosong.'));
    }

    /** Rekap ADV↔resi (RekapADVtoResi) + worklist pencocokan (prompt.md §8). */
    public function rekap(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
        $advertiserId = $request->integer('advertiser_id') ?: null;

        // Satu query resi untuk seluruh rentang — dibagikan ke rows() dan unmatchedShipments().
        $shipments = $this->rekap->rangeShipments($from, $to);
        $rows = $this->rekap->rows($from, $to, $advertiserId, null, $shipments);
        // Turunan dari $rows (tanpa kalkulasi ulang): baris laporan tanpa resi terkait (prompt.md §8).
        $withoutResi = array_slice(
            array_values(array_filter($rows, fn (array $row) => ! $row['has_resi'])),
            0,
            50
        );
        $unmatched = $this->rekap->unmatchedShipments($from, $to, 50, $shipments);

        $totals = [
            'spend'      => round(array_sum(array_column($rows, 'spend')), 2),
            'laba_kotor' => round(array_sum(array_column($rows, 'laba_kotor')), 2),
            'komisi_cs'  => round(array_sum(array_column($rows, 'komisi_cs')), 2),
            'profit'     => round(array_sum(array_column($rows, 'profit')), 2),
            'packing'    => array_sum(array_map(fn ($r) => $r['counts']['packing'], $rows)),
            'dikirim'    => array_sum(array_map(fn ($r) => $r['counts']['dikirim'], $rows)),
            'undel'      => array_sum(array_map(fn ($r) => $r['counts']['undel'], $rows)),
            'diterima'   => array_sum(array_map(fn ($r) => $r['counts']['diterima'], $rows)),
            'retur'      => array_sum(array_map(fn ($r) => $r['counts']['retur'], $rows)),
        ];

        return Inertia::render('Marketing/Rekap', [
            'rows'         => $rows,
            'withoutResi'  => $withoutResi,
            'unmatched'    => $unmatched,
            'totals'       => $totals,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'advertiserId' => $advertiserId,
            'advertisers'  => Advertiser::orderBy('name')->get(),
        ]);
    }
}
