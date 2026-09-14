@extends('dashboard')
@section('title', 'Customer MD')
@section('page-title', 'Customer MD')
@section('page-subtitle', 'CR tickets and/or tickets with a Customer Mandays proposal')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Customer MD</h2>
            <p class="text-sm text-gray-500 mt-0.5">Tickets with type Change Request and/or an active Customer Mandays proposal</p>
        </div>
        <a href="{{ route('reporting.customer-md.export') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-900 text-white text-sm font-semibold rounded-lg transition-colors">
            <i class="fas fa-file-excel"></i> Export
        </a>
    </div>

    {{-- Filter toolbar --}}
    <div id="cmdToolbar" class="mb-4">
        <div class="flex flex-wrap items-center gap-2">
            {{-- Customer: single-select searchable dropdown (populated from data) --}}
            <div class="custom-dd relative" id="ddCmdCustomer" data-searchable="true" data-onchange="applyCmdColFilter">
                <button type="button" class="custom-dd-btn cmd-filter-btn">
                    <i class="fas fa-building text-[10px] text-gray-400"></i>
                    <span class="custom-dd-label text-gray-500">Customer</span>
                    <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <input type="hidden" id="cmdColCustomer" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                </div>
            </div>

            {{-- Delivery: single-select searchable dropdown (populated from data) --}}
            <div class="custom-dd relative" id="ddCmdDelivery" data-searchable="true" data-onchange="applyCmdColFilter">
                <button type="button" class="custom-dd-btn cmd-filter-btn">
                    <i class="fas fa-truck text-[10px] text-gray-400"></i>
                    <span class="custom-dd-label text-gray-500">Delivery</span>
                    <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <input type="hidden" id="cmdColDelivery" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                </div>
            </div>

            {{-- Status: multi-select dropdown --}}
            <div class="custom-dd relative" id="ddCmdStatus" data-multi="true" data-onchange="applyCmdColFilter" data-placeholder="Status">
                <button type="button" class="custom-dd-btn cmd-filter-btn">
                    <i class="fas fa-flag text-[10px] text-gray-400"></i>
                    <span class="custom-dd-label text-gray-500">Status</span>
                    <svg class="custom-dd-arrow w-3 h-3 text-gray-400 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <input type="hidden" id="cmdColStatus" value="">
                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[60] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="none"><span class="custom-dd-item-text">None</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="pic_draft"><span class="custom-dd-item-text">Draft</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="pending_helpdesk"><span class="custom-dd-item-text">Review by Module Lead</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="sent_to_chat"><span class="custom-dd-item-text">Review by Customer</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                    <button type="button" class="custom-dd-item w-full flex items-center justify-between gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="canceled"><span class="custom-dd-item-text">Cancel</span><svg class="custom-dd-check w-4 h-4 text-red-500 opacity-0 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg></button>
                </div>
            </div>

            <button type="button" id="cmdClearAllBtn" onclick="resetCmdFilters()" class="hidden inline-flex items-center gap-1 text-xs font-semibold text-red-700 hover:underline ml-1">
                <i class="fas fa-times"></i>Clear Filters
            </button>
        </div>

        {{-- Summary bar --}}
        <div class="flex items-center justify-between gap-3 mt-3 text-sm text-gray-500">
            <span id="cmdSummaryText">Loading...</span>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="cmd-th text-left">No Tiket</th>
                    <th class="cmd-th text-left">Description</th>
                    <th class="cmd-th text-left">Type</th>
                    <th class="cmd-th text-left">Customer</th>
                    <th class="cmd-th text-left">Delivery</th>
                    <th class="cmd-th text-left">Ticket Lead</th>
                    <th class="cmd-th text-left">Customer MD Status</th>
                    <th class="cmd-th text-left">Created At</th>
                </tr>
            </thead>
            <tbody id="cmdTableBody">
                {{-- Rendered by JS --}}
            </tbody>
        </table>
    </div>

    {{-- Empty state --}}
    <div id="cmdEmpty" class="hidden py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-14 h-14 mx-auto mb-4 text-gray-300">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
        </svg>
        <p class="text-base font-medium text-gray-900 mb-1">No tickets found</p>
    </div>
