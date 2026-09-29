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
        <div class="flex items-center gap-2">
            <button type="button" id="cmdClearAllBtn" onclick="resetCmdFilters()" class="hidden inline-flex items-center gap-1 text-xs font-semibold text-red-700 hover:underline">
                <i class="fas fa-times"></i>Clear Filters
            </button>
            <a href="{{ route('reporting.customer-md.export') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 hover:bg-red-900 text-white text-sm font-semibold rounded-lg transition-colors">
                <i class="fas fa-file-excel"></i> Export
            </a>
        </div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div id="cmdContainer">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
            {{-- Table Toolbar: range + page size + numbered pagination --}}
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 px-4 py-2.5 border-b border-gray-100 bg-gray-50/60">
                <div class="flex items-center gap-3">
                    <p class="text-xs text-gray-400">
                        Showing <span class="font-semibold text-gray-600" id="cmdRangeStart">0</span>&ndash;<span class="font-semibold text-gray-600" id="cmdRangeEnd">0</span>
                        <span class="text-gray-300 mx-1">of</span>
                        <span class="font-semibold text-gray-700" id="cmdTotalItems">0</span> tickets
                    </p>
                    <label class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span>Show</span>
                        <select id="cmdPageSizeSelect" onchange="cmdChangePageSize(this.value)"
                            class="px-2 py-1 border border-gray-200 rounded-md text-xs font-semibold text-gray-600 bg-white focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400 cursor-pointer">
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                    </label>
                </div>
                <div class="flex items-center gap-1" id="cmdPaginationBar">
                    <button onclick="cmdPreviousPage()" id="cmdBtnPrevPage" disabled
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-1" id="cmdPageNumbers"></div>
                    <button onclick="cmdNextPage()" id="cmdBtnNextPage" disabled
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="overflow-auto" style="max-height: calc(100vh - 320px); min-height: 200px;">
                <table class="w-full text-sm border-collapse" style="min-width: 1500px;">
                    <thead class="sticky top-0 z-10 bg-gray-50 border-b border-gray-200">
                        <tr>
                            {{-- TICKET NUMBER: sortable + keyword filter --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 sticky left-0 bg-gray-50 z-20" style="min-width:140px;">
                                <button type="button" id="cmdTicketFilterBtn" onclick="toggleCmdTicketFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">No Tiket</span>
                                    <span id="cmd-sort-icon-ticket_number" class="cmd-sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                    <svg id="cmdTicketFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors shrink-0 ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div id="cmdTicketFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search ticket number</label>
                                    <input type="text" id="cmdTicketFilterInput" placeholder="e.g. TKT-2024-001…"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                        oninput="onCmdTicketFilterInput()">
                                    <div class="border-t border-gray-100 mt-3 pt-3">
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Sort</label>
                                        <div class="flex gap-2">
                                            <button type="button" onclick="sortCmd('ticket_number','asc'); closeCmdTicketFilter();"
                                                class="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors whitespace-nowrap">↑ Ascending</button>
                                            <button type="button" onclick="sortCmd('ticket_number','desc'); closeCmdTicketFilter();"
                                                class="flex-1 flex items-center justify-center gap-1 px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors whitespace-nowrap">↓ Descending</button>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2 mt-3">
                                        <button type="button" onclick="clearCmdTicketFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    </div>
                                </div>
                            </th>

                            {{-- DESCRIPTION: keyword search filter --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:260px;">
                                <button type="button" id="cmdDescFilterBtn" onclick="toggleCmdDescFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Description</span>
                                    <svg id="cmdDescFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors shrink-0 ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                                <div id="cmdDescFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:260px;">
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search description</label>
                                    <input type="text" id="cmdDescFilterInput" placeholder="Type keyword (case-insensitive)…"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                        oninput="onCmdDescFilterInput()">
                                    <div class="flex justify-end gap-2 mt-3">
                                        <button type="button" onclick="clearCmdDescFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    </div>
                                </div>
                            </th>

                            {{-- TYPE: column filter dropdown --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:150px;">
                                <div class="custom-dd relative w-full" id="ddCmdType" data-fixed="true" data-searchable="true" data-onchange="applyCmdColFilter">
                                    <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Type</span>
                                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <input type="hidden" id="cmdColType" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:200px;">
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    </div>
                                </div>
                            </th>

                            {{-- CUSTOMER: column filter dropdown --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:160px;">
                                <div class="custom-dd relative w-full" id="ddCmdCustomer" data-fixed="true" data-searchable="true" data-onchange="applyCmdColFilter">
                                    <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>
                                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <input type="hidden" id="cmdColCustomer" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    </div>
                                </div>
                            </th>

                            {{-- DELIVERY: column filter dropdown --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">
                                <div class="custom-dd relative w-full" id="ddCmdDelivery" data-fixed="true" data-searchable="true" data-onchange="applyCmdColFilter">
                                    <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                        <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Delivery</span>
                                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <input type="hidden" id="cmdColDelivery" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:260px;min-width:220px;">
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    </div>
                                </div>
                            </th>

                            {{-- TICKET LEAD --}}
                            <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">Ticket Lead</th>

                            {{-- STATUS: checkbox multi-select filter --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:180px;">
                                <button type="button" id="cmdStatusFilterBtn" onclick="toggleCmdStatusFilter(event)"
                                    class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer MD Status</span>
                                    <svg id="cmdStatusFilterArrow" class="w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div id="cmdStatusFilterPanel" class="hidden absolute mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5" style="min-width:230px;">
                                    <div class="max-h-64 overflow-y-auto">
                                        @foreach (['none' => 'None', 'pic_draft' => 'Draft', 'pending_helpdesk' => 'Review by Module Lead', 'sent_to_chat' => 'Review by Customer', 'canceled' => 'Cancel'] as $value => $label)
                                        <label class="flex items-center gap-2.5 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 cursor-pointer">
                                            <input type="checkbox" class="cmd-status-checkbox w-4 h-4 rounded border-gray-300 text-red-600 focus:ring-red-400 focus:ring-offset-0" value="{{ $value }}" onchange="onCmdStatusCheckboxChange()">
                                            <span>{{ $label }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                    <div class="flex justify-end gap-2 px-3 pt-2 mt-1.5 border-t border-gray-100">
                                        <button type="button" onclick="clearCmdStatusFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    </div>
                                </div>
                            </th>

                            {{-- CUSTOMER MANDAYS: sortable --}}
                            <th class="p-0 text-right whitespace-nowrap border-b border-gray-200 bg-gray-50 th-sortable cursor-pointer" style="min-width:150px;" onclick="sortCmd('customer_mandays')" title="Sort by Customer Mandays">
                                <div class="flex items-center justify-end gap-1 px-3 py-2.5">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer Mandays</span>
                                    <span id="cmd-sort-icon-customer_mandays" class="cmd-sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                </div>
                            </th>

                            {{-- CREATED AT: sortable --}}
                            <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50 th-sortable cursor-pointer" style="min-width:120px;" onclick="sortCmd('created_at')" title="Sort by Created At">
                                <div class="flex items-center gap-1 px-3 py-2.5">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Created At</span>
                                    <span id="cmd-sort-icon-created_at" class="cmd-sort-icon text-gray-300 font-normal normal-case tracking-normal text-xs">⇅</span>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="cmdTableBody" class="divide-y divide-gray-100 bg-white">
                        {{-- Rendered by JS --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.cmd-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.cmd-td-desc { white-space: normal; max-width: 420px; }

thead th.th-sortable:hover { background: #f1f5f9; }
.cmd-sort-icon { font-style: normal; transition: color 0.15s; }
.cmd-sort-icon.active { color: #111827; }

.custom-dd-item { text-align: left; }

/* Column filter active state */
.custom-dd.cmd-dd-active .custom-dd-arrow { color: #dc2626; }
.custom-dd.cmd-dd-active .custom-dd-btn > span,
.custom-dd.cmd-dd-active .custom-dd-btn .custom-dd-label { color: #dc2626 !important; font-weight: 700; }

/* Table rows */
#cmdTableBody tr { cursor: pointer; transition: background 0.1s; }
#cmdTableBody tr:hover { background: #f8fafc; }
#cmdTableBody tr:hover td:first-child { background: #f8fafc; }
#cmdTableBody tr td:first-child { box-shadow: 2px 0 4px rgba(0, 0, 0, 0.04); }
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
let cmdFilters = { customer: '', delivery: '', type: '', statuses: [], ticketKeyword: '', descKeyword: '' };
let cmdSort = { key: 'created_at', dir: 'desc' };
let cmdItemsPerPage = 50;
let cmdCurrentPage = 1;

const CMD_STATUS_MAP = {
    'none':             { label: 'None',                   cls: 'bg-gray-100 text-gray-500' },
    'pic_draft':        { label: 'Draft',                  cls: 'bg-blue-50 text-blue-700' },
    'pending_helpdesk': { label: 'Review by Module Lead',  cls: 'bg-amber-50 text-amber-700' },
    'sent_to_chat':     { label: 'Review by Customer',     cls: 'bg-indigo-50 text-indigo-700' },
    'canceled':         { label: 'Cancel',                 cls: 'bg-red-50 text-red-600' },
};

async function loadCustomerMd() {
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
        populateCmdDropdown('ddCmdType', 'ticket_type');
        updateCmdSortIcons();
        applyCmdFilters();
    } catch (e) {
        console.error(e);
        document.getElementById('cmdTableBody').innerHTML = `<tr><td colspan="9" class="text-center py-10 text-red-500 text-sm">
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
    cmdFilters.type = document.getElementById('cmdColType')?.value || '';
    cmdCurrentPage = 1;
    applyCmdFilters();
}

function isCmdFilterActive() {
    return !!(cmdFilters.customer || cmdFilters.delivery || cmdFilters.type || cmdFilters.statuses.length || cmdFilters.ticketKeyword || cmdFilters.descKeyword);
}

function updateCmdFilterIndicators() {
    document.getElementById('ddCmdCustomer')?.classList.toggle('cmd-dd-active', !!cmdFilters.customer);
    document.getElementById('ddCmdDelivery')?.classList.toggle('cmd-dd-active', !!cmdFilters.delivery);
    document.getElementById('ddCmdType')?.classList.toggle('cmd-dd-active', !!cmdFilters.type);
    const statusBtn = document.getElementById('cmdStatusFilterBtn');
    const statusActive = cmdFilters.statuses.length > 0;
    statusBtn?.querySelector('span')?.classList.toggle('text-red-700', statusActive);
    statusBtn?.querySelector('span')?.classList.toggle('font-bold', statusActive);
    document.getElementById('cmdStatusFilterArrow')?.classList.toggle('text-red-500', statusActive);
    document.getElementById('cmdTicketFilterIcon')?.classList.toggle('text-red-500', !!cmdFilters.ticketKeyword);
    document.getElementById('cmdTicketFilterIcon')?.classList.toggle('text-gray-300', !cmdFilters.ticketKeyword);
    document.getElementById('cmdDescFilterIcon')?.classList.toggle('text-red-500', !!cmdFilters.descKeyword);
    document.getElementById('cmdDescFilterIcon')?.classList.toggle('text-gray-300', !cmdFilters.descKeyword);
    document.getElementById('cmdClearAllBtn')?.classList.toggle('hidden', !isCmdFilterActive());
}

function resetCmdFilters() {
    cmdFilters = { customer: '', delivery: '', type: '', statuses: [], ticketKeyword: '', descKeyword: '' };
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('cmdColCustomer', '');
        setCustomDropdownValue('cmdColDelivery', '');
        setCustomDropdownValue('cmdColType', '');
    }
    document.querySelectorAll('.cmd-status-checkbox').forEach(cb => cb.checked = false);
    const tF = document.getElementById('cmdTicketFilterInput');
    if (tF) tF.value = '';
    const dF = document.getElementById('cmdDescFilterInput');
    if (dF) dF.value = '';
    cmdCurrentPage = 1;
    applyCmdFilters();
}

function computeFilteredCmdRows() {
    const tkw = cmdFilters.ticketKeyword.toLowerCase();
    const dkw = cmdFilters.descKeyword.toLowerCase();
    return cmdAllRows.filter(r => {
        if (cmdFilters.customer) {
            if (cmdFilters.customer === '__none__' ? r.customer_name : r.customer_name !== cmdFilters.customer) return false;
        }
        if (cmdFilters.delivery) {
            if (cmdFilters.delivery === '__none__' ? r.delivery_name : r.delivery_name !== cmdFilters.delivery) return false;
        }
        if (cmdFilters.type) {
            if (cmdFilters.type === '__none__' ? r.ticket_type : r.ticket_type !== cmdFilters.type) return false;
        }
        if (cmdFilters.statuses.length && !cmdFilters.statuses.includes(r.md_status)) return false;
        if (tkw && !String(r.ticket_number || '').toLowerCase().includes(tkw)) return false;
        if (dkw && !String(r.description || '').toLowerCase().includes(dkw)) return false;
        return true;
    });
}

function compareCmd(a, b, key, dir) {
    let av = a[key], bv = b[key];
    if (key === 'created_at') {
        av = av ? new Date(av).getTime() : 0;
        bv = bv ? new Date(bv).getTime() : 0;
    } else if (key === 'customer_mandays') {
        av = av === null || av === undefined ? -Infinity : Number(av);
        bv = bv === null || bv === undefined ? -Infinity : Number(bv);
    } else {
        av = (av || '').toString().toLowerCase();
        bv = (bv || '').toString().toLowerCase();
    }
    if (av < bv) return dir === 'asc' ? -1 : 1;
    if (av > bv) return dir === 'asc' ? 1 : -1;
    return 0;
}

function sortCmd(key, forcedDir) {
    if (forcedDir) {
        cmdSort = { key, dir: forcedDir };
    } else if (cmdSort.key === key) {
        cmdSort.dir = cmdSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        cmdSort = { key, dir: key === 'customer_mandays' || key === 'created_at' ? 'desc' : 'asc' };
    }
    updateCmdSortIcons();
    cmdCurrentPage = 1;
    applyCmdFilters();
}

function updateCmdSortIcons() {
    document.querySelectorAll('.cmd-sort-icon').forEach(el => {
        el.textContent = '⇅';
        el.classList.remove('active');
    });
    const icon = document.getElementById(`cmd-sort-icon-${cmdSort.key}`);
    if (icon) {
        icon.textContent = cmdSort.dir === 'asc' ? '↑' : '↓';
        icon.classList.add('active');
    }
}

function applyCmdFilters() {
    let rows = computeFilteredCmdRows();
    rows.sort((a, b) => compareCmd(a, b, cmdSort.key, cmdSort.dir));
    renderCustomerMd(rows);
    updateCmdFilterIndicators();
}

function renderCustomerMd(rows) {
    const body  = document.getElementById('cmdTableBody');
    const totalItems = rows.length;
    const totalPages = Math.max(1, Math.ceil(totalItems / cmdItemsPerPage));
    if (cmdCurrentPage > totalPages) cmdCurrentPage = totalPages;

    if (!totalItems) {
        body.innerHTML = `<tr>
            <td colspan="9" class="py-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 mx-auto mb-3 text-gray-300">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                </svg>
                <p class="text-sm font-medium text-gray-900">No tickets found</p>
                ${isCmdFilterActive() ? '<p class="text-xs text-gray-400 mt-1">Try adjusting or clearing your filters.</p>' : ''}
            </td>
        </tr>`;
        document.getElementById('cmdRangeStart').textContent = 0;
        document.getElementById('cmdRangeEnd').textContent = 0;
        document.getElementById('cmdTotalItems').textContent = 0;
        document.getElementById('cmdBtnPrevPage').disabled = true;
        document.getElementById('cmdBtnNextPage').disabled = true;
        document.getElementById('cmdPageNumbers').innerHTML = '';
        return;
    }

    const startIndex = (cmdCurrentPage - 1) * cmdItemsPerPage;
    const pageRows = rows.slice(startIndex, startIndex + cmdItemsPerPage);

    document.getElementById('cmdRangeStart').textContent = startIndex + 1;
    document.getElementById('cmdRangeEnd').textContent = Math.min(startIndex + cmdItemsPerPage, totalItems);
    document.getElementById('cmdTotalItems').textContent = totalItems;
    document.getElementById('cmdBtnPrevPage').disabled = cmdCurrentPage === 1;
    document.getElementById('cmdBtnNextPage').disabled = cmdCurrentPage === totalPages;

    body.innerHTML = pageRows.map(cmdRow).join('');
    renderCmdPageNumbers(totalPages);
}

// Numbered pagination: always show first/last, current ±1, with "…" gaps —
// keeps the bar short even with hundreds of pages.
function renderCmdPageNumbers(totalPages) {
    const wrap = document.getElementById('cmdPageNumbers');
    if (!wrap) return;

    const pages = [];
    const cur = cmdCurrentPage;
    pages.push(1);
    for (let p = cur - 1; p <= cur + 1; p++) {
        if (p > 1 && p < totalPages) pages.push(p);
    }
    if (totalPages > 1) pages.push(totalPages);
    const uniquePages = [...new Set(pages)].sort((a, b) => a - b);

    let html = '';
    let prev = 0;
    uniquePages.forEach(p => {
        if (p - prev > 1) {
            html += `<span class="px-1.5 text-xs text-gray-300 select-none">…</span>`;
        }
        const active = p === cur;
        html += `<button type="button" onclick="cmdGoToPage(${p})"
            class="inline-flex items-center justify-center min-w-[28px] h-7 px-1.5 rounded-lg text-xs font-semibold transition-all ${active
                ? 'bg-red-800 text-white shadow-sm'
                : 'text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300'}">${p}</button>`;
        prev = p;
    });
    wrap.innerHTML = html;
}

function cmdGoToPage(page) {
    cmdCurrentPage = page;
    applyCmdFilters();
}

function cmdPreviousPage() {
    if (cmdCurrentPage > 1) {
        cmdCurrentPage--;
        applyCmdFilters();
    }
}

function cmdNextPage() {
    cmdCurrentPage++;
    applyCmdFilters();
}

function cmdChangePageSize(val) {
    cmdItemsPerPage = parseInt(val, 10) || 50;
    cmdCurrentPage = 1;
    applyCmdFilters();
}

function cmdRow(r) {
    const dateStr = r.created_at ? new Date(r.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
    const sInfo = CMD_STATUS_MAP[r.md_status] || { label: r.md_status_label || r.md_status || '—', cls: 'bg-gray-100 text-gray-500' };
    const mdStr = (r.customer_mandays !== null && r.customer_mandays !== undefined)
        ? Number(r.customer_mandays).toFixed(2).replace(/\.00$/, '').replace(/(\.\d)0$/, '$1')
        : '—';

    return `
    <tr class="cursor-pointer hover:bg-gray-50" onclick="window.location='/ticket/${r.ticket_id}'">
        <td class="cmd-td text-sm font-semibold text-gray-700 sticky left-0 bg-white">${escHtml(r.ticket_number || '—')}</td>
        <td class="cmd-td cmd-td-desc text-sm text-gray-700">${escHtml(r.description || '—')}</td>
        <td class="cmd-td text-sm text-gray-700">${escHtml(r.ticket_type || '—')}</td>
        <td class="cmd-td text-sm text-gray-700">${r.customer_name ? escHtml(r.customer_name) : '<span class="text-gray-300 italic">—</span>'}</td>
        <td class="cmd-td text-sm text-gray-700">${r.delivery_name ? escHtml(r.delivery_name) : '<span class="text-gray-300 italic">Unassigned</span>'}</td>
        <td class="cmd-td text-sm text-gray-700">${r.lead_name ? escHtml(r.lead_name) : '<span class="text-gray-300 italic">Unassigned</span>'}</td>
        <td class="cmd-td"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${sInfo.cls}">${escHtml(sInfo.label)}</span></td>
        <td class="cmd-td text-right text-sm font-semibold text-gray-800">${mdStr}</td>
        <td class="cmd-td text-xs text-gray-500">${dateStr}</td>
    </tr>`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Ticket Number Keyword Filter (debounced) ───────────────────────────
let _cmdTicketFilterTimer = null;

function toggleCmdTicketFilter(ev) {
    ev?.stopPropagation();
    const panel = document.getElementById('cmdTicketFilterPanel');
    const btn = document.getElementById('cmdTicketFilterBtn');
    const open = !panel.classList.contains('hidden');
    closeCmdDescFilter();
    closeCmdStatusFilter();
    if (open) {
        panel.classList.add('hidden');
        return;
    }
    if (panel.parentElement !== document.body) document.body.appendChild(panel);
    positionCmdPanelUnder(btn, panel);
    panel.classList.remove('hidden');
    document.getElementById('cmdTicketFilterInput')?.focus();
}

function closeCmdTicketFilter() {
    document.getElementById('cmdTicketFilterPanel')?.classList.add('hidden');
}

function onCmdTicketFilterInput() {
    clearTimeout(_cmdTicketFilterTimer);
    _cmdTicketFilterTimer = setTimeout(() => {
        cmdFilters.ticketKeyword = document.getElementById('cmdTicketFilterInput')?.value.trim() || '';
        cmdCurrentPage = 1;
        applyCmdFilters();
    }, 250);
}

function clearCmdTicketFilter() {
    const input = document.getElementById('cmdTicketFilterInput');
    if (input) input.value = '';
    cmdFilters.ticketKeyword = '';
    cmdCurrentPage = 1;
    applyCmdFilters();
}

// ── Description Keyword Filter (debounced) ─────────────────────────────
let _cmdDescFilterTimer = null;

function toggleCmdDescFilter(ev) {
    ev?.stopPropagation();
    const panel = document.getElementById('cmdDescFilterPanel');
    const btn = document.getElementById('cmdDescFilterBtn');
    const open = !panel.classList.contains('hidden');
    closeCmdTicketFilter();
    closeCmdStatusFilter();
    if (open) {
        panel.classList.add('hidden');
        return;
    }
    if (panel.parentElement !== document.body) document.body.appendChild(panel);
    positionCmdPanelUnder(btn, panel);
    panel.classList.remove('hidden');
    document.getElementById('cmdDescFilterInput')?.focus();
}

function closeCmdDescFilter() {
    document.getElementById('cmdDescFilterPanel')?.classList.add('hidden');
}

function onCmdDescFilterInput() {
    clearTimeout(_cmdDescFilterTimer);
    _cmdDescFilterTimer = setTimeout(() => {
        cmdFilters.descKeyword = document.getElementById('cmdDescFilterInput')?.value.trim() || '';
        cmdCurrentPage = 1;
        applyCmdFilters();
    }, 250);
}

function clearCmdDescFilter() {
    const input = document.getElementById('cmdDescFilterInput');
    if (input) input.value = '';
    cmdFilters.descKeyword = '';
    cmdCurrentPage = 1;
    applyCmdFilters();
}

// ── Status Checkbox Filter ──────────────────────────────────────────────
function toggleCmdStatusFilter(ev) {
    ev?.stopPropagation();
    const panel = document.getElementById('cmdStatusFilterPanel');
    const btn = document.getElementById('cmdStatusFilterBtn');
    const open = !panel.classList.contains('hidden');
    closeCmdTicketFilter();
    closeCmdDescFilter();
    if (open) {
        panel.classList.add('hidden');
        return;
    }
    if (panel.parentElement !== document.body) document.body.appendChild(panel);
    positionCmdPanelUnder(btn, panel);
    panel.classList.remove('hidden');
}

function closeCmdStatusFilter() {
    document.getElementById('cmdStatusFilterPanel')?.classList.add('hidden');
}

function onCmdStatusCheckboxChange() {
    cmdFilters.statuses = Array.from(document.querySelectorAll('.cmd-status-checkbox:checked')).map(cb => cb.value);
    cmdCurrentPage = 1;
    applyCmdFilters();
}

function clearCmdStatusFilter() {
    document.querySelectorAll('.cmd-status-checkbox').forEach(cb => cb.checked = false);
    cmdFilters.statuses = [];
    cmdCurrentPage = 1;
    applyCmdFilters();
}

// Position floating panel right under the column header button (handles overflow:auto)
function positionCmdPanelUnder(btn, panel) {
    const rect = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.top = (rect.bottom + 4) + 'px';
    panel.style.left = rect.left + 'px';
}

document.addEventListener('click', (e) => {
    const tp = document.getElementById('cmdTicketFilterPanel');
    const tb = document.getElementById('cmdTicketFilterBtn');
    if (tp && !tp.classList.contains('hidden') && !tp.contains(e.target) && !tb.contains(e.target)) tp.classList.add('hidden');
    const dp = document.getElementById('cmdDescFilterPanel');
    const db = document.getElementById('cmdDescFilterBtn');
    if (dp && !dp.classList.contains('hidden') && !dp.contains(e.target) && !db.contains(e.target)) dp.classList.add('hidden');
    const sp = document.getElementById('cmdStatusFilterPanel');
    const sb = document.getElementById('cmdStatusFilterBtn');
    if (sp && !sp.classList.contains('hidden') && !sp.contains(e.target) && !sb.contains(e.target)) sp.classList.add('hidden');
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeCmdTicketFilter();
        closeCmdDescFilter();
        closeCmdStatusFilter();
    }
});
window.addEventListener('scroll', (e) => {
    const t = e.target;
    if (t && t.nodeType === 1 && t.closest && (t.closest('#cmdTicketFilterPanel') || t.closest('#cmdDescFilterPanel') || t.closest('#cmdStatusFilterPanel'))) return;
    closeCmdTicketFilter();
    closeCmdDescFilter();
    closeCmdStatusFilter();
}, true);
window.addEventListener('resize', () => { closeCmdTicketFilter(); closeCmdDescFilter(); closeCmdStatusFilter(); });
</script>
@endpush
