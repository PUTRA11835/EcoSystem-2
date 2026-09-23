@extends('dashboard')

@section('title', 'Failed Jobs')
@section('page-title', 'Failed Jobs')
@section('page-subtitle', 'Monitor and manage queue jobs that have failed')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
    .hdr-filter-panel { z-index: 9999; }
    #ddQueue .custom-dd-label { font-size: 0.75rem; font-weight: 600; }
</style>

<div class="space-y-6">

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Total Failed</p>
            <p class="text-2xl font-bold text-red-600" id="statTotal">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Oldest Failure</p>
            <p class="text-sm font-medium text-gray-700 mt-1" id="statOldest">-</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Newest Failure</p>
            <p class="text-sm font-medium text-gray-700 mt-1" id="statNewest">-</p>
        </div>
    </div>

    <!-- Table Card -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 gap-3 flex-wrap">
            <h2 class="text-sm font-semibold text-gray-700">Failed Jobs</h2>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="relative">
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="filterSearch" placeholder="Job name, queue, or error…"
                        class="pl-8 pr-3 py-1.5 border border-gray-300 rounded-lg text-xs text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400 w-56">
                </div>
                <div class="custom-dd relative" id="ddQueue" data-fixed="true" data-onchange="applyFilters" style="min-width:130px">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between gap-1.5 px-2.5 py-1.5 bg-white border border-gray-300 rounded-lg text-xs hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-700">All Queues</span>
                        <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="filterQueue" value="">
                    <div class="custom-dd-panel hidden absolute top-full right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5" style="min-width:130px" id="queueOptions">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Queues</button>
                    </div>
                </div>
                <button id="btnResetFilters" onclick="resetFilters()"
                    class="hidden inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium text-red-700 bg-red-50 border border-red-100 rounded-lg hover:bg-red-100 transition-all">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    Clear
                </button>
                <button id="btnRetryAll"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 transition">
                    Retry All
                </button>
                <button id="btnClearAll"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition">
                    Clear All
                </button>
            </div>
        </div>

        <!-- Loading -->
        <div id="loadingState" class="flex items-center justify-center py-16">
            <svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        </div>

        <!-- Empty -->
        <div id="emptyState" class="hidden flex flex-col items-center py-16 text-gray-400">
            <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm">No failed jobs - queue is healthy</p>
        </div>

        <!-- Table -->
        <div id="tableWrapper" class="hidden overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job</th>
                        <th class="p-0">
                            <button type="button" onclick="toggleSort('queue')" class="flex items-center gap-1 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100 transition-colors w-full">
                                Queue
                                <span class="sort-icon text-[10px] text-gray-300" data-sort="queue">&#9650;</span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                        <th class="p-0">
                            <button type="button" onclick="toggleSort('time')" class="flex items-center gap-1 px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hover:bg-gray-100 transition-colors w-full">
                                Failed At
                                <span class="sort-icon text-[10px] text-gray-300" data-sort="time">&#9660;</span>
                            </button>
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody id="jobTableBody" class="divide-y divide-gray-100"></tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="hidden px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
            <span id="paginationInfo"></span>
            <div class="flex gap-1">
                <button id="btnPrev" onclick="changePage(-1)" class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50 disabled:opacity-40">Prev</button>
                <button id="btnNext" onclick="changePage(1)"  class="px-2 py-1 rounded border border-gray-200 hover:bg-gray-50 disabled:opacity-40">Next</button>
            </div>
        </div>
    </div>

</div>

<!-- Exception Modal -->
<div id="exModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800" id="exModalTitle">Exception Detail</h3>
            <button onclick="closeExModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="overflow-y-auto p-5">
            <pre id="exModalBody" class="text-xs text-gray-700 bg-gray-50 rounded-lg p-4 whitespace-pre-wrap break-words font-mono"></pre>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex gap-2 justify-end">
            <button id="exModalRetry" class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Retry</button>
            <button id="exModalDelete" class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Delete</button>
            <button onclick="closeExModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Close</button>
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
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let currentPage = 1;
let totalPages  = 1;
let selectedUuid = null;
let currentFilters = {};
let currentSort  = { by: 'time', dir: 'desc' };

// ─── Sorting ──────────────────────────────────────────────────────────────────
function toggleSort(column) {
    if (currentSort.by === column) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = { by: column, dir: 'asc' };
    }
    updateSortIndicators();
    loadJobs(1);
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

// ─── Filters ──────────────────────────────────────────────────────────────────
async function loadQueueOptions() {
    try {
        const res  = await fetch('/api/admin/failed-jobs/queues', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        const panel = document.getElementById('queueOptions');
        json.data.forEach(q => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'custom-dd-item w-full text-left px-4 py-2 text-xs text-gray-600 hover:bg-gray-50 transition-colors';
            btn.dataset.value = q;
            btn.textContent = q;
            panel.appendChild(btn);
        });
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    } catch (e) {
        console.error('loadQueueOptions error:', e);
    }
}

function applyFilters() {
    currentFilters = {};
    const search = document.getElementById('filterSearch').value.trim();
    const queue  = document.getElementById('filterQueue').value;

    if (search) currentFilters.search = search;
    if (queue)  currentFilters.queue  = queue;

    document.getElementById('btnResetFilters').classList.toggle('hidden', !search && !queue);
    document.getElementById('btnResetFilters').classList.toggle('inline-flex', !!search || !!queue);

    loadJobs(1);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    setCustomDropdownValue('filterQueue', '');
    currentFilters = {};
    document.getElementById('btnResetFilters').classList.add('hidden');
    document.getElementById('btnResetFilters').classList.remove('inline-flex');
    loadJobs(1);
}

let _searchDebounce = null;
document.getElementById('filterSearch').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { clearTimeout(_searchDebounce); applyFilters(); }
});
document.getElementById('filterSearch').addEventListener('input', function () {
    clearTimeout(_searchDebounce);
    _searchDebounce = setTimeout(applyFilters, 400);
});

// ─── Load stats (always unfiltered - overall queue health) ─────────────────────
async function loadStats() {
    try {
        const res  = await fetch('/api/admin/failed-jobs?per_page=1&sort_by=time&sort_dir=desc', { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) return;

        document.getElementById('statTotal').textContent = json.pagination.total;
        if (json.data.length) {
            document.getElementById('statNewest').textContent = json.data[0].failed_at ?? '-';
        }

        const oldestRes  = await fetch('/api/admin/failed-jobs?per_page=1&sort_by=time&sort_dir=asc', { credentials: 'same-origin' });
        const oldestJson = await oldestRes.json();
        if (oldestJson.success && oldestJson.data.length) {
            document.getElementById('statOldest').textContent = oldestJson.data[0].failed_at ?? '-';
        }
    } catch (e) {
        console.error('loadStats error:', e);
    }
}

async function loadJobs(page = 1) {
    currentPage = page;
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('tableWrapper').classList.add('hidden');
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('pagination').classList.add('hidden');

    try {
        const params = new URLSearchParams({
            page,
            per_page: 200,
            sort_by: currentSort.by,
            sort_dir: currentSort.dir,
            ...currentFilters,
        });
        const res  = await fetch(`/api/admin/failed-jobs?${params}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        renderTable(json.data, json.pagination);
    } catch (e) {
        document.getElementById('loadingState').classList.add('hidden');
        showToast('Failed to load jobs: ' + e.message, 'error');
    }
}

function renderTable(data, pagination) {
    document.getElementById('loadingState').classList.add('hidden');
    totalPages = pagination.last_page;

    const isFiltered = Object.keys(currentFilters).length > 0;

    if (!data.length && pagination.total === 0) {
        document.getElementById('emptyState').classList.remove('hidden');
        document.getElementById('emptyState').querySelector('p').textContent = isFiltered
            ? 'No failed jobs match your filters'
            : 'No failed jobs - queue is healthy';
        return;
    }

    document.getElementById('tableWrapper').classList.remove('hidden');

    const tbody = document.getElementById('jobTableBody');
    tbody.innerHTML = '';

    data.forEach(job => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-gray-50 transition-colors';
        row.innerHTML = `
            <td class="px-4 py-3">
                <p class="font-medium text-gray-800">${htmlEsc(job.job_name)}</p>
                <p class="text-xs text-gray-400 font-mono">${htmlEsc(job.uuid)}</p>
            </td>
            <td class="px-4 py-3 text-gray-500 text-xs">${htmlEsc(job.queue_name)}</td>
            <td class="px-4 py-3 text-red-600 text-xs max-w-[280px] truncate cursor-pointer hover:underline"
                onclick='viewDetail("${htmlEsc(job.uuid)}")'
                title="${htmlEsc(job.exception_short)}">${htmlEsc(job.exception_short)}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">${htmlEsc(job.failed_at)}</td>
            <td class="px-4 py-3 text-right">
                <div class="flex gap-2 justify-end">
                    <button onclick='retryJob("${htmlEsc(job.uuid)}")' class="text-xs text-blue-600 hover:text-blue-800 font-medium">Retry</button>
                    <button onclick='deleteJob("${htmlEsc(job.uuid)}")' class="text-xs text-red-600 hover:text-red-800 font-medium">Delete</button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });

    if (pagination.total > pagination.per_page) {
        document.getElementById('pagination').classList.remove('hidden');
        document.getElementById('paginationInfo').textContent =
            `Page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total)`;
        document.getElementById('btnPrev').disabled = pagination.current_page <= 1;
        document.getElementById('btnNext').disabled = pagination.current_page >= pagination.last_page;
    }
}

function changePage(delta) {
    const next = currentPage + delta;
    if (next < 1 || next > totalPages) return;
    loadJobs(next);
}

async function viewDetail(uuid) {
    try {
        const res  = await fetch(`/api/admin/failed-jobs/${uuid}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        selectedUuid = uuid;
        document.getElementById('exModalTitle').textContent = json.data.job_name;
        document.getElementById('exModalBody').textContent  = json.data.exception;
        document.getElementById('exModal').classList.remove('hidden');
    } catch (e) {
        showToast('Failed to load detail', 'error');
    }
}

function closeExModal() {
    selectedUuid = null;
    document.getElementById('exModal').classList.add('hidden');
}

document.getElementById('exModalRetry').addEventListener('click', async () => {
    if (!selectedUuid) return;
    await retryJob(selectedUuid);
    closeExModal();
});

document.getElementById('exModalDelete').addEventListener('click', async () => {
    if (!selectedUuid) return;
    await deleteJob(selectedUuid);
    closeExModal();
});

async function retryJob(uuid) {
    try {
        const res  = await fetch(`/api/admin/failed-jobs/${uuid}/retry`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadJobs(currentPage);
    } catch (e) {
        showToast('Request failed', 'error');
    }
}

async function deleteJob(uuid) {
    if (!await showConfirm('Delete this failed job?', 'Delete Failed Job', 'danger')) return;
    try {
        const res  = await fetch(`/api/admin/failed-jobs/${uuid}/delete`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadJobs(currentPage);
    } catch (e) {
        showToast('Request failed', 'error');
    }
}

document.getElementById('btnRetryAll').addEventListener('click', async () => {
    if (!await showConfirm('Retry ALL failed jobs?', 'Retry All Jobs', 'primary')) return;
    try {
        const res  = await fetch('/api/admin/failed-jobs/retry-all', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadJobs(1);
    } catch (e) {
        showToast('Request failed', 'error');
    }
});

document.getElementById('btnClearAll').addEventListener('click', async () => {
    if (!await showConfirm('Clear ALL failed jobs? This cannot be undone.', 'Clear All Jobs', 'danger')) return;
    try {
        const res  = await fetch('/api/admin/failed-jobs/clear', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': CSRF },
        });
        const json = await res.json();
        showToast(json.message, json.success ? 'success' : 'error');
        if (json.success) loadJobs(1);
    } catch (e) {
        showToast('Request failed', 'error');
    }
});

function htmlEsc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type) {
    const t = document.createElement('div');
    t.className = `fixed bottom-5 right-5 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-medium transition-all ${type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    } else {
        console.warn('initCustomDropdowns belum tersedia - dropdown filter dinonaktifkan, tabel tetap dimuat.');
    }
    updateSortIndicators();
    loadQueueOptions();
    loadStats();
    loadJobs();
});
</script>
@endsection
