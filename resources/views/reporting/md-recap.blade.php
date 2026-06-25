@extends('dashboard')
@section('title', 'MD Recap')
@section('page-title', 'MD Recap')
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
            {{-- Current period badge (static info) --}}
            <div id="recapPeriodBadge" class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-lg text-xs text-gray-600">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span id="recapBadgeLabel">{{ now()->format('F Y') }}</span>
                <span id="recapBadgeStatus" class="font-semibold text-gray-400">...</span>
            </div>
            {{-- Period filter for table view --}}
            <div class="inline-flex items-center gap-1">
                <select id="recapFilterMonth" onchange="onPeriodChange()"
                    class="h-9 px-2 text-xs text-gray-700 bg-white border border-gray-300 rounded-lg outline-none focus:outline-none focus:ring-0 focus:border-gray-400 cursor-pointer shrink-0" style="width:9rem">
                    <option value="0">All Months</option>
                    @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn)
                        <option value="{{ $i + 1 }}" {{ now()->month == $i + 1 ? 'selected' : '' }}>{{ $mn }}</option>
                    @endforeach
                </select>
                <select id="recapFilterYear" onchange="onPeriodChange()"
                    class="h-9 px-2 text-xs text-gray-700 bg-white border border-gray-300 rounded-lg outline-none focus:outline-none focus:ring-0 focus:border-gray-400 cursor-pointer">
                    <option value="0">All Years</option>
                    @for($y = now()->year - 3; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ now()->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <button onclick="exportRecap()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all duration-200">
                <i class="fas fa-file-excel text-green-600 text-sm"></i>
                Export MD Recap
            </button>
            <button onclick="exportResolutionDays()"
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50 transition-all duration-200">
                <i class="fas fa-file-excel text-green-600 text-sm"></i>
                Export Resolution Days
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


    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 300px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 480px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        {{-- Name: text search panel --}}
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:180px; position:relative;">
                            <button type="button" onclick="toggleRecapTextPanel(event,'Name')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Name</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="recapTextIcon_Name" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="recapTextPanel_Name" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search name</label>
                                <input type="text" id="colFilterRecapName" placeholder="Type name…" oninput="applyRecapFilter()" onclick="event.stopPropagation()"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRecapTextPanel('Name')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                    <button type="button" onclick="closeRecapTextPanel('Name')" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button>
                                </div>
                            </div>
                        </th>
                        {{-- Mode: custom-dd --}}
                        <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:120px;">
                            <div class="custom-dd relative w-full" id="ddColFilterRecapMode" data-fixed="true" data-onchange="applyRecapFilter">
                                <button type="button" class="custom-dd-btn w-full flex items-center justify-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Mode</span>
                                    <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <input type="hidden" id="colFilterRecapMode" value="">
                                <div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5" style="max-height:200px;min-width:130px;">
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="">All</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="OnSite">OnSite</button>
                                    <button type="button" class="custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50" data-value="Remote">Remote</button>
                                </div>
                            </div>
                        </th>
                        {{-- Mandays: sort panel --}}
                        <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px; position:relative;">
                            <button type="button" onclick="toggleRecapMdSortPanel(event)" class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Mandays</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <span id="recapSortMdIcon" class="text-[10px] text-gray-300 font-bold shrink-0"></span>
                            </button>
                            <div id="recapMdSortPanel" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:180px;">
                                <span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Sort</span>
                                <div class="flex gap-2 mb-2">
                                    <button type="button" onclick="setRecapSort('mandays','asc')" id="recapSortMdAsc" class="flex-1 px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-gray-50">↑ Ascending</button>
                                    <button type="button" onclick="setRecapSort('mandays','desc')" id="recapSortMdDesc" class="flex-1 px-2 py-1 text-xs border border-gray-200 rounded-md hover:bg-gray-50">↓ Descending</button>
                                </div>
                                <div class="flex justify-end"><button type="button" onclick="closeRecapMdSortPanel()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Done</button></div>
                            </div>
                        </th>
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
let recapData    = [];
let recapSortKey = null;
let recapSortDir = null;
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    loadCurrentPeriod();
    updateRecapSortVisuals();
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="recapTextPanel_"]') && !e.target.closest('#recapMdSortPanel') &&
        !e.target.closest('[onclick*="toggleRecapTextPanel"]') && !e.target.closest('[onclick*="toggleRecapMdSortPanel"]')) {
        closeRecapMdSortPanel();
        closeRecapTextPanelAll();
    }
});

async function loadCurrentPeriod() {
    try {
        const res  = await fetch('/api/reporting/current-period', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success) {
            const p      = json.data;
            document.getElementById('recapBadgeLabel').textContent  = `${MONTHS[p.month - 1]} ${p.year}`;
            const status = document.getElementById('recapBadgeStatus');
            status.textContent = p.is_closed ? '(Closed)' : '(Open)';
            status.className   = p.is_closed ? 'font-semibold text-red-500' : 'font-semibold text-green-500';
        }
    } catch (e) {}
    loadRecap();
}

function onPeriodChange() {
    loadRecap();
}

function _getPeriodParams() {
    const month = parseInt(document.getElementById('recapFilterMonth')?.value || '0');
    const year  = parseInt(document.getElementById('recapFilterYear')?.value  || '0');
    const p     = new URLSearchParams();
    if (month) p.append('month', month);
    if (year)  p.append('year',  year);
    return p;
}

