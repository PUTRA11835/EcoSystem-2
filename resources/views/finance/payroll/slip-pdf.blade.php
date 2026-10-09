@php
    // Slip gaji format ESH, dua bahasa: $lang = 'id' | 'en'.
    $en = $lang === 'en';
    $t = fn (string $id, string $eng) => $en ? $eng : $id;
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $earn = $items->where('type', 'earning')->values();
    $ded  = $items->where('type', 'deduction')->values();
    $rows = max(10, $earn->count(), $ded->count());
    $periodEnd = \Carbon\Carbon::parse($p->period_end);
    $monthName = $periodEnd->locale($en ? 'en' : 'id')->translatedFormat('F Y');
    $join = $emp && $emp->since_date ? \Carbon\Carbon::parse($emp->since_date)->locale($en ? 'en' : 'id')->translatedFormat('d F Y') : '-';
    $words = \App\Support\Letters\LetterTemplates::amountInWords((float) $s->take_home_pay, $lang);
    $logo = public_path('images/eclectic_logo_nobg.png');
    $slipNo = $s->slip_no ? str_pad((string) $s->slip_no, 5, '0', STR_PAD_LEFT) : '-';
    $printed = now()->locale($en ? 'en' : 'id')->translatedFormat('d F Y');
    $city = $company->signing_city ?: '';
@endphp
<!doctype html>
<html lang="{{ $lang }}">
<head>
<meta charset="utf-8">
<title>{{ $t('Slip Gaji', 'Payslip') }} — {{ $s->employee_name }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
    table { width: 100%; border-collapse: collapse; }
    .head td { vertical-align: middle; }
    .company { font-size: 14px; font-weight: bold; }
    .title { text-align: center; font-size: 13px; font-weight: bold; margin: 12px 0 8px; letter-spacing: .5px; }
    .info td { padding: 2px 4px; vertical-align: top; }
    .grid td, .grid th { border: 1px solid #111827; padding: 4px 6px; }
    .grid th { background: #e5e7eb; text-align: left; font-size: 9.5px; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .muted { color: #6b7280; }
    .thp td { font-size: 12px; font-weight: bold; background: #f3f4f6; }
    .sign td { text-align: center; padding-top: 4px; }
    .line { border-bottom: 1px solid #111827; width: 60%; margin: 46px auto 3px; }
</style>
</head>
<body>
    <table class="head">
        <tr>
            <td width="90">@if(is_file($logo))<img src="{{ $logo }}" style="height:46px">@endif</td>
            <td><div class="company">{{ $company->company_name ?: 'PT Eclectic Consulting' }}</div></td>
            <td class="r muted">{{ $t('No. Slip', 'Slip No.') }}: <span class="b" style="color:#111827">{{ $slipNo }}</span></td>
        </tr>
    </table>

    <div class="title">{{ $t('SLIP GAJI PEGAWAI BULAN', 'EMPLOYEE PAYSLIP FOR') }} {{ strtoupper($monthName) }}</div>

    <table class="info">
        <tr><td width="18%">{{ $t('NIK', 'Employee ID') }}</td><td width="32%">: {{ $s->employee_eci ?: '-' }}</td><td width="18%">{{ $t('Tanggal masuk', 'Join date') }}</td><td>: {{ $join }}</td></tr>
        <tr><td>{{ $t('Karyawan', 'Employee') }}</td><td>: <span class="b">{{ $s->employee_name }}</span></td><td>{{ $t('Kategori', 'Category') }}</td><td>: {{ $emp->employee_type ?? '-' }}</td></tr>
        <tr><td>{{ $t('Jabatan', 'Position') }}</td><td>: {{ $s->position ?: '-' }}</td><td>{{ $t('Departemen', 'Department') }}</td><td>: {{ $s->department ?: '-' }}</td></tr>
        <tr><td>PTKP</td><td>: {{ $s->ptkp_code ?: '-' }}</td><td>{{ $t('Periode', 'Period') }}</td><td>: {{ \Carbon\Carbon::parse($p->period_start)->format('d/m/Y') }} – {{ $periodEnd->format('d/m/Y') }}</td></tr>
    </table>

    <table class="grid" style="margin-top:10px">
        <tr><th width="30%">{{ $t('PENDAPATAN', 'EARNINGS') }}</th><th width="20%" class="r">{{ $t('JUMLAH (Rp)', 'AMOUNT (IDR)') }}</th><th width="30%">{{ $t('PENGURANGAN', 'DEDUCTIONS') }}</th><th width="20%" class="r">{{ $t('JUMLAH (Rp)', 'AMOUNT (IDR)') }}</th></tr>
        @for($i = 0; $i < $rows; $i++)
            <tr>
                <td>{{ $earn[$i]->name ?? '' }}&nbsp;</td><td class="r">{{ isset($earn[$i]) ? $m($earn[$i]->amount) : '' }}</td>
                <td>{{ $ded[$i]->name ?? '' }}&nbsp;</td><td class="r">{{ isset($ded[$i]) ? $m($ded[$i]->amount) : '' }}</td>
            </tr>
        @endfor
        <tr class="b"><td>{{ $t('Total Pendapatan', 'Total Earnings') }}</td><td class="r">{{ $m($s->gross_earnings) }}</td><td>{{ $t('Total Pengurangan', 'Total Deductions') }}</td><td class="r">{{ $m($s->total_deductions) }}</td></tr>
        <tr class="thp"><td colspan="3">{{ $t('GAJI BERSIH DITERIMA (TAKE HOME PAY)', 'NET PAY (TAKE HOME PAY)') }}</td><td class="r">Rp {{ $m($s->take_home_pay) }}</td></tr>
    </table>

    <p style="margin:8px 0 2px"><span class="b">{{ $t('TERBILANG', 'IN WORDS') }}:</span> <em>{{ ucfirst($words) }}</em></p>
    <p style="margin:2px 0">{{ $t('Ditransfer kepada', 'Transferred to') }}: <span class="b">{{ $s->employee_name }}</span> — {{ $s->bank_name ?: '-' }} {{ $s->bank_account }}</p>

    <table class="sign" style="margin-top:16px">
        <tr><td width="50%"></td><td>{{ $city ? $city . ', ' : '' }}{{ $printed }}</td></tr>
        <tr>
            <td><div class="line"></div><span class="b">{{ $set->hr_signer_name ?: '' }}</span><br>{{ $set->hr_signer_title ?: 'HR Manager' }}</td>
            <td><div class="line"></div><span class="b">{{ $set->finance_signer_name ?: '' }}</span><br>{{ $set->finance_signer_title ?: 'Finance Manager' }}</td>
        </tr>
    </table>

    <p class="muted" style="margin-top:14px; font-size:8.5px">
        {{ $t('Dokumen ini dibuat oleh sistem.', 'This document is computer-generated.') }}
        @if($s->verification_code) {{ $t('Kode verifikasi', 'Verification code') }}: {{ strtoupper(substr($s->verification_code, 0, 16)) }}@endif
        @if(!$s->slip_no) — {{ $t('Slip belum diterbitkan (Generate Slip).', 'Slip not yet issued (Generate Slip).') }}@endif
    </p>
</body>
</html>
