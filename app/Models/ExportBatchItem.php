<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportBatchItem extends Model
{
    protected $guarded = [];

    public function exportBatch()
    {
        return $this->belongsTo(ExportBatch::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
