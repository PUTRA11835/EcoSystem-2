@extends('dashboard')
@section('title', 'My Tasks')
@section('page-title', 'My Tasks')
@section('page-subtitle', 'Active tickets where I am assigned as PIC')

@section('content')
<div class="flex flex-col gap-4">

    {{-- Filter Bar --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-2 flex-wrap">
            <select id="filterMonth" onchange="loadTasks()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-red-500 focus:border-transparent">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == now()->month ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create()->month($m)->format('F') }}
                    </option>
                @endfor
            </select>
            <select id="filterYear" onchange="loadTasks()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-red-500 focus:border-transparent">
                @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" {{ $y == now()->year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <input id="searchTask" type="text" placeholder="Search ticket, subject, customer..."
                oninput="filterTable()"
                class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white focus:ring-2 focus:ring-red-500 focus:border-transparent w-56">
        </div>
        <div class="flex items-center gap-3 text-sm text-gray-500">
            <span id="summaryText">—</span>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-5 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Active Tickets</p>
            <p class="text-2xl font-bold text-gray-900" id="cardTicketCount">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total Alloc MD</p>
            <p class="text-2xl font-bold text-gray-900" id="cardTotalAllocMd">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total Remain</p>
            <p class="text-2xl font-bold text-orange-600" id="cardTotalRemain">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Workload %</p>
            <p class="text-2xl font-bold" id="cardWorkloadPct">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Load Score</p>
            <p class="text-2xl font-bold" id="cardLoadScore">—</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="taskTable">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left" style="min-width:130px">Ticket No.</th>
                        <th class="px-4 py-3 text-left" style="min-width:150px">Customer</th>
                        <th class="px-4 py-3 text-left" style="min-width:120px">
                            <div class="relative inline-block" id="statusFilterWrap">
                                <button type="button" onclick="toggleStatusFilter()"
                                    class="flex items-center gap-1 text-xs font-semibold text-gray-500 uppercase tracking-wide hover:text-gray-700 transition select-none">
                                    <span>Status</span>
                                    <span id="statusFilterDot" class="hidden w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                                    <svg id="statusFilterArrow" class="w-3 h-3 text-gray-400 transition-transform duration-150" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <input type="hidden" id="filterStatus" value="">
                                <div id="statusFilterPanel"
                                    class="hidden fixed z-9999 bg-white border border-gray-200 rounded-xl shadow-lg py-1 min-w-37.5">
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-gray-50 text-gray-500 font-medium" data-value="" onclick="setStatusFilter('')">All Status</div>
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-yellow-50 text-yellow-700 font-medium" data-value="in_progress" onclick="setStatusFilter('in_progress')">In Progress</div>
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-orange-50 text-orange-700 font-medium" data-value="hold" onclick="setStatusFilter('hold')">Hold</div>
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-purple-50 text-purple-700 font-medium" data-value="reply" onclick="setStatusFilter('reply')">Reply</div>
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-teal-50 text-teal-700 font-medium" data-value="wait_to_close" onclick="setStatusFilter('wait_to_close')">Wait Close</div>
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-green-50 text-green-700 font-medium" data-value="closed" onclick="setStatusFilter('closed')">Closed</div>
                                    <div class="status-opt px-3 py-1.5 text-xs cursor-pointer hover:bg-red-50 text-red-700 font-medium" data-value="cancel" onclick="setStatusFilter('cancel')">Cancelled</div>
                                </div>
                            </div>
                        </th>
                        <th class="px-4 py-3 text-left" style="min-width:90px">Priority</th>
                        <th class="px-4 py-3 text-left cursor-pointer select-none hover:bg-gray-100 transition" style="min-width:120px" onclick="toggleSort('module')" id="th-module">
                            Module <span id="sort-icon-module" class="text-gray-400 text-xs ml-0.5">⇅</span>
                        </th>
                        <th class="px-4 py-3 text-right" style="min-width:90px">Man Days</th>
                        <th class="px-4 py-3 text-right" style="min-width:90px">End Date</th>
                        <th class="px-4 py-3 text-left" style="min-width:200px">Progress</th>
                    </tr>
                </thead>
                <tbody id="taskBody">
                    <tr>
                        <td colspan="9" class="text-center py-12 text-gray-400">
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

