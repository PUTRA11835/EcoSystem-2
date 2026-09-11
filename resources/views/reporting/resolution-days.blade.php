@extends('dashboard')
@section('title', 'Resolution Days')
@section('page-title', 'Resolution Days')
@section('page-subtitle', 'Tickets with resolution days not yet approved')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Resolution Days</h2>
            <p class="text-sm text-gray-500 mt-0.5">Tickets that have not been proposed, or are pending Head approval</p>
        </div>
    </div>

    {{-- ── Summary Stats (click to filter) ───────────────────────────────────── --}}
    <div id="rdStats" class="hidden grid grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
        <div id="rdCardAll" onclick="rdCardFilter('')"
            class="stat-card active-filter bg-white rounded-xl border border-gray-200 shadow-sm p-4 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-gray-300">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Unapproved</p>
            <p id="statRdTotal" class="text-2xl font-bold primary-text">—</p>
            <p class="text-xs text-gray-400 mt-0.5">None + Pending Head</p>
        </div>
        <div id="rdCardNone" onclick="rdCardFilter('none')"
            class="stat-card bg-white rounded-xl border border-gray-200 shadow-sm p-4 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-gray-300">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">None</p>
            <p id="statRdNone" class="text-2xl font-bold text-gray-600">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Never proposed</p>
        </div>
        <div id="rdCardPending" onclick="rdCardFilter('pending_head')"
            class="stat-card bg-white rounded-xl border border-gray-200 shadow-sm p-4 cursor-pointer select-none transition-all duration-200 hover:shadow-md hover:border-yellow-200">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Pending Head</p>
            <p id="statRdPending" class="text-2xl font-bold text-yellow-600">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Awaiting approval</p>
        </div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 320px); min-height: 200px;">
            <table class="w-full text-sm border-collapse" style="min-width: 900px;">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr>
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px; position:relative;">
                            <button type="button" onclick="toggleRdTextPanel(event,'Ticket')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Ticket</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="rdTextIcon_Ticket" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="rdTextPanel_Ticket" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-normal text-gray-500 uppercase tracking-wide mb-1">Search ticket</label>
                                <input type="text" id="colFilterRdTicket" placeholder="e.g. TKT-001…" oninput="applyRdFilter()" onclick="event.stopPropagation()"
                                       class="rd-filter-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRdTextPanel('Ticket')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:180px;">
                            <div class="px-3 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-widest">Description</div>
                        </th>
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:140px; position:relative;">
                            <button type="button" onclick="toggleRdTextPanel(event,'Customer')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="rdTextIcon_Customer" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="rdTextPanel_Customer" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-normal text-gray-500 uppercase tracking-wide mb-1">Search customer</label>
                                <input type="text" id="colFilterRdCustomer" placeholder="Type customer…" oninput="applyRdFilter()" onclick="event.stopPropagation()"
                                       class="rd-filter-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRdTextPanel('Customer')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px; position:relative;">
                            <button type="button" onclick="toggleRdTextPanel(event,'Pic')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">PIC</span>
                                <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                <svg id="rdTextIcon_Pic" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>
                            </button>
                            <div id="rdTextPanel_Pic" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                                <label class="block text-[11px] font-normal text-gray-500 uppercase tracking-wide mb-1">Search PIC</label>
                                <input type="text" id="colFilterRdPic" placeholder="Type PIC name…" oninput="applyRdFilter()" onclick="event.stopPropagation()"
                                       class="rd-filter-input w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                <div class="flex justify-end gap-2 mt-2">
                                    <button type="button" onclick="clearRdTextPanel('Pic')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                                </div>
                            </div>
                        </th>
                        <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px;">
                            <div class="px-3 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-widest">Status</div>
                        </th>
                        <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:150px;">
                            <div class="px-3 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-widest">Proposed By</div>
                        </th>
                        <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:110px;">
                            <div class="px-3 py-2.5 text-[11px] font-semibold text-gray-500 uppercase tracking-widest">Proposed MD</div>
                        </th>
                        <th class="p-0 text-center whitespace-nowrap border-b border-gray-200 bg-gray-50" style="min-width:130px; position:relative;">
                            <button type="button" onclick="toggleRdProposedAtSort()" title="Click to toggle sort (descending ↔ ascending)" class="w-full flex items-center justify-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                                <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Proposed At</span>
                                <span id="rdSortProposedAtIcon" class="text-[10px] text-red-500 font-bold shrink-0"></span>
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody id="rdTableBody" class="bg-white">
                    <tr>
                        <td colspan="8" class="px-4 py-14 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
                            <span class="text-gray-400">Loading data...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        <div id="rdEmpty" class="hidden text-center py-16 px-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-50 mb-4">
                <svg class="w-8 h-8 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">All caught up</p>
            <p class="text-gray-400 text-xs mt-1">No tickets with unapproved resolution days</p>
        </div>
    </div>

