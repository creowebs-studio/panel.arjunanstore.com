<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_mapped' => 'boolean'];
    }

    public function advertiser()
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function csAgent()
    {
        return $this->belongsTo(CsAgent::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function dailyReports()
    {
        return $this->hasMany(MarketingDailyReport::class);
    }
}
