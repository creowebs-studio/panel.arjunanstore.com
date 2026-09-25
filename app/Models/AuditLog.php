<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * jejak audit untuk koreksi data & tindakan finansial (prompt.md §4.6, §12).
 */
class AuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo('auditable');
    }

    /** Helper pembuatan entri audit dari request berjalan. */
    public static function record(string $action, ?Model $subject, array $old, array $new, ?string $reason = null): self
    {
        $user = auth()->user();
        $request = request();

        return static::create([
            'user_id'          => $user?->id,
            'action'           => $action,
            'auditable_type'   => $subject ? get_class($subject) : null,
            'auditable_id'     => $subject?->getKey(),
            'old_values'       => $old,
            'new_values'       => $new,
            'reason'           => $reason,
            'ip'               => $request?->ip(),
            'user_agent'       => $request?->userAgent(),
        ]);
    }
}