</div>

{{-- ── Review Resolution Days Modal ───────────────────────────────────────── --}}
<div id="rdReviewModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl w-full max-w-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 id="rdReviewTicketLabel" class="text-lg font-bold text-gray-900">Review Resolution Days</h3>
                <p class="text-xs text-gray-500 mt-0.5">Status: <span id="rdReviewStatusLabel">—</span></p>
            </div>
            <button onclick="closeRdReviewModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="rdReviewLoading" class="py-10 text-center">
                <i class="fas fa-spinner fa-spin text-xl primary-text opacity-60 mb-2 block"></i>
                <p class="text-xs text-gray-400">Loading resolution days proposal...</p>
            </div>
            <div id="rdReviewStatusBanner" class="hidden mb-4 p-3 rounded-lg text-sm"></div>
            <div id="rdReviewContent" class="hidden">
                <table class="w-full text-xs border-collapse mb-4">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Name</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-14" title="Days — working days">Days</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-16" title="Additional Days proposed by PIC">Add.</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-600 border border-gray-200">Notes</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Enter approved days for each employee (out of the proposed Days)">Approved Days</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Enter approved additional for each employee">Approve Add.</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-600 border border-gray-200 w-20" title="Total Days = Approved Days + Approved Additional">Total Days</th>
                        </tr>
                    </thead>
                    <tbody id="rdReviewBody"></tbody>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold">
                            <td colspan="6" class="px-3 py-2 border border-gray-200 text-right text-xs">Total</td>
                            <td class="px-3 py-2 border border-gray-200 text-center" id="rdReviewTotal">0</td>
                        </tr>
                    </tfoot>
                </table>
                <div id="rdReviewSummaryPanel" class="hidden mb-4 grid grid-cols-2 sm:grid-cols-5 gap-2">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Days Proposed</p>
                        <p class="text-base font-bold text-gray-700" id="rdSummaryDaysProposed">0</p>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Add. Proposed</p>
                        <p class="text-base font-bold text-gray-700" id="rdSummaryAddProposed">0</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        <p class="text-[10px] font-semibold text-green-600 uppercase tracking-wide">Days Approved</p>
                        <p class="text-base font-bold text-green-700" id="rdSummaryDaysApproved">0</p>
                    </div>
                    <div class="bg-green-50 border border-green-200 rounded-lg px-3 py-2">
                        <p class="text-[10px] font-semibold text-green-600 uppercase tracking-wide">Add. Approved</p>
                        <p class="text-base font-bold text-green-700" id="rdSummaryAddApproved">0</p>
                    </div>
                    <div class="bg-green-100 border border-green-300 rounded-lg px-3 py-2">
                        <p class="text-[10px] font-semibold text-green-700 uppercase tracking-wide">Total Approved</p>
                        <p class="text-base font-bold text-green-800" id="rdSummaryGrandApproved">0</p>
                    </div>
                </div>
                <div id="rdReviewProposedBy" class="text-xs text-gray-500 mb-1"></div>
                <div id="rdReviewNoteWrap" class="hidden p-3 bg-gray-50 rounded-lg text-xs text-gray-600 mb-3"></div>
            </div>
        </div>
        <div id="rdReviewFooter" class="px-6 py-4 border-t border-gray-200 flex items-center justify-between flex-shrink-0">
            <p class="text-xs text-gray-400">Edit "Approved Days" / "Approve Add." then save to approve.</p>
            <div class="flex items-center gap-2">
                <a id="rdReviewOpenTicket" href="#" class="inline-flex items-center gap-1.5 px-3 py-2 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-50 border border-gray-300 transition-all duration-200">
                    Open Ticket
                </a>
                <button id="rdBtnApprove" onclick="rdReviewApprove()" class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    Save Approval
                </button>
            </div>
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
.stat-card.active-filter {
    border-left: 3px solid #dc2626 !important;
    border-top-color: #fecaca !important;
    border-right-color: #fecaca !important;
    border-bottom-color: #fecaca !important;
    background: #fff8f8 !important;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.08) !important;
}
.rd-filter-input, .rd-filter-input::placeholder {
    font-weight: 400 !important;
}
</style>
@endpush

