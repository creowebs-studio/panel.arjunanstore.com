<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentStatusEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status_date'        => 'datetime',
            'shipping_fee'       => 'decimal:2',
            'shipping_discount'  => 'decimal:2',
            'cod_fee'            => 'decimal:2',
            'return_fee'         => 'decimal:2',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
