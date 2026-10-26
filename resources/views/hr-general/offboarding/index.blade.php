@extends('dashboard')
@section('title', 'Offboarding')
@section('page-title', 'Offboarding')
@section('page-subtitle', 'Monitor resignations, retirements, terminations and the end of engagements, with their checklist and final settlement.')

@php
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'sky' => 'bg-sky-50 text-sky-600', 'red' => 'bg-red-50 text-red-600', 'green' => 'bg-green-50 text-green-600'];
    $cards = [
        ['In progress', $summary['in_progress'], 'user-clock', 'blue'],
        ['Effective within 7 days', $summary['due_week'], 'calendar-week', 'sky'],
        ['With overdue tasks', $summary['overdue'], 'triangle-exclamation', 'red'],
        ['Completed', $summary['completed'], 'circle-check', 'green'],
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.offboarding.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    @unless($enabled)
        <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl px-5 py-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"><i class="fas fa-power-off"></i></span>
                <div><p class="text-sm font-bold">Offboarding is switched off</p>
                    <p class="text-xs mt-0.5">Existing records stay visible, but no new offboarding can be started and nothing runs automatically until it is switched on.</p></div>
            </div>
            @if($canSettings)<a href="{{ route('general.offboarding.settings.index') }}" class="px-3 py-1.5 rounded-lg bg-white border border-amber-300 text-xs font-semibold text-amber-900 hover:bg-amber-100">Open Settings</a>@endif
        </div>
    @endunless

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 max-w-3xl">A person leaves in four steps: <strong>checklist</strong> → <strong>final settlement</strong> (employees) → <strong>access deactivated</strong> on the effective date → <strong>completed</strong>, which closes the contract.</p>
        @if($canCreate)<button type="button" onclick="openOffModal()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> Start Offboarding</button>@endif
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($cards as [$text, $count, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$tone] }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div class="min-w-0"><p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($count) }}</p><p class="text-[11px] text-gray-500 mt-1">{{ $text }}</p></div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="{{ $filterForm }}" method="GET" action="{{ route('general.offboarding.index') }}" data-unsaved-ignore class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-5 relative"><i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="offSearch" value="{{ $filters['search'] }}" autocomplete="off" placeholder="Search name, employee ID or position" class="{{ $input }} pl-9" aria-label="Search"></div>
            <div class="md:col-span-3"><select name="type" class="{{ $input }}" aria-label="Type"><option value="">All types</option>@foreach($types as $k => $v)<option value="{{ $k }}" @selected($filters['type'] === $k)>{{ $v }}</option>@endforeach</select></div>
            <div class="md:col-span-3"><select name="status" class="{{ $input }}" aria-label="Status"><option value="">All statuses</option>
                <option value="in_progress" @selected($filters['status'] === 'in_progress')>In progress</option><option value="completed" @selected($filters['status'] === 'completed')>Completed</option><option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option></select></div>
            <div class="md:col-span-1 flex justify-end"><button type="button" id="offRefresh" class="w-full md:w-auto px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90" title="Refresh the list"><i class="fas fa-rotate" id="offRefreshIcon"></i></button></div>
        </form>
        <div id="offList" class="transition-opacity" aria-live="polite">@include('hr-general.offboarding.components.list-table')</div>
    </div>
</div>

