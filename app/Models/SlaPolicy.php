<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    protected $table = 'sla_policies';

    protected $fillable = [
        'customer_id',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
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
     * Cari policy yang cocok: customer-specific lebih diprioritaskan dari global.
     * Fallback ke NULL customer_id (global default) jika tidak ada yang spesifik.
     */
    public static function findFor(?int $customerId, string $priority, string $scale): ?self
    {
        return self::where('priority', $priority)
            ->where('scale', $scale)
            ->where('is_active', true)
            ->where(function ($q) use ($customerId) {
                if ($customerId !== null) {
                    $q->where('customer_id', $customerId)
                      ->orWhereNull('customer_id');
                } else {
                    // Tiket tanpa customer → hanya cari global policy
                    $q->whereNull('customer_id');
                }
            })
            ->orderByRaw('customer_id IS NULL ASC')
            ->first();
    }
}
