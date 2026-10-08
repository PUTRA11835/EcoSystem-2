@extends('dashboard')
@section('title', 'Payroll Simulation')
@section('page-title', 'Payroll Simulation')
@section('page-subtitle', 'Test payroll scenarios without saving a payroll run. The result uses the same tax, BPJS and deduction formulas as the payroll engine.')

@php
    $m = fn ($n) => \App\Support\Payroll\Money::format($n);
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $enabled = true;   // Simulation tidak bergantung pada saklar payroll
    $r = $result['result'] ?? null;
    $money = fn (string $name, string $label, string $help = '') => new \Illuminate\Support\HtmlString(
        '<div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">' . e($label) . '</label>'
        . '<div class="flex"><span class="inline-flex items-center px-2.5 text-sm text-gray-500 bg-gray-50 border border-r-0 border-gray-200 rounded-l-lg">Rp</span>'
        . '<input type="text" inputmode="decimal" name="' . e($name) . '" id="f_' . e($name) . '" value="' . e($in[$name] ?? '') . '" placeholder="0,00" class="' . $input . ' rounded-l-none js-money text-right tabular-nums"></div>'
        . ($help ? '<p class="text-[11px] text-gray-400 mt-1">' . e($help) . '</p>' : '') . '</div>'
    );
    $num = fn (string $name, string $label, string $step = '1') => new \Illuminate\Support\HtmlString(
        '<div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">' . e($label) . '</label>'
        . '<input type="number" min="0" step="' . $step . '" name="' . e($name) . '" id="f_' . e($name) . '" value="' . e($in[$name] ?? '') . '" placeholder="0" class="' . $input . ' tabular-nums"></div>'
    );
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.payroll.components.tabs')

    @if($error)
        <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-3" role="alert">{{ $error }}</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
        {{-- ───────── Input ───────── --}}
        <form method="GET" action="{{ route('finance.payroll.simulation') }}" data-unsaved-ignore class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4" id="simForm"
              data-snapshot="{{ route('finance.payroll.simulation.snapshot') }}" data-divisor="{{ $settings['fixed_divisor'] }}">
            <input type="hidden" name="run" value="1">
            <h3 class="text-sm font-bold text-gray-900">Simulation input</h3>

            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Employee <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                <select name="employee_id" id="f_employee_id" class="{{ $input }}"><option value="">— Manual input only —</option>
                    @foreach($employees as $e)<option value="{{ $e->employee_id }}" @selected((int) ($in['employee_id'] ?? 0) === (int) $e->employee_id)>{{ trim($e->first_name . ' ' . $e->last_name) }} ({{ $e->eci }}){{ $e->payroll_activated ? '' : ' — payroll off' }}</option>@endforeach
                </select>
                <p class="text-[11px] text-gray-400 mt-1">Choosing an employee fills basic salary, fixed allowance, PTKP, NPWP, BPJS flags and attendance from the master data. You can still change every value.</p>
                <p id="snapStatus" class="text-[11px] text-red-600 mt-1 hidden"></p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Payroll month</label>
                    <select name="month" id="f_month" class="{{ $input }}">@foreach(range(1, 12) as $mo)<option value="{{ $mo }}" @selected((int) $in['month'] === $mo)>{{ \Carbon\Carbon::create(2000, $mo, 1)->format('F') }}</option>@endforeach</select></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Tax year</label>
                    <input type="number" name="year" id="f_year" min="2024" max="2100" value="{{ $in['year'] }}" class="{{ $input }}"></div>
                {!! $money('base_salary', 'Basic salary') !!}
                {!! $money('fixed_allowance', 'Fixed allowance') !!}
                {!! $money('other_taxable', 'Other taxable income') !!}
                {!! $money('other_nontaxable', 'Non-taxable income') !!}
                {!! $money('manual_overtime', 'Manual overtime', 'If auto overtime hours are filled in, the system calculates and this is ignored.') !!}
                {!! $num('overtime_hours', 'Auto overtime hours', '0.25') !!}
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Overtime day type</label>
                    <select name="overtime_day_type" class="{{ $input }}">@foreach(['workday' => 'Workday', 'weekend' => 'Weekend / rest day', 'public_holiday' => 'Public holiday'] as $k => $l)<option value="{{ $k }}" @selected(($in['overtime_day_type'] ?? 'workday') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Overtime workweek</label>
                    <select name="overtime_basis" class="{{ $input }}"><option value="">Follow payroll settings</option>@foreach([5, 6] as $d)<option value="{{ $d }}" @selected((string) ($in['overtime_basis'] ?? '') === (string) $d)>{{ $d }} days</option>@endforeach</select></div>
                {!! $money('reimbursement', 'Reimbursement') !!}
                {!! $money('routine_deduction', 'Routine deduction') !!}
                {!! $money('other_deduction', 'Other deductions') !!}
                {!! $money('loan_installment', 'Loan installment') !!}
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                {!! $num('divisor', 'Workdays / payroll divisor') !!}
                {!! $num('days_present', 'Days present') !!}
                {!! $num('days_sick', 'Sick days', '0.5') !!}
                {!! $num('days_leave', 'Leave days', '0.5') !!}
                {!! $num('days_permit', 'Permit days', '0.5') !!}
                {!! $num('days_absent', 'Absent days', '0.5') !!}
                {!! $num('late_count', 'Late count') !!}
                {!! $num('late_minutes', 'Total late minutes') !!}
            </div>
            <p class="text-[11px] text-gray-400 -mt-2">Absent days (and sick / leave / permit days when those are unpaid in Settings) are deducted at (base + fixed allowance) ÷ divisor. The divisor box overrides the fixed divisor in Settings for this simulation.</p>

            <div class="grid grid-cols-2 gap-3 items-end">
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">PTKP status</label>
                    <select name="ptkp_code" id="f_ptkp_code" class="{{ $input }}">@foreach($ptkpCodes as $c)<option value="{{ $c }}" @selected(($in['ptkp_code'] ?? 'TK/0') === $c)>{{ $c }}</option>@endforeach</select></div>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="has_tax_id" id="f_has_tax_id" value="1" @checked($in['has_tax_id']) class="rounded border-gray-300 text-indigo-600"> Has NPWP / NIK</label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="bpjs_health" id="f_bpjs_health" value="1" @checked($in['bpjs_health']) class="rounded border-gray-300 text-indigo-600"> BPJS Health active</label>
                <label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="bpjs_employment" id="f_bpjs_employment" value="1" @checked($in['bpjs_employment']) class="rounded border-gray-300 text-indigo-600"> BPJS Employment active</label>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="px-5 py-2.5 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-calculator mr-2"></i>Run simulation</button>
                <a href="{{ route('finance.payroll.simulation') }}" data-unsaved-ignore class="px-4 py-2.5 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Reset form</a>
            </div>
        </form>

        {{-- ───────── Result ───────── --}}
        <div class="xl:col-span-3 space-y-4">
            @if(!$r)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center text-sm text-gray-500">
                    <i class="fas fa-flask text-3xl text-gray-300 mb-3"></i>
                    <p>Fill in the form and click <strong>Run simulation</strong>. Nothing is saved.</p>
                </div>
            @else
                @php $s = $r['summary']; $pph = $r['pph21']; $b = $r['bpjs']; $rates = $result['rates']; $cfg = $result['settings']; @endphp
                @if(!($result['attendance_applies'] ?? true))
                    <div class="text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2"><i class="fas fa-circle-info mr-1"></i>Attendance does not affect payroll for this month (switched off in Payroll → Settings, or it applies from a later date), so the absent / late fields were ignored.</div>
                @endif
                @foreach($r['warnings'] as $w)
                    <div class="text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $w }}</div>
                @endforeach

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3"><p class="text-[11px] text-gray-500">Total earnings</p><p class="text-lg font-bold text-green-600 tabular-nums">Rp {{ $m($s['gross_earnings']) }}</p></div>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3"><p class="text-[11px] text-gray-500">Total deductions</p><p class="text-lg font-bold text-red-600 tabular-nums">Rp {{ $m($s['total_deductions']) }}</p></div>
                    <div class="primary-surface rounded-xl shadow-sm px-4 py-3 text-white"><p class="text-[11px] text-white text-opacity-70">Take-home pay</p><p class="text-lg font-bold tabular-nums">Rp {{ $m($s['take_home_pay']) }}</p></div>
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3"><p class="text-[11px] text-gray-500">Company cost</p><p class="text-lg font-bold text-gray-900 tabular-nums">Rp {{ $m($s['employer_cost']) }}</p></div>
                </div>

                @foreach([['Earnings', 'earning', 'Total earnings', $s['gross_earnings']], ['Employee deductions', 'deduction', 'Total employee deductions', $s['total_deductions']], ['Company contributions (information)', 'employer', 'Total company contributions', $s['bpjs_employer']]] as [$title, $type, $totLabel, $totVal])
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-5 py-2.5 border-b border-gray-100 text-xs font-bold uppercase tracking-wide text-gray-500">{{ $title }}</div>
                        <table class="w-full text-sm"><tbody class="divide-y divide-gray-100">
                            @forelse(array_filter($r['items'], fn ($i) => $i['type'] === $type) as $i)<tr><td class="px-5 py-1.5 text-gray-700">{{ $i['name'] }}</td><td class="px-5 py-1.5 text-right tabular-nums">{{ $m($i['amount']) }}</td></tr>
                            @empty<tr><td colspan="2" class="px-5 py-3 text-center text-xs text-gray-400">None</td></tr>@endforelse
                            <tr class="bg-gray-50 font-bold"><td class="px-5 py-2">{{ $totLabel }}</td><td class="px-5 py-2 text-right tabular-nums">{{ $m($totVal) }}</td></tr>
                        </tbody></table>
                    </div>
                @endforeach

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-sm space-y-1.5">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-1">Tax &amp; BPJS summary</h4>
                        <div class="flex justify-between"><span class="text-gray-500">BPJS base</span><span class="tabular-nums">{{ $m($s['bpjs_wage']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">BPJS Health (employee / employer)</span><span class="tabular-nums">{{ $m($b['health']['employee'] + $b['health']['dependents_amount']) }} / {{ $m($b['health']['employer']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">JHT (employee / employer)</span><span class="tabular-nums">{{ $m($b['jht']['employee']) }} / {{ $m($b['jht']['employer']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">JP (employee / employer)</span><span class="tabular-nums">{{ $m($b['jp']['employee']) }} / {{ $m($b['jp']['employer']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">JKK + JKM (employer)</span><span class="tabular-nums">{{ $m($b['jkk']['employer'] + $b['jkm']['employer']) }}</span></div>
                        <div class="flex justify-between border-t border-gray-100 pt-1.5"><span class="text-gray-500">PTKP status</span><span>{{ $in['ptkp_code'] }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">PPh 21 method</span><span>
                            @if($pph['method'] === 'ter')TER {{ $pph['category'] }} · {{ rtrim(rtrim(number_format($pph['rate'], 2, ',', ''), '0'), ',') }}%
                            @elseif($pph['method'] === 'annual_true_up')Annual (final period)
                            @elseif($pph['method'] === 'disabled')Switched off in Settings
                            @else—@endif</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Taxable gross income</span><span class="tabular-nums">{{ $m($pph['tax_gross']) }}</span></div>
                        @if($pph['method'] === 'annual_true_up' && $pph['annual'])
                            <div class="flex justify-between"><span class="text-gray-500">Annual PPh 21 / already withheld</span><span class="tabular-nums">{{ $m($pph['annual']['annual']['tax']) }} / {{ $m($pph['annual']['withheld']) }}</span></div>
                        @endif
                        <div class="flex justify-between font-bold"><span>PPh 21</span><span class="tabular-nums">{{ $m($s['pph21']) }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">NPWP</span><span>{{ $in['has_tax_id'] ? 'Yes' : 'No' }}{{ !empty($pph['surcharge']) ? ' (+20%)' : '' }}</span></div>
                    </div>

                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 text-sm space-y-1.5">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-1">Active payroll assumptions <span class="normal-case font-normal text-gray-400">(from Settings)</span></h4>
                        @foreach([
                            ['Overtime wage basis', 'Base + fixed allowances'],
                            ['Overtime hourly rate', 'Rp ' . $m($s['hourly_rate'])],
                            ['Proration', $cfg['proration_basis'] === 'fixed_divisor' ? 'Fixed divisor (' . $cfg['fixed_divisor'] . ' days)' : 'Calendar'],
                            ['Workdays in the month', $result['workdays'] . ' days (' . $cfg['work_days_per_week'] . '-day week)'],
                            ['Attendance in payroll', $cfg['attendance_enabled'] ? 'On' . ($cfg['attendance_effective_from'] ? ' (from ' . $cfg['attendance_effective_from'] . ')' : '') : 'Off'],
                            ['Absence deduction', $cfg['absence_deduction_enabled'] ? 'On' : 'Off'],
                            ['Late penalty', $cfg['late_penalty_enabled'] ? 'On (grace ' . $cfg['late_grace_minutes'] . ' min, base ' . $cfg['workday_base_minutes'] . ' min)' : 'Off'],
                            ['Paid sick / leave / permit', ($cfg['sick_paid'] ? 'Yes' : 'No') . ' / ' . ($cfg['leave_paid'] ? 'Yes' : 'No') . ' / ' . ($cfg['permit_paid'] ? 'Yes' : 'No')],
                            ['BPJS deduction', $cfg['bpjs_enabled'] ? 'On (setting ' . $rates['bpjs_effective_date'] . ')' : 'Off'],
                            ['PPh 21 deduction', $cfg['pph21_enabled'] ? 'On (year ' . $rates['pph21_year'] . ')' : 'Off'],
                            ['BPJS TK before PPh 21', $cfg['deduct_bpjs_tk_before_pph21'] ? 'Yes' : 'No'],
                        ] as [$label, $val])
                            <div class="flex justify-between gap-3"><span class="text-gray-500">{{ $label }}</span><span class="text-right">{{ $val }}</span></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('simForm');
    if (!form) { return; }
    const $ = (id) => document.getElementById(id);
    const num = (n) => Number(n).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const set = (id, v) => { const el = $(id); if (el) { el.value = v; } };

    async function fill() {
        const status = $('snapStatus'); status.classList.add('hidden');
        const emp = $('f_employee_id').value;
        if (!emp) { return; }
        const q = new URLSearchParams({ employee_id: emp, month: $('f_month').value, year: $('f_year').value });
        const res = await fetch(form.dataset.snapshot + '?' + q, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        let json = null; try { json = await res.json(); } catch (e) { /* bukan JSON */ }
        if (!res.ok || !json?.success) { status.textContent = json?.message || 'Could not load the employee data.'; status.classList.remove('hidden'); return; }
        const d = json.data, a = d.attendance || {};
        set('f_base_salary', d.base ? num(d.base) : ''); set('f_fixed_allowance', d.fixed ? num(d.fixed) : '');
        if (d.ptkp_code) { set('f_ptkp_code', d.ptkp_code); }
        $('f_has_tax_id').checked = !!d.has_tax_id; $('f_bpjs_health').checked = !!d.bpjs_health_active; $('f_bpjs_employment').checked = !!d.bpjs_employment_active;
        set('f_divisor', form.dataset.divisor);
        if (a.available) {
            set('f_days_present', a.present); set('f_days_sick', a.sick); set('f_days_leave', a.leave); set('f_days_permit', a.permit);
            set('f_days_absent', a.absent); set('f_late_count', a.late_days); set('f_late_minutes', a.late_minutes);
        } else {
            ['days_present', 'days_sick', 'days_leave', 'days_permit', 'days_absent', 'late_count', 'late_minutes'].forEach(k => set('f_' + k, ''));
            status.textContent = 'No attendance records for this employee in that month — attendance fields left empty.';
            status.classList.remove('hidden');
        }
    }
    $('f_employee_id').addEventListener('change', fill);
    $('f_month').addEventListener('change', fill);
    $('f_year').addEventListener('change', fill);
})();
</script>
@endpush
