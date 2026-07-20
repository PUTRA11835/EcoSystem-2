@extends('dashboard')
@section('title', 'Collection Outlook')
@section('page-title', 'Collection Outlook')
@section('page-subtitle', 'Billing outlook (Term Of Payment) by project and month')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    {{-- ── Page Header ────────────────────────────────────────────────────── --}}
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Collection Outlook</h2>
            <p id="coRangeLabel" class="text-sm text-gray-500 mt-0.5">Planned billing based on each project's Term Of Payment</p>
        </div>

        {{-- Month & year range --}}
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">From</label>
                <div class="inline-flex items-center gap-1">
                    <select id="coFromMonth" class="co-select" style="width:8rem">
                        @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn)
                            <option value="{{ $i + 1 }}" {{ now()->month == $i + 1 ? 'selected' : '' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                    <select id="coFromYear" class="co-select">
                        @for($y = now()->year - 3; $y <= now()->year + 2; $y++)
                            <option value="{{ $y }}" {{ now()->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">To</label>
                <div class="inline-flex items-center gap-1">
                    <select id="coToMonth" class="co-select" style="width:8rem">
                        @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mn)
                            <option value="{{ $i + 1 }}" {{ now()->month == $i + 1 ? 'selected' : '' }}>{{ $mn }}</option>
                        @endforeach
                    </select>
                    <select id="coToYear" class="co-select">
                        @for($y = now()->year - 3; $y <= now()->year + 2; $y++)
                            <option value="{{ $y }}" {{ now()->year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <button onclick="loadOutlook()" id="coApplyBtn"
                class="inline-flex items-center gap-1.5 px-4 py-2 primary-bg text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
                <i class="fas fa-filter text-xs"></i>
                Apply
            </button>
        </div>
    </div>

    {{-- ── Summary Stats ───────────────────────────────────────────────────── --}}
    <div id="coStats" class="hidden grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Outlook</p>
            <p id="coStatTotal" class="text-2xl font-bold primary-text">—</p>
            <p class="text-xs text-gray-400 mt-0.5">All billings in range</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Paid</p>
            <p id="coStatPaid" class="text-2xl font-bold text-green-700">—</p>
            <p id="coStatPaidCount" class="text-xs text-gray-400 mt-0.5">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Unpaid</p>
            <p id="coStatUnpaid" class="text-2xl font-bold text-amber-600">—</p>
            <p id="coStatUnpaidCount" class="text-xs text-gray-400 mt-0.5">—</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Payment Terms</p>
            <p id="coStatTerms" class="text-2xl font-bold text-gray-800">—</p>
            <p class="text-xs text-gray-400 mt-0.5">Billing rows shown</p>
        </div>
    </div>

    {{-- ── Table ───────────────────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-auto" style="max-height: calc(100vh - 320px); min-height: 220px;">
            <table id="coTable" class="text-sm border-collapse w-full" style="min-width: 640px;">
                <thead class="sticky top-0 z-20 bg-gray-50">
                    <tr id="coHeadRow">
                        <th class="co-th co-sticky-cust text-left">Customer<div class="co-resizer" data-col="cust"></div></th>
                        <th class="co-th co-sticky-proj text-left">Project Name<div class="co-resizer" data-col="proj"></div></th>
                        <th class="co-th co-sticky-top text-center">TOP No.</th>
                        {{-- month columns injected by JS --}}
                    </tr>
                </thead>
                <tbody id="coTableBody" class="bg-white">
                    <tr>
                        <td colspan="3" class="px-4 py-14 text-center text-gray-400 text-sm">
                            <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
                            <span class="text-gray-400">Loading data...</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Empty state --}}
        <div id="coEmpty" class="hidden text-center py-16 px-4">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                <svg class="w-8 h-8 text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                </svg>
            </div>
            <p class="text-gray-700 font-semibold">No billing found</p>
            <p class="text-gray-400 text-xs mt-1">There are no Terms Of Payment within the selected month range</p>
        </div>
    </div>

    <p class="text-[11px] text-gray-400 mt-3">
        <i class="fas fa-info-circle mr-1"></i>
        Billing amounts are placed in the month of each term's <span class="font-semibold">Estimated Date</span>.
        Click an amount to view the full TOP &amp; invoice details. Terms with a
        <span class="inline-flex items-center gap-1 text-green-700 font-semibold"><i class="fas fa-check-circle"></i> Paid</span>
        status are highlighted in green.
    </p>
</div>

{{-- ── Detail Modal ─────────────────────────────────────────────────────────── --}}
<div id="coDetailModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCoDetail()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[92vh] overflow-hidden flex flex-col">
        {{-- Modal header --}}
        <div id="coModalHeader" class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Term Of Payment Details</p>
                <h3 id="coModalTitle" class="text-lg font-bold text-gray-900 mt-0.5">—</h3>
                <p id="coModalSub" class="text-xs text-gray-500 mt-0.5">—</p>
            </div>
            <button onclick="closeCoDetail()" class="shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        {{-- Modal body --}}
        <div class="px-8 py-6 overflow-y-auto space-y-6">
            {{-- Amount highlight --}}
            <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Billing Amount</p>
                    <p id="coDetAmount" class="text-2xl font-bold text-gray-900 mt-0.5">—</p>
                    <p id="coDetPct" class="text-xs text-gray-500 mt-0.5">—</p>
                </div>
                <span id="coDetStatus" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold">—</span>
            </div>

            {{-- TOP detail --}}
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">TOP Information</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-4 text-sm">
                    <div><dt class="text-xs text-gray-400">Project</dt><dd id="coDetProject" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Client</dt><dd id="coDetClient" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">IO Number</dt><dd id="coDetIo" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Term No.</dt><dd id="coDetTermNo" class="text-gray-800 font-medium">—</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-400">Term Name</dt><dd id="coDetTermName" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Percentage</dt><dd id="coDetPctRow" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Project Revenue</dt><dd id="coDetRevenue" class="text-gray-800 font-medium">—</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-400">Requirements</dt><dd id="coDetReq" class="text-gray-800 whitespace-pre-line">—</dd></div>
                </dl>
            </div>

            {{-- Invoice detail (read view) --}}
            <div id="coDetailView">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Invoice Information</p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-4 text-sm">
                    <div><dt class="text-xs text-gray-400">Estimated Date</dt><dd id="coDetEst" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Submit Invoice Date</dt><dd id="coDetSubmit" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Invoice Number</dt><dd id="coDetInvNo" class="text-gray-800 font-medium">—</dd></div>
                    <div><dt class="text-xs text-gray-400">Paid Date</dt><dd id="coDetPaid" class="text-gray-800 font-medium">—</dd></div>
                </dl>
            </div>

            @if($can('reporting.collection-outlook.edit'))
            {{-- Invoice detail (edit form) — hanya untuk role dengan izin
                 "Edit Payment Status" pada Menu Access. Nominal & percentage
                 sengaja tidak ada di sini; itu tetap dikelola dari Delivery
                 Information project agar total termin terjaga ≤ revenue. --}}
            <form id="coEditForm" class="hidden" onsubmit="event.preventDefault(); saveCoStatus();">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Edit Payment Status</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-10 gap-y-4 text-sm">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Status</label>
                        <select id="coEditStatus" class="co-select w-full" onchange="toggleCoPaidDate()">
                            <option value="Open">Open</option>
                            <option value="Paid">Paid</option>
                            <option value="Delay">Delay</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Paid Date <span id="coPaidReq" class="hidden text-red-500">*</span></label>
                        <input type="date" id="coEditPaidDate" class="co-select w-full">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Submit Invoice Date</label>
                        <input type="date" id="coEditSubmitDate" class="co-select w-full" onchange="toggleCoInvoiceRequired()">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Invoice Number <span id="coInvReq" class="hidden text-red-500">*</span></label>
                        <input type="text" id="coEditInvNo" class="co-select w-full" placeholder="e.g. INV/2026/001">
                    </div>
                </div>
                <p id="coEditError" class="hidden mt-3 text-xs text-red-600"></p>
            </form>
            @endif
        </div>

        {{-- Modal footer --}}
        <div class="px-6 py-3 border-t border-gray-100 flex justify-end gap-2">
            @if($can('reporting.collection-outlook.edit'))
            <button type="button" id="coEditBtn" onclick="startCoEdit()"
                    class="px-4 py-2 text-sm font-semibold text-white primary-bg rounded-lg hover:opacity-90 transition-opacity">
                <i class="fas fa-pen text-xs mr-1"></i> Edit Status
            </button>
            <button type="button" id="coCancelEditBtn" onclick="cancelCoEdit()"
                    class="hidden px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button type="button" id="coSaveBtn" onclick="saveCoStatus()"
                    class="hidden px-4 py-2 text-sm font-semibold text-white primary-bg rounded-lg hover:opacity-90 transition-opacity">
                Save Changes
            </button>
            @endif
            <button type="button" id="coCloseBtn" onclick="closeCoDetail()" class="px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Close</button>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.primary-text { color: var(--primary-color) !important; }
.primary-bg   { background-color: var(--primary-color) !important; }
.co-select {
    height: 2.25rem; padding: 0 0.5rem; font-size: 0.8rem; color: #374151;
    background: #fff; border: 1px solid #d1d5db; border-radius: 0.5rem;
    outline: none; cursor: pointer;
}
.co-select:focus { border-color: #9ca3af; }
/* Frozen-column widths (adjustable at runtime via drag handles). */
#coTable { --w-cust: 200px; --w-proj: 250px; --w-top: 74px; }
.co-th {
    padding: 0.7rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #cbd5e1; border-right: 1px solid #d1d5db;
    background: #f9fafb; position: relative;
}
.co-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    border-right: 1px solid #e5e7eb; vertical-align: middle;
}
/* Frozen columns: Customer + Project Name + TOP No. Widths come from CSS vars
   so the sticky offsets stay aligned when a column is resized. */
.co-sticky-cust, .co-sticky-proj, .co-sticky-top { position: sticky; z-index: 6; }
.co-sticky-cust { left: 0; }
.co-sticky-proj { left: var(--w-cust); }
.co-sticky-top  { left: calc(var(--w-cust) + var(--w-proj)); }
.co-sticky-cust { width: var(--w-cust); min-width: var(--w-cust); max-width: var(--w-cust); }
.co-sticky-proj { width: var(--w-proj); min-width: var(--w-proj); max-width: var(--w-proj); }
.co-sticky-top  { width: var(--w-top);  min-width: var(--w-top);  max-width: var(--w-top); }
/* Vertical divider separating the frozen block from the scrolling months. */
.co-sticky-top { box-shadow: 2px 0 5px -2px rgba(0,0,0,0.12); }
/* Slightly darker shade so the frozen columns read as a distinct group. */
thead .co-sticky-cust, thead .co-sticky-proj, thead .co-sticky-top { background: #e5e7eb; z-index: 26; }
tbody .co-sticky-cust, tbody .co-sticky-proj, tbody .co-sticky-top { background: #f3f4f6; }
tbody tr:hover .co-sticky-cust, tbody tr:hover .co-sticky-proj, tbody tr:hover .co-sticky-top { background: #e9ebef; }
/* Truncate long text to the current column width (ellipsis; full text on hover). */
.co-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.co-sticky-cust .co-name { max-width: calc(var(--w-cust) - 1.8rem); }
.co-sticky-proj .co-name { max-width: calc(var(--w-proj) - 1.8rem); }
/* Drag handle to resize a column. */
.co-resizer { position: absolute; top: 0; right: 0; width: 9px; height: 100%; cursor: col-resize; user-select: none; z-index: 30; }
.co-resizer::after { content: ''; position: absolute; top: 22%; right: 3px; width: 2px; height: 56%; background: #cbd5e1; border-radius: 1px; }
.co-resizer:hover::after { background: var(--primary-color); }
body.co-resizing { cursor: col-resize !important; user-select: none !important; }
/* Amount cell button */
.co-amount-btn {
    display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.5rem;
    border-radius: 0.5rem; font-weight: 600; cursor: pointer; transition: all .15s;
    border: 1px solid transparent;
}
.co-amount-btn:hover { background: #f3f4f6; }
.co-paid   { color: #15803d; }
.co-paid:hover { background: #dcfce7; }
.co-open   { color: #374151; }
.co-delay  { color: #b45309; }
.co-delay:hover { background: #fef3c7; }
</style>
@endpush

@push('scripts')
<script>
const CO_MONTHS_EN = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const CO_CAN_EDIT  = @json($can('reporting.collection-outlook.edit'));
let coRows      = [];
let coMonths    = [];
let coActiveRow = null;   // baris TOP yang sedang dibuka di modal detail

document.addEventListener('DOMContentLoaded', function () {
    // Default range: January to December of the current year
    const fm = document.getElementById('coFromMonth');
    const tm = document.getElementById('coToMonth');
    if (fm) fm.value = 1;
    if (tm) tm.value = 12;
    initColumnResizers();
    loadOutlook();
});

// ── Resizable frozen columns (Customer / Project Name) ────────────────────────
// Drag the handle on a header's right edge to widen or narrow that column so long
// names can be read in full. Handlers are delegated so they survive header re-renders.
function initColumnResizers() {
    let active = null; // { table, varName, startX, startW }

    document.addEventListener('mousedown', function (e) {
        const handle = e.target.closest('.co-resizer');
        if (!handle) return;
        e.preventDefault();
        const table   = document.getElementById('coTable');
        const varName = handle.dataset.col === 'cust' ? '--w-cust' : '--w-proj';
        const startW  = parseInt(getComputedStyle(table).getPropertyValue(varName), 10) || 0;
        active = { table, varName, startX: e.clientX, startW };
        document.body.classList.add('co-resizing');
    });

    document.addEventListener('mousemove', function (e) {
        if (!active) return;
        let w = active.startW + (e.clientX - active.startX);
        w = Math.max(120, Math.min(640, w)); // clamp between 120px and 640px
        active.table.style.setProperty(active.varName, w + 'px');
    });

    document.addEventListener('mouseup', function () {
        if (!active) return;
        active = null;
        document.body.classList.remove('co-resizing');
    });
}

function coParams() {
    const p = new URLSearchParams();
    p.append('from_month', document.getElementById('coFromMonth').value);
    p.append('from_year',  document.getElementById('coFromYear').value);
    p.append('to_month',   document.getElementById('coToMonth').value);
    p.append('to_year',    document.getElementById('coToYear').value);
    return p;
}

async function loadOutlook() {
    const tbody = document.getElementById('coTableBody');
    const empty = document.getElementById('coEmpty');
    const stats = document.getElementById('coStats');
    empty.classList.add('hidden');
    stats.classList.add('hidden');

    tbody.innerHTML = `<tr><td colspan="99" class="px-4 py-14 text-center text-gray-400 text-sm">
        <i class="fas fa-spinner fa-spin text-2xl mb-3 block primary-text opacity-50"></i>
        <span>Loading data...</span>
    </td></tr>`;

    try {
        const res = await fetch(`/api/reporting/collection-outlook?${coParams().toString()}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        coRows   = json.rows   || [];
        coMonths = json.months || [];
        renderOutlook();
    } catch (e) {
        console.error(e);
        tbody.innerHTML = `<tr><td colspan="99" class="px-4 py-10 text-center text-sm">
            <div class="inline-flex flex-col items-center gap-2 text-red-500">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span>${escHtml(e.message)}</span>
            </div>
        </td></tr>`;
    }
}

function renderOutlook() {
    const headRow = document.getElementById('coHeadRow');
    const tbody   = document.getElementById('coTableBody');
    const empty   = document.getElementById('coEmpty');
    const stats   = document.getElementById('coStats');

    // Header: reset to the three frozen columns, then append month columns
    headRow.innerHTML =
        `<th class="co-th co-sticky-cust text-left">Customer<div class="co-resizer" data-col="cust"></div></th>
         <th class="co-th co-sticky-proj text-left">Project Name<div class="co-resizer" data-col="proj"></div></th>
         <th class="co-th co-sticky-top text-center">TOP No.</th>` +
        coMonths.map(m => `<th class="co-th text-right" style="min-width:130px;">${escHtml(monthHeadLabel(m))}</th>`).join('');

    // Range label
    document.getElementById('coRangeLabel').textContent = coMonths.length
        ? `Period ${monthHeadLabel(coMonths[0])} – ${monthHeadLabel(coMonths[coMonths.length - 1])} (${coMonths.length} ${coMonths.length === 1 ? 'month' : 'months'})`
        : "Planned billing based on each project's Term Of Payment";

    if (coRows.length === 0) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        stats.classList.add('hidden');
        return;
    }
    empty.classList.add('hidden');

    let totalAll = 0, totalPaid = 0, totalUnpaid = 0, paidCount = 0, unpaidCount = 0;

    let html = '';
    coRows.forEach(r => {
        totalAll += r.amount;
        if (r.status === 'Paid') { totalPaid += r.amount; paidCount++; }
        else { totalUnpaid += r.amount; unpaidCount++; }

        // Month cells
        let cells = '';
        coMonths.forEach(m => {
            if (m.key === r.month_key) {
                cells += `<td class="co-td text-right">${amountCell(r)}</td>`;
            } else {
                cells += `<td class="co-td text-right text-gray-300">·</td>`;
            }
        });

        const clientName = r.client_name || '—';
        html += `<tr>
            <td class="co-td co-sticky-cust text-left text-gray-600"><span class="co-name" title="${escAttr(clientName)}">${escHtml(clientName)}</span></td>
            <td class="co-td co-sticky-proj text-left font-medium text-gray-800"><span class="co-name" title="${escAttr(r.project_name)}">${escHtml(r.project_name)}</span></td>
            <td class="co-td co-sticky-top text-center text-gray-600">${r.term_number}</td>
            ${cells}
        </tr>`;
    });

    tbody.innerHTML = html;

    // Stats
    document.getElementById('coStatTotal').textContent  = formatShortIDR(totalAll);
    document.getElementById('coStatPaid').textContent   = formatShortIDR(totalPaid);
    document.getElementById('coStatUnpaid').textContent = formatShortIDR(totalUnpaid);
    document.getElementById('coStatTerms').textContent  = coRows.length;
    document.getElementById('coStatPaidCount').textContent   = `${paidCount} ${paidCount === 1 ? 'term' : 'terms'}`;
    document.getElementById('coStatUnpaidCount').textContent = `${unpaidCount} ${unpaidCount === 1 ? 'term' : 'terms'}`;
    stats.classList.remove('hidden');
}

function monthHeadLabel(m) {
    // m.month is 1-based; show short English month + year (e.g. "Jan 2026")
    return `${CO_MONTHS_EN[(m.month - 1)].slice(0, 3)} ${m.year}`;
}

function amountCell(r) {
    const val = formatShortIDR(r.amount);
    if (r.status === 'Paid') {
        return `<button type="button" onclick="openCoDetail(${r.term_id})" class="co-amount-btn co-paid" title="Paid — click for details">
            <i class="fas fa-check-circle text-[11px]"></i>${escHtml(val)}
        </button>`;
    }
    const cls = r.status === 'Delay' ? 'co-delay' : 'co-open';
    const title = r.status === 'Delay' ? 'Delayed — click for details' : 'Unpaid — click for details';
    return `<button type="button" onclick="openCoDetail(${r.term_id})" class="co-amount-btn ${cls}" title="${title}">${escHtml(val)}</button>`;
}

// ── Detail modal ──────────────────────────────────────────────────────────────
function openCoDetail(termId) {
    const r = coRows.find(x => x.term_id === termId);
    if (!r) return;

    coActiveRow = r;
    if (CO_CAN_EDIT) cancelCoEdit();   // selalu buka dalam mode baca

    document.getElementById('coModalTitle').textContent = r.project_name || '—';
    document.getElementById('coModalSub').textContent   = `TOP #${r.term_number} · ${r.payment_term || '-'}`;

    document.getElementById('coDetAmount').textContent = formatFullIDR(r.amount);
    document.getElementById('coDetPct').textContent    = `${fmtPct(r.payment_percentage)}% of revenue`;

    const st = document.getElementById('coDetStatus');
    st.textContent = r.status;
    st.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold ' + statusClasses(r.status);

    document.getElementById('coDetProject').textContent  = r.project_name || '—';
    document.getElementById('coDetClient').textContent   = r.client_name || '—';
    document.getElementById('coDetIo').textContent       = r.io_number || '—';
    document.getElementById('coDetTermNo').textContent   = r.term_number;
    document.getElementById('coDetTermName').textContent = r.payment_term || '—';
    document.getElementById('coDetPctRow').textContent   = `${fmtPct(r.payment_percentage)}%`;
    document.getElementById('coDetRevenue').textContent  = formatFullIDR(r.project_revenue);
    document.getElementById('coDetReq').textContent      = r.requirements || '—';

    document.getElementById('coDetEst').textContent    = r.estimated_date || '—';
    document.getElementById('coDetSubmit').textContent = r.submit_invoice_date || '—';
    document.getElementById('coDetInvNo').textContent  = r.invoice_number || '—';
    document.getElementById('coDetPaid').textContent   = r.paid_date || '—';

    document.getElementById('coDetailModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCoDetail() {
    document.getElementById('coDetailModal').classList.add('hidden');
    document.body.style.overflow = '';
    coActiveRow = null;
}

// ── Edit payment status (izin: reporting.collection-outlook.edit) ─────────────
// Hanya field pelunasan yang bisa diubah di sini. Nominal/percentage tetap milik
// Delivery Information project supaya total termin tidak bisa melampaui revenue.

function startCoEdit() {
    if (!coActiveRow) return;

    document.getElementById('coEditStatus').value     = coActiveRow.status || 'Open';
    document.getElementById('coEditPaidDate').value   = coActiveRow.paid_date_iso || '';
    document.getElementById('coEditSubmitDate').value = coActiveRow.submit_invoice_date_iso || '';
    document.getElementById('coEditInvNo').value      = coActiveRow.invoice_number || '';
    hideCoError();
    toggleCoPaidDate();
    toggleCoInvoiceRequired();

    document.getElementById('coDetailView').classList.add('hidden');
    document.getElementById('coEditForm').classList.remove('hidden');
    document.getElementById('coEditBtn').classList.add('hidden');
    document.getElementById('coCancelEditBtn').classList.remove('hidden');
    document.getElementById('coSaveBtn').classList.remove('hidden');
}

function cancelCoEdit() {
    const form = document.getElementById('coEditForm');
    if (!form) return;
    hideCoError();
    document.getElementById('coDetailView').classList.remove('hidden');
    form.classList.add('hidden');
    document.getElementById('coEditBtn').classList.remove('hidden');
    document.getElementById('coCancelEditBtn').classList.add('hidden');
    document.getElementById('coSaveBtn').classList.add('hidden');
}

// Paid Date wajib saat status Paid — cerminan aturan TOP Plan di Delivery Info.
function toggleCoPaidDate() {
    const isPaid = document.getElementById('coEditStatus').value === 'Paid';
    document.getElementById('coPaidReq').classList.toggle('hidden', !isPaid);
    document.getElementById('coEditPaidDate').required = isPaid;
}

// Invoice Number wajib saat Submit Invoice Date terisi.
function toggleCoInvoiceRequired() {
    const filled = !!document.getElementById('coEditSubmitDate').value;
    document.getElementById('coInvReq').classList.toggle('hidden', !filled);
    document.getElementById('coEditInvNo').required = filled;
}

function showCoError(msg) {
    const el = document.getElementById('coEditError');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function hideCoError() {
    const el = document.getElementById('coEditError');
    if (el) el.classList.add('hidden');
}

async function saveCoStatus() {
    if (!coActiveRow) return;

    const status     = document.getElementById('coEditStatus').value;
    const paidDate   = document.getElementById('coEditPaidDate').value;
    const submitDate = document.getElementById('coEditSubmitDate').value;
    const invNo      = document.getElementById('coEditInvNo').value.trim();

    if (status === 'Paid' && !paidDate) {
        showCoError('Paid Date is required when Status is Paid.');
        return;
    }
    if (submitDate && !invNo) {
        showCoError('Invoice Number is required when Submit Invoice Date is filled.');
        return;
    }

    const btn = document.getElementById('coSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    try {
        const res = await fetch(`/api/reporting/collection-outlook/terms/${coActiveRow.term_id}`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                status:              status,
                paid_date:           paidDate || null,
                submit_invoice_date: submitDate || null,
                invoice_number:      invNo || null,
            }),
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Failed to save.');

        // Perbarui baris di memori lalu render ulang matrix + stats, supaya
        // pewarnaan hijau/kuning dan total Paid/Unpaid langsung ikut berubah.
        Object.assign(coActiveRow, {
            status:                  json.term.status,
            paid_date:               json.term.paid_date,
            paid_date_iso:           paidDate || null,
            submit_invoice_date:     json.term.submit_invoice_date,
            submit_invoice_date_iso: submitDate || null,
            invoice_number:          json.term.invoice_number,
        });
        renderOutlook();
        openCoDetail(coActiveRow.term_id);
    } catch (e) {
        showCoError(e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeCoDetail();
});

function statusClasses(status) {
    if (status === 'Paid')  return 'bg-green-100 text-green-700';
    if (status === 'Delay') return 'bg-amber-100 text-amber-700';
    return 'bg-gray-100 text-gray-600';
}

// ── Formatters ────────────────────────────────────────────────────────────────
// Short Indonesian-Rupiah style (matches the design spec): Rp5,42 M (Miliar / billion),
// Rp800 Jt (Juta / million), Rp x Rb (Ribu / thousand), comma as decimal separator.
function formatShortIDR(value) {
    const v = Number(value) || 0;
    if (v === 0) return 'Rp0';
    const sign = v < 0 ? '-' : '';
    const abs = Math.abs(v);
    if (abs >= 1e12) return `${sign}Rp${trimNum(abs / 1e12)} T`;
    if (abs >= 1e9)  return `${sign}Rp${trimNum(abs / 1e9)} M`;
    if (abs >= 1e6)  return `${sign}Rp${trimNum(abs / 1e6)} Jt`;
    if (abs >= 1e3)  return `${sign}Rp${trimNum(abs / 1e3)} Rb`;
    return `${sign}Rp${Math.round(abs)}`;
}

// Whole number → no decimals (800); otherwise 2 decimals with comma (5,42 / 2,50)
function trimNum(n) {
    if (Number.isInteger(n)) return String(n);
    return n.toFixed(2).replace('.', ',');
}

function formatFullIDR(value) {
    const v = Math.round(Number(value) || 0);
    return 'Rp ' + v.toLocaleString('id-ID');
}

function fmtPct(n) {
    const v = Number(n) || 0;
    return (Number.isInteger(v) ? String(v) : v.toFixed(2)).replace('.', ',');
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function escAttr(str) {
    return escHtml(str).replace(/"/g, '&quot;');
}
</script>
@endpush
