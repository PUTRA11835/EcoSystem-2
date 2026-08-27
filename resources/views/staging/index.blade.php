@extends('dashboard')
@section('title', 'Incoming Ticket Validation')
@section('page-title', 'Incoming Ticket Validation')
@section('page-subtitle', 'Tickets submitted by customers awaiting approval')

@section('content')
<script>
const canApproveStaging = {{ $can('staging.approve') ? 'true' : 'false' }};
const canRejectStaging  = {{ $can('staging.reject')  ? 'true' : 'false' }};
</script>

{{-- ── Header ────────────────────────────────────────────────────────────────── --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
    <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl primary-gradient flex items-center justify-center shadow-sm">
            <i class="fas fa-inbox text-white text-sm"></i>
        </div>
        <div>
            <h1 class="text-xl font-bold text-gray-900 leading-tight">Incoming Ticket Validation</h1>
            <p class="text-xs text-gray-400 mt-0.5">Tickets submitted by customers awaiting approval</p>
        </div>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        {{-- Status filter --}}
        <div class="custom-dd relative" data-onchange="loadStagingTickets" data-fixed="true" style="min-width:170px">
            <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs font-semibold hover:border-gray-300 transition-all text-left shadow-sm">
                <span class="custom-dd-label text-gray-700">Pending Validation</span>
                <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <input type="hidden" id="filterStatus" value="unvalidated">
            <div class="custom-dd-panel hidden absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:240px;">
                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="">All Status</button>
                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-900 font-medium bg-gray-50 hover:bg-gray-50 transition-colors" data-value="unvalidated">Pending Validation</button>
                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="approved">Approved</button>
                <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="rejected">Rejected</button>
            </div>
        </div>
        <button onclick="handleRefresh()" id="btnRefresh"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
            <i class="fas fa-sync-alt text-xs"></i>Refresh
        </button>
        <a href="{{ route('staging.rejected') }}"
            class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all shadow-sm">
            <i class="fas fa-times-circle text-red-400 text-xs"></i>View Rejected
            <span id="rejectedNavBadge" class="hidden bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"></span>
        </a>
    </div>
</div>

{{-- ── Stats Cards ──────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 mb-4">
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:shadow-md hover:border-amber-200 transition-all duration-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Pending</p>
        </div>
        <p class="text-2xl font-bold text-amber-600 leading-none" id="statUnvalidated">—</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:shadow-md hover:border-green-200 transition-all duration-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Approved</p>
        </div>
        <p class="text-2xl font-bold text-green-600 leading-none" id="statApproved">—</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-4 py-3.5 hover:shadow-md hover:border-red-200 transition-all duration-200">
        <div class="flex items-center gap-1.5 mb-2">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Rejected</p>
        </div>
        <p class="text-2xl font-bold text-red-600 leading-none" id="statRejected">—</p>
    </div>
</div>

{{-- ── Table ────────────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    {{-- Table Toolbar --}}
    <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100 bg-gray-50/60">
        <span id="fetchEmailStatus" class="text-xs text-gray-400"></span>
        <div id="paginationArea" class="hidden items-center gap-1">
            <span class="text-xs text-gray-400 mr-2" id="pageInfo"></span>
            <button id="btnPrev" onclick="changePage(-1)"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                Prev
            </button>
            <button id="btnNext" onclick="changePage(1)"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium text-gray-500 bg-white border border-gray-200 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-30 disabled:cursor-not-allowed transition-all">
                Next
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
            </button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead class="sticky top-0 z-10 bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:60px">#</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:180px">Customer / Sender</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:280px">Description / Subject</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:100px">Priority</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:90px">Channel</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:130px">Status</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:110px">Submit Date</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200" style="min-width:90px">Action</th>
                </tr>
            </thead>
            <tbody id="stagingTableBody" class="divide-y divide-gray-100 bg-white">
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
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col" style="max-height:92vh">
        {{-- Header --}}
        <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center gap-3">
                <div>
                    <h3 class="text-base font-extrabold text-gray-900">Incoming Ticket Details</h3>
                    <p class="text-xs text-gray-400 mt-0.5" id="modalStagingId"></p>
                </div>
                <span id="modalStatusBadge"></span>
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
const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
let currentPage = 1;
let meta = {};
let currentStagingId = null;
let currentStagingData = null;
let _lastAiAnalysis = null;
let _aiProgressTimer = null;
let _aiProgressStart = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Init custom-dd untuk filter "Pending Validation". Guard typeof biar
    // halaman tidak crash kalau custom-dropdown.js gagal di-load.
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns();
    }
    loadStats();
    loadStagingTickets();
    fetchEmailInbox(true);                              // fetch sekali saat halaman dibuka
    setInterval(() => fetchEmailInbox(true), 60000);   // auto-poll email tiap 60 detik
    setInterval(() => { loadStats(); loadStagingTickets(); }, 30000); // auto-refresh list tiap 30 detik
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
    const params = new URLSearchParams({ per_page: 200, page });
    if (status) params.append('status', status);

    const url = '/api/staging-tickets?' + params.toString();
    const ts = new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

    const tbody = document.getElementById('stagingTableBody');
    tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-12 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading...</td></tr>`;

    try {
        const opts = {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        };
        const rawRes = await fetch(url, opts);

        const data = await rawRes.json();

        if (!data.success) {
            console.error(`[StagingFetch] Server error:`, data.message ?? data);
            throw new Error(data.message || 'Request failed');
        }

        meta = data.meta ?? {};
        renderTable(data.data ?? []);
        renderPagination();
    } catch (err) {
        console.error(`[StagingFetch] Exception:`, err);
        tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-10 text-center text-red-500 text-sm">
            <i class="fas fa-exclamation-circle mr-1"></i>Failed to load data.</td></tr>`;
    }
}

function renderTable(rows) {
    const tbody = document.getElementById('stagingTableBody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-6 py-16 text-center">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-inbox text-gray-300 text-2xl"></i>
            </div>
            <p class="text-gray-600 font-semibold text-sm mb-1">No tickets found</p>
            <p class="text-gray-400 text-xs">Try changing the status filter</p>
        </td></tr>`;
        return;
    }

    const badge = (label, cls, dot) =>
        `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold ${cls}">
            ${dot ? `<span class="w-1.5 h-1.5 rounded-full ${dot} flex-shrink-0"></span>` : ''}${label}
         </span>`;

    const prioCfg = {
        'Very High': { cls: 'bg-red-50 text-red-700',    dot: 'bg-red-500'    },
        'High':      { cls: 'bg-orange-50 text-orange-700', dot: 'bg-orange-500' },
        'Medium':    { cls: 'bg-yellow-50 text-yellow-700', dot: 'bg-yellow-500' },
        'Low':       { cls: 'bg-blue-50 text-blue-700',  dot: 'bg-blue-400'   },
    };
    const statusCfg = {
        unvalidated: { label: 'Pending',  cls: 'bg-amber-50 text-amber-700',  dot: 'bg-amber-500'  },
        approved:    { label: 'Approved', cls: 'bg-green-50 text-green-700',  dot: 'bg-green-500'  },
        rejected:    { label: 'Rejected', cls: 'bg-red-50 text-red-700',      dot: 'bg-red-500'    },
    };

    tbody.innerHTML = rows.map(s => {
        const date  = s.created_at ? new Date(s.created_at).toLocaleDateString('en-GB', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric' }) : '—';
        const short = s.description ? (s.description.length > 65 ? s.description.substring(0, 65) + '…' : s.description) : '—';

        const pCfg = prioCfg[s.ticket_priority];
        const prioBadge = pCfg
            ? badge(s.ticket_priority, pCfg.cls, pCfg.dot)
            : `<span class="text-gray-300 text-xs">—</span>`;

        const chBadge = s.channel === 'email'
            ? badge('<i class="fas fa-envelope text-[9px]"></i>&nbsp;Email', 'bg-blue-50 text-blue-700')
            : badge('<i class="fas fa-globe text-[9px]"></i>&nbsp;Web',   'bg-gray-100 text-gray-600');

        const sCfg = statusCfg[s.status] ?? { label: s.status, cls: 'bg-gray-100 text-gray-500', dot: 'bg-gray-400' };
        const statusHtml = badge(sCfg.label, sCfg.cls, sCfg.dot)
            + (s.status === 'approved' && s.ticket_number
                ? `<br><a href="/ticket/${s.ticket_id}" class="text-[10px] text-green-600 hover:underline font-mono mt-0.5 inline-block">${escHtml(s.ticket_number)}</a>`
                : '');

        const actionBtn = s.status === 'unvalidated'
            ? `<button onclick="openModal(${s.id})"
                       class="inline-flex items-center gap-1 px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all">
                   <i class="fas fa-clipboard-check text-[10px]"></i>Validate
               </button>`
            : `<button onclick="openModal(${s.id})"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all">
                   <i class="fas fa-eye text-[10px]"></i>Detail
               </button>`;

        const senderDisplay = s.customer_name ?? s.sender_name ?? 'Unknown';

        return `<tr class="hover:bg-gray-50/60 transition-colors cursor-pointer">
            <td class="px-3 py-3 text-gray-400 font-mono text-xs whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 text-[11px] font-bold text-gray-500">#${s.id}</span>
            </td>
            <td class="px-3 py-3 whitespace-nowrap">
                <p class="text-sm font-semibold text-gray-800 leading-snug">${escHtml(senderDisplay)}</p>
                ${s.end_customer_name ? `<p class="text-[10px] text-gray-400 mt-0.5">↳ ${escHtml(s.end_customer_name)}</p>` : ''}
                ${s.submitted_by_email ? `<p class="text-[10px] text-gray-400">${escHtml(s.submitted_by_email)}</p>` : ''}
            </td>
            <td class="px-3 py-3 text-sm text-gray-600" style="max-width:320px">
                <span class="block truncate" title="${escHtml(s.description ?? '')}">${escHtml(short)}</span>
            </td>
            <td class="px-3 py-3 whitespace-nowrap">${prioBadge}</td>
            <td class="px-3 py-3 whitespace-nowrap">${chBadge}</td>
            <td class="px-3 py-3 whitespace-nowrap">${statusHtml}</td>
            <td class="px-3 py-3 whitespace-nowrap"><span class="text-xs text-gray-500">${date}</span></td>
            <td class="px-3 py-3 whitespace-nowrap">${actionBtn}</td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    const area = document.getElementById('paginationArea');
    if (!meta.total || meta.last_page <= 1) { area.classList.add('hidden'); area.classList.remove('flex'); return; }
    area.classList.remove('hidden');
    area.classList.add('flex');
    document.getElementById('pageInfo').textContent =
        `${Math.min((currentPage-1)*meta.per_page+1, meta.total)}–${Math.min(currentPage*meta.per_page, meta.total)} of ${meta.total}`;
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
    _stagingDsSelected = { id: null, name: '' };
    document.getElementById('stagingModal').style.display = 'flex';
    document.getElementById('modalStagingId').textContent  = `Staging #${id}`;
    document.getElementById('modalStatusBadge').innerHTML  = '';
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

    // ── Helpers ──
    const dateStr = s.created_at
        ? new Date(s.created_at).toLocaleString('en-GB', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:false }) + ' WIB'
        : '—';

    const statusMap = {
        unvalidated: { cls: 'bg-amber-100 text-amber-700',  icon: 'fas fa-clock',       label: 'Pending Validation' },
        approved:    { cls: 'bg-green-100 text-green-700',  icon: 'fas fa-check-circle', label: 'Approved' },
        rejected:    { cls: 'bg-red-100 text-red-700',      icon: 'fas fa-times-circle', label: 'Rejected' },
    };
    const st    = statusMap[s.status] ?? { cls: 'bg-gray-100 text-gray-600', icon: 'fas fa-question-circle', label: s.status };

    // Set status badge in header
    const badge = document.getElementById('modalStatusBadge');
    if (badge) badge.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${st.cls}"><i class="${st.icon}"></i>${st.label}</span>`;

    const prioColors = { 'Very High': 'bg-purple-100 text-purple-700', High: 'bg-red-100 text-red-700', Medium: 'bg-blue-100 text-blue-700', Low: 'bg-green-100 text-green-700' };
    const typeColors = { Incident: 'bg-red-50 text-red-600 border-red-200', 'Change Request': 'bg-amber-50 text-amber-600 border-amber-200', 'Service Request': 'bg-indigo-50 text-indigo-600 border-indigo-200', EWA: 'bg-orange-50 text-orange-600 border-orange-200', RISE: 'bg-violet-50 text-violet-600 border-violet-200', Consult: 'bg-teal-50 text-teal-600 border-teal-200', Internal: 'bg-slate-100 text-slate-600 border-slate-200' };

    // ── Parse CC ──
    let ccDisplay = '';
    if (s.cc_emails) {
        try {
            const ccParsed = typeof s.cc_emails === 'string' ? JSON.parse(s.cc_emails) : s.cc_emails;
            if (Array.isArray(ccParsed)) {
                ccDisplay = ccParsed.map(e => typeof e === 'object' && e.address
                    ? escHtml((e.name && e.name !== e.address) ? `${e.name} <${e.address}>` : e.address)
                    : escHtml(String(e))
                ).join(', ');
            }
        } catch { ccDisplay = escHtml(String(s.cc_emails)); }
    }

    // ── Meta strip (unified for email & web) ──
    const metaRow = (label, value) =>
        `<tr class="border-b border-gray-100 last:border-0">
            <td class="px-5 py-2.5 text-xs font-semibold text-gray-400 whitespace-nowrap w-24 align-top">${label}</td>
            <td class="px-5 py-2.5 text-sm text-gray-800">${value}</td>
        </tr>`;

    let metaHtml = '';
    if (isEmail) {
        const fromDisplay = s.sender_name
            ? `<span class="font-semibold">${escHtml(s.sender_name)}</span> <span class="text-gray-400 text-xs">&lt;${escHtml(s.submitted_by_email ?? '')}&gt;</span>`
            : `<span class="text-gray-700">${escHtml(s.submitted_by_email ?? '—')}</span>`;
        const rows = [
            metaRow('From', fromDisplay),
            metaRow('Date', `<span class="text-gray-500 text-xs">${dateStr}</span>`),
            ccDisplay ? metaRow('CC', `<span class="text-gray-600 text-xs">${ccDisplay}</span>`) : '',
            metaRow('Subject', `<span class="font-semibold">${escHtml(s.description ?? '—')}</span>`),
        ].join('');
        metaHtml = `<div class="border border-gray-200 rounded-xl overflow-hidden mb-5"><table class="w-full">${rows}</table></div>`;
    } else {
        const custName = s.customer_name ?? s.sender_name ?? '—';
        const senderEmail = s.submitted_by_email
            ? ` <span class="text-gray-400 text-xs">&lt;${escHtml(s.submitted_by_email)}&gt;</span>` : '';
        const unidentified = !s.customer_name && s.submitted_by_email
            ? ' <span class="text-amber-500 text-xs">(unidentified)</span>' : '';
        const extraFields = [
            s.no_hp  ? ['Phone',  s.no_hp]  : null,
            s.module ? ['Module', s.module] : null,
            s.client ? ['Client', s.client] : null,
        ].filter(Boolean);
        const rows = [
            metaRow('From', `<span class="font-semibold">${escHtml(custName)}</span>${senderEmail}${unidentified}`),
            metaRow('Date', `<span class="text-gray-500 text-xs">${dateStr}</span>`),
            ccDisplay ? metaRow('CC', `<span class="text-gray-600 text-xs">${ccDisplay}</span>`) : '',
            metaRow('Subject', `<span class="font-semibold">${escHtml(s.description ?? '—')}</span>`),
            ...extraFields.map(([k,v]) => metaRow(k, escHtml(v))),
        ].join('');
        metaHtml = `<div class="border border-gray-200 rounded-xl overflow-hidden mb-5"><table class="w-full">${rows}</table></div>`;
    }

    // ── Message body (email iframe OR web body/description) ──
    // Both channels use an iframe for consistent rendering.
    // hasEmailSource = true jika ticket ini punya email di Graph (channel email ATAU web + graph_message_id).
    // Dalam kasus ini, body ditampilkan via previewBody agar inline images bisa di-resolve.
    const hasEmailSource = isEmail || !!(s.graph_message_id);
    const hasEmailBody   = !!(s.email_body_html);
    let contentHtml = '';
    const bodySource = hasEmailBody ? s.email_body_html : (s.body || null);
    const bodyLabel  = hasEmailSource ? 'Email Body' : 'Message Body';

    if (bodySource || (hasEmailSource && s.graph_message_id)) {
        contentHtml = `<div class="border border-gray-200 rounded-xl overflow-hidden mb-5">
            <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-envelope-open text-gray-400 text-xs"></i>
                <span class="text-xs font-semibold text-gray-500">${bodyLabel}</span>
            </div>
            <iframe id="emailBodyIframe" sandbox="allow-same-origin"
                    class="w-full" style="min-height:280px;border:none;display:block" title="${bodyLabel}"></iframe>
        </div>`;
    } else {
        // Fallback: description as plain text (when no body stored)
        contentHtml = `<div class="border border-gray-200 rounded-xl overflow-hidden mb-5">
            <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-align-left text-gray-400 text-xs"></i>
                <span class="text-xs font-semibold text-gray-500">Description</span>
            </div>
            <div class="px-4 py-4 text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">${escHtml(s.description ?? '—')}</div>
        </div>`;
    }

    // ── Attachments (web uploads from Jarvies local storage) ──
    let attachmentsHtml = '';
    const webAttachments = Array.isArray(s.attachments) ? s.attachments : [];
    if (webAttachments.length > 0) {
        attachmentsHtml = buildAttachmentsBlock(webAttachments, 'local');
    } else if (s.graph_message_id) {
        // Placeholder — email attachments loaded lazily after DOM insert
        attachmentsHtml = `<div id="emailAttachmentsBlock" class="border border-gray-200 rounded-xl overflow-hidden mb-5">
            <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-paperclip text-gray-400 text-xs"></i>
                <span class="text-xs font-semibold text-gray-500">Attachments</span>
            </div>
            <div class="px-4 py-3 text-xs text-gray-400 flex items-center gap-2">
                <i class="fas fa-spinner fa-spin"></i> Loading attachments…
            </div>
        </div>`;
    }

    // ── Validation panel ──
    // Delivery support milik customer tiket ini. Aturan bisnis:
    //   • customer punya ≥1 delivery support  → WAJIB dipilih (tak ada opsi kosong)
    //   • tepat 1                             → langsung terisi otomatis
    //   • tidak punya sama sekali             → field dikunci & approve tetap boleh
    const dsOptions  = DELIVERY_SUPPORTS.filter(ds => ds.client_id == s.customer_id);
    const dsRequired = dsOptions.length > 0;

    // Reset pilihan setiap kali modal dibuka supaya tidak bocor dari staging sebelumnya.
    _stagingDsSelected = { id: null, name: '' };

    let validationHtml = '';
    if (isUnvalidated) {
        validationHtml = `
        ${aiAnalysisPanelHtml(s)}
        <div class="border border-gray-200 rounded-xl overflow-hidden mb-5">
            <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
                <i class="fas fa-clipboard-check text-gray-500 text-xs"></i>
                <span class="text-xs font-semibold text-gray-600">Ticket Classification</span>
                <span class="text-xs text-gray-400 ml-1">— required before approving</span>
            </div>
            <div class="px-4 py-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Type <span class="text-red-500">*</span></label>
                    <select id="approveTicketType"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all">
                        ${buildOptionsHtml(TICKET_TYPES, 'Select type…')}
                    </select>
                    <p id="typeError" class="hidden mt-1 text-xs text-red-500">Required.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Priority <span class="text-red-500">*</span></label>
                    <select id="approvePriority"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all">
                        ${buildOptionsHtml(TICKET_PRIORITIES, 'Select priority…')}
                    </select>
                    <p id="priorityError" class="hidden mt-1 text-xs text-red-500">Required.</p>
                </div>
                {{-- Scale: opsional. --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Scale</label>
                    <select id="approveScale"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all">
                        ${buildOptionsHtml(TICKET_SCALES, 'Select scale…')}
                    </select>
                    <p class="mt-1 text-[11px] text-gray-400">Optional</p>
                </div>
            </div>
            <div class="border-t border-gray-100 px-4 pt-3 pb-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Delivery Support
                    ${dsRequired
                        ? '<span class="text-red-500">*</span>'
                        : '<span class="text-gray-400 font-normal">(no delivery support registered for this customer)</span>'}
                </label>
                <input type="hidden" id="stagingDsHidden" value="">
                <div id="stagingDsDd" class="relative">
                    <input type="text" id="stagingDsSearch"
                        placeholder="${dsRequired ? 'Select delivery support…' : 'Not available for this customer'}"
                        autocomplete="off"
                        ${dsRequired ? '' : 'disabled'}
                        oninput="filterStagingDs(this.value)"
                        onfocus="openStagingDsDd()"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm ${dsRequired ? 'bg-white' : 'bg-gray-50 text-gray-400 cursor-not-allowed'} focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all">
                    <div id="stagingDsPanel" class="hidden absolute top-full left-0 right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-y-auto" style="max-height:200px;">
                        ${dsOptions.map(ds =>
                            '<button type="button" class="staging-ds-opt w-full text-left px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition" ' +
                            'onclick="selectStagingDs(this.dataset.id, this.dataset.name)" ' +
                            'data-id="' + ds.id + '" data-name="' + escHtml(ds.name) + '">' + escHtml(ds.name) + '</button>'
                        ).join('')}
                    </div>
                </div>
                <p id="dsError" class="hidden mt-1 text-xs text-red-500">Delivery support is required.</p>
                ${dsRequired && dsOptions.length > 1
                    ? '<p class="mt-1 text-[11px] text-gray-400">This customer has ' + dsOptions.length + ' delivery supports — choose the right one.</p>'
                    : ''}
            </div>
            <div id="forCustomerWrap" class="hidden border-t border-gray-100 px-4 pt-3 pb-3">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">For customer <span class="text-gray-400 font-normal">(end-customer under this parent)</span></label>
                <select id="approveEndCustomer"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all">
                    <option value="">— On behalf of the parent itself —</option>
                </select>
                <p class="mt-1 text-[11px] text-gray-400">This email was routed to the parent customer. Choose which end-customer it is actually for.</p>
            </div>
            <div class="border-t border-gray-100 px-4 pt-3 pb-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Additional Info <span class="font-normal normal-case">(optional)</span></p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name</label>
                        <input type="text" id="approveName" maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all"
                               placeholder="Contact person name">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">No HP</label>
                        <input type="text" id="approveNoHp" maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all"
                               placeholder="Phone number">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Module</label>
                        <input type="text" id="approveModule" maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all"
                               placeholder="Related module">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Client</label>
                        <input type="text" id="approveClient" maxlength="255"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-gray-800 focus:border-transparent transition-all"
                               placeholder="Client name">
                    </div>
                </div>
            </div>
        </div>`;
    } else if (s.ticket_type || s.ticket_priority || s.scale) {
        const typePill  = s.ticket_type     ? `<span class="px-2.5 py-1 rounded-full text-xs font-semibold border ${typeColors[s.ticket_type]  ?? 'bg-gray-50 text-gray-600 border-gray-200'}">${escHtml(s.ticket_type)}</span>`     : '';
        const prioPill  = s.ticket_priority ? `<span class="px-2.5 py-1 rounded-full text-xs font-semibold ${prioColors[s.ticket_priority] ?? 'bg-gray-100 text-gray-600'}">${escHtml(s.ticket_priority)}</span>` : '';
        const scalePill = s.scale           ? `<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">Scale: ${escHtml(s.scale)}</span>` : '';
        const ticketLink = s.ticket_number  ? `<a href="/ticket/${s.ticket_id}" class="inline-flex items-center gap-1 text-xs font-semibold text-red-700 hover:underline ml-auto"><i class="fas fa-external-link-alt text-[10px]"></i> Ticket ${escHtml(s.ticket_number)}</a>` : '';
        validationHtml  = `
        <div class="flex items-center gap-2.5 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl mb-5 text-xs">
            ${typePill}${prioPill}${scalePill}${ticketLink}
        </div>`;
    }

    if (s.status === 'rejected' && s.rejection_reason) {
        validationHtml += `<div class="flex gap-2.5 px-4 py-3 bg-red-50 border border-red-200 rounded-xl mb-5 text-xs">
            <i class="fas fa-times-circle text-red-400 mt-0.5 shrink-0"></i>
            <div><span class="font-semibold text-red-600">Rejection reason: </span><span class="text-red-700">${escHtml(s.rejection_reason)}</span></div>
        </div>`;
    }

    // ── Reject reason area ──
    const rejectAreaHtml = `
    <div id="rejectReasonArea" class="hidden mb-5">
        <div class="border border-red-200 rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 bg-red-50 border-b border-red-200 flex items-center gap-2">
                <i class="fas fa-times-circle text-red-500 text-xs"></i>
                <span class="text-xs font-semibold text-red-700">Reason for Rejection <span class="text-red-500">*</span></span>
            </div>
            <div class="p-4">
                <textarea id="rejectReasonInput" rows="3"
                          placeholder="Describe why this ticket is being rejected…"
                          class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent transition-all resize-none"></textarea>
                <p id="rejectReasonError" class="hidden mt-1.5 text-xs text-red-500"></p>
            </div>
        </div>
    </div>`;

    // ── Assemble body ──
    document.getElementById('modalBody').innerHTML =
        metaHtml + validationHtml + rejectAreaHtml + contentHtml + attachmentsHtml;

    // ── Lazy-load email attachments from Graph if no local attachments ──
    if (!webAttachments.length && s.graph_message_id) {
        loadEmailAttachments(s.id);
    }

    // Set iframe srcdoc after DOM is updated (works for both email and web body)
    if (bodySource) {
        const iframe = document.getElementById('emailBodyIframe');
        if (iframe) {
            const setIframeContent = (html) => {
                if (!html) return; // guard: jangan panggil startsWith pada null/undefined
                // Wrap bare text/HTML in basic styling for consistent look
                const wrapped = html.startsWith('<') ? html
                    : `<div style="font-family:system-ui,sans-serif;font-size:14px;color:#374151;padding:4px;white-space:pre-wrap">${html}</div>`;
                iframe.srcdoc = wrapped;
                iframe.addEventListener('load', () => {
                    try {
                        const h = iframe.contentDocument?.documentElement?.scrollHeight
                               ?? iframe.contentDocument?.body?.scrollHeight;
                        if (h && h > 100) {
                            iframe.style.minHeight = Math.min(h + 24, 600) + 'px';
                        }
                    } catch {}
                }, { once: true });
            };

            // For email: resolve inline images via Graph API (cid: → base64 data URI)
            // For web: resolve cid:img-N@jarvies references from staging_attachments list
            const needsEmailImageResolve = isEmail && s.graph_message_id && (
                (s.email_body_html || '').includes('cid:') ||
                s.has_attachments ||
                /\[[^\]]+\.(png|jpe?g|gif|bmp|webp)\]/i.test(s.email_body_html || '')
            );
            if (needsEmailImageResolve && s.id) {
                setIframeContent(bodySource); // show immediately while loading (pakai bodySource, bukan s.email_body_html yang bisa null)
                fetch(`/api/staging-tickets/${s.id}/preview-body`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                }).then(r => r.json()).then(data => {
                    if (data.success && data.html) setIframeContent(data.html);
                }).catch(() => {});
            } else if (!isEmail && bodySource && bodySource.includes('cid:')) {
                // Web channel: replace cid:img-N@jarvies with actual attachment public URLs
                const imageAtts = (s.attachments || []).filter(a => a.mime_type?.startsWith('image/'));
                let resolved = bodySource.replace(/src="cid:img-(\d+)@jarvies"/gi, (match, n) => {
                    const att = imageAtts[parseInt(n, 10) - 1];
                    return att ? `src="${att.url}"` : match;
                });
                setIframeContent(resolved);
            } else {
                setIframeContent(bodySource);
            }
        }
    }

    // ── Footer buttons ──
    renderFooter(s);

    // ── Pre-fill additional info fields (if staging already has data) ──
    if (isUnvalidated) {
        const prefill = [
            ['approveName',   s.name   ?? ''],
            ['approveNoHp',   s.no_hp  ?? ''],
            ['approveModule', s.module ?? ''],
            ['approveClient', s.client ?? ''],
        ];
        prefill.forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        });
        // Pre-select type if staging already has a value
        if (s.ticket_type) {
            const typeEl = document.getElementById('approveTicketType');
            if (typeEl) typeEl.value = s.ticket_type;
        }
        // Pre-select priority if staging already has a value
        if (s.ticket_priority) {
            const prioEl = document.getElementById('approvePriority');
            if (prioEl) prioEl.value = s.ticket_priority;
        }
        // Pre-select scale if staging already has a value
        if (s.scale) {
            const scaleEl = document.getElementById('approveScale');
            if (scaleEl) scaleEl.value = s.scale;
        }

        // Delivery support: kalau customer hanya punya satu, langsung pilihkan —
        // validator tetap jalan, tapi user tak perlu memilih manual.
        if (dsOptions.length === 1) {
            selectStagingDs(dsOptions[0].id, dsOptions[0].name);
        }

        // Analisa AI: otomatis sekali saat modal dibuka pertama kali, admin
        // bisa memicu ulang manual lewat tombol Re-analyze (lihat komentar di
        // aiAnalysisPanelHtml()). Kalau sudah ada hasil tersimpan, isi field
        // yang masih kosong. Kalau belum pernah dicoba sama sekali (status
        // null), picu sekarang — panel sudah terlanjur nampilin state loading
        // dari aiAnalysisPanelHtml() di atas. Status 'pending'/'failed' TIDAK
        // memicu apa pun di sini secara otomatis (lihat catatan di aiAnalysisPanelHtml()).
        _lastAiAnalysis = s.ai_analysis || null;
        if (s.ai_analysis) {
            autoFillEmptyFromAi(s.ai_analysis);
        } else if (!s.ai_analysis_status) {
            runAiAnalysis(s.id);
        }

        // ── "For customer": tampil hanya jika customer ter-match adalah parent
        //    yang punya end-customers (kasus tiket email di-route via domain). ──
        if (s.customer_id) {
            loadForCustomerOptions(s.customer_id, s.end_customer_id);
        }
    }
}

