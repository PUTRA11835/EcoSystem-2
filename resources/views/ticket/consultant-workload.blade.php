@extends('dashboard')
@section('title', 'Consultant Workload')
@section('page-title', 'Consultant Workload')
@section('page-subtitle', 'Monitor workload and ticket progress for each consultant')

@section('content')
<div class="flex flex-col gap-4">

    {{-- Filter Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2 flex-wrap">
            <select id="filterMonth" onchange="loadWorkload()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-red-500 focus:border-transparent">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                    </option>
                @endfor
            </select>
            <select id="filterYear" onchange="loadWorkload()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-red-500 focus:border-transparent">
                @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <input id="searchConsultant" type="text" placeholder="Search name / ECI / module..."
                oninput="filterTable()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-red-500 focus:border-transparent w-52">
        </div>
        <div class="flex items-center gap-3 text-sm text-gray-500">
            <span id="summaryText">—</span>
            <span class="text-gray-300">|</span>
            <button onclick="expandAll()" class="text-xs text-blue-600 hover:underline">Expand All</button>
            <button onclick="collapseAll()" class="text-xs text-blue-600 hover:underline">Collapse All</button>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-4 text-xs text-gray-500 bg-gray-50 rounded-lg px-4 py-2 border border-gray-100">
        <span class="font-semibold text-gray-600">Legend:</span>
        <span><span class="inline-block w-3 h-3 rounded-full bg-red-500 mr-1"></span>Busy &gt;70%</span>
        <span><span class="inline-block w-3 h-3 rounded-full bg-yellow-400 mr-1"></span>Moderate 40–70%</span>
        <span><span class="inline-block w-3 h-3 rounded-full bg-green-500 mr-1"></span>Light &lt;40%</span>
        <span class="text-gray-300">|</span>
        <span><b>Workload</b> = Σ(mandays × (1 − progress%)) / Σ(mandays) × 100 &nbsp;·&nbsp; 0% = done, 100% = not started</span>
        <span class="text-gray-300">|</span>
        <span><b>Load Score</b> = Remain × (1 + 0.1 × n) &nbsp;·&nbsp; merah ≥10, kuning ≥5, hijau &lt;5</span>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="workloadTable">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-left">Consultant</th>
                        <th class="px-4 py-3 text-left">Module</th>
                        <th class="px-4 py-3 text-center cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('ticket_count')" title="Sort">
                            <div class="flex items-center justify-center gap-1">
                                Tickets <span id="sort-icon-ticket_count" class="text-gray-300">⇅</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer select-none hover:bg-gray-100 transition"
                            onclick="sortBy('total_days')" title="Sort">
                            <div class="flex items-center justify-end gap-1">
                                Alloc MD <span id="sort-icon-total_days" class="text-gray-300">⇅</span>
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

{{-- Progress Modal --}}
<div id="progressModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-1">Update Ticket Progress</h3>
        <p class="text-sm text-gray-500 mb-4 truncate" id="progressModalSubject">—</p>
        <input type="hidden" id="progressTicketId">
        <div class="mb-4">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Progress (%)</label>
            <div class="flex items-center gap-3 mt-2">
                <input type="range" id="progressSlider" min="0" max="100" step="5" value="0"
                    oninput="document.getElementById('progressValue').textContent = this.value + '%'"
                    class="flex-1 accent-red-600">
                <span id="progressValue" class="text-sm font-bold text-red-600 w-10 text-right">0%</span>
            </div>
        </div>
        <div class="mb-5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notes</label>
            <textarea id="progressNote" rows="2" placeholder="Latest update..."
                class="mt-2 w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-red-500 resize-none"></textarea>
        </div>
        <div class="flex gap-2">
            <button onclick="submitProgress()"
                class="flex-1 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold py-2.5 rounded-lg transition">Save</button>
            <button onclick="closeProgressModal()"
                class="px-4 py-2.5 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">Cancel</button>
        </div>
    </div>
</div>

{{-- Consultant Progress Modal --}}
<div id="consultantProgressModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-0.5">Update Consultant Progress</h3>
        <p class="text-xs text-indigo-600 font-semibold mb-0.5" id="cpEmpName">—</p>
        <p class="text-sm text-gray-500 mb-4 truncate" id="cpSubject">—</p>
        <input type="hidden" id="cpDetailId">
        <input type="hidden" id="cpTicketId">
        <div class="mb-4">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Progress (%)</label>
            <div class="flex items-center gap-3 mt-2">
                <input type="range" id="cpSlider" min="0" max="100" step="5" value="0"
                    oninput="document.getElementById('cpValue').textContent = this.value + '%'"
                    class="flex-1 accent-indigo-600">
                <span id="cpValue" class="text-sm font-bold text-indigo-600 w-10 text-right">0%</span>
            </div>
        </div>
        <div class="mb-5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notes</label>
            <textarea id="cpNote" rows="2" placeholder="Latest update..."
                class="mt-2 w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
        </div>
        <div class="flex gap-2">
            <button onclick="submitConsultantProgress()"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-lg transition">Save</button>
            <button onclick="closeConsultantProgressModal()"
                class="px-4 py-2.5 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">Cancel</button>
        </div>
    </div>
</div>

<script>
let allConsultants = [];
let currentSort   = { key: 'workload_pct', dir: 'desc' };

const STATUS_BADGE = {
    open:          { text: 'Open',        cls: 'bg-blue-100 text-blue-700' },
    in_progress:   { text: 'In Progress', cls: 'bg-yellow-100 text-yellow-700' },
    hold:          { text: 'Hold',        cls: 'bg-orange-100 text-orange-700' },
    reply:         { text: 'Reply',       cls: 'bg-purple-100 text-purple-700' },
    wait_to_close: { text: 'Wait Close',  cls: 'bg-teal-100 text-teal-700' },
};

const PRIORITY_CLS = {
    Low:    'bg-gray-100 text-gray-600',
    Medium: 'bg-blue-100 text-blue-700',
    High:   'bg-red-100 text-red-700',
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
    const year  = document.getElementById('filterYear').value;

    document.getElementById('workloadBody').innerHTML = `
        <tr><td colspan="8" class="text-center py-12 text-gray-400">
            <svg class="animate-spin w-5 h-5 mx-auto mb-2 text-red-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>Loading data...
        </td></tr>`;

    try {
        const res  = await fetch(`/api/consultant-workload?month=${month}&year=${year}`);
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); }
        catch {
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
        updateSortIcons();
        renderTable(applySortTo(allConsultants));
        updateSummary(allConsultants);
    } catch (e) {
        document.getElementById('workloadBody').innerHTML =
            `<tr><td colspan="8" class="text-center py-8 text-red-500 text-sm">Failed: ${e.message}</td></tr>`;
    }
}

function filterTable() {
    const q = document.getElementById('searchConsultant').value.toLowerCase();
    const filtered = q
        ? allConsultants.filter(c =>
            c.name.toLowerCase().includes(q) ||
            (c.modules ?? '').toLowerCase().includes(q) ||
            c.eci.toLowerCase().includes(q))
        : allConsultants;
    renderTable(applySortTo(filtered));
}

// ── Sort ───────────────────────────────────────────────────────────
const SORTABLE_KEYS = ['ticket_count', 'total_days', 'workload_days', 'workload_pct', 'load_score'];

function sortBy(key) {
    if (currentSort.key === key) {
        currentSort.dir = currentSort.dir === 'asc' ? 'desc' : 'asc';
    } else {
        currentSort = { key, dir: 'asc' };
    }
    updateSortIcons();
    filterTable();
}

function applySortTo(list) {
    const { key, dir } = currentSort;
    return [...list].sort((a, b) => {
        const va = parseFloat(a[key]) || 0;
        const vb = parseFloat(b[key]) || 0;
        return dir === 'desc' ? vb - va : va - vb;
    });
}

function updateSortIcons() {
    SORTABLE_KEYS.forEach(k => {
        const el = document.getElementById(`sort-icon-${k}`);
        if (!el) return;
        if (k === currentSort.key) {
            el.textContent = currentSort.dir === 'asc' ? '↓' : '↑';
            el.className   = 'text-red-500 font-bold';
        } else {
            el.textContent = '⇅';
            el.className   = 'text-gray-300';
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
    const w  = Math.min(pct, 100);
    const cl = colorFn(pct);
    return `
        <div class="flex items-center gap-2">
            <div class="bg-gray-100 rounded-full h-2" style="width:${width}px">
                <div class="${cl} h-2 rounded-full transition-all" style="width:${w}%"></div>
            </div>
            <span class="text-xs font-bold ${workloadTextColor(pct)} w-9 text-right shrink-0">${pct}%</span>
        </div>`;
}

function consultantRows(c) {
    const wPct      = parseFloat(c.workload_pct) || 0;
    const wDays     = parseFloat(c.workload_days) || 0;
    const loadScore = parseFloat(c.load_score) || 0;

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
                ${c.ticket_count > 0 ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400'}">
                ${c.ticket_count}
            </span>
        </td>
        <td class="px-4 py-3 text-right font-semibold text-gray-800 tabular-nums">${c.total_days} md</td>
        <td class="px-4 py-3 text-right font-semibold text-orange-600 tabular-nums">${c.workload_days} d</td>
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
                <div class="text-xs text-gray-400">${wDays} d × (1 + 0.1×${c.ticket_count})</div>
            </div>
        </td>
    </tr>`;

    if (c.tickets.length === 0) {
        html += `
    <tr id="tickets-${c.employee_id}" class="hidden bg-slate-50/60 border-b border-gray-100">
        <td colspan="8" class="pl-12 pr-4 py-2.5 text-xs text-gray-400 italic">
            No active tickets this month
        </td>
    </tr>`;
    } else {
        html += `
    <tr id="tickets-${c.employee_id}" class="hidden">
        <td colspan="8" class="p-0 border-b border-gray-200">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 text-xs font-semibold text-gray-500 uppercase tracking-wide border-y border-slate-200">
                        <th class="pl-12 pr-3 py-2 text-left w-8">#</th>
                        <th class="px-3 py-2 text-left w-36">Ticket No.</th>
                        <th class="px-3 py-2 text-left w-20">Role</th>
                        <th class="px-3 py-2 text-left w-28">Status</th>
                        <th class="px-3 py-2 text-left w-20">Priority</th>
                        <th class="px-3 py-2 text-right w-24">Alloc MD</th>
                        <th class="px-3 py-2 text-right w-24">Add. MD</th>
                        <th class="px-3 py-2 text-right w-36">Remain</th>
                        <th class="px-3 py-2 text-left w-48">Progress</th>
                        <th class="px-3 py-2 text-left w-32">Notes</th>
                        <th class="px-3 py-2 text-center w-20">Action</th>
                    </tr>
                </thead>
                <tbody>
                    ${c.tickets.map((t, idx) => ticketRow(t, idx + 1, c.employee_id, c.modules)).join('')}
                    <tr class="bg-slate-50 border-t border-slate-200">
                        <td colspan="6" class="pl-12 pr-3 py-2 text-xs text-right text-gray-500 font-semibold">Total</td>
                        <td class="px-3 py-2 text-right text-xs font-bold text-gray-700">
                            ${c.tickets.reduce((s, t) => {
                                const my = (t.consultant_details ?? []).find(d => d.employee_id == c.employee_id);
                                return s + (my ? parseFloat(my.mandays) || 0 : 0);
                            }, 0).toFixed(1)} md
                        </td>
                        <td class="px-3 py-2 text-right text-xs font-bold text-orange-600">
                            ${c.workload_days} d
                        </td>
                        <td class="px-3 py-2 text-xs font-semibold text-orange-600" colspan="3">
                            ${wDays} d remain across ${c.tickets.length} ticket(s)
                        </td>
                    </tr>
                </tbody>
            </table>
        </td>
    </tr>`;
    }

    return html;
}

function ticketRow(t, num, empId, consultantModules) {
    const st    = STATUS_BADGE[t.status] ?? { text: t.status, cls: 'bg-gray-100 text-gray-600' };
    const prCls = PRIORITY_CLS[t.ticket_priority] ?? 'bg-gray-100 text-gray-600';
    const isPic = t.role_in_ticket === 'pic';
    const roleCls   = isPic ? 'bg-amber-100 text-amber-700 border border-amber-200'
                             : 'bg-sky-100 text-sky-700 border border-sky-200';
    const roleLabel = isPic ? 'PIC' : 'Member';

    // Find this consultant's own entry in the mandays detail
    const myDetail = (t.consultant_details ?? []).find(d => d.employee_id == empId);
    const myPct    = myDetail ? parseFloat(myDetail.progress_percentage) || 0 : null;
    const myRemain = myDetail ? parseFloat(myDetail.remain_md) : null;
    const myNote   = myDetail ? (myDetail.progress_note || '—') : '—';
    const myMd     = myDetail ? parseFloat(myDetail.mandays) : null;
    const myDetailId = myDetail ? myDetail.detail_id : null;
    const updAt    = (myDetail && myDetail.progress_updated_at)
        ? new Date(myDetail.progress_updated_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
        : '—';

    const progressCell = myDetail ? `
        <div class="flex items-center gap-2">
            <div class="bg-gray-200 rounded-full h-2" style="width:90px">
                <div class="${progressBarColor(myPct)} h-2 rounded-full" style="width:${myPct}%"></div>
            </div>
            <span class="text-xs font-bold ${myPct>=75?'text-green-700':myPct>=40?'text-yellow-600':'text-red-600'}">${myPct}%</span>
        </div>
        <div class="text-xs text-gray-400 mt-0.5">Updated: ${updAt}</div>`
        : `<span class="text-xs text-gray-300">No mandays data</span>`;

    const remainCell = myRemain !== null
        ? `<span class="font-semibold ${myRemain > 0 ? 'text-orange-600' : 'text-green-600'}">${myRemain} d</span>
           <span class="ml-1 text-xs font-bold bg-orange-100 text-orange-700 rounded px-1 py-0.5">↑${Math.ceil(myRemain)} d</span>`
        : `<span class="text-gray-300">—</span>`;

    const actionCell = myDetail
        ? `<button onclick="event.stopPropagation(); openConsultantProgressModal(${myDetailId}, '${(myDetail.emp_name??'').replace(/'/g,"\\'")}', ${t.ticket_id}, '${(t.subject??'').replace(/'/g,"\\'")}', ${myPct}, '${myNote.replace(/'/g,"\\'")}')"
               class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold border border-indigo-200 px-2 py-0.5 rounded hover:bg-indigo-50 transition whitespace-nowrap">
               Edit
           </button>`
        : `<span class="text-gray-300 text-xs">—</span>`;

    return `
    <tr class="border-t border-slate-100 hover:bg-blue-50/30">
        <td class="pl-12 pr-3 py-2.5 text-xs text-gray-400">${num}</td>
        <td class="px-3 py-2.5 font-mono text-xs text-gray-500 whitespace-nowrap">${t.ticket_number ?? '—'}</td>
        <td class="px-3 py-2.5">
            <span class="px-1.5 py-0.5 rounded text-xs font-semibold ${roleCls}">${roleLabel}</span>
        </td>
        <td class="px-3 py-2.5">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${st.cls}">${st.text}</span>
        </td>
        <td class="px-3 py-2.5">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${prCls}">${t.ticket_priority ?? '—'}</span>
        </td>
        <td class="px-3 py-2.5 text-right text-xs font-semibold text-gray-700">${myMd !== null ? myMd + ' md' : '—'}</td>
        <td class="px-3 py-2.5 text-right text-xs text-gray-500">
            ${myDetail && myDetail.approved_additional > 0
                ? `<span class="text-indigo-600 font-semibold">${myDetail.approved_additional} md</span>`
                : '<span class="text-gray-300">—</span>'}
        </td>
        <td class="px-3 py-2.5 text-right">${remainCell}</td>
        <td class="px-3 py-2.5">${progressCell}</td>
        <td class="px-3 py-2.5 text-xs text-gray-500 max-w-xs"><span class="line-clamp-2">${myNote}</span></td>
        <td class="px-3 py-2.5 text-center">${actionCell}</td>
    </tr>`;
}

// ── Expand / Collapse ──────────────────────────────────────────────
function toggleTickets(empId) {
    const row     = document.getElementById(`tickets-${empId}`);
    const chevron = document.getElementById(`chevron-${empId}`);
    const hidden  = row.classList.contains('hidden');
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
    const high = data.filter(c => c.workload_pct >= 70).length;
    const mid  = data.filter(c => c.workload_pct >= 40 && c.workload_pct < 70).length;
    const low  = data.filter(c => c.workload_pct < 40).length;
    document.getElementById('summaryText').innerHTML =
        `${data.length} consultant(s) &nbsp;·&nbsp;
         <span class="text-red-600 font-semibold">${high} busy</span> &nbsp;·&nbsp;
         <span class="text-yellow-600 font-semibold">${mid} moderate</span> &nbsp;·&nbsp;
         <span class="text-green-600 font-semibold">${low} light</span>`;
}

// ── Progress Modal ─────────────────────────────────────────────────
function openProgressModal(ticketId, subject, currentPct, currentNote) {
    document.getElementById('progressTicketId').value    = ticketId;
    document.getElementById('progressModalSubject').textContent = subject;
    document.getElementById('progressSlider').value      = currentPct;
    document.getElementById('progressValue').textContent = currentPct + '%';
    document.getElementById('progressNote').value        = currentNote ?? '';
    document.getElementById('progressModal').classList.remove('hidden');
    document.getElementById('progressModal').classList.add('flex');
}

function closeProgressModal() {
    document.getElementById('progressModal').classList.add('hidden');
    document.getElementById('progressModal').classList.remove('flex');
}

async function submitProgress() {
    const ticketId = document.getElementById('progressTicketId').value;
    const pct      = document.getElementById('progressSlider').value;
    const note     = document.getElementById('progressNote').value;
    try {
        const res = await fetch(`/api/consultant-workload/tickets/${ticketId}/progress`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ progress_percentage: pct, progress_note: note }),
        });
        const json = await res.json();
        if (json.success) { closeProgressModal(); loadWorkload(); }
        else alert('Failed: ' + (json.message ?? 'Error'));
    } catch (e) { alert('Error: ' + e.message); }
}

document.getElementById('progressModal').addEventListener('click', function(e) {
    if (e.target === this) closeProgressModal();
});

// ── Per-Consultant Progress Modal ──────────────────────────────────
function openConsultantProgressModal(detailId, empName, ticketId, subject, currentPct, currentNote) {
    document.getElementById('cpDetailId').value        = detailId;
    document.getElementById('cpTicketId').value        = ticketId;
    document.getElementById('cpEmpName').textContent   = empName;
    document.getElementById('cpSubject').textContent   = subject;
    document.getElementById('cpSlider').value          = currentPct;
    document.getElementById('cpValue').textContent     = currentPct + '%';
    document.getElementById('cpNote').value            = currentNote ?? '';
    document.getElementById('consultantProgressModal').classList.remove('hidden');
    document.getElementById('consultantProgressModal').classList.add('flex');
}

function closeConsultantProgressModal() {
    document.getElementById('consultantProgressModal').classList.add('hidden');
    document.getElementById('consultantProgressModal').classList.remove('flex');
}

async function submitConsultantProgress() {
    const detailId = document.getElementById('cpDetailId').value;
    const pct      = document.getElementById('cpSlider').value;
    const note     = document.getElementById('cpNote').value;
    try {
        const res = await fetch(`/api/consultant-workload/consultant-progress/${detailId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ progress_percentage: pct, progress_note: note }),
        });
        const json = await res.json();
        if (json.success) {
            closeConsultantProgressModal();
            loadWorkload();
        } else alert('Failed: ' + (json.message ?? 'Error'));
    } catch (e) { alert('Error: ' + e.message); }
}

document.getElementById('consultantProgressModal').addEventListener('click', function(e) {
    if (e.target === this) closeConsultantProgressModal();
});

document.addEventListener('DOMContentLoaded', loadWorkload);
</script>
@endsection
