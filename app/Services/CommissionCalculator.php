<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\Shipment;

/**
 * Kalkulator rantai komisi/profit per resi — replikasi PERSIS `OutputResi` (audit STAGE1 §9.2):
 *
 *   T Biaya COD        = if(O=RETUR,0, Q × 3%)
 *   U PPn COD          = if(O=RETUR,0, T × 11%)
 *   V Ongkir(dgn dk)   = R − S + T + U        W (tanpa dk) = R + T + U
 *   X Perubahan KasCOD = RETUR → −V ; DITERIMA → P+Q−V ; else ""
 *   Z COGS Produk      = HPP Produk × Qty     AA COGS Packing = Packing
 *   AB HPP+Packing     = Z + AA               AC COGS Ops = Biaya Operasional
 *   AD COGS Total      = AB + AC
 *   AE LabaKotor(dgn dk)= RETUR → −((R−S)+(T+U)+AA) ; else (P+Q)−(R−S)−(T+U)−AD
 *   AF LabaKotor(tanpa dk, Implisit) = RETUR → −(R+(T+U)+AA) ; else (P+Q)−R−(T+U)−AD
 *   AG Komisi CS Order = RETUR → −(K8·R + L8) ; else tier pada AF (§9.1)
 *   AH Komisi CS Transfer = if(P>0 and O=DITERIMA,1000,0)
 *   AI Komisi CS >1 Paket = 0 (rumus sel sumber tak tertangkap — audit §10 U6; JANGAN ditebak)
 *   AJ Komisi CS Ongkir   = if(RETUR,0, ((P+Q) − W − HargaJualMin) × 25%)
 *   AK TOTAL Komisi CS    = AG + AH + AI + AJ
 *   AL Komisi Admin Input = 500 (flat per resi berstatus)
 *   AM LabaKotor Eksplisit = (P+Q) − R − (T+U) − AD − AK − AL
 *
 * P = Pembayaran Transfer (Non-COD), Q = Pembayaran COD (audit §7.4: P←AD Non COD, Q←L COD).
 * Tarif diambil dari commission_rules yang BERLAKU pada tanggal resi (create_date) agar
 * revisi tarif tidak mengubah periode yang sudah lewat (audit §9.1; prompt.md §9).
 */
class CommissionCalculator
{
    /** @var array<string,?CommissionRule> */
    private array $ruleCache = [];

    /** @var array<string,?Product> */
    private array $productCache = [];

