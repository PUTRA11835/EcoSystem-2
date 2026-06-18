@extends('dashboard')
@section('title', 'Consultant Workload')
@section('page-title', 'Consultant Workload')
@section('page-subtitle', 'Monitor workload and ticket progress for each consultant')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Consultant Workload</h2>
            <p class="text-sm text-gray-500 mt-0.5">Monitor workload and ticket progress for each consultant</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="expandAll()"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all duration-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
                Expand All
            </button>
            <button onclick="collapseAll()"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-all duration-200">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                </svg>
                Collapse All
            </button>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Consultants</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1.5" id="cardTotal">—</p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Total active consultants</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Busy</p>
                    <p class="text-3xl font-bold text-red-600 mt-1.5" id="cardBusy">—</p>
                </div>
                <div class="w-10 h-10 bg-red-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Workload ≥ 70%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Moderate</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-1.5" id="cardModerate">—</p>
                </div>
                <div class="w-10 h-10 bg-yellow-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Workload 40–70%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Light</p>
                    <p class="text-3xl font-bold text-green-600 mt-1.5" id="cardLight">—</p>
                </div>
                <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Workload &lt; 40%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Active Tickets</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1.5" id="cardTickets">—</p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">In-progress tickets</p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="bg-gray-50 rounded-lg border border-gray-200 p-4 mb-5">
        <div class="flex items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filters</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Month</label>
                <select id="filterMonth" onchange="loadWorkload()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white hover:border-gray-400 focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Year</label>
                <select id="filterYear" onchange="loadWorkload()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white hover:border-gray-400 focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
                    @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Search</label>
                <input id="searchConsultant" type="text" placeholder="Name / ECI / module..."
                    oninput="filterTable()"
                    class="text-sm border border-gray-300 rounded-lg px-3 py-2 bg-white hover:border-gray-400 focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="workloadTable">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-left">Consultant</th>
                        <th class="px-4 py-3 text-left" style="min-width:170px">
                            <div class="flex items-center gap-1.5">
                                <span>Module</span>
                                <div class="relative" id="moduleFilterDd">
                                    <button type="button" id="moduleFilterBtn" onclick="toggleModulePanel()" class="flex items-center gap-1 normal-case font-normal tracking-normal text-xs text-gray-400 hover:text-gray-600 cursor-pointer bg-transparent border-0 p-0 focus:outline-none">
                                        <span id="moduleFilterLabel">All</span>
                                        <svg id="moduleFilterArrow" class="w-3 h-3 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div id="moduleFilterPanel" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] overflow-hidden" style="min-width:180px;">
                                        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
                                            <span class="text-xs font-semibold text-gray-500">Filter Modul</span>
                                            <button onclick="clearModuleFilter()" class="text-xs text-red-600 hover:underline">Reset</button>
                                        </div>
                                        <div id="moduleFilterList" class="overflow-y-auto py-1" style="max-height:220px;"></div>
                                    </div>
                                </div>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('ticket_count')" title="Sort">
                            <div class="flex items-center justify-center gap-1">
                                Tickets <span id="sort-icon-ticket_count" class="text-gray-300">⇅</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('total_days')" title="Sort">
                            <div class="flex items-center justify-end gap-1">
                                Alloc Days <span id="sort-icon-total_days" class="text-gray-300">⇅</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('workload_days')" title="Sort">
                            <div class="flex items-center justify-end gap-1">
                                Remain <span id="sort-icon-workload_days" class="text-gray-300">⇅</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('workload_pct')" style="min-width:200px" title="Sort">
                            <div class="flex items-center gap-1">
                                Workload
                                <span class="normal-case font-normal text-gray-400">(remain / allocated)</span>
                                <span id="sort-icon-workload_pct" class="text-gray-300">⇅</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('load_score')" style="min-width:180px" title="Sort">
                            <div class="flex items-center gap-1">
                                Load Score
                                <span class="normal-case font-normal text-gray-400">(remain × (1 + 0.1n))</span>
                                <span id="sort-icon-load_score" class="text-gray-300">⇅</span>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody id="workloadBody">
                    <tr>
                        <td colspan="8" class="text-center py-12 text-gray-400">
                            <svg class="animate-spin w-5 h-5 mx-auto mb-2 text-red-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            Loading data...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>



<script>
    let allConsultants = [];
    let currentSort = {
        key: 'workload_pct',
        dir: 'desc'
    };

    const STATUS_BADGE = {
        open:                    { text: 'Open',                    cls: 'bg-blue-100 text-blue-700' },
        inprocess:               { text: 'Inprocess',               cls: 'bg-yellow-100 text-yellow-700' },
        waiting_on_customer:     { text: 'Waiting Customer',        cls: 'bg-amber-100 text-amber-700' },
        waiting_on_3rd_party:    { text: 'Waiting 3rd Party',       cls: 'bg-indigo-100 text-indigo-700' },
        waiting_to_confirmation: { text: 'Waiting Confirmation',    cls: 'bg-teal-100 text-teal-700' },
        hold:                    { text: 'Hold',                    cls: 'bg-orange-100 text-orange-700' },
        cancelled:               { text: 'Cancelled',               cls: 'bg-gray-100 text-gray-500' },
        closed:                  { text: 'Closed',                  cls: 'bg-green-100 text-green-700' },
    };

    const PRIORITY_CLS = {
        Low: 'bg-gray-100 text-gray-600',
        Medium: 'bg-blue-100 text-blue-700',
        High: 'bg-red-100 text-red-700',
    };

    function barColor(pct) {
        if (pct >= 70) return 'bg-red-500';
        if (pct >= 40) return 'bg-yellow-400';
        return 'bg-green-500';
    }

    function progressBarColor(pct) {
        if (pct >= 75) return 'bg-green-500';
        if (pct >= 40) return 'bg-yellow-400';
        return 'bg-red-400';
    }

    function workloadTextColor(pct) {
        if (pct >= 70) return 'text-red-600';
        if (pct >= 40) return 'text-yellow-600';
        return 'text-green-600';
    }

    // ── API Load ───────────────────────────────────────────────────────
    async function loadWorkload() {
        const month = document.getElementById('filterMonth').value;
        const year = document.getElementById('filterYear').value;

        document.getElementById('workloadBody').innerHTML = `
        <tr><td colspan="8" class="text-center py-12 text-gray-400">
            <svg class="animate-spin w-5 h-5 mx-auto mb-2 text-red-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>Loading data...
        </td></tr>`;

        try {
            const res = await fetch(`/api/consultant-workload?month=${month}&year=${year}`);
            const text = await res.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch {
                console.error('Non-JSON response:', text.substring(0, 500));
                document.getElementById('workloadBody').innerHTML =
                    `<tr><td colspan="8" class="text-center py-8 text-red-500 text-sm">Server error. Check console.</td></tr>`;
                return;
            }
            if (!json.success) {
                document.getElementById('workloadBody').innerHTML =
                    `<tr><td colspan="8" class="text-center py-8 text-red-500 text-sm">${json.message}</td></tr>`;
                return;
            }
            allConsultants = json.data ?? [];
            populateModuleFilter();
            updateSortIcons();
            renderTable(applySortTo(allConsultants));
            updateSummary(allConsultants);
        } catch (e) {
            document.getElementById('workloadBody').innerHTML =
                `<tr><td colspan="8" class="text-center py-8 text-red-500 text-sm">Failed: ${e.message}</td></tr>`;
        }
    }

    let selectedModules = new Set();

    function toggleModulePanel() {
        const panel = document.getElementById('moduleFilterPanel');
        const arrow = document.getElementById('moduleFilterArrow');
        const isOpen = !panel.classList.contains('hidden');
        panel.classList.toggle('hidden', isOpen);
        arrow.classList.toggle('rotate-180', !isOpen);
    }

    function populateModuleFilter() {
        const modules = new Set();
        allConsultants.forEach(c => {
            if (c.modules && c.modules !== '-') {
                c.modules.split(', ').forEach(m => { if (m.trim()) modules.add(m.trim()); });
            }
        });

        // Remove deselected modules that no longer exist
        [...selectedModules].forEach(m => { if (!modules.has(m)) selectedModules.delete(m); });

        const list = document.getElementById('moduleFilterList');
        list.innerHTML = '';
        [...modules].sort().forEach(m => {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2.5 px-4 py-2 hover:bg-gray-50 cursor-pointer transition-colors';
            label.innerHTML = `
                <input type="checkbox" class="module-checkbox w-3.5 h-3.5 text-red-800 border-gray-300 rounded focus:ring-red-800 cursor-pointer" value="${m}" ${selectedModules.has(m) ? 'checked' : ''}>
                <span class="text-sm text-gray-700">${m}</span>`;
            label.querySelector('input').addEventListener('change', e => {
                e.target.checked ? selectedModules.add(m) : selectedModules.delete(m);
                updateModuleFilterLabel();
                filterTable();
            });
            list.appendChild(label);
        });

        updateModuleFilterLabel();
    }

    function updateModuleFilterLabel() {
        const lbl = document.getElementById('moduleFilterLabel');
        if (selectedModules.size === 0) {
            lbl.textContent = 'All';
            lbl.className = 'text-gray-400';
        } else {
            lbl.textContent = '';
        }
    }

    function clearModuleFilter() {
        selectedModules.clear();
        document.querySelectorAll('.module-checkbox').forEach(c => c.checked = false);
        updateModuleFilterLabel();
        filterTable();
    }

    // Close panel on outside click
    document.addEventListener('click', e => {
        const dd = document.getElementById('moduleFilterDd');
        if (dd && !dd.contains(e.target)) {
            document.getElementById('moduleFilterPanel')?.classList.add('hidden');
            document.getElementById('moduleFilterArrow')?.classList.remove('rotate-180');
        }
    });

    function filterTable() {
        const q = document.getElementById('searchConsultant').value.toLowerCase();
        let filtered = allConsultants;
        if (selectedModules.size > 0) {
            filtered = filtered.filter(c => {
                const empModules = (c.modules ?? '').split(', ').map(m => m.trim());
                return [...selectedModules].every(m => empModules.includes(m));
            });
        }
        if (q) filtered = filtered.filter(c =>
            c.name.toLowerCase().includes(q) ||
            (c.modules ?? '').toLowerCase().includes(q) ||
            c.eci.toLowerCase().includes(q));
        renderTable(applySortTo(filtered));
    }


    // ── Sort ───────────────────────────────────────────────────────────
    const SORTABLE_KEYS = ['ticket_count', 'total_days', 'workload_days', 'workload_pct', 'load_score'];

    function sortBy(key) {
        if (currentSort.key === key) {
            currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort = {
                key,
                dir: 'asc'
            };
        }
        updateSortIcons();
        filterTable();
    }

    function calcInProgress(c) {
        const tickets = c.tickets.filter(t => t.status === 'inprocess');
        let allocMd = 0,
            effectiveMd = 0,
            remainMd = 0;
        tickets.forEach(t => {
            const myD = (t.consultant_details ?? []).find(d => d.employee_id == c.employee_id);
            if (myD) {
                const alloc  = parseFloat(myD.mandays) || 0;
                const add    = parseFloat(myD.approved_additional) || 0;
                const remain = parseFloat(myD.remain_md) || 0;
                allocMd     += alloc;
                effectiveMd += alloc + add;
                remainMd    += remain;
            }
        });
        return {
            ticket_count: tickets.length,
            total_days: allocMd,
            workload_days: remainMd,
            workload_pct: effectiveMd > 0 ? Math.round(remainMd / effectiveMd * 1000) / 10 : 0,
            load_score: Math.round(remainMd * (1 + 0.1 * tickets.length) * 100) / 100,
        };
    }

    function applySortTo(list) {
        const {
            key,
            dir
        } = currentSort;
        return [...list].sort((a, b) => {
            const va = calcInProgress(a)[key] ?? 0;
            const vb = calcInProgress(b)[key] ?? 0;
            return dir === 'desc' ? vb - va : va - vb;
        });
    }

    function updateSortIcons() {
        SORTABLE_KEYS.forEach(k => {
            const el = document.getElementById(`sort-icon-${k}`);
            if (!el) return;
            if (k === currentSort.key) {
                el.textContent = currentSort.dir === 'asc' ? '↓' : '↑';
                el.className = 'text-red-500 font-bold';
            } else {
                el.textContent = '⇅';
                el.className = 'text-gray-300';
            }
        });
    }

    // ── Render ─────────────────────────────────────────────────────────
    function renderTable(consultants) {
        if (!consultants.length) {
            document.getElementById('workloadBody').innerHTML =
                `<tr><td colspan="8" class="text-center py-8 text-gray-400">No data available.</td></tr>`;
            return;
        }

        document.getElementById('workloadBody').innerHTML = consultants.map(c => consultantRows(c)).join('');
    }

    function barHtml(pct, colorFn, width = 100) {
        const w = Math.min(pct, 100);
        const cl = colorFn(pct);
        return `
        <div class="flex items-center gap-2">
            <div class="bg-gray-100 rounded-full h-2" style="width:${width}px">
                <div class="${cl} h-2 rounded-full transition-all" style="width:${w}%"></div>
            </div>
            <span class="text-xs font-bold ${workloadTextColor(pct)} w-9 text-right shrink-0">${pct}%</span>
        </div>`;
    }

    function ticketTotalMd(t) {
        return (t.consultant_details ?? []).reduce((s, d) => s + (parseFloat(d.mandays) || 0), 0);
    }

    function ticketTotalAddMd(t) {
        return (t.consultant_details ?? []).reduce((s, d) => s + (parseFloat(d.approved_additional) || 0), 0);
    }

    function ticketTotalEffectiveMd(t) {
        return (t.consultant_details ?? []).reduce((s, d) => s + (parseFloat(d.effective_md) || 0), 0);
    }

    function ticketRemainMd(t) {
        // Gunakan remain_md per-consultant yang sudah dihitung di backend
        const details = t.consultant_details ?? [];
        if (details.length > 0) {
            return Math.round(details.reduce((s, d) => s + (parseFloat(d.remain_md) || 0), 0) * 100) / 100;
        }
        const pct = parseFloat(t.progress_percentage) || 0;
        return Math.round(ticketTotalEffectiveMd(t) * (1 - pct / 100) * 100) / 100;
    }

    function consultantRows(c) {
        const activeStatuses = ['open', 'inprocess', 'waiting_on_customer', 'waiting_on_3rd_party', 'waiting_to_confirmation', 'hold'];
        const visibleTickets = c.tickets.filter(t => activeStatuses.includes(t.status));

        // Akumulasi hanya dari mandays consultant ini (bukan total semua consultant per tiket)
        let totalAllocMdMain = 0,
            totalAddMdMain = 0,
            totalEffectiveMdMain = 0,
            totalRemainMain = 0;
        visibleTickets.forEach(t => {
            const myD = (t.consultant_details ?? []).find(d => d.employee_id == c.employee_id);
            if (myD) {
                const alloc   = parseFloat(myD.mandays) || 0;
                const add     = parseFloat(myD.approved_additional) || 0;
                const remain  = parseFloat(myD.remain_md) || 0;
                totalAllocMdMain     += alloc;
                totalAddMdMain       += add;
                totalEffectiveMdMain += alloc + add;
                totalRemainMain      += remain;
            }
        });
        const ticketCount = visibleTickets.length;
        const wPct = totalEffectiveMdMain > 0 ? Math.round(totalRemainMain / totalEffectiveMdMain * 100 * 10) / 10 : 0;
        const wDays = Math.round(totalRemainMain * 100) / 100;
        const loadScore = Math.round(totalRemainMain * (1 + 0.1 * ticketCount) * 100) / 100;

        let html = `
    <tr class="border-b border-gray-100 hover:bg-gray-50/70 cursor-pointer"
        onclick="toggleTickets(${c.employee_id})" data-emp="${c.employee_id}">
        <td class="px-3 py-3 text-center text-gray-400 text-xs">
            <span id="chevron-${c.employee_id}" class="inline-block transition-transform duration-200">▶</span>
        </td>
        <td class="px-4 py-3">
            <div class="font-semibold text-gray-900 text-sm">${c.name}</div>
            <div class="text-xs text-gray-400 mt-0.5">${c.eci}</div>
        </td>
        <td class="px-4 py-3">
            ${c.modules && c.modules !== '-'
                ? `<span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded font-medium">${c.modules}</span>`
                : `<span class="text-xs text-gray-300">—</span>`}
        </td>
        <td class="px-4 py-3 text-center">
            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                ${ticketCount > 0 ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400'}">
                ${ticketCount}
            </span>
        </td>
        <td class="px-4 py-3 text-right font-semibold text-gray-800 tabular-nums">${totalAllocMdMain.toFixed(2)} days</td>
        <td class="px-4 py-3 text-right font-semibold text-orange-600 tabular-nums">
            ${wDays} d
            <span class="ml-1 text-xs font-bold bg-orange-100 text-orange-700 rounded px-1 py-0.5">↑${Math.ceil(wDays)} d</span>
        </td>
        <td class="px-4 py-3">
            <div class="flex items-center gap-2">
                <div class="bg-gray-100 rounded-full h-2" style="width:100px">
                    <div class="${barColor(wPct)} h-2 rounded-full transition-all" style="width:${Math.min(wPct,100)}%"></div>
                </div>
                <div class="shrink-0">
                    <span class="text-xs font-bold ${workloadTextColor(wPct)}">${wPct}%</span>
                    <span class="text-xs text-gray-400 ml-1">${wDays} d</span>
                </div>
            </div>
        </td>
        <td class="px-4 py-3">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold ${loadScore >= 10 ? 'text-red-600' : loadScore >= 5 ? 'text-yellow-600' : 'text-green-600'}">
                        ${loadScore}
                    </span>
                    <span class="text-xs text-gray-400 font-normal">score</span>
                </div>
                <div class="text-xs text-gray-400">${wDays} d × (1 + 0.1×${ticketCount})</div>
            </div>
        </td>
    </tr>`;

        if (visibleTickets.length === 0) {
            html += `
    <tr id="tickets-${c.employee_id}" class="hidden border-b border-blue-100">
        <td colspan="8" class="pl-12 pr-4 py-3 text-xs text-slate-400 italic bg-slate-50/80">
            No In Progress tickets
        </td>
    </tr>`;
        } else {
            html += `
    <tr id="tickets-${c.employee_id}" class="hidden">
        <td colspan="8" class="p-0 border-b-2 border-blue-200" style="background:#f0f5ff">
            <div class="mx-4 my-3 rounded-xl overflow-hidden border border-blue-200 shadow-sm">
            <table class="w-full">
                <thead>
                    <tr class="text-xs font-semibold text-blue-800 uppercase tracking-wide" style="background:#dbeafe">
                        <th class="pl-6 pr-3 py-2.5 text-left w-8">#</th>
                        <th class="px-3 py-2.5 text-left w-36">Ticket No.</th>
                        <th class="px-3 py-2.5 text-left">Subject</th>
                        <th class="px-3 py-2.5 text-left w-20">Role</th>
                        <th class="px-3 py-2.5 text-left w-28">Status</th>
                        <th class="px-3 py-2.5 text-left w-20">Priority</th>
                        <th class="px-3 py-2.5 text-right w-24">Alloc Days</th>
                        <th class="px-3 py-2.5 text-right w-24">Add. Days</th>
                        <th class="px-3 py-2.5 text-right w-36">Remain</th>
                        <th class="px-3 py-2.5 text-left w-48">Progress</th>
                    </tr>
                </thead>
                <tbody>
                    ${visibleTickets.map((t, idx) => ticketRow(t, idx + 1, c.employee_id, c.modules)).join('')}
                    <tr style="background:#eff6ff;border-top:1px solid #bfdbfe">
                        <td colspan="6" class="pl-6 pr-3 py-2.5 text-xs text-left text-blue-700 font-semibold">
                            Total (${visibleTickets.length} ticket${visibleTickets.length > 1 ? 's' : ''})
                        </td>
                        <td class="px-3 py-2.5 text-right text-xs font-bold text-gray-700">
                            ${totalAllocMdMain.toFixed(1)} days
                        </td>
                        <td class="px-3 py-2.5 text-right text-xs font-bold text-indigo-600">
                            ${totalAddMdMain.toFixed(1)} days
                        </td>
                        <td class="px-3 py-2.5 text-right text-xs font-bold text-orange-600">
                            ${totalRemainMain.toFixed(2)} d
                        </td>
                        <td colspan="1"></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </td>
    </tr>`;
        }

        return html;
    }

    function ticketRow(t, num, empId) {
        const st = STATUS_BADGE[t.status] ?? {
            text: t.status,
            cls: 'bg-gray-100 text-gray-600'
        };
        const prCls = PRIORITY_CLS[t.ticket_priority] ?? 'bg-gray-100 text-gray-600';
        const isTicketLead = t.role_in_ticket === 'pic';
        const roleCls = isTicketLead ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-sky-100 text-sky-700 border border-sky-200';
        const roleLabel = isTicketLead ? 'Ticket Lead' : 'Member';

        // Hanya ambil mandays milik consultant ini (bukan total semua consultant)
        const myDetail    = (t.consultant_details ?? []).find(d => d.employee_id == empId);
        const myAllocMd   = myDetail ? parseFloat(myDetail.mandays) || 0 : 0;
        const myAddMd     = myDetail ? parseFloat(myDetail.approved_additional) || 0 : 0;
        const myRemainMd  = myDetail ? parseFloat(myDetail.remain_md) || 0 : 0;
        const myPct       = myDetail ? parseFloat(myDetail.progress_percentage) || 0 : 0;

        const ticketPct = parseFloat(t.progress_percentage) || 0;
        const updAt = t.last_progress_at ?
            new Date(t.last_progress_at).toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }) :
            '—';

        const remainCell = myAllocMd > 0
            ? `<span class="font-semibold ${myRemainMd > 0 ? 'text-orange-600' : 'text-green-600'}">${myRemainMd.toFixed(2)} d</span>
               <span class="ml-1 text-xs font-bold bg-orange-100 text-orange-700 rounded px-1 py-0.5">↑${Math.ceil(myRemainMd)} d</span>`
            : `<span class="text-gray-300">—</span>`;

        return `
    <tr class="border-t border-blue-100 hover:bg-blue-50/60 transition-colors" style="background:#ffffff">
        <td class="pl-6 pr-3 py-2.5 text-xs text-gray-400">${num}</td>
        <td class="px-3 py-2.5 font-mono text-xs text-gray-500 whitespace-nowrap">${t.ticket_number ?? '—'}</td>
        <td class="px-3 py-2.5 text-xs text-gray-700 max-w-xs"><span class="line-clamp-2">${t.subject || '—'}</span></td>
        <td class="px-3 py-2.5">
            <span class="px-1.5 py-0.5 rounded text-xs font-semibold ${roleCls}">${roleLabel}</span>
        </td>
        <td class="px-3 py-2.5">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${st.cls}">${st.text}</span>
        </td>
        <td class="px-3 py-2.5">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${prCls}">${t.ticket_priority ?? '—'}</span>
        </td>
        <td class="px-3 py-2.5 text-right text-xs font-semibold text-gray-700">${myAllocMd > 0 ? myAllocMd.toFixed(2) + ' days' : '—'}</td>
        <td class="px-3 py-2.5 text-right text-xs text-gray-500">
            ${myAddMd > 0
                ? `<span class="text-indigo-600 font-semibold">${myAddMd.toFixed(2)} days</span>`
                : '<span class="text-gray-300">—</span>'}
        </td>
        <td class="px-3 py-2.5 text-right">${remainCell}</td>
        <td class="px-3 py-2.5">
            <div class="flex items-center gap-2">
                <div class="bg-gray-200 rounded-full h-2" style="width:90px">
                    <div class="${progressBarColor(myPct)} h-2 rounded-full" style="width:${myPct}%"></div>
                </div>
                <span class="text-xs font-bold ${myPct>=75?'text-green-700':myPct>=40?'text-yellow-600':'text-red-600'}">${myPct}%</span>
            </div>
            <div class="text-xs text-gray-400 mt-0.5">Updated: ${updAt}</div>
        </td>
    </tr>`;
    }

    // ── Expand / Collapse ──────────────────────────────────────────────
    function toggleTickets(empId) {
        const row = document.getElementById(`tickets-${empId}`);
        const chevron = document.getElementById(`chevron-${empId}`);
        const hidden = row.classList.contains('hidden');
        row.classList.toggle('hidden', !hidden);
        chevron.style.transform = hidden ? 'rotate(90deg)' : '';
    }

    function expandAll() {
        document.querySelectorAll('[id^="tickets-"]').forEach(el => el.classList.remove('hidden'));
        document.querySelectorAll('[id^="chevron-"]').forEach(el => el.style.transform = 'rotate(90deg)');
    }

    function collapseAll() {
        document.querySelectorAll('[id^="tickets-"]').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('[id^="chevron-"]').forEach(el => el.style.transform = '');
    }

    // ── Summary ────────────────────────────────────────────────────────
    function updateSummary(data) {
        const high = data.filter(c => calcInProgress(c).workload_pct >= 70).length;
        const mid = data.filter(c => {
            const p = calcInProgress(c).workload_pct;
            return p >= 40 && p < 70;
        }).length;
        const low = data.filter(c => calcInProgress(c).workload_pct < 40).length;
        const tickets = data.reduce((s, c) => s + calcInProgress(c).ticket_count, 0);

        document.getElementById('cardTotal').textContent = data.length;
        document.getElementById('cardBusy').textContent = high;
        document.getElementById('cardModerate').textContent = mid;
        document.getElementById('cardLight').textContent = low;
        document.getElementById('cardTickets').textContent = tickets;
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
        loadWorkload();
    });
</script>
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
@endsection