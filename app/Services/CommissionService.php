<?php

namespace App\Services;

use App\Models\Advertiser;
use App\Models\AuditLog;
use App\Models\CommissionEntry;
use App\Models\CommissionPeriod;
use App\Models\CsAgent;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur F — komisi CS & ADV (prompt.md §9; audit STAGE1 §9.1–§9.4).
 *
 *  - Periode CS (16–15) dan ADV (bulan kalender) dibuat TERPISAH via windowFor().
 *  - `compute()` menghitung ulang entries periode OPEN dari rantai OutputResi; periode
 *    `closed` dibekukan (tarif/isi tidak berubah lagi).
 *  - `close()` memindahkan sisa belum dibayar menjadi saldo periode berikutnya (Z →
 *    carried_balance, audit §9.3/U11).
 */
class CommissionService
{
    private const MONTHS_ID = [
        1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI',
        'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
    ];

    public function __construct(private readonly CommissionCalculator $calculator)
    {
    }

    /** Periode milik owner untuk bulan penutup tertentu (CS: 16 Apr–15 Mei = "MEI"). */
    public function periodFor(string $ownerType, int $ownerId, int $year, int $month): CommissionPeriod
    {
        [$start, $end] = CommissionPeriod::windowFor($ownerType, $year, $month);
        $ownerName = $this->ownerName($ownerType, $ownerId);
        $label = CommissionPeriod::labelFor($ownerType, $ownerName, $this->monthLabel($month) . ' ' . $year, $start, $end);

        return CommissionPeriod::firstOrCreate(
            [
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
                'start_date' => $start->toDateString(),
                'end_date'   => $end->toDateString(),
            ],
            [
                'label'       => $label,
                'period_type' => $ownerType === 'cs' ? CommissionPeriod::CS_PERIOD_TYPE : CommissionPeriod::ADV_PERIOD_TYPE,
                'status'      => 'open',
            ]
        );
    }

    /** Resi yang menjadi basis komisi pemilik periode (atribusi audit §7.1: kode ADV/CS). */
    public function shipmentsFor(CommissionPeriod $period)
    {
        $query = Shipment::query()
            ->whereDate('create_date', '>=', $period->start_date->toDateString())
            ->whereDate('create_date', '<=', $period->end_date->toDateString())
            ->whereNotNull('status_internal')
            ->with(['order.product']);

        if ($period->owner_type === 'cs') {
            $agent = CsAgent::find($period->owner_id);
            $query->where(fn ($q) => $agent ? $q->forCsAgent($agent) : $q->whereRaw('1 = 0'));
        } else {
            $advertiser = Advertiser::find($period->owner_id);
            $query->where(fn ($q) => $advertiser ? $q->forAdvertiser($advertiser) : $q->whereRaw('1 = 0'));
        }

        return $query->orderBy('create_date')->orderBy('id')->get();
    }

    /**
     * Hitung (ulang) entries periode OPEN dari rantai OutputResi.
     * Periode tertutup tidak boleh dihitung ulang (audit §9.1: periode tertutup tak berubah).
     */
    public function compute(CommissionPeriod $period): CommissionPeriod
    {
        abort_if($period->isClosed(), 409, 'Periode sudah ditutup — isi komisi dibekukan.');

        $entries = 0;
        DB::transaction(function () use ($period, &$entries) {
            $period->entries()->delete();

            foreach ($this->shipmentsFor($period) as $shipment) {
                $calc = $this->calculator->compute($shipment);
                if ($calc === null) {
                    continue; // resi tanpa status internal = bahan Data Error, bukan komisi
                }

                $payable = in_array($shipment->status_internal, ['diterima', 'retur'], true);
                foreach ($calc['components'] as $component => $amount) {
                    CommissionEntry::create([
                        'commission_period_id' => $period->id,
                        'shipment_id'          => $shipment->id,
                        'order_id'             => $shipment->order_id,
                        'component'            => $component,
                        'status_internal'      => $shipment->status_internal,
                        'is_on_progress'       => ! $payable,
                        'is_payable'           => $payable,
                        'amount'               => $amount,
                        'rule_snapshot'        => $calc['rule_snapshot'],
                        'computed_at'          => now(),
                    ]);
                    $entries++;
                }
            }
        });

        AuditLog::record('commission.compute', $period, ['entries' => null], ['entries' => $entries],
            'Hitung komisi periode ' . $period->label);

        return $period->refresh();
    }

