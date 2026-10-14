@extends('dashboard')
@section('title', $doc['plural'])
@section('page-title', $doc['plural'])
@section('page-subtitle', 'Issue and archive ' . strtolower($doc['plural']) . ' for external consultants.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $filterForm = 'extDocFilters';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-green-50 text-green-600'];
    $total = array_sum($counts);

    // The document types this person may create — the dialog switches between them.
    $creatable = collect($letterTypes)->filter(fn ($d) => $canDo($d['slug'], 'create'))->map(fn ($d, $key) => [
        'label' => $d['label'], 'store' => route(str_replace('.index', '.store', $d['route'])),
        'purpose' => $d['purpose'], 'help' => $d['purpose_help'], 'default' => $form['defaults'][$key] ?? [],
    ]);
    $signerRows = $form['signatories']->map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'position' => $p['position'] ?? '', 'signature' => (bool) ($p['has_signature'] ?? false)])->values();
    $reopen = $errors->any() && old('employee_id') !== null;
    $startType = $creatable->has($type) ? $type : ($creatable->keys()->first() ?? $type);
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')
    @include('hr-general.contracts.components.confirm-forms')
    @include('partials.hub-tabs', ['hubTabs' => collect($tabs)->map(fn ($t) => [
        'label' => $t['plural'], 'icon' => $t['icon'], 'route' => $t['route'], 'is' => 'general/external/' . $t['path'] . '*',
        'gate' => $t['slug'], 'strict' => true,
    ])->values()->all(), 'hubHideSingle' => true])

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 max-w-3xl">
            Every PDF made here is saved in this archive and registered as an <strong>outgoing letter</strong> in the Letter Register, with the next number of the outgoing-letter sequence.
        </p>
        <div class="flex flex-wrap gap-2">
            @if($canDo('general.external.consultants'))<a href="{{ route('general.external.consultants.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-users mr-1"></i> Consultants</a>@endif
            @if($canCreate)<button type="button" onclick="openDocModal()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Create Document</button>@endif
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones['blue'] }}"><i class="fas fa-folder-open"></i></span>
            <div><p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($total) }}</p><p class="text-[11px] text-gray-500 mt-1">All external documents</p></div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones['green'] }}"><i class="fas fa-{{ $doc['icon'] }}"></i></span>
            <div><p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($counts[$type] ?? 0) }}</p><p class="text-[11px] text-gray-500 mt-1">{{ $doc['plural'] }}</p></div>
        </div>
        <div class="col-span-2 lg:col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 bg-gray-100 text-gray-500"><i class="fas fa-shield-halved"></i></span>
            <div><p class="text-sm font-bold text-gray-800 leading-tight">Automatically archived</p><p class="text-[11px] text-gray-500 mt-1">A generated letter is kept as issued and registered as an outgoing letter.</p></div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="{{ $filterForm }}" method="GET" action="{{ route($doc['route']) }}" data-unsaved-ignore
              class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-8 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="extSearch" value="{{ $filters['search'] }}" autocomplete="off"
                       placeholder="Search consultant, consultant ID, letter number or contract" class="{{ $input }} pl-9 pr-9" aria-label="Search documents">
                <button type="button" id="extSearchClear" class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 {{ $filters['search'] === '' ? 'hidden' : '' }}" aria-label="Clear search"><i class="fas fa-xmark text-xs"></i></button>
            </div>
            <div class="md:col-span-3">
                <select name="status" class="{{ $input }}" aria-label="Status">
                    <option value="">All statuses</option>
                    <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                    <option value="signed" @selected($filters['status'] === 'signed')>Signed</option>
                    <option value="sent" @selected($filters['status'] === 'sent')>Sent</option>
                    <option value="void" @selected($filters['status'] === 'void')>Void</option>
                </select>
            </div>
            <div class="md:col-span-1 flex justify-end">
                <button type="button" id="extRefresh" class="w-full md:w-auto px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 inline-flex items-center justify-center gap-1.5" title="Refresh the list">
                    <i class="fas fa-rotate" id="extRefreshIcon"></i><span class="md:hidden">Refresh</span>
                </button>
            </div>
        </form>

        <div id="extList" class="transition-opacity" aria-live="polite">
            @include('hr-general.external.documents.components.list-table')
        </div>
    </div>
</div>

