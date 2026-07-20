<?php

namespace App\Enums;

/**
 * Division employee. Label murni, daftar pendek & stabil, sehingga cukup
 * enum PHP (bukan tabel) — sama pola dengan HomeBase.
 *
 * Penggunaan:
 *   Division::options()   // ['BUSINESS SUPPORT', 'SALES & BUSINESS DEVELOPMENT', 'OPERATIONS']
 */
enum Division: string
{
    case BUSINESS_SUPPORT             = 'BUSINESS SUPPORT';
    case SALES_BUSINESS_DEVELOPMENT   = 'SALES & BUSINESS DEVELOPMENT';
    case OPERATIONS                   = 'OPERATIONS';

    /**
     * @return string[]
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
