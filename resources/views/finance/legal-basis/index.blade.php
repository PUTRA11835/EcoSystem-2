@extends('dashboard')
@section('title', 'Legal Basis')
@section('page-title', 'Legal Basis')
@section('page-subtitle', 'The laws and regulations behind payroll, BPJS, overtime, contract and offboarding rules — with the date each was last amended.')

@php
    $canCreate = $canDo('finance.legal-basis', 'create');
    $canEdit = $canDo('finance.legal-basis', 'edit');
    $canDelete = $canDo('finance.legal-basis', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'amber' => 'bg-amber-50 text-amber-600', 'green' => 'bg-green-50 text-green-600', 'gray' => 'bg-gray-100 text-gray-500'];
    $confTone = ['high' => 'text-green-700', 'medium' => 'text-amber-700', 'low' => 'text-red-600'];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')
    @include('hr-general.contracts.components.confirm-forms')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-5 py-4 flex items-start gap-3">
        <span class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"><i class="fas fa-scale-balanced"></i></span>
        <div class="text-xs"><p class="text-sm font-bold">Read this before relying on a rule</p>
            <p class="mt-1">The entries were written from the preparer's knowledge and cross-checked on <strong>19 Oct 2026</strong> against secondary sources (law-firm, tax and BPJS publications) — <strong>the official text has not been opened</strong>. Every entry stays <strong>Needs review</strong> until a person confirms it. HR, Legal or Tax must compare it with the official regulation, fill in the source link, correct any value, and then mark it <strong>Reviewed</strong>. Changing the content of an entry sends it back to Needs review. The register never changes a Payroll formula or number; the switches below only decide whether a module shows the legal basis, warns about it, or refuses to run until its rules are reviewed.</p></div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([['Entries', $counts['total'], 'book', 'blue'], ['Needs review', $counts['needs_review'], 'triangle-exclamation', 'amber'], ['Reviewed', $counts['reviewed'], 'circle-check', 'green'], ['Reviewed over 12 months ago', $counts['stale'], 'hourglass-half', 'gray']] as [$text, $n, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$tone] }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div><p class="text-2xl font-bold text-gray-800 leading-none">{{ $n }}</p><p class="text-[11px] text-gray-500 mt-1">{{ $text }}</p></div>
            </div>
        @endforeach
    </div>

    {{-- Saklar penerapan per modul --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-start gap-3">
            <span class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="fas fa-sliders"></i></span>
            <div><h3 class="text-sm font-bold text-gray-800">Where the legal basis applies</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Switch how each module uses this register. <strong>Show panel</strong> and <strong>Warn</strong> are informational. <strong>Mode</strong>: <em>Draft mode</em> lets the module run now while its rules are not reviewed — every result is flagged <strong>DRAFT</strong>; <em>Enforced</em> makes the module refuse to run (Payroll: switch on / calculate a period; Offboarding: the settlement calculator) while any of its required rules is not marked Reviewed. Leave Enforce off to keep a module behaving exactly as before.</p></div>
        </div>
        <form method="POST" action="{{ route('finance.legal-basis.switches') }}">
            @csrf
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-4 py-3">Module</th><th class="px-4 py-3">Affects</th><th class="px-4 py-3 text-center">Show panel</th><th class="px-4 py-3 text-center">Warn</th><th class="px-4 py-3">Mode</th><th class="px-4 py-3">Rules not reviewed</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @php $affects = ['pph21' => 'Payroll → PPh 21 settings, switching Payroll on, calculating a period', 'bpjs' => 'Payroll → BPJS settings, switching Payroll on, calculating a period', 'overtime' => 'Payroll → overtime rates, switching Payroll on, calculating a period', 'contract' => 'Contract (PKWT term) — information only', 'offboarding' => 'Offboarding → Final Settlement calculator']; @endphp
                        @foreach($switches as $m => $sw)
                            <tr class="align-top">
                                <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ $modules[$m] ?? $m }}</td>
                                <td class="px-4 py-3 text-[11px] text-gray-500 max-w-xs">{{ $affects[$m] ?? '' }}</td>
                                @foreach(['show_panel', 'warn_banner'] as $f)
                                    <td class="px-4 py-3 text-center"><input type="checkbox" name="sw[{{ $m }}][{{ $f }}]" value="1" class="w-4 h-4 rounded border-gray-300" {{ $sw->$f ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }}></td>
                                @endforeach
                                <td class="px-4 py-3">
                                    <select name="sw[{{ $m }}][enforce]" {{ $canEdit && $m !== 'contract' ? '' : 'disabled' }} class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs bg-white" @if($m === 'contract') title="Contract has no feature to enforce yet" @endif>
                                        <option value="" @selected(!$sw->enforce)>Draft mode — runs now, flagged DRAFT</option>
                                        <option value="1" @selected($sw->enforce)>Enforced — blocked until Reviewed</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-[11px] {{ count($pending[$m] ?? []) ? 'text-amber-700' : 'text-green-700' }}">
                                    {{ count($pending[$m] ?? []) ? implode(', ', $pending[$m]) : 'All reviewed' }}
                                    @if($canEdit && count($pending[$m] ?? []))
                                        <button type="submit" form="reviewForm-{{ $m }}" onclick="return confirm('Accept ALL rules of {{ $modules[$m] ?? $m }} as reviewed? Do this only after Legal (or the responsible person) has checked them against the official regulation.')" class="block mt-1.5 px-2.5 py-1 rounded-md border border-green-300 text-green-700 font-semibold hover:bg-green-50">Accept review (all)</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($canEdit)<div class="px-5 py-3 border-t border-gray-100 flex justify-end"><button class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm">Save switches</button></div>@endif
        </form>
        @foreach($switches as $m => $sw)
            <form id="reviewForm-{{ $m }}" method="POST" action="{{ route('finance.legal-basis.review-module', $m) }}" class="hidden">@csrf</form>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap gap-1.5">
            <a href="{{ route('finance.legal-basis.index') }}" class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $module === '' ? 'primary-gradient text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50' }}">All</a>
            @foreach($modules as $k => $v)<a href="{{ route('finance.legal-basis.index', ['module' => $k]) }}" class="px-3 py-1.5 rounded-full text-xs font-semibold {{ $module === $k ? 'primary-gradient text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50' }}">{{ $v }}</a>@endforeach
        </div>
        @if($canCreate)<button type="button" onclick="openLegal()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Add rule or new version</button>@endif
    </div>

    @forelse($rows as $mod => $list)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3.5 border-b border-gray-100"><h3 class="text-sm font-bold text-gray-800">{{ $modules[$mod] ?? $mod }}</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3">Rule</th><th class="px-4 py-3">Regulation</th><th class="px-4 py-3">In force since</th><th class="px-4 py-3">Last amended</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Confidence</th><th class="px-4 py-3 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @foreach($list as $r)
                            <tr class="align-top {{ $r->status === 'superseded' ? 'opacity-60' : '' }}">
                                <td class="px-4 py-3 font-mono text-[11px] text-gray-600 whitespace-nowrap">{{ $r->rule_key }}</td>
                                <td class="px-4 py-3"><span class="font-semibold text-gray-800">{{ $r->label }}</span><span class="block text-[11px] text-gray-500 max-w-md">{{ $r->title }}</span>
                                    @if($r->article)<span class="block text-[10px] text-gray-400 max-w-md">{{ $r->article }}</span>@endif
                                    @if($r->source_url)<a href="{{ $r->source_url }}" target="_blank" rel="noopener" class="text-[10px] primary-text hover:underline"><i class="fas fa-arrow-up-right-from-square mr-0.5"></i> source</a>@else<span class="block text-[10px] text-amber-600">No source link yet</span>@endif
                                    @if($r->notes)<span class="block text-[10px] text-gray-400 max-w-md mt-0.5 italic">{{ $r->notes }}</span>@endif</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($r->effective_date)->format('d M Y') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $r->last_amended_date ? \Illuminate\Support\Carbon::parse($r->last_amended_date)->format('d M Y') : '—' }}@if($r->amended_by)<span class="block text-[10px] text-gray-400 max-w-[14rem] whitespace-normal">{{ $r->amended_by }}</span>@endif</td>
                                <td class="px-4 py-3 whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $badges[$r->status] ?? '' }}">{{ $statuses[$r->status] ?? $r->status }}</span>
                                    @if($r->reviewed_at)<span class="block text-[10px] text-gray-400">{{ trim($r->reviewer) }} · {{ \Illuminate\Support\Carbon::parse($r->reviewed_at)->format('d M Y') }}</span>@endif</td>
                                <td class="px-4 py-3 text-[11px] font-semibold {{ $confTone[$r->confidence] ?? '' }}">{{ $confidence[$r->confidence] ?? $r->confidence }}</td>
                                <td class="px-4 py-3"><div class="flex justify-end flex-wrap gap-1.5">
                                    @if($canEdit && $r->status === 'needs_review')<form method="POST" action="{{ route('finance.legal-basis.review', $r->id) }}" data-confirm="Mark {{ $r->rule_key }} ({{ $r->label }}) as reviewed? Confirm you compared it with the official text." data-confirm-title="Mark as Reviewed" data-confirm-ok="Reviewed" data-confirm-variant="primary" data-unsaved-ignore>@csrf<button class="px-2.5 py-1.5 rounded-lg bg-green-600 text-white text-[11px] font-semibold hover:opacity-90">Mark reviewed</button></form>@endif
                                    @if($canEdit && $r->status === 'active')<form method="POST" action="{{ route('finance.legal-basis.needs-review', $r->id) }}" data-unsaved-ignore>@csrf<button class="px-2.5 py-1.5 rounded-lg border border-gray-300 text-gray-600 text-[11px] font-semibold hover:bg-gray-50">Needs review</button></form>@endif
                                    @if($canEdit && $r->status !== 'superseded')<button type="button" class="px-2.5 py-1.5 rounded-lg border border-gray-300 text-gray-700 text-[11px] font-semibold hover:bg-gray-50" data-legal="{{ json_encode($r) }}" onclick="openLegal(JSON.parse(this.dataset.legal))">Edit</button>@endif
                                    @if($canDelete && $r->status !== 'superseded')<form method="POST" action="{{ route('finance.legal-basis.supersede', $r->id) }}" data-confirm="Mark this version superseded? It is no longer used by calculators." data-confirm-title="Supersede" data-confirm-ok="Supersede" data-unsaved-ignore>@csrf<button class="px-2.5 py-1.5 rounded-lg border border-red-300 text-red-600 text-[11px] font-semibold hover:bg-red-50">Supersede</button></form>@endif
                                </div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-6 py-12 text-center text-sm text-gray-500">No legal-basis entry in this module yet.</div>
    @endforelse
