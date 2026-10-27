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
    {{-- Legal basis of the rules on this page (Register Dasar Hukum; read-only here) --}}
    @include('partials.legal-basis-panel', ['legalModule' => 'overtime'])
    @include('partials.legal-basis-panel', ['legalModule' => 'contract'])
    @include('hr-general.recruitment.components.form-errors')

    <div class="lg:grid lg:grid-cols-[14.5rem_minmax(0,1fr)] lg:gap-6 items-start space-y-4 lg:space-y-0">
    @include('finance.payroll.components.settings-nav')
    <div class="space-y-6 min-w-0">

    {{-- ───────── Module status (separate permission) ───────── --}}
    <div id="payroll-module" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-bold text-gray-900">Payroll module</h3>
                    <span class="inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full {{ $enabled ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $enabled ? 'Switched on' : 'Switched off' }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-1 max-w-2xl">While off, payroll periods cannot be created, calculated or locked (Settings and Simulation still work). This is the safety switch for go-live; only people with the “Switch payroll on/off” permission see the button.
                    @if($enabledAt) Last changed {{ $enabledAt->format('d M Y H:i') }}{{ $enabledBy ? ' by ' . $enabledBy : '' }}. @endif</p>
            </div>
            @if($can('finance.payroll.activate'))
                @if($enabled)
                    <form method="POST" action="{{ route('finance.payroll.activation') }}" data-confirm="Existing periods and payslips are kept, but nobody can create, calculate or lock periods until it is switched on again." data-confirm-title="Switch payroll off?" data-confirm-ok="Switch off" data-confirm-variant="danger">@csrf
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
        <div id="work-schedule" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
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
        <div id="attendance" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
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
        <div id="earnings" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">Earnings</h3>
            <div>{!! $sw('overtime_enabled', 'Enable overtime calculation', 'Approved overtime requests in the period are paid at PP 35/2021 multipliers on 1/173 of (base salary + fixed allowances).') !!}</div>
            <div>{!! $sw('include_reimbursement', 'Include reimbursement in payroll', 'Reimbursements approved inside the period (IDR) are added as non-taxable earnings, once only.') !!}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Overtime wage basis</label>
                    <select name="overtime_wage_basis" @disabled($dis) class="{{ $input }}">
                        <option value="base_fixed" @selected($v('overtime_wage_basis') !== 'base_only')>Basic salary + fixed allowance</option>
                        <option value="base_only" @selected($v('overtime_wage_basis') === 'base_only')>Basic salary only</option></select>
                    <p class="text-xs text-gray-500 mt-1">Whether fixed allowances are included in the 1/173 overtime wage base.</p></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Minimum overtime minutes</label>
                    <select name="overtime_min_minutes" @disabled($dis) class="{{ $input }}">@foreach([0, 15, 30, 45, 60] as $m)<option value="{{ $m }}" @selected((int) $v('overtime_min_minutes') === $m)>{{ $m }} minutes</option>@endforeach</select>
                    <p class="text-xs text-gray-500 mt-1">An overtime request shorter than this is not paid.</p></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Overtime minute rounding</label>
                    <select name="overtime_round_minutes" @disabled($dis) class="{{ $input }}"><option value="0" @selected((int) $v('overtime_round_minutes') === 0)>No rounding</option>@foreach([5, 10, 15, 30, 60] as $m)<option value="{{ $m }}" @selected((int) $v('overtime_round_minutes') === $m)>To {{ $m }} minutes</option>@endforeach</select></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Overtime rounding method</label>
                    <select name="overtime_round_method" @disabled($dis) class="{{ $input }}">@foreach(['nearest' => 'Nearest', 'up' => 'Round up', 'down' => 'Round down'] as $k => $l)<option value="{{ $k }}" @selected($v('overtime_round_method') === $k)>{{ $l }}</option>@endforeach</select></div>
            </div>
        </div>

        {{-- BPJS & tax --}}
        <div id="bpjs-tax" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">BPJS &amp; PPh 21</h3>
            <div>{!! $sw('bpjs_enabled', 'Enable BPJS deduction', 'Rates, caps and effective dates are in BPJS → Settings; each employee’s BPJS flags in Master → Employee → Compensation.') !!}</div>
            <div>{!! $sw('pph21_enabled', 'Enable PPh 21 deduction', 'Rates and PTKP are in PPh 21 → Settings.') !!}</div>
            <div>{!! $sw('deduct_bpjs_tk_before_pph21', 'Deduct BPJS TK before PPh 21 calculation', 'Takes the employee’s JHT and JP off the monthly TER base (BPJS Health excluded). Not the PMK 168/2023 default — TER normally applies to gross income. Leave off unless Accounting asks for it.') !!}</div>
            <div>{!! $sw('apply_no_npwp_surcharge', 'Add 20% to PPh 21 when the employee has no NPWP/NIK', 'Based on whether an NPWP number is on file in Master → Employee → Identification.') !!}</div>
            <div class="md:w-1/2"><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Monthly TER tax-base rounding</label>
                <select name="ter_base_rounding" @disabled($dis) class="{{ $input }}">@foreach(\App\Support\Payroll\PayrollRounding::MODES as $k => $l)<option value="{{ $k }}" @selected($v('ter_base_rounding') === $k)>{{ $l }}{{ $k === 'none' ? ' (recommended)' : ' to Rp 1.000' }}</option>@endforeach</select>
                <p class="text-xs text-gray-500 mt-1">Applies only to the January–November monthly TER base, after the BPJS TK deduction when enabled. Example: Rp 9.687.800 stays as is with no rounding, or becomes Rp 9.687.000 when rounded down. The December annual calculation is not affected.</p></div>
        </div>

        {{-- Net pay rounding --}}
        <div id="net-rounding" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-gray-900">Net pay rounding</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Net pay rounding</label>
                    <select name="net_rounding_mode" @disabled($dis) class="{{ $input }}">@foreach(\App\Support\Payroll\PayrollRounding::MODES as $k => $l)<option value="{{ $k }}" @selected($v('net_rounding_mode') === $k)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Rounding increment</label>
                    <select name="net_rounding_increment" @disabled($dis) class="{{ $input }}">@foreach([100, 500, 1000, 5000] as $n)<option value="{{ $n }}" @selected((int) $v('net_rounding_increment') === $n)>{{ $n }}</option>@endforeach</select></div>
            </div>
            <p class="text-xs text-gray-500">The rounding difference is recorded as its own “Net pay rounding” line on the payslip, so the totals stay auditable.</p>
        </div>

        {{-- Cut-off, timesheet, loans --}}
        <div id="cutoff-loans" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
            <div><h3 class="text-sm font-bold text-gray-900">Cut-off, timesheet &amp; loans</h3>
                <p class="text-xs text-gray-500 mt-0.5">Switches the people in charge can turn on or off. Items marked <span class="font-semibold">not connected yet</span> are saved and ready, but have no effect on a payslip until their source module is linked.</p></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                <div>{!! $sw('cutoff_enabled', 'Enable payroll processing cut-off', 'New periods get a default cut-off date. Workdays after the cut-off are treated as fully present and corrected in the next payroll (Payroll corrections).') !!}</div>
                <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Payroll processing cut-off date (day of month)</label>
                    <input type="number" min="1" max="31" name="cutoff_day" value="{{ $v('cutoff_day') }}" @disabled($dis) class="{{ $input }}">
                    <p class="text-xs text-gray-500 mt-1">Months shorter than this use their last day. You can still change the cut-off on each period.</p></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>{!! $sw('timesheet_allowance_enabled', 'Include timesheet and project allowances in payroll', 'Not connected yet — switch for the future Timesheet → Payroll link. Off by default.') !!}</div>
                <div>{!! $sw('tax_timesheet_allowance', 'Tax project and timesheet allowances', 'When on, those allowances will count as monthly TER gross income. Not connected yet.') !!}</div>
                <div>{!! $sw('loan_deduction_enabled', 'Enable loan installment deduction', 'Not connected yet — will take loan / cash advance installments off take-home pay once a source is linked.') !!}</div>
            </div>
            <div><label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Internal payroll notes</label>
                <textarea name="internal_notes" rows="2" maxlength="2000" @disabled($dis) placeholder="e.g. This month’s payroll follows a 10-minute late tolerance." class="{{ $input }}">{{ $v('internal_notes') }}</textarea></div>
            <p class="text-xs text-gray-500"><i class="fas fa-lightbulb mr-1"></i>Tip: choose Calendar proration if payroll must follow the exact working days of each month, or Fixed divisor if your company uses a constant monthly policy.</p>
        </div>

        {{-- Approvals & background --}}
        <div id="data-readiness" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
            <div><h3 class="text-sm font-bold text-gray-900">Data readiness &amp; background processing</h3>
                <p class="text-xs text-gray-500 mt-0.5">Payroll only counts approved overtime, leave, attendance corrections and reimbursements. These switches help Finance keep the order: approvals first, payroll after.</p></div>
            <div>{!! $sw('block_on_pending_inputs', 'Require approvals before calculating', 'When on, Calculate is refused while overtime, leave, attendance corrections or reimbursements of the period are still waiting for approval. When off (default), the period page only shows how many are waiting.') !!}</div>
            <div>@if(\App\Support\SchemaCache::hasColumn('payroll_settings', 'auto_publish_slips')){!! $sw('auto_publish_slips', 'Publish payslips automatically after calculating', 'When on, finishing “Calculate” also numbers the payslips (Generate Slip) and publishes them, so each employee sees the payslip in ESS → Paystub right away. When off (default), Finance presses Generate Slip and Publish Payslip by hand after reviewing the numbers.') !!}@else<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"><i class="fas fa-triangle-exclamation mr-1"></i> “Publish payslips automatically” needs a database update first (column <code>payroll_settings.auto_publish_slips</code>).</p>@endif</div>
            <div>{!! $sw('calculate_in_background', 'Calculate periods in the background', 'Runs “Calculate” as a background job so a large payroll cannot time out the page. Needs a queue worker (php artisan queue:work) on the server — leave OFF if you are not sure one is running; without a worker the period would stay “queued”. Currently: queue = ' . config('queue.default') . (config('queue.default') === 'sync' ? ' (background is unavailable, calculations run directly)' : '') . '.') !!}</div>
        </div>

        {{-- Queue check --}}
        <div class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3" id="queueCard">
            <div><h3 class="text-sm font-bold text-gray-900">Background processing check</h3>
                <p class="text-xs text-gray-500 mt-0.5">A “queue worker” is a small program on the server that picks up background jobs. Run the test to find out whether one is alive before you switch on “Calculate periods in the background”.</p></div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" id="qtBtn" @disabled($dis) class="px-3.5 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50"><i class="fas fa-vial mr-2"></i>Run worker test</button>
                <span id="qtResult" class="text-sm text-gray-600">Not tested yet.</span>
            </div>
            <p id="qtInfo" class="text-xs text-gray-500"></p>
        </div>
        <script>
        (function () {
            const btn = document.getElementById('qtBtn'), res = document.getElementById('qtResult'), info = document.getElementById('qtInfo');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const get = () => fetch(@json(route('finance.payroll.settings.queue-status')), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(r => r.json());
            const paint = (s) => {
                info.textContent = 'Queue driver: ' + s.driver + (s.jobs_table !== null ? ' · jobs waiting: ' + s.jobs_table : '') + (s.failed !== null ? ' · failed jobs: ' + s.failed : '');
                if (s.test.state === 'ok') res.innerHTML = s.driver === 'sync'
                    ? '<span class="text-amber-700 font-semibold"><i class="fas fa-circle-info mr-1"></i>Queue is “sync”: jobs run immediately, so no worker is needed — and background calculation is not available.</span>'
                    : '<span class="text-green-700 font-semibold"><i class="fas fa-circle-check mr-1"></i>A worker is alive — it answered in ' + s.test.seconds + ' s.</span>';
                else if (s.test.state === 'waiting') res.innerHTML = '<span class="text-amber-700"><i class="fas fa-spinner fa-spin mr-1"></i>Waiting for a worker… ' + s.test.seconds + ' s' + (s.test.seconds > 20 ? ' — no answer yet. Probably no worker is running; keep the background switch OFF.' : '') + '</span>';
                return s.test.state === 'waiting';
            };
            const poll = (n) => get().then(s => { if (paint(s) && n < 40) setTimeout(() => poll(n + 1), 2000); });
            get().then(paint);
            btn.addEventListener('click', () => {
                btn.disabled = true; res.textContent = 'Sending the test…';
                fetch(@json(route('finance.payroll.settings.queue-test')), { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin' })
                    .then(() => { btn.disabled = false; poll(0); }).catch(() => { btn.disabled = false; res.textContent = 'Could not send the test.'; });
            });
        })();
        </script>

        {{-- Payslip --}}
        <div class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5" id="signerCard">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Payslip</h3>
                <p class="text-xs text-gray-500 mt-0.5">Choose the signer by <strong>role first</strong>, then the <strong>user</strong> who holds it. The title is picked from the position list and follows the user’s position by default.</p>
            </div>
            @foreach(['hr' => 'HR', 'finance' => 'Finance'] as $who => $whoLabel)
                @php
                    $legacy = $v($who . '_signer_name') && !$v($who . '_signer_employee_id');
                @endphp
                <div class="rounded-lg border border-gray-200 p-4 space-y-3" data-signer="{{ $who }}">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ $whoLabel }} signer</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Role</label>
                            <select name="{{ $who }}_signer_role_id" data-searchable="true" data-search-placeholder="Search role…" data-signer-role @disabled($dis) class="{{ $input }}">
                                <option value="">— Select role —</option>
                                @foreach($signerRoles as $ro)<option value="{{ $ro['id'] }}" @selected((int) $v($who . '_signer_role_id') === $ro['id'])>{{ $ro['name'] }}</option>@endforeach
                            </select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">User</label>
                            <select name="{{ $who }}_signer_employee_id" data-searchable="true" data-search-placeholder="Search name or employee ID…" data-signer-user data-selected="{{ (int) $v($who . '_signer_employee_id') }}" @disabled($dis) class="{{ $input }}">
                                <option value="">— Select user —</option>
                            </select></div>
                        <div><label class="block text-xs font-semibold text-gray-600 mb-1">Title</label>
                            <select name="{{ $who }}_signer_title" data-searchable="true" data-search-placeholder="Search title…" data-signer-title data-selected="{{ $v($who . '_signer_title') }}" @disabled($dis) class="{{ $input }}">
                                <option value="">— Select title —</option>
                                @foreach($signerTitles as $t)<option value="{{ $t }}" @selected($v($who . '_signer_title') === $t)>{{ $t }}</option>@endforeach
                                @if($v($who . '_signer_title') && !in_array($v($who . '_signer_title'), $signerTitles, true))<option value="{{ $v($who . '_signer_title') }}" selected>{{ $v($who . '_signer_title') }}</option>@endif
                            </select></div>
                    </div>
                    @if($legacy)
                        <div class="flex items-center justify-between gap-3 text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-3 py-2" data-signer-legacy>
                            <span><i class="fas fa-circle-info mr-1"></i>Currently typed by hand: <strong>{{ $v($who . '_signer_name') }}</strong>@if($v($who . '_signer_title')) — {{ $v($who . '_signer_title') }}@endif. Pick a role and user to replace it.</span>
                            <label class="flex items-center gap-1.5 font-semibold shrink-0"><input type="checkbox" name="{{ $who }}_signer_keep" value="1" checked @disabled($dis) class="rounded border-gray-300"> Keep</label>
                        </div>
                    @endif
                </div>
            @endforeach
            <div class="md:w-1/2"><label class="block text-xs font-semibold text-gray-600 mb-1">Default payslip language</label>
                <select name="slip_language" @disabled($dis) class="{{ $input }}"><option value="id" @selected($v('slip_language') !== 'en')>Indonesian</option><option value="en" @selected($v('slip_language') === 'en')>English</option></select>
                <p class="text-xs text-gray-500 mt-1">The language can still be chosen each time a payslip is opened.</p></div>
        </div>
        <script>
        (function () {
            const PEOPLE = @json($signerPeople);
            document.querySelectorAll('[data-signer]').forEach(function (box) {
                const role = box.querySelector('[data-signer-role]'), user = box.querySelector('[data-signer-user]'), title = box.querySelector('[data-signer-title]');
                const ensureTitle = function (t) {
                    if (!t) return;
                    if (!Array.from(title.options).some(function (o) { return o.value === t; })) { const o = document.createElement('option'); o.value = t; o.textContent = t; title.appendChild(o); }
                };
                const fillUsers = function (selected) {
                    const rid = parseInt(role.value || '0', 10);
                    user.innerHTML = '';
                    const blank = document.createElement('option'); blank.value = ''; blank.textContent = rid ? '— Select user —' : '— Select a role first —'; user.appendChild(blank);
                    PEOPLE.filter(function (p) { return rid && p.roles.indexOf(rid) !== -1; }).forEach(function (p) {
                        const o = document.createElement('option'); o.value = p.id; o.textContent = p.name + ' (' + p.eci + ')'; user.appendChild(o);
                    });
                    user.value = selected ? String(selected) : '';
                    user.dispatchEvent(new Event('change', { bubbles: true }));
                };
                role.addEventListener('change', function () { fillUsers(null); });
                user.addEventListener('change', function () {
                    const p = PEOPLE.find(function (x) { return String(x.id) === user.value; });
                    if (p && p.position && !title.value) { ensureTitle(p.position); title.value = p.position; title.dispatchEvent(new Event('change', { bubbles: true })); }
                    const legacy = box.querySelector('[data-signer-legacy]'); if (legacy && user.value) legacy.classList.add('hidden');
                });
                fillUsers(parseInt(user.dataset.selected || '0', 10) || null);
            });
        })();
        </script>
        @if($canEdit)
            <div><button type="submit" class="px-5 py-2.5 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-floppy-disk mr-2"></i>Save payroll settings</button></div>
        @else
            <p class="text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> View only — you do not have permission to change these settings.</p>
        @endif
    </form>

    <div id="other-rules" class="pst-section bg-white rounded-xl border border-gray-200 shadow-sm p-5 max-w-3xl text-sm text-gray-600 space-y-1">
        <h3 class="text-sm font-bold text-gray-900 mb-1">Where the other rules live</h3>
        <p>• BPJS rates and caps → <a class="text-indigo-700 hover:underline" href="{{ route('finance.bpjs.index') }}">BPJS</a></p>
        <p>• PTKP, progressive and TER rates, employer-premium treatment → <a class="text-indigo-700 hover:underline" href="{{ route('finance.pph21.index') }}">PPh 21</a></p>
        <p>• Salary components, PTKP status, BPJS flags and “include in payroll” per employee → Master → Employee → <strong>Compensation</strong> tab</p>
    </div>

    </div>{{-- /sections --}}
    </div>{{-- /sidebar + sections --}}
</div>
@endsection
