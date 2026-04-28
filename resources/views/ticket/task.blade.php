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
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Active Tickets</p>
            <p class="text-2xl font-bold text-gray-900" id="cardTicketCount">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total Man Days</p>
            <p class="text-2xl font-bold text-gray-900" id="cardTotalMd">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-5 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Actual MD This Month</p>
            <p class="text-2xl font-bold text-gray-900" id="cardActualMd">—</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm" id="taskTable">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-4 py-3 text-left" style="min-width:130px">Ticket No.</th>
                        <th class="px-4 py-3 text-left" style="min-width:260px">Subject</th>
                        <th class="px-4 py-3 text-left" style="min-width:150px">Customer</th>
                        <th class="px-4 py-3 text-left" style="min-width:110px">Status</th>
                        <th class="px-4 py-3 text-left" style="min-width:90px">Priority</th>
                        <th class="px-4 py-3 text-left" style="min-width:80px">Module</th>
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

{{-- Consultant Progress Modal --}}
<div id="cpModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h3 class="text-base font-bold text-gray-900 mb-0.5">Update My Progress</h3>
        <p class="text-xs text-indigo-600 font-semibold mb-0.5" id="cpModalEmp">—</p>
        <p class="text-sm text-gray-500 mb-4 truncate" id="cpModalSubject">—</p>
        <input type="hidden" id="cpModalDetailId">
        <div class="mb-4">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Progress (%)</label>
            <div class="flex items-center gap-3 mt-2">
                <input type="range" id="cpModalSlider" min="0" max="100" step="5" value="0"
                    oninput="document.getElementById('cpModalValue').textContent = this.value + '%'"
                    class="flex-1 accent-indigo-600">
                <span id="cpModalValue" class="text-sm font-bold text-indigo-600 w-10 text-right">0%</span>
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
let allTasks = [];

const STATUS_BADGE = {
    open:          { text: 'Open',        cls: 'bg-blue-100 text-blue-700' },
    in_progress:   { text: 'In Progress', cls: 'bg-yellow-100 text-yellow-700' },
    hold:          { text: 'Hold',        cls: 'bg-orange-100 text-orange-700' },
    reply:         { text: 'Reply',       cls: 'bg-purple-100 text-purple-700' },
    wait_to_close: { text: 'Wait Close',  cls: 'bg-teal-100 text-teal-700' },
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
        <tr><td colspan="9" class="text-center py-12 text-gray-400">
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
        allTasks = json.data ?? [];
        updateCards(json.summary ?? {});
        renderTable(allTasks);
    } catch (e) {
        document.getElementById('taskBody').innerHTML =
            `<tr><td colspan="9" class="text-center py-8 text-red-500 text-sm">Failed: ${e.message}</td></tr>`;
    }
}

function filterTable() {
    const q = document.getElementById('searchTask').value.toLowerCase();
    const filtered = q
        ? allTasks.filter(t =>
            (t.ticket_number ?? '').toLowerCase().includes(q) ||
            (t.subject ?? '').toLowerCase().includes(q) ||
            (t.customer_name ?? '').toLowerCase().includes(q) ||
            (t.module ?? '').toLowerCase().includes(q))
        : allTasks;
    renderTable(filtered);
}

function updateCards(summary) {
    document.getElementById('cardTicketCount').textContent = summary.ticket_count ?? 0;
    document.getElementById('cardTotalMd').textContent     = (summary.total_man_days ?? 0) + ' md';
    document.getElementById('cardActualMd').textContent    = (summary.actual_md ?? 0) + ' md';
    document.getElementById('summaryText').textContent     = `${summary.ticket_count ?? 0} active ticket(s) as PIC`;
}

// ── Render ─────────────────────────────────────────────────────────
function renderTable(tasks) {
    if (!tasks.length) {
        document.getElementById('taskBody').innerHTML =
            `<tr><td colspan="9" class="text-center py-12 text-gray-400">
                <i class="fas fa-check-circle text-3xl mb-3 block text-gray-300"></i>
                No active tickets assigned as PIC
            </td></tr>`;
        return;
    }
    document.getElementById('taskBody').innerHTML = tasks.map(t => taskRows(t)).join('');
}

