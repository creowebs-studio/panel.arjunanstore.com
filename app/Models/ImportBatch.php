<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function rows()
    {
        return $this->hasMany(ImportRow::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
