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

    /**
     * Daftar nama grade tanpa suffix " Consultant" (mis. "Junior Consultant" -> "Junior").
     * Dipakai untuk opsi dropdown "Level" di Employee Qualification (tipe Certification) —
     * konsep career-level yang sama dengan Grade, cuma labelnya lebih singkat.
     *
     * @return string[]
     */
    public static function levelOptions(): array
    {
        return array_map(
            fn (string $name) => preg_replace('/\s*Consultant$/', '', $name),
            static::options()
        );
    }

    /**
     * Resolve the `sort_order` for a level name as stored on
     * EmployeeQualification.qualification_level — which uses the short form
     * from levelOptions() (e.g. "Middle"), not the full grade name ("Middle
     * Consultant"). Accepts either form. Returns null if no match.
     */
    public static function sortOrderForLevel(?string $level): ?int
    {
        $level = trim((string) $level);
        if ($level === '') {
            return null;
        }

        return static::query()
            ->where('is_active', true)
            ->where(function ($q) use ($level) {
                $q->where('name', $level)
                  ->orWhere('name', $level . ' Consultant');
            })
            ->value('sort_order');
    }
}
