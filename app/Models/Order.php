<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_date'            => 'date',
            'price'                 => 'decimal:2',
            'weight'                => 'decimal:2',
            'is_manual_override'    => 'boolean',
            'validated_at'          => 'datetime',
            'override_at'           => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function csAgent()
    {
        return $this->belongsTo(CsAgent::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function validations()
    {
        return $this->hasMany(OrderValidation::class);
    }

    public function ruleVersion()
    {
        return $this->belongsTo(RuleVersion::class);
    }

    public function overrideBy()
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    /** Batch ekspor yang memuat order ini (jejak Alur B). */
    public function exportBatchItems()
    {
        return $this->hasMany(ExportBatchItem::class);
    }

    /** Jejak audit koreksi/tindakan atas order ini. */
    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /** Boleh diekspor ke antrean positif? (positif + data wajib lengkap) */
    public function isExportable(): bool
    {
        return $this->classification === 'positif'
            && ! empty($this->address_detail)
            && ! empty($this->kelurahan)
            && ! empty($this->kecamatan)
            && $this->product_id !== null;
    }
}
