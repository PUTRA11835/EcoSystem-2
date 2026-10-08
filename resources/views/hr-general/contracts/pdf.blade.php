{{--
    The contract as a PDF (DomPDF — CSS 2.1, tables for columns): the letterhead of the letter type "Employment Contract"
    behind every page, then the contract text. Given by ContractController::pdf(): $doc (ContractService::document()).
--}}
@php
    // An article heading ("PASAL 3" + its title) must not be left alone at the bottom of a page: keep it with what follows.
    $body = preg_replace('#<p style="text-align:center">(<strong>PASAL \d+</strong>)</p>\s*<p style="text-align:center">#', '<p style="text-align:center;page-break-after:avoid">$1</p><p style="text-align:center;page-break-after:avoid">', $doc['html']);
    $background = $doc['letterhead']?->backgroundDataUri();
    $side = 56;
    $top = $background ? 120 : 56;
    $bottom = $background ? 100 : 56;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $doc['title'] }} {{ $doc['number'] }}</title>
    <style>
        @page { margin: {{ $top }}pt {{ $side }}pt {{ $bottom }}pt {{ $side }}pt; }
        body { font-family: "Times New Roman", Times, serif; font-size: 11pt; color: #000; line-height: 1.4; }
        p { margin: 0 0 8pt; }
        ol, ul { margin: 0 0 8pt 0; padding-left: 22pt; }
        table { border-collapse: collapse; margin: 0 0 8pt; }
        td { vertical-align: top; padding: 1pt 2pt; }
        .background { position: fixed; top: -{{ $top }}pt; left: -{{ $side }}pt; width: 595.28pt; height: 841.89pt; z-index: -1; }
        .background img { display: block; width: 595.28pt; height: 841.89pt; }
    </style>
</head>
<body>
    @if($background)
        <div class="background"><img src="{{ $background }}"></div>
    @endif
    {!! $body !!}
</body>
</html>
