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
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300 bg-white disabled:bg-gray-50 disabled:text-gray-400';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $hint = 'text-[11px] text-gray-400 mt-1';
    $card = 'bg-white rounded-xl border border-gray-200 shadow-sm';
    $dt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : '—';

    $editing = $edit !== null;
    // The engagement default rate has its own permission (Employee Data → engagement rate); without it the rate is neither shown nor prefilled.
    $canEngRate = property_exists($c, 'rate_text');
    $terms = $editing ? $edit->terms : null;
    // Starting values: what is being edited, else the consultant's engagement (scheme, dates, rate when allowed).
    $v = [
        // The option code: a custom contract type when the contract holds one, else the built-in scheme.
        'engagement_scheme' => $editing ? ($edit->contract_kind ?: ($edit->scheme_key ?? '')) : ($defaultScheme ?: ($c->scheme_key ?? 'mandays')),
        'status'            => $editing ? (in_array($edit->lifecycle_status, $settable, true) ? $edit->lifecycle_status : 'draft') : 'draft',
        'template_id'       => $editing ? $edit->template_id : '',
        'position_type'     => $editing ? ($edit->position_type ?? '') : ($defaultPositionType ?? ''),
        'position'          => $editing ? ($edit->position ?? '') : ($c->assignment_role ?? ''),
        'start_date'        => $editing ? $edit->start_date?->toDateString() : ($c->engagement_start ?? ''),
        'end_date'          => $editing ? $edit->end_date?->toDateString() : ($c->engagement_end ?? ''),
        'signed_date'       => $editing ? $edit->signed_date?->toDateString() : now()->toDateString(),
        'rate'              => $editing ? ($terms->rate ?? '') : ($canRate && $canEngRate ? ($engagement->rate ?? '') : ''),
        'currency'          => $editing ? ($terms->currency ?? 'IDR') : ($canEngRate ? ($engagement->currency ?? 'IDR') : 'IDR'),
        'billing_unit'      => $editing ? ($terms->billing_unit ?? '') : '',
        'work_volume'       => $editing ? $edit->work_volume : '',
        'work_location'     => $editing ? $edit->work_location : ($defaultPlacement ?? ''),
        'hours_per_manday'  => $editing ? ($terms->hours_per_manday ?? 8) : 8,
        'proration'         => $editing ? ($terms->proration ?? 'working_days') : 'working_days',
        'payment_term_days' => $editing ? ($terms->payment_term_days ?? 14) : 14,
        'notes'             => $editing ? $edit->notes : '',
        'signatory_employee_id' => $editing ? $edit->signatory_employee_id : ($defaultSigner ?? ''),
        'assignment'        => $editing ? ($terms->assignment ?? '') : ($c->assignment_role ?? ''),
    ];
    $old = fn (string $key) => old($key, $v[$key] ?? '');
    // A saved rate ("2700000.00") is shown in the Indonesian format; what was typed after a refused save is shown as typed.
    $rateShown = session()->hasOldInput('rate') ? old('rate') : (($v['rate'] ?? '') !== '' && $v['rate'] !== null ? number_format((float) $v['rate'], 2, ',', '.') : '');
    $showForm = $canCreate || ($editing && $canEdit);
    $lockActive = $activeLocked && !($editing && $edit->is_active);
    $pct = $readiness['percent'];
    $pctTone = $pct >= 100 ? 'bg-green-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-red-500');
    $pctText = $pct >= 100 ? 'text-green-700' : ($pct >= 50 ? 'text-amber-700' : 'text-red-700');
    $employeeUrl = route('general.external.consultants.edit', $c->employee_id);
    // Active terkunci: sebutkan APA yang kurang (butir pertama saja di sini; daftar lengkap ada di kartu "Contract readiness").
    $lockedIssues = $lockActive ? \App\Support\Contracts\DataIssues::fromMissing($readiness['missing'], $employeeUrl) : [];
    $summary = [
        ['Contract type', $c->scheme_label ?: '—'],
        ['Default rate', $canEngRate ? (!empty($c->rate_text) ? $c->rate_text . ' ' . ($c->rate_unit ?? '') : '—') : '•••••'],
        ['Engagement period', $dt($c->engagement_start) . ' – ' . $dt($c->engagement_end)],
        ['Client', $c->client_company ?: '—'],
    ];
@endphp

@section('content')
<div class="w-full space-y-5 px-1 lg:px-2">
    @include('hr-general.external.contracts.components.tabs')
    @include('partials.accent-remap')
    <style>#extContractForm .grid > *, #extContractForm fieldset { min-width: 0; } #extContractForm .se-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }</style>
    @include('hr-general.contracts.components.issues-panel', ['issuesCanOpen' => $canProfile])
    @include('hr-general.recruitment.components.form-errors')
    @include('hr-general.contracts.components.confirm-forms')

    {{-- Consultant --}}
    <header class="{{ $card }} px-5 py-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
            <span class="w-14 h-14 rounded-full tone-primary flex items-center justify-center text-base font-bold shrink-0" aria-hidden="true">{{ $c->initials }}</span>
            <div class="min-w-0">
                <a href="{{ route('general.external.contracts.index') }}" class="text-[11px] font-semibold text-gray-400 hover:text-gray-700"><i class="fas fa-arrow-left mr-1"></i> External contracts</a>
                <h2 class="text-xl font-bold text-gray-900 leading-tight truncate">{{ $c->name }}</h2>
                <p class="text-xs text-gray-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-semibold text-gray-600">{{ $c->eci }}</span>
                    <span class="text-gray-300">•</span><span>{{ $c->assignment_role ?: ($c->position ?: '—') }}</span>
                    @if($c->managed_by)<span class="text-gray-300">•</span><span>Managed by {{ $c->managed_by }}</span>@endif
                </p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            @if($canDo('general.external.consultants'))
                <a href="{{ route('general.external.consultants.show', $c->employee_id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50"><i class="far fa-id-card text-[11px]"></i> Consultant detail</a>
            @endif
            @if($showForm && !$editing)
                <a href="#new-contract" class="inline-flex items-center gap-1.5 px-3.5 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus text-[10px]"></i> New contract</a>
            @endif
        </div>
    </header>

    {{-- Summary and readiness side by side --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <section class="{{ $card }} xl:col-span-2" aria-labelledby="sumTitle">
            <div class="px-5 pt-4 pb-3">
                <h3 id="sumTitle" class="text-sm font-bold text-gray-900">Consultant summary</h3>
                <p class="text-xs text-gray-500 mt-0.5">The contract type and default rate are the starting point of a new contract.</p>
            </div>
            <dl class="px-5 pb-5 grid grid-cols-2 lg:grid-cols-4 gap-x-6 gap-y-4">
                @foreach($summary as [$term, $value])
                    <div class="min-w-0"><dt class="text-[11px] text-gray-400">{{ $term }}</dt><dd class="text-sm font-semibold text-gray-900 wrap-break-word">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>

        @if($gate !== 'off')
            <section class="{{ $card }}" aria-labelledby="readyTitle">
                <div class="px-5 pt-4 pb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 id="readyTitle" class="text-sm font-bold text-gray-900">Contract readiness</h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            @if($gate === 'create') Needed before a contract can be created.
                            @else Needed before a contract can be set to Active; a Draft can always be prepared. @endif
                        </p>
                    </div>
                    <span class="text-sm font-bold {{ $pctText }}">{{ $pct }}%</span>
                </div>
                <div class="px-5 pb-5">
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Data completeness">
                        <div class="h-full rounded-full {{ $pctTone }}" style="width: {{ $pct }}%"></div>
                    </div>
                    @if($readiness['ready'])
                        <p class="mt-3 text-xs text-green-700"><i class="fas fa-circle-check mr-1"></i> The consultant's data is complete.</p>
                    @else
                        <p class="mt-3 text-xs font-semibold text-gray-700">Still missing</p>
                        @php $pillLinks = collect(\App\Support\Contracts\DataIssues::fromMissing($readiness['missing'], $employeeUrl))->keyBy('key'); @endphp
                        <ul class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach($readiness['missing'] as $item)
                                <li>
                                    @if($canProfile)
                                        <a href="{{ $pillLinks[$item['key']]['url'] ?? $employeeUrl }}" title="Open the {{ $pillLinks[$item['key']]['tab_label'] ?? '' }} tab" class="inline-block px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-900 text-[11px] hover:bg-amber-100">{{ $item['label'] }} <span class="text-amber-700/70">· {{ $pillLinks[$item['key']]['tab_label'] ?? '' }}</span></a>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-50 border border-amber-200 text-amber-900 text-[11px]">{{ $item['label'] }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endif
    </div>

    {{-- History --}}
    <section class="{{ $card }} overflow-hidden" aria-labelledby="histTitle">
        <div class="px-5 pt-4 pb-3 flex items-center justify-between gap-3">
            <div>
                <h3 id="histTitle" class="text-sm font-bold text-gray-900">Contract history</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ count($history) }} document{{ count($history) === 1 ? '' : 's' }}</p>
            </div>
        </div>
        <div class="overflow-x-auto border-t border-gray-100">
            <table class="w-full text-xs text-left min-w-208">
                <thead class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500">
                    <tr>
                        <th scope="col" class="pl-5 pr-2 py-2.5 w-10">No</th><th scope="col" class="px-3 py-2.5">Contract</th><th scope="col" class="px-3 py-2.5">Type</th>
                        <th scope="col" class="px-3 py-2.5">Period</th><th scope="col" class="px-3 py-2.5">Rate</th><th scope="col" class="px-3 py-2.5">Status</th>
                        <th scope="col" class="pl-3 pr-5 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700">
                    @forelse($history as $h)
                        <tr class="hover:bg-gray-50/60 align-middle {{ $editing && $edit->contract_id === $h->contract_id ? 'bg-indigo-50/40' : '' }}">
                            <td class="pl-5 pr-2 py-3 text-gray-400">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3">
                                <span class="font-semibold text-gray-900 whitespace-nowrap">{{ $h->contract_number }}</span>
                                <span class="block text-[11px] text-gray-400">{{ $h->contract_name ?: '—' }}</span>
                            </td>
                            <td class="px-3 py-3">
                                @if($h->scheme_key)<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $schemeTone[$h->scheme_key] ?? 'bg-gray-100 text-gray-600' }}">{{ $kindLabels[$h->contract_kind] ?? \App\Support\External\ExternalRules::schemeLabel($h->scheme_key) }}</span>@else<span class="text-gray-300">—</span>@endif
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">{{ $dt($h->start_date) }}<span class="block text-[11px] text-gray-400">until {{ $dt($h->end_date) }}</span></td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                @if(!$canRate)<span class="text-gray-300" title="Needs the permission “External Contract rate” (Menu Access)">•••••</span>
                                @elseif($h->rate_text)<span class="font-semibold text-gray-900">{{ $h->rate_text }}</span><span class="block text-[11px] text-gray-400">/ {{ $h->terms->billing_unit ?? '' }}</span>
                                @else<span class="text-gray-300">—</span>@endif
                            </td>
                            <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $badge[$h->effective_status] ?? 'bg-gray-100 text-gray-500' }}">{{ $statuses[$h->effective_status] ?? $h->effective_status }}</span></td>
                            <td class="pl-3 pr-5 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    <div class="seg">
                                        <a href="{{ route('general.external.contracts.document', $h->contract_id) }}" class="seg-view">View</a>
                                        @if($canRate)<a href="{{ route('general.external.contracts.document', $h->contract_id) }}?print=1" class="seg-print">Print</a>@endif
                                        @if($canEdit)<a href="{{ route('general.external.contracts.manage', $c->employee_id) }}?edit={{ $h->contract_id }}#new-contract" class="seg-edit">Edit</a>@endif
                                    </div>
                                    @if($canDelete && $h->lifecycle_status === 'draft')
                                        <form method="POST" action="{{ route('general.external.contracts.destroy', $h->contract_id) }}" data-confirm="Delete draft {{ $h->contract_number }}? This cannot be undone." data-confirm-title="Delete Draft" data-confirm-ok="Delete" data-confirm-variant="danger" data-unsaved-ignore>@csrf
                                            <button type="submit" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-red-500 hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300" title="Delete draft" aria-label="Delete draft {{ $h->contract_number }}"><i class="fas fa-trash-can text-xs"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center">
                                <i class="far fa-file-lines text-2xl text-gray-300 mb-2 block"></i>
                                <p class="text-sm font-semibold text-gray-700">No external contract yet</p>
                                @if($showForm)<p class="text-xs text-gray-500 mt-1">Use the form below to prepare the first one.</p>@endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- New / edit contract --}}
    @if($showForm)
    <section id="new-contract" class="{{ $card }} scroll-mt-24" aria-labelledby="formTitle">
        <div class="px-5 pt-4 pb-3 border-b border-gray-100 flex items-start justify-between gap-3">
            <div>
                <h3 id="formTitle" class="text-base font-bold text-gray-900">{{ $editing ? 'Edit external contract' : 'New external contract' }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    @if($editing)
                        {{ $edit->contract_number }}
                    @else
                        Fill in the fields, generate the document to read it, then save. Its text is frozen once the contract is Active.
                    @endif
                </p>
            </div>
            @if($editing)<a href="{{ route('general.external.contracts.manage', $c->employee_id) }}#new-contract" class="text-xs font-semibold primary-text hover:underline shrink-0">Cancel editing</a>@endif
        </div>

        <form method="POST" action="{{ $editing ? route('general.external.contracts.update', $edit->contract_id) : route('general.external.contracts.store', $c->employee_id) }}" id="extContractForm">
            @csrf
            <div class="p-5 space-y-6">

                {{-- 1 · Template --}}
                <fieldset class="rounded-lg border border-gray-200 bg-gray-50/60 p-4">
                    <legend class="px-2 text-[11px] font-bold uppercase tracking-wide text-gray-500">Contract text</legend>
                    <div class="flex items-center justify-between gap-3 mb-1">
                        <label class="{{ $label }} mb-0" for="fTpl">Template</label>
                        @if($canTemplates)<a href="{{ route('general.contracts.templates.index') }}" class="text-[11px] font-semibold primary-text hover:underline"><i class="far fa-file-lines mr-1"></i>Manage templates</a>@endif
                    </div>
                    <select id="fTpl" name="template_id" data-searchable="true" data-search-placeholder="Search template…" class="{{ $input }}">
                        <option value="">Automatic — by position type, else the default external template</option>
                        @foreach($templates as $t)
                            <option value="{{ $t->id }}" @selected((string) $old('template_id') === (string) $t->id)>{{ $t->name }}@if($t->position_type) — {{ $t->position_type }}@endif @if($t->is_system_default) (system default)@endif</option>
                        @endforeach
                    </select>
                    @if($canTemplates)
                        <a id="tplPreview" href="#" target="_blank" rel="noopener" class="hidden mt-2 text-[11px] font-semibold primary-text hover:underline"><i class="far fa-eye mr-1"></i>Preview this template</a>
                    @endif
                </fieldset>

                {{-- 2 · The contract --}}
                <fieldset>
                    <legend class="text-sm font-bold text-gray-900 mb-3">Contract</legend>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4">
                            @if($editing)
                                <label class="{{ $label }}" for="fNumber">Contract number</label>
                                <input id="fNumber" name="contract_number" maxlength="100" value="{{ old('contract_number', $edit->contract_number) }}" class="{{ $input }}">
                                <p class="{{ $hint }}">Generated by the system; correct it only if needed.</p>
                            @else
                                <label class="{{ $label }}" for="fNumberShown">Contract number</label>
                                <input id="fNumberShown" value="Generated when saved" disabled class="{{ $input }}">
                            @endif
                        </div>
                        <div class="md:col-span-4">
                            <label class="{{ $label }}" for="fScheme">Contract type <span class="text-red-500">*</span></label>
                            <select id="fScheme" name="engagement_scheme" data-searchable="true" data-search-placeholder="Search contract type…" class="{{ $input }}" required>
                                @foreach($schemes as $key => $name)<option value="{{ $key }}" @selected($old('engagement_scheme') === $key)>{{ $name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <label class="{{ $label }}" for="fStatus">Status</label>
                            <select id="fStatus" name="status" data-searchable="true" data-search-placeholder="Search status…" class="{{ $input }}">
                                @foreach($settable as $s)
                                    <option value="{{ $s }}" @selected($old('status') === $s) @disabled($s === 'active' && $lockActive)>{{ $statuses[$s] }}{{ $s === 'active' && $lockActive ? ' (needs complete consultant data)' : '' }}</option>
                                @endforeach
                            </select>
                            @if($lockedIssues)
                                <p class="text-[11px] text-amber-800 mt-1.5 leading-snug"><i class="fas fa-lock mr-1"></i>Active is locked: {{ count($lockedIssues) }} item{{ count($lockedIssues) === 1 ? '' : 's' }} missing —
                                    @foreach(array_slice($lockedIssues, 0, 3) as $li)@if($canProfile)<a href="{{ $li['url'] }}" target="_blank" rel="noopener" class="font-semibold underline decoration-dotted">{{ $li['label'] }}</a>@else<strong>{{ $li['label'] }}</strong>@endif{{ $loop->last ? '' : ', ' }}@endforeach
                                    @if(count($lockedIssues) > 3) and {{ count($lockedIssues) - 3 }} more @endif.
                                    <a href="#readyTitle" class="font-semibold underline">See the full list</a>. A Draft can still be saved.</p>
                            @endif
                        </div>

                        <div class="md:col-span-4">
                            <label class="{{ $label }}" for="fPosType">Position type</label>
                            <select id="fPosType" name="position_type" data-searchable="true" data-search-placeholder="Search position type…" class="{{ $input }}">
                                <option value="">Not specified</option>
                                @foreach($positionTypes as $pt)<option value="{{ $pt }}" @selected((string) $old('position_type') === (string) $pt)>{{ $pt }}</option>@endforeach
                            </select>
                            <p class="{{ $hint }}">Picks the template written for it.@if($canDo('general.external.contracts.settings')) <a href="{{ route('general.external.contracts.settings.index') }}" class="underline font-semibold">Manage</a>@endif</p>
                        </div>
                        <div class="md:col-span-8">
                            <label class="{{ $label }}" for="fPosition">Position / role</label>
                            <input id="fPosition" name="position" maxlength="255" value="{{ $old('position') }}" placeholder="e.g. Business Analyst" class="{{ $input }}">
                            <p class="{{ $hint }}">Printed as the specialisation of the consultant.</p>
                        </div>
                    </div>
                </fieldset>

                {{-- 3 · Period --}}
                <fieldset>
                    <legend class="text-sm font-bold text-gray-900 mb-3">Period</legend>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-4"><label class="{{ $label }}" for="fStart">Start <span class="text-red-500">*</span></label>
                            <input type="date" id="fStart" name="start_date" value="{{ $old('start_date') }}" class="{{ $input }}" required></div>
                        <div class="md:col-span-4"><label class="{{ $label }}" for="fEnd">End</label>
                            <input type="date" id="fEnd" name="end_date" value="{{ $old('end_date') }}" class="{{ $input }}">
                            <p class="{{ $hint }}">Optional. “Expired” follows the end date by itself.</p></div>
                        <div class="md:col-span-4"><label class="{{ $label }}" for="fSigned">Signing date</label>
                            <input type="date" id="fSigned" name="signed_date" value="{{ $old('signed_date') }}" class="{{ $input }}"></div>
                    </div>
                </fieldset>

                {{-- 4 · Commercial terms --}}
                <fieldset>
                    <legend class="text-sm font-bold text-gray-900 mb-3">Commercial terms</legend>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        @if($canRate)
                            <div class="md:col-span-4"><label class="{{ $label }}" for="fRate">Rate</label>
                                <input id="fRate" name="rate" inputmode="decimal" value="{{ $rateShown }}" placeholder="e.g. 2.700.000" class="{{ $input }}"></div>
                            <div class="md:col-span-4"><label class="{{ $label }}" for="fCur">Currency</label>
                                <select id="fCur" name="currency" data-searchable="true" data-search-placeholder="Search currency…" class="{{ $input }}">@foreach($currencies as $cur)<option value="{{ $cur }}" @selected($old('currency') === $cur)>{{ $cur }}</option>@endforeach</select></div>
                        @else
                            <div class="md:col-span-8 self-end">
                                <p class="flex items-start gap-2 text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5"><i class="fas fa-lock mt-0.5 text-gray-400"></i><span>The rate is hidden: it needs the permission “External Contract rate” (an administrator adds it in Control Center → Menu Access). Saving here never changes it.</span></p>
                            </div>
                        @endif
                        <div class="md:col-span-4"><label class="{{ $label }}" for="fUnit">Billing unit</label>
                            <input id="fUnit" name="billing_unit" maxlength="30" value="{{ $old('billing_unit') }}" placeholder="Follows the contract type" class="{{ $input }}"></div>

                        <div class="md:col-span-4"><label class="{{ $label }}" for="fVolume">Work volume</label>
                            <input id="fVolume" name="work_volume" maxlength="100" value="{{ $old('work_volume') }}" placeholder="e.g. 20,00 mandays" class="{{ $input }}">
                            <p class="{{ $hint }}">Printed as “Jumlah waktu pelaksanaan”.</p></div>
                        <div id="fHoursWrap" class="md:col-span-4"><label class="{{ $label }}" for="fHours">Hours per manday</label>
                            <input id="fHours" name="hours_per_manday" inputmode="decimal" value="{{ $old('hours_per_manday') }}" class="{{ $input }}"></div>
                        <div id="fProWrap" class="md:col-span-4"><label class="{{ $label }}" for="fPro">Monthly proration</label>
                            <select id="fPro" name="proration" data-searchable="true" data-search-placeholder="Search…" class="{{ $input }}">@foreach($proration as $key => $name)<option value="{{ $key }}" @selected($old('proration') === $key)>{{ $name }}</option>@endforeach</select></div>

                        <div class="md:col-span-4"><label class="{{ $label }}" for="fTerm">Payment term (days)</label>
                            <input type="number" min="0" max="365" id="fTerm" name="payment_term_days" value="{{ $old('payment_term_days') }}" class="{{ $input }}"></div>
                        @php
                            $locNow = (string) $old('work_location');
                            $locKnown = in_array($locNow, $placements['branches'], true) || in_array($locNow, $placements['custom'], true);
                        @endphp
                        <div class="md:col-span-8"><label class="{{ $label }}" for="fLocSel">Work placement</label>
                            <select id="fLocSel" class="{{ $input }}" aria-describedby="fLocHint">
                                <option value="">— Not specified —</option>
                                @if($placements['branches'])
                                    <optgroup label="Branches (Management → Branches)">@foreach($placements['branches'] as $b)<option value="{{ $b }}" @selected($locNow === $b)>{{ $b }}</option>@endforeach</optgroup>
                                @endif
                                @if($placements['custom'])
                                    <optgroup label="Other placements">@foreach($placements['custom'] as $b)<option value="{{ $b }}" @selected($locNow === $b)>{{ $b }}</option>@endforeach</optgroup>
                                @endif
                                <option value="__other" @selected($locNow !== '' && !$locKnown)>Other (type it)…</option>
                            </select>
                            <input id="fLoc" name="work_location" maxlength="255" value="{{ $locNow }}" placeholder="Type the placement" class="{{ $input }} mt-2 {{ $locNow !== '' && !$locKnown ? '' : 'hidden' }}" aria-label="Work placement (typed)">
                            <p id="fLocHint" class="{{ $hint }}">Printed in the contract as the work location. Branches come from Management → Branches; HR adds other places in this menu’s Settings.</p></div>
                        <div class="md:col-span-12">
                            <label class="{{ $label }}" for="fAssign">Assignment <span class="text-gray-400 normal-case font-normal">(project / scope of work)</span></label>
                            @if($assignmentSupported ?? true)
                                <input id="fAssign" name="assignment" maxlength="255" value="{{ $old('assignment') }}" placeholder="e.g. SAP FI implementation — PT Contoh Client" class="{{ $input }}">
                                <p class="{{ $hint }}">Printed as the assignment (“Penugasan”) of the contract. Left empty, it follows the consultant’s Engagement.</p>
                            @else
                                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"><i class="fas fa-triangle-exclamation mr-1"></i> The assignment field needs a database update first (column <code>external_contract_terms.assignment</code>). Ask the administrator to run the update; until then the contract uses the consultant’s Engagement assignment.</p>
                            @endif
                        </div>
                    </div>
                </fieldset>

                {{-- 5 · Signing and notes --}}
                <fieldset>
                    <legend class="text-sm font-bold text-gray-900 mb-3">Signing and notes</legend>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-6">
                            <label class="{{ $label }}" for="fSign">Company signatory</label>
                            <select id="fSign" name="signatory_employee_id" data-searchable="true" data-search-placeholder="Search signer…" class="{{ $input }}">
                                @if(empty($signerNote))<option value="">Template default (if the template sets one)</option>@endif
                                @foreach($signatories as $p)<option value="{{ $p['id'] }}" @selected((string) $old('signatory_employee_id') === (string) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>@endforeach
                            </select>
                            <p class="{{ $hint }}">{{ $signerNote ?? 'From Letter Templates → Settings → Signers.' }}</p>
                            @include('hr-general.external.components.signer-warning', ['signerScope' => 'contract'])
                        </div>
                        <div class="md:col-span-12">
                            <label class="{{ $label }}" for="fNotes">Notes</label>
                            <textarea id="fNotes" name="notes" rows="2" maxlength="2000" class="{{ $input }}" placeholder="Internal notes, not printed unless the template uses them">{{ $old('notes') }}</textarea>
                        </div>
                    </div>
                </fieldset>

                {{-- 6 · Generate the contract from the fields above, then read it and adjust it (Legal / HR / RPMO) before saving. --}}
                <div class="grid grid-cols-1">
                    @include('hr-general.contracts.components.document-composer', [
                        'dcForm' => 'extContractForm', 'dcUrl' => route('general.external.contracts.preview', $c->employee_id), 'dcExternal' => true, 'dcContract' => $editing ? $edit : null,
                    ])
                </div>
            </div>

            {{-- The save bar stays in view while the long form scrolls --}}
            <div class="sticky bottom-0 z-10 px-5 py-3 bg-white/95 backdrop-blur border-t border-gray-200 rounded-b-xl flex flex-wrap items-center justify-between gap-3">
                <p class="text-[11px] text-gray-400"><span class="text-red-500">*</span> required</p>
                <div class="flex items-center gap-2">
                    @if($editing)
                        <a href="{{ route('general.external.contracts.manage', $c->employee_id) }}#new-contract" class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</a>
                    @endif
                    <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-check text-[11px]"></i> {{ $editing ? 'Save changes' : 'Save contract' }}</button>
                </div>
            </div>
        </form>
    </section>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id);
    const scheme = $('fScheme');
    if (!scheme) return;
    // option code → the built-in scheme its terms follow (a custom contract type behaves like its base)
    const schemeBase = @json($schemeBase);
    const baseScheme = () => schemeBase[scheme.value] || scheme.value;

    // Hours per manday only matters for mandays; proration only for the monthly scheme.
    function schemeRules() {
        $('fHoursWrap').classList.toggle('hidden', baseScheme() !== 'mandays');
        $('fProWrap').classList.toggle('hidden', baseScheme() !== 'monthly');
        const unit = { mandays: 'mandays', monthly: 'months', fixed_price: 'package', hourly: 'hours' }[baseScheme()] || '';
        $('fUnit').placeholder = 'Follows the contract type (' + unit + ')';
    }
    scheme.addEventListener('change', schemeRules);
    schemeRules();

    // Work placement: a pick from the list fills the typed field the form posts; "Other" reveals it for free text.
    (function () {
        const sel = $('fLocSel'), typed = $('fLoc');
        if (!sel || !typed) return;
        sel.addEventListener('change', function () {
            const other = sel.value === '__other';
            typed.classList.toggle('hidden', !other);
            typed.value = other ? '' : sel.value;
            if (other) typed.focus();
            typed.dispatchEvent(new Event('input', { bubbles: true }));
        });
    })();

    @if($canTemplates)
    const preview = $('tplPreview'), tpl = $('fTpl'), base = @json(url('general/contracts/templates'));
    function tplLink() { preview.classList.toggle('hidden', !tpl.value); preview.href = base + '/' + tpl.value; }
    tpl.addEventListener('change', tplLink);
    tplLink();
    @endif

    // End date cannot precede the start date: the browser says so before the server does.
    const start = $('fStart'), end = $('fEnd');
    function limitEnd() { end.min = start.value || ''; }
    start.addEventListener('change', limitEnd);
    limitEnd();

    // Opening the page with #new-contract (Add Contract / Edit) scrolls to the form.
    if (location.hash === '#new-contract') $('new-contract')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
})();
</script>
@endpush
@endsection