// ─── AI Ticket Analyzer ───────────────────────────────────────────────────────

// Analisa AI otomatis dipicu saat modal validasi dibuka (fillModal() →
// runAiAnalysis()) — tapi admin juga bisa memicu ulang secara sengaja lewat
// tombol "Re-analyze" (muncul begitu ada hasil atau status failed) yang
// memanggil runAiAnalysis(id, true).
//
// Urutan prioritas SENGAJA begini, bukan sekadar dua flag independen:
//   1. status === 'pending'  → SEDANG berjalan (request ini atau tab/admin
//      lain) — SELALU tampil loading & tombol Re-analyze disembunyikan,
//      APAPUN isi s.ai_analysis. Awalnya urutan ini kebalik (hasResult
//      dicek duluan) — akibatnya re-analyze pada tiket yang sudah punya
//      hasil lama tetap menampilkan hasil lama itu SELAMA request berjalan,
//      dan tombol Re-analyze tetap bisa diklik. User yang tidak melihat
//      indikasi apa pun sedang terjadi lalu klik dua kali, dan klik kedua
//      itu yang menabrak klaim atomic pertama (lihat
///     StagingTicketController::analyze()) dengan pesan "already running".
//   2. s.ai_analysis ada     → hasil tersedia (status completed, ATAU staging
//      lama dari sebelum kolom ai_analysis_status ditambahkan — migration
//      2026_08_24_000002 — yang punya ai_analysis terisi tapi status masih
//      NULL, lihat staging #316).
//   3. status === 'failed'   → gagal, tombol Re-analyze tersedia.
//   4. lainnya (null, belum ada hasil) → belum dicoba, akan dipicu fillModal.
function aiAnalysisPanelHtml(s) {
    const status = s.ai_analysis_status || null;
    const isRunning = 'pending' === status;
    const hasResult = !isRunning && !!s.ai_analysis;
    const timeNote = (hasResult && s.ai_analysis_generated_at)
        ? `<span class="text-[11px] text-gray-400 ml-2">Analyzed ${timeAgo(s.ai_analysis_generated_at)}</span>`
        : '';
    const reanalyzeBtn = (hasResult || status === 'failed')
        ? `<button type="button" onclick="runAiAnalysis(${s.id}, true)" class="ml-auto text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">
               <i class="fas fa-rotate-right text-[10px]"></i> Re-analyze
           </button>`
        : '';

    let bodyHtml;
    if (hasResult) {
        bodyHtml = renderAiAnalysisBody(s.ai_analysis);
    } else if ('failed' === status) {
        bodyHtml = `<p class="text-sm text-red-600"><i class="fas fa-circle-exclamation"></i> AI analysis failed for this ticket. Click Re-analyze to try again, or fill in the classification (Type/Priority/Scale/Module) manually.</p>`;
    } else {
        // null (belum dicoba, akan dipicu fillModal) atau pending (sedang jalan).
        // Progress bar-nya ESTIMASI (bukan progress asli dari provider — API
        // ticket analysis bukan streaming), diisi lewat startAiProgressAnimation()
        // yang jalan di luar re-render ini supaya tidak ke-reset tiap kali panel
        // di-render ulang.
        bodyHtml = `
            <div class="flex items-center gap-2 text-sm text-gray-400 py-1"><i class="fas fa-spinner fa-spin"></i> Analyzing ticket…</div>
            <div class="w-full h-1.5 bg-indigo-100 rounded-full overflow-hidden mt-2">
                <div id="aiAnalysisProgressFill" class="h-full bg-indigo-500 transition-all duration-300 ease-out" style="width:2%"></div>
            </div>`;
    }

    return `
    <div class="border border-indigo-200 rounded-xl overflow-hidden mb-5 bg-indigo-50/40">
        <div class="px-4 py-2.5 border-b border-indigo-100 bg-indigo-50 flex items-center gap-2">
            <i class="fas fa-wand-magic-sparkles text-indigo-500 text-xs"></i>
            <span class="text-xs font-semibold text-indigo-700">AI Ticket Analyzer</span>
            ${timeNote}
            ${reanalyzeBtn}
        </div>
        <div id="aiAnalysisBody" class="px-4 py-4">
            ${bodyHtml}
        </div>
    </div>`;
}

