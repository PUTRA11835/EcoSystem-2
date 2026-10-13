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
        ['Other / not set', $summary['other'] + $summary['unset'], 'circle-question', 'gray', ['scheme' => 'other_unset']],
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
            @if($canDo('general.external.consultants', 'create'))<button type="button" id="addConsultantBtn" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Add Consultant</button>@endif
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

@if($canDo('general.external.consultants', 'create'))
{{-- Add Consultant: membuat akun konsultan (Employee Group = EXTERNAL), lalu membuka halaman edit untuk melengkapi datanya. --}}
<div id="addConsultantModal" class="hidden fixed inset-0 z-50 items-start justify-center overflow-y-auto bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="acTitle">
    <form id="addConsultantForm" class="bg-white rounded-xl shadow-xl w-full max-w-xl my-8" autocomplete="off">
        <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
            <div><h3 class="text-base font-bold text-gray-900" id="acTitle">Add consultant</h3>
                <p class="text-xs text-gray-500">Creates the consultant account. You complete the profile, engagement and documents on the next page.</p></div>
            <button type="button" class="ac-close text-gray-400 hover:text-gray-700 text-xl leading-none" aria-label="Close">&times;</button>
        </div>
        <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
            @php $lbl = 'block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1'; @endphp
            <div><label class="{{ $lbl }}">Consultant ID (ECI) *</label><input name="eci" required maxlength="50" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">Work email *</label><input name="email_work" type="email" required maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">First name *</label><input name="first_name" required maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">Last name</label><input name="last_name" maxlength="255" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">Nick name *</label><input name="nick_name" required maxlength="100" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">Mobile phone</label><input name="cell_phone" maxlength="50" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">Password *</label><input name="password" type="password" required minlength="6" autocomplete="new-password" class="{{ $input }}"></div>
            <div><label class="{{ $lbl }}">Confirm password *</label><input name="password_confirmation" type="password" required minlength="6" autocomplete="new-password" class="{{ $input }}"></div>
            <p id="acError" class="sm:col-span-2 hidden text-xs text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></p>
        </div>
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50 rounded-b-xl">
            <button type="button" class="ac-close px-4 py-2 text-sm font-semibold rounded-lg border border-gray-300 text-gray-700 hover:bg-white">Cancel</button>
            <button type="submit" id="acSave" class="px-4 py-2 text-sm font-semibold rounded-lg primary-gradient text-white hover:opacity-90"><i class="fas fa-check mr-2"></i>Create consultant</button>
        </div>
    </form>
</div>
<script>
(function () {
    var modal = document.getElementById('addConsultantModal'), form = document.getElementById('addConsultantForm');
    if (!modal) return;
    var open = function () { form.reset(); document.getElementById('acError').classList.add('hidden'); modal.classList.remove('hidden'); modal.classList.add('flex'); form.eci.focus(); };
    var close = function () { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.getElementById('addConsultantBtn').addEventListener('click', open);
    modal.querySelectorAll('.ac-close').forEach(function (b) { b.addEventListener('click', close); });
    modal.addEventListener('mousedown', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('acSave'), err = document.getElementById('acError');
        btn.disabled = true; err.classList.add('hidden');
        fetch(@json(route('general.external.consultants.store')), {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                       'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            body: JSON.stringify(Object.fromEntries(new FormData(form).entries()))
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
              if (res.ok && res.j.success) {
                  if (window.showNotification) showNotification('Consultant created. Complete the profile on the next page.', 'success');
                  window.location.href = @json(url('/general/external/consultants')) + '/' + res.j.data.employee_id + '/edit';
              } else {
                  err.textContent = res.j.message || 'Could not create the consultant.'; err.classList.remove('hidden'); btn.disabled = false;
              }
          }).catch(function () { err.textContent = 'Network error. Please try again.'; err.classList.remove('hidden'); btn.disabled = false; });
    });
})();
</script>
@endif
@endsection