@if($canCreate)
<div id="offModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form method="POST" action="{{ route('general.offboarding.store') }}" class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[92vh] flex flex-col" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><div><h3 class="text-sm font-bold text-gray-800">Start Offboarding</h3><p class="text-[11px] text-gray-400">The checklist is created from the task templates in Settings.</p></div>
            <button type="button" onclick="closeOffModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button></div>
        <div class="px-5 py-4 space-y-3 overflow-y-auto">
            <div><label class="{{ $label }}" for="oEmployee">Employee or consultant</label>
                <select id="oEmployee" name="employee_id" required data-searchable="true" data-search-placeholder="Search name or employee ID…" class="{{ $input }}"><option value="">Select a person</option>
                    @foreach($employees as $e)<option value="{{ $e['id'] }}" data-kind="{{ $e['kind'] }}" @selected((string) old('employee_id') === (string) $e['id'])>{{ $e['eci'] }} — {{ $e['name'] }} · {{ $e['kind'] }}</option>@endforeach</select></div>
            <div><label class="{{ $label }}" for="oType">Offboarding type</label><select id="oType" name="offboarding_type" required class="{{ $input }}"><option value="">Select a person first</option></select></div>
            <div id="oReasonWrap" class="hidden"><label class="{{ $label }}" for="oReason">Reason for the termination</label>
                <select id="oReason" name="reason_code" class="{{ $input }}">@foreach($terminationReasons as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="{{ $label }}" for="oEffective">Effective date</label><input type="date" id="oEffective" name="effective_date" required value="{{ old('effective_date') }}" class="{{ $input }}"></div>
                <div><label class="{{ $label }}" for="oLast">Last working day</label><input type="date" id="oLast" name="last_working_day" value="{{ old('last_working_day') }}" class="{{ $input }}"></div>
            </div>
            <div><label class="{{ $label }}" for="oNotes">Notes</label><textarea id="oNotes" name="notes" rows="2" maxlength="2000" class="{{ $input }}">{{ old('notes') }}</textarea></div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2"><button type="button" onclick="closeOffModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button><button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Start &amp; open</button></div>
    </form>
</div>
@endif

@push('scripts')
<script>
(function () {
    const form = document.getElementById(@json($filterForm)), list = document.getElementById('offList'), icon = document.getElementById('offRefreshIcon');
    let timer = null, controller = null;
    const query = extra => { const m = new Map(); new FormData(form).forEach((v, k) => m.set(k, v)); Object.entries(extra || {}).forEach(([k, v]) => m.set(k, v)); const q = new URLSearchParams(); m.forEach((v, k) => { if (String(v) !== '') q.set(k, v); }); return q.toString(); };
    async function load(qs, push = true) {
        if (controller) controller.abort(); controller = new AbortController();
        list.classList.add('opacity-50'); icon.classList.add('fa-spin');
        const url = form.action + (qs ? '?' + qs : '');
        try { const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' }); if (!res.ok) throw new Error(res.status); list.innerHTML = await res.text(); if (push) history.pushState({ qs }, '', url); }
        catch (e) { if (e.name !== 'AbortError') window.showNotification('The list could not be refreshed. Please try again.', 'error'); }
        finally { list.classList.remove('opacity-50'); icon.classList.remove('fa-spin'); }
    }
    const reload = () => load(query({ page: '' }));
    document.getElementById('offSearch').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(reload, 300); });
    form.addEventListener('change', e => { if (e.target.matches('select')) reload(); });
    form.addEventListener('submit', e => { e.preventDefault(); reload(); });
    document.getElementById('offRefresh').addEventListener('click', () => load(query()));
    list.addEventListener('click', e => { const a = e.target.closest('a[href*="page="]'); if (!a || e.metaKey || e.ctrlKey) return; e.preventDefault(); load(new URL(a.href).searchParams.toString()); });
    window.addEventListener('popstate', () => load(location.search.replace(/^\?/, ''), false));

    @if($canCreate)
    const modal = document.getElementById('offModal'), emp = document.getElementById('oEmployee'), type = document.getElementById('oType'), wrap = document.getElementById('oReasonWrap');
    const byKind = @json($typesByKind), names = @json($types);
    function fillTypes() {
        const kind = emp.selectedOptions[0]?.dataset.kind;
        type.innerHTML = kind ? '<option value="">Select a type</option>' + byKind[kind].map(k => `<option value="${k}">${names[k]}</option>`).join('') : '<option value="">Select a person first</option>';
        wrap.classList.add('hidden');
    }
    emp.addEventListener('change', fillTypes);
    type.addEventListener('change', () => wrap.classList.toggle('hidden', type.value !== 'termination' || emp.selectedOptions[0]?.dataset.kind !== 'Internal'));
    window.openOffModal = () => { modal.style.display = 'flex'; modal.classList.remove('hidden'); };
    window.closeOffModal = () => { modal.style.display = 'none'; modal.classList.add('hidden'); };
    @if($errors->has('offboarding') && old('employee_id'))
        emp.value = @json((string) old('employee_id')); fillTypes(); type.value = @json((string) old('offboarding_type')); openOffModal();
    @endif
    @endif
})();
</script>
@endpush
@endsection
