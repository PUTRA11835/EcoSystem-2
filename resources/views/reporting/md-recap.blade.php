@extends('dashboard')
@section('title', 'MD Recap')
@section('page-title', 'Reporting')
@section('page-subtitle', 'MD Recap — mandays by employee and mode')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">MD Recap</h2>
            <p id="recapPeriodLabel" class="text-sm text-gray-500 mt-0.5">Approved mandays by employee and mode</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div id="recapPeriodBadge" class="hidden items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span id="recapBadgeLabel">—</span>
                <span id="recapBadgeStatus" class="font-semibold"></span>
            </div>
            <button onclick="exportRecap()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export Excel
            </button>
        </div>
    </div>

    {{-- ── Summary Stats ───────────────────────────────────────────────────── --}}
    <div id="recapStats" class="hidden grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total MD</p>
            <p id="statTotalMd" class="text-2xl font-bold primary-text">—</p>
            <p class="text-xs text-gray-400 mt-0.5">All modes combined</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Employees</p>
            <p id="statEmployees" class="text-2xl font-bold text-gray-800">—</p>
            <p class="text-xs text-gray-400 mt-0.5">With approved timesheets</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">OnSite MD</p>
            <p id="statOnsiteMd" class="text-2xl font-bold text-green-700">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Field visits</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Remote MD</p>
            <p id="statRemoteMd" class="text-2xl font-bold text-blue-700">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Remote work</p>
        </div>
    </div>

    {{-- ── Filters ─────────────────────────────────────────────────────────── --}}
    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            {{-- Period --}}
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Period</label>
                <div class="flex items-center gap-2">
                    <div class="custom-dd relative flex-1" data-onchange="updatePeriodLabel">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                            <span class="custom-dd-label text-gray-700">—</span>
                            <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <input type="hidden" id="recapMonth" value="">
                        <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:220px;min-width:140px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="1">January</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="2">February</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="3">March</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="4">April</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="5">May</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="6">June</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="7">July</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="8">August</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="9">September</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="10">October</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="11">November</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="12">December</button>
                        </div>
                    </div>
                    <input type="number" id="recapYear" min="2000" step="1" onchange="updatePeriodLabel()"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white" style="width:90px;">
                </div>
                <p id="recapPeriodRange" class="text-xs text-gray-400 mt-1.5"></p>
            </div>
            {{-- Employee Search --}}
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Employee</label>
                <div class="relative">
                    <input type="text" id="recapFilterName" placeholder="Search employee…"
                        class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white transition-shadow">
                    <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>
            {{-- Mode --}}
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Mode</label>
                <div class="custom-dd relative" data-onchange="applyRecapFilter">
                    <button type="button" class="custom-dd-btn w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:border-gray-400 transition-all text-left">
                        <span class="custom-dd-label text-gray-500">All Modes</span>
                        <svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <input type="hidden" id="recapFilterMode" value="">
                    <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5">
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Modes</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="OnSite">OnSite</button>
                        <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="Remote">Remote</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex gap-2 justify-end mt-3 pt-3 border-t border-gray-100">
            <button onclick="resetRecap()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Reset
            </button>
            <button onclick="loadRecap()" id="recapApplyBtn"
                class="inline-flex items-center gap-1.5 px-5 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200 shadow-sm">
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                Apply
            </button>
        </div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 300px); min-height: 200px;">
            <table class="w-full text-sm border-collapse table-fixed" style="min-width: 480px;">
                <colgroup>
                    <col style="width: 40%">
                    <col style="width: 25%">
                    <col style="width: 20%">
                    <col style="width: 15%">
                </colgroup>
                <thead class="sticky top-0 z-10">
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Entries</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Mode</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Mandays</th>
                    </tr>
                </thead>
                <tbody id="recapTableBody" class="bg-white">
                    <tr>
                        <td colspan="4" class="px-4 py-14 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
                            <span class="text-gray-400">Loading data...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        <div id="recapEmpty" class="hidden text-center py-16 px-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">No data found</p>
            <p class="text-gray-400 text-xs mt-1">No approved timesheets for the selected period</p>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.primary-focus:focus {
    outline: none;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.15) !important;
}
.primary-text { color: var(--primary-color) !important; }
.recap-emp-row td   { background: #f9fafb; }
.recap-emp-row:hover td { background: #f3f4f6; }
.recap-sub-row td   { background: #fff; }
.recap-sub-row:hover td { background: #f9fafb; }
</style>
@endpush

@push('scripts')
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script>
let recapData = [];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

const MONTH_NAMES_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function updatePeriodLabel() {
    const month = parseInt(document.getElementById('recapMonth').value);
    const year  = parseInt(document.getElementById('recapYear').value);
    if (!month || !year) return;

    // Period M/Y: 21st of (M-1) → 20th of M
    const startMonth = month === 1 ? 12 : month - 1;
    const startYear  = month === 1 ? year - 1 : year;
    const label = `${21} ${MONTH_NAMES_SHORT[startMonth - 1]} ${startYear} – ${20} ${MONTH_NAMES_SHORT[month - 1]} ${year}`;
    const el = document.getElementById('recapPeriodRange');
    if (el) el.textContent = label;
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();

    const now  = new Date();
    const day  = now.getDate();
    const m0   = now.getMonth();
    const year = now.getFullYear();
    let periodMonth, periodYear;
    if (day >= 21) {
        const nextM = m0 + 2;
        if (nextM > 12) { periodMonth = 1;      periodYear = year + 1; }
        else            { periodMonth = nextM;  periodYear = year; }
    } else {
        periodMonth = m0 + 1;
        periodYear  = year;
    }
    document.getElementById('recapYear').value = periodYear;
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('recapMonth', String(periodMonth));
    } else {
        document.getElementById('recapMonth').value = periodMonth;
    }
    updatePeriodLabel();
    loadCurrentPeriodBadge();
    loadRecap();

    document.getElementById('recapFilterName').addEventListener('input', applyRecapFilter);
    // recapFilterMode is now a custom dropdown — change fires via data-onchange="applyRecapFilter"
});

async function loadCurrentPeriodBadge() {
    try {
        const res  = await fetch('/api/reporting/current-period', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) return;
        const p      = json.data;
        const badge  = document.getElementById('recapPeriodBadge');
        const label  = document.getElementById('recapBadgeLabel');
        const status = document.getElementById('recapBadgeStatus');
        label.textContent  = `${MONTHS[p.month - 1]} ${p.year}`;
        status.textContent = p.is_closed ? '(Closed)' : '(Open)';
        status.className   = p.is_closed ? 'font-semibold text-red-500' : 'font-semibold text-green-500';
        badge.classList.remove('hidden');
        badge.classList.add('flex');
    } catch (e) {}
}

async function loadRecap() {
    const month    = document.getElementById('recapMonth').value;
    const year     = document.getElementById('recapYear').value;
    const tbody    = document.getElementById('recapTableBody');
    const applyBtn = document.getElementById('recapApplyBtn');

    tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-14 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
        <span>Loading data...</span>
    </td></tr>`;
    document.getElementById('recapEmpty').classList.add('hidden');
    document.getElementById('recapStats').classList.add('hidden');
    applyBtn.disabled = true;
    applyBtn.innerHTML = `<i class="fas fa-spinner fa-spin text-xs"></i> Loading...`;

    try {
        const res  = await fetch(`/api/reporting/md-recap?month=${month}&year=${year}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        recapData = json.data || [];
        document.getElementById('recapPeriodLabel').textContent = `Period: ${MONTHS[month - 1]} ${year}`;
        renderRecap();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="4" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${escHtml(e.message)}</span>
            </div>
        </td></tr>`;
    } finally {
        applyBtn.disabled = false;
        applyBtn.innerHTML = `<svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg> Apply`;
    }
}

function resetRecap() {
    const now  = new Date();
    const day  = now.getDate();
    const m0   = now.getMonth();
    const year = now.getFullYear();
    let resetMonth, resetYear;
    if (day >= 21) {
        const nextM = m0 + 2;
        if (nextM > 12) { resetMonth = 1;      resetYear = year + 1; }
        else            { resetMonth = nextM;  resetYear = year; }
    } else {
        resetMonth = m0 + 1;
        resetYear  = year;
    }
    document.getElementById('recapYear').value = resetYear;
    document.getElementById('recapFilterName').value = '';
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('recapMonth', String(resetMonth));
        setCustomDropdownValue('recapFilterMode', '');
    } else {
        document.getElementById('recapMonth').value = resetMonth;
        document.getElementById('recapFilterMode').value = '';
    }
    updatePeriodLabel();
    recapData = [];
    document.getElementById('recapTableBody').innerHTML = '';
    document.getElementById('recapEmpty').classList.remove('hidden');
    document.getElementById('recapStats').classList.add('hidden');
}

function applyRecapFilter() {
    renderRecap();
}

function renderRecap() {
    const tbody      = document.getElementById('recapTableBody');
    const empty      = document.getElementById('recapEmpty');
    const stats      = document.getElementById('recapStats');
    const nameFilter = (document.getElementById('recapFilterName')?.value || '').toLowerCase().trim();
    const modeFilter = (document.getElementById('recapFilterMode')?.value || '');

    const filtered = recapData.filter(r => {
        if (nameFilter && !(r.name || '').toLowerCase().includes(nameFilter)) return false;
        if (modeFilter && r.mode !== modeFilter) return false;
        return true;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        stats.classList.add('hidden');
        return;
    }
    empty.classList.add('hidden');

    const modeBadge = (mode) => (mode || '').toLowerCase() === 'onsite'
        ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700">OnSite</span>`
        : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700">Remote</span>`;

    // Group by employee → mode → {count, mandays}
    const grouped = new Map();
    filtered.forEach(row => {
        if (!grouped.has(row.name)) grouped.set(row.name, new Map());
        const modeMap = grouped.get(row.name);
        if (!modeMap.has(row.mode)) modeMap.set(row.mode, { count: 0, mandays: 0 });
        const agg = modeMap.get(row.mode);
        agg.count++;
        agg.mandays += Number(row.mandays || 0);
    });

    let totalMd     = 0;
    let totalOnsite = 0;
    let totalRemote = 0;
    const empCount  = grouped.size;

    let html = '';
    grouped.forEach((modeMap, name) => {
        modeMap.forEach((agg, mode) => {
            totalMd += agg.mandays;
            if ((mode || '').toLowerCase() === 'onsite') totalOnsite += agg.mandays;
            else totalRemote += agg.mandays;
        });

        // Employee header row
        const initials = name.trim().split(' ').map(w => w[0]).slice(0,2).join('').toUpperCase();
        html += `<tr class="recap-emp-row border-t-2 border-gray-200">
            <td class="px-4 py-2.5" colspan="4">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold flex items-center justify-center shrink-0">${initials}</div>
                    <span class="text-sm font-semibold text-gray-800">${escHtml(name)}</span>
                </div>
            </td>
        </tr>`;

        // One merged row per mode
        modeMap.forEach((agg, mode) => {
            const isOnsite   = (mode || '').toLowerCase() === 'onsite';
            const valueColor = isOnsite ? 'text-green-700 font-semibold' : 'text-blue-700 font-semibold';
            const entryLabel = agg.count === 1 ? '1 entry' : `${agg.count} entries`;
            html += `<tr class="recap-sub-row border-t border-gray-100">
                <td class="px-4 py-2">
                    <span class="inline-flex items-center gap-2 pl-6 text-xs text-gray-400">
                        <span class="w-1 h-1 rounded-full bg-gray-300 shrink-0"></span>
                        ${entryLabel}
                    </span>
                </td>
                <td class="px-4 py-2"></td>
                <td class="px-4 py-2">${modeBadge(mode)}</td>
                <td class="px-4 py-2 text-sm text-center ${valueColor}">${agg.mandays.toFixed(2)}</td>
            </tr>`;
        });
    });

    tbody.innerHTML = html;
    document.getElementById('statTotalMd').textContent   = totalMd.toFixed(2);
    document.getElementById('statEmployees').textContent = empCount;
    document.getElementById('statOnsiteMd').textContent  = totalOnsite.toFixed(2);
    document.getElementById('statRemoteMd').textContent  = totalRemote.toFixed(2);
    stats.classList.remove('hidden');
}

function exportRecap() {
    const month = document.getElementById('recapMonth').value;
    const year  = document.getElementById('recapYear').value;
    window.location.href = `/reporting/md-recap/export?month=${month}&year=${year}`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush
