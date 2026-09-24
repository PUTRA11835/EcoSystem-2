@extends('dashboard')
@section('title', 'Log Shifting')
@section('page-title', 'Log Shifting')
@section('page-subtitle', 'Export SLA notes per shift & ringkasan tiket')

@section('content')

{{-- ============================================================ --}}
{{-- Export Log Shifting: flat per-SLA-note, filtered by date(+optional hour) range --}}
{{-- ============================================================ --}}
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex items-start gap-3 mb-5 pb-4 border-b-2 border-gray-100">
        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center text-red-800 shrink-0">
            <i class="fas fa-file-export"></i>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Export Log Shifting</h2>
            <p class="text-sm text-gray-500 mt-0.5">Rekap SLA note antar-shift dalam rentang tanggal &amp; jam tertentu — siap diexport ke Excel sebagai laporan.</p>
        </div>
    </div>

    {{-- Quick date presets --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mr-1">Cepat:</span>
        <button type="button" onclick="lsnPreset('today')" class="lsn-preset-btn">Hari Ini</button>
        <button type="button" onclick="lsnPreset('yesterday')" class="lsn-preset-btn">Kemarin</button>
        <button type="button" onclick="lsnPreset('7d')" class="lsn-preset-btn">7 Hari Terakhir</button>
        <button type="button" onclick="lsnPreset('month')" class="lsn-preset-btn">Bulan Ini</button>
    </div>

    {{-- Filter bar --}}
    <div class="flex flex-wrap items-end gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Dari Tanggal <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-1">
                <input type="date" id="lsnDateFrom" oninput="lsnClearMsg()"
                    class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                <button type="button" onclick="lsnClearField('lsnDateFrom')" class="lsn-clear-btn" title="Reset Dari Tanggal" aria-label="Reset Dari Tanggal">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Jam <span class="normal-case text-gray-400">(opsional)</span></label>
            <div class="flex items-center gap-1">
                <input type="time" id="lsnTimeFrom" oninput="lsnClearMsg()"
                    class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                <button type="button" onclick="lsnClearField('lsnTimeFrom')" class="lsn-clear-btn" title="Reset Jam Dari" aria-label="Reset Jam Dari">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </div>
        <div class="hidden sm:flex items-center justify-center pb-2.5 text-gray-300">
            <i class="fas fa-arrow-right"></i>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Sampai Tanggal <span class="text-red-500">*</span></label>
            <div class="flex items-center gap-1">
                <input type="date" id="lsnDateTo" oninput="lsnClearMsg()"
                    class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                <button type="button" onclick="lsnClearField('lsnDateTo')" class="lsn-clear-btn" title="Reset Sampai Tanggal" aria-label="Reset Sampai Tanggal">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Jam <span class="normal-case text-gray-400">(opsional)</span></label>
            <div class="flex items-center gap-1">
                <input type="time" id="lsnTimeTo" oninput="lsnClearMsg()"
                    class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                <button type="button" onclick="lsnClearField('lsnTimeTo')" class="lsn-clear-btn" title="Reset Jam Sampai" aria-label="Reset Jam Sampai">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
        </div>

        <div class="flex gap-2 ml-auto">
            <button type="button" id="lsnResetAllBtn" onclick="lsnResetAll()"
                class="px-3 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-semibold rounded-md hover:bg-gray-100 transition-colors" title="Reset semua field sekaligus">
                <i class="fas fa-rotate-left mr-1.5"></i>Reset Semua
            </button>
            <button type="button" id="lsnSearchBtn" onclick="lsLoadNotes()"
                class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-md hover:bg-red-900 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fas fa-search mr-1.5"></i>Tampilkan
            </button>
            <button type="button" id="lsnExportBtn" onclick="lsExportNotes()"
                class="px-4 py-2 bg-white border border-red-800 text-red-800 text-sm font-semibold rounded-md hover:bg-red-50 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fas fa-file-excel mr-1.5"></i>Export Excel
            </button>
        </div>
    </div>

    <div id="lsnMsg" class="hidden mt-3 text-sm text-red-600 flex items-center gap-1.5">
        <i class="fas fa-circle-exclamation"></i><span id="lsnMsgText"></span>
    </div>

    {{-- Result summary --}}
    <div id="lsnSummary" class="mt-5 mb-3 text-sm text-gray-500">
        Pilih rentang tanggal lalu klik <strong>Tampilkan</strong> untuk memuat SLA note. Klik salah satu baris hasil untuk membuka tiketnya di tab baru.
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto border border-gray-200 rounded-xl">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="ls-th text-left">No Tiket</th>
                    <th class="ls-th text-left">Deskripsi</th>
                    <th class="ls-th text-left">Tanggal</th>
                    <th class="ls-th text-left">Jam</th>
                    <th class="ls-th text-left">SLA Note</th>
                    <th class="ls-th text-left">PIC</th>
                </tr>
            </thead>
            <tbody id="lsnTableBody">
                <tr><td colspan="6" class="text-center py-10 text-gray-400 text-sm">Belum ada data — pilih rentang tanggal di atas.</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ============================================================ --}}
{{-- Ringkasan per tiket (fitur lama): daftar tiket yang pernah punya SLA note --}}
{{-- ============================================================ --}}
<div class="bg-white rounded-xl p-6 shadow-sm mt-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Ringkasan per Tiket</h2>
            <p class="text-sm text-gray-500 mt-0.5">Semua tiket yang pernah punya SLA message. Klik baris untuk lihat seluruh catatannya (tanpa filter tanggal).</p>
        </div>
    </div>

    {{-- Summary bar --}}
    <div id="lsSummary" class="mb-4">
        <span id="lsSummaryText" class="text-sm text-gray-500">Loading...</span>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto border border-gray-200 rounded-xl">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50">
                        <button type="button" id="lsColBtn-ticket" onclick="lsToggleColFilter('ticket', event)"
                            class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">No Tiket</span>
                            <svg id="lsColIcon-ticket" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="lsColPanel-ticket" class="hidden bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search ticket number</label>
                            <input type="text" id="lsColInput-ticket" placeholder="e.g. 8000004211…"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                oninput="lsOnColFilterInput('ticket')">
                            <div class="flex justify-end gap-2 mt-3">
                                <button type="button" onclick="lsClearColFilter('ticket')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                            </div>
                        </div>
                    </th>
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50">
                        <button type="button" id="lsColBtn-desc" onclick="lsToggleColFilter('desc', event)"
                            class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Description</span>
                            <svg id="lsColIcon-desc" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="lsColPanel-desc" class="hidden bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:260px;">
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search description</label>
                            <input type="text" id="lsColInput-desc" placeholder="Type keyword (case-insensitive)…"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                oninput="lsOnColFilterInput('desc')">
                            <div class="flex justify-end gap-2 mt-3">
                                <button type="button" onclick="lsClearColFilter('desc')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                            </div>
                        </div>
                    </th>
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50">
                        <button type="button" id="lsColBtn-pic" onclick="lsToggleColFilter('pic', event)"
                            class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">PIC</span>
                            <svg id="lsColIcon-pic" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="lsColPanel-pic" class="hidden bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search PIC</label>
                            <input type="text" id="lsColInput-pic" placeholder="Type a name…"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400"
                                oninput="lsOnColFilterInput('pic')">
                            <div class="flex justify-end gap-2 mt-3">
                                <button type="button" onclick="lsClearColFilter('pic')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                            </div>
                        </div>
                    </th>
                    <th class="p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50">
                        <button type="button" id="lsColBtn-date" onclick="lsToggleColFilter('date', event)"
                            class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors">
                            <span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Created At</span>
                            <svg id="lsColIcon-date" class="w-3.5 h-3.5 text-gray-300 transition-colors ml-auto shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="lsColPanel-date" class="hidden bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
                            <div class="space-y-2">
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">From</label>
                                    <input type="date" id="lsDateFrom" onchange="lsOnColFilterInput('date')"
                                        class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">To</label>
                                    <input type="date" id="lsDateTo" onchange="lsOnColFilterInput('date')"
                                        class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-3">
                                <button type="button" onclick="lsClearColFilter('date')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
                            </div>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody id="lsTableBody">
                {{-- Rendered by JS --}}
            </tbody>
        </table>
    </div>

    {{-- Empty state --}}
    <div id="lsEmpty" class="hidden py-12 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-14 h-14 mx-auto mb-4 text-gray-300">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
        </svg>
        <p class="text-base font-medium text-gray-900 mb-1">No tickets with an SLA message found</p>
    </div>
</div>

{{-- Detail modal --}}
<div id="lsDetailModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full max-h-[85vh] shadow-2xl overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-900" id="lsModalTicketNumber">—</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="lsModalTicketDesc">—</p>
            </div>
            <button onclick="closeLsDetailModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="overflow-y-auto flex-1">
            <table class="w-full">
                <thead class="sticky top-0 bg-white z-10">
                    <tr>
                        <th class="ls-th text-left">Date</th>
                        <th class="ls-th text-left">Time</th>
                        <th class="ls-th text-left">SLA Message</th>
                        <th class="ls-th text-left">PIC</th>
                    </tr>
                </thead>
                <tbody id="lsModalBody">
                    {{-- Rendered by JS --}}
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.ls-th {
    padding: 0.65rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}
.ls-td {
    padding: 0.65rem 0.9rem; white-space: nowrap; border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
}
.ls-td-desc { white-space: normal; max-width: 420px; }
.ls-td-msg { white-space: pre-wrap; max-width: 380px; }

.lsn-preset-btn {
    padding: 0.35rem 0.9rem; border-radius: 9999px; font-size: 12px; font-weight: 600;
    border: 1px solid #e5e7eb; color: #4b5563; background: #fff; cursor: pointer;
    transition: all .15s;
}
.lsn-preset-btn:hover { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

.lsn-clear-btn {
    width: 30px; height: 38px; display: flex; align-items: center; justify-content: center;
    border: 1px solid #e5e7eb; border-radius: 0.375rem; background: #fff; color: #9ca3af;
    font-size: 11px; cursor: pointer; transition: all .15s; flex-shrink: 0;
}
.lsn-clear-btn:hover { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', loadLogShifting);

let lsAllTickets = [];

async function loadLogShifting() {
    const body    = document.getElementById('lsTableBody');
    const empty   = document.getElementById('lsEmpty');
    const summary = document.getElementById('lsSummaryText');
    empty.classList.add('hidden');

    try {
        const res = await fetch('/api/reporting/log-shifting', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        lsAllTickets = json.data || [];
        renderLogShifting(lsAllTickets);
    } catch (e) {
        console.error(e);
        summary.textContent = 'Failed to load data.';
        body.innerHTML = `<tr><td colspan="4" class="text-center py-10 text-red-500 text-sm">
            <i class="fas fa-exclamation-circle text-xl block mb-2"></i>${escHtml(e.message)}
        </td></tr>`;
    }
}

// ── Per-column header filters (ticket number / description / PIC / date range) ──
const lsFilters = { ticket: '', desc: '', pic: '', dateFrom: '', dateTo: '' };
let _lsFilterTimer = null;
const LS_COLS = ['ticket', 'desc', 'pic', 'date'];

function lsToggleColFilter(col, ev) {
    ev?.stopPropagation();
    const panel = document.getElementById(`lsColPanel-${col}`);
    const btn   = document.getElementById(`lsColBtn-${col}`);
    const open  = panel.dataset.open === '1';

    LS_COLS.forEach(c => lsClosePanel(c));
    if (open) return;

    if (panel.parentElement !== document.body) document.body.appendChild(panel);
    const rect = btn.getBoundingClientRect();
    panel.style.position = 'fixed';
    panel.style.top  = (rect.bottom + 4) + 'px';
    panel.style.left = rect.left + 'px';
    panel.classList.remove('hidden');
    panel.dataset.open = '1';
    document.getElementById(`lsColInput-${col}`)?.focus();
}

function lsClosePanel(col) {
    const panel = document.getElementById(`lsColPanel-${col}`);
    if (panel) {
        panel.classList.add('hidden');
        panel.dataset.open = '0';
    }
}

document.addEventListener('click', (e) => {
    LS_COLS.forEach(col => {
        const panel = document.getElementById(`lsColPanel-${col}`);
        const btn   = document.getElementById(`lsColBtn-${col}`);
        if (panel && panel.dataset.open === '1' && !panel.contains(e.target) && !btn.contains(e.target)) {
            lsClosePanel(col);
        }
    });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') LS_COLS.forEach(col => lsClosePanel(col));
});

function lsOnColFilterInput(col) {
    clearTimeout(_lsFilterTimer);
    _lsFilterTimer = setTimeout(() => lsApplyFilters(), 200);
}

function lsClearColFilter(col) {
    if (col === 'date') {
        document.getElementById('lsDateFrom').value = '';
        document.getElementById('lsDateTo').value = '';
    } else {
        const input = document.getElementById(`lsColInput-${col}`);
        if (input) input.value = '';
    }
    lsApplyFilters();
}

function lsUpdateColIndicators() {
    const active = {
        ticket: lsFilters.ticket !== '',
        desc:   lsFilters.desc !== '',
        pic:    lsFilters.pic !== '',
        date:   lsFilters.dateFrom !== '' || lsFilters.dateTo !== '',
    };
    LS_COLS.forEach(col => {
        const icon = document.getElementById(`lsColIcon-${col}`);
        if (!icon) return;
        icon.classList.toggle('text-red-500', active[col]);
        icon.classList.toggle('text-gray-300', !active[col]);
    });
}

function lsApplyFilters() {
    lsFilters.ticket   = (document.getElementById('lsColInput-ticket')?.value || '').trim().toLowerCase();
    lsFilters.desc     = (document.getElementById('lsColInput-desc')?.value || '').trim().toLowerCase();
    lsFilters.pic      = (document.getElementById('lsColInput-pic')?.value || '').trim().toLowerCase();
    lsFilters.dateFrom = document.getElementById('lsDateFrom')?.value || '';
    lsFilters.dateTo   = document.getElementById('lsDateTo')?.value || '';
    lsUpdateColIndicators();

    const hasFilter = lsFilters.ticket || lsFilters.desc || lsFilters.pic || lsFilters.dateFrom || lsFilters.dateTo;

    const filtered = lsAllTickets.filter(t => {
        if (lsFilters.ticket && !(t.ticket_number || '').toLowerCase().includes(lsFilters.ticket)) return false;
        if (lsFilters.desc && !(t.description || '').toLowerCase().includes(lsFilters.desc)) return false;
        if (lsFilters.pic && !((t.pic || 'unknown').toLowerCase().includes(lsFilters.pic))) return false;
        if (t.created_at) {
            const d = t.created_at.slice(0, 10); // YYYY-MM-DD
            if (lsFilters.dateFrom && d < lsFilters.dateFrom) return false;
            if (lsFilters.dateTo && d > lsFilters.dateTo) return false;
        } else if (lsFilters.dateFrom || lsFilters.dateTo) {
            return false;
        }
        return true;
    });

    renderLogShifting(filtered, hasFilter);
}

function renderLogShifting(tickets, searchActive) {
    const body    = document.getElementById('lsTableBody');
    const empty   = document.getElementById('lsEmpty');
    const summary = document.getElementById('lsSummaryText');

    if (!tickets.length) {
        body.innerHTML = '';
        empty.classList.remove('hidden');
        summary.textContent = searchActive ? 'No tickets match your filters.' : 'No tickets found.';
        return;
    }
    empty.classList.add('hidden');
    summary.textContent = `${tickets.length} ticket${tickets.length !== 1 ? 's' : ''}`
        + (searchActive ? ` (filtered from ${lsAllTickets.length})` : '');

    body.innerHTML = tickets.map(t => {
        const dateStr = t.created_at
            ? new Date(t.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
            : '—';
        return `
        <tr class="cursor-pointer hover:bg-gray-50" onclick="openLsDetailModal(${t.ticket_id})">
            <td class="ls-td text-sm font-semibold text-gray-700">${escHtml(t.ticket_number || '—')}</td>
            <td class="ls-td ls-td-desc text-sm text-gray-700">${escHtml(t.description || '—')}</td>
            <td class="ls-td text-sm text-gray-700">${t.pic ? escHtml(t.pic) : '<span class="text-gray-300 italic">Unknown</span>'}</td>
            <td class="ls-td text-xs text-gray-500">${dateStr}</td>
        </tr>`;
    }).join('');
}

async function openLsDetailModal(ticketId) {
    const modal = document.getElementById('lsDetailModal');
    const body  = document.getElementById('lsModalBody');
    document.getElementById('lsModalTicketNumber').textContent = 'Loading...';
    document.getElementById('lsModalTicketDesc').textContent   = '';
    body.innerHTML = `<tr><td colspan="4" class="text-center py-10 text-gray-400 text-sm">Loading...</td></tr>`;
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    try {
        const res = await fetch(`/api/reporting/log-shifting/${ticketId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load detail');

        const { ticket, messages } = json.data;
        document.getElementById('lsModalTicketNumber').textContent = ticket.ticket_number || '—';
        document.getElementById('lsModalTicketDesc').textContent   = ticket.description || '—';

        if (!messages.length) {
            body.innerHTML = `<tr><td colspan="4" class="text-center py-10 text-gray-400 text-sm">No SLA messages found.</td></tr>`;
            return;
        }

        body.innerHTML = messages.map(m => {
            const bubble = m.bubble_date ? new Date(m.bubble_date) : null;
            const dateStr = bubble ? bubble.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
            const timeStr = bubble ? bubble.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' WIB' : '—';
            return `
            <tr>
                <td class="ls-td text-xs text-gray-500">${dateStr}</td>
                <td class="ls-td text-xs text-gray-500">${timeStr}</td>
                <td class="ls-td ls-td-msg text-sm text-gray-700">${escHtml(m.sla_message || '—')}</td>
                <td class="ls-td text-sm text-gray-700">${m.sla_message_by ? escHtml(m.sla_message_by) : '<span class="text-gray-300 italic">Unknown</span>'}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        console.error(e);
        body.innerHTML = `<tr><td colspan="4" class="text-center py-10 text-red-500 text-sm">
            <i class="fas fa-exclamation-circle text-xl block mb-2"></i>${escHtml(e.message)}
        </td></tr>`;
    }
}

function closeLsDetailModal() {
    const modal = document.getElementById('lsDetailModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Export Log Shifting: flat SLA notes filtered by date(+optional hour) range ──

function lsnPad(n) { return String(n).padStart(2, '0'); }
function lsnFmtDate(d) { return `${d.getFullYear()}-${lsnPad(d.getMonth() + 1)}-${lsnPad(d.getDate())}`; }

function lsnPreset(kind) {
    const today = new Date();
    let from, to;

    if (kind === 'today') {
        from = to = lsnFmtDate(today);
    } else if (kind === 'yesterday') {
        const y = new Date(today); y.setDate(y.getDate() - 1);
        from = to = lsnFmtDate(y);
    } else if (kind === '7d') {
        const s = new Date(today); s.setDate(s.getDate() - 6);
        from = lsnFmtDate(s); to = lsnFmtDate(today);
    } else if (kind === 'month') {
        from = lsnFmtDate(new Date(today.getFullYear(), today.getMonth(), 1));
        to   = lsnFmtDate(today);
    } else {
        return;
    }

    document.getElementById('lsnDateFrom').value = from;
    document.getElementById('lsnDateTo').value   = to;
    document.getElementById('lsnTimeFrom').value = '';
    document.getElementById('lsnTimeTo').value   = '';
    lsLoadNotes();
}

function lsnClearMsg() {
    document.getElementById('lsnMsg').classList.add('hidden');
}

// Reset satu field saja (kalender atau jam) — independen, tidak menyentuh field lain.
function lsnClearField(id) {
    const el = document.getElementById(id);
    if (el) el.value = '';
    lsnClearMsg();
}

// Reset semua field sekaligus, untuk yang mau mulai dari kosong lagi.
function lsnResetAll() {
    ['lsnDateFrom', 'lsnTimeFrom', 'lsnDateTo', 'lsnTimeTo'].forEach(id => {
        document.getElementById(id).value = '';
    });
    lsnClearMsg();
}

function lsnShowMsg(text) {
    document.getElementById('lsnMsgText').textContent = text;
    document.getElementById('lsnMsg').classList.remove('hidden');
}

function lsnBuildQuery() {
    const dateFrom = document.getElementById('lsnDateFrom').value;
    const dateTo   = document.getElementById('lsnDateTo').value;
    const timeFrom = document.getElementById('lsnTimeFrom').value;
    const timeTo   = document.getElementById('lsnTimeTo').value;

    if (!dateFrom || !dateTo) {
        lsnShowMsg('Tanggal dari dan sampai wajib diisi.');
        return null;
    }
    if (dateFrom > dateTo || (dateFrom === dateTo && timeFrom && timeTo && timeFrom > timeTo)) {
        lsnShowMsg('Tanggal/jam "dari" tidak boleh setelah "sampai".');
        return null;
    }
    lsnClearMsg();

    const params = new URLSearchParams({ date_from: dateFrom, date_to: dateTo });
    if (timeFrom) params.set('time_from', timeFrom);
    if (timeTo)   params.set('time_to', timeTo);
    return { params, dateFrom, dateTo, timeFrom, timeTo };
}

function lsnRangeLabel(q) {
    const fromStr = `${q.dateFrom.split('-').reverse().join('/')}${q.timeFrom ? ' ' + q.timeFrom : ' 00:00'}`;
    const toStr   = `${q.dateTo.split('-').reverse().join('/')}${q.timeTo ? ' ' + q.timeTo : ' 23:59'}`;
    return `${fromStr} — ${toStr}`;
}

async function lsLoadNotes() {
    const q = lsnBuildQuery();
    if (!q) return;

    const body       = document.getElementById('lsnTableBody');
    const summary     = document.getElementById('lsnSummary');
    const searchBtn   = document.getElementById('lsnSearchBtn');

    searchBtn.disabled = true;
    searchBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Memuat...';
    body.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400 text-sm">Loading...</td></tr>`;

    try {
        const res = await fetch(`/api/reporting/log-shifting/notes?${q.params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load data');

        const rows = json.data || [];
        renderLsNotes(rows);
        summary.innerHTML = rows.length
            ? `<strong class="text-gray-900">${rows.length}</strong> SLA note ditemukan &middot; Periode <strong class="text-gray-900">${escHtml(lsnRangeLabel(q))}</strong> WIB`
            : `Tidak ada SLA note pada periode <strong class="text-gray-900">${escHtml(lsnRangeLabel(q))}</strong> WIB.`;
    } catch (e) {
        console.error(e);
        lsnShowMsg(e.message);
        summary.textContent = 'Gagal memuat data.';
        body.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-red-500 text-sm">
            <i class="fas fa-exclamation-circle text-xl block mb-2"></i>${escHtml(e.message)}
        </td></tr>`;
    } finally {
        searchBtn.disabled = false;
        searchBtn.innerHTML = '<i class="fas fa-search mr-1.5"></i>Tampilkan';
    }
}

function renderLsNotes(rows) {
    const body = document.getElementById('lsnTableBody');

    if (!rows.length) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400 text-sm">Tidak ada SLA note pada rentang tanggal/jam ini.</td></tr>`;
        return;
    }

    body.innerHTML = rows.map(r => {
        const bubble = r.bubble_date ? new Date(r.bubble_date) : null;
        const dateStr = bubble ? bubble.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
        const timeStr = bubble ? bubble.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false }) + ' WIB' : '—';
        return `
        <tr class="cursor-pointer hover:bg-gray-50" title="Klik untuk membuka tiket di tab baru" onclick="window.open('/ticket/${r.ticket_id}', '_blank')">
            <td class="ls-td text-sm font-semibold text-gray-700">${escHtml(r.ticket_number || '—')}</td>
            <td class="ls-td ls-td-desc text-sm text-gray-700">${escHtml(r.description || '—')}</td>
            <td class="ls-td text-xs text-gray-500">${dateStr}</td>
            <td class="ls-td text-xs text-gray-500">${timeStr}</td>
            <td class="ls-td ls-td-msg text-sm text-gray-700">${escHtml(r.sla_message || '—')}</td>
            <td class="ls-td text-sm text-gray-700">${r.pic ? escHtml(r.pic) : '<span class="text-gray-300 italic">Unknown</span>'}</td>
        </tr>`;
    }).join('');
}

function lsExportNotes() {
    const q = lsnBuildQuery();
    if (!q) return;
    window.location.href = `/reporting/log-shifting/export?${q.params.toString()}`;
}
</script>
@endpush
