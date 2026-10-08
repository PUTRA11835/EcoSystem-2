@extends('dashboard')
@section('title', 'Contracts')
@section('page-title', 'Contract')
@section('page-subtitle', 'Manage PKWT, PKWTT and external consultant contracts for each employee in one place.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $filterForm = 'contractFilters';
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach([
            ['Employees', $summary['employees'], 'users', 'primary-text'],
            ['Active contracts', $summary['active'], 'circle-check', 'text-green-600'],
            ['Ending within ' . config('hc_contract.expiring_soon_days', 30) . ' days', $summary['ending'], 'hourglass-half', 'text-amber-600'],
            ['Without contract', $summary['none'], 'file-circle-xmark', 'text-gray-500'],
        ] as [$label, $count, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 flex items-center gap-3">
                <span class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center {{ $tone }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div>
                    <p class="text-lg font-bold text-gray-800 leading-tight">{{ number_format($count) }}</p>
                    <p class="text-[11px] text-gray-500">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {{-- Live filters: typing and the two selects refresh the list by themselves; the button re-reads it. --}}
        <form id="{{ $filterForm }}" method="GET" action="{{ route('general.contracts.list') }}" data-unsaved-ignore
              class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-6 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="contractSearch" value="{{ $filters['search'] }}" autocomplete="off"
                       placeholder="Search name, ECI, NIK, contract number, position or department"
                       class="{{ $input }} pl-9 pr-9" aria-label="Search contracts">
                <button type="button" id="contractSearchClear" class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 {{ $filters['search'] === '' ? 'hidden' : '' }}" aria-label="Clear search"><i class="fas fa-xmark text-xs"></i></button>
            </div>
            <div class="md:col-span-3">
                <select name="type" class="{{ $input }}" aria-label="Contract type">
                    <option value="">All contract types</option>
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-2">
                <select name="status" class="{{ $input }}" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-1 flex justify-end">
                <button type="button" id="contractRefresh" class="w-full md:w-auto px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 inline-flex items-center justify-center gap-1.5" title="Refresh the list">
                    <i class="fas fa-rotate" id="contractRefreshIcon"></i><span class="md:hidden">Refresh</span>
                </button>
            </div>
        </form>

        <div id="contractList" class="transition-opacity" aria-live="polite">
            @include('hr-general.contracts.components.list-table')
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById(@json($filterForm));
    const list = document.getElementById('contractList');
    const search = document.getElementById('contractSearch');
    const clear = document.getElementById('contractSearchClear');
    const icon = document.getElementById('contractRefreshIcon');
    let timer = null, controller = null;

    // The form's fields as a query string; fields with the same name (rows per page sits outside the form tag) keep the last value.
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
        list.classList.add('opacity-50');
        icon.classList.add('fa-spin');
        const url = form.action + (qs ? '?' + qs : '');
        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            list.innerHTML = await res.text();
            if (push) history.pushState({ qs }, '', url);
        } catch (e) {
            if (e.name !== 'AbortError') window.showNotification('The list could not be refreshed. Please try again.', 'error');
        } finally {
            list.classList.remove('opacity-50');
            icon.classList.remove('fa-spin');
        }
    }

    const reload = () => load(query({ page: '' }));

    // Typing waits for a short pause; the selects and Enter apply at once.
    search.addEventListener('input', () => { clear.classList.toggle('hidden', search.value === ''); clearTimeout(timer); timer = setTimeout(reload, 300); });
    form.addEventListener('change', e => { if (e.target.matches('select')) reload(); });
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); reload(); });
    clear.addEventListener('click', () => { search.value = ''; clear.classList.add('hidden'); search.focus(); reload(); });
    document.getElementById('contractRefresh').addEventListener('click', () => { clearTimeout(timer); load(query()); });

    // Pagination links stay inside the list (no full page reload).
    list.addEventListener('click', e => {
        const a = e.target.closest('a[href*="page="]');
        if (!a || e.metaKey || e.ctrlKey) return;
        e.preventDefault();
        load(new URL(a.href).searchParams.toString());
    });
    window.addEventListener('popstate', () => load(location.search.replace(/^\?/, ''), false));
})();
</script>
@endpush
@endsection