function taskRows(t) {
    const pct     = parseFloat(t.consultant_progress) || 0;
    const st      = STATUS_BADGE[t.status] ?? { text: t.status, cls: 'bg-gray-100 text-gray-600' };
    const prCls   = PRIORITY_CLS[t.ticket_priority] ?? 'bg-gray-100 text-gray-600';
    const endDate = t.end_date ? new Date(t.end_date).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—';

    const details    = t.consultant_details ?? [];
    const hasDetails = details.length > 0;

    const expandBtn = hasDetails
        ? `<button onclick="event.stopPropagation(); toggleDetail(${t.ticket_id})"
                class="ml-1 text-indigo-500 hover:text-indigo-700 text-xs font-semibold border border-indigo-200 px-1.5 py-0.5 rounded hover:bg-indigo-50 transition"
                title="Progress per consultant">
                <span id="det-icon-${t.ticket_id}">▶</span> ${details.length}
           </button>`
        : `<span class="ml-1 text-xs text-gray-300">No mandays yet</span>`;

    const detailSubTable = hasDetails ? `
    <tr id="det-${t.ticket_id}" class="hidden bg-indigo-50/40">
        <td colspan="9" class="px-0 py-0">
            <div class="ml-8 mr-4 my-2 rounded-lg border border-indigo-100 overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-indigo-50 text-indigo-700 font-semibold uppercase tracking-wide">
                            <th class="px-3 py-1.5 text-left">Consultant</th>
                            <th class="px-3 py-1.5 text-left">Module</th>
                            <th class="px-3 py-1.5 text-right">MD</th>
                            <th class="px-3 py-1.5 text-right">+Additional</th>
                            <th class="px-3 py-1.5 text-right">Remain Duration</th>
                            <th class="px-3 py-1.5 text-left w-44">Progress</th>
                            <th class="px-3 py-1.5 text-left">Notes</th>
                            <th class="px-3 py-1.5 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${details.map(d => {
                            const dpct  = parseFloat(d.progress_percentage) || 0;
                            const updAt = d.progress_updated_at
                                ? new Date(d.progress_updated_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})
                                : '—';
                            return `<tr class="border-t border-indigo-100 hover:bg-indigo-50/60">
                                <td class="px-3 py-1.5 font-medium text-gray-800">
                                    ${d.emp_name}<br><span class="text-gray-400 font-normal">${d.eci}</span>
                                </td>
                                <td class="px-3 py-1.5 text-gray-500">${d.module}</td>
                                <td class="px-3 py-1.5 text-right font-semibold text-gray-700">${d.mandays}</td>
                                <td class="px-3 py-1.5 text-right text-gray-500">${d.approved_additional > 0 ? d.approved_additional : '—'}</td>
                                <td class="px-3 py-1.5 text-right">
                                    ${d.remain_md !== null && d.remain_md !== undefined
                                        ? `<span class="font-semibold ${d.remain_md > 0 ? 'text-orange-600' : 'text-green-600'}">${d.remain_md} d</span>
                                           <span class="ml-1 text-xs font-bold text-orange-800 bg-orange-100 rounded px-1 py-0.5">↑${Math.ceil(d.remain_md)} d</span>`
                                        : '<span class="text-gray-300">—</span>'}
                                </td>
                                <td class="px-3 py-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <div class="bg-gray-200 rounded-full h-1.5" style="width:80px">
                                            <div class="${progressBarColor(dpct)} h-1.5 rounded-full" style="width:${dpct}%"></div>
                                        </div>
                                        <span class="font-bold ${dpct>=75?'text-green-700':dpct>=40?'text-yellow-700':'text-red-600'}">${dpct}%</span>
                                    </div>
                                    <div class="text-gray-400 mt-0.5">Updated: ${updAt}</div>
                                </td>
                                <td class="px-3 py-1.5 text-gray-500 max-w-xs">
                                    <span class="line-clamp-2">${d.progress_note || '—'}</span>
                                </td>
                                <td class="px-3 py-1.5 text-center">
                                    <button onclick="event.stopPropagation(); openCpModal(${d.detail_id}, '${d.emp_name.replace(/'/g,"\\'")}', '${(t.subject??'').replace(/'/g,"\\'")}', ${dpct}, '${(d.progress_note??'').replace(/'/g,"\\'")}')"
                                        class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold border border-indigo-200 px-2 py-0.5 rounded hover:bg-indigo-50 transition whitespace-nowrap">
                                        Edit
                                    </button>
                                </td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        </td>
    </tr>` : '';

    const mainRow = `
    <tr class="border-b border-gray-100 hover:bg-gray-50/70 cursor-pointer"
        onclick="window.location='/ticket/${t.ticket_id}'">
        <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-700">${t.ticket_number ?? '—'}</td>
        <td class="px-4 py-3 text-sm text-gray-800 max-w-xs">
            <span class="line-clamp-2" title="${(t.subject??'').replace(/"/g,'&quot;')}">${t.subject ?? '—'}</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-700">${t.customer_name ?? '—'}</td>
        <td class="px-4 py-3">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${st.cls}">${st.text}</span>
        </td>
        <td class="px-4 py-3">
            <span class="px-1.5 py-0.5 rounded text-xs font-medium ${prCls}">${t.ticket_priority ?? '—'}</span>
        </td>
        <td class="px-4 py-3 text-xs text-gray-500">${t.module ?? '—'}</td>
        <td class="px-4 py-3 text-right font-semibold text-gray-700">${t.man_days || '—'}</td>
        <td class="px-4 py-3 text-right text-xs text-gray-500">${endDate}</td>
        <td class="px-4 py-3" onclick="event.stopPropagation()">
            <div class="flex items-center gap-2">
                <div class="bg-gray-100 rounded-full h-2" style="width:110px">
                    <div class="${progressBarColor(pct)} h-2 rounded-full transition-all" style="width:${pct}%"></div>
                </div>
                <span class="text-xs font-bold text-gray-700 w-9 text-right shrink-0">${pct}%</span>
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

// ── Consultant Progress Modal ──────────────────────────────────────
function openCpModal(detailId, empName, subject, pct, note) {
    document.getElementById('cpModalDetailId').value       = detailId;
    document.getElementById('cpModalEmp').textContent      = empName;
    document.getElementById('cpModalSubject').textContent  = subject;
    document.getElementById('cpModalSlider').value         = pct;
    document.getElementById('cpModalValue').textContent    = pct + '%';
    document.getElementById('cpModalNote').value           = note ?? '';
    document.getElementById('cpModal').classList.remove('hidden');
    document.getElementById('cpModal').classList.add('flex');
}

function closeCpModal() {
    document.getElementById('cpModal').classList.add('hidden');
    document.getElementById('cpModal').classList.remove('flex');
}

async function submitCpModal() {
    const detailId = document.getElementById('cpModalDetailId').value;
    const pct      = document.getElementById('cpModalSlider').value;
    const note     = document.getElementById('cpModalNote').value;
    try {
        const res = await fetch(`/api/consultant-workload/consultant-progress/${detailId}`, {
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

document.addEventListener('DOMContentLoaded', loadTasks);
</script>
@endsection