</div>

@if($canCreate || $canEdit)
<div id="legalModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form id="legalForm" method="POST" class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[92vh] flex flex-col" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between"><div><h3 class="text-sm font-bold text-gray-800" id="legalTitle">Add rule or new version</h3>
            <p class="text-[11px] text-gray-400">A new effective date makes a new version; the older one stays in the history.</p></div>
            <button type="button" onclick="closeLegal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button></div>
        <div class="px-5 py-4 overflow-y-auto grid grid-cols-1 md:grid-cols-3 gap-4">
            <div><label class="{{ $label }}" for="lKey">Rule key</label><input id="lKey" name="rule_key" required maxlength="60" pattern="[a-z0-9_.]+" placeholder="e.g. bpjs.jp" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="lMod">Module</label><select id="lMod" name="module" class="{{ $input }}">@foreach($modules as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}" for="lConf">Your confidence</label><select id="lConf" name="confidence" class="{{ $input }}">@foreach($confidence as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}" for="lType">Regulation type</label><select id="lType" name="regulation_type" class="{{ $input }}">@foreach($types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}" for="lNum">Number</label><input id="lNum" name="number" required maxlength="40" placeholder="e.g. 35" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="lYear">Year</label><input id="lYear" type="number" name="year" required min="1945" max="2100" class="{{ $input }}"></div>
            <div class="md:col-span-3"><label class="{{ $label }}" for="lTitle">Title / subject</label><input id="lTitle" name="title" required maxlength="400" class="{{ $input }}"></div>
            <div class="md:col-span-3"><label class="{{ $label }}" for="lArt">Article(s)</label><input id="lArt" name="article" maxlength="400" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="lEff">In force since</label><input id="lEff" type="date" name="effective_date" required class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="lAm">Last amended</label><input id="lAm" type="date" name="last_amended_date" class="{{ $input }}"></div>
            <div><label class="{{ $label }}" for="lUrl">Official source link</label><input id="lUrl" type="url" name="source_url" maxlength="500" placeholder="https://" class="{{ $input }}"></div>
            <div class="md:col-span-3"><label class="{{ $label }}" for="lBy">Amended by</label><input id="lBy" name="amended_by" maxlength="400" placeholder="Regulation(s) that changed this one" class="{{ $input }}"></div>
            <div class="md:col-span-3"><label class="{{ $label }}" for="lPay">Calculation data (JSON)</label><textarea id="lPay" name="payload" rows="5" class="{{ $input }} font-mono text-xs" placeholder='Rates / tables read by the calculator — leave empty if none'></textarea>
                <p class="text-[10px] text-gray-400 mt-1">Changing this changes calculations that read this rule. Keep a valid JSON structure.</p></div>
            <div class="md:col-span-3"><label class="{{ $label }}" for="lNotes">Notes</label><textarea id="lNotes" name="notes" rows="2" maxlength="3000" class="{{ $input }}"></textarea></div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"><button type="button" onclick="closeLegal()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button></div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const $ = id => document.getElementById(id), modal = $('legalModal');
    const store = @json(route('finance.legal-basis.store')), base = @json(url('legal/basis'));
    window.openLegal = function (r) {
        const f = $('legalForm'); f.reset();
        $('legalTitle').textContent = r ? 'Edit ' + r.rule_key : 'Add rule or new version';
        f.action = r ? base + '/' + r.id + '/update' : store;
        if (r) {
            $('lKey').value = r.rule_key; $('lMod').value = r.module; $('lConf').value = r.confidence; $('lType').value = r.regulation_type; $('lNum').value = r.number; $('lYear').value = r.year;
            $('lTitle').value = r.title; $('lArt').value = r.article || ''; $('lEff').value = (r.effective_date || '').substring(0, 10); $('lAm').value = (r.last_amended_date || '').substring(0, 10);
            $('lUrl').value = r.source_url || ''; $('lBy').value = r.amended_by || ''; $('lNotes').value = r.notes || '';
            $('lPay').value = r.payload ? JSON.stringify(JSON.parse(r.payload), null, 2) : '';
        }
        modal.style.display = 'flex'; modal.classList.remove('hidden');
    };
    window.closeLegal = () => { modal.style.display = 'none'; modal.classList.add('hidden'); };
})();
</script>
@endpush
@endif
@endsection
