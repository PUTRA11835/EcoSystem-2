<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupportCostItem extends Model
{
    use HasFactory;

    protected $table = 'delivery_support_cost_items';

    protected $fillable = [
        'delivery_support_cost_id',
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
        return $this->belongsTo(DeliverySupportCost::class, 'delivery_support_cost_id');
    }
}
