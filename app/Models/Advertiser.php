<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Advertiser extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function csAgents()
    {
        return $this->hasMany(CsAgent::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}
