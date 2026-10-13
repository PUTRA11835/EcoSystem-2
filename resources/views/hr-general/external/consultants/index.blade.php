@extends('dashboard')
@section('title', 'External Consultants')
@section('page-title', 'External Consultants')
@section('page-subtitle', 'Profiles, engagement schemes, rates and assignments of external consultants — read from the employee master data.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $filterForm = 'consultantFilters';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-green-50 text-green-600', 'amber' => 'bg-amber-50 text-amber-600', 'gray' => 'bg-gray-100 text-gray-500'];
    // Each card filters the list when clicked (the same filters as the form below).
    $cards = [
        ['Total consultants', $summary['total'], 'users', 'blue', []],
        ['Active', $summary['active'], 'user-check', 'green', ['status' => 'active']],
        ['Mandays', $summary['mandays'], 'calendar-day', 'blue', ['scheme' => 'mandays']],
        ['Monthly', $summary['monthly'], 'calendar-days', 'amber', ['scheme' => 'monthly']],
        ['Other / not set', $summary['other'] + $summary['unset'], 'circle-question', 'gray', ['scheme' => 'other']],
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('partials.accent-remap')

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 max-w-3xl">
            Consultants are the <strong>External</strong> employees of the master data. Their profile and engagement are changed in <strong>Employee Data</strong>; this page reads them from there, so there is still one source.
        </p>
        <div class="flex flex-wrap gap-2">
            @if($canDo('general.external.timesheet'))<a href="{{ route('general.external.timesheet.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-clock mr-1"></i> Timesheet &amp; Payment</a>@endif
            @if($canDo('general.external.contracts'))<a href="{{ route('general.external.contracts.index') }}" class="px-3 py-1.5 rounded-full border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50"><i class="fas fa-file-contract mr-1"></i> External Contract</a>@endif
            @if($canDo('master.employee'))<a href="{{ route('master.employee.index') }}" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Consultant</a>@endif
        </div>
    </div>

    {{-- Summary: compact cards like the dashboards; click one to filter --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @foreach($cards as [$label, $count, $icon, $tone, $query])
            @php
                $active = $query !== [] && collect($query)->every(fn ($v, $k) => ($filters[$k] ?? '') === $v);
                $url = route('general.external.consultants.index', $query);
            @endphp
            <a href="{{ $url }}"
               class="bg-white rounded-xl border shadow-sm p-4 flex items-center gap-3 hover:shadow transition {{ $active ? 'border-indigo-300 ring-2 ring-indigo-200' : 'border-gray-200' }}">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$tone] }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div class="min-w-0">
                    <p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($count) }}</p>
                    <p class="text-[11px] text-gray-500 mt-1">{{ $label }}</p>
                </div>
            </a>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="{{ $filterForm }}" method="GET" action="{{ route('general.external.consultants.index') }}" data-unsaved-ignore
              class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-5 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="extSearch" value="{{ $filters['search'] }}" autocomplete="off"
                       placeholder="Search name, consultant ID, vendor, client or assignment"
                       class="{{ $input }} pl-9 pr-9" aria-label="Search consultants">
                <button type="button" id="extSearchClear" class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 {{ $filters['search'] === '' ? 'hidden' : '' }}" aria-label="Clear search"><i class="fas fa-xmark text-xs"></i></button>
            </div>
            <div class="md:col-span-3">
                <select name="scheme" class="{{ $input }}" aria-label="Scheme">
                    <option value="">All schemes</option>
                    @foreach($schemes as $value => $label)<option value="{{ $value }}" @selected($filters['scheme'] === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <select name="status" class="{{ $input }}" aria-label="Status">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="md:col-span-1 flex justify-end">
                <button type="button" id="extRefresh" class="w-full md:w-auto px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 inline-flex items-center justify-center gap-1.5" title="Refresh the list">
                    <i class="fas fa-rotate" id="extRefreshIcon"></i><span class="md:hidden">Refresh</span>
                </button>
            </div>
        </form>

        <div id="extList" class="transition-opacity" aria-live="polite">
            @include('hr-general.external.consultants.components.list-table')
        </div>
    </div>
</div>

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
})();
</script>
@endpush
@endsection
