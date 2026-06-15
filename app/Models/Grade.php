<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Grade consultant — referensi tunggal untuk opsi dropdown grade & harga MD.
 * Lihat migration create_grades_table.
 */
class Grade extends Model
{
    protected $table = 'grades';

    protected $fillable = ['name', 'md_price', 'sort_order', 'is_active'];

    protected $casts = [
        'md_price'   => 'decimal:2',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Daftar nama grade aktif (urut sort_order) — dipakai untuk render dropdown
     * & normalisasi import. Mengembalikan array string nama.
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
