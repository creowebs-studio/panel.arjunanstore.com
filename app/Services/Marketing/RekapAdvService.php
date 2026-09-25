<?php

namespace App\Services\Marketing;

use App\Models\Campaign;
use App\Models\MarketingDailyReport;
use App\Models\Shipment;
use App\Services\CommissionCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap ADV↔resi (padanan `RekapADVtoResi (All)` + `RekapADV (All)`, audit STAGE1 §8.2/§8.3).
 *
 * Per baris laporan harian kampanye (kampanye × ADV × CS × Produk × tanggal):
 *   G..K Packing/Dikirim/Undel/Diterima/Retur, L Total, N %Close=(Diterima+Retur)/Total,
 *   P Est Return = if(age≥10 → 100% else age/10) × (Total − %Close·Total) + Retur,
 *   Q Est Diterima = Total − Est Return (turunan — dokumen §8.3),
 *   S/T Laba Kotor = Σ OutputResi!AM, U Komisi CS = Σ OutputResi!AK,
 *   V Profit = Laba Kotor − Komisi CS − Spend Iklan — persis rumus workbook
 *   `V = S−U−Y` (out/audit/_rekapadvtoresi_f.txt), meski ΣAM sudah net komisi (replikasi sumber).
 *
 * Pencocokan resi↔kampanye: shipment.campaign_id eksplisit ATAU kesamaan kode resi
 * ADV/CS/Produk (audit §7.1) yang sudah terpetakan — dimensi null tidak dipaksakan.
 */
class RekapAdvService
{
    public function __construct(private readonly CommissionCalculator $calculator)
    {
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function rows(Carbon $from, Carbon $to, ?int $advertiserId = null, ?int $limit = null, ?Collection $shipments = null): array
    {
        $reports = MarketingDailyReport::query()
            ->with(['campaign.advertiser', 'campaign.csAgent', 'campaign.product'])
            ->whereDate('date_start', '<=', $to->toDateString())
            ->whereDate('date_end', '>=', $from->toDateString())
            ->when($advertiserId, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('advertiser_id', $advertiserId)))
            ->orderBy('date_start')->orderBy('id')
            ->limit($limit ?? 500)
            ->get();

        // Satu query resi untuk seluruh rentang — bukan satu per baris laporan.
        $shipments ??= $this->rangeShipments($from, $to);
        $index = $this->shipmentIndex($shipments);

        return $reports->map(function (MarketingDailyReport $r) use (&$index) {
            return $this->buildRow($r, $index);
        })->all();
    }

    /**
     * Resi pada rentang [from,to] untuk dipakai bersama rows() dan unmatchedShipments()
     * agar halaman rekap tidak mengulang query per baris laporan.
     */
    public function rangeShipments(Carbon $from, Carbon $to): Collection
    {
        return Shipment::query()
            ->whereDate('create_date', '>=', $from->toDateString())
            ->whereDate('create_date', '<=', $to->toDateString())
            // Kolom hitung (CommissionCalculator) + pencocokan + tampilan worklist resi tak tercocokkan.
            ->select([
                'id', 'order_id', 'platform', 'tracking_id', 'platform_order_id', 'create_date', 'last_update',
                'customer_name', 'customer_phone', 'status_internal', 'campaign_id',
                'adv_resi_code', 'cs_resi_code', 'product_resi_code', 'expedition',
                'cod_value', 'product_value', 'shipping_fee', 'shipping_discount', 'return_fee', 'quantity',
            ])
            ->with(['order.product'])
            ->orderByDesc('create_date')
            ->get();
    }