    /**
     * Ringkasan periode — komponen, on-progress vs payable, pembayaran, sisa (audit §9.3).
     * Komponen komisi = order+transfer+multi_paket+ongkir (OutputResi!AK); admin_input terpisah (AL).
     *
     * @return array<string,mixed>
     */
    public function stats(CommissionPeriod $period): array
    {
        $komisiComponents = ['order', 'transfer', 'multi_paket', 'ongkir'];
        $sum = fn (array $comps, array $extra = []) => (float) $period->entries()
            ->whereIn('component', $comps)
            ->when($extra, fn ($q) => $q->where($extra))
            ->sum('amount');

        $order = $sum(['order']);
        $transfer = $sum(['transfer']);
        $ongkir = $sum(['ongkir']);
        $multi = $sum(['multi_paket']);
        $total = round($order + $transfer + $ongkir + $multi, 2);

        $onProgress = $sum($komisiComponents, ['is_on_progress' => true]);
        $payable = $sum($komisiComponents, ['is_payable' => true]);
        $paid = (float) $period->payments()->sum('amount');
        $carried = (float) $period->carried_balance;

        $counts = $period->entries()
            ->selectRaw('status_internal, COUNT(DISTINCT shipment_id) c')
            ->whereNotNull('status_internal')
            ->groupBy('status_internal')
            ->pluck('c', 'status_internal');

        $qty = (int) $counts->sum();
        $closed = (int) ($counts['diterima'] ?? 0) + (int) ($counts['retur'] ?? 0);
        $pct = fn (int $n) => $qty > 0 ? round($n / $qty * 100, 2) : 0.0;

        return [
            'counts'         => $counts->all(),
            'qty'            => $qty,
            'pct_undel'      => $pct((int) ($counts['undel'] ?? 0)),
            'pct_retur'      => $pct((int) ($counts['retur'] ?? 0)),
            'pct_close'      => $pct((int) $closed),
            'komisi_order'   => round($order, 2),
            'komisi_transfer' => round($transfer, 2),
            'komisi_ongkir'  => round($ongkir, 2),
            'komisi_multi_paket' => round($multi, 2),
            'komisi_total'   => $total,
            'admin_input'    => round($sum(['admin_input']), 2),
            'on_progress'    => round($onProgress, 2),
            'payable'        => round($payable, 2),
            'carried'        => round($carried, 2),
            'paid'           => round($paid, 2),
            'unpaid'         => round($carried + $payable - $paid, 2),
            'entries'        => (int) $period->entries()->count(),
        ];
    }

    /** Tutup periode + pindahkan sisa belum dibayar ke periode berikutnya (Z / U11). */
    public function close(CommissionPeriod $period, User $user): CommissionPeriod
    {
        abort_if($period->isClosed(), 409, 'Periode ini sudah ditutup.');

        $stats = $this->stats($period);

        DB::transaction(function () use ($period, $user, $stats) {
            $nextClose = $period->end_date->copy()->addMonthNoOverflow();
            $next = $this->periodFor($period->owner_type, $period->owner_id, $nextClose->year, $nextClose->month);
            $next->update(['carried_balance' => round((float) $next->carried_balance + $stats['unpaid'], 2)]);

            $period->update([
                'status'    => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
        });

        AuditLog::record('commission.close_period', $period, ['status' => 'open'],
            ['status' => 'closed', 'unpaid' => $stats['unpaid']], 'Tutup periode ' . $period->label);

        return $period->refresh();
    }

    public function ownerName(string $ownerType, int $ownerId): string
    {
        return $ownerType === 'cs'
            ? (CsAgent::find($ownerId)?->name ?? 'CS#' . $ownerId)
            : (Advertiser::find($ownerId)?->name ?? 'ADV#' . $ownerId);
    }

    public function monthLabel(int $month): string
    {
        return self::MONTHS_ID[$month] ?? (string) $month;
    }

    /** Daftar bulan pilihan (untuk form periode). */
    public function monthOptions(): array
    {
        $out = [];
        for ($i = 1; $i <= 12; $i++) {
            $out[$i] = $this->monthLabel($i);
        }

        return $out;
    }

    public function currentMonth(): Carbon
    {
        return Carbon::now();
    }
}
