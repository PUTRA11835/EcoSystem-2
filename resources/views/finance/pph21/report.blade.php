@extends('dashboard')
@section('title', 'PPh 21 Report')
@section('page-title', 'PPh 21 Report')
@section('page-subtitle', 'Summary of employee tax deductions per payroll period.')

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.pph21.components.tabs')

    <form method="GET" action="{{ route('finance.pph21.report') }}" data-unsaved-ignore class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Payroll period</label>
            <select name="period" onchange="this.form.submit()" class="{{ $input }} min-w-[16rem]">
                @forelse($periods as $p)<option value="{{ $p->id }}" @selected($period && $period->id === $p->id)>{{ $p->name }}</option>
                @empty<option value="">No calculated period yet</option>@endforelse
            </select>
        </div>
        @if($period)
            <a href="{{ route('finance.pph21.report.export', ['period' => $period->id]) }}" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-file-excel mr-2"></i>Export Excel</a>
        @endif
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Employees</p><p class="text-3xl font-bold text-gray-900 mt-1">{{ $totals['employees'] }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Gross income for tax</p><p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">Rp {{ $m($totals['gross']) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Total PPh 21</p><p class="text-2xl font-bold text-red-600 mt-1 tabular-nums">Rp {{ $m($totals['tax']) }}</p></div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900">PPh 21 details {{ $period?->name }}</h3>
            <span class="text-xs text-gray-500">{{ count($rows) }} records</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                    <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Employee</th><th class="px-3 py-3">PTKP / Tax ID</th><th class="px-3 py-3 text-right">Gross</th>
                    <th class="px-3 py-3 text-right">PPh 21</th><th class="px-3 py-3">Method</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $i => $r)
                        <tr>
                            <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-3"><p class="font-semibold text-gray-900">{{ $r['name'] }}</p><p class="text-xs text-gray-500">{{ $r['eci'] }}{{ $r['department'] ? ' · ' . $r['department'] : '' }}</p></td>
                            <td class="px-3 py-3 text-xs text-gray-600"><p>PTKP: {{ $r['ptkp'] ?: '—' }}</p><p>NPWP: {{ $r['npwp'] ?: '—' }}</p></td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $m($r['gross']) }}</td>
                            <td class="px-3 py-3 text-right tabular-nums font-semibold text-red-600">{{ $m($r['tax']) }}@if($r['refund'] > 0)<p class="text-[10px] font-normal text-amber-700">over-withheld {{ $m($r['refund']) }}</p>@endif</td>
                            <td class="px-3 py-3 text-xs text-gray-600">{{ $r['method_label'] }}</td>
                            <td class="px-5 py-3 text-right">
                                @if($can('finance.payroll.periods'))
                                    <a href="{{ route('finance.payroll.slips.show', [$period->id, $r['slip_id']]) }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50"><i class="fas fa-receipt mr-1"></i>Tax slip</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">{{ $period ? 'This period has no payslips.' : 'No payroll period has been calculated yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
