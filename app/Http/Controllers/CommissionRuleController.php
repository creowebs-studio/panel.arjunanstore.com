<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Riwayat perubahan tarif komisi (prompt.md §9: "Riwayat perubahan tarif komisi dengan
 * tanggal berlaku agar perubahan tarif tidak diam-diam mengubah periode yang sudah ditutup").
 *
 * Versi baru = baris commission_rules baru dengan `effective_from`; versi lama ditutup
 * (`effective_to` = sehari sebelum versi baru). Kalkulator selalu memilih tarif yang
 * berlaku pada TANGGAL RESI, bukan tarif terbaru (audit §9.1).
 */
class CommissionRuleController extends Controller
{
    /** Kunci aturan yang dikenal kalkulator (audit §9.1/§9.2 + §8.2). */
    public const KEYS = [
        'order_tier', 'retur_penalty', 'transfer_bonus', 'ongkir_pct',
        'admin_input', 'cod_rate', 'ppn_cod_rate', 'margin_pct_below_setup', 'spend_ppn',
    ];

    public function index()
    {
        $rules = CommissionRule::with('reviewer')
            ->orderBy('key')->orderByDesc('effective_from')
            ->get()
            ->groupBy('key');

        return Inertia::render('Komisi/Rules', [
            'rules' => $rules,
            'keys'  => self::KEYS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'key'            => ['required', 'in:' . implode(',', self::KEYS)],
            'applies_to'     => ['required', 'in:cs,adv,shipment'],
            'name'           => ['required', 'string', 'max:255'],
            'params'         => ['required', 'string'],
            'effective_from' => ['required', 'date'],
        ]);

        $params = json_decode($data['params'], true);
        if (! is_array($params)) {
            return back()->withErrors(['params' => 'Params harus berupa JSON objek yang valid.'])->withInput();
        }

        $rule = DB::transaction(function () use ($data, $params, $request) {
            // Tutup versi terbuka sebelumnya agar rentang berlaku tidak tumpang tindih.
            CommissionRule::where('key', $data['key'])
                ->where('is_active', true)
                ->whereNull('effective_to')
                ->whereDate('effective_from', '<', $data['effective_from'])
                ->update(['effective_to' => Carbon::parse($data['effective_from'])->subDay()->toDateString()]);

            return CommissionRule::updateOrCreate(
                ['key' => $data['key'], 'effective_from' => $data['effective_from']],
                [
                    'applies_to'  => $data['applies_to'],
                    'name'        => $data['name'],
                    'params'      => $params,
                    'is_active'   => true,
                    'reviewed_by' => $request->user()->id,
                ]
            );
        });

        AuditLog::record('commission_rule.create', $rule, [], [
            'key'            => $rule->key,
            'effective_from' => $data['effective_from'],
            'params'         => $params,
        ], 'Versi tarif komisi baru');

        return back()->with('flash', "Versi tarif {$rule->key} berlaku mulai {$data['effective_from']} disimpan. "
            . 'Periode yang sudah dihitung/ditutup tidak berubah — hitung ulang hanya periode OPEN.');
    }
}
