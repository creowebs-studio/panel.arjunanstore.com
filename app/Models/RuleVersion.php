<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'definition'     => 'array',
            'effective_from' => 'date',
            'is_active'      => 'boolean',
        ];
    }

    public static function active(string $name = 'phone_classification'): ?self
    {
        return static::where('is_active', true)
            ->where('name', $name)
            ->orderByDesc('effective_from')
            ->first();
    }
}
