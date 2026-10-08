<?php

namespace App\Support\Contracts;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Menyaring HTML teks kontrak dari editor (Contract Templates). Daftar putihnya lebih sempit daripada sanitizer
 * pesan tiket: tanpa gambar dan tautan — kop surat dipasang oleh sistem dari Letter Templates, bukan dari teks.
 * Teks {{placeholder}} tidak tersentuh (hanya teks biasa bagi HTML).
 */
class ContractHtmlSanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        return $html === '' ? '' : trim(self::purifier()->purify($html));
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();

        $cacheDir = storage_path('app/htmlpurifier');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $config->set('Cache.SerializerPath', $cacheDir);
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed',
            'p[style],br,div[style],span[style],b,strong,i,em,u,s,strike,sub,sup,'
            . 'ul[style],ol[style],li[style],blockquote[style],h1[style],h2[style],h3[style],'
            . 'table[style],thead,tbody,tr,td[colspan|rowspan|style],th[colspan|rowspan|style]'
        );
        $config->set('CSS.AllowedProperties', [
            'text-align', 'font-weight', 'font-style', 'text-decoration', 'font-size', 'color',
            'margin-left', 'padding-left', 'text-indent', 'list-style-type', 'vertical-align', 'width', 'height',
            'border', 'border-width', 'border-style', 'border-color',
        ]);
        $config->set('AutoFormat.RemoveEmpty', false);

        return self::$purifier = new HTMLPurifier($config);
    }
}