</div>
@endsection

@push('styles')
<style>
.cmd-th {
    padding: 0.65rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
}
.cmd-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.cmd-td-desc { white-space: normal; max-width: 420px; }

/* Filter toolbar */
.cmd-filter-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 10px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;
    font-size: 12px; font-weight: 600; color: #4b5563; cursor: pointer; transition: all 0.15s;
}
.cmd-filter-btn:hover { background: #f9fafb; border-color: #d1d5db; }
.custom-dd.cmd-dd-active .custom-dd-arrow { color: #dc2626; }
.custom-dd.cmd-dd-active .custom-dd-btn { border-color: #fecaca; background: #fff8f8; }
.custom-dd.cmd-dd-active .custom-dd-btn span,
.custom-dd.cmd-dd-active .custom-dd-btn .custom-dd-label { color: #dc2626 !important; font-weight: 700; }
</style>
@endpush

@push('scripts')
@php
$customDdPath = public_path('js/custom-dropdown.js');
$customDdVer = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    loadCustomerMd();
});

let cmdAllRows = [];
let cmdFilters = { customer: '', delivery: '', statuses: [] };

const CMD_STATUS_MAP = {
    'none':             { label: 'None',                   cls: 'bg-gray-100 text-gray-500' },
    'pic_draft':        { label: 'Draft',                  cls: 'bg-blue-50 text-blue-700' },
    'pending_helpdesk': { label: 'Review by Module Lead',  cls: 'bg-amber-50 text-amber-700' },
    'sent_to_chat':     { label: 'Review by Customer',     cls: 'bg-indigo-50 text-indigo-700' },
    'canceled':         { label: 'Cancel',                 cls: 'bg-red-50 text-red-600' },
};

async function loadCustomerMd() {
    const empty   = document.getElementById('cmdEmpty');
    const summary = document.getElementById('cmdSummaryText');
    empty.classList.add('hidden');

    try {
        const res = await fetch('/api/reporting/customer-md', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        cmdAllRows = json.data || [];
        populateCmdDropdown('ddCmdCustomer', 'customer_name');
        populateCmdDropdown('ddCmdDelivery', 'delivery_name');
        applyCmdFilters();
    } catch (e) {
        console.error(e);
        summary.textContent = 'Failed to load data.';
        document.getElementById('cmdTableBody').innerHTML = `<tr><td colspan="8" class="text-center py-10 text-red-500 text-sm">
            <i class="fas fa-exclamation-circle text-xl block mb-2"></i>${escHtml(e.message)}
        </td></tr>`;
    }
}

function populateCmdDropdown(ddId, field) {
    const ddEl = document.getElementById(ddId);
    if (!ddEl) return;
    const panel = ddEl._ddPanel || ddEl.querySelector('.custom-dd-panel');
    if (!panel) return;
    panel.querySelectorAll('.custom-dd-item').forEach(el => el.remove());

    let hasUnassigned = false;
    const seen = new Set();
    const names = [];
    cmdAllRows.forEach(r => {
        const val = r[field];
        if (val) {
            if (!seen.has(val)) { seen.add(val); names.push(val); }
        } else {
            hasUnassigned = true;
        }
    });
    names.sort((a, b) => a.localeCompare(b));

    const fragment = document.createDocumentFragment();
    fragment.appendChild(cmdMakeDdItem('', 'All'));
    names.forEach(n => fragment.appendChild(cmdMakeDdItem(n, n)));
    if (hasUnassigned) fragment.appendChild(cmdMakeDdItem('__none__', 'Unassigned', true));

    panel.appendChild(fragment);
}

function cmdMakeDdItem(val, text, italic) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50' + (italic ? ' italic text-gray-400' : '');
    btn.dataset.value = val;
    btn.textContent = text;
    return btn;
}

function applyCmdColFilter() {
    cmdFilters.customer = document.getElementById('cmdColCustomer')?.value || '';
    cmdFilters.delivery = document.getElementById('cmdColDelivery')?.value || '';
    cmdFilters.statuses = (document.getElementById('cmdColStatus')?.value || '').split(',').filter(Boolean);
    applyCmdFilters();
}

function isCmdFilterActive() {
    return !!(cmdFilters.customer || cmdFilters.delivery || cmdFilters.statuses.length);
}

function updateCmdFilterIndicators() {
    document.getElementById('ddCmdCustomer')?.classList.toggle('cmd-dd-active', !!cmdFilters.customer);
    document.getElementById('ddCmdDelivery')?.classList.toggle('cmd-dd-active', !!cmdFilters.delivery);
    document.getElementById('ddCmdStatus')?.classList.toggle('cmd-dd-active', cmdFilters.statuses.length > 0);
    document.getElementById('cmdClearAllBtn')?.classList.toggle('hidden', !isCmdFilterActive());
}

function resetCmdFilters() {
    cmdFilters = { customer: '', delivery: '', statuses: [] };
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('cmdColCustomer', '');
        setCustomDropdownValue('cmdColDelivery', '');
    }
    if (typeof clearCustomDropdownMulti === 'function') clearCustomDropdownMulti('cmdColStatus');
    applyCmdFilters();
}

function computeFilteredCmdRows() {
    return cmdAllRows.filter(r => {
        if (cmdFilters.customer) {
            if (cmdFilters.customer === '__none__' ? r.customer_name : r.customer_name !== cmdFilters.customer) return false;
        }
        if (cmdFilters.delivery) {
            if (cmdFilters.delivery === '__none__' ? r.delivery_name : r.delivery_name !== cmdFilters.delivery) return false;
        }
        if (cmdFilters.statuses.length && !cmdFilters.statuses.includes(r.md_status)) return false;
        return true;
    });
}

function applyCmdFilters() {
    renderCustomerMd(computeFilteredCmdRows());
    updateCmdFilterIndicators();
}

function renderCustomerMd(rows) {
    const body    = document.getElementById('cmdTableBody');
    const empty   = document.getElementById('cmdEmpty');
    const summary = document.getElementById('cmdSummaryText');

    if (!rows.length) {
        body.innerHTML = '';
        empty.classList.remove('hidden');
        summary.textContent = isCmdFilterActive() ? 'No tickets match the selected filters.' : 'No tickets found.';
        return;
    }
    empty.classList.add('hidden');
    summary.textContent = `${rows.length} ticket${rows.length !== 1 ? 's' : ''}`;

    body.innerHTML = rows.map(cmdRow).join('');
}

function cmdRow(r) {
    const dateStr = r.created_at ? new Date(r.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const sInfo = CMD_STATUS_MAP[r.md_status] || { label: r.md_status_label || r.md_status || '—', cls: 'bg-gray-100 text-gray-500' };

    return `
    <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='/ticket/${r.ticket_id}'">
        <td class="cmd-td text-sm font-semibold text-gray-700">${escHtml(r.ticket_number || '—')}</td>
        <td class="cmd-td cmd-td-desc text-sm text-gray-700">${escHtml(r.description || '—')}</td>
        <td class="cmd-td text-sm text-gray-700">${escHtml(r.ticket_type || '—')}</td>
        <td class="cmd-td text-sm text-gray-700">${r.customer_name ? escHtml(r.customer_name) : '<span class="text-gray-300 italic">—</span>'}</td>
        <td class="cmd-td text-sm text-gray-700">${r.delivery_name ? escHtml(r.delivery_name) : '<span class="text-gray-300 italic">Unassigned</span>'}</td>
        <td class="cmd-td text-sm text-gray-700">${r.lead_name ? escHtml(r.lead_name) : '<span class="text-gray-300 italic">Unassigned</span>'}</td>
        <td class="cmd-td"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${sInfo.cls}">${escHtml(sInfo.label)}</span></td>
        <td class="cmd-td text-xs text-gray-500">${dateStr}</td>
    </tr>`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush
