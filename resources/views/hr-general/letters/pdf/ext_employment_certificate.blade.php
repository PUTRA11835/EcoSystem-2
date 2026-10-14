{{-- Surat Keterangan Kerja Konsultan Eksternal / External Employment Certificate — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', $lang === 'en' ? 'EMPLOYMENT CERTIFICATE' : 'SURAT KETERANGAN KERJA')

@section('body')
    @php
        $from = !empty($f['period_from']) ? $date($f['period_from']) : null;
        $to = !empty($f['period_to']) ? $date($f['period_to']) : null;
        $place = $f['location'] ?? null;
    @endphp
    @include('hr-general.letters.pdf.ext-recipient')
    @if($lang === 'en')
        <p>The undersigned, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} of {{ $company }}, hereby states that:</p>
        @include('hr-general.letters.pdf.ext-details')
        <p>
            is an external consultant engaged through {{ $company }}@if($place) at {{ $place }}@endif
            @if($from && $to) for the period {{ $from }} – {{ $to }}@elseif($from) since {{ $from }}@endif.
        </p>
        <p>This certificate is issued for: {{ $f['purpose'] ?? '-' }}.</p>
    @else
        <p>Yang bertanda tangan di bawah ini, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} {{ $company }}, dengan ini menerangkan bahwa:</p>
        @include('hr-general.letters.pdf.ext-details')
        <p>
            adalah konsultan eksternal yang terlibat melalui {{ $company }}@if($place) di {{ $place }}@endif
            @if($from && $to) untuk periode {{ $from }} s.d. {{ $to }}@elseif($from) sejak {{ $from }}@endif.
        </p>
        <p>Surat keterangan ini dibuat untuk keperluan: {{ $f['purpose'] ?? '-' }}.</p>
    @endif
@endsection
