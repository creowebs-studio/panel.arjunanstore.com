<?php

namespace App\Http\Controllers;

use App\Models\Advertiser;
use App\Models\CsAgent;
use App\Models\MarketingDailyReport;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Services\CommissionCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Dashboard (prompt.md §9): filter tanggal, ADV, CS, produk, agregator (platform), ekspedisi, status.
 * Semua kartu dihitung dari KUMPULAN RESI yang sama dengan tabel detail di bawahnya:
 *  - kartu status = tabel "Resi per Status Internal" (jumlah barisnya identik),
 *  - kartu finansial = tabel "Ringkasan Finansial" (satu baris per kartu),
 *  - kartu spend = tabel "Laporan Kampanye" (total kolom spend+PPN),
 *  - kartu order = tabel "Order per Klasifikasi".
 * Nilai finansial memakai rantai OutputResi yang sama dengan halaman Komisi (audit §9.2);
 * profit = laba eksplisit − komisi CS − spend (padanan kolom V = S−U−Y RekapADVtoResi).
 */
class DashboardController extends Controller
{
    /** Status internal resi yang dikenal (sama dengan parser impor). */
    private const STATUSES = ['packing', 'dikirim', 'undel', 'diterima', 'retur'];

    public function __construct(private readonly CommissionCalculator $calculator)
    {
    }

    public function index(Request $request)
    {
        $from = $this->parseDate($request->input('from')) ?? now()->startOfMonth();
        $to = $this->parseDate($request->input('to')) ?? now()->endOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $advertiser = $request->integer('advertiser_id') ? Advertiser::find($request->integer('advertiser_id')) : null;
        $csAgent = $request->integer('cs_agent_id') ? CsAgent::find($request->integer('cs_agent_id')) : null;
        $product = $request->integer('product_id') ? Product::find($request->integer('product_id')) : null;
        $platform = $request->input('platform') ?: null;
        $expedition = $request->input('expedition') ?: null;
        $status = in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : null;

        $filters = compact('advertiser', 'csAgent', 'product', 'platform', 'expedition', 'status');

        // ---- Resi (basis kartu status + finansial + tabel detail) ------------------------
        $shipmentQuery = $this->shipmentQuery($from, $to, $filters);
        $shipments = (clone $shipmentQuery)->with(['order.product'])->orderBy('create_date')->orderBy('id')->get();

        $counts = array_fill_keys(self::STATUSES, 0);
        $withoutStatus = 0;
        $nilaiPenjualan = $ongkir = $labaEksplisit = $komisiCs = $adminInput = 0.0;

        foreach ($shipments as $shipment) {
            if (isset($counts[$shipment->status_internal])) {
                $counts[$shipment->status_internal]++;
            }
            $calc = $this->calculator->compute($shipment);
            if ($calc === null) {
                $withoutStatus++; // resi tanpa status internal = bahan Data Error, bukan angka finansial
                continue;
            }
            $nilaiPenjualan += $calc['p'] + $calc['q'];
            $ongkir += $calc['r'];
            $labaEksplisit += $calc['am_laba_eksplisit'];
            $komisiCs += $calc['ak_komisi_total'];
            $adminInput += $calc['al_komisi_admin_input'];
        }

        // ---- Spend iklan (kampanye) ------------------------------------------------------
        $reportQuery = MarketingDailyReport::query()
            ->whereDate('date_start', '<=', $to->toDateString())
            ->whereDate('date_end', '>=', $from->toDateString())
            ->when($advertiser, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('advertiser_id', $advertiser->id)))
            ->when($csAgent, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('cs_agent_id', $csAgent->id)))
            ->when($product, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('product_id', $product->id)));

        $spend = round((float) $reportQuery->sum('spend_ppn'), 2);
        $spendRows = $reportQuery->with('campaign.advertiser')->orderBy('date_start')->orderBy('id')->get();

        // ---- Order (kartu klasifikasi) ---------------------------------------------------
        $orderQuery = Order::query()
            ->whereDate('order_date', '>=', $from->toDateString())
            ->whereDate('order_date', '<=', $to->toDateString())
            ->when($csAgent, fn ($q) => $q->where('cs_agent_id', $csAgent->id))
            ->when($product, fn ($q) => $q->where('product_id', $product->id))
            ->when($advertiser, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('advertiser_id', $advertiser->id)));

        $orderCounts = $orderQuery->clone()->selectRaw('classification, COUNT(*) c')
            ->groupBy('classification')->pluck('c', 'classification');

        $totals = [
            'nilai_penjualan' => round($nilaiPenjualan, 2),
            'ongkir'          => round($ongkir, 2),
            'laba_eksplisit'  => round($labaEksplisit, 2),
            'komisi_cs'       => round($komisiCs, 2),
            'admin_input'     => round($adminInput, 2),
            'spend'           => $spend,
            'profit'          => round($labaEksplisit - $komisiCs - $spend, 2),
        ];

        // Baris tabel detail memakai kalkulator yang sama agar angka baris konsisten dengan kartu.
        $latestShipments = (clone $shipmentQuery)->with(['order.product'])
            ->orderByDesc('create_date')->orderByDesc('id')->paginate(50)->withQueryString();
        $rowCalcs = $latestShipments->getCollection()
            ->mapWithKeys(fn (Shipment $s) => [$s->id => $this->calculator->compute($s)]);

        return Inertia::render('Dashboard', [
            'from' => $from, 'to' => $to,
            'filters' => $filters,
            'advertisers' => Advertiser::orderBy('name')->get(),
            'csAgents' => CsAgent::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'platforms' => Shipment::whereNotNull('platform')->distinct()->orderBy('platform')->pluck('platform'),
            'expeditions' => Shipment::whereNotNull('expedition')->distinct()->orderBy('expedition')->pluck('expedition'),
            'statuses' => self::STATUSES,
            'counts' => $counts,
            'withoutStatus' => $withoutStatus,
            'shipmentTotal' => $shipments->count(),
            'latestShipments' => $latestShipments,
            'rowCalcs' => $rowCalcs,
            'totals' => $totals,
            'spendRows' => $spendRows,
            'orderCounts' => $orderCounts,
            'orderTotal' => (int) $orderCounts->sum(),
        ]);
    }

    /** Filter resi bersama (tanggal create_date) — dipakai agregat DAN tabel detail. */
    private function shipmentQuery(Carbon $from, Carbon $to, array $filters)
    {
        return Shipment::query()
            ->whereDate('create_date', '>=', $from->toDateString())
            ->whereDate('create_date', '<=', $to->toDateString())
            ->when($filters['advertiser'], fn ($q, $adv) => $q->forAdvertiser($adv))
            ->when($filters['csAgent'], fn ($q, $cs) => $q->forCsAgent($cs))
            ->when($filters['product'], function ($q, $product) {
                $codes = array_values(array_filter([$product->resi_code, $product->code]));
                $q->where(function ($qq) use ($product, $codes) {
                    if ($codes !== []) {
                        $qq->orWhereIn('product_resi_code', $codes);
                    }
                    $qq->orWhereHas('order', fn ($o) => $o->where('product_id', $product->id));
                });
            })
            ->when($filters['platform'], fn ($q, $platform) => $q->where('platform', $platform))
            ->when($filters['expedition'], fn ($q, $expedition) => $q->where('expedition', $expedition))
            ->when($filters['status'], fn ($q, $status) => $q->where('status_internal', $status));
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
