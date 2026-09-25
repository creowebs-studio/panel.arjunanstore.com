<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_reexport' => 'boolean'];
    }

    public function items()
    {
        return $this->hasMany(ExportBatchItem::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'export_batch_items');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
