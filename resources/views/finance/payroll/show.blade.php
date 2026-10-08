@extends('dashboard')
@section('title', $p->name)
@section('page-title', $p->name)
@section('page-subtitle', 'Payroll period detail: adjustments, calculation, payslips and approval.')

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $badge = ['open' => 'bg-gray-100 text-gray-700', 'approved' => 'bg-blue-100 text-blue-700', 'paid' => 'bg-green-100 text-green-700', 'locked' => 'bg-gray-800 text-white'];
    $btn = 'px-4 py-2 text-sm font-semibold rounded-lg';
    $post = fn ($route, $label, $cls, $confirm = null) => '';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.payroll.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Header + actions --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <a href="{{ route('finance.payroll.periods') }}" class="text-xs text-indigo-700 hover:underline"><i class="fas fa-arrow-left mr-1"></i>All periods</a>
            <div class="flex items-center gap-3 mt-1">
                <h2 class="text-lg font-bold text-gray-900">{{ $p->name }}</h2>
                <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full {{ $badge[$p->status] ?? '' }}">{{ \App\Support\Payroll\PayrollPeriodRules::STATUSES[$p->status] ?? $p->status }}</span>
            </div>
            <p class="text-sm text-gray-600 mt-1">{{ \Carbon\Carbon::parse($p->period_start)->format('d M Y') }} – {{ \Carbon\Carbon::parse($p->period_end)->format('d M Y') }} · Pay date {{ \Carbon\Carbon::parse($p->pay_date)->format('d M Y') }}</p>
            <p class="text-xs text-gray-500 mt-1">
                @if($p->calculated_at) Calculated {{ \Carbon\Carbon::parse($p->calculated_at)->format('d M Y H:i') }}. @endif
                @if($p->approved_at) Approved {{ \Carbon\Carbon::parse($p->approved_at)->format('d M Y H:i') }}. @endif
                @if($p->paid_at) Paid {{ \Carbon\Carbon::parse($p->paid_at)->format('d M Y H:i') }}. @endif
                @if($p->locked_at) Locked {{ \Carbon\Carbon::parse($p->locked_at)->format('d M Y H:i') }}. @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($p->status === 'open' && $caps['edit'])
                <form method="POST" action="{{ route('finance.payroll.periods.calculate', $p->id) }}">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} border border-green-300 text-green-700 hover:bg-green-50 disabled:opacity-50"><i class="fas fa-calculator mr-2"></i>{{ $p->calculated_at ? 'Recalculate' : 'Calculate' }}</button></form>
            @endif
            @if($p->status === 'open' && $p->calculated_at && $can('finance.payroll.approve'))
                <form method="POST" action="{{ route('finance.payroll.periods.approve', $p->id) }}" onsubmit="return confirm('Approve this payroll? Payslips can no longer be recalculated unless it is reopened.')">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} primary-gradient text-white hover:opacity-90 disabled:opacity-50"><i class="fas fa-circle-check mr-2"></i>Approve</button></form>
            @endif
            @if($p->status === 'approved' && $can('finance.payroll.approve'))
                <form method="POST" action="{{ route('finance.payroll.periods.reopen', $p->id) }}" onsubmit="return confirm('Reopen this payroll for changes?')">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">Reopen</button></form>
            @endif
            @if($p->status === 'approved' && $can('finance.payroll.pay'))
                <form method="POST" action="{{ route('finance.payroll.periods.pay', $p->id) }}" onsubmit="return confirm('Mark as paid only after the salaries were really transferred. Continue?')">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} primary-gradient text-white hover:opacity-90 disabled:opacity-50"><i class="fas fa-money-bill-transfer mr-2"></i>Mark paid</button></form>
            @endif
            @if($p->status === 'paid' && $can('finance.payroll.lock'))
                <form method="POST" action="{{ route('finance.payroll.periods.lock', $p->id) }}" onsubmit="return confirm('Lock this payroll? Locked payroll can never be changed.')">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} bg-gray-800 text-white hover:bg-gray-900 disabled:opacity-50"><i class="fas fa-lock mr-2"></i>Lock</button></form>
            @endif
            @if($slips->count())
                <a href="{{ route('finance.payroll.periods.export', $p->id) }}" class="{{ $btn }} border border-indigo-200 text-indigo-700 hover:bg-indigo-50"><i class="fas fa-file-csv mr-2"></i>Bank transfer CSV</a>
            @endif
        </div>
    </div>

    {{-- Totals --}}
    @if($p->calculated_at)
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3">
            @foreach([['Employees', $p->employee_count, false], ['Gross', $p->total_gross, true], ['Deductions', $p->total_deductions, true], ['Take-home pay', $p->total_take_home, true], ['BPJS (employee / employer)', null, true], ['PPh 21', $p->total_pph21, true]] as [$label, $v, $money])
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    <p class="text-[11px] text-gray-500">{{ $label }}</p>
                    @if($label === 'BPJS (employee / employer)')
                        <p class="text-xs font-bold text-gray-900 mt-1 tabular-nums">{{ $m($p->total_bpjs_employee) }}<br><span class="font-normal text-gray-500">{{ $m($p->total_bpjs_employer) }}</span></p>
                    @else
                        <p class="text-sm font-bold text-gray-900 mt-1 tabular-nums">{{ $money ? 'Rp ' . $m($v) : $v }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Warnings --}}
    @if($warnings)
        <details class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3" {{ count($warnings) <= 5 ? 'open' : '' }}>
            <summary class="text-sm font-semibold text-amber-800 cursor-pointer"><i class="fas fa-triangle-exclamation mr-2"></i>{{ count($warnings) }} warning(s) from the last calculation</summary>
            <ul class="mt-2 text-xs text-amber-900 list-disc list-inside space-y-0.5">@foreach($warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
        </details>
    @endif

    {{-- Adjustments (open periods only) --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-900">Adjustments</h3>
        <p class="text-xs text-gray-500 mt-0.5">One-off items for this period only — bonus, THR, correction, or an extra deduction. Recalculate after changing them.</p>

        @if($editable && $employees->isEmpty())
            <div class="mt-4 text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <i class="fas fa-circle-info mr-1"></i><strong>No employee is included in payroll yet</strong>, so there is nobody to adjust or calculate. In Master → Employee → <strong>Compensation</strong>, for each employee: set the PTKP status, add a Base Salary, and tick <em>“Include this employee when payroll is calculated”</em>. Then come back here.
            </div>
        @endif

        @if($editable && $caps['create'] && $employees->isNotEmpty())
            <form method="POST" action="{{ route('finance.payroll.adjustments.store', $p->id) }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end mt-4">
                @csrf
                <div class="md:col-span-3"><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Employee</label>
                    <select name="employee_id" class="{{ $input }}"><option value="">Choose…</option>
                        @foreach($employees as $e)<option value="{{ $e->employee_id }}" @selected((int) old('employee_id') === (int) $e->employee_id)>{{ trim($e->first_name . ' ' . $e->last_name) }} ({{ $e->eci }})</option>@endforeach
                    </select></div>
                <div class="md:col-span-2"><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Type</label>
                    <select name="kind" class="{{ $input }}"><option value="earning" @selected(old('kind') === 'earning')>Earning</option><option value="deduction" @selected(old('kind') === 'deduction')>Deduction</option></select></div>
                <div class="md:col-span-3"><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Description</label>
                    <input type="text" name="name" maxlength="150" value="{{ old('name') }}" placeholder="e.g. Performance bonus" class="{{ $input }}"></div>
                <div class="md:col-span-2"><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Amount (IDR)</label>
                    <input type="text" inputmode="decimal" name="amount" value="{{ old('amount') }}" placeholder="0,00" class="{{ $input }} js-money text-right tabular-nums"></div>
                <div class="md:col-span-2 flex items-center gap-3">
                    <label class="flex items-center gap-1.5 text-xs text-gray-700"><input type="checkbox" name="taxable" value="1" @checked(old('_token') ? old('taxable') : true) class="rounded border-gray-300 text-indigo-600"> Taxable</label>
                    <button type="submit" @disabled(!$enabled) class="px-3 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90 disabled:opacity-50">Add</button>
                </div>
            </form>
        @endif

        <div class="overflow-x-auto mt-4">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Description</th><th class="py-2 pr-3 text-right">Amount</th><th class="py-2 pr-3">Taxable</th><th class="py-2"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($adjustments as $a)
                        <tr>
                            <td class="py-2 pr-3 text-gray-900">{{ trim($a->first_name . ' ' . $a->last_name) }}</td>
                            <td class="py-2 pr-3"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $a->kind === 'earning' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($a->kind) }}</span></td>
                            <td class="py-2 pr-3 text-gray-700">{{ $a->name }}</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ $m($a->amount) }}</td>
                            <td class="py-2 pr-3 text-xs text-gray-500">{{ $a->taxable ? 'Yes' : 'No' }}</td>
                            <td class="py-2 text-right">
                                @if($editable && $caps['edit'])
                                    <form method="POST" action="{{ route('finance.payroll.adjustments.destroy', [$p->id, $a->id]) }}" class="inline" onsubmit="return confirm('Remove this adjustment?')">@csrf
                                        <button type="submit" @disabled(!$enabled) class="px-2 py-1 text-xs font-semibold rounded border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50">Remove</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-sm text-gray-500">No adjustments for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Payslips --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-900">Payslips</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                    <th class="px-5 py-3">Employee</th><th class="px-3 py-3">PTKP</th><th class="px-3 py-3 text-right">Gross</th><th class="px-3 py-3 text-right">BPJS</th>
                    <th class="px-3 py-3 text-right">PPh 21</th><th class="px-3 py-3 text-right">Deductions</th><th class="px-3 py-3 text-right">Take-home</th><th class="px-5 py-3"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($slips as $s)
                        <tr>
                            <td class="px-5 py-2.5"><p class="font-semibold text-gray-900">{{ $s->employee_name }}</p><p class="text-xs text-gray-500">{{ $s->employee_eci }} · {{ $s->position }}</p></td>
                            <td class="px-3 py-2.5 text-gray-600">{{ $s->ptkp_code ?: '—' }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $m($s->gross_earnings) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $m($s->bpjs_employee) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $m($s->pph21) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $m($s->total_deductions) }}</td>
                            <td class="px-3 py-2.5 text-right tabular-nums font-bold text-gray-900 {{ $s->take_home_pay < 0 ? 'text-red-700' : '' }}">{{ $m($s->take_home_pay) }}</td>
                            <td class="px-5 py-2.5 whitespace-nowrap text-right">
                                <a href="{{ route('finance.payroll.slips.show', [$p->id, $s->id]) }}" class="px-2.5 py-1 text-xs font-semibold rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50">View</a>
                                <a href="{{ route('finance.payroll.slips.pdf', [$p->id, $s->id]) }}" target="_blank" class="px-2.5 py-1 text-xs font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-50">PDF</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-gray-500">
                            @if($p->calculated_at)
                                This period was calculated but produced no payslips — see the warnings above. {{ $editable && $employees->isEmpty() ? 'No employee is included in payroll yet (Master → Employee → Compensation).' : '' }}
                            @else
                                No payslips yet. {{ $editable ? 'Click Calculate to generate them.' : '' }}
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
