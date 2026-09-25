<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'create_date'       => 'datetime',
            'last_update'       => 'datetime',
            'cod_value'         => 'decimal:2',
            'product_value'     => 'decimal:2',
            'shipping_fee'      => 'decimal:2',
            'shipping_discount' => 'decimal:2',
            'cod_fee'           => 'decimal:2',
            'return_fee'        => 'decimal:2',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function statusEvents()
    {
        return $this->hasMany(ShipmentStatusEvent::class);
    }

    /** Biaya ekspedisi bersih (dengan diskon) — padanan OutputResi!V (audit §9.2). */
    public function netShippingCost(): float
    {
        return (float) ($this->shipping_fee - $this->shipping_discount + $this->cod_fee);
    }

    /**
     * Atribusi ADV (audit §7.1: AE KodeADV dari remark) — dipakai rekap ADV, dashboard, komisi.
     * Prioritas: kode resi yang dipecah dari remark → kampanye → (opsional) kode ADV langsung.
     */
    public function scopeForAdvertiser($query, Advertiser $advertiser)
    {
        $codes = array_values(array_filter([$advertiser->resi_code, $advertiser->code]));

        return $query->where(function ($q) use ($advertiser, $codes) {
            if ($codes !== []) {
                $q->orWhereIn('adv_resi_code', $codes);
            }
            $q->orWhereHas('campaign', fn ($c) => $c->where('advertiser_id', $advertiser->id));
        });
    }

    /** Atribusi CS (audit §7.1: AF Kode CS) — dipakai laporan komisi CS & dashboard. */
    public function scopeForCsAgent($query, CsAgent $agent)
    {
        $codes = array_values(array_filter([$agent->resi_cs_code, $agent->code]));

        return $query->where(function ($q) use ($agent, $codes) {
            if ($codes !== []) {
                $q->orWhereIn('cs_resi_code', $codes);
            }
            $q->orWhereHas('order', fn ($o) => $o->where('cs_agent_id', $agent->id));
        });
    }
}
