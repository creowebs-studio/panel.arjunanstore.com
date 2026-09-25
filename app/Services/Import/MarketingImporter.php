<?php

namespace App\Services\Import;

use App\Models\Campaign;
use App\Models\CommissionRule;
use App\Models\DataIssue;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\MarketingDailyReport;
use App\Services\Marketing\CampaignCodeResolver;
use Illuminate\Support\Facades\DB;

/**
 * Pemroses impor laporan kampanye marketing (Alur E, prompt.md §8; audit §8.1/§8.2).
 *
 * Jaminan:
 *  - IDEMPOTEN: file yang sama diimpor ulang tidak menggandakan campaigns/marketing_daily_reports.
 *    Baris identik (kunci kampanye+tanggal sama & nilai sama) → result=duplicate.
 *  - Kode tak dikenal (ADV/CS/Produk) TIDAK disembunyikan: kampanye is_mapped=false +
 *    DataIssue `campaign_unmapped` untuk worklist (prompt.md §8).
 *  - spend_ppn = spend_raw × multiplier (aturan `spend_ppn`, audit §8.2: Col13 × 1.12).
 *  - Sel turunan rusak/kosong pada workbook sumber (titik desimal termakan locale)
 *    dihitung ulang dari kolom dasar secara deterministik + worklist `metric_normalized`
 *    (tidak ditolak, tidak disembunyikan — audit Tahap 6).
 */
class MarketingImporter
{
    /** Kolom marketing_daily_reports yang boleh diisi dari hasil pemetaan. */
    private const REPORT_FIELDS = [
        'periode', 'date_start', 'date_end', 'results',
        'reach', 'frequency', 'cost_per_result', 'budget_set', 'budget_type',
        'spend_raw', 'impressions', 'cpm', 'clicks_link', 'cpc_link', 'ctr_link',
        'clicks_all', 'ctr_all', 'cpc_all',
    ];

    /** Kolom meta yang tersimpan di KAMPANYE (bukan per baris laporan). */
    private const CAMPAIGN_META = ['delivery_status', 'attribution', 'result_indicator'];

    /** Kolom pembanding untuk deteksi baris identik (impor ulang file yang sama). */
    private const COMPARE_FIELDS = [
        'spend_raw', 'spend_ppn', 'results', 'reach', 'frequency', 'cost_per_result', 'budget_set',
        'impressions', 'cpm', 'clicks_link', 'cpc_link', 'ctr_link', 'clicks_all', 'ctr_all', 'cpc_all',
    ];

    public function __construct(private readonly CampaignCodeResolver $resolver)
    {
    }

    public function process(ImportBatch $batch): ImportBatch
    {
        $batch->update(['status' => 'processing']);
        $multiplier = $this->spendMultiplier();
        $normalized = ['rows' => 0, 'metrics' => []];

        foreach ($batch->rows()->orderBy('row_number')->get() as $row) {
            DB::transaction(function () use ($row, $batch, $multiplier, &$normalized) {
                $result = $this->applyRow($row, $batch, $multiplier);
                if ($result === 'new' || $result === 'update') {
                    $this->tallyNormalized($row, $normalized);
                }
            });
        }

        $this->reportNormalized($batch, $normalized);

        return $this->finalize($batch);
    }

    /** Sel turunan yang dinormalisasi dihitung pada baris new/update saja. */
    private function tallyNormalized(ImportRow $row, array &$normalized): void
    {
        $labels = $row->mapped_data['_metric_normalized'] ?? [];
        if ($labels === []) {
            return;
        }

        $normalized['rows']++;
        foreach ($labels as $label) {
            $normalized['metrics'][$label] = ($normalized['metrics'][$label] ?? 0) + 1;
        }
    }

    /**
     * Satu worklist per batch (audit Tahap 6): sel turunan workbook yang rusak/kosong
     * dinormalisasi dari kolom dasar — transparan, tidak disembunyikan.
     */
    private function reportNormalized(ImportBatch $batch, array $normalized): void
    {
        if ($normalized['rows'] === 0) {
            return;
        }

        $exists = DataIssue::where('type', 'metric_normalized')
            ->where('payload->batch_id', $batch->id)
            ->exists();
        if ($exists) {
            return;
        }

        arsort($normalized['metrics']);
        $parts = [];
        foreach ($normalized['metrics'] as $label => $count) {
            $parts[] = $label . ' (' . number_format($count, 0, ',', '.') . ' baris)';
        }

        DataIssue::create([
            'type'    => 'metric_normalized',
            'message' => number_format($normalized['rows'], 0, ',', '.')
                . ' baris laporan memiliki sel turunan rusak/kosong pada workbook sumber; nilainya dihitung ulang dari kolom dasar: '
                . implode(', ', $parts) . '.',
            'payload' => [
                'batch_id'    => $batch->id,
                'source_file' => $batch->source_filename,
                'rows'        => $normalized['rows'],
                'metrics'     => $normalized['metrics'],
            ],
            'status'  => 'open',
        ]);
    }

