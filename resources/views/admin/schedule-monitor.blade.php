@extends('dashboard')

@section('title', 'Schedule Monitor')
@section('page-title', 'Schedule Monitor')
@section('page-subtitle', 'Visibility into whether scheduled/cron tasks are actually running')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    .hdr-filter-panel { z-index: 9999; }
    #ddTaskName .custom-dd-label, #ddStatus .custom-dd-label { font-size: 0.75rem; font-weight: 600; }
</style>

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Known Tasks</p>
            <p class="text-2xl font-bold text-gray-900" id="statTotal">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Healthy</p>
            <p class="text-2xl font-bold text-green-600" id="statHealthy">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Stale / Never Run</p>
            <p class="text-2xl font-bold text-orange-600" id="statStale">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Failing</p>
            <p class="text-2xl font-bold text-red-600" id="statFailing">-</p>
        </div>
    </div>

    <!-- Task Status Cards -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Scheduled Tasks</h3>
                <span class="text-xs text-gray-400" id="lastUpdated"></span>
            </div>
            <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer select-none">
                <input type="checkbox" id="autoRefreshToggle" checked class="rounded border-gray-300 text-amber-600 focus:ring-amber-400">
                Auto-refresh
            </label>
        </div>
        <div id="taskCards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-5">
            <div class="col-span-full text-center text-sm text-gray-400 py-8">Loading…</div>
        </div>
    </div>

    <!-- Queue Health -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Queue Health</h3>
                <span class="text-xs text-gray-400">Supervised queue workers - a stuck worker never shows up in Failed Jobs</span>
            </div>
        </div>
        <div id="queueCards" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 p-5">
            <div class="col-span-full text-center text-sm text-gray-400 py-8">Loading…</div>
        </div>
    </div>

    <!-- Recent Failures / Skips Log -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div class="flex items-center gap-2.5">
                <h3 class="text-sm font-semibold text-gray-800">Recent Failures &amp; Skips</h3>
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

                        {{-- TASK: single-select dropdown, sortable --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="flex items-center">
                                <button type="button" onclick="toggleSort('task_name')" class="flex items-center gap-1 pl-4 pr-1 py-3 hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600">Task</span>
                                    <span class="sort-icon text-[10px] text-gray-300" data-sort="task_name">&#9650;</span>
                                </button>
                                <div class="custom-dd relative" id="ddTaskName" data-fixed="true" data-onchange="applyFilters">
                                    <button type="button" class="custom-dd-btn flex items-center px-2 py-3 hover:bg-gray-100 transition-colors">
                                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" id="filterTaskName" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:220px;" id="taskNameOptions">
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Tasks</button>
                                    </div>
                                </div>
                            </div>
                        </th>

                        {{-- STATUS: single-select dropdown, sortable --}}
                        <th class="p-0 whitespace-nowrap">
                            <div class="flex items-center">
                                <button type="button" onclick="toggleSort('status')" class="flex items-center gap-1 pl-4 pr-1 py-3 hover:bg-gray-100 transition-colors">
                                    <span class="text-xs font-semibold text-gray-600">Status</span>
                                    <span class="sort-icon text-[10px] text-gray-300" data-sort="status">&#9650;</span>
                                </button>
                                <div class="custom-dd relative" id="ddStatus" data-fixed="true" data-onchange="applyFilters">
                                    <button type="button" class="custom-dd-btn flex items-center px-2 py-3 hover:bg-gray-100 transition-colors">
                                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <input type="hidden" id="filterStatus" value="">
                                    <div class="custom-dd-panel hidden absolute top-full left-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:140px;">
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All</button>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="failed">Failed</button>
                                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="skipped">Skipped</button>
                                    </div>
                                </div>
                            </div>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Duration</th>
                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Exit Code</th>

                        <th class="p-0 whitespace-nowrap">
                            <button type="button" onclick="toggleSort('time')" class="flex items-center gap-1 px-4 py-3 text-left hover:bg-gray-100 transition-colors w-full">
                                <span class="text-xs font-semibold text-gray-600">Time (WIB)</span>
                                <span class="sort-icon text-[10px] text-gray-300" data-sort="time">&#9660;</span>
                            </button>
                        </th>

                        <th class="px-4 py-3 text-xs font-semibold text-gray-600 whitespace-nowrap">Error</th>
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

<!-- Error Modal -->
<div id="errorModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h4 class="text-sm font-semibold text-gray-900" id="errorModalTitle">Error Detail</h4>
            <button onclick="closeErrorModal()" class="text-gray-400 hover:text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto px-5 py-4">
            <pre id="errorModalBody" class="text-xs text-gray-700 bg-gray-50 rounded-lg p-4 whitespace-pre-wrap break-words font-mono"></pre>
            <p class="text-xs text-gray-400 mt-3">Full details (if any) are in the application log on the server.</p>
        </div>
    </div>
</div>

{{-- Cache buster pakai filemtime supaya tiap deploy otomatis invalidate cache.
     `@file_exists` guard mencegah error di edge case file belum ter-deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}" onerror="window.__customDdLoadFailed=true;console.error('custom-dropdown.js gagal dimuat - dropdown filter akan jalan tanpa custom UI');"></script>
<script>
let currentPage    = 1;
let currentPerPage = 25;
let currentFilters = {};
let currentSort    = { by: 'time', dir: 'desc' };
let autoRefreshTimer = null;

// ─── Sorting ──────────────────────────────────────────────────────────────────
function toggleSort(column) {
    if (currentSort.by === column) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = { by: column, dir: 'asc' };
    }
    updateSortIndicators();
    loadRuns(1);
}

function updateSortIndicators() {
    document.querySelectorAll('.sort-icon').forEach(el => {
        if (el.dataset.sort === currentSort.by) {
            el.innerHTML = currentSort.dir === 'asc' ? '&#9650;' : '&#9660;';
            el.classList.remove('text-gray-300');
            el.classList.add('text-red-600');
        } else {
            el.classList.remove('text-red-600');
            el.classList.add('text-gray-300');
        }
    });
}

// ─── Task status cards ──────────────────────────────────────────────────────────
const STATUS_META = {
    success:   { label: 'Success',   badge: 'bg-green-100 text-green-700' },
    failed:    { label: 'Failed',    badge: 'bg-red-100 text-red-700' },
    running:   { label: 'Running',   badge: 'bg-blue-100 text-blue-700' },
    skipped:   { label: 'Skipped',   badge: 'bg-gray-100 text-gray-600' },
    never_run: { label: 'Never Run', badge: 'bg-gray-100 text-gray-500' },
};

async function loadTaskStatus() {
    try {
        const res  = await fetch('/api/admin/schedule-monitor', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        document.getElementById('statTotal').textContent   = json.summary.total;
        document.getElementById('statHealthy').textContent = json.summary.total - json.summary.issues;
        document.getElementById('statStale').textContent   = json.summary.stale;
        document.getElementById('statFailing').textContent = json.summary.failing;

        const cards = document.getElementById('taskCards');
        if (!json.data.length) {
            cards.innerHTML = '<div class="col-span-full text-center text-sm text-gray-400 py-8">No scheduled tasks configured</div>';
            return;
        }

        cards.innerHTML = json.data.map(task => {
            const meta = STATUS_META[task.status] || STATUS_META.never_run;
            const borderClass = task.status === 'failed' ? 'border-red-200' : (task.is_stale ? 'border-orange-200' : 'border-gray-200');
            const lastActivity = task.last_finished_at || task.last_started_at || 'Never';

            return `<div class="border ${borderClass} rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">${escHtml(task.label)}</p>
                        <p class="text-xs text-gray-400 font-mono mt-0.5">${escHtml(task.command)}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap ${meta.badge}">${meta.label}</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mt-3">
                    <div>
                        <p class="text-gray-400">Frequency</p>
                        <p class="text-gray-700 font-medium">${escHtml(task.frequency_label)}</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Last Activity</p>
                        <p class="text-gray-700 font-medium">${escHtml(lastActivity)}</p>
                    </div>
                </div>
                ${task.is_stale ? `<div class="mt-3 px-2.5 py-1.5 bg-orange-50 text-orange-700 text-xs rounded-lg flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    Overdue for its schedule
                </div>` : ''}
                ${task.consecutive_failures > 0 ? `<div class="mt-2 text-xs text-red-600">${task.consecutive_failures} consecutive failure${task.consecutive_failures > 1 ? 's' : ''}</div>` : ''}
            </div>`;
        }).join('');
    } catch (e) {
        console.error('loadTaskStatus error:', e);
    }
}

// ─── Queue health cards ─────────────────────────────────────────────────────────
async function loadQueueHealth() {
    try {
        const res  = await fetch('/api/admin/schedule-monitor/queue-health', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const cards = document.getElementById('queueCards');
        if (!json.data.length) {
            cards.innerHTML = '<div class="col-span-full text-center text-sm text-gray-400 py-8">No queues configured for monitoring</div>';
            return;
        }

        cards.innerHTML = json.data.map(q => {
            const borderClass = q.is_healthy ? 'border-gray-200' : 'border-red-200';
            const badge = q.is_healthy
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap bg-green-100 text-green-700">Healthy</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap bg-red-100 text-red-700">Backlog</span>';

            return `<div class="border ${borderClass} rounded-xl p-4">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <p class="text-sm font-semibold text-gray-900">${escHtml(q.label)}</p>
                    ${badge}
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mt-3">
                    <div>
                        <p class="text-gray-400">Pending Jobs</p>
                        <p class="text-gray-700 font-medium">${q.pending_count}${q.max_pending !== null ? ` <span class="text-gray-400">/ ${q.max_pending} max</span>` : ''}</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Oldest Pending</p>
                        <p class="text-gray-700 font-medium">${q.oldest_pending_minutes !== null ? q.oldest_pending_minutes + ' min' : 'None'}</p>
                    </div>
                </div>
                ${!q.is_healthy ? `<div class="mt-3 px-2.5 py-1.5 bg-red-50 text-red-700 text-xs rounded-lg flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" /></svg>
                    Worker may be stuck or offline
                </div>` : ''}
            </div>`;
        }).join('');
    } catch (e) {
        console.error('loadQueueHealth error:', e);
    }
}

// ─── Recent failures/skips table ────────────────────────────────────────────────
async function loadRuns(page = 1) {
    currentPage = page;

    const params = new URLSearchParams({
        page,
        per_page: currentPerPage,
        sort_by: currentSort.by,
        sort_dir: currentSort.dir,
        ...currentFilters,
    });

    const tbody  = document.getElementById('tableBody');
    const infoEl = document.getElementById('tableInfo');
    const pagEl  = document.getElementById('paginationRow');
    tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">Loading…</td></tr>';

    try {
        const res  = await fetch(`/api/admin/schedule-monitor/runs?${params}`, { credentials: 'same-origin' });
        const data = await res.json();

        if (!data.success) {
            tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-500">${data.message || 'Failed to load data'}</td></tr>`;
            return;
        }

        const { data: rows, meta } = data;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-400">No failures or skips recorded</td></tr>';
            infoEl.textContent = '0 records';
            pagEl.innerHTML    = '';
            return;
        }

        infoEl.textContent = `Showing ${((meta.current_page - 1) * meta.per_page) + 1}-${Math.min(meta.current_page * meta.per_page, meta.total)} of ${meta.total.toLocaleString('id-ID')}`;

        tbody.innerHTML = rows.map((row, idx) => {
            const meta2 = STATUS_META[row.status] || STATUS_META.never_run;
            const rowNum = ((meta.current_page - 1) * meta.per_page) + idx + 1;
            const statusBadge = `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${meta2.badge}">${meta2.label}</span>`;

            return `<tr class="border-t border-gray-50 hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-xs text-gray-400">${rowNum}</td>
                <td class="px-4 py-3 text-sm text-gray-800">${escHtml(row.label)}</td>
                <td class="px-4 py-3">${statusBadge}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${row.duration_ms !== null && row.duration_ms !== undefined ? row.duration_ms + ' ms' : '-'}</td>
                <td class="px-4 py-3 text-xs text-gray-700">${row.exit_code ?? '-'}</td>
                <td class="px-4 py-3 text-xs text-gray-700 whitespace-nowrap">${escHtml(row.created_at)}</td>
                <td class="px-4 py-3">
                    ${row.error
                        ? `<button type="button" onclick="showError(this)" class="text-xs text-blue-600 hover:underline" data-error="${escAttr(row.error)}" data-label="${escAttr(row.label)}">View</button>`
                        : '<span class="text-xs text-gray-300">-</span>'}
                </td>
            </tr>`;
        }).join('');

        pagEl.innerHTML = buildPagination(meta);
    } catch (e) {
        console.error('loadRuns error:', e);
        tbody.innerHTML = '<tr><td colspan="7" class="px-4 py-6 text-center text-sm text-red-500">Error loading data</td></tr>';
    }
}

async function loadTaskFilterOptions() {
    try {
        const res  = await fetch('/api/admin/schedule-monitor', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const panel = document.getElementById('taskNameOptions');
        json.data.forEach(task => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors';
            btn.dataset.value = task.task_name;
            btn.textContent = task.label;
            panel.appendChild(btn);
        });
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    } catch (e) {
        console.error('loadTaskFilterOptions error:', e);
    }
}

function buildPagination(meta) {
    if (meta.last_page <= 1) return '';

    const prev = meta.current_page > 1
        ? `<button onclick="loadRuns(${meta.current_page - 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">← Prev</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">← Prev</button>`;

    const next = meta.current_page < meta.last_page
        ? `<button onclick="loadRuns(${meta.current_page + 1})" class="px-3 py-1.5 text-xs font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-all">Next →</button>`
        : `<button disabled class="px-3 py-1.5 text-xs font-medium bg-gray-100 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">Next →</button>`;

    const start = Math.max(1, meta.current_page - 2);
    const end   = Math.min(meta.last_page, meta.current_page + 2);
    let pageButtons = '';
    for (let p = start; p <= end; p++) {
        const active = p === meta.current_page
            ? 'bg-red-800 text-white border-red-800'
            : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
        pageButtons += `<button onclick="loadRuns(${p})" class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-all ${active}">${p}</button>`;
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

// ─── Error modal ──────────────────────────────────────────────────────────────
function showError(btn) {
    document.getElementById('errorModalTitle').textContent = btn.dataset.label;
    document.getElementById('errorModalBody').textContent  = btn.dataset.error || 'No error message recorded.';
    document.getElementById('errorModal').classList.remove('hidden');
}

function closeErrorModal() {
    document.getElementById('errorModal').classList.add('hidden');
}

document.getElementById('errorModal').addEventListener('click', function (e) {
    if (e.target === this) closeErrorModal();
});

// ─── Header filter popovers ─────────────────────────────────────────────────────
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

// ─── Filters ──────────────────────────────────────────────────────────────────
function applyFilters() {
    currentFilters = {};
    const taskName = document.getElementById('filterTaskName').value;
    const status   = document.getElementById('filterStatus').value;

    if (taskName) currentFilters.task_name = taskName;
    if (status)   currentFilters.status    = status;

    const anyActive = !!taskName || !!status;
    const resetBtn  = document.getElementById('btnResetFilters');
    resetBtn.classList.toggle('hidden', !anyActive);
    resetBtn.classList.toggle('inline-flex', anyActive);

    loadRuns(1);
}

function resetFilters() {
    setCustomDropdownValue('filterTaskName', '');
    setCustomDropdownValue('filterStatus', '');
    currentFilters = {};
    document.getElementById('btnResetFilters').classList.add('hidden');
    document.getElementById('btnResetFilters').classList.remove('inline-flex');
    closeHeaderFilters();
    loadRuns(1);
}

function onPerPageChange() {
    const val = document.getElementById('perPageSelect').value;
    changePerPage(val);
}

function changePerPage(val) {
    currentPerPage = parseInt(val, 10);
    loadRuns(1);
}

// ─── Auto-refresh ─────────────────────────────────────────────────────────────
function stampLastUpdated() {
    const now = new Date();
    document.getElementById('lastUpdated').textContent = `Updated ${now.toLocaleTimeString('en-GB')}`;
}

function refreshAll() {
    loadTaskStatus();
    loadQueueHealth();
    loadRuns(currentPage);
    stampLastUpdated();
}

function setupAutoRefresh() {
    const toggle = document.getElementById('autoRefreshToggle');
    const start = () => {
        stop();
        autoRefreshTimer = setInterval(refreshAll, 30000);
    };
    const stop = () => {
        if (autoRefreshTimer) clearInterval(autoRefreshTimer);
        autoRefreshTimer = null;
    };
    toggle.addEventListener('change', () => toggle.checked ? start() : stop());
    if (toggle.checked) start();
}

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    } else {
        console.warn('initCustomDropdowns belum tersedia - dropdown filter dinonaktifkan, tabel tetap dimuat.');
    }
    updateSortIndicators();
    loadTaskFilterOptions();
    loadTaskStatus();
    loadQueueHealth();
    loadRuns(1);
    stampLastUpdated();
    setupAutoRefresh();
});
</script>
@endsection
