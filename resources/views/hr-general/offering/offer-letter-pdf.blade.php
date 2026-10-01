{{--
    The offering letter as a PDF (DomPDF — CSS 2.1, tables for columns).

    The wording is fixed here; what varies comes from the letter ($offer) and
    Offering Letter → Settings ($settings). $letterhead is the letterhead
    ticked for "Offering Letter" in Letter Templates, or null: its header and
    footer images are printed edge to edge on every page, and the page margins
    grow to make room for them.
--}}
@php
    $company = 'PT Eclectic Consulting';
    $side = 56;
    $headerHeight = $letterhead?->heightPt('header') ?? 0;
    $footerHeight = $letterhead?->heightPt('footer') ?? 0;
    $top = max($headerHeight + 18, $side);
    $bottom = max($footerHeight + 18, $side);

    $rupiah = fn ($amount) => 'Rp ' . number_format((float) $amount, 0, ',', '.') . ',-';
    $date = fn ($value) => $value->locale('id')->translatedFormat('j F Y');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Offering Letter {{ $offer->letter_number }}</title>
    <style>
        @page { margin: {{ $top }}pt {{ $side }}pt {{ $bottom }}pt {{ $side }}pt; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10.5pt; color: #000; line-height: 1.35; }
        p { margin: 0 0 10pt; }
        .letterhead { position: fixed; left: -{{ $side }}pt; width: 595.28pt; }
        .letterhead img { display: block; width: 595.28pt; }
        .title { text-align: center; font-weight: bold; margin-bottom: 22pt; }
        .title u { font-size: 11.5pt; }
        table { border-collapse: collapse; }
        .compensation { margin: 0 0 12pt; }
        .compensation td { padding: 1.5pt 0; vertical-align: top; }
        .compensation td.amount { padding-left: 28pt; white-space: nowrap; }
        .signatures { width: 100%; margin-top: 26pt; page-break-inside: avoid; }
        .signatures td { width: 50%; vertical-align: top; }
        .signatures td.gap { height: 70pt; }
    </style>
</head>
<body>
    @if($headerHeight)
        <div class="letterhead" style="top: -{{ $top }}pt;"><img src="{{ $letterhead->dataUri('header') }}" style="height: {{ $headerHeight }}pt;"></div>
    @endif
    @if($footerHeight)
        <div class="letterhead" style="bottom: -{{ $bottom }}pt; height: {{ $footerHeight }}pt;"><img src="{{ $letterhead->dataUri('footer') }}" style="height: {{ $footerHeight }}pt;"></div>
    @endif

    <div class="title">
        <u>OFFERING LETTER</u><br>
        {{ $offer->letter_number }}
    </div>

    <p>
        Kepada Yth,<br>
        Bapak/Ibu/Saudara/i <strong>{{ $offer->candidate_name }}</strong><br>
        Di Tempat
    </p>

    <p>Dengan Hormat,</p>

    <p>
        Kami mewakili <strong>{{ $company }}</strong> menyampaikan bahwa Anda terpilih untuk mengisi posisi
        <strong>{{ $offer->position_title }}</strong> di perusahaan kami.
    </p>

    @if($offer->job_description)
        <p>Anda akan bertugas untuk {!! nl2br(e($offer->job_description)) !!}</p>
    @else
        <p>Anda akan bertugas sesuai dengan posisi <strong>{{ $offer->position_title }}</strong>.</p>
    @endif

    @if($offer->joining_date)
        <p>Kami berharap Anda dapat bergabung pada tanggal <strong>{{ $date($offer->joining_date) }}</strong>.</p>
    @endif

    <p style="margin-bottom: 4pt;"><strong>Kompensasi:</strong></p>
    <table class="compensation">
        @foreach($offer->lines() as $line)
            <tr>
                <td>&bull; {{ $line['name'] }}</td>
                <td class="amount">{{ $rupiah($line['amount']) }}</td>
            </tr>
        @endforeach
    </table>

    <p>
        Total kompensasi setiap bulan: <strong>{{ $rupiah($offer->total_compensation) }}</strong>
        ({{ \App\Models\Recruitment\Offer::SALARY_TYPES[$offer->salary_type] ?? $offer->salary_type }})
    </p>

    @if($offer->benefits)
        <p>Benefit: {!! nl2br(e($offer->benefits)) !!}</p>
    @endif

    @if($offer->has_probation)
        <p>Masa percobaan berlangsung selama 3 (tiga) bulan sejak tanggal bergabung.</p>
    @endif

    @if($offer->notes)
        <p>Catatan: {!! nl2br(e($offer->notes)) !!}</p>
    @endif

    <p>
        Apabila Anda menerima tawaran ini, mohon menandatangani surat ini dan mengembalikannya maksimal
        {{ $settings->offer_response_days }} hari sejak tanggal dibuat.
    </p>

    <table class="signatures">
        <tr>
            <td>{{ $settings->offer_signing_city }}, {{ $date($offer->offer_date) }}<br>Hormat kami,</td>
            <td><br>Menyetujui,</td>
        </tr>
        <tr><td class="gap" colspan="2"></td></tr>
        <tr>
            <td><strong><u>{{ $offer->signatory_name }}</u></strong><br>{{ $offer->signatory_title }}</td>
            <td><strong>{{ $offer->candidate_name }}</strong></td>
        </tr>
    </table>
</body>
</html>
