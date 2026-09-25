<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Periode komisi. Memisahkan PERIODE CS (16–15) dari PERIODE ADV (bulan kalender)
 * agar tidak tertukar (audit STAGE1 §9.4).
 */
class CommissionPeriod extends Model
{
    protected $guarded = [];

    public const CS_PERIOD_TYPE = 'cs_16_15';

    public const ADV_PERIOD_TYPE = 'calendar_month';

    protected function casts(): array
    {
        return [
            'start_date'      => 'date',
            'end_date'        => 'date',
            'closed_at'       => 'datetime',
            'carried_balance' => 'decimal:2',
        ];
    }

    public function entries()
    {
        return $this->hasMany(CommissionEntry::class);
    }

    public function payments()
    {
        return $this->hasMany(CommissionPayment::class);
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Pemilik (CS atau ADV) secara polymorphic-by-type. */
    public function owner()
    {
        return $this->owner_type === 'cs'
            ? CsAgent::find($this->owner_id)
            : Advertiser::find($this->owner_id);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Jendela periode (audit §9.4 — WAJIB terpisah):
     *  - CS  : 16 bulan sebelumnya s/d 15 bulan berjalan (pola `CS MEI` = 16 Apr–15 Mei).
     *  - ADV : bulan kalender penuh (pola `Dashboard`/`ADV <BULAN>` via EOMONTH).
     *
     * @return array{0:Carbon,1:Carbon} [start 00:00, end 23:59:59]
     */
    public static function windowFor(string $ownerType, int $year, int $month): array
    {
        if ($ownerType === 'cs') {
            $start = Carbon::create($year, $month, 1)->startOfMonth()->subMonthNoOverflow()->day(16)->startOfDay();
            $end = Carbon::create($year, $month, 15)->endOfDay();
        } else {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        }

        return [$start, $end];
    }

    /** Label eksplisit pada laporan (prompt.md §9: tampilkan definisi periode). */
    public static function labelFor(string $ownerType, string $ownerName, string $monthLabel, Carbon $start, Carbon $end): string
    {
        $def = $ownerType === 'cs' ? 'CS 16–15' : 'ADV bulan kalender';

        return sprintf('%s %s (%s: %s s/d %s)', strtoupper($ownerType), $ownerName, $def, $start->format('d/m/Y'), $end->format('d/m/Y'))
            . ' — ' . $monthLabel;
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
