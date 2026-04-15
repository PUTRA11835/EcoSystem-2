@extends('dashboard')
@section('title', 'MD Recap')
@section('page-title', 'Reporting')
@section('page-subtitle', 'MD Recap — mandays by employee and mode')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">MD Recap</h2>
            <p class="text-gray-600 mt-1">Approved timesheets — mandays per employee per mode</p>
        </div>
        <button onclick="exportRecap()"
            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-all duration-200">
            <i class="fas fa-file-excel text-xs"></i> Export Excel
        </button>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <div class="flex flex-wrap items-end justify-end gap-3">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Month</label>
                <div class="relative">
                <select id="recapMonth" class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white appearance-none">
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
                <i class="fas fa-bars absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">Year</label>
                <input type="number" id="recapYear" min="2000" step="1"
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent bg-white" style="width:110px;">
            </div>
            <div class="flex gap-2">
                <button onclick="loadRecap()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    Apply
                </button>
                <button onclick="resetRecap()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 340px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 480px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:200px;">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:120px;">Mode</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wide border-b border-gray-200" style="min-width:110px;">Mandays</th>
                    </tr>
                </thead>
                <tbody id="recapTableBody" class="divide-y divide-gray-100 bg-white">
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-xl mb-2 block"></i>
                            Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="recapEmpty" class="hidden text-center py-16">
            <i class="fas fa-inbox text-gray-300 text-4xl mb-3"></i>
            <p class="text-gray-600 font-semibold mb-1">No data found</p>
            <p class="text-gray-400 text-xs">No approved timesheets for the selected period</p>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
let recapData = [];

document.addEventListener('DOMContentLoaded', function () {
    const now = new Date();
    initYearDropdown(now.getFullYear());
    document.getElementById('recapMonth').value = now.getMonth() + 1;
    loadRecap();
});

function initYearDropdown(currentYear) {
    const inp = document.getElementById('recapYear');
    inp.value = currentYear;
}

async function loadRecap() {
    const month = document.getElementById('recapMonth').value;
    const year  = document.getElementById('recapYear').value;
    const tbody = document.getElementById('recapTableBody');

    tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-12 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-xl mb-2 block"></i>Loading...</td></tr>`;
    document.getElementById('recapEmpty').classList.add('hidden');

    try {
        const res  = await fetch(`/api/reporting/md-recap?month=${month}&year=${year}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        recapData = json.data || [];
        renderRecap();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-10 text-center text-red-500 text-sm">${e.message}</td></tr>`;
    }
}

function resetRecap() {
    const now = new Date();
    document.getElementById('recapMonth').value = now.getMonth() + 1;
    document.getElementById('recapYear').value  = now.getFullYear();
    recapData = [];
    document.getElementById('recapTableBody').innerHTML = '';
    document.getElementById('recapEmpty').classList.remove('hidden');
}

function renderRecap() {
    const tbody = document.getElementById('recapTableBody');
    const empty = document.getElementById('recapEmpty');

    if (recapData.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const fmt = v => v != null ? Number(v).toFixed(2) : '—';

    tbody.innerHTML = recapData.map(row => {
        const modeBadge = row.mode === 'OnSite'
            ? `<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs font-semibold">OnSite</span>`
            : `<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs font-semibold">Remote</span>`;

        return `<tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm font-medium text-gray-800">${esc(row.name)}</td>
            <td class="px-4 py-3">${modeBadge}</td>
            <td class="px-4 py-3 text-sm text-center font-semibold text-gray-800">${fmt(row.mandays)}</td>
        </tr>`;
    }).join('');

}

function exportRecap() {
    const month = document.getElementById('recapMonth').value;
    const year  = document.getElementById('recapYear').value;
    window.location.href = `/reporting/md-recap/export?month=${month}&year=${year}`;
}
</script>
@endpush