    /**
     * @return array<string,mixed>|null null bila resi belum punya status internal (bahan Data Error)
     */
    public function compute(Shipment $shipment, ?Product $product = null): ?array
    {
        $status = $shipment->status_internal;
        if ($status === null) {
            return null;
        }

        $product ??= $this->resolveProduct($shipment);
        $ruleDate = ($shipment->create_date ?? $shipment->last_update ?? now())->toDateString();
        $isRetur = $status === 'retur';

        // P & Q (audit §7.4/§7.1): Non-COD = nilai produk saat COD=0.
        $q = (float) $shipment->cod_value;
        $p = $q > 0 ? 0.0 : (float) $shipment->product_value;
        $r = (float) $shipment->shipping_fee;
        $s = (float) $shipment->shipping_discount;

        $codPct = (float) ($this->rule('cod_rate', $ruleDate)?->params['percent'] ?? 3);
        $ppnPct = (float) ($this->rule('ppn_cod_rate', $ruleDate)?->params['percent'] ?? 11);

        $t = $isRetur ? 0.0 : round($q * $codPct / 100, 2);   // Biaya COD
        $u = $isRetur ? 0.0 : round($t * $ppnPct / 100, 2);   // PPn COD
        $v = round($r - $s + $t + $u, 2);                      // ongkir dgn diskon
        $w = round($r + $t + $u, 2);                           // ongkir tanpa diskon

        $x = match ($status) {
            'retur'    => -$v,
            'diterima' => round($p + $q - $v, 2),
            default    => null,
        };

        $qty = (int) $shipment->quantity;
        $z = round((float) ($product->hpp ?? 0) * $qty, 2);         // COGS Produk
        $aa = round((float) ($product->packing_cost ?? 0), 2);      // COGS Packing
        $ab = round($z + $aa, 2);                                   // HPP + Packing
        $ac = round((float) ($product->ops_cost ?? 0), 2);          // Biaya Operasional
        $ad = round($ab + $ac, 2);                                  // Total HPP

        $ae = $isRetur
            ? -round(($r - $s) + ($t + $u) + $aa, 2)
            : round(($p + $q) - ($r - $s) - ($t + $u) - $ad, 2);
        $af = $isRetur
            ? -round($r + ($t + $u) + $aa, 2)
            : round(($p + $q) - $r - ($t + $u) - $ad, 2);

        // AG Komisi CS Order (§9.1/§9.2).
        if ($isRetur) {
            $penalty = $this->rule('retur_penalty', $ruleDate);
            $factor = (float) ($penalty?->params['ongkir_factor'] ?? 0.2);
            $potongan = (float) ($penalty?->params['potongan'] ?? 0);
            $ag = -round($factor * $r + $potongan, 2);
        } else {
            $ag = $this->tierAmount($af, $this->rule('order_tier', $ruleDate));
        }

        // AH Komisi CS Transfer — hanya bila pembayaran transfer & sudah DITERIMA.
        $transferBonus = $this->rule('transfer_bonus', $ruleDate);
        $ah = ($p > 0 && $status === 'diterima') ? round((float) ($transferBonus?->params['amount'] ?? 1000), 2) : 0.0;

        $ai = 0.0; // >1 Paket: rumus sumber tak tertangkap (U6) — dipertahankan 0, bukan tebakan.

        // AJ Komisi CS Ongkir = ((P+Q) − W − HargaJualMin) × 25%.
        $ongkirRule = $this->rule('ongkir_pct', $ruleDate);
        $ongkirPct = (float) ($ongkirRule?->params['percent'] ?? 25);
        $minPrice = $product?->minPriceForQty(max($qty, 1)) ?? 0.0;
        $aj = $isRetur ? 0.0 : round((($p + $q) - $w - $minPrice) * $ongkirPct / 100, 2);

        $ak = round($ag + $ah + $ai + $aj, 2);   // Total Komisi CS
        $al = round((float) ($this->rule('admin_input', $ruleDate)?->params['amount'] ?? 500), 2);

        $am = round(($p + $q) - $r - ($t + $u) - $ad - $ak - $al, 2); // Laba Kotor Eksplisit

        return [
            'status'   => $status,
            'rule_date' => $ruleDate,
            'p' => $p, 'q' => $q, 'r' => $r, 's' => $s,
            't' => $t, 'u' => $u, 'v' => $v, 'w' => $w,
            'x_kas_cod' => $x,
            'y_return_fee' => (float) $shipment->return_fee,
            'z_cogs_produk' => $z, 'aa_cogs_packing' => $aa, 'ab_cogs_hpp_packing' => $ab,
            'ac_cogs_ops' => $ac, 'ad_cogs_total' => $ad,
            'ae_laba_dgn_diskon' => $ae, 'af_laba_tanpa_diskon' => $af,
            'ag_komisi_order' => $ag, 'ah_komisi_transfer' => $ah, 'ai_komisi_multi_paket' => $ai,
            'aj_komisi_ongkir' => $aj, 'ak_komisi_total' => $ak, 'al_komisi_admin_input' => $al,
            'am_laba_eksplisit' => $am,
            'components' => [
                'order'       => $ag,
                'transfer'    => $ah,
                'multi_paket' => $ai,
                'ongkir'      => $aj,
                'admin_input' => $al,
            ],
            'rule_snapshot' => collect(['order_tier', 'retur_penalty', 'transfer_bonus', 'ongkir_pct', 'admin_input', 'cod_rate', 'ppn_cod_rate'])
                ->mapWithKeys(fn ($key) => [$key => [
                    'params'         => $this->rule($key, $ruleDate)?->params,
                    'effective_from' => $this->rule($key, $ruleDate)?->effective_from?->toDateString(),
                ]])
                ->all(),
        ];
    }

    /**
     * Tier komisi order pada Laba Kotor Tanpa-Diskon (§9.1):
     *   AF < 26.000 → 0 ; 26.000 ≤ AF < 89.000 → 5.000 ; AF ≥ 99.001 → % × AF ;
     *   [89.000 .. 99.001) → 0 (gap sumber — audit §10 U5, dipertahankan apa adanya).
     */
    public function tierAmount(float $af, ?CommissionRule $rule): float
    {
        foreach ($rule?->params['brackets'] ?? [] as $bracket) {
            if (isset($bracket['lt']) && $af < (float) $bracket['lt']) {
                return round((float) ($bracket['amount'] ?? 0), 2);
            }
            if (isset($bracket['gap_to']) && $af < (float) $bracket['gap_to']) {
                return round((float) ($bracket['amount'] ?? 0), 2);
            }
            if (isset($bracket['gte']) && $af >= (float) $bracket['gte']) {
                return round($af * (float) ($bracket['percent'] ?? 0) / 100, 2);
            }
        }

        return 0.0;
    }

    /** Aturan aktif pada tanggal tertentu (cache per request). */
    private function rule(string $key, string $date): ?CommissionRule
    {
        $cacheKey = $key . '|' . $date;
        if (! array_key_exists($cacheKey, $this->ruleCache)) {
            $this->ruleCache[$cacheKey] = CommissionRule::activeOn($key, $date)->first();
        }

        return $this->ruleCache[$cacheKey];
    }

    /** Produk resi: order → kode produk resi (audit §7.1: AG Kode Produk). */
    private function resolveProduct(Shipment $shipment): ?Product
    {
        $fromOrder = $shipment->order?->product;
        if ($fromOrder) {
            return $fromOrder;
        }

        $code = (string) $shipment->product_resi_code;
        if ($code === '') {
            return null;
        }
        if (! array_key_exists($code, $this->productCache)) {
            $this->productCache[$code] = Product::where('resi_code', $code)->orWhere('code', $code)->first();
        }

        return $this->productCache[$code];
    }
}
