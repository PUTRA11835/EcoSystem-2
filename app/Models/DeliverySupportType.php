<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data tipe support delivery (mis. AMS, MO, ATS, CR, ...), dipakai di
 * dropdown "Type" pada form create/edit Delivery Support & filter list.
 * Menggantikan daftar hardcoded lama di Delivery\DeliverySupportController,
 * TicketController & resources/views/delivery/support/**.
 */
class DeliverySupportType extends Model
{
    protected $table = 'delivery_support_types';

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
