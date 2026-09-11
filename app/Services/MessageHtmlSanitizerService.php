<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitizes rich-text HTML coming out of the ticket reply / internal-note
 * Quill editors before it is persisted (message_html), rendered back into
 * the message thread, or relayed to the customer's inbox as email HTML.
 *
 * Nothing upstream of this class was sanitizing user-authored HTML —
 * TicketMessageController stored the raw `message_body` from the browser
 * as-is. This whitelist covers every format the editor toolbar can
 * currently produce (bold/italic/underline/strike, blockquote, ordered/
 * bullet lists, headers, links, pasted/embedded images as data: URIs or
 * /storage relative URLs) plus tables (quill-table-better: table/thead/
 * tbody/tr/td/th/colgroup/col, with colspan/rowspan/style on cells).
 */
class MessageHtmlSanitizerService
{
    private static ?HTMLPurifier $purifier = null;

    public static function sanitize(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        return self::purifier()->purify($html);
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
        // Quill 1.3.7 menaruh warna mention langsung sebagai style di tag inline
        // (mis. <strong style="color: rgb(...)">@Nama</strong>), jadi tag inline
        // formatting HARUS mengizinkan [style] atau warna biru/ungu mention hilang.
        $config->set(
            'HTML.Allowed',
            'p[style],br,div[style],span[style],b[style],strong[style],i[style],em[style],'
            . 'u[style],s[style],strike[style],a[href|title|style],'
            . 'ul,ol,li[style],blockquote[style],h1[style],h2[style],h3[style],'
            . 'img[src|alt|width|height|style],'
            . 'table[style],thead,tbody,tfoot,colgroup,col[style|width],'
            . 'tr,td[colspan|rowspan|style],th[colspan|rowspan|style]'
        );
        $config->set('CSS.AllowedProperties', [
            'color', 'background-color', 'text-align', 'font-weight', 'font-style',
            'text-decoration', 'border', 'border-color', 'border-width', 'border-style',
            'width', 'height', 'padding', 'vertical-align', 'margin-top',
        ]);

        // Editor image paste embeds compressed screenshots as base64 data: URIs;
        // attachments/inline images are served from relative /storage paths.
        $config->set('URI.AllowedSchemes', [
            'http' => true, 'https' => true, 'mailto' => true, 'data' => true,
        ]);

        // Avoid HTMLPurifier auto-filling alt="<base64 fragment>" on images that
        // have no alt text (e.g. pasted screenshots).
        $config->set('Attr.DefaultImageAlt', '');

        self::$purifier = new HTMLPurifier($config);

        return self::$purifier;
    }
}
