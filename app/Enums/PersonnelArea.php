<?php

namespace App\Enums;

/**
 * Personnel Area employee. Label murni, daftar pendek & stabil, sehingga cukup
 * enum PHP (bukan tabel) — sama pola dengan HomeBase.
 *
 * Penggunaan:
 *   PersonnelArea::options()   // ['BACK OFFICE', 'SALES', 'OPERATION']
 */
enum PersonnelArea: string
{
    case BACK_OFFICE = 'BACK OFFICE';
    case SALES       = 'SALES';
    case OPERATION   = 'OPERATION';

    /**
     * @return string[]
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
