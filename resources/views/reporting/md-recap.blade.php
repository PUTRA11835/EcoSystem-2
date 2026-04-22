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
            <p id="recapPeriodLabel" class="text-sm text-gray-500 mt-0.5">Select a period and click Apply</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Month</label>
                <div class="relative">
                    <select id="recapMonth" class="pl-3 pr-8 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white appearance-none min-w-[130px]">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                    <svg class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Year</label>
                <input type="number" id="recapYear" min="2000" step="1"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm primary-focus bg-white" style="width:100px;">
            </div>
            <div class="flex gap-2 items-end">
                <button onclick="loadRecap()" id="recapApplyBtn"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200 shadow-sm">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                    Apply
                </button>
                <button onclick="exportRecap()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-lg transition-all duration-200 shadow-sm">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Export Excel
                </button>
                <button onclick="resetRecap()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-600 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Reset
                </button>
            </div>
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
            <table class="w-full text-sm border-collapse" style="min-width: 400px;">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-28">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-28">Mode</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider w-28">Mandays</th>
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
.recap-total-row td { background: #f3f4f6; }
</style>
@endpush

@push('scripts')
<script>
let recapData = [];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

document.addEventListener('DOMContentLoaded', function () {
    const now = new Date();
    document.getElementById('recapMonth').value = now.getMonth() + 1;
    document.getElementById('recapYear').value  = now.getFullYear();
    loadRecap();
});

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
    const now = new Date();
    document.getElementById('recapMonth').value = now.getMonth() + 1;
    document.getElementById('recapYear').value  = now.getFullYear();
    document.getElementById('recapPeriodLabel').textContent = 'Select a period and click Apply';
    recapData = [];
    document.getElementById('recapTableBody').innerHTML = '';
    document.getElementById('recapEmpty').classList.remove('hidden');
    document.getElementById('recapStats').classList.add('hidden');
}

function renderRecap() {
    const tbody = document.getElementById('recapTableBody');
    const empty = document.getElementById('recapEmpty');
    const stats = document.getElementById('recapStats');

    if (recapData.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        stats.classList.add('hidden');
        return;
    }
    empty.classList.add('hidden');

    const fmtDate = (d) => {
        if (!d) return '—';
        const [y, m, day] = String(d).split('-');
        return `${day}/${m}/${y}`;
    };
    const modeBadge = (mode) => (mode || '').toLowerCase() === 'onsite'
        ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700">OnSite</span>`
        : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-700">Remote</span>`;

    // Group entries by employee (preserve insertion order = sorted by name from backend)
    const grouped = new Map();
    recapData.forEach(row => {
        if (!grouped.has(row.name)) grouped.set(row.name, []);
        grouped.get(row.name).push(row);
    });

    let totalMd     = 0;
    let totalOnsite = 0;
    let totalRemote = 0;
    const empCount  = grouped.size;

    let html = '';
    grouped.forEach((entries, name) => {
        const subtotal = entries.reduce((s, r) => s + Number(r.mandays || 0), 0);
        totalMd += subtotal;
        entries.forEach(r => {
            if ((r.mode || '').toLowerCase() === 'onsite') totalOnsite += Number(r.mandays || 0);
            else totalRemote += Number(r.mandays || 0);
        });

        // Employee header row (name + subtotal)
        html += `<tr class="recap-emp-row border-t-2 border-gray-200">
            <td class="px-4 py-2.5 text-sm font-semibold text-gray-800" colspan="2">${escHtml(name)}</td>
            <td class="px-4 py-2.5"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-gray-200 text-gray-600">SUBTOTAL</span></td>
            <td class="px-4 py-2.5 text-sm text-center font-bold text-gray-800">${subtotal.toFixed(2)}</td>
        </tr>`;

        // One row per timesheet entry
        entries.forEach(r => {
            const isOnsite   = (r.mode || '').toLowerCase() === 'onsite';
            const valueColor = isOnsite ? 'text-green-700' : 'text-blue-700';
            html += `<tr class="recap-sub-row border-t border-gray-100">
                <td class="pl-8 pr-4 py-2 text-xs text-gray-400"></td>
                <td class="px-4 py-2 text-xs text-gray-600 whitespace-nowrap">${fmtDate(r.date)}</td>
                <td class="px-4 py-2">${modeBadge(r.mode)}</td>
                <td class="px-4 py-2 text-xs text-center font-semibold ${valueColor}">${Number(r.mandays).toFixed(2)}</td>
            </tr>`;
        });
    });

    html += `<tr class="recap-total-row border-t-2 border-gray-300">
        <td class="px-4 py-3 text-sm font-bold text-gray-800" colspan="2">Grand Total</td>
        <td class="px-4 py-3 text-xs text-gray-500">${empCount} employee${empCount !== 1 ? 's' : ''}</td>
        <td class="px-4 py-3 text-sm text-center font-bold primary-text">${totalMd.toFixed(2)}</td>
    </tr>`;

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
