<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingDailyReport extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_start' => 'date',
            'date_end'   => 'date',
            'spend_raw'  => 'decimal:2',
            'spend_ppn'  => 'decimal:2',
        ];
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }
}
