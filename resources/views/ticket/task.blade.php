@extends('dashboard')
@section('title', 'My Tasks')
@section('page-title', 'My Tasks')
@section('page-subtitle', 'Active tickets where I am assigned as Lead')

@section('content')
<div class="flex flex-col gap-6">

    {{-- Top Controls --}}
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        {{-- Period Selectors --}}
        <div class="flex items-center gap-2">
            <select id="filterMonth" onchange="loadTasks()"
                class="text-sm px-3 py-2 bg-white border border-gray-200 rounded-xl shadow-sm text-gray-700 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent cursor-pointer hover:border-gray-300 transition">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                    </option>
                    @endfor
            </select>
            <select id="filterYear" onchange="loadTasks()"
                class="text-sm px-3 py-2 bg-white border border-gray-200 rounded-xl shadow-sm text-gray-700 font-medium focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent cursor-pointer hover:border-gray-300 transition">
                @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
            </select>
        </div>

        {{-- Search --}}
        <div class="relative flex-1 max-w-xs">
            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                </svg>
            </span>
            <input id="searchTask" type="text" placeholder="Search ticket, subject, customer…"
                oninput="filterTable()"
                class="w-full text-sm pl-9 pr-4 py-2 bg-white border border-gray-200 rounded-xl shadow-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent hover:border-gray-300 transition">
        </div>

        {{-- Ticket counter badge --}}
        <div class="sm:ml-auto flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 inline-block"></span>
                <span id="summaryText">— active ticket(s)</span>
            </span>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        {{-- Active Tickets --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-col gap-3 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute top-0 left-0 w-1 h-full bg-indigo-500 rounded-l-2xl"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Active Tickets</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-gray-900 leading-none" id="cardTicketCount">—</p>
        </div>

        {{-- Total Alloc MD --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-col gap-3 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute top-0 left-0 w-1 h-full bg-blue-500 rounded-l-2xl"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Alloc MD</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 group-hover:bg-blue-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-gray-900 leading-none" id="cardTotalAllocMd">—</p>
        </div>

        {{-- Total Remain --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-col gap-3 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute top-0 left-0 w-1 h-full bg-orange-400 rounded-l-2xl"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Remaining</span>
                <div class="w-8 h-8 rounded-xl bg-orange-50 flex items-center justify-center text-orange-400 group-hover:bg-orange-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold text-orange-500 leading-none" id="cardTotalRemain">—</p>
        </div>

        {{-- Workload % --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-col gap-3 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute top-0 left-0 w-1 h-full bg-emerald-500 rounded-l-2xl" id="cardWorkloadBar"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Workload</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 group-hover:bg-emerald-100 transition" id="cardWorkloadIcon">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold leading-none" id="cardWorkloadPct">—</p>
        </div>

        {{-- Load Score --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 flex flex-col gap-3 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute top-0 left-0 w-1 h-full bg-violet-500 rounded-l-2xl" id="cardScoreBar"></div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Load Score</span>
                <div class="w-8 h-8 rounded-xl bg-violet-50 flex items-center justify-center text-violet-500 group-hover:bg-violet-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </div>
            <p class="text-3xl font-bold leading-none" id="cardLoadScore">—</p>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="taskTable">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:135px">Ticket No.</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:155px">Customer</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:120px">
                            <div class="relative inline-block" id="statusFilterWrap">
                                <button type="button" onclick="toggleStatusFilter()"
                                    class="flex items-center gap-1 text-xs font-semibold text-gray-400 uppercase tracking-wider hover:text-gray-600 transition select-none">
                                    <span>Status</span>
                                    <span id="statusFilterDot" class="hidden w-1.5 h-1.5 rounded-full bg-indigo-500 shrink-0"></span>
                                    <svg id="statusFilterArrow" class="w-3 h-3 text-gray-400 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <input type="hidden" id="filterStatus" value="">
                                <div id="statusFilterPanel"
                                    class="hidden fixed z-[9999] bg-white border border-gray-100 rounded-2xl shadow-xl py-2 min-w-[160px]">
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-gray-50 text-gray-500 font-medium rounded-lg mx-1 transition" data-value="" onclick="setStatusFilter('')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300 shrink-0"></span> All Status
                                    </div>
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-yellow-50 text-yellow-700 font-medium rounded-lg mx-1 transition" data-value="in_progress" onclick="setStatusFilter('in_progress')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-yellow-400 shrink-0"></span> In Progress
                                    </div>
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-orange-50 text-orange-700 font-medium rounded-lg mx-1 transition" data-value="hold" onclick="setStatusFilter('hold')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-orange-400 shrink-0"></span> Hold
                                    </div>
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-purple-50 text-purple-700 font-medium rounded-lg mx-1 transition" data-value="reply" onclick="setStatusFilter('reply')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-purple-400 shrink-0"></span> Reply
                                    </div>
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-teal-50 text-teal-700 font-medium rounded-lg mx-1 transition" data-value="wait_to_close" onclick="setStatusFilter('wait_to_close')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-teal-400 shrink-0"></span> Wait Close
                                    </div>
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-green-50 text-green-700 font-medium rounded-lg mx-1 transition" data-value="closed" onclick="setStatusFilter('closed')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 shrink-0"></span> Closed
                                    </div>
                                    <div class="status-opt flex items-center gap-2 px-4 py-2 text-xs cursor-pointer hover:bg-red-50 text-red-700 font-medium rounded-lg mx-1 transition" data-value="cancel" onclick="setStatusFilter('cancel')">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 shrink-0"></span> Cancelled
                                    </div>
                                </div>
                            </div>
                        </th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:95px">Priority</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider cursor-pointer select-none hover:text-gray-600 transition" style="min-width:120px" onclick="toggleSort('module')" id="th-module">
                            <span class="flex items-center gap-1">Module <span id="sort-icon-module" class="text-gray-300 text-xs">⇅</span></span>
                        </th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:95px">Man Days</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:95px">End Date</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wider" style="min-width:210px">Progress</th>
                    </tr>
                </thead>
                <tbody id="taskBody">
                    <tr>
                        <td colspan="8" class="text-center py-16 text-gray-400">
                            <div class="flex flex-col items-center gap-3">
                                <svg class="animate-spin w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                <span class="text-sm text-gray-400">Loading tasks…</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Ticket Progress Modal --}}
<div id="cpModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="closeCpModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6 border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-gray-900">Update Progress</h3>
                <p class="text-sm text-gray-500 mt-0.5 truncate max-w-xs font-medium" id="cpModalSubject">—</p>
            </div>
            <button onclick="closeCpModal()" class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-500 transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <input type="hidden" id="cpModalTicketId">

        <div class="mb-5">
            <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-2">Progress</label>
            <div class="flex items-center gap-3">
                <input type="range" id="cpModalSlider" min="0" max="100" step="5" value="0"
                    oninput="const v=Math.min(100,Math.max(0,+this.value));this.value=v;document.getElementById('cpModalValue').value=v"
                    class="flex-1 accent-indigo-500 h-2 rounded-full cursor-pointer">
                <div class="relative">
                    <input type="number" id="cpModalValue" min="0" max="100" value="0"
                        oninput="const v=Math.min(100,Math.max(0,+this.value||0));document.getElementById('cpModalSlider').value=v"
                        class="w-18 text-sm font-bold text-indigo-600 border border-indigo-200 rounded-xl px-2 py-1.5 pr-5 text-right focus:ring-2 focus:ring-indigo-400 focus:outline-none">
                    <span class="absolute right-2 inset-y-0 flex items-center text-xs font-semibold text-indigo-400 pointer-events-none">%</span>
                </div>
            </div>
        </div>

        <div class="mb-5">
            <label class="text-xs font-semibold text-gray-400 uppercase tracking-wider block mb-2">Notes</label>
            <textarea id="cpModalNote" rows="3" placeholder="What's the latest update?"
                class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 focus:ring-2 focus:ring-indigo-400 focus:outline-none resize-none text-gray-700 placeholder-gray-400 hover:border-gray-300 transition"></textarea>
        </div>

        <div class="flex gap-2.5">
            <button onclick="submitCpModal()"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold py-2.5 rounded-xl transition shadow-sm shadow-indigo-200">
                Save Changes
            </button>
            <button onclick="closeCpModal()"
                class="px-4 py-2.5 text-sm text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition font-medium">
                Cancel
            </button>
        </div>
    </div>
</div>

<script>
    let allTasks = [];
    let myModules = '-';
    let myEmpId = null;
    let sortState = {
        col: null,
        dir: 'asc'
    };

    function computeSummary(tasks) {
        let totalAllocMd = 0,
            totalRemain = 0;
        tasks.forEach(t => {
            const me = (t.consultant_details ?? []).find(d => d.employee_id == myEmpId);
            if (me) {
                totalAllocMd += parseFloat(me.effective_md) || 0;
                totalRemain += parseFloat(me.remain_md) || 0;
            }
        });
        const ticketCount = tasks.length;
        const workloadPct = totalAllocMd > 0 ? Math.round(totalRemain / totalAllocMd * 1000) / 10 : 0;
        const loadScore = Math.round(totalRemain * (1 + 0.1 * ticketCount) * 100) / 100;
        return {
            ticket_count: ticketCount,
            total_alloc_md: Math.round(totalAllocMd * 100) / 100,
            total_remain: Math.round(totalRemain * 100) / 100,
            workload_pct: workloadPct,
            load_score: loadScore
        };
    }

    const STATUS_BADGE = {
        open: {
            text: 'Open',
            cls: 'bg-blue-100 text-blue-700'
        },
        inprocess: {
            text: 'In Progress',
            cls: 'bg-yellow-100 text-yellow-700'
        },
        hold: {
            text: 'Hold',
            cls: 'bg-orange-100 text-orange-700'
        },
        reply: {
            text: 'Reply',
            cls: 'bg-purple-100 text-purple-700'
        },
        wait_to_close: {
            text: 'Wait Close',
            cls: 'bg-teal-100 text-teal-700'
        },
        closed: {
            text: 'Closed',
            cls: 'bg-green-100 text-green-700'
        },
        cancel: {
            text: 'Cancelled',
            cls: 'bg-red-100 text-red-700'
        },
    };

    const PRIORITY_CLS = {
        Low: {
            cls: 'bg-gray-50 text-gray-500 ring-1 ring-gray-200',
            dot: 'bg-gray-400'
        },
        Medium: {
            cls: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200',
            dot: 'bg-blue-400'
        },
        High: {
            cls: 'bg-red-50 text-red-700 ring-1 ring-red-200',
            dot: 'bg-red-400'
        },
        'Very High': {
            cls: 'bg-purple-50 text-purple-700 ring-1 ring-purple-200',
            dot: 'bg-purple-400'
        },
    };

    function progressBarColor(pct) {
        if (pct >= 75) return 'bg-emerald-500';
        if (pct >= 40) return 'bg-yellow-400';
        return 'bg-red-400';
    }

    // ── Load ───────────────────────────────────────────────────────────
    async function loadTasks() {
        const month = document.getElementById('filterMonth').value;
        const year = document.getElementById('filterYear').value;

        document.getElementById('taskBody').innerHTML = `
        <tr><td colspan="8" class="text-center py-16 text-gray-400">
            <div class="flex flex-col items-center gap-3">
                <svg class="animate-spin w-6 h-6 text-indigo-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <span class="text-sm text-gray-400">Loading tasks…</span>
            </div>
        </td></tr>`;

        try {
            const res = await fetch(`/api/task?month=${month}&year=${year}`);
            const text = await res.text();
            let json;
            try {
                json = JSON.parse(text);
            } catch {
                console.error('Non-JSON:', text.substring(0, 500));
                document.getElementById('taskBody').innerHTML =
                    `<tr><td colspan="9" class="text-center py-10 text-red-500 text-sm">Server error. Check console.</td></tr>`;
                return;
            }
            if (!json.success) {
                document.getElementById('taskBody').innerHTML =
                    `<tr><td colspan="9" class="text-center py-10 text-red-500 text-sm">${json.message}</td></tr>`;
                return;
            }
            myEmpId = json.emp_id ?? null;
            allTasks = (json.data ?? []).filter(t => t.status !== 'open');
            myModules = json.my_modules ?? '-';
            updateCards(computeSummary(allTasks));
            filterTable();
        } catch (e) {
            document.getElementById('taskBody').innerHTML =
                `<tr><td colspan="9" class="text-center py-10 text-red-500 text-sm">Failed: ${e.message}</td></tr>`;
        }
    }

    function filterTable() {
        const q = document.getElementById('searchTask').value.toLowerCase();
        const statusFilter = document.getElementById('filterStatus')?.value || '';
        let filtered = [...allTasks];
        if (q) {
            filtered = filtered.filter(t =>
                (t.ticket_number ?? '').toLowerCase().includes(q) ||
                (t.subject ?? '').toLowerCase().includes(q) ||
                (t.customer_name ?? '').toLowerCase().includes(q) ||
                (t.module ?? '').toLowerCase().includes(q));
        }
        if (statusFilter) {
            filtered = filtered.filter(t => t.status === statusFilter);
        }
        if (sortState.col) filtered = applySortTo(filtered);
        renderTable(filtered);
    }

    function toggleSort(col) {
        if (sortState.col === col) {
            sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            sortState.col = col;
            sortState.dir = 'asc';
        }
        document.getElementById('sort-icon-module').textContent =
            sortState.col === 'module' ? (sortState.dir === 'asc' ? '↑' : '↓') : '⇅';
        filterTable();
    }

    function applySortTo(arr) {
        return [...arr].sort((a, b) => {
            const va = (a.module ?? '').toLowerCase();
            const vb = (b.module ?? '').toLowerCase();
            if (va < vb) return sortState.dir === 'asc' ? -1 : 1;
            if (va > vb) return sortState.dir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    function updateCards(summary) {
        const wPct = parseFloat(summary.workload_pct) || 0;
        const score = parseFloat(summary.load_score) || 0;

        document.getElementById('cardTicketCount').textContent = summary.ticket_count ?? 0;
        document.getElementById('cardTotalAllocMd').textContent = (summary.total_alloc_md ?? 0) + ' md';
        document.getElementById('cardTotalRemain').textContent = (summary.total_remain ?? 0) + ' d';

        const wPctColor = wPct >= 70 ? 'text-red-600' : wPct >= 40 ? 'text-yellow-600' : 'text-emerald-600';
        const wBarColor = wPct >= 70 ? 'bg-red-500' : wPct >= 40 ? 'bg-yellow-400' : 'bg-emerald-500';
        const wEl = document.getElementById('cardWorkloadPct');
        wEl.textContent = wPct + '%';
        wEl.className = `text-3xl font-bold leading-none ${wPctColor}`;
        document.getElementById('cardWorkloadBar').className = `absolute top-0 left-0 w-1 h-full rounded-l-2xl ${wBarColor}`;

        const sColor = score >= 10 ? 'text-red-600' : score >= 5 ? 'text-yellow-600' : 'text-emerald-600';
        const sEl = document.getElementById('cardLoadScore');
        sEl.textContent = score;
        sEl.className = `text-3xl font-bold leading-none ${sColor}`;

        document.getElementById('summaryText').textContent = `${summary.ticket_count ?? 0} active ticket(s) as Lead`;
    }

    // ── Render ─────────────────────────────────────────────────────────
    function renderTable(tasks) {
        if (!tasks.length) {
            document.getElementById('taskBody').innerHTML =
                `<tr><td colspan="8" class="text-center py-12 text-gray-400">
                <i class="fas fa-check-circle text-3xl mb-3 block text-gray-300"></i>
                No active tickets assigned as Lead
            </td></tr>`;
            return;
        }
        document.getElementById('taskBody').innerHTML = tasks.map(t => taskRows(t)).join('');
    }

    function taskRows(t) {
        const pct = parseFloat(t.progress_percentage) || 0;
        const st = STATUS_BADGE[t.status] ?? {
            text: t.status,
            dot: 'bg-gray-400',
            cls: 'bg-gray-50 text-gray-500 ring-1 ring-gray-200'
        };
        const prInfo = PRIORITY_CLS[t.ticket_priority] ?? {
            cls: 'bg-gray-50 text-gray-500 ring-1 ring-gray-200',
            dot: 'bg-gray-400'
        };
        const endDate = t.end_date ? new Date(t.end_date).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        }) : '—';

        const isOverdue = t.end_date && new Date(t.end_date) < new Date() && t.status !== 'closed' && t.status !== 'cancel';

        const details = t.consultant_details ?? [];
        const myDetail = details.find(d => d.employee_id == myEmpId);
        const myMd = myDetail ? Math.round((parseFloat(myDetail.mandays) + parseFloat(myDetail.approved_additional || 0)) * 100) / 100 : null;
        const displayMd = myMd || (t.man_days ? parseFloat(t.man_days) : null);
        const hasDetails = details.length > 0;

        const expandBtn = hasDetails ?
            `<button onclick="event.stopPropagation(); toggleDetail(${t.ticket_id})"
                class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold border border-indigo-200 text-indigo-500 px-2 py-0.5 rounded-lg hover:bg-indigo-50 transition"
                title="Consultants">
                <span id="det-icon-${t.ticket_id}" class="text-[10px]">▶</span>${details.length}
           </button>` :
            '';

        const detailSubTable = hasDetails ? `
    <tr id="det-${t.ticket_id}" class="hidden bg-indigo-50/30">
        <td colspan="8" class="px-0 py-0">
            <div class="ml-10 mr-5 my-3 rounded-xl border border-indigo-100 overflow-hidden shadow-sm">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-indigo-50 text-indigo-600 font-semibold uppercase tracking-wider">
                            <th class="px-4 py-2 text-left">Consultant</th>
                            <th class="px-4 py-2 text-left">Module</th>
                            <th class="px-4 py-2 text-right">Alloc MD</th>
                            <th class="px-4 py-2 text-right">Add. MD</th>
                            <th class="px-4 py-2 text-right">Remain</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${details.map(d => `
                        <tr class="border-t border-indigo-100 hover:bg-indigo-50/50 transition">
                            <td class="px-4 py-2">
                                <p class="font-semibold text-gray-800">${d.emp_name}</p>
                                <p class="text-gray-400 font-mono text-[11px]">${d.eci}</p>
                            </td>
                            <td class="px-4 py-2 text-gray-500">${d.module}</td>
                            <td class="px-4 py-2 text-right font-semibold text-gray-700">${d.mandays} md</td>
                            <td class="px-4 py-2 text-right font-semibold ${d.approved_additional > 0 ? 'text-indigo-600' : 'text-gray-300'}">${d.approved_additional > 0 ? d.approved_additional + ' md' : '—'}</td>
                            <td class="px-4 py-2 text-right">
                                ${d.remain_md !== null && d.remain_md !== undefined
                                    ? `<span class="font-bold ${d.remain_md > 0 ? 'text-orange-600' : 'text-emerald-600'}">${d.remain_md} d</span>`
                                    : '<span class="text-gray-300">—</span>'}
                            </td>
                        </tr>`).join('')}
                        <tr class="border-t-2 border-indigo-200 bg-indigo-50/80 font-bold">
                            <td class="px-4 py-2 text-indigo-700">Total · ${details.length} consultant${details.length > 1 ? 's' : ''}</td>
                            <td class="px-4 py-2"></td>
                            <td class="px-4 py-2 text-right text-gray-700">${details.reduce((s,d)=>s+(parseFloat(d.mandays)||0),0).toFixed(2)} md</td>
                            <td class="px-4 py-2 text-right text-indigo-600">${details.reduce((s,d)=>s+(parseFloat(d.approved_additional)||0),0).toFixed(2)} md</td>
                            <td class="px-4 py-2 text-right text-orange-600">${details.reduce((s,d)=>s+(parseFloat(d.remain_md)||0),0).toFixed(2)} d</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>` : '';

        const moduleHtml = myModules && myModules !== '-' ?
            myModules.split(', ').map(m => `<span class="inline-flex items-center px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100 text-xs font-medium mr-1 mb-0.5">${m}</span>`).join('') :
            '<span class="text-gray-300 text-xs">—</span>';

        const mainRow = `
    <tr class="border-b border-gray-50 hover:bg-gray-50/60 cursor-pointer group transition-colors"
        onclick="window.location='/ticket/${t.ticket_id}'">
        <td class="px-5 py-3.5">
            <span class="font-mono text-xs font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-lg group-hover:bg-indigo-50 group-hover:text-indigo-700 transition">${t.ticket_number ?? '—'}</span>
        </td>
        <td class="px-5 py-3.5 text-sm font-medium text-gray-700 max-w-[180px] truncate">${t.customer_name ?? '—'}</td>
        <td class="px-5 py-3.5">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${st.cls}">
                <span class="w-1.5 h-1.5 rounded-full ${st.dot} shrink-0"></span>${st.text}
            </span>
        </td>
        <td class="px-5 py-3.5">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${prInfo.cls}">
                <span class="w-1.5 h-1.5 rounded-full ${prInfo.dot} shrink-0"></span>${t.ticket_priority ?? '—'}
            </span>
        </td>
        <td class="px-4 py-3">${moduleHtml}</td>
        <td class="px-4 py-3 text-right font-semibold text-gray-700">${displayMd ? displayMd + ' md' : '<span class="text-gray-400 italic font-normal">Belum ditentukan</span>'}</td>
        <td class="px-4 py-3 text-right text-xs text-gray-500">${endDate}</td>
        <td class="px-4 py-3" onclick="event.stopPropagation()">
            <div class="flex items-center gap-2">
                <div class="bg-gray-100 rounded-full h-2" style="width:110px">
                    <div class="${progressBarColor(pct)} h-2 rounded-full transition-all" style="width:${pct}%"></div>
                </div>
                <span class="text-xs font-bold text-gray-600 w-8 text-right shrink-0">${pct}%</span>
                <button onclick="event.stopPropagation(); openCpModal(${t.ticket_id}, '${(t.ticket_number??'').replace(/'/g,"\\'")}', ${pct}, '${(t.progress_note??'').replace(/'/g,"\\'")}')"
                    class="shrink-0 inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition">
                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                    Edit
                </button>
                ${expandBtn}
            </div>
        </td>
    </tr>`;

        return mainRow + detailSubTable;
    }

    function toggleDetail(ticketId) {
        const row = document.getElementById(`det-${ticketId}`);
        const icon = document.getElementById(`det-icon-${ticketId}`);
        if (!row) return;
        const hidden = row.classList.contains('hidden');
        row.classList.toggle('hidden', !hidden);
        if (icon) icon.textContent = hidden ? '▼' : '▶';
    }

    // ── Ticket Progress Modal ──────────────────────────────────────────
    function openCpModal(ticketId, ticketNumber, pct, note) {
        document.getElementById('cpModalTicketId').value = ticketId;
        document.getElementById('cpModalSubject').textContent = ticketNumber;
        document.getElementById('cpModalSlider').value = pct;
        document.getElementById('cpModalValue').value = pct;
        document.getElementById('cpModalNote').value = note ?? '';
        document.getElementById('cpModal').classList.remove('hidden');
        document.getElementById('cpModal').classList.add('flex');
    }

    function closeCpModal() {
        document.getElementById('cpModal').classList.add('hidden');
        document.getElementById('cpModal').classList.remove('flex');
    }

    async function submitCpModal() {
        const ticketId = document.getElementById('cpModalTicketId').value;
        const pct = document.getElementById('cpModalValue').value;
        const note = document.getElementById('cpModalNote').value;
        try {
            const res = await fetch(`/api/consultant-workload/tickets/${ticketId}/progress`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    progress_percentage: pct,
                    progress_note: note
                }),
            });
            const json = await res.json();
            if (json.success) {
                closeCpModal();
                loadTasks();
            } else alert('Failed: ' + (json.message ?? 'Error'));
        } catch (e) {
            alert('Error: ' + e.message);
        }
    }

    // ── Status header filter ───────────────────────────────────────────
    function toggleStatusFilter() {
        const panel = document.getElementById('statusFilterPanel');
        const arrow = document.getElementById('statusFilterArrow');
        const btn = document.querySelector('#statusFilterWrap button');
        const open = !panel.classList.contains('hidden');
        if (!open) {
            const r = btn.getBoundingClientRect();
            panel.style.top = `${r.bottom + 6}px`;
            panel.style.left = `${r.left}px`;
        }
        panel.classList.toggle('hidden', open);
        arrow.style.transform = open ? '' : 'rotate(180deg)';
    }

    function setStatusFilter(value) {
        document.getElementById('filterStatus').value = value;
        document.getElementById('statusFilterPanel').classList.add('hidden');
        document.getElementById('statusFilterArrow').style.transform = '';
        document.getElementById('statusFilterDot').classList.toggle('hidden', !value);
        document.querySelectorAll('.status-opt').forEach(el => {
            el.classList.toggle('bg-gray-100', el.dataset.value === value);
            el.classList.toggle('font-bold', el.dataset.value === value);
        });
        filterTable();
    }

    document.addEventListener('click', function(e) {
        if (!document.getElementById('statusFilterWrap')?.contains(e.target)) {
            document.getElementById('statusFilterPanel')?.classList.add('hidden');
            if (document.getElementById('statusFilterArrow'))
                document.getElementById('statusFilterArrow').style.transform = '';
        }
    });

    document.addEventListener('DOMContentLoaded', loadTasks);
</script>
@endsection