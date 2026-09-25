<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataIssue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function importRow()
    {
        return $this->belongsTo(ImportRow::class);
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}