@push('scripts')
<script src="/js/custom-dropdown.js?v={{ filemtime(public_path('js/custom-dropdown.js')) }}"></script>
<script>
let rdData         = [];
let rdSortKey      = null;
let rdSortDir      = null;
let rdStatusFilter = ''; // '' | 'none' | 'pending_head' — driven by the summary stat cards

document.addEventListener('DOMContentLoaded', function () {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    updateRdSortVisuals();
    loadRd();
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="rdTextPanel_"]') &&
        !e.target.closest('[onclick*="toggleRdTextPanel"]')) {
        closeRdTextPanelAll();
    }
});

window.addEventListener('scroll', function(e) {
    const t = e.target;
    if (t && t.nodeType === 1 && t.closest && t.closest('[id^="rdTextPanel_"]')) return;
    closeRdTextPanelAll();
}, true);
window.addEventListener('resize', closeRdTextPanelAll);

async function loadRd() {
    const tbody = document.getElementById('rdTableBody');
    tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-14 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
        <span>Loading data...</span>
    </td></tr>`;
    document.getElementById('rdEmpty').classList.add('hidden');
    document.getElementById('rdStats').classList.add('hidden');

    try {
        const res  = await fetch('/api/reporting/resolution-days', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'API error');

        rdData = json.data || [];
        renderRd();

    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${escHtml(e.message)}</span>
            </div>
        </td></tr>`;
    }
}

function applyRdFilter() {
    _updateRdFilterIcons();
    renderRd();
}

function rdCardFilter(status) {
    rdStatusFilter = status;
    document.querySelectorAll('.stat-card').forEach(el => el.classList.remove('active-filter'));
    const activeCard = status === 'none' ? 'rdCardNone' : status === 'pending_head' ? 'rdCardPending' : 'rdCardAll';
    document.getElementById(activeCard)?.classList.add('active-filter');
    renderRd();
}

function toggleRdProposedAtSort() {
    rdSortKey = 'proposed_at';
    rdSortDir = rdSortDir === 'asc' ? 'desc' : 'asc';
    updateRdSortVisuals();
    closeRdTextPanelAll();
    renderRd();
}

function updateRdSortVisuals() {
    const icon = document.getElementById('rdSortProposedAtIcon');
    if (icon) icon.textContent = rdSortKey === 'proposed_at' ? (rdSortDir === 'asc' ? '↑' : '↓') : '';
}

