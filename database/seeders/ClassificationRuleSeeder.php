<?php

namespace Database\Seeders;

use App\Models\RuleVersion;
use Illuminate\Database\Seeder;

/**
 * Versi aktif aturan klasifikasi nomor (prompt.md §4.5 "simpan versi aturan").
 * definition menyalin decision tree AK/AP/AQ (audit STAGE1 §4.3) agar hasil lama dapat
 * ditelusuri ke aturan yang berlaku saat itu.
 */
class ClassificationRuleSeeder extends Seeder
{
    public function run(): void
    {
        RuleVersion::updateOrCreate(
            ['name' => 'phone_classification', 'version' => 'v1'],
            [
                'definition' => [
                    'source' => 'ID 01 Admin!Input AK/AP/AQ (STAGE1 §4.3)',
                    'AK_last_order' => 'status_terakhir==retur ? Negatif : Positif (tanpa riwayat→Positif)',
                    'AP_by_wa' => 'if in_progress→Negatif elif retur>0→Negatif elif terima>0→Positif else DataBelumTersedia',
                    'AQ_final' => 'if AK==Negatif→Negatif elif AP==Negatif→Negatif elif AP==Positif→Negatif else Positif',
                    'key_insight' => 'Positif hanya bila nomor BELUM punya riwayat berarti; pernah diterima ⇒ Negatif (anti kirim ulang)',
                ],
                'effective_from' => '2026-01-01',
                'is_active'      => true,
            ]
        );
    }
}
