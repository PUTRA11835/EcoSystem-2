@extends('dashboard')
@section('title', 'Rejected Tickets')
@section('page-title', 'Rejected Tickets')
@section('page-subtitle', 'Tickets that were rejected during validation')

@section('content')
{{-- ===== HEADER ===== --}}
<div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
        <div class="flex items-center gap-3 mb-1">
            <a href="{{ route('staging.index') }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-50 shadow-sm transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
                Back to Ticket Validation
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight mb-1">Rejected Tickets</h1>
        <p class="text-gray-500 text-sm">Tickets that were rejected during validation</p>
    </div>
    <div class="text-right lg:text-right">
        <p class="text-xs font-medium text-gray-500 mb-1">Total Rejected</p>
        <p class="text-2xl font-bold text-gray-900" id="totalRejected">—</p>
    </div>
</div>

{{-- ===== FILTER / SEARCH ===== --}}
<div class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <h3 class="text-sm font-semibold text-gray-700">Rejected Tickets</h3>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-gray-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
                <input type="text" id="searchInput" placeholder="Search customer or description..."
                       oninput="debounceSearch()"
                       class="pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-transparent w-72">
            </div>
            <button onclick="loadRejectedTickets()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-all">
                <i class="fas fa-sync-alt text-xs"></i> Refresh
            </button>
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
                    <th class="px-6 py-3 text-left">Customer</th>
                    <th class="px-6 py-3 text-left">Description</th>
                    <th class="px-6 py-3 text-left">Priority</th>
                    <th class="px-6 py-3 text-left">Channel</th>
                    <th class="px-6 py-3 text-left">Rejection Reason</th>
                    <th class="px-6 py-3 text-left">Rejected By</th>
                    <th class="px-6 py-3 text-left">Date</th>
                    <th class="px-6 py-3 text-left">Actions</th>
                </tr>
            </thead>
            <tbody id="rejectedTableBody">
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>
                        Loading data...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ===== DETAIL MODAL ===== --}}
<div id="detailModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" style="display:none">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 overflow-hidden">
        {{-- Header --}}
        <div class="px-7 py-5 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-base font-extrabold text-gray-900">Rejected Ticket Details</h3>
                <p class="text-xs text-gray-400 mt-0.5" id="modalStagingId"></p>
            </div>
            <button onclick="closeModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Content --}}
        <div class="p-7 space-y-4" id="modalContent">
            <div class="text-center text-gray-400 py-8">
                <i class="fas fa-spinner fa-spin text-2xl"></i>
            </div>
        </div>

        {{-- Footer --}}
        <div class="border-t border-gray-100 px-7 py-4 bg-gray-50 flex justify-end">
            <button onclick="closeModal()" class="px-5 py-2.5 text-sm font-semibold text-gray-700 border-2 border-gray-200 rounded-xl hover:bg-white transition-all">
                Close
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
let currentPage = 1;
let meta = {};
let searchTimer = null;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    loadRejectedTickets();
});

// ─── Debounce search ──────────────────────────────────────────────────────────
function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadRejectedTickets(1), 400);
}