// Satu pola visual dipakai berulang di semua section supaya konsisten
// (sebelumnya tiap section pakai ukuran/struktur beda-beda — itu yang bikin
// panel ini terlihat berantakan).
const AI_LABEL_CLS = 'text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5';
const AI_BODY_CLS  = 'text-sm text-gray-700 leading-relaxed';
const AI_SECTION_CLS = 'pt-3 mt-3 border-t border-indigo-100/70 first:pt-0 first:mt-0 first:border-0';

function aiSection(label, innerHtml) {
    return `<div class="${AI_SECTION_CLS}">
        <p class="${AI_LABEL_CLS}">${label}</p>
        ${innerHtml}
    </div>`;
}

function renderAiAnalysisBody(data) {
    const confidencePct = (data.confidence !== null && data.confidence !== undefined)
        ? Math.round(data.confidence * 100) : null;

    // ── Ringkasan saran klasifikasi + confidence, sebagai chip di paling atas ──
    const chip = (text, cls) => text
        ? `<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold border ${cls}">${escHtml(text)}</span>` : '';
    const chipsHtml = [
        chip(data.suggested_ticket_type, 'bg-white text-gray-600 border-gray-200'),
        chip(data.suggested_priority,    'bg-white text-gray-600 border-gray-200'),
        chip(data.suggested_scale,       'bg-white text-gray-600 border-gray-200'),
        chip(data.suggested_module_name ? `Module: ${data.suggested_module_name}` : '', 'bg-white text-gray-600 border-gray-200'),
        confidencePct !== null ? chip(`Confidence ${confidencePct}%`, 'bg-indigo-600 text-white border-indigo-600') : '',
    ].filter(Boolean).join('');

    // ── Langkah penyelesaian: nomor bulat + teks, semua text-sm konsisten ──
    const steps = data.resolution_steps || [];
    const stepsHtml = steps.length
        ? `<ol class="space-y-2">${steps.map((step, i) => `
            <li class="flex gap-2.5">
                <span class="shrink-0 w-5 h-5 mt-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[11px] font-bold flex items-center justify-center">${i + 1}</span>
                <span class="${AI_BODY_CLS}">${escHtml(step)}</span>
            </li>`).join('')}</ol>`
        : `<p class="${AI_BODY_CLS} text-gray-400">No specific steps provided by AI.</p>`;

    // ── Risiko/catatan ──
    const risks = data.risks || [];
    const risksHtml = risks.length
        ? `<ul class="space-y-1.5">${risks.map(r => `
            <li class="flex gap-2 text-sm text-amber-800 leading-relaxed">
                <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5 text-xs shrink-0"></i>
                <span>${escHtml(r)}</span>
            </li>`).join('')}</ul>`
        : '';

    // ── Suggested assignee: selalu tampil, dengan empty-state yang jelas ──
    const assignees = data.suggested_assignees || [];
    let assigneesInner;
    if (assignees.length) {
        assigneesInner = `<div class="space-y-1.5">
            ${assignees.map(a => {
                const wp = Math.round(a.workload_pct ?? 0);
                const wColor = a.warning ? 'text-red-600' : (wp >= 50 ? 'text-amber-600' : 'text-green-600');
                return `<div class="flex items-center gap-2 text-sm bg-white border border-gray-200 rounded-lg px-3 py-2">
                    <span class="font-semibold text-gray-800">${escHtml(a.name)}</span>
                    <span class="text-gray-400 text-xs">(${escHtml(a.eci ?? '-')})</span>
                    ${a.is_module_lead ? '<span class="px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-semibold">Module Lead</span>' : ''}
                    ${a.qualification_level ? `<span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-[10px] font-semibold">${escHtml(a.qualification_level)}</span>` : ''}
                    <span class="ml-auto font-semibold text-xs ${wColor}">${wp}% workload</span>
                    ${a.warning ? `<i class="fas fa-triangle-exclamation text-red-500 text-xs" title="${escHtml(a.warning_message || '')}"></i>` : ''}
                </div>`;
            }).join('')}
        </div>`;
    } else if (data.suggested_module_name) {
        assigneesInner = `<p class="text-sm text-gray-400">No Module Lead or certified consultant registered in the system for module <span class="font-semibold text-gray-500">${escHtml(data.suggested_module_name)}</span>.</p>`;
    } else {
        assigneesInner = `<p class="text-sm text-gray-400">AI did not identify a clear module, so no assignee suggestion is available.</p>`;
    }

    return `
        ${chipsHtml ? `<div class="flex flex-wrap items-center gap-1.5 mb-3">${chipsHtml}</div>` : ''}
        ${aiSection('Overview', `<p class="${AI_BODY_CLS}">${escHtml(data.overview || '-')}</p>`)}
        ${data.root_cause_hypothesis ? aiSection('Root Cause Hypothesis', `<p class="${AI_BODY_CLS}">${escHtml(data.root_cause_hypothesis)}</p>`) : ''}
        ${aiSection('Resolution Steps', stepsHtml)}
        ${risks.length ? aiSection('Notes / Risks', risksHtml) : ''}
        ${aiSection('Suggested Assignee', assigneesInner)}
        <div class="pt-3 mt-3 border-t border-indigo-100/70">
            <button type="button" onclick="applyAiSuggestions()" class="text-xs font-semibold text-indigo-700 hover:underline">
                <i class="fas fa-arrow-turn-down text-[10px]"></i> Apply suggestion to form
            </button>
        </div>`;
}

