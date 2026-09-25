<?php

namespace Database\Seeders;

use App\Models\CarrierStatusMapping;
use Illuminate\Database\Seeder;

/**
 * Tabel pemetaan status agregator → status internal 5-state (audit STAGE1 §7.2).
 * platform='general' dipakai lintas Mengantar/Lincah bila kode status sama.
 *
 * Diselaraskan PERSIS dengan tab `Status (Agregator)` workbook (56 baris, Tahap 6):
 * Status System → Status Manual. Beberapa varian ejaan data nyata ditambahkan
 * (mis. 'WEEKEND OR HOLIDAY', 'DELIVERED (Pending)' dengan spasi) agar tidak muncul
 * sebagai worklist palsu; arahkan ke internal yang sama dengan versi workbook.
 *
 * Catatan bisnis: RETURN IN PROGRESS → UNDEL (bukan RETUR) — dipertahankan sesuai sumber.
 * Status yang tidak ada di sini → status_internal kosong → masuk Data Error.
 */
class CarrierStatusMappingSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'dikirim' => [
                'INCOMING', 'MANIFEST OUTGOING', 'MISSROUTE', 'ON DELIVERY', 'OUTGOING',
                'OUTGOING SMU', 'REDELIVERY', 'TAKE SELF', 'FORWARDED', 'TRANSIT CITY',
                'SORTING CENTER', 'WAREHOUSE', 'NEED REDELIVERY', 'SHIPMENT ISSUE',
                'WEEKEND OR HOLYDAY', 'PICKED UP', 'INBOUND STATION', 'ORIGIN GATEWAY',
                'ON PROCESS', 'DELIVERY COURIER', 'AT COUNTER', 'OUTBOUND', 'INBOUND PROCESS',
                // Varian ejaan data nyata:
                'WEEKEND OR HOLIDAY',
            ],
            'undel' => [
                'UNDELIVERED', 'BAD ADDRESS', 'CLOSED OR NOT AT HOME', 'RECEIVER ISSUE',
                'PARCEL LOST', 'SHIPMENT DAMAGE', 'RECEIVER RESIGNED', 'REJECTED',
                'UNKNOWN RESEIVER', 'WANT TO OPEN PARCEL', 'CONSIGNEE NOT AVAILABLE',
                'ON HOLD', 'DELIVERY PROBLEM', 'NEW ADDRESS', 'SHIPPING PROBLEM',
                'PARCEL DAMAGED', 'IRREGULARITY', 'INCOMPLETE ADDRESS', 'SHIPMENT BREACH',
                'INTERNAL BREACH',
                // Varian ejaan data nyata:
                'CLOSED OR NOT AVAILABLE',
            ],
            'diterima' => [
                'DELIVERED', 'DELIVERED(PENDING)',
                // Varian ejaan data nyata:
                'DELIVERED (PENDING)',
            ],
            'packing' => [
                'PENDING PICKUP', 'AWAITING PICKUP', 'PICKUP FAILED',
            ],
            'retur' => [
                'DELIVERY RETURN', 'RTS', 'RETURN PROCESS', 'RTS IN PROGRESS', 'PRE RTS',
                'SHIPMENT RETURN',
            ],
        ];

        // Keterangan khusus (beberapa status punya nuansa) — disalin dari workbook.
        $keterangan = [
            'RETURN IN PROGRESS'   => 'Pengembalian sedang diproses (UNDEL per keputusan sumber)',
            'DELIVERED (PENDING)'  => 'Terkirim tapi berstatus pending',
            'DELIVERY PROBLEM'     => 'Masalah dengan alamat, ketidaktersediaan penerima',
            'SHIPPING PROBLEM'     => 'Masalah dengan kurir atau jadwal pengiriman',
            'UNKNOWN RESEIVER'     => 'Penerima tidak dikenal',
        ];

        foreach ($map as $internal => $statuses) {
            foreach ($statuses as $status) {
                $this->upsert('general', $status, $internal, $keterangan[strtoupper($status)] ?? null);
            }
        }

        // RETURN IN PROGRESS secara eksplisit UNDEL (§7.2 ⚠️).
        $this->upsert('general', 'RETURN IN PROGRESS', 'undel', $keterangan['RETURN IN PROGRESS']);
    }

    private function upsert(string $platform, string $system, string $internal, ?string $ket): void
    {
        CarrierStatusMapping::updateOrCreate(
            ['platform' => $platform, 'status_system' => $system],
            [
                'status_internal' => $internal,
                'keterangan'      => $ket,
                'is_active'       => true,
                'effective_from'  => now()->toDateString(),
            ]
        );
    }
}
