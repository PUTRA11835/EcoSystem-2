<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryProjectPaymentTerm extends Model
{
    use HasFactory;

    protected $table = 'delivery_project_payment_terms';

    protected $fillable = [
        'delivery_projects_id',
        'term_number',
        'payment_term',
        'payment_percentage',
        'amount',
        'requirements',
        'estimated_date',
        'submit_invoice_date',
        'invoice_number',
        'paid_date',
        'status',
    ];

    protected $casts = [
        'term_number'         => 'integer',
        'payment_percentage'  => 'decimal:2',
        'amount'              => 'decimal:2',
        'estimated_date'      => 'date',
        'submit_invoice_date' => 'date',
        'paid_date'           => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }
}
