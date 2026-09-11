<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliverySupportUpdate extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_updates';

    protected $fillable = [
        'delivery_support_id',
        'highlight_issue',
        'action',
        'due_date',
        'status',
        'complexity',
        'deliverable',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['completed', 'closed']);
    }

    public function scopeUpcoming($query, $days = 7)
    {
        return $query->whereBetween('due_date', [now(), now()->addDays($days)])
            ->whereNotIn('status', ['completed', 'closed']);
    }
}