// Dipanggil OTOMATIS dari fillModal() saat modal validasi dibuka untuk tiket
// yang belum pernah dianalisa, dan juga dipanggil MANUAL dari tombol
// "Re-analyze" di panel (force=true) untuk memicu ulang setelah completed/
// failed. Endpoint-nya sendiri yang menegakkan aturan klaim (lihat
// StagingTicketController::analyze()) — force cuma dikirim sebagai niat,
// server tetap menolak kalau ternyata sedang 'pending' di request lain.
//
// _aiAnalysisInFlight menjaga tab INI sendiri tidak menembak dua request
// sekaligus buat tiket yang sama — insiden nyata: staging #316 dan #317
// sama-sama sukses dianalisa (ada di audit log), TAPI ai_analysis_status
// balik lagi ke 'pending' segera sesudahnya dan macet berjam-jam, pola yang
// paling cocok dengan klik Re-analyze kedua menembak SELAGI klik pertama
// masih berjalan (server sisi klaim atomic tetap benar — masalahnya baris
// ini yang membiarkan tab yang sama memicu request kedua sama sekali).
// Ini pelengkap, bukan pengganti, klaim atomic & stale-reclaim di server:
// kalau tab LAIN atau admin lain yang memicu bersamaan, itu tetap ditangani
// di sana.
let _aiAnalysisInFlight = null;

