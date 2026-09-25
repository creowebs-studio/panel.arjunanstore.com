<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarrierStatusMapping extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'effective_from' => 'date'];
    }

    /** Terjemahkan status mentah platform ke status internal (5 state). */
    public static function internalFor(string $platform, ?string $rawStatus): ?string
    {
        if ($rawStatus === null || $rawStatus === '') {
            return null;
        }
        return static::where('is_active', true)
            ->whereIn('platform', [$platform, 'general'])
            ->whereRaw('UPPER(status_system) = ?', [strtoupper($rawStatus)])
            ->orderByRaw('CASE platform WHEN ? THEN 0 ELSE 1 END', [$platform])
            ->value('status_internal');
    }
}
