@extends('dashboard')
@section('title', 'Incoming Ticket Validation')
@section('page-title', 'Incoming Ticket Validation')
@section('page-subtitle', 'Tickets submitted by customers awaiting approval')

@section('content')
{{-- ===== STATS CARDS ===== --}}
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md transition-all duration-200">
        <p class="text-xs font-medium text-gray-500 mb-1">Pending Validation</p>
        <p class="text-2xl font-bold text-gray-900" id="statUnvalidated">—</p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md transition-all duration-200">
        <p class="text-xs font-medium text-gray-500 mb-1">Approved</p>
        <p class="text-2xl font-bold text-gray-900" id="statApproved">—</p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 p-3 hover:shadow-md transition-all duration-200">
        <p class="text-xs font-medium text-gray-500 mb-1">Rejected</p>
        <p class="text-2xl font-bold text-gray-900" id="statRejected">—</p>
    </div>
</div>

{{-- ===== TOOLBAR ===== --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-700">Incoming Tickets</h3>
        <div class="flex items-center gap-2 flex-wrap">
            <select id="filterStatus" onchange="loadStagingTickets()"
                    class="pl-3 pr-8 py-2 text-sm border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent">
                <option value="">All Status</option>
                <option value="unvalidated" selected>Pending Validation</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
            </select>
            <button onclick="loadStagingTickets()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-sync-alt text-xs"></i> Refresh
            </button>
            <a href="{{ route('staging.rejected') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-red-50 text-red-700 border border-red-200 text-sm font-semibold rounded-lg hover:bg-red-100 transition-all">
                <i class="fas fa-ban text-xs"></i> View Rejected
                <span id="rejectedNavBadge" class="hidden bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"></span>
            </a>
        </div>
    </div>
</div>

{{-- ===== PAGINATION ===== --}}
<div id="paginationArea" class="items-center justify-between mb-4 hidden">
    <span class="text-sm text-gray-500" id="pageInfo"></span>
    <div class="flex items-center gap-1">
        <button id="btnPrev" onclick="changePage(-1)"
                class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>
        <button id="btnNext" onclick="changePage(1)"
                class="p-1.5 rounded-lg border border-gray-200 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>
</div>

{{-- ===== TABLE ===== --}}
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50 text-xs text-gray-500 font-bold uppercase tracking-wide">
                    <th class="px-6 py-3 text-left">ID</th>
                    <th class="px-6 py-3 text-left">Customer / Sender</th>
                    <th class="px-6 py-3 text-left">Description / Subject</th>
                    <th class="px-6 py-3 text-left">Priority</th>
                    <th class="px-6 py-3 text-left">Channel</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Submit Date</th>
                    <th class="px-6 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody id="stagingTableBody">
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                        Loading data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ===== MODAL DETAIL / APPROVE / REJECT ===== --}}
<div id="stagingModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display:none">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col" style="max-height:90vh">
        {{-- Header --}}
        <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div>
                <h3 class="text-base font-extrabold text-gray-900">Incoming Ticket Details</h3>
                <p class="text-xs text-gray-400 mt-0.5" id="modalStagingId"></p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Body (scrollable) --}}
        <div class="overflow-y-auto flex-1 p-7" id="modalBody">
            <div class="flex items-center justify-center py-12 text-gray-400">
                <i class="fas fa-spinner fa-spin text-2xl mr-2"></i> Loading...
            </div>
        </div>

        {{-- Footer --}}
        <div id="modalFooter" class="border-t border-gray-100 px-7 py-4 bg-gray-50 flex justify-end gap-3 flex-shrink-0">
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentPage = 1;
let meta = {};
let currentStagingId = null;
let currentStagingData = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadStagingTickets();
});

