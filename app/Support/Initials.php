<?php

namespace App\Support;

/**
 * Inisial avatar — SATU aturan untuk seluruh aplikasi (top bar, My Profile, Master Employee, Dashboard).
 *
 * Sebelumnya tiap tempat memakai caranya sendiri sehingga orang yang sama tampil "SY" (dua huruf pertama
 * nama), "SA" (huruf pertama nama depan + belakang), dan "A" (huruf pertama nickname).
 *
 * Aturan (praktik umum Google/Microsoft/Slack):
 *   - Dari NAMA LENGKAP, bukan nickname (nickname hanya untuk sapaan).
 *   - Dua kata atau lebih  -> huruf pertama kata PERTAMA + huruf pertama kata TERAKHIR ("System Administrator" -> "SA").
 *   - Satu kata            -> dua huruf pertama kata itu ("Admin" -> "AD").
 *   - Kata tanpa huruf/angka (mis. nama belakang "-") diabaikan.
 *   - Selalu huruf kapital, tak pernah kosong (cadangan "?").
 */
class Initials
{
    public static function make(?string $fullName, string $fallback = '?'): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim((string) $fullName)) ?: [],
            fn ($w) => $w !== '' && preg_match('/[\p{L}\p{N}]/u', $w)
        ));

        if (!$words) {
            return $fallback;
        }

        $letter = fn (string $w) => mb_strtoupper(mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $w), 0, 1));

        if (count($words) === 1) {
            $w = preg_replace('/[^\p{L}\p{N}]/u', '', $words[0]);

            return mb_strtoupper(mb_substr($w, 0, 2)) ?: $fallback;
        }

        return $letter($words[0]) . $letter($words[count($words) - 1]);
    }
}
