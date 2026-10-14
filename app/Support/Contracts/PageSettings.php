<?php

namespace App\Support\Contracts;

/**
 * Ukuran kertas, orientasi, dan margin dokumen kontrak (HC-D72). Satu sumber untuk editor (penggaris & halaman),
 * PDF, dan penyimpanan: template menyimpan pilihannya, kontrak membekukannya saat Active (bersama teksnya).
 *
 * Margin dalam CM. Margin yang KOSONG (null) berarti "bawaan": sisi 56 pt dan atas/bawah 56 pt — atau 120/100 pt bila
 * kop surat dipakai (kop tidak boleh tertimpa teks). Tanpa pengaturan sama sekali hasilnya persis seperti sebelum fitur ini (A4).
 */
class PageSettings
{
    public const CM = 72 / 2.54;

    /** key => [lebar pt, tinggi pt (potret), label] */
    public const SIZES = [
        'A3'     => [841.89, 1190.55, 'A3 (29.7 × 42 cm)'],
        'A4'     => [595.28, 841.89, 'A4 (21 × 29.7 cm)'],
        'A5'     => [419.53, 595.28, 'A5 (14.8 × 21 cm)'],
        'B5'     => [498.90, 708.66, 'B5 (17.6 × 25 cm)'],
        'F4'     => [609.45, 935.43, 'F4 / Folio (21.5 × 33 cm)'],
        'LETTER' => [612.00, 792.00, 'Letter (21.6 × 27.9 cm)'],
        'LEGAL'  => [612.00, 1008.00, 'Legal (21.6 × 35.6 cm)'],
    ];

    public const MARGIN_KEYS = ['top', 'right', 'bottom', 'left'];
    public const MARGIN_MIN_CM = 0.5;
    public const MARGIN_MAX_CM = 8.0;

    /** @return array<string,string> key => label */
    public static function sizeOptions(): array
    {
        return array_map(fn ($s) => $s[2], self::SIZES);
    }

    /** Margin bawaan dalam cm. */
    public static function defaultMarginsCm(bool $letterhead): array
    {
        // Tidak dibulatkan: 56 / 120 / 100 pt harus kembali tepat seperti sebelum fitur ini (editor membulatkan hanya untuk tampilan).
        $side = 56 / self::CM;

        return ['top' => $letterhead ? 120 / self::CM : $side, 'right' => $side, 'bottom' => $letterhead ? 100 / self::CM : $side, 'left' => $side];
    }

    /**
     * @param  array{size?:?string,orientation?:?string,margins?:?array<string,mixed>}|null  $stored
     * @return array{size:string,orientation:string,width:float,height:float,margins_cm:array<string,float>,margins_pt:array<string,float>,custom_margins:bool}
     */
    public static function resolve(?array $stored, bool $letterhead): array
    {
        $size = strtoupper((string) ($stored['size'] ?? 'A4'));
        $size = isset(self::SIZES[$size]) ? $size : 'A4';
        $landscape = strtolower((string) ($stored['orientation'] ?? 'portrait')) === 'landscape';
        [$w, $h] = [self::SIZES[$size][0], self::SIZES[$size][1]];
        if ($landscape) {
            [$w, $h] = [$h, $w];
        }

        $defaults = self::defaultMarginsCm($letterhead);
        $given = (array) ($stored['margins'] ?? []);
        $custom = false;
        $cm = [];
        foreach (self::MARGIN_KEYS as $k) {
            $v = $given[$k] ?? null;
            if ($v !== null && $v !== '' && is_numeric($v)) {
                $cm[$k] = round(max(self::MARGIN_MIN_CM, min(self::MARGIN_MAX_CM, (float) $v)), 2);
                $custom = true;
            } else {
                $cm[$k] = $defaults[$k];
            }
        }
        // The text area must stay at least 5 cm wide / tall.
        $maxSide = ($w / self::CM) - 5;
        if ($cm['left'] + $cm['right'] > $maxSide) {
            $cm['left'] = $cm['right'] = round(max(self::MARGIN_MIN_CM, $maxSide / 2), 2);
        }
        $maxTop = ($h / self::CM) - 5;
        if ($cm['top'] + $cm['bottom'] > $maxTop) {
            $cm['top'] = $cm['bottom'] = round(max(self::MARGIN_MIN_CM, $maxTop / 2), 2);
        }

        return [
            'size' => $size, 'orientation' => $landscape ? 'landscape' : 'portrait', 'width' => $w, 'height' => $h,
            'margins_cm' => $cm, 'margins_pt' => array_map(fn ($v) => round($v * self::CM, 2), $cm), 'custom_margins' => $custom,
        ];
    }

    /** Pengaturan yang disimpan di template / kontrak (margin kosong tetap kosong). */
    public static function store(?string $size, ?string $orientation, array $margins): array
    {
        $out = ['size' => isset(self::SIZES[strtoupper((string) $size)]) ? strtoupper((string) $size) : 'A4', 'orientation' => strtolower((string) $orientation) === 'landscape' ? 'landscape' : 'portrait', 'margins' => []];
        foreach (self::MARGIN_KEYS as $k) {
            $v = $margins[$k] ?? null;
            $out['margins'][$k] = ($v === null || $v === '' || !is_numeric($v)) ? null : round(max(self::MARGIN_MIN_CM, min(self::MARGIN_MAX_CM, (float) $v)), 2);
        }

        return $out;
    }

    /** Argumen setPaper() DomPDF: ukuran sudah searah orientasi, jadi orientasi DomPDF tetap potret. */
    public static function paper(?array $doc): array
    {
        $p = $doc['page'] ?? self::resolve(null, false);

        return [0, 0, $p['width'], $p['height']];
    }
}
