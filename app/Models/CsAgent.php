<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsAgent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function advertiser()
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
