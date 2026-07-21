<?php

namespace App\Enums;

/**
 * Employee Group. Label murni, daftar pendek & stabil, sehingga cukup
 * enum PHP (bukan tabel) — sama pola dengan HomeBase.
 *
 * Penggunaan:
 *   EmployeeGroup::options()   // ['INTERNAL', 'EXTERNAL', 'INTERNSHIP']
 */
enum EmployeeGroup: string
{
    case INTERNAL   = 'INTERNAL';
    case EXTERNAL   = 'EXTERNAL';
    case INTERNSHIP = 'INTERNSHIP';

    /**
     * @return string[]
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
