<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tarif komisi bertanggal-berlaku (effective-dated) agar periode yang sudah
 * ditutup tidak berubah nilainya saat tarif baru dimasukkan (audit STAGE1 §9.1/§9.2).
 */
class CommissionRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'params'         => 'array',
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Rule aktif untuk key + tanggal tertentu (efektif berlaku, belum berakhir, is_active). */
    public function scopeActiveOn($query, string $key, $date)
    {
        $date = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date;

        return $query->where('key', $key)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from');
    }
}
