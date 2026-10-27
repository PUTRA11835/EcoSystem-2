{{--
    A letter whose wording was written in Letter Templates → Settings → Template Designs (the Word-style editor), as a PDF
    (DomPDF — CSS 2.1, tables for columns): the letterhead behind every page, then the text with its fields already filled.
    Given by Letter::designedPdf(): $title, $lang, $bodyHtml (placeholders filled), $letterhead, $page (PageSettings::resolve).
--}}
@php
    // The editor offers Arial / Courier New / Times New Roman; the PDF engine knows them by their core names.
    $body = preg_replace_callback('/font-family\s*:\s*([^;"]+)/i', function ($m) {
        $v = strtolower($m[1]);
        $face = str_contains($v, 'arial') ? 'Helvetica' : (str_contains($v, 'courier') ? 'Courier' : (str_contains($v, 'times') ? 'Times' : trim($m[1])));

        return 'font-family:' . $face;
    }, $bodyHtml);
    $background = $letterhead?->backgroundDataUri();
    $top = $page['margins_pt']['top'];
    $bottom = $page['margins_pt']['bottom'];
    $left = $page['margins_pt']['left'];
    $right = $page['margins_pt']['right'];
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: {{ $top }}pt {{ $right }}pt {{ $bottom }}pt {{ $left }}pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #000; line-height: 1.4; }
        p { margin: 0 0 10pt; }
        ol, ul { margin: 0 0 10pt 0; padding-left: 22pt; }
        h1 { font-size: 14pt; font-weight: bold; margin: 0 0 8pt; }
        h2 { font-size: 12.5pt; font-weight: bold; margin: 0 0 8pt; }
        h3 { font-size: 11.5pt; font-weight: bold; margin: 0 0 8pt; }
        blockquote { margin: 0 0 8pt 18pt; padding-left: 10pt; border-left: 2px solid #bbb; }
        table { border-collapse: collapse; margin: 0 0 10pt; }
        td { vertical-align: top; padding: 1.5pt 2pt; }
        .background { position: fixed; top: -{{ $top }}pt; left: -{{ $left }}pt; width: {{ $page['width'] }}pt; height: {{ $page['height'] }}pt; z-index: -1; }
        .background img { display: block; width: {{ $page['width'] }}pt; height: {{ $page['height'] }}pt; }
    </style>
</head>
<body>
    @if($background)
        <div class="background"><img src="{{ $background }}"></div>
    @endif
    {!! $body !!}
</body>
</html>
