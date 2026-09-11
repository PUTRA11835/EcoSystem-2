<?php

namespace App\Enums;

/**
 * Personnel Subarea. Label murni, daftar pendek & stabil, sehingga cukup
 * enum PHP (bukan tabel) — sama pola dengan EmployeeSubgroup/HomeBase.
 *
 * Penggunaan:
 *   PersonnelSubarea::options()   // ['Support', 'Project', 'Administrasi', 'Other']
 */
enum PersonnelSubarea: string
{
    case SUPPORT       = 'Support';
    case PROJECT       = 'Project';
    case ADMINISTRASI  = 'Administrasi';
    case OTHER         = 'Other';

    /**
     * @return string[]
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