{{-- Ticket Progress Modal --}}
<div id="cpModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-1">Update Ticket Progress</h3>
        <p class="text-sm text-gray-500 mb-4 truncate font-semibold" id="cpModalSubject">—</p>
        <input type="hidden" id="cpModalTicketId">
        <div class="mb-4">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Progress (%)</label>
            <div class="flex items-center gap-3 mt-2">
                <input type="range" id="cpModalSlider" min="0" max="100" step="5" value="0"
                    oninput="const v=Math.min(100,Math.max(0,+this.value));this.value=v;document.getElementById('cpModalValue').value=v"
                    class="flex-1 accent-indigo-600">
                <input type="number" id="cpModalValue" min="0" max="100" value="0"
                    oninput="const v=Math.min(100,Math.max(0,+this.value||0));document.getElementById('cpModalSlider').value=v"
                    class="w-16 text-sm font-bold text-indigo-600 border border-indigo-200 rounded-lg px-2 py-1 text-right focus:ring-2 focus:ring-indigo-400 focus:outline-none">
            </div>
        </div>
        <div class="mb-5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notes</label>
            <textarea id="cpModalNote" rows="2" placeholder="Latest update..."
                class="mt-2 w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-indigo-500 resize-none"></textarea>
        </div>
        <div class="flex gap-2">
            <button onclick="submitCpModal()"
                class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold py-2.5 rounded-lg transition">Save</button>
            <button onclick="closeCpModal()"
                class="px-4 py-2.5 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition">Cancel</button>
        </div>
    </div>
</div>

<script>
let allTasks   = [];
let myModules  = '-';
let myEmpId    = null;
let sortState  = { col: null, dir: 'asc' };

function computeSummary(tasks) {
    let totalAllocMd = 0, totalRemain = 0;
    tasks.forEach(t => {
        const me = (t.consultant_details ?? []).find(d => d.employee_id == myEmpId);
        if (me) {
            totalAllocMd += parseFloat(me.effective_md) || 0;
            totalRemain  += parseFloat(me.remain_md) || 0;
        }
    });
    const ticketCount = tasks.length;
    const workloadPct = totalAllocMd > 0 ? Math.round(totalRemain / totalAllocMd * 1000) / 10 : 0;
    const loadScore   = Math.round(totalRemain * (1 + 0.1 * ticketCount) * 100) / 100;
    return { ticket_count: ticketCount, total_alloc_md: Math.round(totalAllocMd * 100) / 100,
             total_remain: Math.round(totalRemain * 100) / 100, workload_pct: workloadPct, load_score: loadScore };
}

const STATUS_BADGE = {
    open:          { text: 'Open',        cls: 'bg-blue-100 text-blue-700' },
    in_progress:   { text: 'In Progress', cls: 'bg-yellow-100 text-yellow-700' },
    hold:          { text: 'Hold',        cls: 'bg-orange-100 text-orange-700' },
    reply:         { text: 'Reply',       cls: 'bg-purple-100 text-purple-700' },
    wait_to_close: { text: 'Wait Close',  cls: 'bg-teal-100 text-teal-700' },
    closed:        { text: 'Closed',      cls: 'bg-green-100 text-green-700' },
    cancel:        { text: 'Cancelled',   cls: 'bg-red-100 text-red-700' },
};

const PRIORITY_CLS = {
    Low:       'bg-gray-100 text-gray-600',
    Medium:    'bg-blue-100 text-blue-700',
    High:      'bg-red-100 text-red-700',
    'Very High':'bg-purple-100 text-purple-700',
};

function progressBarColor(pct) {
    if (pct >= 75) return 'bg-green-500';
    if (pct >= 40) return 'bg-yellow-400';
    return 'bg-red-400';
}

// ── Load ───────────────────────────────────────────────────────────
async function loadTasks() {
    const month = document.getElementById('filterMonth').value;
    const year  = document.getElementById('filterYear').value;

    document.getElementById('taskBody').innerHTML = `
        <tr><td colspan="8" class="text-center py-12 text-gray-400">
            <svg class="animate-spin w-5 h-5 mx-auto mb-2 text-red-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>Loading data...
        </td></tr>`;

    try {
        const res  = await fetch(`/api/task?month=${month}&year=${year}`);
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); }
        catch {
            console.error('Non-JSON:', text.substring(0, 500));
            document.getElementById('taskBody').innerHTML =
                `<tr><td colspan="9" class="text-center py-8 text-red-500 text-sm">Server error. Check console.</td></tr>`;
            return;
        }
        if (!json.success) {
            document.getElementById('taskBody').innerHTML =
                `<tr><td colspan="9" class="text-center py-8 text-red-500 text-sm">${json.message}</td></tr>`;
            return;
        }
        myEmpId   = json.emp_id ?? null;
        allTasks  = (json.data ?? []).filter(t => t.status !== 'open');
        myModules = json.my_modules ?? '-';
        updateCards(computeSummary(allTasks));
        filterTable();
    } catch (e) {
        document.getElementById('taskBody').innerHTML =
            `<tr><td colspan="9" class="text-center py-8 text-red-500 text-sm">Failed: ${e.message}</td></tr>`;
    }
}

function filterTable() {
    const q            = document.getElementById('searchTask').value.toLowerCase();
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
    const wPct  = parseFloat(summary.workload_pct) || 0;
    const score = parseFloat(summary.load_score) || 0;

    document.getElementById('cardTicketCount').textContent   = summary.ticket_count ?? 0;
    document.getElementById('cardTotalAllocMd').textContent  = (summary.total_alloc_md ?? 0) + ' md';
    document.getElementById('cardTotalRemain').textContent   = (summary.total_remain ?? 0) + ' d';

    const wEl = document.getElementById('cardWorkloadPct');
    wEl.textContent = wPct + '%';
    wEl.className   = `text-2xl font-bold ${wPct >= 70 ? 'text-red-600' : wPct >= 40 ? 'text-yellow-600' : 'text-green-600'}`;

    const sEl = document.getElementById('cardLoadScore');
    sEl.textContent = score;
    sEl.className   = `text-2xl font-bold ${score >= 10 ? 'text-red-600' : score >= 5 ? 'text-yellow-600' : 'text-green-600'}`;

    document.getElementById('summaryText').textContent = `${summary.ticket_count ?? 0} active ticket(s) as PIC`;
}

// ── Render ─────────────────────────────────────────────────────────
function renderTable(tasks) {
    if (!tasks.length) {
        document.getElementById('taskBody').innerHTML =
            `<tr><td colspan="8" class="text-center py-12 text-gray-400">
                <i class="fas fa-check-circle text-3xl mb-3 block text-gray-300"></i>
                No active tickets assigned as PIC
            </td></tr>`;
        return;
    }
    document.getElementById('taskBody').innerHTML = tasks.map(t => taskRows(t)).join('');
}