// ─── Stats ────────────────────────────────────────────────────────────────────
async function loadStats() {
    try {
        const res  = await apiFetch('/api/staging-tickets/statistics');
        const data = res.data;
        document.getElementById('statUnvalidated').textContent = data.unvalidated ?? 0;
        document.getElementById('statApproved').textContent    = data.approved    ?? 0;
        document.getElementById('statRejected').textContent    = data.rejected    ?? 0;
        updateSidebarBadge(data.unvalidated ?? 0);
        const rejBadge = document.getElementById('rejectedNavBadge');
        if (rejBadge) {
            const rCount = data.rejected ?? 0;
            rejBadge.textContent = rCount > 99 ? '99+' : rCount;
            rejBadge.classList.toggle('hidden', rCount === 0);
        }
    } catch {}
}

function updateSidebarBadge(count) {
    const badge = document.getElementById('sidebarValidationBadge');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

// ─── Table ────────────────────────────────────────────────────────────────────
async function loadStagingTickets(page = 1) {
    currentPage = page;
    const status = document.getElementById('filterStatus').value;
    const params = new URLSearchParams({ per_page: 15, page });
    if (status) params.append('status', status);

    const tbody = document.getElementById('stagingTableBody');
    tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-12 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading...</td></tr>`;

    try {
        const res = await apiFetch('/api/staging-tickets?' + params.toString());
        meta = res.meta ?? {};
        renderTable(res.data ?? []);
        renderPagination();
    } catch {
        tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-10 text-center text-red-500 text-sm">
            <i class="fas fa-exclamation-circle mr-1"></i>Failed to load data.</td></tr>`;
    }
}

