@extends('dashboard')
@section('title', 'Payslip — ' . $s->employee_name)
@section('page-title', 'Payslip')
@section('page-subtitle', $p->name . ' · ' . $s->employee_name)

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $earn = $items->where('type', 'earning');
    $ded  = $items->where('type', 'deduction');
    $emp  = $items->where('type', 'employer');
    $pph  = $breakdown['pph21'] ?? [];
    $rates = $breakdown['rates'] ?? [];
    $methodLabel = ['ter' => 'Monthly effective rate (TER)', 'annual_true_up' => 'Final period — annual calculation (Article 17) less tax already withheld', 'none' => 'Not calculated'];
@endphp

@section('content')
<div class="w-full max-w-5xl space-y-6 px-1 lg:px-2">
    @include('finance.payroll.components.tabs', ['enabled' => true])

    <div class="flex items-center justify-between">
        <a href="{{ route('finance.payroll.periods.show', $p->id) }}" class="text-xs text-indigo-700 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to {{ $p->name }}</a>
        <a href="{{ route('finance.payroll.slips.pdf', [$p->id, $s->id, 'lang' => 'id']) }}" target="_blank" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-file-pdf mr-2"></i>PDF (Indonesia)</a>
    </div>

    {{-- Identity --}}
    <div class="primary-surface rounded-xl p-6 shadow-sm text-white">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-white text-opacity-70">Employee</p>
                <h2 class="text-xl font-bold">{{ $s->employee_name }}</h2>
                <p class="text-sm text-white text-opacity-80">{{ $s->employee_eci }} · {{ $s->position ?: '—' }} · {{ $s->department ?: '—' }}</p>
                <p class="text-xs text-white text-opacity-70 mt-1">PTKP {{ $s->ptkp_code ?: '—' }} · {{ $s->bank_name ?: 'No bank' }} {{ $s->bank_account }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs uppercase tracking-wide text-white text-opacity-70">Take-home pay</p>
                <p class="text-3xl font-bold tabular-nums">Rp {{ $m($s->take_home_pay) }}</p>
                <p class="text-xs text-white text-opacity-70">{{ \Carbon\Carbon::parse($p->period_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($p->period_end)->format('d M Y') }}</p>
            </div>
        </div>
    </div>

    @if(!empty($breakdown['warnings']))
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-900">
            <p class="font-semibold mb-1"><i class="fas fa-triangle-exclamation mr-1"></i>Warnings</p>
            <ul class="list-disc list-inside">@foreach($breakdown['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach([['Earnings', $earn, $s->gross_earnings, 'Total earnings'], ['Deductions', $ded, $s->total_deductions, 'Total deductions']] as [$title, $rows, $total, $totLabel])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">{{ $title }}</h3></div>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($rows as $r)
                            <tr><td class="px-5 py-2 text-gray-700">{{ $r->name }}@if($r->taxable)<span class="ml-1 text-[10px] text-gray-400">taxable</span>@endif</td><td class="px-5 py-2 text-right tabular-nums">{{ $m($r->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="px-5 py-4 text-center text-gray-400 text-xs">None</td></tr>
                        @endforelse
                        <tr class="bg-gray-50 font-bold"><td class="px-5 py-2.5">{{ $totLabel }}</td><td class="px-5 py-2.5 text-right tabular-nums">{{ $m($total) }}</td></tr>
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">Employer BPJS contributions <span class="font-normal text-xs text-gray-400">(information — not deducted from pay)</span></h3></div>
            <table class="w-full text-sm"><tbody class="divide-y divide-gray-100">
                @forelse($emp as $r)<tr><td class="px-5 py-2 text-gray-700">{{ $r->name }}</td><td class="px-5 py-2 text-right tabular-nums">{{ $m($r->amount) }}</td></tr>@empty<tr><td class="px-5 py-4 text-center text-gray-400 text-xs">None</td></tr>@endforelse
                <tr class="bg-gray-50 font-bold"><td class="px-5 py-2.5">Total employer</td><td class="px-5 py-2.5 text-right tabular-nums">{{ $m($s->bpjs_employer) }}</td></tr>
            </tbody></table>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">PPh 21 calculation</h3></div>
            <dl class="px-5 py-3 text-sm space-y-1.5">
                <div class="flex justify-between"><dt class="text-gray-500">Method</dt><dd class="text-gray-900 text-right max-w-[16rem]">{{ $methodLabel[$pph['method'] ?? 'none'] ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Gross income for tax</dt><dd class="tabular-nums">{{ $m($s->tax_gross) }}</dd></div>
                @if(($pph['method'] ?? '') === 'ter')
                    <div class="flex justify-between"><dt class="text-gray-500">TER category · rate</dt><dd>{{ $pph['category'] }} · {{ rtrim(rtrim(number_format($pph['rate'], 2, ',', ''), '0'), ',') }}%</dd></div>
                @elseif(($pph['method'] ?? '') === 'annual_true_up' && !empty($pph['annual']))
                    @php $a = $pph['annual']['annual']; @endphp
                    <div class="flex justify-between"><dt class="text-gray-500">Annual gross income</dt><dd class="tabular-nums">{{ $m($a['annual_gross']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Occupational cost</dt><dd class="tabular-nums">− {{ $m($a['occupational_cost']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">JHT + JP (employee)</dt><dd class="tabular-nums">− {{ $m($a['employee_pension']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">PTKP</dt><dd class="tabular-nums">− {{ $m($a['ptkp']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Taxable income (PKP)</dt><dd class="tabular-nums">{{ $m($a['pkp']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Annual PPh 21</dt><dd class="tabular-nums">{{ $m($a['tax']) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Already withheld</dt><dd class="tabular-nums">− {{ $m($pph['annual']['withheld']) }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-gray-200 pt-2 font-bold"><dt>PPh 21 this period</dt><dd class="tabular-nums">{{ $m($s->pph21) }}</dd></div>
                @if($s->pph21_refund > 0)
                    <p class="text-xs text-amber-700 bg-amber-50 rounded px-2 py-1">Over-withheld by Rp {{ $m($s->pph21_refund) }} — refund or offset is handled by Accounting.</p>
                @endif
            </dl>
        </div>
    </div>

    <p class="text-xs text-gray-400">Rates used: BPJS setting effective {{ $rates['bpjs_effective_date'] ?? '—' }}, PPh 21 tax year {{ $rates['pph21_year'] ?? '—' }}. This payslip keeps those rates even if settings change later.</p>
</div>
@endsection
