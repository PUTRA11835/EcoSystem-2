@extends('dashboard')
@section('title', 'Reporting')
@section('page-title', 'Reporting')
@section('page-subtitle', 'Timesheet performance reports')

@push('styles')
<style>
    .primary-focus:focus {
        outline: none;
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important;
    }
    .primary-text { color: var(--primary-color) !important; }

    /* Summary card active state */
    .rpt-card-active {
        border-color: var(--primary-color) !important;
        box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.18) !important;
    }

    /* Sticky table header */
    #rptTableHead th {
        position: sticky;
        top: 0;
        z-index: 5;
    }

    /* Table totals row */
    #rptTotalsRow td {
        background: #f8fafc;
        border-top: 2px solid #e2e8f0;
    }

    /* Loading shimmer */
    @keyframes rptShimmer {
        0%   { opacity: 1; }
        50%  { opacity: 0.45; }
        100% { opacity: 1; }
    }
    .rpt-loading-icon { animation: rptShimmer 1.4s ease-in-out infinite; }

</style>
@endpush

@section('content')
@php
    $roleId = session('user')['role']['id'] ?? 0;
    $canManage = in_array($roleId, [1, 5]); // EC Administrator (1) or Delivery Support Head (5)
@endphp

<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Timesheet Report</h2>
            <p class="text-sm text-gray-500 mt-0.5">Approved support timesheets — MD quota vs consumed per ticket</p>
        </div>
        @if($canManage)
        <div class="flex flex-wrap items-center gap-2">
            {{-- Period badge --}}
            <div id="periodBadge"
                 class="hidden items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span id="periodLabel">—</span>
                <span id="periodStatus" class="font-semibold"></span>
            </div>
            {{-- Export Excel --}}
            <button onclick="exportExcel()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Excel
            </button>
            {{-- Close Period --}}
            <button id="btnClosePeriod" onclick="openClosePeriodModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Close Period
            </button>
        </div>
        @endif
    </div>

    {{-- ── Summary Cards ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        {{-- Total --}}
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total Entries</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1.5" id="rptCardTotal">0</p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">All timesheet records</p>
        </div>
        {{-- Match --}}
        <div id="rptCardMatchBox"
             class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-green-400 hover:shadow-md transition-all duration-200"
             onclick="filterCardStatus('Match')">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Match</p>
                    <p class="text-3xl font-bold text-green-600 mt-1.5" id="rptCardMatch">0</p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MD consumed = quota</p>
        </div>
        {{-- Less --}}
        <div id="rptCardLessBox"
             class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-yellow-400 hover:shadow-md transition-all duration-200"
             onclick="filterCardStatus('Less')">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Less</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-1.5" id="rptCardLess">0</p>
                </div>
                <div class="w-10 h-10 bg-yellow-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MD consumed &lt; quota</p>
        </div>
        {{-- Over --}}
        <div id="rptCardOverBox"
             class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm cursor-pointer hover:border-red-400 hover:shadow-md transition-all duration-200"
             onclick="filterCardStatus('Over')">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Over</p>
                    <p class="text-3xl font-bold text-red-600 mt-1.5" id="rptCardOver">0</p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">MD consumed &gt; quota</p>
        </div>
    </div>

    {{-- ── Filter Bar ─────────────────────────────────────────────────────── --}}
    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</span>
        </div>
        <div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                {{-- Start Date --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Start Date</label>
                    <input type="date" id="rptStartDate"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white transition-shadow">
                </div>
                {{-- End Date --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">End Date</label>
                    <input type="date" id="rptEndDate"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white transition-shadow">
                </div>
                {{-- Employee Search --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Employee</label>
                    <div class="relative">
                        <input type="text" id="rptFilterName" placeholder="Search employee…"
                            class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white transition-shadow">
                        <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
                {{-- Status --}}
                <div class="flex flex-col">
                    <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Status</label>
                    <div class="relative">
                        <select id="rptFilterStatus"
                            class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm primary-focus bg-white appearance-none transition-shadow">
                            <option value="">All Status</option>
                            <option value="Match">Match</option>
                            <option value="Less">Less</option>
                            <option value="Over">Over</option>
                        </select>
                        <svg class="w-4 h-4 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
                <button onclick="resetReport()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Reset
                </button>
                <button id="btnApplyReport" onclick="loadReport()"
                    class="inline-flex items-center gap-1.5 px-5 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Apply
                </button>
            </div>
        </div>
    </div>

    {{-- ── Data Table ──────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">

        {{-- Table container --}}
        <div class="overflow-auto" style="max-height: calc(100vh - 400px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 820px;">
                <thead id="rptTableHead">
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:160px;">Employee</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:110px;">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:130px;">Ticket</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:130px;">Customer</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:95px;">MD Input</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:90px;">Quota MD</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:90px;">Remain</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wide" style="min-width:100px;">Status</th>
                    </tr>
                </thead>
                <tbody id="rptTableBody" class="divide-y divide-gray-100 bg-white">
                    {{-- Initial loading state --}}
                    <tr>
                        <td colspan="8" class="px-4 py-16 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="w-8 h-8 text-gray-300 rpt-loading-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <p class="text-sm text-gray-400 font-medium">Loading report data…</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state (outside scroll area) --}}
        <div id="rptEmpty" class="hidden py-16 text-center">
            <svg class="w-14 h-14 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-gray-600 font-semibold text-sm">No timesheet records found</p>
            <p class="text-gray-400 text-xs mt-1">Try adjusting the date range or clearing the status filter</p>
        </div>
    </div>
</div>

@if($canManage)
{{-- ── Close Period Modal ──────────────────────────────────────────────────── --}}
<div id="closePeriodModal"
     class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full shadow-2xl overflow-hidden">
        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900">Close Reporting Period</h3>
            </div>
            <button onclick="closeClosePeriodModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        {{-- Modal body --}}
        <div class="p-6">
            <p class="text-sm text-gray-600 text-center mb-2">
                You are about to close the reporting period:
            </p>
            <p class="text-lg font-bold text-gray-900 text-center mb-4" id="closePeriodName">—</p>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-sm text-red-700">
                        After closing, new timesheet submissions dated within this period will be counted in the next period.
                        <strong>This action cannot be undone.</strong>
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeClosePeriodModal()"
                    class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button onclick="confirmClosePeriod()"
                    class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all">
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
let currentPeriod = null;

const MONTH_NAMES = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];

// ── Init ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    initPeriodDates();
    @if($canManage)
    loadCurrentPeriod();
    @endif
    loadReport();

    // Live employee name filter
    document.getElementById('rptFilterName').addEventListener('input', applyRptFilter);
    document.getElementById('rptFilterStatus').addEventListener('change', applyRptFilter);
});

function initPeriodDates() {
    // Always default to the most recently completed period:
    // 21st of the previous calendar month → 20th of the current calendar month.
    // On day >= 21 the new period just started (empty); showing the previous one is more useful.
    const now   = new Date();
    const prev  = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const pYear  = prev.getFullYear();
    const pMonth = prev.getMonth(); // 0-indexed
    const startDate = `${pYear}-${String(pMonth + 1).padStart(2,'0')}-21`;
    const endMonth  = pMonth === 11 ? 1 : pMonth + 2;
    const endYear   = pMonth === 11 ? pYear + 1 : pYear;
    const endDate   = `${endYear}-${String(endMonth).padStart(2,'0')}-20`;
    document.getElementById('rptStartDate').value = startDate;
    document.getElementById('rptEndDate').value   = endDate;
}

// ── Period info ───────────────────────────────────────────────────────────────

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
    const badge    = document.getElementById('periodBadge');
    const label    = document.getElementById('periodLabel');
    const status   = document.getElementById('periodStatus');
    const btnClose = document.getElementById('btnClosePeriod');

    label.textContent = `${MONTH_NAMES[currentPeriod.month - 1]} ${currentPeriod.year}`;

    if (currentPeriod.is_closed) {
        status.textContent = '(Closed)';
        status.className   = 'font-semibold text-red-500';
        if (btnClose) { btnClose.disabled = true; btnClose.classList.add('opacity-50', 'cursor-not-allowed'); }
    } else {
        status.textContent = '(Open)';
        status.className   = 'font-semibold text-green-500';
        if (btnClose) { btnClose.disabled = false; btnClose.classList.remove('opacity-50', 'cursor-not-allowed'); }
    }
    badge.classList.remove('hidden');
    badge.classList.add('flex');
}

// ── Close Period Modal ────────────────────────────────────────────────────────

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

document.getElementById('closePeriodModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeClosePeriodModal();
});

// ── Export Excel ──────────────────────────────────────────────────────────────

function exportExcel() {
    if (!currentPeriod) {
        showNotification('Period info not loaded yet. Please wait.', 'error');
        return;
    }
    window.location.href = `/reporting/export-excel?year=${currentPeriod.year}&month=${currentPeriod.month}`;
}
@endif

// ── Report data ───────────────────────────────────────────────────────────────

async function loadReport() {
    const start  = document.getElementById('rptStartDate').value;
    const end    = document.getElementById('rptEndDate').value;
    const tbody  = document.getElementById('rptTableBody');
    const btn    = document.getElementById('btnApplyReport');

    // Apply button loading state
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg> Loading…`;
    }

    tbody.innerHTML = `<tr>
        <td colspan="8" class="px-4 py-16 text-center">
            <div class="flex flex-col items-center gap-2">
                <svg class="w-8 h-8 text-gray-300 rpt-loading-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <p class="text-sm text-gray-400 font-medium">Loading report data…</p>
            </div>
        </td>
    </tr>`;
    document.getElementById('rptEmpty')?.classList.add('hidden');

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
        tbody.innerHTML = `<tr>
            <td colspan="8" class="px-4 py-12 text-center">
                <div class="flex flex-col items-center gap-2">
                    <svg class="w-8 h-8 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-sm text-red-500 font-medium">Failed to load report</p>
                    <p class="text-xs text-gray-400">${escRpt(e.message)}</p>
                </div>
            </td>
        </tr>`;
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg> Apply`;
        }
    }
}

function applyRptFilter() {
    const statusFilter = document.getElementById('rptFilterStatus').value;
    const nameFilter   = (document.getElementById('rptFilterName').value || '').trim().toLowerCase();

    rptFiltered = rptAllData.filter(r => {
        const matchStatus = !statusFilter || r.status === statusFilter;
        const matchName   = !nameFilter   || String(r.employee_name ?? '').toLowerCase().includes(nameFilter);
        return matchStatus && matchName;
    });
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
    document.getElementById('rptFilterName').value   = '';
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

    const fmt     = (v) => v != null ? Number(v).toFixed(1) : '—';
    const fmtDate = (d) => {
        if (!d) return '—';
        const [y, m, day] = String(d).split('-');
        return `${day}/${m}/${y}`;
    };
    const remClass = (v) => v === null ? 'text-gray-400'
        : v < 0  ? 'text-red-600 font-semibold'
        : v === 0 ? 'text-green-600 font-semibold'
        : 'text-yellow-600 font-semibold';

    // ── Flat list: one row per timesheet entry ─────────────────────────────
    let rows = rptFiltered.map(row => `<tr class="hover:bg-gray-50 transition-colors border-t border-gray-100">
        <td class="px-4 py-3 text-sm font-medium text-gray-800">${escRpt(row.employee_name)}</td>
        <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">${fmtDate(row.date)}</td>
        <td class="px-4 py-3 text-sm text-purple-700 font-semibold whitespace-nowrap">
            <span class="inline-flex items-center gap-1">
                <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>
                </svg>
                #${escRpt(row.ticket_number || row.ticket_id)}
            </span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600">${escRpt(row.customer_name)}</td>
        <td class="px-4 py-3 text-sm text-center font-semibold text-gray-800">${fmt(row.md_consumed)}</td>
        <td class="px-4 py-3 text-sm text-center font-semibold text-gray-600">${fmt(row.jatah_md)}</td>
        <td class="px-4 py-3 text-sm text-center ${remClass(row.remain)}">${fmt(row.remain)}</td>
        <td class="px-4 py-3 text-center">${statusBadge(row.status)}</td>
    </tr>`).join('');

    // ── Totals row ─────────────────────────────────────────────────────────
    const totalConsumed = rptFiltered.reduce((s, r) => s + (Number(r.md_consumed) || 0), 0);

    rows += `<tr id="rptTotalsRow">
        <td class="px-4 py-3 text-xs font-bold text-gray-500 uppercase tracking-wide" colspan="4">
            Totals (${rptFiltered.length} entries)
        </td>
        <td class="px-4 py-3 text-sm text-center font-bold text-gray-800">${totalConsumed.toFixed(1)}</td>
        <td class="px-4 py-3" colspan="3"></td>
    </tr>`;

    tbody.innerHTML = rows;
}

function escRpt(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush
@endsection
