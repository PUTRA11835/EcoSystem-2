{{--
    The contract as a PDF (DomPDF — CSS 2.1, tables for columns): the letterhead of the letter type "Employment Contract"
    behind every page, then the contract text. Given by ContractController::pdf(): $doc (ContractService::document()).
--}}
@php
    // An article heading ("PASAL 3" + its title) must not be left alone at the bottom of a page: keep it with what follows.
    $body = preg_replace('#<p style="text-align:center">(<strong>PASAL \d+</strong>)</p>\s*<p style="text-align:center">#', '<p style="text-align:center;page-break-after:avoid">$1</p><p style="text-align:center;page-break-after:avoid">', $doc['html']);
    // The editor offers Arial / Courier New / Times New Roman; the PDF engine knows them by their core names.
    $body = preg_replace_callback('/font-family\s*:\s*([^;"]+)/i', function ($m) {
        $v = strtolower($m[1]);
        $face = str_contains($v, 'arial') ? 'Helvetica' : (str_contains($v, 'courier') ? 'Courier' : (str_contains($v, 'times') ? 'Times' : trim($m[1])));

        return 'font-family:' . $face;
    }, $body);
    $background = $doc['letterhead']?->backgroundDataUri();
    $page = $doc['page'] ?? \App\Support\Contracts\PageSettings::resolve(null, (bool) $background);
    $top = $page['margins_pt']['top'];
    $bottom = $page['margins_pt']['bottom'];
    $left = $page['margins_pt']['left'];
    $right = $page['margins_pt']['right'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc['title'] }} {{ $doc['number'] }}</title>
    <style>
        @page { margin: {{ $top }}pt {{ $right }}pt {{ $bottom }}pt {{ $left }}pt; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11pt; color: #000; line-height: 1.4; }
        p { margin: 0 0 8pt; }
        ol, ul { margin: 0 0 8pt 0; padding-left: 22pt; }
        h1 { font-size: 14pt; font-weight: bold; margin: 0 0 8pt; }
        h2 { font-size: 12.5pt; font-weight: bold; margin: 0 0 8pt; }
        h3 { font-size: 11.5pt; font-weight: bold; margin: 0 0 8pt; }
        blockquote { margin: 0 0 8pt 18pt; padding-left: 10pt; border-left: 2px solid #bbb; }
        table { border-collapse: collapse; margin: 0 0 8pt; }
        td { vertical-align: top; padding: 1pt 2pt; }
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
