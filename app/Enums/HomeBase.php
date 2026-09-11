<?php

namespace App\Enums;

/**
 * Home Base (lokasi kantor) employee. Label murni — tidak membawa data harga,
 * sehingga cukup enum PHP (bukan tabel). Bila kelak tarif bergantung lokasi,
 * naikkan ke tabel referensi seperti Grade.
 *
 * Penggunaan:
 *   HomeBase::options()        // ['Jakarta', 'Yogyakarta', 'Surabaya']
 *   HomeBase::tryFrom('Jakarta')
 */
enum HomeBase: string
{
    case JAKARTA    = 'Jakarta';
    case YOGYAKARTA = 'Yogyakarta';
    case SURABAYA   = 'Surabaya';
    // "Others" = penanda employee External (tanpa home base kantor internal).
    // Lihat App\Models\EmployeeBasicData::deriveEmployeeType().
    case OTHERS     = 'Others';

    /**
     * Daftar nilai home base untuk render dropdown & normalisasi import.
     *
     * @return string[]
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
