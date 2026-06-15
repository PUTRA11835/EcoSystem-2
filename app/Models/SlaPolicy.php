<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $table = 'sla_policies';

    protected $fillable = [
        'delivery_support_id',
        'priority',
        'scale',
        'response_hours',
        'resolution_hours',
        'is_24_hours',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'response_hours'   => 'decimal:2',
        'resolution_hours' => 'decimal:2',
        'is_24_hours'      => 'boolean',
        'is_active'        => 'boolean',
    ];

    // Very High priority always uses 24/7 calendar hours
    public function getIs24HoursAttribute($value): bool
    {
        return $this->attributes['priority'] === 'Very High' ? true : (bool) $value;
    }

    public function deliverySupport()
    {
        return $this->belongsTo(\App\Models\DeliverySupport::class, 'delivery_support_id', 'id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    public function ticketSlas()
    {
        return $this->hasMany(TicketSla::class, 'sla_policy_id');
    }

    /**
     * Cari policy yang cocok untuk delivery support + priority + scale tertentu.
     */
    public static function findFor(?int $deliverySupportId, string $priority, string $scale): ?self
    {
        return self::where('priority', $priority)
            ->where('scale', $scale)
            ->where('is_active', true)
            ->where('delivery_support_id', $deliverySupportId)
            ->first();
    }
}
