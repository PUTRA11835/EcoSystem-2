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

    <!-- Filters -->
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <div class="flex flex-wrap gap-3 items-end">
            <!-- Search -->
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                <input type="text" id="filterSearch" placeholder="Name, IP, browser, location…"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <!-- Status -->
            <div class="min-w-[140px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">All Status</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="filterStatus" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Status</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="success">Success</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="failed">Failed</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="logout">Logout</button>
                    </div>
                </div>
            </div>
            <!-- User Type -->
            <div class="min-w-[140px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">User Type</label>
                <div class="custom-dd relative">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">All Types</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="filterUserType" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Types</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="employee">Employee</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="customer">Customer</button>
                    </div>
                </div>
            </div>
            <!-- Date From -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                <div class="relative">
                    <input type="date" id="filterDateFrom"
                        class="pl-3 pr-9 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white hover:border-gray-400 transition-all cursor-pointer">
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Date To -->
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                <div class="relative">
                    <input type="date" id="filterDateTo"
                        class="pl-3 pr-9 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white hover:border-gray-400 transition-all cursor-pointer">
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Apply -->
            <div class="flex gap-2">
                <button onclick="applyFilters()" class="px-4 py-2 text-sm font-semibold bg-red-800 text-white rounded-lg hover:bg-red-900 transition-all">
                    Apply
                </button>
                <button onclick="resetFilters()" class="px-4 py-2 text-sm font-semibold bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-all">
                    Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-800">Activity Records</h3>
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
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">User</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">IP Address</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Device</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Browser / OS</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Location</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Time (WIB)</th>
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

    loadTable(1);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    setCustomDropdownValue('filterStatus', '');
    setCustomDropdownValue('filterUserType', '');
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    currentFilters = {};
    loadTable(1);
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

// ─── Search on Enter ──────────────────────────────────────────────────────────
document.getElementById('filterSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') applyFilters();
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
    loadStats();
    loadTable(1);
});
</script>
@endsection
