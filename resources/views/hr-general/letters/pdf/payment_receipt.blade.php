{{-- Kwitansi / Payment Receipt — Bahasa Indonesia and English side by side. --}}
@extends('hr-general.letters.pdf.layout')

@section('title', __('letters.titles.payment_receipt'))

@section('closing', $lang === 'en' ? 'Received by,' : 'Yang menerima,')

@section('body')
    @php
        $amount = (float) ($f['amount'] ?? 0);
        $words = ($f['amount_words'] ?? null) ?: \App\Support\Letters\LetterTemplates::amountInWords($amount, $lang);
    @endphp
    <table class="details" style="margin-left: 0; width: 100%;">
        <tr>
            <td class="label">{{ $lang === 'en' ? 'Received from' : 'Telah terima dari' }}</td>
            <td class="colon">:</td>
            <td><strong>{{ $f['received_from'] ?? '' }}</strong></td>
        </tr>
        <tr>
            <td class="label">{{ $lang === 'en' ? 'The sum of' : 'Uang sejumlah' }}</td>
            <td class="colon">:</td>
            <td><em>{{ ucfirst($words) }}</em></td>
        </tr>
        <tr>
            <td class="label">{{ $lang === 'en' ? 'For payment of' : 'Untuk pembayaran' }}</td>
            <td class="colon">:</td>
            <td>{!! nl2br(e($f['purpose'] ?? '')) !!}</td>
        </tr>
        @if($employee)
            <tr>
                <td class="label">{{ $lang === 'en' ? 'Employee concerned' : 'Karyawan terkait' }}</td>
                <td class="colon">:</td>
                <td>{{ $employee['name'] }}</td>
            </tr>
        @endif
    </table>

    <table style="margin: 6pt 0 0;">
        <tr>
            <td style="border: 1pt solid #000; padding: 6pt 14pt; font-size: 13pt; font-weight: bold;">{{ $money($amount) }}</td>
        </tr>
    </table>
@endsection
