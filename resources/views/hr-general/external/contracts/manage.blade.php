@extends('dashboard')
@section('title', $c->name . ' — External Contract')
@section('page-title', 'External Contract')
@section('page-subtitle', 'Contract history of one external consultant, and the form for a new contract.')

@php
    $canCreate = $canDo('general.external.contracts', 'create');
    $canEdit   = $canDo('general.external.contracts', 'edit');
    $canDelete = $canDo('general.external.contracts', 'delete');
    $badge = [
        'active' => 'bg-green-100 text-green-700', 'draft' => 'bg-gray-200 text-gray-600', 'expired' => 'bg-red-100 text-red-700',
        'terminated' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-100 text-gray-500',
    ];
    $schemeTone = ['mandays' => 'tone-primary', 'monthly' => 'bg-amber-100 text-amber-700', 'fixed_price' => 'bg-sky-100 text-sky-700', 'hourly' => 'bg-purple-100 text-purple-700'];
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : '—';

    $editing = $edit !== null;
    // The engagement default rate has its own permission (Employee Data → engagement rate); without it the rate is neither shown nor prefilled.
    $canEngRate = property_exists($c, 'rate_text');
    $terms = $editing ? $edit->terms : null;
    // Starting values: what is being edited, else the consultant's engagement (scheme, dates, rate when allowed).
    $v = [
        'engagement_scheme' => $editing ? ($edit->scheme_key ?? '') : ($c->scheme_key ?? 'mandays'),
        'status'            => $editing ? (in_array($edit->lifecycle_status, $settable, true) ? $edit->lifecycle_status : 'draft') : 'draft',
        'template_id'       => $editing ? $edit->template_id : '',
        'start_date'        => $editing ? $edit->start_date?->toDateString() : ($c->engagement_start ?? ''),
        'end_date'          => $editing ? $edit->end_date?->toDateString() : ($c->engagement_end ?? ''),
        'signed_date'       => $editing ? $edit->signed_date?->toDateString() : now()->toDateString(),
        'rate'              => $editing ? ($terms->rate ?? '') : ($canRate && $canEngRate ? ($engagement->rate ?? '') : ''),
        'currency'          => $editing ? ($terms->currency ?? 'IDR') : ($canEngRate ? ($engagement->currency ?? 'IDR') : 'IDR'),
        'billing_unit'      => $editing ? ($terms->billing_unit ?? '') : '',
        'work_volume'       => $editing ? $edit->work_volume : '',
        'work_location'     => $editing ? $edit->work_location : '',
        'hours_per_manday'  => $editing ? ($terms->hours_per_manday ?? 8) : 8,
        'proration'         => $editing ? ($terms->proration ?? 'working_days') : 'working_days',
        'payment_term_days' => $editing ? ($terms->payment_term_days ?? 14) : 14,
        'notes'             => $editing ? $edit->notes : '',
        'signatory_employee_id' => $editing ? $edit->signatory_employee_id : '',
    ];
    $old = fn (string $key) => old($key, $v[$key] ?? '');
    // A saved rate ("2700000.00") is shown in the Indonesian format; what was typed after a refused save is shown as typed.
    $rateShown = session()->hasOldInput('rate') ? old('rate') : (($v['rate'] ?? '') !== '' && $v['rate'] !== null ? number_format((float) $v['rate'], 2, ',', '.') : '');
    $showForm = $canCreate || ($editing && $canEdit);
    $lockActive = $activeLocked && !($editing && $edit->is_active);
    $pct = $readiness['percent'];
    $pctTone = $pct >= 100 ? 'bg-green-100 text-green-700' : ($pct >= 50 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700');
    $employeeUrl = route('general.external.consultants.edit', $c->employee_id);
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')
    @include('hr-general.recruitment.components.form-errors')
    @include('hr-general.contracts.components.confirm-forms')

    {{-- Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="w-12 h-12 rounded-full tone-primary flex items-center justify-center text-sm font-bold shrink-0">{{ $c->initials }}</span>
            <div>
                <h2 class="text-lg font-bold text-gray-800 leading-tight">{{ $c->name }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ $c->eci }} • {{ $c->assignment_role ?: ($c->position ?: '-') }}@if($c->managed_by) • Managed by {{ $c->managed_by }}@endif</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canDo('general.external.consultants'))<a href="{{ route('general.external.consultants.show', $c->employee_id) }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">Consultant Detail</a>@endif
            <a href="{{ route('general.external.contracts.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50">External Contract List</a>
        </div>
    </div>

    {{-- Consultant summary --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Consultant Summary</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">The scheme and default rate below are the starting point of a new contract.</p>
        </div>
        <div class="p-5 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div><p class="text-[11px] text-gray-400">Scheme</p><p class="text-sm font-semibold text-gray-800">{{ $c->scheme_label ?: '—' }}</p></div>
            <div><p class="text-[11px] text-gray-400">Default rate</p><p class="text-sm font-semibold text-gray-800">{{ $canEngRate ? (!empty($c->rate_text) ? $c->rate_text . ' ' . ($c->rate_unit ?? '') : '—') : '•••••' }}</p></div>
            <div><p class="text-[11px] text-gray-400">Engagement period</p><p class="text-sm font-semibold text-gray-800">{{ $dt($c->engagement_start) }} – {{ $dt($c->engagement_end) }}</p></div>
            <div><p class="text-[11px] text-gray-400">Client</p><p class="text-sm font-semibold text-gray-800">{{ $c->client_company ?: '—' }}</p></div>
        </div>
    </div>

    {{-- Readiness --}}
    @if($gate !== 'off')
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Contract Readiness</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        @if($gate === 'create') A contract can only be created after the consultant's master data and minimum documents are complete.
                        @else A contract can be prepared as a Draft at any time, but it can only be set to Active after the consultant's master data and minimum documents are complete. @endif
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $pctTone }}">{{ $pct }}%</span>
            </div>
            @unless($readiness['ready'])
                <div class="px-5 pb-4">
                    <div class="text-xs bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-4 py-3">
                        <p class="font-semibold mb-1.5">Complete the following data in Employee Data:</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($readiness['missing'] as $item)
                                @if($canProfile)
                                    <a href="{{ $employeeUrl }}?section={{ $item['section'] ?? '' }}" class="px-2 py-0.5 rounded-full bg-white border border-amber-300 text-amber-900 hover:bg-amber-100">{{ $item['label'] }}</a>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-white border border-amber-300">{{ $item['label'] }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endunless
        </div>
    @endif

    {{-- History --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">External Contract History</h3>
            <p class="text-[11px] text-gray-500 mt-0.5">{{ count($history) }} document{{ count($history) === 1 ? '' : 's' }} available</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-3 w-10">No</th><th class="px-4 py-3">Number</th><th class="px-4 py-3">Template</th><th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Period</th><th class="px-4 py-3">Rate</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($history as $h)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-4 py-3 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ $h->contract_number }}</td>
                            <td class="px-4 py-3">{{ $h->contract_name ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @if($h->scheme_key)<span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $schemeTone[$h->scheme_key] ?? 'bg-gray-100 text-gray-600' }}">{{ \App\Support\External\ExternalRules::schemeLabel($h->scheme_key) }}</span>@else <span class="text-gray-300">-</span>@endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $dt($h->start_date) }}<span class="block text-[11px] text-gray-400">until {{ $dt($h->end_date) }}</span></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if(!$canRate) <span class="text-gray-300">•••••</span>
                                @elseif($h->rate_text) <span class="font-semibold text-gray-800">{{ $h->rate_text }}</span><span class="block text-[11px] text-gray-400">/ {{ $h->terms->billing_unit ?? '' }}</span>
                                @else <span class="text-gray-300">-</span> @endif
                            </td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badge[$h->effective_status] ?? '' }}">{{ $statuses[$h->effective_status] ?? $h->effective_status }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="seg">
                                        <a href="{{ route('general.external.contracts.document', $h->contract_id) }}" class="seg-view">View</a>
                                        @if($canRate)<a href="{{ route('general.external.contracts.document', $h->contract_id) }}?print=1" class="seg-print">Print</a>@endif
                                        @if($canEdit)<a href="{{ route('general.external.contracts.manage', $c->employee_id) }}?edit={{ $h->contract_id }}#new-contract" class="seg-edit">Edit</a>@endif
                                    </div>
                                    @if($canDelete && $h->lifecycle_status === 'draft')
                                        <form method="POST" action="{{ route('general.external.contracts.destroy', $h->contract_id) }}" data-confirm="Delete draft {{ $h->contract_number }}? This cannot be undone." data-confirm-title="Delete Draft" data-confirm-ok="Delete" data-unsaved-ignore>@csrf
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-[11px] font-semibold">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-gray-400">No external contract yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- New / edit contract --}}
    @if($showForm)
    <div id="new-contract" class="bg-white rounded-xl border border-gray-200 shadow-sm scroll-mt-24">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">{{ $editing ? 'Edit External Contract' : 'Add New External Contract' }}</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">
                    {{ $editing ? $edit->contract_number : 'Pick the contract text from Contract Templates. The text is frozen as a snapshot once the contract is Active, so old documents never change.' }}
                </p>
            </div>
            @if($editing)<a href="{{ route('general.external.contracts.manage', $c->employee_id) }}#new-contract" class="text-[11px] font-semibold primary-text hover:underline">Cancel editing</a>@endif
        </div>

        <form method="POST" action="{{ $editing ? route('general.external.contracts.update', $edit->contract_id) : route('general.external.contracts.store', $c->employee_id) }}" id="extContractForm" class="p-5 space-y-5">
            @csrf
            {{-- Template --}}
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <label class="{{ $label }} mb-0" for="fTpl">Contract template</label>
                    @if($canTemplates)<a href="{{ route('general.contracts.templates.index') }}" class="text-[11px] font-semibold primary-text hover:underline"><i class="far fa-file-lines mr-1"></i>Manage templates</a>@endif
                </div>
                <select id="fTpl" name="template_id" class="{{ $input }}">
                    <option value="">Automatic — the default external template</option>
                    @foreach($templates as $t)
                        <option value="{{ $t->id }}" @selected((string) $old('template_id') === (string) $t->id)>{{ $t->name }}@if($t->is_system_default) (system default)@endif</option>
                    @endforeach
                </select>
                @if($canTemplates)
                    <a id="tplPreview" href="#" target="_blank" class="hidden mt-2 text-[11px] font-semibold primary-text hover:underline"><i class="far fa-eye mr-1"></i>Preview this template</a>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @if($editing)
                    <div><label class="{{ $label }}" for="fNumber">Contract number</label><input id="fNumber" name="contract_number" maxlength="100" value="{{ old('contract_number', $edit->contract_number) }}" class="{{ $input }}"><p class="text-[10px] text-gray-400 mt-1">Generated by the system; correct it only if needed.</p></div>
                @else
                    <div><label class="{{ $label }}">Contract number</label><input value="Generated when the contract is saved" disabled class="{{ $input }} bg-gray-50 text-gray-400"></div>
                @endif
                <div>
                    <label class="{{ $label }}" for="fScheme">Contract type</label>
                    <select id="fScheme" name="engagement_scheme" class="{{ $input }}" required>
                        @foreach($schemes as $key => $name)<option value="{{ $key }}" @selected($old('engagement_scheme') === $key)>{{ $name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $label }}" for="fStatus">Status</label>
                    <select id="fStatus" name="status" class="{{ $input }}">
                        @foreach($settable as $s)
                            <option value="{{ $s }}" @selected($old('status') === $s) @disabled($s === 'active' && $lockActive)>{{ $statuses[$s] }}{{ $s === 'active' && $lockActive ? ' (needs complete consultant data)' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div><label class="{{ $label }}" for="fStart">Start</label><input type="date" id="fStart" name="start_date" value="{{ $old('start_date') }}" class="{{ $input }}" required></div>
                <div><label class="{{ $label }}" for="fEnd">End</label><input type="date" id="fEnd" name="end_date" value="{{ $old('end_date') }}" class="{{ $input }}"><p class="text-[10px] text-gray-400 mt-1">Optional. “Expired” follows the end date by itself.</p></div>
                <div><label class="{{ $label }}" for="fSigned">Signing date</label><input type="date" id="fSigned" name="signed_date" value="{{ $old('signed_date') }}" class="{{ $input }}"></div>

                @if($canRate)
                    <div><label class="{{ $label }}" for="fRate">Rate</label><input id="fRate" name="rate" inputmode="decimal" value="{{ $rateShown }}" placeholder="e.g. 2.700.000" class="{{ $input }}"></div>
                    <div><label class="{{ $label }}" for="fCur">Currency</label>
                        <select id="fCur" name="currency" class="{{ $input }}">@foreach($currencies as $cur)<option value="{{ $cur }}" @selected($old('currency') === $cur)>{{ $cur }}</option>@endforeach</select></div>
                @else
                    <p class="md:col-span-2 text-[11px] text-gray-400 self-end pb-2"><i class="fas fa-lock mr-1"></i> The rate is hidden: it needs the “View Salary” permission. Saving here never changes it.</p>
                @endif

                <div><label class="{{ $label }}" for="fUnit">Billing unit</label><input id="fUnit" name="billing_unit" maxlength="30" value="{{ $old('billing_unit') }}" placeholder="Follows the contract type" class="{{ $input }}"></div>
                <div><label class="{{ $label }}" for="fVolume">Work volume</label><input id="fVolume" name="work_volume" maxlength="100" value="{{ $old('work_volume') }}" placeholder="e.g. 20,00 mandays" class="{{ $input }}"><p class="text-[10px] text-gray-400 mt-1">Printed as “Jumlah waktu pelaksanaan”.</p></div>
                <div id="fHoursWrap"><label class="{{ $label }}" for="fHours">Hours per manday</label><input id="fHours" name="hours_per_manday" inputmode="decimal" value="{{ $old('hours_per_manday') }}" class="{{ $input }}"></div>

                <div id="fProWrap">
                    <label class="{{ $label }}" for="fPro">Monthly proration</label>
                    <select id="fPro" name="proration" class="{{ $input }}">@foreach($proration as $key => $name)<option value="{{ $key }}" @selected($old('proration') === $key)>{{ $name }}</option>@endforeach</select>
                </div>
                <div><label class="{{ $label }}" for="fTerm">Payment term (days)</label><input type="number" min="0" max="365" id="fTerm" name="payment_term_days" value="{{ $old('payment_term_days') }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}" for="fLoc">Work location / model</label><input id="fLoc" name="work_location" maxlength="255" value="{{ $old('work_location') }}" placeholder="e.g. ONSITE, REMOTE" class="{{ $input }}"></div>

                <div class="md:col-span-3">
                    <label class="{{ $label }}" for="fSign">Company signatory</label>
                    <select id="fSign" name="signatory_employee_id" class="{{ $input }} md:w-1/2">
                        <option value="">Template default (if the template sets one)</option>
                        @foreach($signatories as $p)<option value="{{ $p['id'] }}" @selected((string) $old('signatory_employee_id') === (string) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>@endforeach
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1">From Letter Templates → Settings → Signers.</p>
                </div>
                <div class="md:col-span-3"><label class="{{ $label }}" for="fNotes">Notes</label><textarea id="fNotes" name="notes" rows="2" maxlength="2000" class="{{ $input }}">{{ $old('notes') }}</textarea></div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-check mr-1"></i> {{ $editing ? 'Save External Contract' : 'Save External Contract' }}</button>
            </div>
        </form>
    </div>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id);
    const scheme = $('fScheme');
    if (!scheme) return;

    // Hours per manday only matters for mandays; proration only for the monthly scheme.
    function schemeRules() {
        $('fHoursWrap').classList.toggle('hidden', scheme.value !== 'mandays');
        $('fProWrap').classList.toggle('hidden', scheme.value !== 'monthly');
        const unit = { mandays: 'mandays', monthly: 'months', fixed_price: 'package', hourly: 'hours' }[scheme.value] || '';
        $('fUnit').placeholder = 'Follows the contract type (' + unit + ')';
    }
    scheme.addEventListener('change', schemeRules);
    schemeRules();

    @if($canTemplates)
    const preview = $('tplPreview'), tpl = $('fTpl'), base = @json(url('general/contracts/templates'));
    function tplLink() { preview.classList.toggle('hidden', !tpl.value); preview.href = base + '/' + tpl.value; }
    tpl.addEventListener('change', tplLink);
    tplLink();
    @endif

    // Opening the page with #new-contract (Add Contract / Edit) scrolls to the form.
    if (location.hash === '#new-contract') $('new-contract')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
})();
</script>
@endpush
@endsection
