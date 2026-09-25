<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CarrierStatusMapping;
use App\Models\DataIssue;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Peninjauan pemetaan status agregator → 5 state internal (prompt.md §7:
 * "pemetaan status ... harus mengikuti workbook dan dapat ditinjau admin berwenang").
 * Guard: permission carriers.mapping.manage (superadmin lolos otomatis).
 */
class CarrierMappingController extends Controller
{
    private const INTERNAL = ['packing', 'dikirim', 'undel', 'diterima', 'retur'];

    public function index()
    {
        return Inertia::render('CarrierMappings/Index', [
            'mappings' => CarrierStatusMapping::orderBy('platform')->orderBy('status_system')->get(),
            'internal' => self::INTERNAL,
            'unmapped' => $this->unmappedStatuses(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'platform'        => ['required', 'in:mengantar,lincah,general'],
            'status_system'   => ['required', 'string', 'max:64'],
            'status_internal' => ['required', 'in:' . implode(',', self::INTERNAL)],
            'keterangan'      => ['nullable', 'string', 'max:255'],
        ]);
        $data['status_system'] = strtoupper(trim($data['status_system']));
        $data['is_active'] = true;

        $exists = CarrierStatusMapping::where('platform', $data['platform'])
            ->where('status_system', $data['status_system'])->exists();
        if ($exists) {
            return back()->withErrors(['status_system' => 'Pemetaan untuk status ini sudah ada — sunting baris yang ada.']);
        }

        $mapping = CarrierStatusMapping::create($data);
        AuditLog::record('carrier_mapping.created', $mapping, [], $mapping->only(['id', 'platform', 'status_system', 'status_internal']));

        return back()->with('flash', "Pemetaan {$data['platform']} / {$data['status_system']} → {$data['status_internal']} dibuat.");
    }

    public function update(Request $request, CarrierStatusMapping $mapping)
    {
        $data = $request->validate([
            'status_internal' => ['required', 'in:' . implode(',', self::INTERNAL)],
            'keterangan'      => ['nullable', 'string', 'max:255'],
            'is_active'       => ['sometimes', 'boolean'],
        ]);

        $old = $mapping->only(['status_internal', 'keterangan', 'is_active']);
        $mapping->update([
            'status_internal' => $data['status_internal'],
            'keterangan'      => $data['keterangan'] ?? null,
            'is_active'       => $request->boolean('is_active'),
        ]);
        AuditLog::record('carrier_mapping.updated', $mapping, $old,
            $mapping->only(['status_internal', 'keterangan', 'is_active']));

        return back()->with('flash', "Pemetaan {$mapping->platform} / {$mapping->status_system} diperbarui.");
    }

    /**
     * Terapkan pemetaan terkini ke resi lama yang status internalnya masih kosong
     * (baris dengan status tak terpetakan diproses sebagai 'new', bukan 'error',
     * sehingga tidak terikut "proses ulang baris error"). Issue status_unmapped
     * yang terbuka otomatis ditutup bila sekarang sudah punya pemetaan.
     */
    public function sync(Request $request)
    {
        $fixed = 0;
        $userId = $request->user()->id;

        Shipment::whereNull('status_internal')->whereNotNull('status_raw')->where('status_raw', '<>', '')
            ->chunkById(200, function ($shipments) use (&$fixed, $userId) {
                foreach ($shipments as $s) {
                    $internal = CarrierStatusMapping::internalFor($s->platform, $s->status_raw);
                    if (! $internal) {
                        continue;
                    }

                    $s->update(['status_internal' => $internal]);
                    // Rapikan riwayat yang belum terpetakan untuk status yang sama.
                    $s->statusEvents()->where('status_raw', $s->status_raw)
                        ->whereNull('status_internal')->update(['status_internal' => $internal]);
                    // Tutup Data Error status_unmapped yang kini sudah terselesaikan.
                    DataIssue::where('shipment_id', $s->id)->where('type', 'status_unmapped')
                        ->where('status', 'open')->update([
                            'status'      => 'resolved',
                            'resolved_by' => $userId,
                            'resolved_at' => now(),
                            'message'     => DB::raw("CONCAT(message, ' — dipetakan ulang via halaman pemetaan status')"),
                        ]);
                    $fixed++;
                }
            });

        AuditLog::record('carrier_mapping.sync', null,
            ['unmapped' => null], ['shipments_fixed' => $fixed], 'Sinkronisasi pemetaan status ke resi lama');

        return back()->with('flash', $fixed > 0
            ? "Sinkronisasi selesai: {$fixed} resi kini punya status internal."
            : 'Tidak ada resi yang perlu diperbaiki — semua sudah terpetakan.');
    }

    /**
     * Status mentah yang pernah masuk dari impor tetapi belum punya pemetaan aktif —
     * daftar kerja admin supaya Data Error tidak menumpuk (prompt.md §7.2).
     */
    private function unmappedStatuses()
    {
        $keys = CarrierStatusMapping::where('is_active', true)->get()
            ->map(fn ($m) => $m->platform . '|' . strtoupper($m->status_system))
            ->all();

        return Shipment::select('platform', 'status_raw', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('status_raw')->where('status_raw', '<>', '')
            ->groupBy('platform', 'status_raw')
            ->get()
            ->reject(fn ($r) => in_array($r->platform . '|' . strtoupper($r->status_raw), $keys, true)
                || in_array('general|' . strtoupper($r->status_raw), $keys, true))
            ->sortByDesc('jumlah')
            ->values();
    }
}
