<?php

namespace Database\Seeders;

use App\Models\CommissionRule;
use Illuminate\Database\Seeder;

/**
 * Tarif komisi dasar dari `Setup Komisi CS` + rantai OutputResi (audit STAGE1 §9.1/§9.2).
 * Semua disimpan sebagai rule effective-dated (effective_from = tanggal mulai berlaku) supaya
 * periode yang sudah ditutup tidak berubah saat tarif direvisi.
 *
 * ⚠️ Gap sumber §10 U5: rentang AF [89.000 .. 99.001) menghasilkan 0 pada workbook. Dipertahankan
 *    apa adanya (bracket 'gap_to_amount') — perlu verifikasi pemilik sebelum produksi.
 */
class CommissionRuleSeeder extends Seeder
{
    public function run(): void
    {
        $from = '2026-01-01';

        // Tier Komisi CS Order pada Laba Kotor Tanpa-Diskon (AF) — §9.1.
        $this->put('order_tier', 'cs', 'Tier Komisi CS Order (berdasar Laba Kotor AF)', [
            'basis'   => 'laba_kotor_tanpa_diskon',
            'brackets' => [
                ['lt' => 26000,             'amount' => 0],      // AF < 26.000 → 0
                ['lt' => 89000,             'amount' => 5000],   // 26.000 ≤ AF < 89.000 → 5.000
                ['gap_to' => 99001,         'amount' => 0],       // [89.000..99.001) → 0 (gap sumber U5)
                ['gte' => 99001, 'percent' => 10],                // AF ≥ 99.001 → 10% × AF
            ],
        ], $from);

        // Komisi CS Transfer — §9.2 AH.
        $this->put('transfer_bonus', 'cs', 'Komisi CS Transfer', [
            'condition' => 'payment_transfer>0 AND status=diterima',
            'amount'    => 1000,
        ], $from);

        // Komisi CS Ongkir — §9.2 AJ = ((P+Q) − W − HargaJualMin) × 25%.
        $this->put('ongkir_pct', 'cs', 'Komisi CS Ongkir', [
            'percent'   => 25,
            'formula'   => '((transfer + cod) - ongkir_tanpa_diskon - harga_jual_min) * 25%',
            'basis'     => 'selisih di atas harga jual minimum (OutputResi!L, INDEX Produk!L:U by qty)',
            'if_retur'  => 0,
        ], $from);

        // Komisi Admin Input — §9.2 AL = 500 flat/resi berstatus.
        $this->put('admin_input', 'shipment', 'Komisi Admin Input', [
            'amount'    => 500,
            'per'       => 'resi_with_status',
        ], $from);

        // Penalti Retur CS Order — §9.1 RETUR → −(0.2 × ongkir + potongan).
        $this->put('retur_penalty', 'cs', 'Penalti Komisi CS saat RETUR', [
            'ongkir_factor' => 0.2,
            'potongan'      => 0,
            'formula'       => '-(0.2 * ongkir + potongan)',
        ], $from);

        // Biaya COD & PPn — §9.2 T/U.
        $this->put('cod_rate', 'shipment', 'Biaya COD', ['percent' => 3, 'if_retur' => 0], $from);
        $this->put('ppn_cod_rate', 'shipment', 'PPn atas Biaya COD', ['percent' => 11], $from);

        // Pemotongan margin bila jual di bawah setup DB — §9.1 catatan ("dipotong 5% margin").
        $this->put('margin_pct_below_setup', 'cs', 'Pemotongan komisi bila harga di bawah setup', [
            'percent' => 5,
        ], $from);

        // PPN atas spend iklan — §8.2 (dipakai rekap ADV/Dashboard).
        $this->put('spend_ppn', 'adv', 'PPN atas Spend Iklan', ['multiplier' => 1.12], $from);
    }

    private function put(string $key, string $appliesTo, string $name, array $params, string $from): void
    {
        CommissionRule::updateOrCreate(
            ['key' => $key, 'effective_from' => $from],
            [
                'applies_to'    => $appliesTo,
                'name'          => $name,
                'params'        => $params,
                'effective_to'  => null,
                'is_active'     => true,
            ]
        );
    }
}