function taskRows(t) {
    const pct     = parseFloat(t.progress_percentage) || 0;
    const st      = STATUS_BADGE[t.status] ?? { text: t.status, cls: 'bg-gray-100 text-gray-600' };
    const prCls   = PRIORITY_CLS[t.ticket_priority] ?? 'bg-gray-100 text-gray-600';
    const endDate = t.end_date ? new Date(t.end_date).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—';

    const details    = t.consultant_details ?? [];
    const hasDetails = details.length > 0;

    const expandBtn = hasDetails
        ? `<button onclick="event.stopPropagation(); toggleDetail(${t.ticket_id})"
                class="ml-1 text-indigo-500 hover:text-indigo-700 text-xs font-semibold border border-indigo-200 px-1.5 py-0.5 rounded hover:bg-indigo-50 transition"
                title="Consultants">
                <span id="det-icon-${t.ticket_id}">▶</span> ${details.length}
           </button>`
        : '';

    const detailSubTable = hasDetails ? `
    <tr id="det-${t.ticket_id}" class="hidden bg-indigo-50/40">
        <td colspan="8" class="px-0 py-0">
            <div class="ml-8 mr-4 my-2 rounded-lg border border-indigo-100 overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-indigo-50 text-indigo-700 font-semibold uppercase tracking-wide">
                            <th class="px-3 py-1.5 text-left">Consultant</th>
                            <th class="px-3 py-1.5 text-left">Module</th>
                            <th class="px-3 py-1.5 text-right">Alloc MD</th>
                            <th class="px-3 py-1.5 text-right">Add. MD</th>
                            <th class="px-3 py-1.5 text-right">Remain</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${details.map(d => `
                        <tr class="border-t border-indigo-100 hover:bg-indigo-50/60">
                            <td class="px-3 py-1.5 font-medium text-gray-800">
                                ${d.emp_name}<br><span class="text-gray-400 font-normal">${d.eci}</span>
                            </td>
                            <td class="px-3 py-1.5 text-gray-500">${d.module}</td>
                            <td class="px-3 py-1.5 text-right font-semibold text-gray-700">${d.mandays} md</td>
                            <td class="px-3 py-1.5 text-right text-indigo-600 font-semibold">${d.approved_additional > 0 ? d.approved_additional + ' md' : '<span class="text-gray-300">—</span>'}</td>
                            <td class="px-3 py-1.5 text-right">
                                ${d.remain_md !== null && d.remain_md !== undefined
                                    ? `<span class="font-semibold ${d.remain_md > 0 ? 'text-orange-600' : 'text-green-600'}">${d.remain_md} d</span>
                                       <span class="ml-1 text-xs font-bold text-orange-800 bg-orange-100 rounded px-1 py-0.5">↑${Math.ceil(d.remain_md)} d</span>`
                                    : '<span class="text-gray-300">—</span>'}
                            </td>
                        </tr>`).join('')}
                        <tr class="border-t-2 border-indigo-200 bg-indigo-50 font-semibold text-xs">
                            <td class="px-3 py-1.5 text-indigo-700">Total (${details.length} consultant${details.length > 1 ? 's' : ''})</td>
                            <td class="px-3 py-1.5"></td>
                            <td class="px-3 py-1.5 text-right text-gray-700">${details.reduce((s,d)=>s+(parseFloat(d.mandays)||0),0).toFixed(2)} md</td>
                            <td class="px-3 py-1.5 text-right text-indigo-600">${details.reduce((s,d)=>s+(parseFloat(d.approved_additional)||0),0).toFixed(2)} md</td>
                            <td class="px-3 py-1.5 text-right text-orange-600">${details.reduce((s,d)=>s+(parseFloat(d.remain_md)||0),0).toFixed(2)} d</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </td>
    </tr>` : '';

    const moduleHtml = myModules && myModules !== '-'
        ? myModules.split(', ').map(m => `<span class="inline-block bg-indigo-50 text-indigo-700 border border-indigo-100 rounded px-1.5 py-0.5 mr-0.5 mb-0.5 text-xs font-medium">${m}</span>`).join('')
        : '<span class="text-gray-300">—</span>';

    const mainRow = `
    <tr class="border-b border-gray-100 hover:bg-gray-50/70 cursor-pointer"
        onclick="window.location='/ticket/${t.ticket_id}'">
        <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-700">${t.ticket_number ?? '—'}</td>
        <td class="px-4 py-3 text-sm text-gray-700">${t.customer_name ?? '—'}</td>
        <td class="px-4 py-3">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${st.cls}">${st.text}</span>
        </td>
        <td class="px-4 py-3">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${prCls}">${t.ticket_priority ?? '—'}</span>
        </td>
        <td class="px-4 py-3">${moduleHtml}</td>
        <td class="px-4 py-3 text-right font-semibold text-gray-700">${t.man_days || '—'}</td>
        <td class="px-4 py-3 text-right text-xs text-gray-500">${endDate}</td>
        <td class="px-4 py-3" onclick="event.stopPropagation()">
            <div class="flex items-center gap-2">
                <div class="bg-gray-100 rounded-full h-2" style="width:110px">
                    <div class="${progressBarColor(pct)} h-2 rounded-full transition-all" style="width:${pct}%"></div>
                </div>
                <span class="text-xs font-bold text-gray-700 w-9 text-right shrink-0">${pct}%</span>
                <button onclick="event.stopPropagation(); openCpModal(${t.ticket_id}, '${(t.ticket_number??'').replace(/'/g,"\\'")}', ${pct}, '${(t.progress_note??'').replace(/'/g,"\\'")}')"
                    class="ml-1 text-xs text-red-600 hover:text-red-700 font-semibold border border-red-200 px-1.5 py-0.5 rounded hover:bg-red-50 transition">
                    Edit
                </button>
                ${expandBtn}
            </div>
        </td>
    </tr>`;

    return mainRow + detailSubTable;
}