function _updateRdFilterIcons() {
    [['Ticket','colFilterRdTicket'], ['Customer','colFilterRdCustomer'], ['Pic','colFilterRdPic']].forEach(([key, id]) => {
        const icon  = document.getElementById('rdTextIcon_' + key);
        const input = document.getElementById(id);
        if (!icon) return;
        const active = !!(input?.value);
        icon.classList.toggle('text-red-500', active);
        icon.classList.toggle('text-gray-300', !active);
    });
}

function toggleRdTextPanel(event, key) {
    event.stopPropagation();
    const panel = document.getElementById('rdTextPanel_' + key);
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeRdTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) {
        panel.classList.remove('hidden');
        const inp = panel.querySelector('input[type="text"]');
        if (inp) setTimeout(() => inp.focus(), 30);
    }
}

function closeRdTextPanelAll() {
    document.querySelectorAll('[id^="rdTextPanel_"]').forEach(p => p.classList.add('hidden'));
}

function clearRdTextPanel(key) {
    const inp = document.getElementById('colFilterRd' + key);
    if (inp) { inp.value = ''; applyRdFilter(); }
}

function statusBadge(status) {
    return status === 'pending_head'
        ? `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-yellow-100 text-yellow-700">Pending Head</span>`
        : `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600">None</span>`;
}