async function runAiAnalysis(id, force = false) {
    if (_aiAnalysisInFlight === id) {
        return;
    }
    _aiAnalysisInFlight = id;

    if (currentStagingData && currentStagingData.id === id) {
        currentStagingData.ai_analysis_status = 'pending';
    }
    refreshAiAnalysisPanel(id);
    startAiProgressAnimation();

    try {
        const res = await apiFetch(`/api/staging-tickets/${id}/analyze`, 'POST', force ? { force: true } : null);
        _lastAiAnalysis = res.data;
        if (currentStagingData && currentStagingData.id === id) {
            currentStagingData.ai_analysis = res.data;
            currentStagingData.ai_analysis_status = 'completed';
            currentStagingData.ai_analysis_generated_at = new Date().toISOString();
        }
        autoFillEmptyFromAi(res.data);

        // Snap progress bar ke 100% sekilas sebelum panel di-render ulang
        // dengan hasil sebenarnya — cuma flourish visual, tidak mempengaruhi
        // data (progress-nya sendiri sudah selalu estimasi, bukan real).
        stopAiProgressAnimation();
        const fillEl = document.getElementById('aiAnalysisProgressFill');
        if (fillEl) fillEl.style.width = '100%';
        await new Promise(resolve => setTimeout(resolve, 200));
        refreshAiAnalysisPanel(id);
    } catch (e) {
        stopAiProgressAnimation();
        if (currentStagingData && currentStagingData.id === id) {
            currentStagingData.ai_analysis_status = 'failed';
        }
        // Refresh dulu (supaya header ikut ter-render ulang — kalau tidak,
        // tombol Re-analyze tetap hilang karena disembunyikan saat status
        // sempat 'pending' di atas), baru timpa isi body dengan pesan
        // spesifik dari server (mis. rate limit/config) alih-alih pesan
        // generik "gagal" dari aiAnalysisPanelHtml().
        refreshAiAnalysisPanel(id);
        const freshBody = document.getElementById('aiAnalysisBody');
        if (freshBody) freshBody.innerHTML = `<p class="text-sm text-red-600"><i class="fas fa-circle-exclamation"></i> ${escHtml(e.message || 'AI analysis failed. Please fill in the classification manually.')}</p>`;
    } finally {
        // WAJIB finally, bukan ditaruh lepas di akhir try/catch: harus tetap
        // kelepas apa pun jalur keluarnya (sukses, gagal, atau exception tak
        // terduga lain), atau tab ini tidak akan pernah bisa memicu
        // analyze() lagi buat tiket ini sampai halaman di-reload.
        _aiAnalysisInFlight = null;
    }
}

