<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    public function ticketSlas(): HasMany
    {
        return $this->hasMany(TicketSla::class, 'sla_policy_id');
    }

    // Cari policy yang cocok: exact match dulu, fallback ke global (customer_id NULL)
    public static function findFor(int $customerId, string $priority, string $scale): ?self
    {
        return self::where('is_active', true)
            ->where('priority', $priority)
            ->where('scale', $scale)
            ->where(function ($q) use ($customerId) {
                $q->where('customer_id', $customerId)
                  ->orWhereNull('customer_id');
            })
            ->orderByRaw('customer_id IS NULL ASC') // customer-specific lebih prioritas dari global
            ->first();
    }
}
