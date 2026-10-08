@extends('dashboard')
@section('title', 'BPJS Report')
@section('page-title', 'BPJS Report')
@section('page-subtitle', 'Summary of employee BPJS contributions and company contributions per payroll period.')

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.bpjs.components.tabs')

    <form method="GET" action="{{ route('finance.bpjs.report') }}" data-unsaved-ignore class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-end gap-2">
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Payroll period</label>
                <select name="period" onchange="this.form.submit()" class="{{ $input }} min-w-[16rem]">
                    @forelse($periods as $p)<option value="{{ $p->id }}" @selected($period && $period->id === $p->id)>{{ $p->name }}</option>
                    @empty<option value="">No calculated period yet</option>@endforelse
                </select>
            </div>
            <noscript><button type="submit" class="px-3 py-2 text-sm font-semibold rounded-lg primary-gradient text-white">Show</button></noscript>
        </div>
        @if($period)
            <a href="{{ route('finance.bpjs.report.export', ['period' => $period->id]) }}" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-file-excel mr-2"></i>Export Excel</a>
        @endif
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Employees</p><p class="text-3xl font-bold text-gray-900 mt-1">{{ $totals['employees'] }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Total BPJS base</p><p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums">Rp {{ $m($totals['base']) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Employee deduction</p><p class="text-2xl font-bold text-red-600 mt-1 tabular-nums">Rp {{ $m($totals['employee']) }}</p></div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4"><p class="text-xs text-gray-500">Company contribution</p><p class="text-2xl font-bold text-green-600 mt-1 tabular-nums">Rp {{ $m($totals['employer']) }}</p></div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900">BPJS details {{ $period?->name }}</h3>
            <span class="text-xs text-gray-500">{{ count($rows) }} data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                    <th class="px-5 py-3 w-12">No</th><th class="px-3 py-3">Employee</th><th class="px-3 py-3">BPJS no.</th><th class="px-3 py-3 text-right">BPJS base</th>
                    <th class="px-3 py-3">Employee deduction</th><th class="px-5 py-3">Company contribution</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $i => $r)
                        <tr class="align-top">
                            <td class="px-5 py-3 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-3"><p class="font-semibold text-gray-900">{{ $r['name'] }}</p><p class="text-xs text-gray-500">{{ $r['eci'] }}{{ $r['department'] ? ' · ' . $r['department'] : '' }}</p></td>
                            <td class="px-3 py-3 text-xs text-gray-600"><p>Kes: {{ $r['bpjs_health_no'] ?: '—' }}</p><p>TK: {{ $r['bpjs_employment_no'] ?: '—' }}</p></td>
                            <td class="px-3 py-3 text-right tabular-nums">{{ $m($r['base']) }}</td>
                            <td class="px-3 py-3 text-xs tabular-nums space-y-0.5">
                                <p>Kes <span class="float-right ml-6">{{ $m($r['ee']['health']) }}</span></p><p>JHT <span class="float-right ml-6">{{ $m($r['ee']['jht']) }}</span></p><p>JP <span class="float-right ml-6">{{ $m($r['ee']['jp']) }}</span></p>
                                <p class="font-bold text-red-600 border-t border-gray-200 pt-0.5">Total <span class="float-right ml-6">{{ $m($r['employee_total']) }}</span></p>
                            </td>
                            <td class="px-5 py-3 text-xs tabular-nums space-y-0.5">
                                <p>Kes <span class="float-right ml-6">{{ $m($r['er']['health']) }}</span></p><p>JHT <span class="float-right ml-6">{{ $m($r['er']['jht']) }}</span></p><p>JP <span class="float-right ml-6">{{ $m($r['er']['jp']) }}</span></p>
                                <p>JKK <span class="float-right ml-6">{{ $m($r['er']['jkk']) }}</span></p><p>JKM <span class="float-right ml-6">{{ $m($r['er']['jkm']) }}</span></p>
                                <p class="font-bold text-green-600 border-t border-gray-200 pt-0.5">Total <span class="float-right ml-6">{{ $m($r['employer_total']) }}</span></p>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">{{ $period ? 'This period has no payslips.' : 'No payroll period has been calculated yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
