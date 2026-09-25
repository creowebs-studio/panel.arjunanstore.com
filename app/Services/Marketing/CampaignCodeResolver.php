<?php

namespace App\Services\Marketing;

use App\Models\Advertiser;
use App\Models\Campaign;
use App\Models\CsAgent;
use App\Models\Product;

/**
 * Pemetaan kode dari NAMA KAMPANYE ke master (audit STAGE1 §8.2):
 *  - D Kode ADVS  : `LEFT(nama, 4)` → ADV!B:C (fallback "XXXX")
 *  - E Kode CS    : `REGEXEXTRACT(nama, "-(.*?)-")` → ADV_CS (fallback "XXXX")
 *  - F Kode Produk: `RIGHT(nama, 2)` → Produk (fallback "XX")
 * Format acuan: `AMxx-ARyy-PZ`.
 */
class CampaignCodeResolver
{
    /** @return array{adv:?string,cs:?string,product:?string} */
    public function tokens(string $name): array
    {
        $name = trim($name);

        return [
            'adv'     => strlen($name) >= 4 ? (substr($name, 0, 4) ?: null) : null,
            'cs'      => preg_match('/-(.*?)-/', $name, $m) ? ($m[1] ?: null) : null,
            'product' => strlen($name) >= 2 ? (substr($name, -2) ?: null) : null,
        ];
    }

    /**
     * Sinkronkan pemetaan kampanye ke master; kembalikan token yang GAGAL dikenal
     * (mis. ['adv' => 'AM99', 'product' => 'XX']) agar dapat ditampilkan sebagai worklist.
     *
     * @return array<string,string>
     */
    public function sync(Campaign $campaign): array
    {
        $t = $this->tokens($campaign->name);

        $adv = $t['adv'] ? Advertiser::where('code', $t['adv'])->orWhere('resi_code', $t['adv'])->first() : null;
        $cs = $t['cs'] ? CsAgent::where('code', $t['cs'])->orWhere('lookup_key', $t['cs'])->orWhere('resi_cs_code', $t['cs'])->first() : null;
        $prod = $t['product'] ? Product::where('code', $t['product'])->orWhere('resi_code', $t['product'])->first() : null;

        $unresolved = [];
        if (! $adv) {
            $unresolved['adv'] = (string) $t['adv'];
        }
        if (! $cs) {
            $unresolved['cs'] = (string) $t['cs'];
        }
        if (! $prod) {
            $unresolved['product'] = (string) $t['product'];
        }

        $campaign->update([
            'adv_code'          => $t['adv'],
            'cs_code'           => $t['cs'],
            'product_resi_code' => $t['product'],
            'advertiser_id'     => $adv?->id,
            'cs_agent_id'       => $cs?->id,
            'product_id'        => $prod?->id,
            'is_mapped'         => $unresolved === [],
        ]);

        return $unresolved;
    }
}
