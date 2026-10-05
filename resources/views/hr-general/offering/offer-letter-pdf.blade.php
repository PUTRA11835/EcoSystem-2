{{--
    The offering letter as a PDF (DomPDF — CSS 2.1, tables for columns).

    The wording lives in lang/{id,en}/offering_letter.php and is printed in the
    letter's own language ($offer->language); what varies comes from the letter
    ($offer) and Offering Letter → Settings ($settings). Every value put into a
    sentence is escaped first — trans() fills placeholders as they are.

    $letterhead is the letterhead ticked for "Offering Letter" in Letter
    Templates, or null: its image is stretched over the whole A4 page, behind
    the text, on every page. The page margins then keep the text clear of the
    logo at the top and the footer at the bottom of that image — adjust
    $top / $bottom here if a letterhead's header or footer is taller.
--}}
@php
    $company = 'PT Eclectic Consulting';
    $lang = $offer->languageCode();
    $t = fn (string $key, array $values = []) => __("offering_letter.{$key}", array_map(fn ($value) => e($value), $values), $lang);
    $multiline = fn (?string $text) => nl2br(e($text));

    $background = $letterhead?->backgroundDataUri();
    $side = 56;
    $top = $background ? 120 : 56;
    $bottom = $background ? 100 : 56;

    $date = fn ($value) => $value->locale($lang)->translatedFormat('j F Y');
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <title>Offering Letter {{ $offer->letter_number }}</title>
    <style>
        @page { margin: {{ $top }}pt {{ $side }}pt {{ $bottom }}pt {{ $side }}pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #000; line-height: 1.35; }
        p { margin: 0 0 10pt; }
        .background { position: fixed; top: -{{ $top }}pt; left: -{{ $side }}pt; width: 595.28pt; height: 841.89pt; z-index: -1; }
        .background img { display: block; width: 595.28pt; height: 841.89pt; }
        .title { text-align: center; font-weight: bold; margin-bottom: 22pt; }
        .title u { font-size: 11.5pt; }
        table { border-collapse: collapse; }
        .compensation { margin: 0 0 12pt; }
        .compensation td { padding: 1.5pt 0; vertical-align: top; }
        .compensation td.amount { padding-left: 28pt; white-space: nowrap; }
        .signatures { width: 100%; margin-top: 26pt; page-break-inside: avoid; }
        .signatures td { vertical-align: top; }
        .signatures td.approval { width: 36%; }
        .signatures td.gap { height: 70pt; vertical-align: middle; }
        .signatures img.signature { max-height: 64pt; max-width: 160pt; }
    </style>
</head>
<body>
    @if($background)
        <div class="background"><img src="{{ $background }}"></div>
    @endif

    <div class="title">
        <u>{{ $t('title') }}</u><br>
        {{ $offer->letter_number }}
    </div>

    <p>{!! $t('to', ['name' => $offer->candidate_name]) !!}</p>

    <p>{!! $t('greeting', ['name' => $offer->candidate_name]) !!}</p>

    <p>{!! $t('selected', ['company' => $company, 'position' => $offer->position_title]) !!}</p>

    @if($offer->job_description)
        {{-- The description keeps its line breaks, so it is escaped here rather than by $t. --}}
        <p>{!! str_replace(':description', $multiline($offer->job_description), __('offering_letter.duties', [], $lang)) !!}</p>
    @else
        <p>{!! $t('duties_default', ['position' => $offer->position_title]) !!}</p>
    @endif

    @if($offer->joining_date)
        <p>{!! $t('joining', ['date' => $date($offer->joining_date)]) !!}</p>
    @endif

    <p style="margin-bottom: 4pt;"><strong>{{ $t('compensation') }}</strong></p>
    <table class="compensation">
        @foreach($offer->lines() as $line)
            <tr>
                <td>&bull; {{ $offer->lineName($line) }}</td>
                <td class="amount">{{ $offer->money($line['amount']) }}</td>
            </tr>
        @endforeach
    </table>

    <p>{!! $t('total', [
        'amount' => $offer->money($offer->total_compensation),
        'type'   => \App\Models\Recruitment\Offer::SALARY_TYPES[$offer->salary_type] ?? $offer->salary_type,
    ]) !!}</p>

    @if($offer->benefits)
        <p>{!! str_replace(':benefits', $multiline($offer->benefits), __('offering_letter.benefits', [], $lang)) !!}</p>
    @endif

    @if($offer->has_probation)
        <p>{{ $t('probation') }}</p>
    @endif

    @if($offer->notes)
        <p>{!! str_replace(':notes', $multiline($offer->notes), __('offering_letter.notes', [], $lang)) !!}</p>
    @endif

    <p>{!! $t('respond', ['days' => $settings->offer_response_days]) !!}</p>

    <table class="signatures">
        <tr>
            <td>{{ $settings->offer_signing_city }}, {{ $date($offer->offer_date) }}<br>{{ $t('closing') }}</td>
            <td class="approval"><br>{{ $t('approval') }}</td>
        </tr>
        {{-- $signature: the signatory's signature from the employee master data, once HR signed the letter. --}}
        <tr>
            <td class="gap">@if($signature ?? null)<img src="{{ $signature }}" class="signature">@endif</td>
            <td class="gap approval"></td>
        </tr>
        <tr>
            <td><strong><u>{{ $offer->signatory_name }}</u></strong><br>{{ $offer->signatory_title }}</td>
            <td class="approval"><strong>{{ $offer->candidate_name }}</strong></td>
        </tr>
    </table>
</body>
</html>
