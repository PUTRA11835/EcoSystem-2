<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Customer Group struktural.
 *
 * Berbeda dengan Parent–Child (hierarki `customer.parent_customer_id`),
 * Customer Group adalah grouping datar: tiap anggota tetap customer mandiri
 * penuh (punya login, kontak, tiket, akses Jarvies sendiri) — grup hanya
 * mengelompokkan mereka untuk keperluan tampilan.
 */
class CustomerGroup extends Model
{
    protected $table = 'customer_groups';

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Anggota grup (customer mandiri yang tergabung).
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'customer_group_id', 'id');
    }
}
