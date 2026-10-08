@extends('dashboard')
@section('title', 'Payroll Settings')
@section('page-title', 'Payroll Settings')
@section('page-subtitle', 'Configure Indonesian payroll policy: work schedule, proration, late penalty, absence deduction, overtime, BPJS and PPh 21. Changes apply the next time a period is calculated.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white disabled:bg-gray-50 disabled:text-gray-500';
    $s = $settings;
    $v = fn (string $k) => old('_token') ? old($k) : $s[$k];          // setelah gagal validasi: nilai yang diketik
    $dis = !$canEdit;
@endphp

@push('styles')
<style>
    /* Switch: mengikuti Accent colour (--primary-rgb) seperti sidebar. */
    .sw { position: relative; display: inline-flex; flex-shrink: 0; margin-top: 2px; }
    .sw input { position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; z-index: 1; }
    .sw .sw-track { width: 2.5rem; height: 1.4rem; border-radius: 999px; background: #d1d5db; transition: background-color .15s; }
    .sw .sw-track::after { content: ''; position: absolute; top: 2px; left: 2px; width: 1.1rem; height: 1.1rem; border-radius: 999px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.3); transition: transform .15s; }
    .sw input:checked + .sw-track { background: rgb(var(--primary-rgb)); }
    .sw input:checked + .sw-track::after { transform: translateX(1.1rem); }
    .sw input:disabled { cursor: not-allowed; }
    .sw input:disabled + .sw-track { opacity: .55; }
    .sw input:focus-visible + .sw-track { outline: 2px solid rgba(var(--primary-rgb), .5); outline-offset: 2px; }
</style>
@endpush

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('finance.payroll.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- ───────── Module status (separate permission) ───────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-gray-900">Payroll module</h3>
                    <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $enabled ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $enabled ? 'Switched on' : 'Switched off' }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1 max-w-2xl">While off, payroll periods cannot be created, calculated or approved (Settings and Simulation still work). This is the safety switch for go-live; only people with the “Switch payroll on/off” permission see the button.
                    @if($enabledAt) Last changed {{ $enabledAt->format('d M Y H:i') }}{{ $enabledBy ? ' by ' . $enabledBy : '' }}. @endif</p>
            </div>
            @if($can('finance.payroll.activate'))
                @if($enabled)
                    <form method="POST" action="{{ route('finance.payroll.activation') }}" onsubmit="return confirm('Switch payroll off? Existing periods and payslips are kept.')">@csrf
                        <input type="hidden" name="state" value="off">
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg border border-red-300 text-red-700 hover:bg-red-50"><i class="fas fa-power-off mr-2"></i>Switch off</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('finance.payroll.activation') }}" class="space-y-2 max-w-sm">@csrf
                        <input type="hidden" name="state" value="on">
                        <label class="flex items-start gap-2 text-xs text-gray-700"><input type="checkbox" name="confirm" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600">
                            <span>I have checked that salary components, PTKP, BPJS rates and PPh 21 tables are correct, and I want to start running payroll.</span></label>
                        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-power-off mr-2"></i>Switch payroll on</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    {{-- ───────── Policy ───────── --}}
    <form method="POST" action="{{ route('finance.payroll.settings.save') }}" class="space-y-6">
        @csrf

        @php
            // [name, label, help] — satu baris saklar
            $sw = function (string $name, string $label, string $help = '') use ($v, $dis) {
                $checked = (bool) $v($name) ? 'checked' : '';
                $d = $dis ? 'disabled' : '';
                $helpHtml = $help !== '' ? '<span class="block text-xs text-gray-500 mt-0.5">' . e($help) . '</span>' : '';

                return new \Illuminate\Support\HtmlString(
                    '<label class="flex items-start gap-3 text-sm text-gray-700"><span class="sw"><input type="checkbox" name="' . e($name) . '" value="1" ' . $checked . ' ' . $d . '><span class="sw-track"></span></span>'
                    . '<span><span class="font-semibold text-gray-900">' . e($label) . '</span>' . $helpHtml . '</span></label>'
                );
            };
        @endphp

        {{-- Work schedule, proration --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
            <h3 class="text-sm font-bold text-gray-900">Work schedule &amp; proration</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Weekly work schedule</label>
                    <select name="work_days_per_week" @disabled($dis) class="{{ $input }}">@foreach([5, 6] as $d)<option value="{{ $d }}" @selected((int) $v('work_days_per_week') === $d)>{{ $d }} days (Mon–{{ $d === 5 ? 'Fri' : 'Sat' }})</option>@endforeach</select>
                    <p class="text-xs text-gray-500 mt-1">Decides which days count as workdays (holidays from Management → Holidays are excluded).</p>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Overtime workweek basis</label>
                    <select name="overtime_work_days" @disabled($dis) class="{{ $input }}">
                        <option value="" @selected($v('overtime_work_days') === null || $v('overtime_work_days') === '')>Follow payroll work schedule</option>
                        @foreach([5, 6] as $d)<option value="{{ $d }}" @selected((string) $v('overtime_work_days') === (string) $d)>{{ $d }} days</option>@endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Use when the overtime policy follows a different week than regular payroll (moves the rest-day multiplier bands).</p>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Final tax month</label>
                    <select name="final_tax_month" @disabled($dis) class="{{ $input }}">@foreach(range(1, 12) as $mo)<option value="{{ $mo }}" @selected((int) $v('final_tax_month') === $mo)>{{ \Carbon\Carbon::create(2000, $mo, 1)->format('F') }}</option>@endforeach</select>
                    <p class="text-xs text-gray-500 mt-1">The period ending in this month uses the annual PPh 21 calculation (usually December).</p>
                </div>
            </div>
            <div>{!! $sw('prorate_partial_period', 'Prorate components that cover only part of the period', 'New joiner, leaver or mid-period raise: each component is paid for the days it is effective. Turn off to pay every component in full. BPJS always uses the full monthly wage.') !!}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Proration basis</label>
                    <select name="proration_basis" @disabled($dis) class="{{ $input }}">
                        <option value="fixed_divisor" @selected($v('proration_basis') === 'fixed_divisor')>Fixed divisor</option>
                        <option value="calendar" @selected($v('proration_basis') === 'calendar')>Calendar</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Fixed divisor: paid workdays ÷ the divisor below (a full period is always 100%). Calendar: covered calendar days ÷ days in the period; daily rate = wage ÷ workdays in the period.</p>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Fixed payroll divisor</label>
                    <select name="fixed_divisor" @disabled($dis) class="{{ $input }}">@foreach([21, 22, 25, 26] as $d)<option value="{{ $d }}" @selected((int) $v('fixed_divisor') === $d)>{{ $d }} days</option>@endforeach</select>
                    <p class="text-xs text-gray-500 mt-1">Common practice: 21–22 days for a 5-day week, 25–26 days for a 6-day week. Also the divisor of the daily absence rate and the per-minute late penalty.</p>
                </div>
            </div>
        </div>

        {{-- Attendance --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Attendance deductions</h3>
                <p class="text-xs text-gray-500 mt-0.5">Read from Attendance and Leave &amp; Permit. Absence is derived (workdays − days with attendance − approved leave); an employee with no attendance record at all in the period is never deducted.</p>
            </div>
            {{-- Saklar INDUK: mematikan seluruh pengaruh absensi (potongan alpa, denda terlambat) tanpa menghapus pengaturan di bawahnya. --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                    <div>{!! $sw('attendance_enabled', 'Use attendance data in payroll', 'Master switch. While off, absence deduction and late penalty are never calculated, whatever is set below — use it during UAT / soft go-live when attendance is not reliable yet.') !!}</div>
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Attendance applies from <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                        <input type="date" name="attendance_effective_from" value="{{ $v('attendance_effective_from') }}" @disabled($dis) class="{{ $input }}">
                        <p class="text-xs text-gray-500 mt-1">Only periods that START on or after this date use attendance. Leave empty to apply to every period. Payslips already calculated are not changed until recalculated.</p>
                    </div>
                </div>
                @unless($s['attendance_enabled'])
                    <p class="text-xs text-amber-800"><i class="fas fa-circle-info mr-1"></i>Attendance is currently <strong>off</strong>: the options below are saved but have no effect on payroll.</p>
                @endunless
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                <div>{!! $sw('late_penalty_enabled', 'Enable late penalty', 'Deducts a per-minute amount for lateness beyond the grace period.') !!}</div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Grace period (minutes)</label>
                    <input type="number" min="0" max="240" name="late_grace_minutes" value="{{ $v('late_grace_minutes') }}" @disabled($dis) class="{{ $input }}">
                    <p class="text-xs text-gray-500 mt-1">Per late day, on top of the shift's own tolerance.</p>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Workday base (minutes)</label>
                    <input type="number" min="60" max="720" name="workday_base_minutes" value="{{ $v('workday_base_minutes') }}" @disabled($dis) class="{{ $input }}">
                    <p class="text-xs text-gray-500 mt-1">Penalty per minute = (base + fixed allowances) ÷ (divisor × this).</p>
                </div>
            </div>
            <div>{!! $sw('absence_deduction_enabled', 'Enable absence deduction', 'Deducts (base + fixed allowances) ÷ divisor for each unexcused absent workday and each unpaid leave day.') !!}</div>
            <div>
                <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Paid days</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {!! $sw('sick_paid', 'Sick days are paid') !!}
                    {!! $sw('leave_paid', 'Leave days are paid') !!}
                    {!! $sw('permit_paid', 'Permit days are paid') !!}
                </div>
                <p class="text-xs text-gray-500 mt-1.5">A leave type marked “unpaid” in Leave &amp; Permit is always deducted, whatever is set here.</p>
            </div>
        </div>

        {{-- Earnings --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">Earnings</h3>
            <div>{!! $sw('overtime_enabled', 'Enable overtime calculation', 'Approved overtime requests in the period are paid at PP 35/2021 multipliers on 1/173 of (base salary + fixed allowances).') !!}</div>
            <div>{!! $sw('include_reimbursement', 'Include reimbursement in payroll', 'Reimbursements approved inside the period (IDR) are added as non-taxable earnings, once only.') !!}</div>
        </div>

        {{-- BPJS & tax --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">BPJS &amp; PPh 21</h3>
            <div>{!! $sw('bpjs_enabled', 'Enable BPJS deduction', 'Rates, caps and effective dates are in BPJS → Settings; each employee’s BPJS flags in Master → Employee → Compensation.') !!}</div>
            <div>{!! $sw('pph21_enabled', 'Enable PPh 21 deduction', 'Rates and PTKP are in PPh 21 → Settings.') !!}</div>
            <div>{!! $sw('deduct_bpjs_tk_before_pph21', 'Deduct BPJS TK before PPh 21 calculation', 'Takes the employee’s JHT and JP off the monthly TER base (BPJS Health excluded). Not the PMK 168/2023 default — TER normally applies to gross income. Leave off unless Accounting asks for it.') !!}</div>
            <div>{!! $sw('apply_no_npwp_surcharge', 'Add 20% to PPh 21 when the employee has no NPWP/NIK', 'Based on whether an NPWP number is on file in Master → Employee → Identification.') !!}</div>
        </div>

        @if($canEdit)
            <div><button type="submit" class="px-5 py-2.5 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-floppy-disk mr-2"></i>Save payroll settings</button></div>
        @else
            <p class="text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> View only — you do not have permission to change these settings.</p>
        @endif
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 max-w-3xl text-sm text-gray-600 space-y-1">
        <h3 class="text-sm font-bold text-gray-900 mb-1">Where the other rules live</h3>
        <p>• BPJS rates and caps → <a class="text-indigo-700 hover:underline" href="{{ route('finance.bpjs.index') }}">BPJS</a></p>
        <p>• PTKP, progressive and TER rates, employer-premium treatment → <a class="text-indigo-700 hover:underline" href="{{ route('finance.pph21.index') }}">PPh 21</a></p>
        <p>• Salary components, PTKP status, BPJS flags and “include in payroll” per employee → Master → Employee → <strong>Compensation</strong> tab</p>
    </div>
</div>
@endsection