// Progress bar ESTIMASI (bukan progress asli dari provider — API ticket
// analysis bukan streaming, jadi tidak ada angka progress nyata untuk
// ditampilkan). Naik cepat di awal lalu melambat mendekati asimtot 92%
// (kurva 1 - e^-t/tau) supaya tidak pernah terlihat "selesai" sebelum
// respons beneran datang — endpoint-nya sendiri biasanya makan belasan
// detik sampai ±1 menit tergantung effort/model yang aktif di AI Settings.
// Interval jalan lepas dari siklus render aiAnalysisPanelHtml() supaya
// tidak ke-reset tiap panel di-render ulang — cukup update elemen fill
// lewat getElementById tiap tick, dan diam kalau elemennya sudah tidak ada
// (modal ditutup/di-render ulang ke state lain).
function startAiProgressAnimation() {
    stopAiProgressAnimation();
    _aiProgressStart = Date.now();

    const TAU_MS = 18000;
    const CAP_PCT = 92;

    const tick = () => {
        const el = document.getElementById('aiAnalysisProgressFill');
        if (!el) return;
        const elapsed = Date.now() - _aiProgressStart;
        const pct = CAP_PCT * (1 - Math.exp(-elapsed / TAU_MS));
        el.style.width = pct.toFixed(1) + '%';
    };

    tick();
    _aiProgressTimer = setInterval(tick, 200);
}

function stopAiProgressAnimation() {
    if (_aiProgressTimer) {
        clearInterval(_aiProgressTimer);
        _aiProgressTimer = null;
    }
}

// Render ulang seluruh panel (header dengan tombol Re-analyze + body) —
// dipakai setelah status berubah supaya tombol Re-analyze langsung
// tampil/hilang sesuai status terbaru, bukan cuma isi body-nya.
function refreshAiAnalysisPanel(id) {
    if (!currentStagingData || currentStagingData.id !== id) return;
    const panel = document.getElementById('aiAnalysisBody')?.closest('div.border-indigo-200');
    if (panel) panel.outerHTML = aiAnalysisPanelHtml(currentStagingData);
}

// Isi field klasifikasi HANYA yang masih kosong — tidak menimpa input admin.
function autoFillEmptyFromAi(data) {
    [
        ['approveTicketType', data.suggested_ticket_type],
        ['approvePriority',   data.suggested_priority],
        ['approveScale',      data.suggested_scale],
        ['approveModule',     data.suggested_module_name],
    ].forEach(([id, val]) => {
        if (!val) return;
        const el = document.getElementById(id);
        if (el && !el.value) el.value = val;
    });
}

// Paksa terapkan saran AI terakhir ke form (dipanggil manual dari panel).
function applyAiSuggestions() {
    if (!_lastAiAnalysis) return;
    const d = _lastAiAnalysis;
    const setVal = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
    setVal('approveTicketType', d.suggested_ticket_type);
    setVal('approvePriority',   d.suggested_priority);
    setVal('approveScale',      d.suggested_scale);
    setVal('approveModule',     d.suggested_module_name);
    showNotif('AI suggestions applied to form.', 'success');
}

function timeAgo(iso) {
    if (!iso) return '';
    const diffSec = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diffSec < 60) return 'just now';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + ' min ago';
    if (diffSec < 86400) return Math.floor(diffSec / 3600) + ' hr ago';
    return Math.floor(diffSec / 86400) + ' d ago';
}

