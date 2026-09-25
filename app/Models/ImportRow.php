<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_data'    => 'array',
            'mapped_data' => 'array',
        ];
    }

    public function batch()
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function issues()
    {
        return $this->hasMany(DataIssue::class);
    }
}
