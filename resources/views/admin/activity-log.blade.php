@extends('dashboard')

@section('title', 'Activity Log')
@section('page-title', 'Activity Log')
@section('page-subtitle', 'Track user login, logout, and access activity')

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
    /* Segmented user-type buttons */
    .seg-btn { color: #4b5563; background: #fff; }
    .seg-btn:hover { background: #f9fafb; }
    .seg-btn.active { background: #7f1d1d; border-color: #7f1d1d; color: #fff; }
    /* Header filter popovers detach to <body> on open — keep them above everything */
    .hdr-filter-panel { z-index: 9999; }
    /* Jaga ukuran/berat teks label Status konsisten dengan header lain,
       walau custom-dd menimpa class-nya (text-sm) saat item dipilih. */
    #ddStatus .custom-dd-label { font-size: 0.75rem; font-weight: 600; }
</style>

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="statsRow">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Total Records</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Successful Logins</p>
            <p class="text-2xl font-bold text-green-600" id="statSuccess">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Failed Attempts</p>
            <p class="text-2xl font-bold text-red-600" id="statFailed">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Logouts</p>
            <p class="text-2xl font-bold text-blue-600" id="statLogout">—</p>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Activity Records</h3>
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

                        {{-- USER: keyword search + user type filter popover --}}
                        <th class="p-0 whitespace-nowrap">
                            <button type="button" id="userFilterBtn" onclick="toggleHeaderFilter('userFilterPanel', this)"
                                class="hdr-filter-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600">User</span>
                                <svg id="userFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="userFilterPanel" class="hdr-filter-panel hidden bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:260px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                                <input type="text" id="filterSearch" placeholder="Name, IP, browser, location…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="border-t border-gray-100 mt-3 pt-3">
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">User Type</label>
                                    <input type="hidden" id="filterUserType" value="">
                                    <div id="userTypeSeg" class="grid grid-cols-3 gap-1.5">
                                        <button type="button" data-value="" onclick="setUserType('')"
                                            class="seg-btn px-2 py-1.5 text-xs font-medium border border-gray-200 rounded-md transition-colors">All</button>
                                        <button type="button" data-value="employee" onclick="setUserType('employee')"
                                            class="seg-btn px-2 py-1.5 text-xs font-medium border border-gray-200 rounded-md transition-colors">Employee</button>
                                        <button type="button" data-value="customer" onclick="setUserType('customer')"
                                            class="seg-btn px-2 py-1.5 text-xs font-medium border border-gray-200 rounded-md transition-colors">Customer</button>
                                    </div>
                                </div>
                                <div class="flex justify-end mt-3">
                                    <button type="button" onclick="clearUserFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>

                        {{-- STATUS: single-select dropdown --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="custom-dd relative w-full" id="ddStatus" data-fixed="true" data-onchange="applyFilters">
                                <button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                    <span class="custom-dd-label text-xs font-semibold text-gray-600">Status</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="filterStatus" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:160px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Status</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="success">Success</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="failed">Failed</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="logout">Logout</button>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">IP Address</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Device</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Browser / OS</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Location</th>

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
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50" id="paginationRow">
        </div>
    </div>

</div>

<!-- UA Tooltip Modal -->
<div id="uaModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold text-gray-900">Full User-Agent</h4>
            <button onclick="closeUaModal()" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <p id="uaModalText" class="text-xs text-gray-700 font-mono break-all leading-relaxed bg-gray-50 rounded-lg p-3"></p>
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
        const res  = await fetch('/api/admin/activity-logs?per_page=1', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) return;

        // Quick summary query — use separate calls with status filter
        const [s, f, l] = await Promise.all([
            fetch('/api/admin/activity-logs?per_page=1&status=success', { credentials: 'same-origin' }).then(r => r.json()),
            fetch('/api/admin/activity-logs?per_page=1&status=failed', { credentials: 'same-origin' }).then(r => r.json()),
            fetch('/api/admin/activity-logs?per_page=1&status=logout', { credentials: 'same-origin' }).then(r => r.json()),
        ]);

        document.getElementById('statTotal').textContent   = data.meta.total.toLocaleString('id-ID');
        document.getElementById('statSuccess').textContent = s.meta?.total?.toLocaleString('id-ID') ?? '—';
        document.getElementById('statFailed').textContent  = f.meta?.total?.toLocaleString('id-ID') ?? '—';
        document.getElementById('statLogout').textContent  = l.meta?.total?.toLocaleString('id-ID') ?? '—';
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
    tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td></tr>';

    try {
        const res  = await fetch(`/api/admin/activity-logs?${params}`, { credentials: 'same-origin' });
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-6 text-center text-sm text-red-500">${data.message || 'Failed to load data'}</td></tr>`;
            return;
        }

        const { data: rows, meta } = data;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-sm text-gray-400">No records found</td></tr>';
            infoEl.textContent = '0 records';
            pagEl.innerHTML    = '';
            return;
        }

        infoEl.textContent = `Showing ${((meta.current_page - 1) * meta.per_page) + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total.toLocaleString('id-ID')}`;

        tbody.innerHTML = rows.map((row, idx) => {
            const statusBadge = statusBadgeHtml(row.status);
            const deviceIcon  = deviceIconHtml(row.device_type);
            const rowNum      = ((meta.current_page - 1) * meta.per_page) + idx + 1;
            const timeDisplay = row.status === 'logout' ? row.logout_at : row.login_at;

            return `<tr class="border-t border-gray-50 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-xs text-gray-400">${rowNum}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 text-sm">${escHtml(row.user_name)}</div>
                    <div class="text-xs text-gray-400 mt-0.5">${row.user_type}</div>
                </td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3 text-xs font-mono text-gray-700">${escHtml(row.ip_address)}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-1.5">${deviceIcon}<span class="text-xs text-gray-700 capitalize">${escHtml(row.device_type)}</span></div>
                    ${row.device_brand ? `<div class="text-xs text-gray-800 mt-0.5 font-medium">${escHtml(row.device_brand)}</div>` : ''}
                    ${row.device_model ? `<div class="text-xs text-gray-400">${escHtml(row.device_model)}</div>` : ''}
                </td>
                <td class="px-4 py-3">
                    <div class="text-xs text-gray-800">${escHtml(row.browser)}</div>
                    <div class="text-xs text-gray-400 mt-0.5">${escHtml(row.os)}</div>
                    ${row.user_agent ? `<button onclick="showUa('${escAttr(row.user_agent)}')" class="text-xs text-blue-600 hover:underline mt-0.5">UA</button>` : ''}
                </td>
                <td class="px-4 py-3 text-xs text-gray-700">${escHtml(row.location)}</td>
                <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">${escHtml(timeDisplay)}</td>
            </tr>`;
        }).join('');

        // Pagination
        pagEl.innerHTML = buildPagination(meta);

    } catch (e) {
        console.error('loadTable error:', e);
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center text-sm text-red-500">Error loading data</td></tr>';
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function statusBadgeHtml(status) {
    const map = {
        success: 'bg-green-100 text-green-700',
        failed:  'bg-red-100 text-red-700',
        logout:  'bg-blue-100 text-blue-700',
    };
    const cls = map[status] || 'bg-gray-100 text-gray-600';
    return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${cls} capitalize">${status}</span>`;
}

function deviceIconHtml(type) {
    if (type === 'mobile')  return '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3" /></svg>';
    if (type === 'tablet')  return '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5h3m-6.75 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-15a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>';
    return '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3" /></svg>';
}

function buildPagination(meta) {
    if (meta.last_page <= 1) return '';

    const prev = meta.current_page > 1
        ? `<button onclick="loadTable(${meta.current_page - 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">← Prev</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">← Prev</button>`;

    const next = meta.current_page < meta.last_page
        ? `<button onclick="loadTable(${meta.current_page + 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">Next →</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">Next →</button>`;

    // Show up to 5 page buttons around current
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
    if (!str) return '-';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function escAttr(str) {
    if (!str) return '';
    return String(str).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

// ─── Header filter popovers ─────────────────────────────────────────────────────
// Popover panel di-detach ke <body> saat dibuka + di-posisikan fixed, supaya tidak
// ter-clip oleh `overflow-x-auto` pada wrapper tabel (pola sama seperti custom-dd).
function _positionHeaderPanel(btn, panel) {
    const r = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.zIndex   = '9999';
    panel.style.top      = `${r.bottom + 4}px`;
    panel.style.left     = `${r.left}px`;
    panel.style.right    = 'auto';
    // Jaga agar panel tetap di dalam viewport secara horizontal.
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
    // Tutup panel header lain + dropdown custom-dd yang mungkin terbuka.
    closeHeaderFilters();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (isOpen) return;

    if (!panel._origParent) panel._origParent = panel.parentElement;
    document.body.appendChild(panel);
    panel.classList.remove('hidden');
    _positionHeaderPanel(btn, panel);

    // Auto-focus input teks pertama (search) untuk kenyamanan.
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

// Klik di luar panel/tombol header → tutup.
document.addEventListener('click', function (e) {
    if (e.target.closest('.hdr-filter-panel') || e.target.closest('.hdr-filter-btn')) return;
    closeHeaderFilters();
});

// ─── Segmented User Type ─────────────────────────────────────────────────────────
function setUserType(val) {
    document.getElementById('filterUserType').value = val;
    document.querySelectorAll('#userTypeSeg .seg-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.value === val);
    });
    applyFilters();
}

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

function clearUserFilter() {
    document.getElementById('filterSearch').value = '';
    setUserType('');            // juga men-trigger applyFilters()
    closeHeaderFilters();
}

// ─── Filters ──────────────────────────────────────────────────────────────────
function applyFilters() {
    currentFilters = {};
    const search   = document.getElementById('filterSearch').value.trim();
    const status   = document.getElementById('filterStatus').value;
    const userType = document.getElementById('filterUserType').value;
    const dateFrom = document.getElementById('filterDateFrom').value;
    const dateTo   = document.getElementById('filterDateTo').value;

    if (search)   currentFilters.search    = search;
    if (status)   currentFilters.status    = status;
    if (userType) currentFilters.user_type = userType;
    if (dateFrom) currentFilters.date_from = dateFrom;
    if (dateTo)   currentFilters.date_to   = dateTo;

    updateFilterIndicators({ search, status, userType, dateFrom, dateTo });
    loadTable(1);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    setCustomDropdownValue('filterStatus', '');
    document.getElementById('filterUserType').value = '';
    document.querySelectorAll('#userTypeSeg .seg-btn').forEach(b => b.classList.toggle('active', b.dataset.value === ''));
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    document.getElementById('dateFilterError').classList.add('hidden');
    currentFilters = {};
    updateFilterIndicators({});
    closeHeaderFilters();
    loadTable(1);
}

// Sorot ikon corong + tampilkan tombol "Clear filters" saat ada filter aktif.
function updateFilterIndicators({ search = '', status = '', userType = '', dateFrom = '', dateTo = '' } = {}) {
    const userActive = !!(search || userType);
    const timeActive = !!(dateFrom || dateTo);
    document.getElementById('userFilterIcon').classList.toggle('text-red-600', userActive);
    document.getElementById('userFilterIcon').classList.toggle('text-gray-300', !userActive);
    document.getElementById('timeFilterIcon').classList.toggle('text-red-600', timeActive);
    document.getElementById('timeFilterIcon').classList.toggle('text-gray-300', !timeActive);

    const anyActive = userActive || timeActive || !!status;
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

// ─── UA Modal ─────────────────────────────────────────────────────────────────
function showUa(ua) {
    document.getElementById('uaModalText').textContent = ua;
    document.getElementById('uaModal').classList.remove('hidden');
}

function closeUaModal() {
    document.getElementById('uaModal').classList.add('hidden');
}

document.getElementById('uaModal').addEventListener('click', function(e) {
    if (e.target === this) closeUaModal();
});

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
    // Guard: kalau custom-dropdown.js gagal di-load (404 di production / network error),
    // jangan biarkan ReferenceError menghentikan loadStats & loadTable. Filter
    // dropdown akan tampil sebagai elemen statis tapi data tabel tetap muncul.
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    } else {
        console.warn('initCustomDropdowns belum tersedia — dropdown filter dinonaktifkan, tabel tetap dimuat.');
    }
    // Tandai "All" sebagai user-type default aktif.
    document.querySelectorAll('#userTypeSeg .seg-btn').forEach(b => b.classList.toggle('active', b.dataset.value === ''));
    // Membuka dropdown Status harus menutup popover header lain (custom-dd
    // memakai stopPropagation sehingga listener document tidak ter-trigger).
    const statusBtn = document.querySelector('#ddStatus .custom-dd-btn');
    if (statusBtn) statusBtn.addEventListener('click', closeHeaderFilters);
    loadStats();
    loadTable(1);
});
</script>
@endsection
