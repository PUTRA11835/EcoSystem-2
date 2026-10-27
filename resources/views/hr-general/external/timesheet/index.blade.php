@extends('dashboard')
@section('title', $invoiceView ? 'Invoices & Payments' : 'Timesheet & Payment')
@section('page-title', 'Timesheet & Payment')
@section('page-subtitle', 'Approval by the Head of RPMO and invoice verification by Finance for external consultants.')

@php
    use App\Support\External\ExternalTimesheetRules as R;

    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 bg-white';
    $tones = ['blue' => 'bg-blue-50 text-blue-600', 'green' => 'bg-green-50 text-green-600', 'amber' => 'bg-amber-50 text-amber-600', 'sky' => 'bg-sky-50 text-sky-600', 'gray' => 'bg-gray-100 text-gray-500'];
    $cards = $invoiceView ? [
        ['Awaiting invoice', $summary['awaiting_invoice'], 'hourglass-half', 'amber'],
        ['To verify', $summary['to_verify'], 'file-circle-question', 'blue'],
        ['Verified, to pay', $summary['to_pay'], 'circle-check', 'sky'],
        ['Paid', $summary['paid'], 'money-check-dollar', 'green'],
    ] : [
        ['All timesheets', $summary['total'], 'clock', 'blue'],
        ['Waiting approval', $summary['submitted'], 'user-clock', 'sky'],
        ['Returned', $summary['returned'], 'rotate-left', 'amber'],
        ['Approved', $summary['approved'], 'circle-check', 'green'],
        ['Draft', $summary['draft'], 'pen-to-square', 'gray'],
    ];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.external.timesheet.components.tabs')
    @include('hr-general.recruitment.components.form-errors')
    @if(session('success'))<div class="text-xs bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3"><i class="fas fa-circle-check mr-1"></i> {{ session('success') }}</div>@endif

    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500 max-w-3xl">
            @if($invoiceView) Approved timesheets and their invoices. Open one to verify the invoice and record the payment.
            @else Open a timesheet to review its hours, approve it or return it for correction. Consultants fill their own timesheet in <strong>My Timesheet &amp; Invoice</strong>. @endif
        </p>
        @if($canCreate)<button type="button" onclick="openSheetModal()" class="px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm"><i class="fas fa-plus mr-1"></i> New Timesheet</button>@endif
    </div>

    <div class="grid grid-cols-2 {{ count($cards) === 5 ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }} gap-4">
        @foreach($cards as [$label, $count, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tones[$tone] }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div class="min-w-0"><p class="text-2xl font-bold text-gray-800 leading-none">{{ number_format($count) }}</p><p class="text-[11px] text-gray-500 mt-1">{{ $label }}</p></div>
            </div>
        @endforeach
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <form id="{{ $filterForm }}" method="GET" action="{{ $invoiceView ? route('general.external.timesheet.invoices') : route('general.external.timesheet.index') }}" data-unsaved-ignore
              class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="md:col-span-4 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="search" name="search" id="extSearch" value="{{ $filters['search'] }}" autocomplete="off" placeholder="Search consultant, ID, timesheet, contract or invoice number" class="{{ $input }} pl-9" aria-label="Search">
            </div>
            <div class="md:col-span-3"><input type="month" name="month" value="{{ $filters['month'] }}" class="{{ $input }}" aria-label="Month"></div>
            <div class="md:col-span-3">
                @if($invoiceView)
                    <select name="invoice" class="{{ $input }}" aria-label="Invoice status"><option value="">All invoice statuses</option>@foreach(R::INVOICE_LABELS as $k => $v)<option value="{{ $k }}" @selected($filters['invoice'] === $k)>{{ $v }}</option>@endforeach</select>
                @else
                    <select name="status" class="{{ $input }}" aria-label="Status"><option value="">All statuses</option>@foreach(R::STATUS_LABELS as $k => $v)<option value="{{ $k }}" @selected($filters['status'] === $k)>{{ $v }}</option>@endforeach</select>
                @endif
            </div>
            <div class="md:col-span-2 flex justify-end gap-2">
                <button type="button" id="extClear" class="hidden flex-1 md:flex-none px-3 py-2 bg-white border border-gray-300 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50" title="Clear the search and filters"><i class="fas fa-xmark mr-1"></i> Clear</button>
                <button type="button" id="extRefresh" class="flex-1 md:flex-none px-3 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90" title="Refresh the list"><i class="fas fa-rotate" id="extRefreshIcon"></i></button>
            </div>
        </form>
        <div id="extList" class="transition-opacity" aria-live="polite">@include('hr-general.external.timesheet.components.list-table')</div>
    </div>
</div>

@if($canCreate)
<div id="sheetModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
    <form method="POST" action="{{ route('general.external.timesheet.open') }}" class="bg-white rounded-xl shadow-xl w-full max-w-md" data-unsaved-ignore>@csrf
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between"><h3 class="text-sm font-bold text-gray-800">New Timesheet</h3><button type="button" onclick="closeSheetModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button></div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-[11px] text-gray-500">Fill the hours on behalf of a consultant. The consultant sees the timesheet in My Timesheet &amp; Invoice.</p>
            <div><label class="block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1" for="shConsultant">External consultant</label>
                <select id="shConsultant" name="employee_id" required class="{{ $input }}"><option value="">Select a consultant</option>
                    @foreach($consultants as $c)@if(isset($periods[$c['id']]))<option value="{{ $c['id'] }}">{{ $c['eci'] }} — {{ $c['name'] }}</option>@endif @endforeach</select></div>
            <div><label class="block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1" for="shPeriod">Month</label>
                <select id="shPeriod" required class="{{ $input }}"><option value="">Select a consultant first</option></select>
                <input type="hidden" name="contract_id" id="shContract"><input type="hidden" name="year" id="shYear"><input type="hidden" name="month" id="shMonth">
                <p class="text-[10px] text-gray-400 mt-1">Only months covered by a consultant's contract, without a timesheet yet.</p></div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 flex justify-end gap-2">
            <button type="button" onclick="closeSheetModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
            <button class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Create &amp; open</button>
        </div>
    </form>
</div>
@endif

@push('scripts')
<script>
(function () {
    const form = document.getElementById(@json($filterForm)), list = document.getElementById('extList'), icon = document.getElementById('extRefreshIcon');
    let timer = null, controller = null;
    const query = extra => { const m = new Map(); new FormData(form).forEach((v, k) => m.set(k, v)); Object.entries(extra || {}).forEach(([k, v]) => m.set(k, v)); const q = new URLSearchParams(); m.forEach((v, k) => { if (String(v) !== '') q.set(k, v); }); return q.toString(); };
    async function load(qs, push = true) {
        if (controller) controller.abort(); controller = new AbortController();
        list.classList.add('opacity-50'); icon.classList.add('fa-spin');
        const url = form.action + (qs ? '?' + qs : '');
        try { const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal, credentials: 'same-origin' }); if (!res.ok) throw new Error(res.status); list.innerHTML = await res.text(); if (push) history.pushState({ qs }, '', url); }
        catch (e) { if (e.name !== 'AbortError') window.showNotification(({ 419: 'Your session has expired. Reload the page.', 403: 'You do not have permission to view this list.', 500: 'The server hit an error while filtering (code 500). Try again; if it repeats, tell the administrator.' })[e.message] || 'The list could not be refreshed (' + (e.message || 'network problem') + '). Check your connection and try again.', 'error'); }
        finally { list.classList.remove('opacity-50'); icon.classList.remove('fa-spin'); }
    }
    const clearBtn = document.getElementById('extClear');
    const filtered = () => [...form.querySelectorAll('input[name=search], input[name=month], select')].some(el => String(el.value || '') !== '');
    const syncClear = () => clearBtn.classList.toggle('hidden', !filtered());
    const reload = () => { syncClear(); return load(query({ page: '' })); };
    clearBtn.addEventListener('click', () => { form.querySelectorAll('input[name=search], input[name=month]').forEach(el => { el.value = ''; }); form.querySelectorAll('select').forEach(el => { el.value = ''; el.dispatchEvent(new Event('change', { bubbles: false })); }); reload(); });
    syncClear();
    document.getElementById('extSearch').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(reload, 300); });
    form.addEventListener('change', e => { if (e.target.matches('select, input[type=month]')) reload(); });
    form.addEventListener('submit', e => { e.preventDefault(); reload(); });
    document.getElementById('extRefresh').addEventListener('click', () => load(query()));
    list.addEventListener('click', e => { const a = e.target.closest('a[href*="page="]'); if (!a || e.metaKey || e.ctrlKey) return; e.preventDefault(); load(new URL(a.href).searchParams.toString()); });
    window.addEventListener('popstate', () => load(location.search.replace(/^\?/, ''), false));

    @if($canCreate)
    const modal = document.getElementById('sheetModal'), periods = @json($periods), sel = document.getElementById('shConsultant'), per = document.getElementById('shPeriod');
    window.openSheetModal = () => { modal.style.display = 'flex'; modal.classList.remove('hidden'); };
    window.closeSheetModal = () => { modal.style.display = 'none'; modal.classList.add('hidden'); };
    sel.addEventListener('change', () => { per.innerHTML = '<option value="">Select a month</option>' + (periods[sel.value] || []).map((p, i) => `<option value="${i}">${p.label}</option>`).join(''); });
    per.addEventListener('change', () => { const p = (periods[sel.value] || [])[per.value]; document.getElementById('shContract').value = p ? p.contract_id : ''; document.getElementById('shYear').value = p ? p.year : ''; document.getElementById('shMonth').value = p ? p.month : ''; });
    @endif
})();
</script>
@endpush
@endsection