function renderTable(rows) {
    const tbody = document.getElementById('stagingTableBody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-12 text-center text-gray-400 text-sm">
            <i class="fas fa-inbox text-3xl mb-3 block opacity-30"></i>No data found.</td></tr>`;
        return;
    }

    const prioColor = { Low: 'bg-green-100 text-green-700', Medium: 'bg-blue-100 text-blue-700', High: 'bg-red-100 text-red-700' };
    const statusBadge = {
        unvalidated: '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span>Pending</span>',
        approved:    '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>Approved</span>',
        rejected:    '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-700"><span class="w-1.5 h-1.5 rounded-full bg-red-500 inline-block"></span>Rejected</span>',
    };

    tbody.innerHTML = rows.map(s => {
        const date   = s.created_at ? new Date(s.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—';
        const short  = s.description ? (s.description.length > 60 ? s.description.substring(0, 60) + '…' : s.description) : '—';
        const prio   = s.ticket_priority;
        const prioBadge = prio
            ? `<span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold ${prioColor[prio] ?? 'bg-gray-100 text-gray-600'}">${prio}</span>`
            : `<span class="text-gray-400 text-xs italic">—</span>`;
        const ch = s.channel === 'email'
            ? '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 bg-blue-50 text-blue-700 rounded-md"><i class="fas fa-envelope text-[9px]"></i> Email</span>'
            : '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 bg-gray-100 text-gray-600 rounded-md"><i class="fas fa-globe text-[9px]"></i> Web</span>';

        const actionBtn = s.status === 'unvalidated'
            ? `<button onclick="openModal(${s.id})"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-700 text-white text-xs font-bold rounded-lg hover:bg-red-800 transition-all">
                   <i class="fas fa-gavel text-[10px]"></i> Validate
               </button>`
            : `<button onclick="openModal(${s.id})"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition-all">
                   <i class="fas fa-eye text-[10px]"></i> Detail
               </button>`;

        const senderDisplay = s.customer_name ?? s.sender_name ?? 'Unknown';

        return `<tr class="border-b border-gray-50 hover:bg-gray-50/60 transition-colors">
            <td class="px-6 py-4 text-gray-500 font-mono text-xs">#${s.id}</td>
            <td class="px-6 py-4">
                <p class="font-semibold text-gray-900 text-xs">${escHtml(senderDisplay)}</p>
                ${s.submitted_by_email ? `<p class="text-[10px] text-gray-400">${escHtml(s.submitted_by_email)}</p>` : ''}
            </td>
            <td class="px-6 py-4 text-gray-600 max-w-xs text-xs">${escHtml(short)}</td>
            <td class="px-6 py-4">${prioBadge}</td>
            <td class="px-6 py-4">${ch}</td>
            <td class="px-6 py-4">${statusBadge[s.status] ?? s.status}</td>
            <td class="px-6 py-4 text-gray-500 text-xs whitespace-nowrap">${date}</td>
            <td class="px-6 py-4">${actionBtn}</td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    const area = document.getElementById('paginationArea');
    if (!meta.total) { area.classList.add('hidden'); area.classList.remove('flex'); return; }
    area.classList.remove('hidden');
    area.classList.add('flex');
    document.getElementById('pageInfo').textContent =
        `Showing ${Math.min((currentPage-1)*meta.per_page+1, meta.total)}–${Math.min(currentPage*meta.per_page, meta.total)} of ${meta.total}`;
    document.getElementById('btnPrev').disabled = currentPage <= 1;
    document.getElementById('btnNext').disabled = currentPage >= meta.last_page;
}

function changePage(dir) {
    loadStagingTickets(currentPage + dir);
}

// ─── Modal ────────────────────────────────────────────────────────────────────
async function openModal(id) {
    currentStagingId   = id;
    currentStagingData = null;
    document.getElementById('stagingModal').style.display = 'flex';
    document.getElementById('modalStagingId').textContent  = `Staging #${id}`;
    document.getElementById('modalBody').innerHTML =
        `<div class="flex items-center justify-center py-12 text-gray-400">
            <i class="fas fa-spinner fa-spin text-2xl mr-2"></i> Loading...
        </div>`;
    document.getElementById('modalFooter').innerHTML = '';

    try {
        const res = await apiFetch(`/api/staging-tickets/${id}`);
        currentStagingData = res.data;
        fillModal(res.data);
    } catch {
        document.getElementById('modalBody').innerHTML =
            `<div class="text-center py-12 text-red-500 text-sm">
                <i class="fas fa-exclamation-circle mr-1"></i>Failed to load details.
            </div>`;
        document.getElementById('modalFooter').innerHTML =
            `<button onclick="closeModal()" class="px-5 py-2.5 text-sm font-semibold text-gray-700 border-2 border-gray-200 rounded-xl hover:bg-gray-50">Close</button>`;
    }
}

function fillModal(s) {
    const isUnvalidated = s.status === 'unvalidated';
    const isEmail       = s.channel === 'email';

    // ── Status badge ──
    const statusColors = {
        unvalidated: 'bg-amber-50 text-amber-700 border-amber-200',
        approved:    'bg-green-50 text-green-700 border-green-200',
        rejected:    'bg-red-50 text-red-700 border-red-200',
    };
    let statusHtml = `<div class="flex flex-wrap items-center gap-2 p-3 rounded-xl border ${statusColors[s.status] ?? ''} text-sm font-semibold mb-4">
        <span>Status: ${s.status.charAt(0).toUpperCase() + s.status.slice(1)}</span>`;
    if (s.status === 'approved' && s.ticket_number) {
        statusHtml += ` <a href="/ticket/${s.ticket_id}" class="underline font-bold">→ Ticket ${escHtml(s.ticket_number)}</a>`;
    }
    if (s.status === 'rejected' && s.rejection_reason) {
        statusHtml += `<span class="w-full font-normal text-xs">Reason: ${escHtml(s.rejection_reason)}</span>`;
    }
    statusHtml += '</div>';

    // ── Email header (for email-channel tickets) ──
    let emailHeaderHtml = '';
    if (isEmail) {
        const fromDisplay = s.sender_name
            ? `${escHtml(s.sender_name)} &lt;${escHtml(s.submitted_by_email ?? '')}&gt;`
            : escHtml(s.submitted_by_email ?? '—');
        const ccRaw = s.cc_emails;
        const ccDisplay = ccRaw
            ? escHtml(Array.isArray(ccRaw) ? ccRaw.join(', ') : ccRaw)
            : null;

        emailHeaderHtml = `
        <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden mb-4">
            <div class="px-4 py-2 border-b border-gray-200 bg-gray-100">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">
                    <i class="fas fa-envelope mr-1.5"></i>Email Details
                </span>
            </div>
            <div class="divide-y divide-gray-100 text-sm">
                <div class="grid px-4 py-2" style="grid-template-columns:70px 1fr">
                    <span class="text-xs font-semibold text-gray-500 pt-0.5">From</span>
                    <span class="text-gray-800">${fromDisplay}</span>
                </div>
                ${ccDisplay ? `<div class="grid px-4 py-2" style="grid-template-columns:70px 1fr">
                    <span class="text-xs font-semibold text-gray-500 pt-0.5">CC</span>
                    <span class="text-gray-700">${ccDisplay}</span>
                </div>` : ''}
                <div class="grid px-4 py-2" style="grid-template-columns:70px 1fr">
                    <span class="text-xs font-semibold text-gray-500 pt-0.5">Date</span>
                    <span class="text-gray-700">${s.created_at ? new Date(s.created_at).toLocaleString('en-GB') : '—'}</span>
                </div>
                <div class="grid px-4 py-2" style="grid-template-columns:70px 1fr">
                    <span class="text-xs font-semibold text-gray-500 pt-0.5">Subject</span>
                    <span class="font-semibold text-gray-800">${escHtml(s.description ?? '—')}</span>
                </div>
            </div>
        </div>`;
    }

    // ── Email body / Description ──
    let contentHtml = '';
    if (isEmail) {
        if (s.email_body_html) {
            contentHtml = `
            <div class="mb-4">
                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">
                    <i class="fas fa-file-alt mr-1"></i>Email Content
                </p>
                <div class="border border-gray-200 rounded-xl overflow-hidden bg-white">
                    <iframe id="emailBodyIframe" sandbox="allow-same-origin"
                            class="w-full" style="min-height:300px;border:none;display:block"
                            title="Email content preview"></iframe>
                </div>
            </div>`;
        } else {
            contentHtml = `
            <div class="mb-4 bg-gray-50 border border-dashed border-gray-300 rounded-xl p-6 text-center text-gray-400 text-sm">
                <i class="fas fa-envelope-open text-3xl mb-2 block opacity-30"></i>
                No email body content stored for this ticket.
            </div>`;
        }
    } else {
        // Web ticket — customer info + description
        contentHtml = `
        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Customer</p>
                <p class="text-sm font-bold text-gray-800">${escHtml(s.customer_name ?? '—')}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Submit Date</p>
                <p class="text-sm font-bold text-gray-800">${s.created_at ? new Date(s.created_at).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '—'}</p>
            </div>
        </div>
        <div class="mb-4 bg-gray-50 rounded-xl p-4">
            <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-2">Description</p>
            <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">${escHtml(s.description ?? '—')}</p>
        </div>`;
    }

    // ── Validation form (type + priority) ──
    let validationHtml = '';
    if (isUnvalidated) {
        validationHtml = `
        <div class="border-t border-gray-100 pt-4 mt-2">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">
                <i class="fas fa-clipboard-check mr-1"></i>Set Ticket Details
            </p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">
                        Ticket Type <span class="text-red-500">*</span>
                    </label>
                    <select id="approveTicketType"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:border-green-600 focus:ring-4 focus:ring-green-600/10 transition-all">
                        <option value="">-- Select Type --</option>
                        <option value="Incident">Incident</option>
                        <option value="Service Request">Service Request</option>
                        <option value="Change Request">Change Request</option>
                        <option value="Consult">Consult</option>
                    </select>
                    <p id="typeError" class="hidden mt-1 text-xs text-red-600 font-medium">Please select a ticket type.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">
                        Priority <span class="text-red-500">*</span>
                    </label>
                    <select id="approvePriority"
                            class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl text-sm bg-white focus:outline-none focus:border-green-600 focus:ring-4 focus:ring-green-600/10 transition-all">
                        <option value="">-- Select Priority --</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                    <p id="priorityError" class="hidden mt-1 text-xs text-red-600 font-medium">Please select a priority.</p>
                </div>
            </div>
        </div>`;
    } else {
        // Show existing type/priority as info badges
        const prioColor = { Low: 'bg-green-100 text-green-700', Medium: 'bg-blue-100 text-blue-700', High: 'bg-red-100 text-red-700' };
        const typeColor = { 'Incident': 'bg-red-50 text-red-600', 'Service Request': 'bg-indigo-50 text-indigo-600', 'Change Request': 'bg-amber-50 text-amber-600', 'Consult': 'bg-teal-50 text-teal-600' };
        const typeBadge = s.ticket_type
            ? `<span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold ${typeColor[s.ticket_type] ?? 'bg-gray-100 text-gray-600'}">${escHtml(s.ticket_type)}</span>`
            : '<span class="text-sm text-gray-400 italic">—</span>';
        const prioBadge = s.ticket_priority
            ? `<span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold ${prioColor[s.ticket_priority] ?? 'bg-gray-100 text-gray-600'}">${escHtml(s.ticket_priority)}</span>`
            : '<span class="text-sm text-gray-400 italic">—</span>';
        validationHtml = `
        <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-2">Ticket Type</p>
                ${typeBadge}
            </div>
            <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-2">Priority</p>
                ${prioBadge}
            </div>
        </div>`;
    }

    // ── Reject reason area ──
    const rejectAreaHtml = `
    <div id="rejectReasonArea" class="hidden border-t border-gray-100 pt-4 mt-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">
            Rejection Reason <span class="text-red-500">*</span>
        </label>
        <textarea id="rejectReasonInput" rows="3"
                  placeholder="Explain the reason for rejecting this ticket..."
                  class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-700 focus:ring-4 focus:ring-red-700/10 transition-all resize-none"></textarea>
        <p id="rejectReasonError" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
    </div>`;

    // ── Assemble body ──
    document.getElementById('modalBody').innerHTML =
        statusHtml + emailHeaderHtml + contentHtml + validationHtml + rejectAreaHtml;

    // Set iframe srcdoc after DOM is updated
    if (isEmail && s.email_body_html) {
        const iframe = document.getElementById('emailBodyIframe');
        if (iframe) {
            iframe.srcdoc = s.email_body_html;
            iframe.addEventListener('load', () => {
                try {
                    const h = iframe.contentDocument?.documentElement?.scrollHeight
                           ?? iframe.contentDocument?.body?.scrollHeight;
                    if (h && h > 100) {
                        iframe.style.minHeight = Math.min(h + 24, 600) + 'px';
                    }
                } catch {}
            }, { once: true });
        }
    }

    // ── Footer buttons ──
    renderFooter(s);
}

function renderFooter(s) {
    const footer = document.getElementById('modalFooter');
    if (s.status === 'unvalidated') {
        footer.innerHTML = `
            <button onclick="closeModal()" class="px-5 py-2.5 text-sm font-semibold text-gray-700 border-2 border-gray-200 rounded-xl hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="showRejectInput(${s.id})" id="btnReject"
                    class="px-5 py-2.5 text-sm font-bold text-white bg-gray-600 rounded-xl hover:bg-gray-700 transition-all flex items-center gap-2">
                <i class="fas fa-times"></i> Reject
            </button>
            <button onclick="submitApprove(${s.id})" id="btnApprove"
                    class="px-5 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-green-600 to-green-700 rounded-xl hover:shadow-lg transition-all flex items-center gap-2">
                <i class="fas fa-check"></i> Approve & Create Ticket
            </button>`;
    } else {
        footer.innerHTML = `
            <button onclick="closeModal()" class="px-5 py-2.5 text-sm font-semibold text-gray-700 border-2 border-gray-200 rounded-xl hover:bg-gray-50 transition-all">Close</button>`;
    }
}

function showRejectInput(id) {
    const rejectArea = document.getElementById('rejectReasonArea');
    if (rejectArea) {
        rejectArea.classList.remove('hidden');
        document.getElementById('rejectReasonInput')?.focus();
    }
    const footer = document.getElementById('modalFooter');
    footer.innerHTML = `
        <button onclick="cancelReject()" class="px-5 py-2.5 text-sm font-semibold text-gray-700 border-2 border-gray-200 rounded-xl hover:bg-gray-50 transition-all">Cancel</button>
        <button onclick="submitReject(${id})" id="btnConfirmReject"
                class="px-5 py-2.5 text-sm font-bold text-white bg-red-700 rounded-xl hover:bg-red-800 transition-all flex items-center gap-2">
            <i class="fas fa-times-circle"></i> Confirm Rejection
        </button>`;
}

function cancelReject() {
    const rejectArea = document.getElementById('rejectReasonArea');
    if (rejectArea) {
        rejectArea.classList.add('hidden');
        const inp = document.getElementById('rejectReasonInput');
        if (inp) inp.value = '';
    }
    if (currentStagingData) renderFooter(currentStagingData);
}

async function submitApprove(id) {
    const ticketType = document.getElementById('approveTicketType')?.value ?? '';
    const priority   = document.getElementById('approvePriority')?.value   ?? '';

    const typeErr = document.getElementById('typeError');
    const prioErr = document.getElementById('priorityError');
    let valid = true;

    if (!ticketType) { typeErr?.classList.remove('hidden'); valid = false; }
    else              { typeErr?.classList.add('hidden'); }

    if (!priority)    { prioErr?.classList.remove('hidden'); valid = false; }
    else              { prioErr?.classList.add('hidden'); }

    if (!valid) return;

    const btn = document.getElementById('btnApprove');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'; }

    try {
        const res = await apiFetch(`/api/staging-tickets/${id}/approve`, 'POST', {
            ticket_type:     ticketType,
            ticket_priority: priority,
        });
        showNotif('Ticket approved! Number: ' + (res.data?.ticket_number ?? ''), 'success');
        closeModal();
        loadStagingTickets(currentPage);
        loadStats();
    } catch (e) {
        showNotif(e.message || 'Failed to approve ticket.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check"></i> Approve & Create Ticket'; }
    }
}

async function submitReject(id) {
    const reason = document.getElementById('rejectReasonInput')?.value.trim() ?? '';
    const errEl  = document.getElementById('rejectReasonError');

    if (!reason) {
        if (errEl) { errEl.textContent = 'Rejection reason is required.'; errEl.classList.remove('hidden'); }
        return;
    }
    if (errEl) errEl.classList.add('hidden');

    const btn = document.getElementById('btnConfirmReject');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...'; }

    try {
        await apiFetch(`/api/staging-tickets/${id}/reject`, 'POST', { reason });
        showNotif('Ticket rejected successfully.', 'success');
        closeModal();
        loadStagingTickets(currentPage);
        loadStats();
    } catch (e) {
        showNotif(e.message || 'Failed to reject ticket.', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-times-circle"></i> Confirm Rejection'; }
    }
}

function closeModal() {
    document.getElementById('stagingModal').style.display = 'none';
    currentStagingId   = null;
    currentStagingData = null;
}
document.getElementById('stagingModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

// ─── Helpers ──────────────────────────────────────────────────────────────────
async function apiFetch(url, method = 'GET', body = null) {
    const opts = {
        method,
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        credentials: 'same-origin',
    };
    if (body) opts.body = JSON.stringify(body);
    const res  = await fetch(url, opts);
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function showNotif(msg, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
    const el = document.createElement('div');
    el.className = `fixed top-4 right-4 ${colors[type] ?? 'bg-gray-700'} text-white px-6 py-3 rounded-xl shadow-xl z-[200] text-sm font-semibold`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; setTimeout(() => el.remove(), 300); }, 3500);
}
</script>
@endpush
