<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_sell_pcs'      => 'decimal:2',
            'hpp'                 => 'decimal:2',
            'packing_cost'        => 'decimal:2',
            'total_hpp'           => 'decimal:2',
            'ops_cost'            => 'decimal:2',
            'komisi_cs_input'     => 'decimal:2',
            'cogs'                => 'decimal:2',
            'price_sell_paket'    => 'decimal:2',
            'margin_per_product'  => 'decimal:2',
            'min_price_by_qty'    => 'array',
            'is_active'           => 'boolean',
        ];
    }

    /** Harga jual minimum utk qty tertentu (padanan INDEX(Produk!L:U,,qty) di sumber). */
    public function minPriceForQty(int $qty): ?float
    {
        $list = $this->min_price_by_qty ?? [];
        return isset($list[$qty]) ? (float) $list[$qty] : ($list['default'] ?? null);
    }
}
