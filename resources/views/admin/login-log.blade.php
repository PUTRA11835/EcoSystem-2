@extends('dashboard')

@section('title', 'Login Log')
@section('page-title', 'Login Log')
@section('page-subtitle', 'Latest login/logout activity for each employee')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    #ddStatus .custom-dd-label { font-size: 0.75rem; font-weight: 600; }
    .hdr-filter-panel { z-index: 9999; }
</style>

<div class="space-y-6">

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Login Log</h3>
                <button id="btnResetFilters" onclick="resetFilters()"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span>Clear filters</span>
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

                        {{-- EMPLOYEE: keyword search popover --}}
                        <th class="p-0 whitespace-nowrap">
                            <button type="button" id="userFilterBtn" onclick="toggleHeaderFilter('userFilterPanel', this)"
                                class="hdr-filter-btn w-full flex items-center gap-1.5 px-4 py-3 text-left hover:bg-gray-100 transition-colors">
                                <span class="text-xs font-semibold text-gray-600">Employee</span>
                                <svg id="userFilterIcon" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div id="userFilterPanel" class="hdr-filter-panel hidden bg-white rounded-xl shadow-2xl border border-gray-100 p-3" style="min-width:240px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search</label>
                                <input type="text" id="filterSearch" placeholder="Employee name…"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
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
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="login">Login</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="logout">Logout</button>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Device</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Time (WIB)</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Last Login</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50" id="paginationRow"></div>
    </div>

</div>

@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}" onerror="window.__customDdLoadFailed=true;console.error('custom-dropdown.js gagal dimuat');"></script>
<script>
let currentPage    = 1;
let currentPerPage = 25;
let currentFilters = {};

// ─── Load table ───────────────────────────────────────────────────────────────
async function loadTable(page = 1) {
    currentPage = page;

    const params = new URLSearchParams({ page, per_page: currentPerPage, ...currentFilters });

    const tbody  = document.getElementById('tableBody');
    const infoEl = document.getElementById('tableInfo');
    const pagEl  = document.getElementById('paginationRow');
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td></tr>';

    try {
        const res  = await fetch(`/api/admin/login-logs?${params}`, { credentials: 'same-origin' });
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-red-500">${data.message || 'Failed to load data'}</td></tr>`;
            return;
        }

        const { data: rows, meta } = data;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-12 text-center text-sm text-gray-400">No records found</td></tr>';
            infoEl.textContent = '0 records';
            pagEl.innerHTML = '';
            return;
        }

        infoEl.textContent = `Showing ${((meta.current_page - 1) * meta.per_page) + 1}–${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total.toLocaleString('id-ID')}`;

        tbody.innerHTML = rows.map((row, idx) => {
            const rowNum = ((meta.current_page - 1) * meta.per_page) + idx + 1;
            return `<tr class="border-t border-gray-50 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-xs text-gray-400">${rowNum}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900 text-sm">${escHtml(row.user_name)}</div>
                    <div class="text-xs text-gray-400 mt-0.5">employee</div>
                </td>
                <td class="px-4 py-3">${statusBadgeHtml(row.status)}</td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-1.5">${deviceIconHtml(row.device_type)}<span class="text-xs text-gray-700 capitalize">${escHtml(row.device_type)}</span></div>
                    ${row.device_brand ? `<div class="text-xs text-gray-800 mt-0.5 font-medium">${escHtml(row.device_brand)}</div>` : ''}
                    <div class="text-xs text-gray-400 mt-0.5">${escHtml(row.browser)} · ${escHtml(row.os)}</div>
                </td>
                <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">${escHtml(row.time)}</td>
                <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">${escHtml(row.last_login)}</td>
            </tr>`;
        }).join('');

        pagEl.innerHTML = buildPagination(meta);

    } catch (e) {
        console.error('loadTable error:', e);
        tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-6 text-center text-sm text-red-500">Error loading data</td></tr>';
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function statusBadgeHtml(status) {
    const map = { login: 'bg-green-100 text-green-700', logout: 'bg-blue-100 text-blue-700' };
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
    <span class="text-xs text-gray-400">${meta.total.toLocaleString('id-ID')} total employees</span>`;
}

function escHtml(str) {
    if (!str && str !== 0) return '-';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Header filter popover ──────────────────────────────────────────────────────
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

    const firstInput = panel.querySelector('input[type="text"]');
    if (firstInput) requestAnimationFrame(() => firstInput.focus());
}

function closeHeaderFilters() {
    document.querySelectorAll('.hdr-filter-panel').forEach(p => {
        if (p.classList.contains('hidden')) return;
        p.classList.add('hidden');
        if (p._origParent && p.parentElement !== p._origParent) {
            p._origParent.appendChild(p);
            p.style.position = ''; p.style.top = ''; p.style.left = ''; p.style.right = ''; p.style.zIndex = '';
        }
    });
}

document.addEventListener('click', function (e) {
    if (e.target.closest('.hdr-filter-panel') || e.target.closest('.hdr-filter-btn')) return;
    closeHeaderFilters();
});

// ─── Filters ──────────────────────────────────────────────────────────────────
function applyFilters() {
    currentFilters = {};
    const search = document.getElementById('filterSearch').value.trim();
    const status = document.getElementById('filterStatus').value;

    if (search) currentFilters.search = search;
    if (status) currentFilters.status = status;

    const userActive = !!search;
    document.getElementById('userFilterIcon').classList.toggle('text-red-600', userActive);
    document.getElementById('userFilterIcon').classList.toggle('text-gray-300', !userActive);

    const anyActive = userActive || !!status;
    const resetBtn  = document.getElementById('btnResetFilters');
    resetBtn.classList.toggle('hidden', !anyActive);
    resetBtn.classList.toggle('inline-flex', anyActive);

    loadTable(1);
}

function clearUserFilter() {
    document.getElementById('filterSearch').value = '';
    applyFilters();
    closeHeaderFilters();
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    if (typeof setCustomDropdownValue === 'function') setCustomDropdownValue('filterStatus', '');
    else document.getElementById('filterStatus').value = '';
    currentFilters = {};
    document.getElementById('userFilterIcon').classList.remove('text-red-600');
    document.getElementById('userFilterIcon').classList.add('text-gray-300');
    document.getElementById('btnResetFilters').classList.add('hidden');
    document.getElementById('btnResetFilters').classList.remove('inline-flex');
    closeHeaderFilters();
    loadTable(1);
}

function onPerPageChange() {
    currentPerPage = parseInt(document.getElementById('perPageSelect').value, 10) || 25;
    loadTable(1);
}

// Search: Enter + debounce
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
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    const statusBtn = document.querySelector('#ddStatus .custom-dd-btn');
    if (statusBtn) statusBtn.addEventListener('click', closeHeaderFilters);
    loadTable(1);
});
</script>
@endsection
