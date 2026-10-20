@extends('dashboard')
@section('title', $o->name . ' — Offboarding')
@section('page-title', 'Offboarding')
@section('page-subtitle', 'Checklist and final settlement of one person leaving.')

@php
    use App\Support\Contracts\ContractRules;
    use App\Support\Offboarding\OffboardingSettlementRules as R;

    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white disabled:bg-gray-50 disabled:text-gray-500';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $statusBadge = ['in_progress' => 'bg-blue-100 text-blue-700', 'completed' => 'bg-green-100 text-green-700', 'cancelled' => 'bg-gray-200 text-gray-500'];
    $statusLabel = ['in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    $external = $o->employee_type === 'External';
    $s = $o->settlement;
    $res = $s?->result_data;
    $effective = \Illuminate\Support\Carbon::parse($o->effective_date);
    $due = $effective->lte(now()->endOfDay());
    $money = fn ($v) => ContractRules::money((float) $v);
    $old = fn ($k, $default = '') => old($k, $s->$k ?? $default);
    $deactivated = $o->access_deactivated_at !== null;
    $steps = [
        ['Checklist', $o->done >= $o->total && $o->total > 0, $o->done . '/' . $o->total . ' tasks'],
        ['Settlement', $external ? null : ($s !== null), $external ? 'Not applicable' : ($s ? 'Calculated' : 'Not calculated')],
        ['Access deactivated', $deactivated, $deactivated ? \Illuminate\Support\Carbon::parse($o->access_deactivated_at)->format('d M Y') : 'On ' . $effective->format('d M Y')],
        ['Completed', $o->status === 'completed', $o->status === 'completed' ? 'Contract closed' : 'Closes the contract'],
    ];
    $line = fn ($label, $value, $hint = null, $strong = false) => '<div class="rounded-lg border ' . ($strong ? 'border-green-200 bg-green-50' : 'border-gray-200') . ' px-4 py-3"><p class="text-[11px] text-gray-500">' . e($label) . '</p><p class="text-lg font-bold ' . ($strong ? 'text-green-700' : 'text-gray-800') . ' leading-tight mt-0.5">' . e($value) . '</p>' . ($hint ? '<p class="text-[10px] text-gray-400 mt-0.5">' . e($hint) . '</p>' : '') . '</div>';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.offboarding.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif
    @if(session('warning'))<div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-triangle-exclamation mr-1"></i> {{ session('warning') }}</div>@endif
    @unless($enabled)<div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3"><i class="fas fa-power-off mr-1"></i> Offboarding is switched off, so this record is read-only until it is switched on again.</div>@endunless

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
            <p class="text-[11px] text-gray-400"><a href="{{ route('general.offboarding.index') }}" class="hover:underline">← Offboarding</a></p>
            <h2 class="text-lg font-bold text-gray-800 leading-tight">{{ $o->name }}
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold align-middle {{ $statusBadge[$o->status] }}">{{ $statusLabel[$o->status] }}</span></h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ $o->eci }} • {{ $o->position ?: '-' }}@if($o->department) • {{ $o->department }}@endif
                <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $external ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600' }}">{{ $o->employee_type }}</span></p>
            <p class="text-xs text-gray-500 mt-1"><strong class="text-gray-700">{{ $o->type_label }}</strong> · effective <strong class="text-gray-700">{{ $effective->format('d M Y') }}</strong>@if($o->last_working_day) · last working day {{ \Illuminate\Support\Carbon::parse($o->last_working_day)->format('d M Y') }}@endif</p>
        </div>
        @if($canEdit || $canCancel)
            <div class="flex flex-wrap gap-2">
                @if($canEdit && !$deactivated)
                    <form method="POST" action="{{ route('general.offboarding.deactivate', $o->id) }}" data-confirm="Deactivate the access of {{ $o->name }}? They can no longer log in. Roles and history are kept." data-confirm-title="Deactivate Access" data-confirm-ok="Deactivate" data-unsaved-ignore>@csrf
                        <button @disabled(!$due) title="{{ $due ? '' : 'Available on the effective date, ' . $effective->format('d M Y') }}" class="px-3 py-2 rounded-lg border border-red-300 text-red-600 text-xs font-semibold hover:bg-red-50 disabled:opacity-40 disabled:cursor-not-allowed"><i class="fas fa-user-lock mr-1"></i> Deactivate access</button></form>
                @endif
                @if($canEdit)
                    <form method="POST" action="{{ route('general.offboarding.complete', $o->id) }}" data-confirm="Complete this offboarding? The running contract is closed as Terminated." data-confirm-title="Complete Offboarding" data-confirm-ok="Complete" data-confirm-variant="primary" data-unsaved-ignore>@csrf
                        <button class="px-4 py-2 rounded-lg primary-gradient text-white text-xs font-semibold hover:opacity-90 shadow-sm"><i class="fas fa-flag-checkered mr-1"></i> Complete</button></form>
                @endif
                @if($canCancel && !$deactivated)
                    <button type="button" onclick="document.getElementById('cancelModal').style.display='flex'" class="px-3 py-2 rounded-lg border border-gray-300 text-gray-600 text-xs font-semibold hover:bg-gray-50">Cancel offboarding</button>
                @endif
            </div>
        @endif
    </div>

    {{-- Process steps --}}
    <ol class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($steps as $i => [$name, $done, $hint])
            <li class="bg-white rounded-xl border {{ $done ? 'border-green-200' : 'border-gray-200' }} shadow-sm px-4 py-3 flex items-center gap-3 {{ $done === null ? 'opacity-60' : '' }}">
                <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 {{ $done ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">@if($done)<i class="fas fa-check"></i>@else{{ $i + 1 }}@endif</span>
                <div class="min-w-0"><p class="text-xs font-bold text-gray-800">{{ $name }}</p><p class="text-[11px] text-gray-500 truncate">{{ $hint }}</p></div>
            </li>
        @endforeach
    </ol>

    <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
        {{-- Checklist --}}
        <div class="xl:col-span-2 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800">Offboarding Progress</h3><span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $o->percent >= 100 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $o->percent }}%</span></div>
                <div class="h-2 rounded-full bg-gray-100 mt-3 overflow-hidden"><div class="h-full rounded-full primary-gradient" style="width: {{ $o->percent }}%"></div></div>
                <p class="text-[11px] text-gray-500 mt-2">{{ $o->done }} of {{ $o->total }} tasks completed {{ "" }}@if($o->overdue > 0) · <span class="text-red-600 font-semibold">{{ $o->overdue }} overdue</span>@endif</p>
            </div>

            <form method="POST" action="{{ route('general.offboarding.tasks', $o->id) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm" data-unsaved-ignore>@csrf
                <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Offboarding Checklist</h3></div>
                <div class="p-5 space-y-3">
                    @foreach($o->tasks as $t)
                        @php $late = $t->status === 'pending' && $t->due_date && $t->due_date < now()->toDateString() && $o->status === 'in_progress'; $auto = $t->task_key === 'deactivate_access'; @endphp
                        <div class="rounded-lg border {{ $t->status === 'done' ? 'border-green-200 bg-green-50 bg-opacity-40' : 'border-gray-200' }} p-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><p class="text-sm font-semibold text-gray-800">{{ $t->name }}</p>
                                    @if($t->description)<p class="text-[11px] text-gray-500 mt-0.5">{{ $t->description }}</p>@endif
                                    <p class="text-[11px] mt-1 {{ $late ? 'text-red-600 font-semibold' : 'text-gray-400' }}"><i class="far fa-calendar mr-0.5"></i> Due {{ $t->due_date ? \Illuminate\Support\Carbon::parse($t->due_date)->format('d M Y') : '—' }}@if($late) · overdue @endif</p></div>
                                @if($t->status === 'done')<span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold shrink-0">Done</span>@endif
                            </div>
                            <div class="grid grid-cols-3 gap-2 mt-2.5">
                                <select name="tasks[{{ $t->id }}][status]" class="{{ $input }}" @disabled(!$canEdit || ($auto && !$deactivated))>
                                    <option value="pending" @selected($t->status === 'pending')>Pending</option><option value="done" @selected($t->status === 'done')>Done</option></select>
                                <input name="tasks[{{ $t->id }}][note]" maxlength="500" value="{{ $t->note }}" placeholder="Short note" class="{{ $input }} col-span-2" @disabled(!$canEdit)>
                            </div>
                            @if($auto && !$deactivated)<p class="text-[10px] text-gray-400 mt-1.5">Completed by the <strong>Deactivate access</strong> action on or after the effective date.</p>@endif
                            @if($t->task_key === 'return_assets')
                                <div class="mt-2 text-[11px] {{ $o->assets->isEmpty() ? 'text-green-700' : 'text-amber-700' }}"><i class="fas fa-{{ $o->assets->isEmpty() ? 'circle-check' : 'triangle-exclamation' }} mr-1"></i>
                                    @if($o->assets->isEmpty()) No inventory asset is registered to this person.
                                    @else {{ $o->assets->count() }} asset(s) still registered: {{ $o->assets->map(fn ($a) => $a->code . ' ' . $a->name)->implode(', ') }}. @endif</div>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($canEdit)<div class="px-5 py-3 border-t border-gray-100"><button class="w-full px-4 py-2 text-xs font-semibold text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"><i class="far fa-floppy-disk mr-1"></i> Save checklist</button></div>@endif
            </form>
        </div>

        {{-- Final settlement --}}
        <div class="xl:col-span-3 space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-gray-800">Final Settlement Calculation
                        @php $legalDraft = app(\App\Services\Legal\LegalBasisService::class)->pending('offboarding'); @endphp
                        @if($legalDraft)<span class="ml-2 align-middle inline-block px-2 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-800" title="Rules not reviewed yet: {{ implode(', ', $legalDraft) }}">DRAFT — legal basis not reviewed</span>@endif
                    </h3>
                    <span class="text-[10px] text-gray-400">Legal basis: UU 6/2023, PP 35/2021, PP 68/2009 — see the register below</span>
                </div>

                @if($external)
                    <div class="p-6 text-center text-sm text-gray-500"><span class="w-11 h-11 rounded-xl bg-gray-100 text-gray-400 inline-flex items-center justify-center mb-2"><i class="fas fa-handshake"></i></span>
                        <p class="font-semibold text-gray-700">An external consultant has no final settlement</p><p class="text-xs mt-1 max-w-md mx-auto">There is no severance, service award or PKWT compensation for a consultant. Closing the engagement and the contract, the return of assets and the access are handled by the checklist.</p></div>
                @elseif(!$amounts)
                    <div class="p-6 text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> The settlement amounts need the “View Settlement Amounts” permission.</div>
                @elseif(!$settings->calculator_enabled)
                    <div class="p-6 text-xs text-gray-600"><div class="flex items-start gap-3 bg-gray-50 border border-gray-200 rounded-lg px-4 py-3"><i class="fas fa-calculator mt-0.5 text-gray-400"></i>
                        <div><p class="font-semibold text-gray-700">The settlement calculator is switched off</p><p class="mt-0.5">Switch it on in Offboarding Settings once the legal-basis entries have been reviewed.@if($canSettings) <a href="{{ route('general.offboarding.settings.index') }}" class="primary-text font-semibold underline">Open Settings</a>@endif</p></div></div></div>
                @elseif($missingLegal)
                    <div class="p-6 text-xs text-amber-900"><div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3"><i class="fas fa-scale-balanced mt-0.5"></i>
                        <div><p class="font-semibold">The calculator cannot run yet</p><p class="mt-0.5">These legal-basis entries are not reviewed: {{ implode(', ', $missingLegal) }}.@if($canLegal) <a href="{{ route('finance.legal-basis.index', ['module' => 'offboarding']) }}" class="underline font-semibold">Review them</a>@endif</p></div></div></div>
                @else
                    <form method="POST" action="{{ route('general.offboarding.calculate', $o->id) }}" class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4" data-unsaved-ignore>@csrf
                        <div><label class="{{ $label }}">Offboarding type</label><input value="{{ $o->type_label }}" disabled class="{{ $input }}"></div>
                        <div><label class="{{ $label }}">Contract type</label><input value="{{ $o->contract ? (ContractRules::TYPES[ContractRules::normalizeType($o->contract->contract_type)] ?? $o->contract->contract_type) : 'No running contract' }}" disabled class="{{ $input }}"></div>
                        <div><label class="{{ $label }}">Effective date</label><input value="{{ $effective->format('d/m/Y') }}" disabled class="{{ $input }}"></div>
                        <div>
                            <label class="{{ $label }}" for="reason">Offboarding reason (PHK code)</label>
                            @if($o->offboarding_type === 'termination')
                                <select id="reason" name="reason_code" class="{{ $input }}" @disabled(!$canEdit)>@foreach($terminationReasons as $k => $v)<option value="{{ $k }}" @selected(old('reason_code', $o->reason_code) === $k)>{{ $v }}</option>@endforeach</select>
                            @else <input value="{{ $o->type_label }}" disabled class="{{ $input }}"> @endif
                        </div>
                        <div><label class="{{ $label }}" for="lwd">Last working day</label><input type="date" id="lwd" name="last_working_day" value="{{ old('last_working_day', $o->last_working_day) }}" class="{{ $input }}" @disabled(!$canEdit)></div>
                        <div><label class="{{ $label }}" for="ret">Retirement benefit estimate</label><input id="ret" name="retirement_benefit" inputmode="decimal" value="{{ $s ? number_format((float) $s->retirement_benefit, 0, ',', '.') : '0' }}" class="{{ $input }}" @disabled(!$canEdit || $o->offboarding_type !== 'retirement')>
                            <p class="text-[10px] text-gray-400 mt-1">Only for retirement: offset against the severance and service award.</p></div>
                        <div><label class="{{ $label }}" for="leave">Paid leave balance (days)</label><input id="leave" name="leave_days" inputmode="decimal" value="{{ $s ? rtrim(rtrim(number_format((float) $s->leave_days, 2, ',', '.'), '0'), ',') : '0' }}" class="{{ $input }}" @disabled(!$canEdit)></div>
                        <div><label class="{{ $label }}" for="sep">Separation pay</label><input id="sep" name="separation_pay" inputmode="decimal" value="{{ $s ? number_format((float) $s->separation_pay, 0, ',', '.') : '0' }}" class="{{ $input }}" @disabled(!$canEdit)></div>
                        <div><label class="{{ $label }}" for="oth">Other compensation</label><input id="oth" name="other_compensation" inputmode="decimal" value="{{ $s ? number_format((float) $s->other_compensation, 0, ',', '.') : '0' }}" class="{{ $input }}" @disabled(!$canEdit)></div>
                        <div><label class="{{ $label }}" for="wage">Wage base (override)</label><input id="wage" name="wage_base_override" inputmode="decimal" value="{{ $s && $s->wage_base_override ? number_format((float) $s->wage_base_override, 0, ',', '.') : '' }}" placeholder="{{ $money($suggestedWage) }} from the contract" class="{{ $input }}" @disabled(!$canEdit)>
                            <p class="text-[10px] text-gray-400 mt-1">Empty = basic salary + allowances of the contract.</p></div>
                        <div><label class="{{ $label }}">Service period</label><input value="{{ $res ? number_format($res['service_years'], 2, ',', '.') . ' years (' . $res['service_months'] . ' months)' : 'Calculated on save' }}" disabled class="{{ $input }}"></div>
                        <div class="md:col-span-3"><label class="{{ $label }}" for="ln">Reason / legal notes</label><textarea id="ln" name="legal_notes" rows="2" maxlength="2000" class="{{ $input }}" @disabled(!$canEdit)>{{ old('legal_notes', $s->legal_notes ?? '') }}</textarea></div>
                        <div class="md:col-span-3"><label class="{{ $label }}" for="in">Internal notes</label><textarea id="in" name="internal_notes" rows="2" maxlength="2000" class="{{ $input }}" @disabled(!$canEdit)>{{ old('internal_notes', $s->internal_notes ?? '') }}</textarea></div>
                        @if($canEdit)<div class="md:col-span-3 flex justify-end"><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-calculator mr-1"></i> Calculate &amp; Save</button></div>@endif
                    </form>

                    @if($res)
                        <div class="px-5 pb-5 space-y-4">
                            <div class="text-[11px] bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-2.5">The wage base is the contract's basic salary plus its allowances (override above). Calculated {{ \Illuminate\Support\Carbon::parse($s->calculated_at)->format('d M Y H:i') }} with the legal-basis versions listed below.</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {!! $line('Statutory wage base', $money($res['wage_base']), 'Basic salary + fixed allowances') !!}
                                {!! $line('Severance pay', $money($res['severance']), number_format($res['severance_multiplier'], 2, ',', '.') . '× monthly wage') !!}
                                {!! $line('Service award (UPMK)', $money($res['service_award']), number_format($res['service_award_multiplier'], 2, ',', '.') . '× monthly wage') !!}
                                {!! $line('PKWT compensation', $money($res['pkwt_compensation']), 'Proportional to the contract period') !!}
                                {!! $line('Paid leave settlement', $money($res['leave_settlement']), 'Days × wage ÷ ' . rtrim(rtrim(number_format((float) $settings->leave_divisor, 1, ',', '.'), '0'), ',')) !!}
                                {!! $line('Separation pay', $money($res['separation_pay']), 'Per company regulation or agreement') !!}
                                @if($o->offboarding_type === 'retirement'){!! $line('Gross retirement entitlement', $money($res['gross_entitlement']), 'Before the retirement-benefit offset of ' . $money($res['retirement_offset'])) !!}@endif
                                {!! $line('PKWT penalty risk', $money($res['pkwt_penalty_risk']), $res['pkwt_penalty_months'] > 0 ? $res['pkwt_penalty_months'] . ' month(s) of the contract remain — information only' : 'The PKWT does not end early') !!}
                                {!! $line('Final severance tax', $money($res['severance_tax']), 'PP 68/2009 on ' . $money($res['taxable_base'])) !!}
                                {!! $line('Net settlement', $money($res['net_settlement']), 'After final tax; other compensation is included', true) !!}
                            </div>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Legal basis used --}}
            @if(!$external && $amounts)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800">Legal basis</h3>
                        @if($canLegal)<a href="{{ route('finance.legal-basis.index', ['module' => 'offboarding']) }}" class="text-[11px] font-semibold primary-text hover:underline">Open the register</a>@endif</div>
                    @include('partials.legal-basis-table', ['rows' => $legalRows])
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Cancel dialog --}}
@if($canCancel)
<div id="cancelModal" class="fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form method="POST" action="{{ route('general.offboarding.cancel', $o->id) }}" class="bg-white rounded-xl shadow-xl w-full max-w-md" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">Cancel this offboarding</h3><p class="text-[11px] text-gray-400">Only possible while access has not been deactivated.</p></div>
        <div class="px-5 py-4"><label class="{{ $label }}" for="cancelReason">Reason</label><input id="cancelReason" name="reason" required maxlength="500" class="{{ $input }}" placeholder="Why is it cancelled?"></div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"><button type="button" onclick="document.getElementById('cancelModal').style.display='none'" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Keep it</button>
            <button class="px-5 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:opacity-90">Cancel offboarding</button></div>
    </form>
</div>
@endif
@endsection
