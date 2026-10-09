@extends('dashboard')
@section('title', $p->name)
@section('page-title', $p->name)
@section('page-subtitle', 'Payroll period detail: calculation per employee, tax & BPJS switches, corrections, payslips.')

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $lbl = 'block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1';
    $badge = ['open' => 'bg-blue-100 text-blue-700', 'locked' => 'bg-gray-800 text-white'];
    $btn = 'px-4 py-2 text-sm font-semibold rounded-lg';
    $d = fn ($v) => \Carbon\Carbon::parse($v)->format('d M Y');
    $days = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $sum = fn ($s, $k) => (float) ($s->bd['summary'][$k] ?? 0);
    $direct = fn ($s) => $sum($s, 'absence_deduction') + $sum($s, 'late_penalty') + (float) $s->component_deductions;
    $totalDirect = $slips->sum(fn ($s) => $direct($s));
    $win = \App\Support\Payroll\PayrollPeriodRules::attendanceWindow((array) $p);
    $customWin = $win['start'] !== $p->period_start || $win['end'] !== $p->period_end || $win['cutoff'];
    $correctionLabel = ['adjustment' => 'Adjustment', 'underpaid' => 'Underpaid', 'overpaid' => 'Overpaid'];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.payroll.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Header + actions --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <a href="{{ route('finance.payroll.periods') }}" class="text-xs text-indigo-700 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back</a>
            <div class="flex items-center gap-3 mt-1">
                <h2 class="text-lg font-bold text-gray-900">{{ $p->name }}</h2>
                <span class="inline-block px-2.5 py-1 text-xs font-semibold rounded-full {{ $badge[$p->status] ?? '' }}">{{ \App\Support\Payroll\PayrollPeriodRules::STATUSES[$p->status] ?? $p->status }}</span>
            </div>
            <p class="text-sm text-gray-600 mt-1">{{ $d($p->period_start) }} – {{ $d($p->period_end) }} · Pay date {{ $d($p->pay_date) }}</p>
            <p class="text-xs text-gray-500 mt-1">
                Attendance period: {{ $d($win['start']) }} – {{ $d($win['end']) }}@if($win['cutoff']) · Cut-off: {{ $d($win['cutoff']) }} (later workdays are treated as present and corrected in the next payroll)@endif.
                @if($p->calculated_at) Calculated {{ \Carbon\Carbon::parse($p->calculated_at)->format('d M Y H:i') }}. @endif
                @if($p->slips_generated_at) Payslips generated {{ \Carbon\Carbon::parse($p->slips_generated_at)->format('d M Y H:i') }}. @endif
                @if($p->locked_at) Locked {{ \Carbon\Carbon::parse($p->locked_at)->format('d M Y H:i') }}. @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($slips->count())
                <a href="{{ route('finance.payroll.periods.export-excel', $p->id) }}" class="{{ $btn }} border border-green-300 text-green-700 hover:bg-green-50"><i class="fas fa-file-excel mr-2"></i>Export Excel</a>
                <a href="{{ route('finance.payroll.periods.export', $p->id) }}" class="{{ $btn }} border border-indigo-200 text-indigo-700 hover:bg-indigo-50"><i class="fas fa-building-columns mr-2"></i>Bank Transfer</a>
            @endif
            @if($editable && $caps['edit'])
                <form method="POST" action="{{ route('finance.payroll.periods.calculate', $p->id) }}">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} border border-green-300 text-green-700 hover:bg-green-50 disabled:opacity-50"><i class="fas fa-calculator mr-2"></i>{{ $p->calculated_at ? 'Recalculate' : 'Calculate' }}</button></form>
            @endif
            @if($editable && $p->calculated_at && $canLock)
                <form method="POST" action="{{ route('finance.payroll.periods.lock', $p->id) }}" data-confirm="A locked period can never be recalculated, corrected or deleted. Make sure every amount has been reviewed." data-confirm-title="Lock this payroll?" data-confirm-ok="Lock payroll" data-confirm-variant="danger">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} bg-gray-800 text-white hover:bg-gray-900 disabled:opacity-50"><i class="fas fa-lock mr-2"></i>Lock</button></form>
            @endif
            @if($slips->count() && $canSlip)
                <form method="POST" action="{{ route('finance.payroll.periods.generate-slips', $p->id) }}" data-confirm="Each employee gets a payslip number and verification code. Payslips already generated are not changed. Employees cannot see them until you publish." data-confirm-title="Generate payslips?" data-confirm-ok="Generate">@csrf
                    <button type="submit" @disabled(!$enabled) class="{{ $btn }} primary-gradient text-white hover:opacity-90 disabled:opacity-50"><i class="fas fa-file-invoice mr-2"></i>Generate Slip</button></form>
                <form method="POST" action="{{ route('finance.payroll.periods.publish', $p->id) }}" data-confirm="Published payslips become visible to each employee in ESS → Paystub, where they can download them as PDF. Only generated payslips are published." data-confirm-title="Publish payslips?" data-confirm-ok="Publish">@csrf
                    <button type="submit" @disabled(!$enabled || !$slips->whereNotNull('slip_no')->count()) class="{{ $btn }} border border-indigo-300 text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"><i class="fas fa-paper-plane mr-2"></i>Publish Payslip</button></form>
            @endif
        </div>
    </div>

    {{-- Totals --}}
    @if($p->calculated_at)
        <div class="grid grid-cols-2 lg:grid-cols-7 gap-3">
            @foreach([
                ['Employees calculated', $p->employee_count, false],
                ['Total earnings', $p->total_gross, true],
                ['Total direct deductions', $totalDirect, true],
                ['Total take-home pay', $p->total_take_home, true],
                ['Company cost', $p->total_gross + $p->total_bpjs_employer, true],
                ['PPh 21', $p->total_pph21, true],
                ['BPJS employer', $p->total_bpjs_employer, true],
            ] as [$label, $v, $money])
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    <p class="text-[11px] text-gray-500">{{ $label }}</p>
                    <p class="text-sm font-bold text-gray-900 mt-1 tabular-nums">{{ $money ? 'Rp ' . $m($v) : $v }}</p>
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

    {{-- Payroll corrections --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start justify-between gap-3">
        <div><h3 class="text-sm font-bold text-gray-900">Payroll corrections</h3>
        <p class="text-xs text-gray-500 mt-0.5">Fix an earlier period: <strong>underpaid</strong> is added to this month’s earnings (taxable), <strong>overpaid</strong> is taken from this month’s pay. “Adjustment” is a one-off bonus or deduction. Recalculate after changing them.</p></div>
        @if($editable && $caps['create'] && $employees->isNotEmpty())
            <button type="button" id="corrOpen" @disabled(!$enabled) class="shrink-0 px-3.5 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90 disabled:opacity-50"><i class="fas fa-plus mr-1.5"></i>Add correction</button>
        @endif
        </div>

        @if($editable && $employees->isEmpty())
            <div class="mt-4 text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <i class="fas fa-circle-info mr-1"></i><strong>No employee is included in payroll yet</strong>, so there is nobody to correct or calculate. In Master → Employee → <strong>Compensation</strong>, for each employee: set the PTKP status, add a Base Salary, and tick <em>“Include this employee when payroll is calculated”</em>. Then come back here.
            </div>
        @endif

        <div class="overflow-x-auto mt-4">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide border-b border-gray-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">From period</th><th class="py-2 pr-3">Component / note</th>
                    <th class="py-2 pr-3 text-right">Amount</th><th class="py-2 pr-3">Status</th><th class="py-2"></th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($corrections as $a)
                        <tr>
                            <td class="py-2 pr-3 text-gray-900">{{ trim($a->first_name . ' ' . $a->last_name) }}</td>
                            <td class="py-2 pr-3"><span class="inline-block px-2 py-0.5 text-xs font-semibold rounded {{ $a->kind === 'earning' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $correctionLabel[$a->category] ?? ucfirst($a->category) }}</span></td>
                            <td class="py-2 pr-3 text-gray-600">{{ $a->source_name ?: '—' }}</td>
                            <td class="py-2 pr-3 text-gray-700">{{ $a->component ?: $a->name }}@if($a->component && $a->name !== $a->component)<span class="text-xs text-gray-500"> — {{ $a->name }}</span>@endif</td>
                            <td class="py-2 pr-3 text-right tabular-nums">{{ $m($a->amount) }}</td>
                            <td class="py-2 pr-3 text-xs">
                                @php $applied = $p->calculated_at && $a->created_at <= $p->calculated_at; @endphp
                                <span class="inline-block px-2 py-0.5 font-semibold rounded {{ $applied ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $applied ? 'Applied' : 'Pending' }}</span>
                            </td>
                            <td class="py-2 text-right">
                                @if($editable && $caps['edit'])
                                    <form method="POST" action="{{ route('finance.payroll.adjustments.destroy', [$p->id, $a->id]) }}" class="inline" data-confirm="The correction will be removed. Recalculate afterwards to update the payslip." data-confirm-title="Remove this correction?" data-confirm-ok="Remove" data-confirm-variant="danger">@csrf
                                        <button type="submit" @disabled(!$enabled) class="px-2 py-1 text-xs font-semibold rounded border border-red-300 text-red-700 hover:bg-red-50 disabled:opacity-50">Remove</button></form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-sm text-gray-500">No corrections for this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Employees --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-3">
            <h3 class="text-sm font-bold text-gray-900">Employees</h3>
            <input type="search" id="empFilter" placeholder="Search name, ID or position…" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm w-64 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs font-semibold text-gray-500 uppercase tracking-wide bg-gray-50">
                    <th class="px-5 py-3">Employee</th><th class="px-3 py-3">Attendance</th><th class="px-3 py-3">Payroll</th>
                    <th class="px-3 py-3">Tax &amp; BPJS</th><th class="px-3 py-3">Status</th><th class="px-5 py-3">Actions</th></tr></thead>
                <tbody class="divide-y divide-gray-100" id="empRows">
                    @forelse($slips as $s)
                        @php
                            $att = $s->bd['attendance'] ?? [];
                            $t = $toggles[$s->employee_id] ?? ['bpjs_health' => false, 'bpjs_employment' => false, 'pph21' => true];
                            $formId = 'recalc-' . $s->employee_id;
                        @endphp
                        <tr data-search="{{ strtolower($s->employee_name . ' ' . $s->employee_eci . ' ' . $s->position) }}">
                            <td class="px-5 py-3"><p class="font-semibold text-gray-900">{{ $s->employee_name }}</p><p class="text-xs text-gray-500">{{ $s->employee_eci }} · {{ $s->position ?: '—' }}</p><p class="text-xs text-gray-400">PTKP {{ $s->ptkp_code ?: '—' }}</p></td>
                            <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap leading-5">
                                @if(!empty($att) && ($att['covered_workdays'] ?? 0) > 0)
                                    <p>Present <strong>{{ $att['present'] ?? 0 }}</strong> / {{ $att['covered_workdays'] }}</p>
                                    <p>Paid leave {{ $days($att['paid_leave'] ?? 0) }} · Permit {{ $days($att['paid_permit'] ?? 0) }}</p>
                                    <p>Unpaid leave {{ $days($att['unpaid_leave'] ?? 0) }} · Absent {{ $days($att['absent'] ?? 0) }}</p>
                                    <p>Late {{ $att['late_days'] ?? 0 }}× ({{ $att['late_minutes'] ?? 0 }} min)</p>
                                    @if(($att['assumed_days'] ?? 0) > 0)<p class="text-amber-700">After cut-off {{ $att['assumed_days'] }} day(s) assumed present</p>@endif
                                    @if(empty($att['applied']))<p class="text-gray-400">Not applied to pay</p>@endif
                                @else
                                    <span class="text-gray-400">No attendance data</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-600 whitespace-nowrap leading-5 tabular-nums">
                                <p>Days paid <strong>{{ $days($sum($s, 'paid_days')) }}</strong> of {{ $days($sum($s, 'basis_days')) }}</p>
                                <p>Base salary {{ $m($baseBySlip[$s->id] ?? 0) }}</p>
                                <p>Earnings {{ $m($s->gross_earnings) }}</p>
                                <p>Direct deductions {{ $m($direct($s)) }}</p>
                                <p class="text-sm font-bold text-gray-900 {{ $s->take_home_pay < 0 ? 'text-red-700' : '' }}">THP Rp {{ $m($s->take_home_pay) }}</p>
                            </td>
                            <td class="px-3 py-3 text-xs text-gray-700 whitespace-nowrap">
                                <div class="space-y-1">
                                    <label class="flex items-center gap-1.5"><input type="checkbox" form="{{ $formId }}" name="bpjs_health" value="1" @checked($t['bpjs_health']) @disabled(!$editable || !$bpjsOn) class="rounded border-gray-300 text-indigo-600"> BPJS Kes <span class="text-gray-400 tabular-nums">{{ $m(($s->bd['bpjs']['health']['employee'] ?? 0) + ($s->bd['bpjs']['health']['dependents_amount'] ?? 0)) }}</span></label>
                                    <label class="flex items-center gap-1.5"><input type="checkbox" form="{{ $formId }}" name="bpjs_employment" value="1" @checked($t['bpjs_employment']) @disabled(!$editable || !$bpjsOn) class="rounded border-gray-300 text-indigo-600"> BPJS TK <span class="text-gray-400 tabular-nums">{{ $m(($s->bd['bpjs']['jht']['employee'] ?? 0) + ($s->bd['bpjs']['jp']['employee'] ?? 0)) }}</span></label>
                                    <label class="flex items-center gap-1.5"><input type="checkbox" form="{{ $formId }}" name="pph21" value="1" @checked($t['pph21']) @disabled(!$editable || !$pphOn) class="rounded border-gray-300 text-indigo-600"> PPh 21 <span class="text-gray-400 tabular-nums">{{ $m($s->pph21) }}</span></label>
                                </div>
                                @if($editable && $caps['edit'])
                                    <form id="{{ $formId }}" method="POST" action="{{ route('finance.payroll.periods.employee.recalculate', [$p->id, $s->employee_id]) }}" class="mt-2">@csrf
                                        <input type="hidden" name="apply_toggles" value="1">
                                        <button type="submit" @disabled(!$enabled) class="px-2.5 py-1 text-xs font-semibold rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50 disabled:opacity-50">Apply &amp; Recalculate</button></form>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">
                                <span class="inline-block px-2 py-0.5 font-semibold rounded bg-green-100 text-green-700">Calculated</span>
                                <p class="mt-1 {{ $s->slip_no ? 'text-gray-700' : 'text-gray-400' }}">{{ $s->slip_no ? 'Slip #' . str_pad($s->slip_no, 5, '0', STR_PAD_LEFT) . ' generated' : 'Slip not generated' }}</p>
                                @if($s->slip_no)<p class="{{ $s->published_at ? 'text-indigo-700' : 'text-amber-600' }}"><i class="fas {{ $s->published_at ? 'fa-eye' : 'fa-hourglass-half' }} mr-1"></i>{{ $s->published_at ? 'Published to ESS' : 'Waiting to publish' }}</p>@endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs">
                                <div class="flex flex-col gap-1.5 items-start">
                                    <button type="button" data-detail="{{ route('finance.payroll.slips.detail', [$p->id, $s->id]) }}" class="js-detail px-2.5 py-1 font-semibold rounded border border-indigo-200 text-indigo-700 hover:bg-indigo-50">Detail</button>
                                    @if($editable && $caps['edit'])
                                        <form method="POST" action="{{ route('finance.payroll.periods.employee.recalculate', [$p->id, $s->employee_id]) }}">@csrf
                                            <button type="submit" @disabled(!$enabled) class="px-2.5 py-1 font-semibold rounded border border-green-300 text-green-700 hover:bg-green-50 disabled:opacity-50">Recalculate</button></form>
                                    @endif
                                    @if($s->slip_no)
                                        <span class="inline-flex gap-1">
                                            <a href="{{ route('finance.payroll.slips.pdf', [$p->id, $s->id, 'lang' => 'id']) }}" target="_blank" class="px-2.5 py-1 font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Slip ID</a>
                                            <a href="{{ route('finance.payroll.slips.pdf', [$p->id, $s->id, 'lang' => 'en']) }}" target="_blank" class="px-2.5 py-1 font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-50">Slip EN</a>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">
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

{{-- Add correction modal --}}
@if($editable && $caps['create'] && $employees->isNotEmpty())
<div id="corrModal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="corrTitle">
    <form method="POST" action="{{ route('finance.payroll.adjustments.store', $p->id) }}" class="bg-white rounded-xl shadow-xl w-full max-w-xl my-8" id="corrForm">
        @csrf
        <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
            <div><h3 class="text-base font-bold text-gray-900" id="corrTitle">Add payroll correction</h3><p class="text-xs text-gray-500">Underpaid and overpaid amounts from an earlier period, or a one-off adjustment.</p></div>
            <button type="button" class="js-corr-close text-gray-400 hover:text-gray-700 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2"><label class="{{ $lbl }}">Employee</label>
                <select name="employee_id" data-searchable="true" data-search-placeholder="Search name or employee ID…" class="{{ $input }}"><option value="">Choose…</option>
                    @foreach($employees as $e)<option value="{{ $e->employee_id }}" @selected((int) old('employee_id') === (int) $e->employee_id)>{{ trim($e->first_name . ' ' . $e->last_name) }} ({{ $e->eci }})</option>@endforeach
                </select></div>
            <div><label class="{{ $lbl }}">Type</label>
                <select name="category" id="corrCat" class="{{ $input }}">
                    @foreach(['underpaid' => 'Underpaid (adds to pay)', 'overpaid' => 'Overpaid (deducts from pay)', 'adjustment' => 'Adjustment (one-off)'] as $cv => $cl)<option value="{{ $cv }}" @selected(old('category', 'underpaid') === $cv)>{{ $cl }}</option>@endforeach
                </select></div>
            <div class="js-cat-src"><label class="{{ $lbl }}">From period</label>
                <select name="source_period_id" class="{{ $input }}"><option value="">Choose the earlier period…</option>
                    @foreach($otherPeriods as $op)<option value="{{ $op->id }}" @selected((int) old('source_period_id') === (int) $op->id)>{{ $op->name }}</option>@endforeach</select></div>
            <div class="js-cat-adj"><label class="{{ $lbl }}">Direction</label>
                <select name="kind" class="{{ $input }}"><option value="earning" @selected(old('kind') === 'earning')>Earning</option><option value="deduction" @selected(old('kind') === 'deduction')>Deduction</option></select></div>
            <div><label class="{{ $lbl }}">Amount (IDR)</label>
                <input type="text" inputmode="decimal" name="amount" value="{{ old('amount') }}" placeholder="0,00" class="{{ $input }} js-money text-right tabular-nums"></div>
            <div><label class="{{ $lbl }}">Salary component</label>
                <input type="text" name="component" maxlength="150" value="{{ old('component') }}" placeholder="e.g. Timesheet allowance" class="{{ $input }}"></div>
            <div class="sm:col-span-2"><label class="{{ $lbl }}">Reason</label>
                <textarea name="name" rows="2" maxlength="150" placeholder="e.g. Allowance not paid in the previous period" class="{{ $input }}">{{ old('name') }}</textarea></div>
            <label class="sm:col-span-2 js-cat-adj flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="taxable" value="1" @checked(old('_token') ? old('taxable') : true) class="rounded border-gray-300 text-indigo-600"> Counts as taxable income (PPh 21)</label>
            <p class="sm:col-span-2 js-cat-src text-xs text-gray-500"><i class="fas fa-circle-info mr-1"></i>Underpaid amounts are taxed as income of this month; overpaid amounts are deducted from take-home pay.</p>
        </div>
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <button type="button" class="js-corr-close {{ $btn }} border border-gray-300 text-gray-700 hover:bg-white">Cancel</button>
            <button type="submit" @disabled(!$enabled) class="{{ $btn }} primary-gradient text-white hover:opacity-90 disabled:opacity-50"><i class="fas fa-check mr-2"></i>Save correction</button>
        </div>
    </form>
</div>
@endif
{{-- Detail modal --}}
<div id="detailModal" class="fixed inset-0 z-50 hidden items-start justify-center overflow-y-auto bg-black/50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl my-8">
        <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
            <div><h3 class="text-base font-bold text-gray-900" id="dmTitle">Payroll detail</h3><p class="text-xs text-gray-500" id="dmSub"></p></div>
            <button type="button" id="dmClose" class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-5" id="dmBody"><p class="text-sm text-gray-500">Loading…</p></div>
    </div>
</div>

<script>
(function () {
    // Formulir koreksi: kolom periode asal untuk underpaid/overpaid, arah & pajak untuk adjustment.
    var cm = document.getElementById('corrModal');
    if (cm) {
        var openCm = function () { cm.classList.remove('hidden'); cm.classList.add('flex'); };
        var closeCm = function () { cm.classList.add('hidden'); cm.classList.remove('flex'); };
        var ob = document.getElementById('corrOpen');
        if (ob) ob.addEventListener('click', openCm);
        cm.querySelectorAll('.js-corr-close').forEach(function (b) { b.addEventListener('click', closeCm); });
        cm.addEventListener('mousedown', function (e) { if (e.target === cm) closeCm(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCm(); });
        @if($errors->any() && old('category'))
        openCm();
        @endif
    }

    var cat = document.getElementById('corrCat');
    if (cat) {
        var sync = function () {
            var adj = cat.value === 'adjustment';
            document.querySelectorAll('.js-cat-src').forEach(function (e) { e.style.display = adj ? 'none' : ''; });
            document.querySelectorAll('.js-cat-adj').forEach(function (e) { e.style.display = adj ? '' : 'none'; });
        };
        cat.addEventListener('change', sync); sync();
    }

    // Pencarian cepat pada tabel karyawan.
    var f = document.getElementById('empFilter');
    if (f) f.addEventListener('input', function () {
        var q = f.value.toLowerCase().trim();
        document.querySelectorAll('#empRows tr[data-search]').forEach(function (r) { r.style.display = !q || r.dataset.search.indexOf(q) !== -1 ? '' : 'none'; });
    });

    var modal = document.getElementById('detailModal'), body = document.getElementById('dmBody');
    var rp = function (n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var num = function (n) { return Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var dd = function (n) { return String(Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })); };
    var close = function () { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.getElementById('dmClose').addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    function card(label, value) { return '<div class="rounded-lg border border-gray-200 px-3 py-2"><p class="text-[11px] text-gray-500">' + label + '</p><p class="text-sm font-bold text-gray-900 tabular-nums">' + value + '</p></div>'; }
    function table(title, rows, total, totalLabel) {
        var h = '<div><p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">' + title + '</p><table class="w-full text-sm"><tbody class="divide-y divide-gray-100">';
        if (!rows.length) h += '<tr><td class="py-2 text-gray-400">None</td></tr>';
        rows.forEach(function (r) { h += '<tr><td class="py-1.5 pr-2 text-gray-700">' + esc(r.name) + '</td><td class="py-1.5 text-right tabular-nums">' + num(r.amount) + '</td></tr>'; });
        return h + '<tr class="font-bold"><td class="py-2">' + totalLabel + '</td><td class="py-2 text-right tabular-nums">' + num(total) + '</td></tr></tbody></table></div>';
    }

    function render(j) {
        var s = j.summary || {}, a = j.attendance || {}, px = j.pph21 || {}, bp = j.bpjs || {};
        var earn = j.items.filter(function (i) { return i.type === 'earning'; }), ded = j.items.filter(function (i) { return i.type === 'deduction'; });
        document.getElementById('dmTitle').textContent = j.employee.name + (j.slip_no ? ' · Slip #' + String(j.slip_no).padStart(5, '0') : '');
        document.getElementById('dmSub').textContent = j.employee.eci + ' · ' + (j.employee.position || '—') + ' · PTKP ' + (j.employee.ptkp || '—') + ' · ' + j.period.name;
        var h = '<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">'
            + card('Total earnings', rp(s.gross_earnings)) + card('Total deductions', rp(s.total_deductions)) + card('Take-home pay', rp(s.take_home_pay)) + card('Company cost', rp(s.employer_cost)) + '</div>';
        h += '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">' + table('Earnings', earn, s.gross_earnings, 'Total earnings') + table('Deductions', ded, s.total_deductions, 'Total deductions') + '</div>';
        h += '<div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-xs text-gray-700 leading-5"><p class="font-semibold text-gray-900 mb-1">Proration</p>'
            + '<p>Days paid ' + dd(s.paid_days) + ' of ' + dd(s.basis_days) + '. Pay is prorated when the Base Salary covers only part of the period.</p></div>';
        if (a && a.covered_workdays !== undefined) {
            h += '<div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-xs text-gray-700 leading-5"><p class="font-semibold text-gray-900 mb-1">Attendance &amp; proration</p>'
                + '<p>Attendance period ' + esc(a.window_start) + ' – ' + esc(a.window_end) + (a.cutoff ? ' (cut-off ' + esc(a.cutoff) + ')' : '') + '</p>'
                + '<p>Workdays ' + a.covered_workdays + ' · present ' + a.present + ' · paid leave ' + dd(a.paid_leave) + ' · permit ' + dd(a.paid_permit) + ' · unpaid leave ' + dd(a.unpaid_leave) + ' · absent ' + dd(a.absent) + '</p>'
                + '<p>Late ' + a.late_days + '× (' + a.late_minutes + ' min, ' + a.late_minutes_chargeable + ' min chargeable) · overtime ' + dd(j.overtime_hours) + ' h</p>'
                + (a.assumed_days ? '<p>' + a.assumed_days + ' workday(s) after the cut-off are assumed present.</p>' : '')
                + (a.applied ? '' : '<p class="text-gray-500">Attendance is shown for information only and is not deducted from pay.</p>') + '</div>';
        }
        h += '<div class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3 text-xs text-gray-700 leading-5"><p class="font-semibold text-gray-900 mb-1">BPJS &amp; PPh 21 basis</p>'
            + '<p>BPJS wage base ' + rp(s.bpjs_wage) + ' (base salary + fixed allowances) · employee ' + rp(s.bpjs_employee) + ' · employer ' + rp(s.bpjs_employer) + '</p>';
        if (px && px.method && px.method !== 'disabled' && px.method !== 'none') {
            h += '<p>PPh 21 method: ' + (px.method === 'ter' ? 'TER monthly rate (PMK 168/2023), category ' + esc(px.category) + ', rate ' + dd(px.rate) + '% × gross ' + rp(px.tax_gross) : 'Annual true-up (final tax month)') + ' = ' + rp(px.tax) + '</p>';
        } else { h += '<p>PPh 21 is not withheld for this payslip.</p>'; }
        h += '</div>';
        if (j.warnings && j.warnings.length) h += '<ul class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 list-disc list-inside">' + j.warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('') + '</ul>';
        body.innerHTML = h;
    }

    document.querySelectorAll('.js-detail').forEach(function (b) {
        b.addEventListener('click', function () {
            body.innerHTML = '<p class="text-sm text-gray-500">Loading…</p>';
            modal.classList.remove('hidden'); modal.classList.add('flex');
            fetch(b.dataset.detail, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(); return r.json(); }).then(render)
                .catch(function () { body.innerHTML = '<p class="text-sm text-red-600">Could not load the detail. Please try again.</p>'; });
        });
    });
})();
</script>
@endsection
