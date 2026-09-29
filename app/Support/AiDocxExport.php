<?php

namespace App\Support;

use ZipArchive;

/**
 * Teks/Markdown jawaban AI → berkas .docx yang bisa dibuka Word.
 *
 * Kebalikan dari apa yang dilakukan AiTextAttachment::extractDocxText():
 * di sana .docx diubah jadi teks untuk dikirim ke model, di sini teks
 * jawaban model diubah balik jadi .docx supaya user punya file yang bisa
 * diunduh. Assistant AI Research sendiri tidak punya alat untuk membuat
 * atau melampirkan file — ini murni konversi sisi server saat tombol
 * Download ditekan.
 *
 * Docx yang dihasilkan sengaja MINIMAL — hanya tiga bagian yang benar-benar
 * wajib menurut Open XML ([Content_Types].xml, _rels/.rels, word/document.xml)
 * — karena satu-satunya tujuan file ini adalah dibaca ulang, diedit, atau
 * ditempel ke dokumen lain, bukan menjadi dokumen kop-resmi.
 */
class AiDocxExport
{
    /** Heading level (jumlah '#') → ukuran font, dalam half-point. */
    private const HEADING_SIZES = [1 => 40, 2 => 36, 3 => 32, 4 => 28, 5 => 26, 6 => 24];

    public static function build(string $markdown): string
    {
        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . self::renderBody($markdown)
            . '<w:sectPr>'
            . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417"/>'
            . '</w:sectPr>'
            . '</w:body></w:document>';

        return self::zip($documentXml);
    }

    private static function renderBody(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $out = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ('' === $trimmed) {
                $out .= '<w:p/>';
            } elseif (preg_match('/^(#{1,6})\s+(.*)$/', $trimmed, $m)) {
                $out .= self::paragraph($m[2], bold: true, size: self::HEADING_SIZES[strlen($m[1])]);
            } elseif (preg_match('/^[-*•]\s+(.*)$/', $trimmed, $m)) {
                $out .= self::paragraph('•  ' . $m[1], indent: true);
            } elseif (preg_match('/^\d+[.)]\s+.*$/', $trimmed)) {
                $out .= self::paragraph($trimmed, indent: true);
            } else {
                $out .= self::paragraph($trimmed);
            }
        }

        return $out;
    }

    private static function paragraph(string $text, bool $bold = false, ?int $size = null, bool $indent = false): string
    {
        $pPr = $indent ? '<w:pPr><w:ind w:left="360"/></w:pPr>' : '';

        return '<w:p>' . $pPr . self::renderRuns($text, $bold, $size) . '</w:p>';
    }

    /** Cuma **bold** yang diurai — cukup untuk jawaban markdown AI, tanpa kompleksitas inline lain. */
    private static function renderRuns(string $text, bool $forceBold, ?int $size): string
    {
        $parts = preg_split('/(\*\*[^*]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$text];
        $out = '';

        foreach ($parts as $part) {
            $bold = $forceBold;
            $value = $part;

            if (preg_match('/^\*\*(.+)\*\*$/', $part, $m)) {
                $bold = true;
                $value = $m[1];
            }

            $rPr = '';
            if ($bold || $size) {
                $rPr = '<w:rPr>' . ($bold ? '<w:b/>' : '') . ($size ? '<w:sz w:val="' . $size . '"/>' : '') . '</w:rPr>';
            }

            $out .= '<w:r>' . $rPr . '<w:t xml:space="preserve">' . self::escape($value) . '</w:t></w:r>';
        }

        return $out;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function zip(string $documentXml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'aidocx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', self::contentTypesXml());
        $zip->addFromString('_rels/.rels', self::relsXml());
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }

    private static function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';
    }

    private static function relsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';
    }
}
