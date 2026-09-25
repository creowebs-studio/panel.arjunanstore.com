<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model baca-saja di atas view v_phone_history (agregat 'Perform by wa',
 * audit STAGE1 §3.6/§4.3). Masukan untuk aturan positif/negatif.
 */
class PhoneHistory extends Model
{
    protected $table = 'v_phone_history';

    public $timestamps = false;

    protected $primaryKey = 'phone_normalized';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_shipments'   => 'integer',
            'terima_count'      => 'integer',
            'retur_count'       => 'integer',
            'in_progress_count' => 'integer',
            'last_create_date'  => 'datetime',
        ];
    }

    public static function forPhone(?string $normalized): ?self
    {
        if (!$normalized) {
            return null;
        }

        return static::where('phone_normalized', $normalized)->first();
    }
}
