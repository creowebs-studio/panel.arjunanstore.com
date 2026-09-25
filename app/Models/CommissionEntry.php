<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris komponen komisi per resi (OutputResi AG:AK, audit STAGE1 §9.2).
 * amount disimpan bersama rule_snapshot agar dapat diaudit saat dihitung.
 */
class CommissionEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_on_progress'  => 'boolean',
            'is_payable'      => 'boolean',
            'amount'          => 'decimal:2',
            'rule_snapshot'   => 'array',
            'computed_at'     => 'datetime',
        ];
    }

    public function period()
    {
        return $this->belongsTo(CommissionPeriod::class, 'commission_period_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function ruleVersion()
    {
        return $this->belongsTo(RuleVersion::class);
    }
}
