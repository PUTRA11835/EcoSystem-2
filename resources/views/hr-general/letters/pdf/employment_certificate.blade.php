{{-- Surat Keterangan Kerja / Employment Certificate — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.employment_certificate'))

@section('body')
    @if($lang === 'en')
        <p>The undersigned, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} of {{ $company }}, hereby certifies that:</p>
        @include('hr-general.letters.pdf.employee-details')
        <p>
            is an employee of {{ $company }}{{ !empty($employee['join_date']) ? ', employed since ' . $date($employee['join_date']) . ' up to the date of this letter' : '' }}.
        </p>
        <p>This certificate is issued for the purpose of {{ $f['purpose'] ?? '' }}.</p>
        <p>This certificate is issued truthfully, to be used as appropriate.</p>
    @else
        <p>Yang bertanda tangan di bawah ini, <strong>{{ $letter->signatory_name }}</strong>, {{ $letter->signatory_title }} {{ $company }}, dengan ini menerangkan bahwa:</p>
        @include('hr-general.letters.pdf.employee-details')
        <p>
            adalah benar karyawan {{ $company }}{{ !empty($employee['join_date']) ? ' yang bekerja sejak ' . $date($employee['join_date']) . ' sampai dengan tanggal surat ini dibuat' : '' }}.
        </p>
        <p>Surat keterangan ini dibuat untuk keperluan {{ $f['purpose'] ?? '' }}.</p>
        <p>Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya.</p>
    @endif
@endsection
