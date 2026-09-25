<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderValidation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence'   => 'array',
            'checked_at' => 'datetime',
        ];
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
