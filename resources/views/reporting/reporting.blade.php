@extends('dashboard')
@section('title', 'Reporting')
@section('page-title', 'Reporting')
@section('page-subtitle', 'Timesheet performance reports')

@section('content')
@php
    $roleId = session('user')['role']['id'] ?? 0;
    $canManage = in_array($roleId, [1, 5]); // Admin or Head of Support
@endphp

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Timesheet Report</h2>
            <p class="text-gray-600 mt-1">Approved support timesheets — MD quota vs consumed per ticket</p>
        </div>
        @if($canManage)
        <div class="flex items-center gap-2">
            {{-- Period badge --}}
            <div id="periodBadge" class="hidden items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <i class="fas fa-calendar-alt text-gray-400"></i>
                <span id="periodLabel">—</span>
                <span id="periodStatus" class="font-semibold"></span>
            </div>
            {{-- Export Excel --}}
            <button onclick="exportExcel()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-file-excel text-xs"></i> Export Excel
            </button>
            {{-- Close Period --}}
            <button id="btnClosePeriod" onclick="openClosePeriodModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-sm font-semibold rounded-lg transition-all duration-200">
                <i class="fas fa-lock text-xs"></i> Close Period
            </button>
        </div>
        @endif
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Start Date</label>
                <input type="date" id="rptStartDate"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white">
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">End Date</label>
                <input type="date" id="rptEndDate"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white">
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Status</label>
                <div class="relative">
                    <select id="rptFilterStatus"
                        class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white appearance-none">
                        <option value="">All</option>
                        <option value="Match">Match</option>
                        <option value="Less">Less</option>
                        <option value="Over">Over</option>
                    </select>
                    <i class="fas fa-bars absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>
        </div>
        <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
            <button onclick="loadReport()"
                class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Apply
            </button>
            <button onclick="resetReport()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Reset
            </button>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 mb-1">Total Entries</p>
            <p class="text-2xl font-bold text-gray-900" id="rptCardTotal">0</p>
        </div>
        <div id="rptCardMatchBox" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-green-400 transition-all" onclick="filterCardStatus('Match')">
            <p class="text-xs font-medium text-gray-500 mb-1">Match</p>
            <p class="text-2xl font-bold text-green-600" id="rptCardMatch">0</p>
        </div>
        <div id="rptCardLessBox" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-yellow-400 transition-all" onclick="filterCardStatus('Less')">
            <p class="text-xs font-medium text-gray-500 mb-1">Less</p>
            <p class="text-2xl font-bold text-yellow-600" id="rptCardLess">0</p>
        </div>
        <div id="rptCardOverBox" class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-red-400 transition-all" onclick="filterCardStatus('Over')">
            <p class="text-xs font-medium text-gray-500 mb-1">Over</p>
            <p class="text-2xl font-bold text-red-600" id="rptCardOver">0</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 380px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 800px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:150px;">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:140px;">Ticket</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:140px;">Customer</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:90px;">Quota MD</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:110px;">MD Consumed</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:90px;">Remain</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:110px;">Status</th>
                    </tr>
                </thead>
                <tbody id="rptTableBody" class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-xl mb-2 block"></i>
                            Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="rptEmpty" class="hidden text-center py-16">
            <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
            <p class="text-gray-600 font-semibold mb-1">No data found</p>
            <p class="text-gray-400 text-xs">Try adjusting your filters</p>
        </div>
    </div>
</div>

@if($canManage)
{{-- Close Period Confirmation Modal --}}
<div id="closePeriodModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="flex items-center justify-center w-14 h-14 mx-auto mb-4 bg-red-100 rounded-full">
                <i class="fas fa-lock text-red-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-900 text-center mb-2">Close Current Period</h3>
            <p class="text-sm text-gray-600 text-center mb-2">
                You are about to close the reporting period:
            </p>
            <p class="text-base font-bold text-gray-900 text-center mb-4" id="closePeriodName">—</p>
            <p class="text-sm text-gray-500 text-center mb-6">
                After closing, any new timesheet submissions dated within this period will automatically be counted in the next period.
                <strong class="text-red-600">This action cannot be undone.</strong>
            </p>
            <div class="flex gap-3">
                <button onclick="closeClosePeriodModal()"
                    class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button onclick="confirmClosePeriod()"
                    class="flex-1 px-4 py-2.5 bg-red-700 text-white text-sm font-semibold rounded-lg hover:bg-red-800 transition-all">
                    Yes, Close Period
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
let rptAllData    = [];
let rptFiltered   = [];
let currentPeriod = null; // { year, month, is_closed, start_date, end_date }

const MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

// ── Init ─────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    initPeriodDates();
    @if($canManage)
    loadCurrentPeriod();
    @endif
    loadReport();
});

// Set date inputs to the current period range (21st rule)
function initPeriodDates() {
    const now = new Date();
    let pYear, pMonth;
    if (now.getDate() >= 21) {
        pYear  = now.getFullYear();
        pMonth = now.getMonth(); // 0-based
    } else {
        const prev = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        pYear  = prev.getFullYear();
        pMonth = prev.getMonth(); // 0-based
    }

    // period: day 21 of pMonth → day 20 of pMonth+1
    const startDate = `${pYear}-${String(pMonth + 1).padStart(2,'0')}-21`;
    const endMonth  = pMonth === 11 ? 1 : pMonth + 2;
    const endYear   = pMonth === 11 ? pYear + 1 : pYear;
    const endDate   = `${endYear}-${String(endMonth).padStart(2,'0')}-20`;

    document.getElementById('rptStartDate').value = startDate;
    document.getElementById('rptEndDate').value   = endDate;
}

// ── Period info ───────────────────────────────────────────────────────────

@if($canManage)
async function loadCurrentPeriod() {
    try {
        const res  = await fetch('/api/reporting/current-period', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) return;

        currentPeriod = json.data;
        updatePeriodBadge();
    } catch (e) {
        console.error('loadCurrentPeriod error', e);
    }
}

function updatePeriodBadge() {
    if (!currentPeriod) return;

    const badge   = document.getElementById('periodBadge');
    const label   = document.getElementById('periodLabel');
    const status  = document.getElementById('periodStatus');
    const btnClose = document.getElementById('btnClosePeriod');

    const name = `${MONTH_NAMES[currentPeriod.month - 1]} ${currentPeriod.year}`;
    label.textContent = name;

    if (currentPeriod.is_closed) {
        status.textContent = '(Closed)';
        status.className   = 'font-semibold text-red-600';
        if (btnClose) { btnClose.disabled = true; btnClose.classList.add('opacity-50', 'cursor-not-allowed'); }
    } else {
        status.textContent = '(Open)';
        status.className   = 'font-semibold text-green-600';
        if (btnClose) { btnClose.disabled = false; btnClose.classList.remove('opacity-50', 'cursor-not-allowed'); }
    }

    badge.classList.remove('hidden');
    badge.classList.add('flex');
}

// ── Close Period Modal ────────────────────────────────────────────────────

function openClosePeriodModal() {
    if (!currentPeriod || currentPeriod.is_closed) return;
    const name = `${MONTH_NAMES[currentPeriod.month - 1]} ${currentPeriod.year}`;
    document.getElementById('closePeriodName').textContent = name;
    const modal = document.getElementById('closePeriodModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeClosePeriodModal() {
    const modal = document.getElementById('closePeriodModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function confirmClosePeriod() {
    try {
        const res  = await fetch('/api/reporting/close-period', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            credentials: 'same-origin'
        });
        const json = await res.json();

        closeClosePeriodModal();

        if (json.success) {
            showNotification(json.message, 'success');
            await loadCurrentPeriod();
        } else {
            showNotification(json.message || 'Failed to close period', 'error');
        }
    } catch (e) {
        closeClosePeriodModal();
        showNotification('An error occurred while closing the period', 'error');
    }
}

// Click-outside to close
document.getElementById('closePeriodModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeClosePeriodModal();
});

// ── Export Excel ──────────────────────────────────────────────────────────

function exportExcel() {
    if (!currentPeriod) {
        showNotification('Period info not loaded yet. Please wait.', 'error');
        return;
    }
    const url = `/reporting/export-excel?year=${currentPeriod.year}&month=${currentPeriod.month}`;
    window.location.href = url;
}
@endif

// ── Report data ───────────────────────────────────────────────────────────

async function loadReport() {
    const start = document.getElementById('rptStartDate').value;
    const end   = document.getElementById('rptEndDate').value;
    const tbody = document.getElementById('rptTableBody');

    tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fas fa-spinner fa-spin text-xl mb-2 block"></i>Loading...</td></tr>`;

    try {
        const params = new URLSearchParams();
        if (start) params.append('start_date', start);
        if (end)   params.append('end_date',   end);

        const res  = await fetch(`/api/reporting/timesheet-support?${params}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        rptAllData = json.data || [];
        applyRptFilter();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="7" class="px-4 py-10 text-center text-red-500 text-sm">${e.message}</td></tr>`;
    }
}

function applyRptFilter() {
    const statusFilter = document.getElementById('rptFilterStatus').value;
    rptFiltered = statusFilter ? rptAllData.filter(r => r.status === statusFilter) : rptAllData;
    renderRptTable();
    updateRptCards();
}

function filterCardStatus(status) {
    document.getElementById('rptFilterStatus').value = status;
    applyRptFilter();
}

function resetReport() {
    initPeriodDates();
    document.getElementById('rptFilterStatus').value = '';
    rptAllData  = [];
    rptFiltered = [];
    renderRptTable();
    updateRptCards();
}

function updateRptCards() {
    const all   = rptAllData;
    const match = all.filter(r => r.status === 'Match').length;
    const less  = all.filter(r => r.status === 'Less').length;
    const over  = all.filter(r => r.status === 'Over').length;

    document.getElementById('rptCardTotal').textContent = all.length;
    document.getElementById('rptCardMatch').textContent = match;
    document.getElementById('rptCardLess').textContent  = less;
    document.getElementById('rptCardOver').textContent  = over;
}

function renderRptTable() {
    const tbody = document.getElementById('rptTableBody');
    const empty = document.getElementById('rptEmpty');

    if (rptFiltered.length === 0) {
        tbody.innerHTML = '';
        if (empty) empty.classList.remove('hidden');
        return;
    }
    if (empty) empty.classList.add('hidden');

    const statusBadge = (status) => {
        if (status === 'Match') return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Match</span>`;
        if (status === 'Less')  return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">Less</span>`;
        if (status === 'Over')  return `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">Over</span>`;
        return `<span class="text-xs text-gray-400">—</span>`;
    };

    const fmt = (v) => v != null ? Number(v).toFixed(1) : '—';
    const esc = (s) => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

    tbody.innerHTML = rptFiltered.map(row => {
        const remainClass = row.remain === null ? 'text-gray-400'
            : row.remain < 0 ? 'text-red-600 font-semibold'
            : row.remain === 0 ? 'text-green-600 font-semibold'
            : 'text-yellow-600 font-semibold';

        return `<tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm font-medium text-gray-800">${esc(row.employee_name)}</td>
            <td class="px-4 py-3 text-sm text-purple-700 font-semibold whitespace-nowrap">
                <i class="fas fa-ticket-alt mr-1 opacity-60 text-xs"></i>#${esc(row.ticket_number || row.ticket_id)}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">${esc(row.customer_name)}</td>
            <td class="px-4 py-3 text-sm text-center font-semibold text-gray-800">${fmt(row.jatah_md)}</td>
            <td class="px-4 py-3 text-sm text-center font-semibold text-gray-800">${fmt(row.md_consumed)}</td>
            <td class="px-4 py-3 text-sm text-center ${remainClass}">${fmt(row.remain)}</td>
            <td class="px-4 py-3 text-center">${statusBadge(row.status)}</td>
        </tr>`;
    }).join('');
}
</script>
@endpush
@endsection
