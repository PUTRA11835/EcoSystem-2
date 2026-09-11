<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Department employee — referensi tunggal untuk opsi dropdown Department.
 * Lihat migration create_departments_table.
 */
class Department extends Model
{
    protected $table = 'departments';

    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Daftar nama department aktif (urut sort_order) — dipakai untuk render dropdown.
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