async function loadForCustomerOptions(parentId, selectedEndCustomerId) {
    try {
        const res = await fetch(`/api/customers/${parentId}/end-customers`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!data.success || !Array.isArray(data.data) || !data.data.length) return;

        const sel  = document.getElementById('approveEndCustomer');
        const wrap = document.getElementById('forCustomerWrap');
        if (!sel || !wrap) return;

        data.data.forEach(ec => {
            const opt = document.createElement('option');
            opt.value = ec.id;
            opt.textContent = ec.name + (ec.code ? ` (${ec.code})` : '');
            sel.appendChild(opt);
        });

        if (selectedEndCustomerId) {
            sel.value = String(selectedEndCustomerId);
            sel.dispatchEvent(new Event('change'));
        }

        wrap.classList.remove('hidden');
    } catch (e) {
        console.error('loadForCustomerOptions error', e);
    }
}

function renderFooter(s) {
    const footer = document.getElementById('modalFooter');
    if (s.status === 'unvalidated') {
        footer.innerHTML = `
            ${canRejectStaging ? `<button onclick="showRejectInput(${s.id})" id="btnReject"
                    class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">
                Reject
            </button>` : ''}
            ${canApproveStaging ? `<button onclick="submitApprove(${s.id})" id="btnApprove"
                    class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                Approve
            </button>` : ''}`;
    } else {
        footer.innerHTML = `
            <button onclick="closeModal()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Close</button>`;
    }
}

// ─── Attachment helpers ───────────────────────────────────────────────────────

function mimeIcon(mime) {
    if (!mime) return 'fa-file';
    if (mime.startsWith('image/')) return 'fa-file-image';
    if (mime === 'application/pdf') return 'fa-file-pdf';
    if (mime.includes('word')) return 'fa-file-word';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'fa-file-excel';
    if (mime.includes('zip') || mime.includes('compressed')) return 'fa-file-archive';
    return 'fa-file-alt';
}

function fmtSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/**
 * Build the attachments card HTML from an array of attachment objects.
 * source = 'local' (from staging_attachments) or 'email' (from Graph)
 */
function buildAttachmentsBlock(files, source = 'local') {
    if (!files.length) return '';
    const fileList = files.map(f => {
        const name     = escHtml(f.original_name || f.name || f.file_name || 'attachment');
        const mime     = f.mime_type || f.content_type || f.contentType || '';
        const size     = fmtSize(f.file_size || f.size);
        const url      = escHtml(f.url || '#');
        const icon     = mimeIcon(mime);
        const isImage  = mime.startsWith('image/');
        const isPdf    = mime === 'application/pdf';
        const preview  = isImage
            ? `<img src="${url}" alt="${name}" class="max-h-32 max-w-full rounded object-contain border border-gray-200 mt-2" onerror="this.remove()">`
            : '';
        // Image/PDF: browser punya viewer bawaan, buka di tab baru. Tipe lain (docx, xlsx, zip, dst):
        // browser tidak bisa render inline, jadi paksa download dengan nama file yang benar —
        // tanpa atribut `download` ini, sebagian browser fallback ke nama dari URL (rusak/acak).
        const linkAttrs = (isImage || isPdf) ? 'target="_blank" rel="noopener"' : `download="${name}"`;
        return `<div class="border-b border-gray-100 last:border-0">
            <a href="${url}" ${linkAttrs}
               class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors group">
                <i class="fas ${icon} text-gray-400 group-hover:text-red-500 text-base w-5 text-center flex-shrink-0"></i>
                <span class="text-sm text-gray-800 truncate flex-1">${name}</span>
                ${size ? `<span class="text-xs text-gray-400 flex-shrink-0">${size}</span>` : ''}
                <i class="fas fa-download text-gray-300 group-hover:text-red-500 text-xs flex-shrink-0"></i>
            </a>
            ${preview ? `<div class="px-4 pb-3">${preview}</div>` : ''}
        </div>`;
    }).join('');

    const label = source === 'email' ? 'Email Attachments' : 'Attachments';
    return `<div class="border border-gray-200 rounded-xl overflow-hidden mb-5">
        <div class="px-4 py-2 border-b border-gray-100 bg-gray-50 flex items-center gap-2">
            <i class="fas fa-paperclip text-gray-400 text-xs"></i>
            <span class="text-xs font-semibold text-gray-500">${label}</span>
            <span class="ml-auto text-xs text-gray-400">${files.length} file${files.length !== 1 ? 's' : ''}</span>
        </div>
        ${fileList}
    </div>`;
}

