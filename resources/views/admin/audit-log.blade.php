@extends('dashboard')

@section('title', 'Audit Log')
@section('page-title', 'Audit Log')
@section('page-subtitle', 'Track create, update, and delete activity across all modules')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    input[type="date"]::-webkit-calendar-picker-indicator {
        opacity: 0;
        position: absolute;
        right: 0;
        top: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    input[type="date"] { position: relative; }
    .hdr-filter-panel { z-index: 9999; }
    #ddModule .custom-dd-label, #ddEvent .custom-dd-label { font-size: 0.75rem; font-weight: 600; }
</style>

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="statsRow">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Total Records</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Created</p>
            <p class="text-2xl font-bold text-green-600" id="statCreated">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Updated</p>
            <p class="text-2xl font-bold text-blue-600" id="statUpdated">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Deleted</p>
            <p class="text-2xl font-bold text-red-600" id="statDeleted">—</p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Audit Records</h3>
                <button id="btnResetFilters" onclick="resetFilters()"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span id="btnResetFiltersLabel">Clear filters</span>
                </button>
            </div>
            <div class="flex items-center gap-3">
                <div class="custom-dd relative" style="min-width:100px" data-onchange="onPerPageChange">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-2.5 py-1.5 bg-white border border-gray-300 rounded-lg text-xs hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">25 / page</span>
                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="perPageSelect" value="25">
                    <div class="custom-dd-panel hidden absolute top-full right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:100px">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="25">25 / page</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="50">50 / page</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="100">100 / page</button>
                    </div>
                </div>
                <span class="text-xs text-gray-500" id="tableInfo">Loading…</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-left">
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">#</th>

                        {{-- RECORD: keyword search popover --}}
                        <th class="p-0 whitespace-nowrap">
                            <button type="button" id="recordFilterBtn" onclick="toggleHeaderFilter('recordFilterPanel', this)"
                                class="hdr-filter-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600">Activity</span>
                                <svg id="recordFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="recordFilterPanel" class="hdr-filter-panel hidden bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:260px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                                <input type="text" id="filterSearch" placeholder="Description, record, actor…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end mt-3">
                                    <button type="button" onclick="clearRecordFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>

                        {{-- MODULE: single-select dropdown --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="custom-dd relative w-full" id="ddModule" data-fixed="true" data-onchange="applyFilters">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                    <span class="custom-dd-label text-xs font-semibold text-gray-600">Module</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="filterModule" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:180px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Modules</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Employee">Employee</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Customer">Customer</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Delivery Project">Delivery Project</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Delivery Support">Delivery Support</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Ticket">Ticket</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Mandays">Mandays</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Reporting Period">Reporting Period</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Calendar">Calendar</button>
                                </div>
                            </div>
                        </th>

                        {{-- EVENT: single-select dropdown --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="custom-dd relative w-full" id="ddEvent" data-fixed="true" data-onchange="applyFilters">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                    <span class="custom-dd-label text-xs font-semibold text-gray-600">Event</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="filterEvent" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:160px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Events</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="created">Created</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="updated">Updated</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="deleted">Deleted</button>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Actor</th>

                        {{-- TIME: date range popover --}}
                        <th class="p-0 whitespace-nowrap">
                            <button type="button" id="timeFilterBtn" onclick="toggleHeaderFilter('timeFilterPanel', this)"
                                class="hdr-filter-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600">Time (WIB)</span>
                                <svg id="timeFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="timeFilterPanel" class="hdr-filter-panel hidden bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:240px;">
                                <div class="space-y-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Date From</label>
                                        <input type="date" id="filterDateFrom"
                                            class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Date To</label>
                                        <input type="date" id="filterDateTo"
                                            class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                    </div>
                                    <p id="dateFilterError" class="hidden text-xs text-red-500">"Date To" must be on/after "Date From".</p>
                                </div>
                                <div class="flex justify-end gap-2 mt-3">
                                    <button type="button" onclick="clearTimeFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="applyTimeFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Changes</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50" id="paginationRow">
        </div>
    </div>

</div>

<!-- Changes Modal -->
<div id="changesModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-900" id="changesModalTitle">Changes</h4>
            <button onclick="closeChangesModal()" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto px-5 py-4" id="changesModalBody"></div>
    </div>
</div>

{{-- Cache buster pakai filemtime supaya tiap deploy otomatis invalidate cache.
     `@file_exists` guard mencegah error di edge case file belum ter-deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}" onerror="window.__customDdLoadFailed=true;console.error('custom-dropdown.js gagal dimuat — dropdown filter akan jalan tanpa custom UI');"></script>
<script>
let currentPage  = 1;
let currentPerPage = 25;
let currentFilters = {};

// ─── Load stats ───────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const res  = await fetch('/api/admin/audit-logs?per_page=1', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        document.getElementById('statTotal').textContent   = data.meta.total.toLocaleString('id-ID');
        document.getElementById('statCreated').textContent = (data.stats?.created ?? 0).toLocaleString('id-ID');
        document.getElementById('statUpdated').textContent = (data.stats?.updated ?? 0).toLocaleString('id-ID');
        document.getElementById('statDeleted').textContent = (data.stats?.deleted ?? 0).toLocaleString('id-ID');
    } catch (e) {
        console.error('loadStats error:', e);
    }
}

// ─── Load table ───────────────────────────────────────────────────────────────
async function loadTable(page = 1) {
    currentPage = page;

    const params = new URLSearchParams({
        page,
        per_page: currentPerPage,
        ...currentFilters,
    });

    const tbody    = document.getElementById('tableBody');
    const infoEl   = document.getElementById('tableInfo');
    const pagEl    = document.getElementById('paginationRow');
    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td></tr>';

    try {
        const res  = await fetch(`/api/admin/audit-logs?${params}`, { credentials: 'same-origin' });
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-500">${data.message || 'Failed to load data'}</td></tr>`;
            return;
        }

        const { data: rows, meta } = data;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No records found</td></tr>';
            infoEl.textContent = '0 records';
            pagEl.innerHTML    = '';
            return;
        }

        infoEl.textContent = `Showing ${((meta.current_page - 1) * meta.per_page) + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total.toLocaleString('id-ID')}`;

        tbody.innerHTML = rows.map((row, idx) => {
            const eventBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${eventColorClass(row.event)}">${escHtml(row.event_label)}</span>`;
            const rowNum      = ((meta.current_page - 1) * meta.per_page) + idx + 1;
            const hasChanges  = (row.old_values && Object.keys(row.old_values).length) || (row.new_values && Object.keys(row.new_values).length);

            return `<tr class="border-t border-gray-50 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-xs text-gray-400">${rowNum}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 text-sm">${escHtml(row.actor_name)} ${escHtml(row.description !== '-' ? row.description : ('updated ' + row.module + ': ' + row.record_label))}</div>
                    <div class="text-xs text-gray-400 mt-0.5">${escHtml(row.auditable_type)} #${row.auditable_id}</div>
                </td>
                <td class="px-4 py-3 text-xs text-gray-700">${escHtml(row.module)}</td>
                <td class="px-4 py-3">${eventBadge}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${escHtml(row.actor_name)}</td>
                <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">${escHtml(row.created_at)}</td>
                <td class="px-4 py-3">
                    ${hasChanges
                        ? `<button type="button" onclick="showChanges(this)" class="text-xs text-blue-600 hover:underline"
                                data-old="${escAttr(JSON.stringify(row.old_values))}"
                                data-new="${escAttr(JSON.stringify(row.new_values))}"
                                data-event="${escAttr(row.event)}"
                                data-label="${escAttr(row.description !== '-' ? row.description : row.record_label)}">View</button>`
                        : '<span class="text-xs text-gray-300">—</span>'}
                </td>
            </tr>`;
        }).join('');

        // Pagination
        pagEl.innerHTML = buildPagination(meta);

    } catch (e) {
        console.error('loadTable error:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-500">Error loading data</td></tr>';
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function eventColorClass(event) {
    const map = {
        created: 'bg-green-100 text-green-700',
        updated: 'bg-blue-100 text-blue-700',
        deleted: 'bg-red-100 text-red-700',
    };
    return map[event] || 'bg-gray-100 text-gray-600';
}

function buildPagination(meta) {
    if (meta.last_page <= 1) return '';

    const prev = meta.current_page > 1
        ? `<button onclick="loadTable(${meta.current_page - 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">← Prev</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">← Prev</button>`;

    const next = meta.current_page < meta.last_page
        ? `<button onclick="loadTable(${meta.current_page + 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">Next →</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">Next →</button>`;

    const start = Math.max(1, meta.current_page - 2);
    const end   = Math.min(meta.last_page, meta.current_page + 2);
    let pageButtons = '';
    for (let p = start; p <= end; p++) {
        const active = p === meta.current_page
            ? 'bg-red-800 text-white border-red-800'
            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
        pageButtons += `<button onclick="loadTable(${p})" class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-all ${active}">${p}</button>`;
    }

    return `<div class="flex items-center gap-2 flex-wrap">
        ${prev}
        <div class="flex items-center gap-1">${pageButtons}</div>
        ${next}
        <span class="text-xs text-gray-400 ml-2">Page ${meta.current_page} of ${meta.last_page}</span>
    </div>
    <span class="text-xs text-gray-400">${meta.total.toLocaleString('id-ID')} total records</span>`;
}

function escHtml(str) {
    if (str === null || str === undefined || str === '') return '-';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

function formatValue(v) {
    if (v === null || v === undefined || v === '') return '<em class="text-gray-400">empty</em>';
    if (typeof v === 'object') return escHtml(JSON.stringify(v));
    return escHtml(String(v));
}

// ─── Changes modal ──────────────────────────────────────────────────────────────
function showChanges(btn) {
    let oldValues = null, newValues = null;
    try { oldValues = JSON.parse(btn.dataset.old || 'null'); } catch (e) {}
    try { newValues = JSON.parse(btn.dataset.new || 'null'); } catch (e) {}
    const event = btn.dataset.event;
    const label = btn.dataset.label;

    document.getElementById('changesModalTitle').textContent = `${label} — ${event.charAt(0).toUpperCase() + event.slice(1)}`;

    const body = document.getElementById('changesModalBody');

    if (event === 'created') {
        const keys = Object.keys(newValues || {}).sort();
        body.innerHTML = keys.length
            ? `<table class="w-full text-xs"><thead><tr class="text-left text-gray-500"><th class="pb-2 pr-3">Field</th><th class="pb-2">Initial Value</th></tr></thead><tbody>${
                keys.map(k => `<tr class="border-t border-gray-100"><td class="py-1.5 pr-3 font-medium text-gray-700 align-top whitespace-nowrap">${escHtml(k)}</td><td class="py-1.5 text-gray-600 break-all">${formatValue(newValues[k])}</td></tr>`).join('')
              }</tbody></table>`
            : '<p class="text-xs text-gray-400">No field data recorded.</p>';
    } else if (event === 'deleted') {
        const keys = Object.keys(oldValues || {}).sort();
        body.innerHTML = keys.length
            ? `<table class="w-full text-xs"><thead><tr class="text-left text-gray-500"><th class="pb-2 pr-3">Field</th><th class="pb-2">Value Before Deletion</th></tr></thead><tbody>${
                keys.map(k => `<tr class="border-t border-gray-100"><td class="py-1.5 pr-3 font-medium text-gray-700 align-top whitespace-nowrap">${escHtml(k)}</td><td class="py-1.5 text-gray-600 break-all">${formatValue(oldValues[k])}</td></tr>`).join('')
              }</tbody></table>`
            : '<p class="text-xs text-gray-400">No field data recorded.</p>';
    } else {
        const keys = Array.from(new Set([...Object.keys(oldValues || {}), ...Object.keys(newValues || {})])).sort();
        body.innerHTML = keys.length
            ? `<table class="w-full text-xs"><thead><tr class="text-left text-gray-500"><th class="pb-2 pr-3">Field</th><th class="pb-2 pr-3">Before</th><th class="pb-2">After</th></tr></thead><tbody>${
                keys.map(k => `<tr class="border-t border-gray-100">
                    <td class="py-1.5 pr-3 font-medium text-gray-700 align-top whitespace-nowrap">${escHtml(k)}</td>
                    <td class="py-1.5 pr-3 text-red-600 break-all align-top">${formatValue((oldValues || {})[k])}</td>
                    <td class="py-1.5 text-green-700 break-all align-top">${formatValue((newValues || {})[k])}</td>
                </tr>`).join('')
              }</tbody></table>`
            : '<p class="text-xs text-gray-400">No field data recorded.</p>';
    }

    document.getElementById('changesModal').classList.remove('hidden');
}

function closeChangesModal() {
    document.getElementById('changesModal').classList.add('hidden');
}

document.getElementById('changesModal').addEventListener('click', function(e) {
    if (e.target === this) closeChangesModal();
});

// ─── Header filter popovers ─────────────────────────────────────────────────────
function _positionHeaderPanel(btn, panel) {
    const r = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.zIndex   = '9999';
    panel.style.top      = `${r.bottom + 4}px`;
    panel.style.left     = `${r.left}px`;
    panel.style.right    = 'auto';
    requestAnimationFrame(() => {
        const pr = panel.getBoundingClientRect();
        if (pr.right > window.innerWidth - 8) {
            panel.style.left = `${Math.max(8, window.innerWidth - 8 - pr.width)}px`;
        }
    });
}

function toggleHeaderFilter(panelId, btn) {
    const panel  = document.getElementById(panelId);
    const isOpen = !panel.classList.contains('hidden');
    closeHeaderFilters();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (isOpen) return;

    if (!panel._origParent) panel._origParent = panel.parentElement;
    document.body.appendChild(panel);
    panel.classList.remove('hidden');
    _positionHeaderPanel(btn, panel);

    const firstInput = panel.querySelector('input[type="text"], input[type="date"]');
    if (firstInput) requestAnimationFrame(() => firstInput.focus());
}

function closeHeaderFilters() {
    document.querySelectorAll('.hdr-filter-panel').forEach(p => {
        if (p.classList.contains('hidden')) return;
        p.classList.add('hidden');
        if (p._origParent && p.parentElement !== p._origParent) {
            p._origParent.appendChild(p);
            p.style.position = '';
            p.style.top      = '';
            p.style.left     = '';
            p.style.right    = '';
            p.style.zIndex   = '';
        }
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('.hdr-filter-panel') || e.target.closest('.hdr-filter-btn')) return;
    closeHeaderFilters();
});

// ─── Time range apply/clear ──────────────────────────────────────────────────────
function applyTimeFilter() {
    const from = document.getElementById('filterDateFrom').value;
    const to   = document.getElementById('filterDateTo').value;
    const err  = document.getElementById('dateFilterError');
    if (from && to && to < from) {
        err.classList.remove('hidden');
        return;
    }
    err.classList.add('hidden');
    applyFilters();
    closeHeaderFilters();
}

function clearTimeFilter() {
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    applyFilters();
    closeHeaderFilters();
}

function clearRecordFilter() {
    document.getElementById('filterSearch').value = '';
    applyFilters();
    closeHeaderFilters();
}

// ─── Filters ──────────────────────────────────────────────────────────────────
function applyFilters() {
    currentFilters = {};
    const search   = document.getElementById('filterSearch').value.trim();
    const module   = document.getElementById('filterModule').value;
    const event    = document.getElementById('filterEvent').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo   = document.getElementById('filterDateTo').value;

    if (search)   currentFilters.search    = search;
    if (module)   currentFilters.module    = module;
    if (event)    currentFilters.event     = event;
    if (dateFrom) currentFilters.date_from = dateFrom;
    if (dateTo)   currentFilters.date_to   = dateTo;

    updateFilterIndicators({ search, dateFrom, dateTo, module, event });
    loadTable(1);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    setCustomDropdownValue('filterModule', '');
    setCustomDropdownValue('filterEvent', '');
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    currentFilters = {};
    updateFilterIndicators({});
    closeHeaderFilters();
    loadTable(1);
}

function updateFilterIndicators({ search = '', dateFrom = '', dateTo = '', module = '', event = '' } = {}) {
    const recordActive = !!search;
    const timeActive   = !!(dateFrom || dateTo);
    document.getElementById('recordFilterIcon').classList.toggle('text-red-600', recordActive);
    document.getElementById('recordFilterIcon').classList.toggle('text-gray-300', !recordActive);
    document.getElementById('timeFilterIcon').classList.toggle('text-red-600', timeActive);
    document.getElementById('timeFilterIcon').classList.toggle('text-gray-300', !timeActive);

    const anyActive = recordActive || timeActive || !!module || !!event;
    const resetBtn  = document.getElementById('btnResetFilters');
    resetBtn.classList.toggle('hidden', !anyActive);
    resetBtn.classList.toggle('inline-flex', anyActive);
}

function onPerPageChange() {
    const val = document.getElementById('perPageSelect').value;
    changePerPage(val);
}

function changePerPage(val) {
    currentPerPage = parseInt(val, 10);
    loadTable(1);
}

// ─── Search: Enter langsung apply + debounce saat mengetik ─────────────────────
let _searchDebounce = null;
document.getElementById('filterSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { clearTimeout(_searchDebounce); applyFilters(); }
});
document.getElementById('filterSearch').addEventListener('input', function() {
    clearTimeout(_searchDebounce);
    _searchDebounce = setTimeout(applyFilters, 400);
});

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    } else {
        console.warn('initCustomDropdowns belum tersedia — dropdown filter dinonaktifkan, tabel tetap dimuat.');
    }
    const moduleBtn = document.querySelector('#ddModule .custom-dd-btn');
    if (moduleBtn) moduleBtn.addEventListener('click', closeHeaderFilters);
    const eventBtn = document.querySelector('#ddEvent .custom-dd-btn');
    if (eventBtn) eventBtn.addEventListener('click', closeHeaderFilters);
    loadStats();
    loadTable(1);
});
</script>
@endsection
