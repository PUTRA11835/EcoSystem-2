<?php

namespace App\Enums;

/**
 * Employee Subgroup. Label murni, daftar pendek & stabil, sehingga cukup
 * enum PHP (bukan tabel) — sama pola dengan HomeBase.
 *
 * Penggunaan:
 *   EmployeeSubgroup::options()   // ['PKWT', 'PKWTT', 'CONSULTANCY AGREEMENT']
 */
enum EmployeeSubgroup: string
{
    case PKWT                   = 'PKWT';
    case PKWTT                  = 'PKWTT';
    case CONSULTANCY_AGREEMENT  = 'CONSULTANCY AGREEMENT';

    /**
     * @return string[]
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
