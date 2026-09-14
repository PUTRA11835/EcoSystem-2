<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data tipe project delivery (mis. Implementation, Roll Out, Body
 * Hire, ...), dipakai di dropdown "Project Type" pada form create/edit
 * Delivery Project. Menggantikan daftar hardcoded lama di
 * DeliveryProjectController & resources/views/delivery/project/**.
 */
class DeliveryProjectType extends Model
{
    protected $table = 'delivery_project_types';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'order_seq',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
