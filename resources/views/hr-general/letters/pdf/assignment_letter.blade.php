{{-- Surat Tugas / Assignment Letter — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.assignment_letter'))

@section('body')
    @if($lang === 'en')
        <p>The undersigned, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} of {{ $company }}, hereby assigns:</p>
        @include('hr-general.letters.pdf.employee-details')
        <p>to carry out the following assignment:</p>
        <table class="details">
            <tr><td class="label">Assignment</td><td class="colon">:</td><td>{!! nl2br(e($f['task'] ?? '')) !!}</td></tr>
            <tr><td class="label">Location</td><td class="colon">:</td><td>{{ $f['location'] ?? '' }}</td></tr>
            <tr><td class="label">Period</td><td class="colon">:</td><td>{{ $date($f['period_from'] ?? null) }} – {{ $date($f['period_to'] ?? null) }}</td></tr>
        </table>
        <p>Please carry out this assignment responsibly and report on it upon completion.</p>
    @else
        <p>Yang bertanda tangan di bawah ini, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} {{ $company }}, dengan ini menugaskan:</p>
        @include('hr-general.letters.pdf.employee-details')
        <p>untuk melaksanakan tugas sebagai berikut:</p>
        <table class="details">
            <tr><td class="label">Tugas</td><td class="colon">:</td><td>{!! nl2br(e($f['task'] ?? '')) !!}</td></tr>
            <tr><td class="label">Lokasi</td><td class="colon">:</td><td>{{ $f['location'] ?? '' }}</td></tr>
            <tr><td class="label">Periode</td><td class="colon">:</td><td>{{ $date($f['period_from'] ?? null) }} s.d. {{ $date($f['period_to'] ?? null) }}</td></tr>
        </table>
        <p>Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab dan dilaporkan setelah selesai.</p>
    @endif
@endsection