    /** PPN/multiplier atas spend iklan dari aturan `spend_ppn` (fallback 1.12 — audit §8.2). */
    private function spendMultiplier(): float
    {
        $rule = CommissionRule::activeOn('spend_ppn', now()->toDateString())->first();

        return (float) ($rule->params['multiplier'] ?? 1.12);
    }

    /** Hitung ulang seluruh counter dari tabel baris — konsisten setelah proses. */
    private function finalize(ImportBatch $batch): ImportBatch
    {
        $c = $batch->rows()
            ->selectRaw("SUM(result = 'new') n, SUM(result = 'update') u, SUM(result = 'duplicate') d, SUM(result = 'error') e")
            ->first();

        $batch->update([
            'status'         => 'done',
            'new_rows'       => (int) ($c->n ?? 0),
            'updated_rows'   => (int) ($c->u ?? 0),
            'duplicate_rows' => (int) ($c->d ?? 0),
            'error_rows'     => (int) ($c->e ?? 0),
            'processed_at'   => now(),
        ]);

        return $batch->refresh();
    }

    /** @return string salah satu: new|update|duplicate|error */
    private function applyRow(ImportRow $row, ImportBatch $batch, float $multiplier): string
    {
        $m = $row->mapped_data ?? [];

        if (! empty($m['_missing_key'])) {
            $row->update(['result' => 'error', 'error_reason' => 'Nama kampanye / tanggal pelaporan kosong.']);
            $this->issue($row, null, 'Baris impor marketing tanpa nama kampanye atau tanggal pelaporan.', $m);

            return 'error';
        }

        $campaign = Campaign::firstOrCreate(
            ['name' => $m['campaign_name']],
            ['platform' => 'meta', 'is_mapped' => false]
        );

        $meta = array_filter(
            array_intersect_key($m, array_flip(self::CAMPAIGN_META)),
            fn ($v) => $v !== null && $v !== ''
        );
        if ($meta !== []) {
            $campaign->update($meta);
        }

        $unresolved = $this->resolver->sync($campaign);
        if ($unresolved !== []) {
            $this->campaignIssue($campaign, $unresolved, $row);
        }

        $data = array_intersect_key($m, array_flip(self::REPORT_FIELDS));
        $data['spend_ppn'] = round(((float) $m['spend_raw']) * $multiplier, 2);

        $existing = MarketingDailyReport::where('campaign_id', $campaign->id)
            ->whereDate('date_start', $m['date_start'])
            ->whereDate('date_end', $m['date_end'])
            ->first();

        if (! $existing) {
            MarketingDailyReport::create($data + [
                'campaign_id'     => $campaign->id,
                'source_file'     => $batch->source_filename,
                'import_batch_id' => $batch->id,
            ]);
            $row->update(['result' => 'new']);

            return 'new';
        }

        if ($this->isIdentical($existing, $data)) {
            $row->update(['result' => 'duplicate']);

            return 'duplicate';
        }

        $existing->update($data + ['source_file' => $batch->source_filename, 'import_batch_id' => $batch->id]);
        $row->update(['result' => 'update']);

        return 'update';
    }

    private function isIdentical(MarketingDailyReport $report, array $data): bool
    {
        foreach (self::COMPARE_FIELDS as $key) {
            if (round((float) $report->{$key}, 4) !== round((float) ($data[$key] ?? 0), 4)) {
                return false;
            }
        }

        return true;
    }

    /** Worklist kode tak dikenal (prompt.md §8) — satu issue terbuka per kampanye. */
    private function campaignIssue(Campaign $campaign, array $unresolved, ImportRow $row): void
    {
        $exists = DataIssue::where('campaign_id', $campaign->id)
            ->where('type', 'campaign_unmapped')
            ->where('status', 'open')
            ->exists();
        if ($exists) {
            return;
        }

        $parts = [];
        foreach ($unresolved as $dim => $code) {
            $parts[] = strtoupper($dim) . ' "' . $code . '"';
        }

        DataIssue::create([
            'type'          => 'campaign_unmapped',
            'campaign_id'   => $campaign->id,
            'import_row_id' => $row->id,
            'message'       => 'Kode kampanye tidak dikenal: ' . implode(', ', $parts)
                . " — kampanye \"{$campaign->name}\" belum terpetakan ke ADV/CS/Produk.",
            'payload'       => ['campaign' => $campaign->name, 'unresolved' => $unresolved],
            'status'        => 'open',
        ]);
    }

    private function issue(ImportRow $row, ?Campaign $campaign, string $message, array $payload): void
    {
        if (DataIssue::where('import_row_id', $row->id)->where('status', 'open')->exists()) {
            return;
        }

        DataIssue::create([
            'type'          => 'required_missing',
            'campaign_id'   => $campaign?->id,
            'import_row_id' => $row->id,
            'message'       => $message,
            'payload'       => $payload,
            'status'        => 'open',
        ]);
    }
}