async function loadRecap() {
    const tbody = document.getElementById('recapTableBody');

    tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-14 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
        <span>Loading data...</span>
    </td></tr>`;
    document.getElementById('recapEmpty').classList.add('hidden');
    document.getElementById('recapStats').classList.add('hidden');

    const params = _getPeriodParams();
    try {
        const res  = await fetch(`/api/reporting/md-recap?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        recapData = json.data || [];
        renderRecap();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${escHtml(e.message)}</span>
            </div>
        </td></tr>`;
    }
}

function resetRecap() {
    const nameEl = document.getElementById('colFilterRecapName');
    if (nameEl) nameEl.value = '';
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('colFilterRecapMode', '');
    }
    recapSortKey = null;
    recapSortDir = null;
    updateRecapSortVisuals();
    _updateRecapFilterIcons();
    closeRecapMdSortPanel();
    closeRecapTextPanelAll();
    applyRecapFilter();
}

function applyRecapFilter() {
    _updateRecapFilterIcons();
    renderRecap();
}

// ── Sort & panel helpers ──────────────────────────────────────────────────────

function setRecapSort(key, dir) {
    recapSortKey = key;
    recapSortDir = dir;
    updateRecapSortVisuals();
    closeRecapMdSortPanel();
    renderRecap();
}

function updateRecapSortVisuals() {
    const icon = document.getElementById('recapSortMdIcon');
    if (icon) icon.textContent = recapSortKey === 'mandays' ? (recapSortDir === 'asc' ? '↑' : '↓') : '';
    const _btn = (id, active) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.className = `flex-1 px-2 py-1 text-xs border rounded-md hover:bg-gray-50 transition-colors ${active ? 'border-red-400 bg-red-50 text-red-700 font-semibold' : 'border-gray-200'}`;
    };
    _btn('recapSortMdAsc',  recapSortKey === 'mandays' && recapSortDir === 'asc');
    _btn('recapSortMdDesc', recapSortKey === 'mandays' && recapSortDir === 'desc');
}

function _updateRecapFilterIcons() {
    const icon  = document.getElementById('recapTextIcon_Name');
    const input = document.getElementById('colFilterRecapName');
    if (!icon) return;
    const active = !!(input?.value);
    icon.classList.toggle('text-red-500', active);
    icon.classList.toggle('text-gray-300', !active);
}

function toggleRecapMdSortPanel(event) {
    event.stopPropagation();
    const panel = document.getElementById('recapMdSortPanel');
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeRecapMdSortPanel(); closeRecapTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) panel.classList.remove('hidden');
}

function closeRecapMdSortPanel() {
    const p = document.getElementById('recapMdSortPanel');
    if (p) p.classList.add('hidden');
}

function toggleRecapTextPanel(event, key) {
    event.stopPropagation();
    const panel = document.getElementById('recapTextPanel_' + key);
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeRecapMdSortPanel(); closeRecapTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) {
        panel.classList.remove('hidden');
        const inp = panel.querySelector('input[type="text"]');
        if (inp) setTimeout(() => inp.focus(), 30);
    }
}

function closeRecapTextPanel(key) {
    const p = document.getElementById('recapTextPanel_' + key);
    if (p) p.classList.add('hidden');
}

function closeRecapTextPanelAll() {
    document.querySelectorAll('[id^="recapTextPanel_"]').forEach(p => p.classList.add('hidden'));
}

function clearRecapTextPanel(key) {
    const inp = document.getElementById('colFilterRecap' + key);
    if (inp) { inp.value = ''; applyRecapFilter(); }
}

function renderRecap() {
    const tbody      = document.getElementById('recapTableBody');
    const empty      = document.getElementById('recapEmpty');
    const stats      = document.getElementById('recapStats');
    const nameFilter = (document.getElementById('colFilterRecapName')?.value || '').toLowerCase().trim();
    const modeFilter = (document.getElementById('colFilterRecapMode')?.value || '');

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

    // Compute per-employee total mandays for sort
    const empTotals = new Map();
    grouped.forEach((modeMap, name) => {
        let t = 0;
        modeMap.forEach(agg => { t += agg.mandays; });
        empTotals.set(name, t);
    });

    let sortedEntries = [...grouped.entries()];
    if (recapSortKey === 'mandays') {
        sortedEntries.sort((a, b) => {
            const va = empTotals.get(a[0]) ?? 0;
            const vb = empTotals.get(b[0]) ?? 0;
            return recapSortDir === 'asc' ? va - vb : vb - va;
        });
    }

    let html = '';
    sortedEntries.forEach(([name, modeMap]) => {
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
                <td class="px-4 py-2 text-center">${modeBadge(mode)}</td>
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
    const params = _getPeriodParams();
    const name = document.getElementById('colFilterRecapName')?.value.trim();
    const mode = document.getElementById('colFilterRecapMode')?.value;
    if (name) params.append('name', name);
    if (mode) params.append('mode', mode);
    window.location.href = `/reporting/md-recap/export?${params.toString()}`;
}

function exportResolutionDays() {
    const params = _getPeriodParams();
    window.location.href = `/reporting/resolution-days/export?${params.toString()}`;
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush
