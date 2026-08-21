<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliveryProjectCostItem extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Project';

    protected $table = 'delivery_project_cost_items';

    protected $fillable = [
        'delivery_project_cost_id',
        'description',
        'amount',
        'document_name',
        'document_file_id',
        'document_url',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function costItem()
    {
        return $this->belongsTo(DeliveryProjectCost::class, 'delivery_project_cost_id');
    }
}