async function loadEmailAttachments(stagingId) {
    const block = document.getElementById('emailAttachmentsBlock');
    if (!block) return;
    try {
        const res = await apiFetch(`/api/staging-tickets/${stagingId}/email-attachments`);
        const atts = res.data ?? [];
        if (atts.length === 0) {
            block.remove();
        } else {
            block.outerHTML = buildAttachmentsBlock(atts, 'email');
        }
    } catch {
        if (block) block.remove();
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
        <button onclick="cancelReject()" class="inline-flex items-center px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all duration-200">Cancel</button>
        <button onclick="submitReject(${id})" id="btnConfirmReject"
                class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            Confirm Rejection
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
    const ticketType        = document.getElementById('approveTicketType')?.value ?? '';
    const priority          = document.getElementById('approvePriority')?.value   ?? '';
    const scale             = document.getElementById('approveScale')?.value      ?? '';
    const name              = document.getElementById('approveName')?.value.trim()   ?? '';
    const noHp              = document.getElementById('approveNoHp')?.value.trim()   ?? '';
    const module            = document.getElementById('approveModule')?.value.trim() ?? '';
    const client            = document.getElementById('approveClient')?.value.trim() ?? '';
    const deliverySupportId = _stagingDsSelected.id || null;
    const endCustomerId     = document.getElementById('approveEndCustomer')?.value || null;

    const typeErr = document.getElementById('typeError');
    const prioErr = document.getElementById('priorityError');
    const dsErr   = document.getElementById('dsError');
    let valid = true;

    if (!ticketType) { typeErr?.classList.remove('hidden'); valid = false; }
    else              { typeErr?.classList.add('hidden'); }

    if (!priority)    { prioErr?.classList.remove('hidden'); valid = false; }
    else              { prioErr?.classList.add('hidden'); }

    // Wajib hanya bila customer memang punya delivery support terdaftar.
    const dsAvailable = document.querySelectorAll('#stagingDsPanel .staging-ds-opt').length > 0;
    if (dsAvailable && !deliverySupportId) { dsErr?.classList.remove('hidden'); valid = false; }
    else                                   { dsErr?.classList.add('hidden'); }

    if (!valid) return;

    const btn = document.getElementById('btnApprove');
    if (btn) { btn.disabled = true; btn.textContent = 'Processing…'; }

    const t0 = performance.now();
    console.group(`%c[Approve] Staging #${id}`, 'color:#c62828;font-weight:bold');

    try {
        const res = await apiFetch(`/api/staging-tickets/${id}/approve`, 'POST', {
            ticket_type:          ticketType,
            ticket_priority:      priority,
            scale:                scale  || null,
            name:                 name   || null,
            no_hp:                noHp   || null,
            module:               module || null,
            client:               client || null,
            delivery_support_id:  deliverySupportId,
            end_customer_id:      endCustomerId,
        });
        const elapsed = ((performance.now() - t0) / 1000).toFixed(2);
        console.groupEnd();
        closeModal();
        loadStagingTickets(currentPage);
        loadStats();
        setTimeout(() => showNotif('Ticket created! Number: ' + (res.data?.ticket_number ?? ''), 'success'), 80);
    } catch (e) {
        const elapsed = ((performance.now() - t0) / 1000).toFixed(2);
        console.error(`❌ Approve FAILED (${elapsed}s)`, e.message, e);
        console.groupEnd();
        showNotif(e.message || 'Failed to approve ticket.', 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Approve'; }
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
    if (btn) { btn.disabled = true; btn.textContent = 'Processing…'; }

    try {
        await apiFetch(`/api/staging-tickets/${id}/reject`, 'POST', { reason });
        closeModal();
        loadStagingTickets(currentPage);
        loadStats();
        setTimeout(() => showNotif('Ticket rejected.', 'info'), 80);
    } catch (e) {
        showNotif(e.message || 'Failed to reject ticket.', 'error');
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm Rejection'; }
    }
}

function closeModal() {
    document.getElementById('stagingModal').style.display = 'none';
    currentStagingId   = null;
    currentStagingData = null;
}
// ─── Helpers ──────────────────────────────────────────────────────────────────
async function apiFetch(url, method = 'GET', body = null) {
    const opts = {
        method,
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        credentials: 'same-origin',
    };
    if (body) opts.body = JSON.stringify(body);

    const t0 = performance.now();

    let res, data;
    try {
        res  = await fetch(url, opts);
        data = await res.json();
    } catch (netErr) {
        console.error(`[apiFetch] Network error on ${method} ${url}:`, netErr);
        throw netErr;
    }

    const elapsed = ((performance.now() - t0) / 1000).toFixed(2);
    const logStyle = res.ok ? 'color:#15803d' : 'color:#b91c1c';

    if (!data.success) throw new Error(data.message || 'Request failed');
    return data;
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function showNotif(msg, type = 'info') {
    showToast(msg, type);
}

function handleRefresh() {
    loadStats();
    loadStagingTickets();
    fetchEmailInbox(false);
}

// ─── Fetch Email (Inbox + Sent Items) ────────────────────────────────────────
async function fetchEmailInbox(silent = false) {
    const btn    = document.getElementById('btnRefresh');
    const status = document.getElementById('fetchEmailStatus');

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Refreshing…'; }
    if (status) { status.textContent = 'Refreshing...'; }

    const ts = new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });

    const postJson = async (url) => {
        const r = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            credentials: 'same-origin',
        });
        const body = await r.json();
        return body;
    };

    try {
        // Jalankan kedua fetch secara paralel
        const [inboxData, sentData] = await Promise.allSettled([
            postJson('/api/email/process-inbox'),
            postJson('/api/email/process-sent'),
        ]);

        const now = new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false });

        const inbox = inboxData.status === 'fulfilled' ? inboxData.value : {};
        const sent  = sentData.status  === 'fulfilled' ? sentData.value  : {};

        if (inboxData.status === 'rejected') console.error(`[FetchEmail] process-inbox NETWORK ERROR:`, inboxData.reason);
        if (sentData.status  === 'rejected') console.error(`[FetchEmail] process-sent NETWORK ERROR:`, sentData.reason);

        // staged  = email baru yang masuk ke staging (notif harus bunyi)
        // linked  = email reply ke tiket existing (JANGAN trigger notif staging)
        const newStaged    = inbox.staged    ?? inbox.processed ?? 0;
        const linkedSent   = sent.linked     ?? 0;

        if (inbox.errors?.length) console.warn(`[FetchEmail] Inbox errors:`, inbox.errors);
        if (sent.errors?.length)  console.warn(`[FetchEmail] Sent errors:`, sent.errors);
        if (!inbox.success && inbox.message) console.warn(`[FetchEmail] process-inbox server error:`, inbox.message);
        if (!sent.success && sent.message)   console.warn(`[FetchEmail] process-sent server error:`, sent.message);

        const hasChanges = newStaged > 0 || linkedSent > 0;

        if (newStaged > 0) {
            // ── 1. Sound on current tab (staging page) ──────────────────────
            if (typeof playStagingSound === 'function') playStagingSound();

            // ── 2. OS notification hanya saat tab ini di background ─────────
            if (document.hidden && typeof showOsNotification === 'function') {
                showOsNotification(
                    'Email Baru · Ticket Validation',
                    `${newStaged} email baru menunggu validasi`,
                    '/staging'
                );
            }

            // ── 3. Broadcast ke tab lain di browser yang sama ───────────────
            // localStorage hanya sebagai fallback jika BroadcastChannel tidak tersedia
            // (mencegah double-play: BC dan storage event keduanya terpicu di tab penerima)
            const _evt = { type: 'new-staging-email', count: newStaged, ts: Date.now() };
            let _bcSent = false;
            try { const _bc = new BroadcastChannel('ecosystem-staging'); _bc.postMessage(_evt); _bc.close(); _bcSent = true; } catch (_e) {}
            if (!_bcSent) { try { localStorage.setItem('_eco_staging_evt', JSON.stringify(_evt)); } catch (_e) {} }
        }

        if (!silent || hasChanges) {
            const parts = [];
            if (newStaged > 0)  parts.push(`${newStaged} new ticket(s) from inbox`);
            if (linkedSent > 0) parts.push(`${linkedSent} staging(s) linked to sent email`);
            if (parts.length > 0) showNotif(parts.join(', ') + '.', 'success');
            else if (!silent) showNotif('No new emails.', 'info');
        }

        const statusParts = [];
        if (newStaged > 0)  statusParts.push(`${newStaged} inbox`);
        if (linkedSent > 0) statusParts.push(`${linkedSent} linked`);
        if (status) status.textContent = `Updated ${now} (WIB)${statusParts.length ? ' · ' + statusParts.join(', ') : ''}`;

        if (hasChanges) { loadStagingTickets(); loadStats(); }

    } catch (err) {
        const now = new Date().toLocaleTimeString('en-GB', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false });
        console.error(`[FetchEmail] ${ts} — Unhandled exception:`, err);
        if (!silent) showNotif('Failed to connect to email server.', 'error');
        if (status) status.textContent = `Error ${now} (WIB)`;
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-sync-alt text-xs"></i> Refresh'; }
    }
}

// ── Delivery Support combobox (validation modal) ──────────────────────────────

const DELIVERY_SUPPORTS = @json($deliverySupportsJson);

// Type/Priority/Scale enums — sumbernya App\Support\TicketClassification (PHP),
// dikirim lewat controller supaya <option> di sini tidak jadi salinan lepas yang
// bisa diam-diam beda dari validasi server / schema AI Ticket Analyzer.
const TICKET_TYPES      = @json($ticketClassification['types']);
const TICKET_PRIORITIES = @json($ticketClassification['priorities']);
const TICKET_SCALES     = @json($ticketClassification['scales']);

function buildOptionsHtml(values, placeholder) {
    const opts = values.map(v => `<option value="${escHtml(v)}">${escHtml(v)}</option>`).join('');
    return `<option value="">${escHtml(placeholder)}</option>${opts}`;
}

let _stagingDsSelected = { id: null, name: '' };

function openStagingDsDd() {
    const input = document.getElementById('stagingDsSearch');
    const panel = document.getElementById('stagingDsPanel');
    if (!input || !panel) return;
    input.select();
    filterStagingDs('');
    panel.classList.remove('hidden');
}

function filterStagingDs(q) {
    const panel = document.getElementById('stagingDsPanel');
    if (!panel) return;
    const term = q.toLowerCase().trim();
    panel.querySelectorAll('.staging-ds-opt').forEach(btn => {
        btn.style.display = (!term || btn.dataset.name.toLowerCase().includes(term)) ? '' : 'none';
    });
    panel.classList.remove('hidden');
}

function selectStagingDs(id, name) {
    _stagingDsSelected = { id: id ? parseInt(id) : null, name };
    const hidden = document.getElementById('stagingDsHidden');
    const input  = document.getElementById('stagingDsSearch');
    if (hidden) hidden.value = id ?? '';
    if (input)  input.value  = name;
    const panel = document.getElementById('stagingDsPanel');
    if (panel)  panel.classList.add('hidden');
}

document.addEventListener('click', function (e) {
    const dd = document.getElementById('stagingDsDd');
    if (dd && !dd.contains(e.target)) {
        const input = document.getElementById('stagingDsSearch');
        if (input) input.value = _stagingDsSelected.name;
        const panel = document.getElementById('stagingDsPanel');
        if (panel) panel.classList.add('hidden');
    }
});


</script>
{{-- Load custom-dd component (sama dengan halaman admin lain). filemtime
     cache buster supaya production auto-invalidate setiap deploy. --}}
@php
    $customDdPath = public_path('js/custom-dropdown.js');
    $customDdVer  = file_exists($customDdPath) ? filemtime($customDdPath) : time();
@endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
@endpush