// ─── Load Table ───────────────────────────────────────────────────────────────
async function loadRejectedTickets(page = 1) {
    currentPage = page;
    const params = new URLSearchParams({ status: 'rejected', per_page: 15, page });
    const search = document.getElementById('searchInput').value.trim();

    const tbody = document.getElementById('rejectedTableBody');
    tbody.innerHTML = `<tr><td colspan="9" class="px-6 py-12 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-2xl mb-2 block"></i>Loading...</td></tr>`;

    try {
        const res = await apiFetch('/api/staging-tickets?' + params.toString());
        meta = res.meta ?? {};
        let rows = res.data ?? [];

        // Client-side search filter (API doesn't support text search)
        if (search) {
            const q = search.toLowerCase();
            rows = rows.filter(s =>
                (s.customer_name ?? '').toLowerCase().includes(q) ||
                (s.submitted_by_email ?? '').toLowerCase().includes(q) ||
                (s.description ?? '').toLowerCase().includes(q)
            );
        }

        document.getElementById('totalRejected').textContent = meta.total ?? rows.length;
        renderTable(rows);
        renderPagination();
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-6 py-10 text-center text-red-500 text-sm">
            <i class="fas fa-exclamation-circle mr-1"></i>Failed to load data.</td></tr>`;
    }
}

function renderTable(rows) {
    const tbody = document.getElementById('rejectedTableBody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-6 py-14 text-center text-gray-400 text-sm">
            <i class="fas fa-check-circle text-4xl mb-3 block text-green-300"></i>
            No rejected tickets found.</td></tr>`;
        return;
    }

    const prioColor = { Low: 'bg-green-100 text-green-700', Medium: 'bg-blue-100 text-blue-700', High: 'bg-red-100 text-red-700' };

    tbody.innerHTML = rows.map(s => {
        const date     = s.validated_at
            ? new Date(s.validated_at).toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric' })
            : (s.created_at ? new Date(s.created_at).toLocaleDateString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric' }) : '—');
        const short    = s.description ? (s.description.length > 55 ? s.description.substring(0, 55) + '…' : s.description) : '—';
        const reason   = s.rejection_reason ? (s.rejection_reason.length > 50 ? s.rejection_reason.substring(0, 50) + '…' : s.rejection_reason) : '<span class="text-gray-300 italic">No reason given</span>';
        const prio     = s.ticket_priority ?? 'Medium';
        const ch       = s.channel === 'email'
            ? '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 bg-blue-50 text-blue-700 rounded-md"><i class="fas fa-envelope text-[9px]"></i> Email</span>'
            : '<span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 bg-gray-100 text-gray-600 rounded-md"><i class="fas fa-globe text-[9px]"></i> Web</span>';
        const validator = escHtml(s.validator_name ?? '—');

        return `<tr class="border-b border-gray-50 hover:bg-red-50/30 transition-colors">
            <td class="px-6 py-4 text-gray-500 font-mono text-xs">#${s.id}</td>
            <td class="px-6 py-4">
                <p class="font-semibold text-gray-900 text-xs">${escHtml(s.customer_name ?? 'Unknown')}</p>
                ${s.submitted_by_email ? `<p class="text-[10px] text-gray-400">${escHtml(s.submitted_by_email)}</p>` : ''}
            </td>
            <td class="px-6 py-4 text-gray-600 max-w-[200px] text-xs">${escHtml(short)}</td>
            <td class="px-6 py-4">
                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold ${prioColor[prio] ?? 'bg-gray-100 text-gray-600'}">${prio}</span>
            </td>
            <td class="px-6 py-4">${ch}</td>
            <td class="px-6 py-4 max-w-[200px]">
                <p class="text-xs text-red-700 italic leading-relaxed">${s.rejection_reason ? escHtml(s.rejection_reason.length > 50 ? s.rejection_reason.substring(0,50) + '…' : s.rejection_reason) : '<span class="text-gray-300">—</span>'}</p>
            </td>
            <td class="px-6 py-4 text-gray-500 text-xs">${validator}</td>
            <td class="px-6 py-4 text-gray-500 text-xs whitespace-nowrap">${date}</td>
            <td class="px-6 py-4">
                <button onclick="openModal(${s.id})"
                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-200 transition-all">
                    <i class="fas fa-eye text-[10px]"></i> Detail
                </button>
            </td>
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
    loadRejectedTickets(currentPage + dir);
}

// ─── Detail Modal ─────────────────────────────────────────────────────────────
async function openModal(id) {
    document.getElementById('detailModal').style.display = 'flex';
    document.getElementById('modalStagingId').textContent = `Staging #${id}`;
    document.getElementById('modalContent').innerHTML =
        `<div class="text-center text-gray-400 py-8"><i class="fas fa-spinner fa-spin text-2xl"></i></div>`;

    try {
        const res = await apiFetch(`/api/staging-tickets/${id}`);
        const s   = res.data;
        renderModalContent(s);
    } catch {
        document.getElementById('modalContent').innerHTML =
            `<p class="text-sm text-red-500 text-center">Failed to load details.</p>`;
    }
}

function renderModalContent(s) {
    const prioColor = { Low: 'bg-green-100 text-green-700', Medium: 'bg-blue-100 text-blue-700', High: 'bg-red-100 text-red-700' };
    const prio      = s.ticket_priority ?? 'Medium';
    const date      = s.created_at  ? new Date(s.created_at).toLocaleString('id-ID',  { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) + ' WIB' : '—';
    const rejDate   = s.validated_at ? new Date(s.validated_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) + ' WIB' : '—';

    document.getElementById('modalContent').innerHTML = `
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Customer</p>
                <p class="text-sm font-bold text-gray-800">${escHtml(s.customer_name ?? s.submitted_by_email ?? 'Unknown')}</p>
                ${s.submitted_by_email ? `<p class="text-[10px] text-gray-400 mt-0.5">${escHtml(s.submitted_by_email)}</p>` : ''}
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Priority</p>
                <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold ${prioColor[prio] ?? 'bg-gray-100 text-gray-600'}">${prio}</span>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Channel</p>
                <p class="text-sm font-bold text-gray-800">${s.channel === 'email' ? '📧 Email' : '🌐 Web'}</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4">
                <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-1">Submitted</p>
                <p class="text-sm font-bold text-gray-800">${date}</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-4">
            <p class="text-[11px] text-gray-400 font-semibold uppercase tracking-wide mb-2">Description</p>
            <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap">${escHtml(s.description ?? '—')}</p>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <div class="flex items-center gap-2 mb-2">
                <i class="fas fa-ban text-red-600 text-xs"></i>
                <p class="text-[11px] text-red-600 font-bold uppercase tracking-wide">Rejection Details</p>
            </div>
            <p class="text-sm text-red-800 font-semibold leading-relaxed">${escHtml(s.rejection_reason ?? 'No reason provided.')}</p>
            <div class="mt-3 pt-3 border-t border-red-200 flex items-center justify-between text-xs text-red-500">
                <span>Rejected by: <strong>${escHtml(s.validator_name ?? '—')}</strong></span>
                <span>${rejDate}</span>
            </div>
        </div>
    `;
}

function closeModal() {
    document.getElementById('detailModal').style.display = 'none';
}
document.getElementById('detailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

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
</script>
@endpush