function renderRd() {
    const tbody         = document.getElementById('rdTableBody');
    const empty          = document.getElementById('rdEmpty');
    const stats           = document.getElementById('rdStats');
    const ticketFilter   = (document.getElementById('colFilterRdTicket')?.value   || '').toLowerCase().trim();
    const customerFilter = (document.getElementById('colFilterRdCustomer')?.value || '').toLowerCase().trim();
    const picFilter      = (document.getElementById('colFilterRdPic')?.value      || '').toLowerCase().trim();

    let filtered = rdData.filter(r => {
        if (ticketFilter && !(r.ticket_number || '').toLowerCase().includes(ticketFilter)) return false;
        if (customerFilter && !(r.customer_name || '').toLowerCase().includes(customerFilter)) return false;
        if (picFilter && !(r.pic_name || '').toLowerCase().includes(picFilter)) return false;
        if (rdStatusFilter && r.resolution_days_status !== rdStatusFilter) return false;
        return true;
    });

    if (rdSortKey === 'proposed_at') {
        filtered = [...filtered].sort((a, b) => {
            const va = a.proposed_at || '';
            const vb = b.proposed_at || '';
            return rdSortDir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        });
    }

    document.getElementById('statRdTotal').textContent   = rdData.length;
    document.getElementById('statRdNone').textContent    = rdData.filter(r => r.resolution_days_status === 'none').length;
    document.getElementById('statRdPending').textContent = rdData.filter(r => r.resolution_days_status === 'pending_head').length;
    stats.classList.remove('hidden');

    if (filtered.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }
    empty.classList.add('hidden');

    tbody.innerHTML = filtered.map(r => `
        <tr class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer" onclick="openRdReviewModal(${r.ticket_id}, '${escJs(r.ticket_number || ('#' + r.ticket_id))}')">
            <td class="px-3 py-2.5">
                <a href="/ticket/${r.ticket_id}" onclick="event.stopPropagation()" class="text-sm font-semibold primary-text hover:underline">${escHtml(r.ticket_number || ('#' + r.ticket_id))}</a>
            </td>
            <td class="px-3 py-2.5 text-xs text-gray-600 max-w-[220px] truncate" title="${escHtml(r.description || '')}">${escHtml(r.description || '-')}</td>
            <td class="px-3 py-2.5 text-sm text-gray-700">${escHtml(r.customer_name || '-')}</td>
            <td class="px-3 py-2.5 text-sm text-gray-700">${escHtml(r.pic_name || '-')}</td>
            <td class="px-3 py-2.5 text-center">${statusBadge(r.resolution_days_status)}</td>
            <td class="px-3 py-2.5 text-xs text-gray-600">${escHtml(r.proposed_by || '—')}</td>
            <td class="px-3 py-2.5 text-center text-sm font-semibold text-gray-700">${r.proposed_total_mandays != null ? Number(r.proposed_total_mandays).toFixed(2) : '—'}</td>
            <td class="px-3 py-2.5 text-center text-xs text-gray-600">${escHtml(r.proposed_at || '—')}</td>
        </tr>
    `).join('');
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function escJs(str) {
    return String(str ?? '').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

// ── Review Resolution Days modal ────────────────────────────────────────────

let rdReviewTicketId = null;

function rdHeaders() {
    return {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
    };
}

async function openRdReviewModal(ticketId, ticketLabel) {
    rdReviewTicketId = ticketId;
    const modal = document.getElementById('rdReviewModal');
    if (!modal) return;
    modal.classList.remove('hidden'); modal.classList.add('flex');
    document.getElementById('rdReviewTicketLabel').textContent = 'Review Resolution Days — ' + (ticketLabel || ('#' + ticketId));
    document.getElementById('rdReviewOpenTicket').href = `/ticket/${ticketId}`;
    document.getElementById('rdReviewLoading').classList.remove('hidden');
    document.getElementById('rdReviewContent').classList.add('hidden');
    document.getElementById('rdReviewStatusBanner').classList.add('hidden');
    document.getElementById('rdBtnApprove').classList.add('hidden');

    try {
        const res  = await fetch(`/api/tickets/${ticketId}/mandays/resolution`, { headers: rdHeaders(), credentials: 'same-origin' });
        const data = await res.json();

        if (!res.ok || !data.success) {
            document.getElementById('rdReviewContent').innerHTML = `<p class="text-sm text-red-500 text-center py-4">${escHtml(data.message || 'Failed to load proposal.')}</p>`;
            document.getElementById('rdReviewContent').classList.remove('hidden');
            return;
        }

        const proposal = data.data;
        const status   = data.resolution_days_status || 'none';

        const statusLabels = {
            'none':         'None',
            'draft':        'Draft',
            'pending_head': 'Pending Review',
            'approved':     'Approved',
            'rejected':     'Needs Revision',
        };
        document.getElementById('rdReviewStatusLabel').textContent = statusLabels[status] || status;

        if (!proposal) {
            document.getElementById('rdReviewContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal submitted yet.</p>';
            document.getElementById('rdReviewContent').classList.remove('hidden');
            return;
        }

        // Build per-employee map (sum across modules)
        const empMap = {};
        (proposal.details || []).forEach(d => {
            const eid = d.employee_id;
            if (!empMap[eid]) empMap[eid] = { name: d.employee_name || '—', mandays: 0, approved_mandays: 0, additional_mandays: 0, approved_additional: 0, notes: '' };
            empMap[eid].mandays            += parseFloat(d.mandays || 0);
            empMap[eid].approved_mandays   += parseFloat(d.approved_mandays || 0);
            empMap[eid].additional_mandays += parseFloat(d.additional_mandays || 0);
            empMap[eid].approved_additional+= parseFloat(d.approved_additional || 0);
            if (d.notes) empMap[eid].notes = d.notes;
        });

        let bodyHtml = '';
        let grandTotal = 0;
        Object.entries(empMap).forEach(([eid, emp]) => {
            const currentApprDays = emp.approved_mandays;
            const currentApprAdd  = emp.approved_additional;
            const rowTotal = currentApprDays + currentApprAdd;
            grandTotal += rowTotal;
            bodyHtml += `<tr>
                <td class="px-3 py-2 border border-gray-200 text-xs font-medium">${escHtml(emp.name)}</td>
                <td class="px-3 py-2 border border-gray-200 text-xs text-center">${emp.mandays > 0 ? emp.mandays.toFixed(1) : '—'}</td>
                <td class="px-3 py-2 border border-gray-200 text-xs text-center">${emp.additional_mandays > 0 ? emp.additional_mandays.toFixed(1) : '—'}</td>
                <td class="px-3 py-2 border border-gray-200 text-xs text-gray-500">${escHtml(emp.notes || '')}</td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="rd-approve-days w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-gray-100 bg-white"
                        data-employee="${eid}"
                        value="${currentApprDays > 0 ? currentApprDays : ''}"
                        oninput="rdUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="rd-approve-add w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-gray-100 bg-white"
                        data-employee="${eid}"
                        value="${currentApprAdd > 0 ? currentApprAdd : ''}"
                        oninput="rdUpdateRowTotal(this)">
                </td>
                <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-rd-total="${eid}">${rowTotal > 0 ? rowTotal.toFixed(1) : '—'}</td>
            </tr>`;
        });
        document.getElementById('rdReviewBody').innerHTML = bodyHtml;
        document.getElementById('rdReviewTotal').textContent = grandTotal.toFixed(1);

        const summaryPanel = document.getElementById('rdReviewSummaryPanel');
        if (summaryPanel) {
            const rows = Object.values(empMap);
            document.getElementById('rdSummaryDaysProposed').textContent =
                rows.reduce((acc, r) => acc + (r.mandays || 0), 0).toFixed(1);
            document.getElementById('rdSummaryAddProposed').textContent =
                rows.reduce((acc, r) => acc + (r.additional_mandays || 0), 0).toFixed(1);
            summaryPanel.classList.toggle('hidden', rows.length === 0);
        }
        rdRecalcApprovedSummary();

        const proposedByEl = document.getElementById('rdReviewProposedBy');
        proposedByEl.textContent = proposal.proposed_by ? ('Proposed by: ' + proposal.proposed_by) : '';

        const nw = document.getElementById('rdReviewNoteWrap');
        if (proposal.notes) {
            nw.textContent = 'Notes: ' + proposal.notes;
            nw.classList.remove('hidden');
        } else {
            nw.classList.add('hidden');
        }

        const bannerEl = document.getElementById('rdReviewStatusBanner');
        bannerEl.classList.add('hidden');
        if (status === 'approved') {
            bannerEl.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
            bannerEl.innerHTML = '<p class="font-semibold">Saved — Proposal Approved</p>'
                + (proposal.approved_by_head ? '<p class="text-xs mt-0.5">Approved by: ' + escHtml(proposal.approved_by_head) + '</p>' : '');
            bannerEl.classList.remove('hidden');
        } else if (status === 'draft') {
            bannerEl.className = 'mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700';
            bannerEl.innerHTML = '<p class="font-semibold">Draft — PIC has not submitted yet.</p>';
            bannerEl.classList.remove('hidden');
        } else if (status === 'rejected') {
            bannerEl.className = 'mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg text-sm text-orange-700';
            bannerEl.innerHTML = '<p class="font-semibold">Needs Revision — PIC is revising.</p>';
            bannerEl.classList.remove('hidden');
        }

        document.getElementById('rdBtnApprove').classList.remove('hidden');
        document.getElementById('rdReviewContent').classList.remove('hidden');
    } catch (e) {
        console.error(e);
        showNotification('Failed to load resolution days proposal', 'error');
    } finally {
        document.getElementById('rdReviewLoading').classList.add('hidden');
    }
}

function closeRdReviewModal() {
    const modal = document.getElementById('rdReviewModal');
    if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    rdReviewTicketId = null;
}

function rdUpdateRowTotal(inp) {
    if (inp.classList.contains('rd-approve-days') && inp.value.trim() !== '') {
        inp.classList.remove('ring-2', 'ring-red-500', 'bg-red-50');
    }
    const row      = inp.closest('tr');
    const apprDays = parseFloat(row.querySelector('.rd-approve-days')?.value) || 0;
    const apprAdd  = parseFloat(row.querySelector('.rd-approve-add')?.value) || 0;
    const total    = apprDays + apprAdd;
    const empId   = inp.dataset.employee;
    const cell    = row.querySelector(`[data-rd-total="${empId}"]`);
    if (cell) cell.textContent = total > 0 ? total.toFixed(1) : '—';
    let grand = 0;
    document.querySelectorAll('[data-rd-total]').forEach(c => grand += parseFloat(c.textContent) || 0);
    document.getElementById('rdReviewTotal').textContent = grand.toFixed(1);
    rdRecalcApprovedSummary();
}

// Sums the currently-typed Approved Days / Approve Add. inputs across all rows —
// called on every keystroke (rdUpdateRowTotal) and once at initial render.
function rdRecalcApprovedSummary() {
    let daysApproved = 0, addApproved = 0;
    document.querySelectorAll('.rd-approve-days').forEach(inp => { daysApproved += parseFloat(inp.value) || 0; });
    document.querySelectorAll('.rd-approve-add').forEach(inp => { addApproved += parseFloat(inp.value) || 0; });
    const daysEl  = document.getElementById('rdSummaryDaysApproved');
    const addEl   = document.getElementById('rdSummaryAddApproved');
    const grandEl = document.getElementById('rdSummaryGrandApproved');
    if (daysEl)  daysEl.textContent  = daysApproved.toFixed(1);
    if (addEl)   addEl.textContent   = addApproved.toFixed(1);
    if (grandEl) grandEl.textContent = (daysApproved + addApproved).toFixed(1);
}

async function rdReviewApprove(confirmNegative = false) {
    if (!rdReviewTicketId) return;

    // Approved Days is a required judgment call per employee — a blank field silently
    // became 0 before this check existed, so a Head could approve without actually
    // reviewing someone's days. Block the save and flag every empty field instead.
    const emptyDaysInputs = Array.from(document.querySelectorAll('.rd-approve-days'))
        .filter(inp => inp.value.trim() === '');
    document.querySelectorAll('.rd-approve-days').forEach(inp => inp.classList.remove('ring-2', 'ring-red-500', 'bg-red-50'));
    if (emptyDaysInputs.length > 0) {
        emptyDaysInputs.forEach(inp => inp.classList.add('ring-2', 'ring-red-500', 'bg-red-50'));
        showNotification('Approved Days must be filled for every employee before saving.', 'error');
        emptyDaysInputs[0].focus();
        return;
    }

    const btn = document.getElementById('rdBtnApprove');
    btn.disabled = true;
    try {
        const approvedDetails = [];
        document.querySelectorAll('.rd-approve-days').forEach(inp => {
            const row = inp.closest('tr');
            approvedDetails.push({
                employee_id:         parseInt(inp.dataset.employee),
                approved_mandays:    parseFloat(inp.value) || 0,
                approved_additional: parseFloat(row.querySelector('.rd-approve-add')?.value) || 0,
            });
        });

        const res  = await fetch(`/api/tickets/${rdReviewTicketId}/mandays/resolution/approve`, {
            method: 'POST', headers: rdHeaders(), credentials: 'same-origin',
            body: JSON.stringify({ approved_details: approvedDetails, confirm_negative: confirmNegative }),
        });
        const data = await res.json();
        if (data.success) {
            showNotification('Resolution days saved!', 'success');
            closeRdReviewModal();
            loadRd();
            return;
        }
        if (data.requires_confirmation) {
            const lines = (data.warnings || []).map(w => `- ${w.employee_name}: remaining would be ${w.remaining} MD`);
            const proceed = await showConfirm(`Some employees will go negative if you approve these numbers:\n\n${lines.join('\n')}\n\nApprove anyway?`, 'Approve Anyway?', 'danger');
            if (proceed) {
                await rdReviewApprove(true);
            } else {
                showNotification('Approval cancelled.', 'info');
            }
            return;
        }
        showNotification(data.message || 'Failed', 'error');
    } catch (e) {
        showNotification('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
    }
}
</script>
@endpush
