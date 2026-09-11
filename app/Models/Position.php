<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Position employee — referensi tunggal untuk opsi dropdown Position.
 * Lihat migration create_positions_table.
 */
class Position extends Model
{
    protected $table = 'positions';

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Daftar nama position aktif (urut sort_order) — dipakai untuk render dropdown.
     *
     * @return string[]
     */
    public static function options(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
