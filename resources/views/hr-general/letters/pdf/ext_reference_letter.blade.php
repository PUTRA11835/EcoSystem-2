{{-- Surat Referensi Konsultan Eksternal / External Reference Letter — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', $lang === 'en' ? 'REFERENCE LETTER' : 'SURAT REFERENSI')

@section('body')
    @php
        $from = !empty($f['period_from']) ? $date($f['period_from']) : null;
        $to = !empty($f['period_to']) ? $date($f['period_to']) : null;
        $place = $f['location'] ?? null;
    @endphp
    @include('hr-general.letters.pdf.ext-recipient')
    @if($lang === 'en')
        <p>To whom it may concern,</p>
        <p>The undersigned, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} of {{ $company }}, hereby states that:</p>
        @include('hr-general.letters.pdf.ext-details')
        <p>
            has worked with us as an external consultant{{ $place ? ' at ' . $place : '' }}
            @if($from && $to) from {{ $from }} until {{ $to }}@elseif($from) since {{ $from }}@endif.
        </p>
        @if(!empty($f['remarks']))
            <p>{!! nl2br(e($f['remarks'])) !!}</p>
        @endif
        <p>We recommend {{ $employee['name'] ?? 'the consultant' }} to any organisation that may consider them, and wish them every success.</p>
    @else
        <p>Kepada pihak yang berkepentingan,</p>
        <p>Yang bertanda tangan di bawah ini, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} {{ $company }}, dengan ini menerangkan bahwa:</p>
        @include('hr-general.letters.pdf.ext-details')
        <p>
            telah bekerja sama dengan kami sebagai konsultan eksternal{{ $place ? ' di ' . $place : '' }}
            @if($from && $to) sejak {{ $from }} sampai dengan {{ $to }}@elseif($from) sejak {{ $from }}@endif.
        </p>
        @if(!empty($f['remarks']))
            <p>{!! nl2br(e($f['remarks'])) !!}</p>
        @endif
        <p>Kami merekomendasikan yang bersangkutan kepada pihak mana pun yang membutuhkan, dan mendoakan kesuksesannya.</p>
    @endif
@endsection
