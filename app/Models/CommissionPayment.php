<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'paid_date' => 'date',
            'amount'    => 'decimal:2',
        ];
    }

    public function period()
    {
        return $this->belongsTo(CommissionPeriod::class, 'commission_period_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