    /** @return array<string,mixed> */
    public function buildRow(MarketingDailyReport $report, ?array &$index = null): array
    {
        $campaign = $report->campaign;
        $shipments = $index === null
            ? $this->matchingShipments($campaign, $report->date_start->toDateString(), $report->date_end->toDateString())
            : $this->matchingFromIndex($campaign, $report, $index);

        $counts = ['packing' => 0, 'dikirim' => 0, 'undel' => 0, 'diterima' => 0, 'retur' => 0];
        $laba = 0.0;
        $komisi = 0.0;

        foreach ($shipments as $shipment) {
            if (isset($counts[$shipment->status_internal])) {
                $counts[$shipment->status_internal]++;
            }
            $calc = $this->calculator->compute($shipment);
            if ($calc !== null) {
                $laba += $calc['am_laba_eksplisit'];
                $komisi += $calc['ak_komisi_total'];
            }
        }

        $total = array_sum($counts);
        $closed = $counts['diterima'] + $counts['retur'];
        $pctClose = $total > 0 ? $closed / $total : 0.0;

        // P Est Return (audit §8.3): faktor (age/10, maks 1) × resi belum close + retur.
        $age = max(0, (int) $report->date_end->startOfDay()->diffInDays(Carbon::today()->startOfDay(), false));
        $factor = min(1.0, $age / 10);
        $estReturn = round($factor * ($total - $closed) + $counts['retur'], 2);
        $estDiterima = round($total - $estReturn, 2);

        $spend = (float) $report->spend_ppn;

        // report/campaign diramping ke field presentasional saja — model utuh (dengan relasi
        // advertiser/cs_agent/product) diduplikasi per baris dan membuat payload ~2 MB untuk 402 baris.
        return [
            'report'       => [
                'id'         => $report->id,
                'date_start' => $report->date_start?->toDateString(),
                'date_end'   => $report->date_end?->toDateString(),
            ],
            'campaign'     => $campaign === null ? null : [
                'id'         => $campaign->id,
                'name'       => $campaign->name,
                'is_mapped'  => (bool) $campaign->is_mapped,
                'advertiser' => $campaign->advertiser === null ? null : [
                    'code' => $campaign->advertiser->code,
                    'name' => $campaign->advertiser->name,
                ],
                'cs_agent'   => $campaign->csAgent === null ? null : [
                    'code' => $campaign->csAgent->code,
                ],
                'product'    => $campaign->product === null ? null : [
                    'code' => $campaign->product->code,
                ],
            ],
            'counts'       => $counts,
            'total'        => $total,
            'pct_close'    => round($pctClose, 4),
            'est_return'   => $estReturn,
            'est_diterima' => $estDiterima,
            'laba_kotor'   => round($laba, 2),
            'komisi_cs'    => round($komisi, 2),
            'spend'        => $spend,
            'profit'       => round($laba - $komisi - $spend, 2),
            'has_resi'     => $total > 0,
        ];
    }

    /** Baris laporan tanpa resi terkait (prompt.md §8: "Data ADV tanpa resi terkait"). */
    public function advWithoutResi(Carbon $from, Carbon $to, ?int $advertiserId = null, int $limit = 50): array
    {
        return array_values(array_filter(
            $this->rows($from, $to, $advertiserId),
            fn (array $row) => ! $row['has_resi']
        ));
    }

    /**
     * Resi pada rentang tanggal yang TIDAK dapat dicocokkan ke kampanye mana pun
     * (prompt.md §8: "Resi tanpa data kampanye/ADV yang diperlukan").
     */
    public function unmatchedShipments(Carbon $from, Carbon $to, int $limit = 50, ?Collection $shipments = null): array
    {
        $dimensionSets = Campaign::with(['advertiser', 'csAgent', 'product'])
            ->get()
            ->map(fn (Campaign $c) => $this->codeDimensions($c))
            ->filter(fn (array $dimensions) => $dimensions !== [])
            ->values();

        // Himpunan tuple kode yang valid (produk kartesius nilai tiap dimensi per kampanye).
        $this->validDimensionTuples = [];
        foreach ($dimensionSets as $dimensions) {
            $keys = [''];
            foreach ($dimensions as [$column, $values]) {
                $next = [];
                foreach ($keys as $prefix) {
                    foreach ($values as $value) {
                        $next[] = $prefix === '' ? (string) $value : $prefix."\x1F".$value;
                    }
                }
                $keys = $next;
            }
            foreach ($keys as $key) {
                $this->validDimensionTuples[$key] = true;
            }
        }

        // Tanpa koleksi pre-load (mis. pemanggil lain), ambil sendiri dengan kolom minimal.
        $shipments ??= Shipment::query()
            ->whereDate('create_date', '>=', $from->toDateString())
            ->whereDate('create_date', '<=', $to->toDateString())
            // Hanya kolom yang dipakai pencocokan + tampilan worklist (hindari hidrasi kolom besar).
            ->select([
                'id', 'platform', 'tracking_id', 'platform_order_id', 'create_date', 'customer_name',
                'customer_phone', 'status_internal', 'campaign_id',
                'adv_resi_code', 'cs_resi_code', 'product_resi_code', 'expedition',
            ])
            ->orderByDesc('create_date')
            ->get();

        $unmatched = $shipments->filter(function (Shipment $s) use ($dimensionSets) {
            if ($s->campaign_id) {
                return false;
            }
            // Tuple kode resi → himpunan tuple valid dari semua kampanye (O(1) per resi).
            $key = implode("\x1F", [(string) $s->adv_resi_code, (string) $s->cs_resi_code, (string) $s->product_resi_code]);

            return ! isset($this->validDimensionTuples[$key]);
        });

        return [
            'count' => $unmatched->count(),
            'items' => $unmatched->take($limit)->values(),
        ];
    }

