{{-- Surat Referensi Kerja / Employment Reference Letter — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.employment_reference'))

@section('body')
    @php
        $since = !empty($employee['join_date']) ? $date($employee['join_date']) : null;
        $until = !empty($f['end_date']) ? $date($f['end_date']) : null;
    @endphp
    @if($lang === 'en')
        <p>To whom it may concern,</p>
        <p>The undersigned, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} of {{ $company }}, hereby states that:</p>
        @include('hr-general.letters.pdf.employee-details')
        <p>
            has worked at {{ $company }}@if($since) from {{ $since }}@endif {{ $until ? 'until ' . $until : 'up to the present' }}.
        </p>
        @if(!empty($f['remarks']))
            <p>{!! nl2br(e($f['remarks'])) !!}</p>
        @endif
        <p>We recommend {{ $employee['name'] ?? 'the employee' }} to any organisation that may consider them, and wish them every success.</p>
    @else
        <p>Kepada pihak yang berkepentingan,</p>
        <p>Yang bertanda tangan di bawah ini, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} {{ $company }}, dengan ini menerangkan bahwa:</p>
        @include('hr-general.letters.pdf.employee-details')
        <p>
            telah bekerja di {{ $company }}@if($since) sejak {{ $since }}@endif {{ $until ? 'sampai dengan ' . $until : 'sampai dengan saat ini' }}.
        </p>
        @if(!empty($f['remarks']))
            <p>{!! nl2br(e($f['remarks'])) !!}</p>
        @endif
        <p>Kami merekomendasikan yang bersangkutan kepada pihak mana pun yang membutuhkan, dan mendoakan kesuksesannya.</p>
    @endif
@endsection
