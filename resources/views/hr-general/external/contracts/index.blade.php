@extends('dashboard')
@section('title', 'External Contract')
@section('page-title', 'External Contract')
@section('page-subtitle', 'Manage external consultant agreements using the reusable contract templates.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $filterForm = 'extContractFilters';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-green-50 text-green-600', 'amber' => 'bg-amber-50 text-amber-600', 'sky' => 'bg-sky-50 text-sky-600'];
    $canCreate = $canDo('general.external.contracts', 'create');
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')
    @include('hr-general.recruitment.components.form-errors')
    @include('hr-general.contracts.components.confirm-forms')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 max-w-3xl">
            An external contract is a <strong>PKS</strong> of the Contract menu: same numbering, templates and PDF. Choose a consultant to prepare a new one, or open a row to view, print or edit it.
        </p>
        <div class="flex flex-wrap gap-2">
            @if($canTemplates)<a href="{{ route('general.contracts.templates.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-file-lines mr-1"></i> Contract Templates</a>@endif
            @if($canCreate)<button type="button" onclick="openExtModal()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Contract</button>@endif
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['All contracts', $summary['total'], 'file-contract', 'blue'],
            ['Active', $summary['active'], 'circle-check', 'green'],
            ['Draft', $summary['draft'], 'pen-to-square', 'amber'],
            ['Using a template', $summary['templated'], 'file-lines', 'sky'],
        ] as [$label, $count, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$tone] }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($count) }}</p>
                    <p class="text-[11px] text-gray-500 mt-1">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="{{ $filterForm }}" method="GET" action="{{ route('general.external.contracts.index') }}" data-unsaved-ignore
              class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-6 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="extSearch" value="{{ $filters['search'] }}" autocomplete="off"
                       placeholder="Search name, consultant ID or contract number" class="{{ $input }} pl-9 pr-9" aria-label="Search contracts">
                <button type="button" id="extSearchClear" class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 {{ $filters['search'] === '' ? 'hidden' : '' }}" aria-label="Clear search"><i class="fas fa-xmark text-xs"></i></button>
            </div>
            <div class="md:col-span-3">
                <select name="scheme" class="{{ $input }}" aria-label="Scheme">
                    <option value="">All schemes</option>
                    @foreach($schemes as $value => $label)<option value="{{ $value }}" @selected($filters['scheme'] === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <select name="status" class="{{ $input }}" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-1 flex justify-end">
                <button type="button" id="extRefresh" class="w-full md:w-auto px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 inline-flex items-center justify-center gap-1.5" title="Refresh the list">
                    <i class="fas fa-rotate" id="extRefreshIcon"></i><span class="md:hidden">Refresh</span>
                </button>
            </div>
        </form>

        <div id="extList" class="transition-opacity" aria-live="polite">
            @include('hr-general.external.contracts.components.list-table')
        </div>
    </div>
</div>

{{-- Add Contract: choose the consultant, then continue to the new-contract form of that consultant --}}
@if($canCreate)
<div id="extModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-800">Add External Contract</h3>
            <button type="button" onclick="closeExtModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="px-5 py-4">
            <label for="extConsultant" class="block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1">External consultant <span class="text-red-500">*</span></label>
            <select id="extConsultant" class="{{ $input }}">
                <option value="">Select a consultant</option>
                @foreach($consultants as $opt)
                    <option value="{{ $opt['id'] }}">{{ $opt['eci'] }} — {{ $opt['name'] }}@if($opt['role']) — {{ $opt['role'] }}@endif</option>
                @endforeach
            </select>
            <p class="text-[11px] text-gray-400 mt-1.5">You will be taken to the new contract form for the selected consultant.</p>
            <p id="extConsultantError" class="hidden text-[11px] text-red-600 mt-1">Select a consultant first.</p>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
            <button type="button" onclick="closeExtModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
            <button type="button" id="extContinue" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90"><i class="fas fa-arrow-right mr-1"></i> Continue</button>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
(function () {
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

    // Add Contract dialog
    const modal = document.getElementById('extModal');
    if (modal) {
        const base = @json(url('general/external/contracts/consultant'));
        const select = document.getElementById('extConsultant');
        const error = document.getElementById('extConsultantError');
        window.openExtModal = () => { select.value = ''; error.classList.add('hidden'); modal.style.display = 'flex'; modal.classList.remove('hidden'); select.focus(); };
        window.closeExtModal = () => { modal.style.display = 'none'; modal.classList.add('hidden'); };
        document.getElementById('extContinue').addEventListener('click', () => {
            if (!select.value) { error.classList.remove('hidden'); return; }
            location.href = base + '/' + select.value + '#new-contract';
        });
        modal.addEventListener('click', e => { if (e.target === modal) closeExtModal(); });
    }
})();
</script>
@endpush
@endsection