    /** @var array<string,bool> tuple kode valid (dibangun unmatchedShipments). */
    private array $validDimensionTuples = [];

    /**
     * Indeks resi sekali per request: byCampaign (campaign_id → koleksi),
     * dimMap (tuple adv\x1Fcs\x1Fproduk → koleksi) dan ymd (id → int Ymd)
     * agar pencocokan per baris O(kecil) tanpa operasi Carbon per elemen.
     *
     * @return array{byCampaign:Collection,dimMap:Collection<int,Collection<int,Shipment>>,ymd:array<int,int>}
     */
    private function shipmentIndex(Collection $shipments): array
    {
        $byCampaign = $shipments->groupBy('campaign_id');

        $dimMap = [];
        $ymd = [];
        foreach ($shipments as $s) {
            $key = implode("\x1F", [(string) $s->adv_resi_code, (string) $s->cs_resi_code, (string) $s->product_resi_code]);
            $dimMap[$key][] = $s;
            $ymd[$s->id] = $s->create_date === null ? 0 : (int) $s->create_date->format('Ymd');
        }

        return [
            'byCampaign' => $byCampaign,
            'dimMap'     => collect($dimMap)->map(fn (array $items) => collect($items)),
            'ymd'        => $ymd,
        ];
    }

    /** Pencocokan dari indeks: kampanye ATAU tuple kode dimensi (audit §7.1), lalu saring tanggal laporan. */
    private function matchingFromIndex(Campaign $campaign, MarketingDailyReport $report, array &$index): Collection
    {
        // Himpunan resi kampanye dimemo — beberapa baris laporan berbagi kampanye yang sama.
        if (! isset($index['campaignMatched'][$campaign->id])) {
            $byCampaign = $index['byCampaign'];
            $dimMap = $index['dimMap'];
            $matched = $byCampaign->get($campaign->id, collect());

            $dimensions = $this->codeDimensions($campaign);
            if ($dimensions !== []) {
                // Produk kartesius nilai tiap dimensi (maks 2×2×2 kombinasi per kampanye).
                $keys = [''];
                foreach ($dimensions as [$column, $values]) {
                    $next = [];
                    foreach ($keys as $prefix) {
                        foreach ($values as $value) {
                            $next[] = $prefix === '' ? (string) $value : $prefix."\x1F".$value;
                        }
                    }
                    $keys = $next;
                }
                foreach ($keys as $key) {
                    $matched = $matched->concat($dimMap->get($key, collect()));
                }
            }

            $index['campaignMatched'][$campaign->id] = $matched->unique(fn (Shipment $s) => $s->id)->values();
        }

        // Bandingkan int Ymd (bukan Carbon) — setara whereDate SQL.
        $startYmd = (int) $report->date_start->format('Ymd');
        $endYmd = (int) $report->date_end->format('Ymd');
        $ymd = $index['ymd'];

        return $index['campaignMatched'][$campaign->id]
            ->filter(fn (Shipment $s) => ($ymd[$s->id] ?? 0) >= $startYmd && ($ymd[$s->id] ?? 0) <= $endYmd)
            ->values();
    }

    /** Resi yang masuk hitungan baris rekap sebuah kampanye. */
    private function matchingShipments(Campaign $campaign, string $from, string $to)
    {
        $dimensions = $this->codeDimensions($campaign);

        return Shipment::query()
            ->whereDate('create_date', '>=', $from)
            ->whereDate('create_date', '<=', $to)
            ->where(function ($q) use ($campaign, $dimensions) {
                $q->where('campaign_id', $campaign->id);
                if ($dimensions !== []) {
                    $q->orWhere(function ($qq) use ($dimensions) {
                        foreach ($dimensions as [$column, $values]) {
                            $qq->whereIn($column, $values);
                        }
                    });
                }
            })
            ->with(['order.product'])
            ->get();
    }

    /** @return array<int,array{0:string,1:array<int,string>}> kode resi per dimensi (audit §7.1). */
    private function codeDimensions(Campaign $campaign): array
    {
        $out = [];
        if ($campaign->advertiser) {
            $values = array_values(array_filter([$campaign->advertiser->resi_code, $campaign->advertiser->code]));
            if ($values !== []) {
                $out[] = ['adv_resi_code', $values];
            }
        }
        if ($campaign->csAgent) {
            $values = array_values(array_filter([$campaign->csAgent->resi_cs_code, $campaign->csAgent->code]));
            if ($values !== []) {
                $out[] = ['cs_resi_code', $values];
            }
        }
        if ($campaign->product) {
            $values = array_values(array_filter([$campaign->product->resi_code, $campaign->product->code]));
            if ($values !== []) {
                $out[] = ['product_resi_code', $values];
            }
        }

        return $out;
    }
}