@if($canCreate)
{{-- Create document --}}
<div id="docModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        <form id="docForm" method="POST" action="{{ $creatable[$startType]['store'] ?? '' }}" class="flex flex-col min-h-0" data-unsaved-ignore>
            @csrf
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Create External Consultant Document</h3>
                    <p class="text-[11px] text-gray-400">Complete the letter details, the contract reference and the signing information before the PDF is generated.</p>
                </div>
                <button type="button" onclick="closeDocModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>

            <div class="px-5 py-4 overflow-y-auto space-y-5">
                @if($errors->any() && $reopen)
                    <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert">
                        <p class="font-semibold mb-1"><i class="fas fa-circle-exclamation mr-1"></i> The document could not be created:</p>
                        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="{{ $label }} text-gray-400">Document info</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="{{ $label }}" for="dType">Document type</label>
                            <select id="dType" class="{{ $input }}">@foreach($creatable as $key => $d)<option value="{{ $key }}">{{ $d['label'] }}</option>@endforeach</select></div>
                        <div><label class="{{ $label }}" for="dDate">Document date</label><input type="date" id="dDate" name="letter_date" value="{{ old('letter_date', now()->toDateString()) }}" class="{{ $input }}" required></div>
                        <div><label class="{{ $label }}" for="dNumber">Reference no.</label><input id="dNumber" name="letter_number" maxlength="100" value="{{ old('letter_number') }}" placeholder="Leave blank for automatic numbering" class="{{ $input }}">
                            <p class="text-[10px] text-gray-400 mt-1">A number from the outgoing-letter sequence is assigned automatically when left blank.</p></div>
                        <div><label class="{{ $label }}" for="dTo">Addressed to</label><input id="dTo" name="addressed_to" maxlength="255" value="{{ old('addressed_to') }}" placeholder="Recipient name or institution" class="{{ $input }}"></div>
                        <div class="md:col-span-2"><label class="{{ $label }}" for="dPurpose"><span id="dPurposeLabel">Purpose</span></label><input id="dPurpose" name="purpose" maxlength="5000" value="{{ old('purpose') }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="dLang">Language</label>
                            <select id="dLang" name="language" class="{{ $input }}">@foreach($form['languages'] as $code => $name)<option value="{{ $code }}" @selected(old('language') === $code)>{{ $name }}</option>@endforeach</select></div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="{{ $label }} text-gray-400">Consultant data</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="{{ $label }}" for="dConsultant">External consultant</label>
                            <select id="dConsultant" name="employee_id" class="{{ $input }}" required>
                                <option value="">Select consultant</option>
                                @foreach($consultants as $opt)<option value="{{ $opt['id'] }}" @selected((string) old('employee_id', $openConsultant ?: '') === (string) $opt['id'])>{{ $opt['eci'] }} — {{ $opt['name'] }}</option>@endforeach
                            </select></div>
                        <div><label class="{{ $label }}" for="dContract">Related EXT contract</label>
                            <select id="dContract" name="contract_no" class="{{ $input }}"><option value="">No contract reference</option></select>
                            <p class="text-[10px] text-gray-400 mt-1">Select a contract if the letter must reference an active engagement period.</p></div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="{{ $label }} text-gray-400">Assignment &amp; authorization</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div><label class="{{ $label }}" for="dLoc">Assignment location</label><input id="dLoc" name="location" maxlength="255" value="{{ old('location') }}" placeholder="Project / client / city" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="dFrom">Assignment start</label><input type="date" id="dFrom" name="period_from" value="{{ old('period_from') }}" class="{{ $input }}"></div>
                        <div><label class="{{ $label }}" for="dUntil">Assignment end</label><input type="date" id="dUntil" name="period_to" value="{{ old('period_to') }}" class="{{ $input }}"></div>
                        <div class="md:col-span-1"><label class="{{ $label }}" for="dSigner">Signer</label>
                            <select id="dSigner" name="signatory_employee_id" class="{{ $input }}" required>
                                @foreach($signerRows as $p)<option value="{{ $p['id'] }}" @selected((string) old('signatory_employee_id', $form['signer']['id'] ?? '') === (string) $p['id'])>{{ $p['name'] }}</option>@endforeach
                            </select></div>
                        <div class="md:col-span-2"><label class="{{ $label }}" for="dTitle">Signer position</label><input id="dTitle" name="signatory_title" maxlength="150" value="{{ old('signatory_title', $form['signer']['position'] ?? '') }}" class="{{ $input }}" required>
                            <p class="text-[10px] text-gray-400 mt-1">Filled from the signer; you can change what is printed under the signature.</p></div>
                        <div class="md:col-span-3">
                            <label class="inline-flex items-center gap-2 text-xs text-gray-700"><input type="checkbox" name="sign_now" value="1" class="rounded" @checked(old('sign_now', !$errors->any() || !$reopen))> Sign with the signer's master-data signature (when they have one)</label>
                        </div>
                        <div class="md:col-span-3"><label class="{{ $label }}" for="dNotes">Archive note</label><textarea id="dNotes" name="notes" rows="2" maxlength="2000" class="{{ $input }}">{{ old('notes') }}</textarea></div>
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeDocModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><i class="far fa-file-pdf mr-1"></i> Generate &amp; Archive PDF</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
(function () {
    // ── Live filters ──
    const form = document.getElementById(@json($filterForm));
    const list = document.getElementById('extList');
    const search = document.getElementById('extSearch');
    const clear = document.getElementById('extSearchClear');
    const icon = document.getElementById('extRefreshIcon');
    let timer = null, controller = null;

    function query(extra) {
        const map = new Map();
        new FormData(form).forEach((v, k) => map.set(k, v));
        Object.entries(extra || {}).forEach(([k, v]) => map.set(k, v));
        const q = new URLSearchParams();
        map.forEach((v, k) => { if (String(v) !== '') q.set(k, v); });
        return q.toString();
    }
    async function load(qs, push = true) {
        if (controller) controller.abort();
        controller = new AbortController();
        list.classList.add('opacity-50'); icon.classList.add('fa-spin');
        const url = form.action + (qs ? '?' + qs : '');
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            list.innerHTML = await res.text();
            if (push) history.pushState({ qs }, '', url);
        } catch (e) {
            if (e.name !== 'AbortError') window.showNotification('The list could not be refreshed. Please try again.', 'error');
        } finally {
            list.classList.remove('opacity-50'); icon.classList.remove('fa-spin');
        }
    }
    const reload = () => load(query({ page: '' }));
    search.addEventListener('input', () => { clear.classList.toggle('hidden', search.value === ''); clearTimeout(timer); timer = setTimeout(reload, 300); });
    form.addEventListener('change', e => { if (e.target.matches('select')) reload(); });
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); reload(); });
    clear.addEventListener('click', () => { search.value = ''; clear.classList.add('hidden'); search.focus(); reload(); });
    document.getElementById('extRefresh').addEventListener('click', () => { clearTimeout(timer); load(query()); });
    list.addEventListener('click', e => {
        const a = e.target.closest('a[href*="page="]');
        if (!a || e.metaKey || e.ctrlKey) return;
        e.preventDefault();
        load(new URL(a.href).searchParams.toString());
    });
    window.addEventListener('popstate', () => load(location.search.replace(/^\?/, ''), false));

    // A generated letter opens in a new tab.
    @if(session('openPdf'))
        window.open(@json(session('openPdf')), '_blank');
    @endif

    @if($canCreate)
    // ── Create dialog ──
    const $ = id => document.getElementById(id);
    const modal = $('docModal'), docForm = $('docForm');
    const types = @json($creatable);
    const consultants = @json(collect($consultants)->keyBy('id'));
    const contracts = @json($contracts);
    const signers = @json($signerRows->keyBy('id'));
    const reopen = @json($reopen);

    function applyType(key, keepLanguage) {
        const t = types[key]; if (!t) return;
        docForm.action = t.store;
        $('dType').value = key;
        $('dPurposeLabel').textContent = t.purpose;
        $('dPurpose').placeholder = t.help;
        $('dPurpose').required = key !== 'reference';
        const assignment = key === 'assignment';
        ['dLoc', 'dFrom', 'dUntil'].forEach(id => { $(id).required = assignment; });
        if (!keepLanguage && t.default && t.default.language) $('dLang').value = t.default.language;
    }

    function applyConsultant(keepTyped) {
        const id = $('dConsultant').value, c = consultants[id];
        const select = $('dContract'), previous = select.value;
        select.innerHTML = '<option value="">No contract reference</option>' + (contracts[id] || []).map(r => `<option value="${r.no}">${r.label}</option>`).join('');
        if (keepTyped && previous) select.value = previous;
        if (!c || keepTyped) return;
        // Starting values from the engagement; everything stays editable.
        if (!$('dLoc').value) $('dLoc').value = c.client || '';
        if (!$('dFrom').value) $('dFrom').value = c.start || '';
        if (!$('dUntil').value) $('dUntil').value = c.end || '';
    }

    function applySigner(keepTitle) {
        const p = signers[$('dSigner').value];
        if (p && !keepTitle) $('dTitle').value = p.position || '';
    }

    window.openDocModal = function () { applyType($('dType').value || @json($startType), true); modal.style.display = 'flex'; modal.classList.remove('hidden'); };
    window.closeDocModal = function () { modal.style.display = 'none'; modal.classList.add('hidden'); };
    modal.addEventListener('click', e => { if (e.target === modal) closeDocModal(); });
    $('dType').addEventListener('change', e => applyType(e.target.value, false));
    $('dConsultant').addEventListener('change', () => applyConsultant(false));
    $('dSigner').addEventListener('change', () => applySigner(false));

    // Start: the page's own document type; with ?consultant=ID (from a consultant's Quick Actions) the dialog opens at once;
    // after a refused save it opens again with what was typed.
    applyType(@json($startType), reopen);
    applyConsultant(reopen);
    @if($reopen)
        $('dContract').value = @json(old('contract_no', ''));
        openDocModal();
    @elseif($openConsultant)
        applyConsultant(false);
        openDocModal();
    @endif
    @endif
})();
</script>
@endpush
@endsection