function toggleDetail(ticketId) {
    const row  = document.getElementById(`det-${ticketId}`);
    const icon = document.getElementById(`det-icon-${ticketId}`);
    if (!row) return;
    const hidden = row.classList.contains('hidden');
    row.classList.toggle('hidden', !hidden);
    if (icon) icon.textContent = hidden ? '▼' : '▶';
}

// ── Ticket Progress Modal ──────────────────────────────────────────
function openCpModal(ticketId, ticketNumber, pct, note) {
    document.getElementById('cpModalTicketId').value        = ticketId;
    document.getElementById('cpModalSubject').textContent   = ticketNumber;
    document.getElementById('cpModalSlider').value = pct;
    document.getElementById('cpModalValue').value  = pct;
    document.getElementById('cpModalNote').value            = note ?? '';
    document.getElementById('cpModal').classList.remove('hidden');
    document.getElementById('cpModal').classList.add('flex');
}

function closeCpModal() {
    document.getElementById('cpModal').classList.add('hidden');
    document.getElementById('cpModal').classList.remove('flex');
}

async function submitCpModal() {
    const ticketId = document.getElementById('cpModalTicketId').value;
    const pct      = document.getElementById('cpModalValue').value;
    const note     = document.getElementById('cpModalNote').value;
    try {
        const res = await fetch(`/api/consultant-workload/tickets/${ticketId}/progress`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ progress_percentage: pct, progress_note: note }),
        });
        const json = await res.json();
        if (json.success) { closeCpModal(); loadTasks(); }
        else alert('Failed: ' + (json.message ?? 'Error'));
    } catch (e) { alert('Error: ' + e.message); }
}

document.getElementById('cpModal').addEventListener('click', function(e) {
    if (e.target === this) closeCpModal();
});

// ── Status header filter ───────────────────────────────────────────
function toggleStatusFilter() {
    const panel = document.getElementById('statusFilterPanel');
    const arrow = document.getElementById('statusFilterArrow');
    const btn   = document.querySelector('#statusFilterWrap button');
    const open  = !panel.classList.contains('hidden');
    if (!open) {
        const r = btn.getBoundingClientRect();
        panel.style.top  = `${r.bottom + 4}px`;
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
    // Highlight active option
    document.querySelectorAll('.status-opt').forEach(el => {
        el.classList.toggle('bg-gray-100', el.dataset.value === value);
        el.classList.toggle('font-bold', el.dataset.value === value);
    });
    filterTable();
}

document.addEventListener('click', function(e) {
    if (!document.getElementById('statusFilterWrap')?.contains(e.target)) {
        document.getElementById('statusFilterPanel')?.classList.add('hidden');
        document.getElementById('statusFilterArrow').style.transform = '';
    }
});

document.addEventListener('DOMContentLoaded', loadTasks);
</script>
@endsection
