<?php

namespace App\Http\Controllers;

use App\Models\Advertiser;
use App\Models\AuditLog;
use App\Models\CommissionPayment;
use App\Models\CommissionPeriod;
use App\Models\CsAgent;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Alur F — laporan & pembayaran komisi CS/ADV (prompt.md §9; audit §9.3/§9.4).
 * Periode CS (16–15) dan ADV (bulan kalender) ditampilkan dengan definisi eksplisit.
 */
class CommissionController extends Controller
{
    public function __construct(private readonly CommissionService $service)
    {
    }

    public function index(Request $request)
    {
        $ownerType = in_array($request->input('owner_type'), ['cs', 'adv'], true) ? $request->input('owner_type') : 'cs';
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $periods = CommissionPeriod::with('closedBy')
            ->where('owner_type', $ownerType)
            ->orderByDesc('start_date')->orderBy('owner_id')
            ->paginate(20)->withQueryString();

        $rows = collect($periods->items())->map(fn (CommissionPeriod $p) => [
            'period' => $p,
            'owner'  => $this->service->ownerName($p->owner_type, $p->owner_id),
            'stats'  => $this->service->stats($p),
        ]);

        return Inertia::render('Komisi/Index', [
            'periods'   => $periods,
            'rows'      => $rows,
            'ownerType' => $ownerType,
            'year'      => $year,
            'month'     => $month,
            'months'    => $this->service->monthOptions(),
            'css'       => CsAgent::orderBy('name')->get(),
            'advs'      => Advertiser::orderBy('name')->get(),
            // Definisi jendela ditampilkan di UI agar CS vs ADV tidak tertukar (audit §9.4).
            'csWindow'  => CommissionPeriod::windowFor('cs', $year, $month),
            'advWindow' => CommissionPeriod::windowFor('adv', $year, $month),
        ]);
    }

    /** Buka/lanjutkan periode + hitung komisi dari rantai OutputResi. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'owner_type' => ['required', 'in:cs,adv'],
            'owner_id'   => ['required', 'integer'],
            'year'       => ['required', 'integer', 'between:2020,2100'],
            'month'      => ['required', 'integer', 'between:1,12'],
        ]);

        $owner = $data['owner_type'] === 'cs'
            ? CsAgent::find($data['owner_id'])
            : Advertiser::find($data['owner_id']);
        abort_if($owner === null, 422, 'Pemilik komisi tidak ditemukan.');

        $period = $this->service->periodFor($data['owner_type'], $data['owner_id'], (int) $data['year'], (int) $data['month']);
        if (! $period->isClosed()) {
            $this->service->compute($period);
        }
        $stats = $this->service->stats($period);

        return redirect()->route('commissions.show', $period)->with('flash',
            "Periode {$period->label} siap — {$stats['entries']} entri komisi, "
            . 'payable Rp ' . number_format($stats['payable'], 0, ',', '.') . ', on progress Rp '
            . number_format($stats['on_progress'], 0, ',', '.') . '.');
    }

    public function show(CommissionPeriod $period)
    {
        $stats = $this->service->stats($period);
        $entries = $period->entries()->with(['shipment'])->orderByDesc('id')->paginate(50);
        $payments = $period->payments()->with('approvedBy')->orderByDesc('paid_date')->get();

        return Inertia::render('Komisi/Show', [
            'period'   => $period,
            'owner'    => $this->service->ownerName($period->owner_type, $period->owner_id),
            'stats'    => $stats,
            'entries'  => $entries,
            'payments' => $payments,
        ]);
    }

    /** Hitung ulang periode OPEN (mis. setelah impor resi baru). */
    public function recompute(CommissionPeriod $period)
    {
        $this->service->compute($period);
        $stats = $this->service->stats($period);

        return redirect()->route('commissions.show', $period)->with('flash',
            "Komisi dihitung ulang — {$stats['entries']} entri.");
    }

    public function storePayment(Request $request, CommissionPeriod $period)
    {
        $data = $request->validate([
            'paid_date' => ['required', 'date'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'method'    => ['nullable', 'string', 'max:32'],
            'note'      => ['nullable', 'string', 'max:255'],
        ]);

        $payment = CommissionPayment::create($data + [
            'commission_period_id' => $period->id,
            'owner_type'           => $period->owner_type,
            'owner_id'             => $period->owner_id,
            'approved_by'          => $request->user()->id,
        ]);

        AuditLog::record('commission.payment', $period, [], [
            'payment_id' => $payment->id,
            'amount'     => (float) $payment->amount,
            'paid_date'  => $data['paid_date'],
            'reference'  => $data['reference'] ?? null,
        ], 'Catat pembayaran komisi ' . $period->label);

        return back()->with('flash', 'Pembayaran Rp ' . number_format((float) $data['amount'], 0, ',', '.')
            . ' dicatat untuk periode ' . $period->label . '.');
    }

    /** Tutup periode — sisa belum dibayar menjadi saldo periode berikutnya (Z/U11). */
    public function close(Request $request, CommissionPeriod $period)
    {
        $stats = $this->service->stats($period);
        $this->service->close($period, $request->user());

        return back()->with('flash', "Periode {$period->label} ditutup. Sisa belum dibayar Rp "
            . number_format($stats['unpaid'], 0, ',', '.') . ' dipindahkan ke periode berikutnya.');
    }
}
