{{--
    The frame every letter of the Letter Templates hub is printed in (DomPDF —
    CSS 2.1, tables for columns): the letterhead behind the page, the title
    with the number, the letter's own text (@section('body')), and the
    signature block — signed with the signatory's master-data signature once
    HR signed the letter.

    Given by App\Models\Letters\Letter::toPdf(), rendered in the letter's
    language: $letter, $f (its saved fields), $employee (snapshot or null),
    $lang, $company, $city, $number, $letterhead, $signature, $date(), $money().
    Words every letter shares: __('letters.*').
--}}
@php
    $background = $letterhead?->backgroundDataUri();
    $side = 56;
    $top = $background ? 120 : 56;
    $bottom = $background ? 100 : 56;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $letter->subject }} {{ $number }}</title>
    <style>
        @page { margin: {{ $top }}pt {{ $side }}pt {{ $bottom }}pt {{ $side }}pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #000; line-height: 1.4; }
        p { margin: 0 0 10pt; text-align: justify; }
        .background { position: fixed; top: -{{ $top }}pt; left: -{{ $side }}pt; width: 595.28pt; height: 841.89pt; z-index: -1; }
        .background img { display: block; width: 595.28pt; height: 841.89pt; }
        .title { text-align: center; font-weight: bold; margin-bottom: 20pt; }
        .title u { font-size: 12pt; }
        table { border-collapse: collapse; }
        .details { margin: 0 0 12pt 20pt; }
        .details td { padding: 1.5pt 0; vertical-align: top; }
        .details td.label { width: 120pt; }
        .details td.colon { width: 12pt; }
        .grid { width: 100%; margin: 0 0 12pt; }
        .grid th, .grid td { border: 0.75pt solid #444; padding: 4pt 6pt; vertical-align: top; text-align: left; }
        .grid th { background: #eee; }
        .signatures { width: 100%; margin-top: 24pt; page-break-inside: avoid; }
        .signatures td { vertical-align: top; }
        .signatures td.gap { height: 70pt; vertical-align: middle; }
        .signatures img.signature { max-height: 64pt; max-width: 160pt; }
        .meta td { padding: 1pt 0; vertical-align: top; }
    </style>
</head>
<body>
    @if($background)
        <div class="background"><img src="{{ $background }}"></div>
    @endif

    <div class="title">
        <u>@yield('title')</u><br>
        {{ __('letters.number') }}: {{ $number }}
    </div>

    @yield('body')

    <table class="signatures">
        <tr><td>{{ $city }}, {{ $date($letter->letter_date) }}<br>@yield('closing', __('letters.closing'))</td></tr>
        <tr>
            <td class="gap">@if($signature)<img src="{{ $signature }}" class="signature">@endif</td>
        </tr>
        <tr>
            <td><strong><u>{{ $letter->signatory_name }}</u></strong><br>{{ $letter->signatory_title }}</td>
        </tr>
    </table>
</body>
</html>
