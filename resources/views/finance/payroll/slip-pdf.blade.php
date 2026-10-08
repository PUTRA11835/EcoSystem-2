<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payslip — {{ $s->employee_name }}</title>
@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $earn = $items->where('type', 'earning');
    $ded  = $items->where('type', 'deduction');
    $emp  = $items->where('type', 'employer');
@endphp
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td, th { padding: 4px 6px; }
    th { text-align: left; background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
    .r { text-align: right; }
    .tot td { font-weight: bold; border-top: 1px solid #111827; }
    .box { border: 1px solid #d1d5db; padding: 8px 10px; margin-top: 10px; }
    .thp { font-size: 15px; font-weight: bold; }
</style>
</head>
<body>
    <h1>Payslip</h1>
    <p class="muted">{{ $p->name }} · {{ \Carbon\Carbon::parse($p->period_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($p->period_end)->format('d M Y') }}</p>

    <div class="box">
        <table>
            <tr><td class="muted" width="22%">Employee</td><td><strong>{{ $s->employee_name }}</strong></td><td class="muted" width="22%">Employee no.</td><td>{{ $s->employee_eci }}</td></tr>
            <tr><td class="muted">Position</td><td>{{ $s->position ?: '—' }}</td><td class="muted">Department</td><td>{{ $s->department ?: '—' }}</td></tr>
            <tr><td class="muted">PTKP</td><td>{{ $s->ptkp_code ?: '—' }}</td><td class="muted">Bank</td><td>{{ $s->bank_name ?: '—' }} {{ $s->bank_account }}</td></tr>
        </table>
    </div>

    <table>
        <tr><th>Earnings</th><th class="r">IDR</th></tr>
        @foreach($earn as $r)<tr><td>{{ $r->name }}</td><td class="r">{{ $m($r->amount) }}</td></tr>@endforeach
        <tr class="tot"><td>Total earnings</td><td class="r">{{ $m($s->gross_earnings) }}</td></tr>
    </table>

    <table>
        <tr><th>Deductions</th><th class="r">IDR</th></tr>
        @foreach($ded as $r)<tr><td>{{ $r->name }}</td><td class="r">{{ $m($r->amount) }}</td></tr>@endforeach
        <tr class="tot"><td>Total deductions</td><td class="r">{{ $m($s->total_deductions) }}</td></tr>
    </table>

    <div class="box"><table><tr><td class="thp">Take-home pay</td><td class="r thp">Rp {{ $m($s->take_home_pay) }}</td></tr></table></div>

    @if($emp->count())
        <table>
            <tr><th>Employer BPJS contributions (information)</th><th class="r">IDR</th></tr>
            @foreach($emp as $r)<tr><td>{{ $r->name }}</td><td class="r">{{ $m($r->amount) }}</td></tr>@endforeach
        </table>
    @endif

    <p class="muted" style="margin-top:14px">Generated {{ now()->format('d M Y H:i') }}. This payslip is computer-generated.</p>
</body>
</html>
