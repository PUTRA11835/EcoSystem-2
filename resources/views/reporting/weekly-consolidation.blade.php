@extends('dashboard')
@section('title', 'Weekly Consolidation')
@section('page-title', 'Weekly Consolidation')
@section('page-subtitle', 'Weekly ticket recon per module for the support team')

@section('content')

{{-- ============================================================ --}}
{{-- Recon history — di atas supaya siapa pun yang buka halaman ini untuk
     cek status recon yang sudah ada bisa langsung lihat tanpa scroll dulu
     lewat panel Generate/Edit di bawahnya. --}}
{{-- ============================================================ --}}
<div class="bg-white rounded-xl p-6 shadow-sm">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900" id="wcHistoryTitle">Recon History</h2>
            <p class="text-sm text-gray-500 mt-0.5" id="wcHistorySubtitle">All Weekly Consolidation batches ever generated. Click "Open" to view or continue editing.</p>
        </div>
        <button type="button" id="wcShowDeletedBtn" onclick="wcToggleShowDeleted()" class="hidden px-3 py-1.5 bg-white border border-gray-300 text-gray-600 text-xs font-semibold rounded-md hover:bg-gray-100 transition-colors">
            <i class="fas fa-trash-can mr-1"></i><span id="wcShowDeletedLabel">Show Deleted</span>
        </button>
    </div>

    <div class="overflow-x-auto border border-gray-200 rounded-xl">
        <table class="w-full">
            <thead>
                <tr>
                    <th class="wc-th text-left">Module</th>
                    <th class="wc-th text-left">Period</th>
                    <th class="wc-th text-left">Tickets</th>
                    <th class="wc-th text-left">Generated</th>
                    <th class="wc-th text-left">Last Refreshed</th>
                    <th class="wc-th text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="wcHistoryBody">
                <tr><td colspan="6" class="text-center py-10 text-gray-400 text-sm">Loading history...</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ============================================================ --}}
{{-- View / Generate / Refresh / Export panel --}}
{{-- ============================================================ --}}
<div class="bg-white rounded-xl p-5 shadow-sm mt-6">

    <div class="flex items-start gap-3 mb-4 pb-3 border-b-2 border-gray-100">
        <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center text-red-800 shrink-0 text-sm">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-900">Weekly Consolidation</h2>
            <p class="text-sm text-gray-500 mt-0.5">Pick a module to view its open tickets, then generate a saved recon once you're ready.</p>
        </div>
    </div>

    {{-- Step 1: pick module(s), tickets auto-load on selection --}}
    <div class="flex flex-wrap items-end gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
        <div class="flex-1 min-w-[220px]">
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Module <span class="text-red-500">*</span></label>
            <div id="wcModuleField"><span class="text-sm text-gray-400">Loading modules...</span></div>
        </div>

        <div class="flex gap-2">
            <button type="button" id="wcViewBtn" onclick="wcViewTickets()" disabled
                class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-md hover:bg-red-900 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fas fa-eye mr-1.5"></i>View Tickets
            </button>
        </div>
    </div>

    <div id="wcMsg" class="hidden mt-3 text-sm text-red-600 flex items-center gap-1.5">
        <i class="fas fa-circle-exclamation"></i><span id="wcMsgText"></span>
    </div>

    {{-- Step 2: preview banner + period inputs + Generate (shown after tickets are viewed) --}}
    <div id="wcGenerateBar" class="hidden mt-4 flex flex-wrap items-end gap-3 p-4 bg-yellow-50 rounded-xl border border-yellow-200">
        <div class="text-xs text-yellow-800 flex items-center gap-1.5 mr-2">
            <i class="fas fa-triangle-exclamation"></i>
            <span>Preview only — nothing is saved yet.</span>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period Start <span class="text-red-500">*</span></label>
            <input type="date" id="wcPeriodStart" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period End <span class="text-red-500">*</span></label>
            <input type="date" id="wcPeriodEnd" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period Label <span class="normal-case text-gray-400">(optional)</span></label>
            <input type="text" id="wcPeriodLabel" placeholder="e.g. Week of Sep 23-27, 2026"
                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <button type="button" id="wcGenerateBtn" onclick="wcGenerate()"
            class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-md hover:bg-red-900 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
            <i class="fas fa-floppy-disk mr-1.5"></i>Generate Recon
        </button>
    </div>

    {{-- Step 3: saved-batch action bar (Refresh / Export / Edit / Delete) --}}
    <div id="wcBatchBar" class="hidden mt-4 flex flex-wrap items-center gap-3 p-4 bg-green-50 rounded-xl border border-green-200">
        <div class="text-sm text-green-800 flex items-center gap-1.5">
            <i class="fas fa-circle-check"></i>
            <span id="wcBatchBarText">Recon saved.</span>
        </div>
        <div class="flex gap-2 ml-auto">
            <button type="button" id="wcRefreshBtn" onclick="wcRefresh()"
                class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-semibold rounded-md hover:bg-gray-100 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                <i class="fas fa-rotate mr-1.5"></i>Refresh
            </button>
            <a href="#" id="wcExportBtn"
                class="px-4 py-2 bg-white border border-red-800 text-red-800 text-sm font-semibold rounded-md hover:bg-red-50 transition-colors">
                <i class="fas fa-file-excel mr-1.5"></i>Export Excel
            </a>
            <button type="button" id="wcEditBtn" onclick="wcEnterEditMode()" class="hidden px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-semibold rounded-md hover:bg-gray-100 transition-colors">
                <i class="fas fa-pen mr-1.5"></i>Edit
            </button>
            <button type="button" id="wcDeleteBtn" onclick="wcDeleteBatch()" class="hidden px-4 py-2 bg-white border border-red-300 text-red-700 text-sm font-semibold rounded-md hover:bg-red-50 transition-colors">
                <i class="fas fa-trash mr-1.5"></i>Delete
            </button>
            <button type="button" onclick="wcCloseBatch()" title="Stop viewing this recon and start a new one"
                class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-semibold rounded-md hover:bg-gray-100 transition-colors">
                <i class="fas fa-xmark mr-1.5"></i>Close
            </button>
        </div>
    </div>

    {{-- Step 3b: edit-mode bar (period fields + Save/Cancel) — module chips above stay interactive while this is open --}}
    <div id="wcEditBar" class="hidden mt-4 flex flex-wrap items-end gap-3 p-4 bg-blue-50 rounded-xl border border-blue-200">
        <div class="text-xs text-blue-800 flex items-center gap-1.5 mr-2 basis-full">
            <i class="fas fa-pen"></i>
            <span>Editing this recon — add/remove modules above, adjust the period, then Save. Removing a module deletes its ticket rows and Notes from this recon.</span>
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period Start <span class="text-red-500">*</span></label>
            <input type="date" id="wcEditPeriodStart" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period End <span class="text-red-500">*</span></label>
            <input type="date" id="wcEditPeriodEnd" class="px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <div class="flex-1 min-w-[180px]">
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Period Label <span class="normal-case text-gray-400">(optional)</span></label>
            <input type="text" id="wcEditPeriodLabel" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <button type="button" id="wcSaveEditBtn" onclick="wcSaveEdit()"
            class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-md hover:bg-red-900 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
            <i class="fas fa-floppy-disk mr-1.5"></i>Save Changes
        </button>
        <button type="button" onclick="wcCancelEdit()"
            class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-semibold rounded-md hover:bg-gray-100 transition-colors">
            Cancel
        </button>
    </div>

    {{-- Result summary --}}
    <div id="wcResultSummary" class="mt-5 mb-3 text-sm text-gray-500">
        Select a module and click <strong>View Tickets</strong> to see its open tickets.
    </div>

    {{-- Ticket table --}}
    <div class="overflow-x-auto border border-gray-200 rounded-xl">
        <table class="w-full" id="wcTicketTable">
            <thead>
                <tr>
                    <th class="wc-th text-left" data-filter-key="last_update" style="min-width:150px;">Last Update</th>
                    <th class="wc-th text-left" data-filter-key="ticket_number" style="min-width:130px;">Ticket</th>
                    <th class="wc-th text-left" data-filter-key="description" style="min-width:220px;">Description</th>
                    <th class="wc-th text-left" data-filter-key="start_date" style="min-width:150px;">Start Date</th>
                    <th class="wc-th text-left" data-filter-key="ticket_type" style="min-width:120px;">Type</th>
                    <th class="wc-th text-left" data-filter-key="status" style="min-width:140px;">Status</th>
                    <th class="wc-th text-left" data-filter-key="module_name" style="min-width:110px;">Module</th>
                    <th class="wc-th text-left" data-filter-key="lead_member" style="min-width:160px;">Lead &amp; Member</th>
                    <th class="wc-th text-left" data-filter-key="pic" style="min-width:120px;">PIC</th>
                    <th class="wc-th text-left" data-filter-key="progress" style="min-width:100px;">Progress</th>
                    <th class="wc-th text-left" data-filter-key="deliverable" style="min-width:110px;">Deliverable</th>
                    <th class="wc-th text-left" data-filter-key="notes" style="min-width:220px;">Notes</th>
                </tr>
            </thead>
            <tbody id="wcTableBody">
                <tr><td colspan="12" class="text-center py-10 text-gray-400 text-sm">No tickets loaded yet.</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Ticket quick-view modal (left click on a ticket number) --}}
<div id="wcTicketModal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <div>
                <h3 class="text-base font-bold text-gray-900" id="wcModalTicketNumber">—</h3>
                <div id="wcModalStatusBadge" class="mt-1"></div>
            </div>
            <button onclick="wcCloseTicketModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="p-6 space-y-3 text-sm text-gray-700" id="wcModalBody"></div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
            <a id="wcModalFullLink" href="#" target="_blank" class="text-red-800 font-semibold text-sm hover:underline">
                Open full ticket page <i class="fas fa-arrow-up-right-from-square ml-1"></i>
            </a>
        </div>
    </div>
</div>

{{-- Note editor modal (expand icon next to the inline Notes textarea) --}}
<div id="wcNoteModal" class="hidden fixed inset-0 bg-black/50 z-[60] items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-xl w-full shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 class="text-base font-bold text-gray-900">Notes — <span id="wcNoteModalTicketNumber">—</span></h3>
            <button onclick="wcCloseNoteModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
        <div class="p-6">
            <textarea id="wcNoteModalTextarea" class="wc-note-modal-textarea" placeholder="Write a note..."></textarea>
            <div class="wc-note-status mt-1" id="wcNoteModalStatus"></div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-2">
            <button type="button" onclick="wcCloseNoteModal()" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-200 transition-colors">Close</button>
            <button type="button" onclick="wcSaveNoteFromModal()" class="px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-md hover:bg-red-900 transition-colors">Save</button>
        </div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<style>
.wc-th {
    padding: 0.65rem 0.9rem; font-size: 11px; font-weight: 600;
    color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em;
    white-space: nowrap; border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}
.wc-td {
    padding: 0.6rem 0.9rem; border-bottom: 1px solid #f3f4f6;
    vertical-align: middle; font-size: 0.875rem; color: #374151;
}
.wc-note-wrap { display: flex; align-items: flex-start; gap: 4px; }
.wc-note-input {
    width: 100%; padding: 0.4rem 0.6rem; border: 1px solid #e5e7eb; border-radius: 0.375rem;
    font-size: 0.8125rem; color: #374151; background: #fff; font-family: inherit;
    resize: none; overflow: hidden; min-height: 34px; line-height: 1.4;
}
.wc-note-input:focus { outline: none; border-color: #f87171; box-shadow: 0 0 0 2px #fee2e2; }
.wc-note-input:disabled { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
.wc-note-status { font-size: 10px; color: #9ca3af; margin-top: 2px; min-height: 12px; }
.wc-note-expand-btn {
    flex-shrink: 0; width: 26px; height: 34px; display: flex; align-items: center; justify-content: center;
    border: 1px solid #e5e7eb; border-radius: 0.375rem; background: #fff; color: #9ca3af;
    font-size: 11px; cursor: pointer; transition: all .15s;
}
.wc-note-expand-btn:hover { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
.wc-note-modal-textarea {
    width: 100%; min-height: 220px; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.5rem;
    font-size: 0.875rem; color: #374151; font-family: inherit; line-height: 1.6; resize: vertical;
}
.wc-note-modal-textarea:focus { outline: none; border-color: #f87171; box-shadow: 0 0 0 2px #fee2e2; }
.wc-status-badge {
    display: inline-block; padding: 1px 7px; border-radius: 9999px; font-size: 10px;
    font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em;
}
.wc-progress-note { font-size: 11px; color: #9ca3af; margin-top: 2px; max-width: 180px; white-space: normal; }
.wc-td-desc { max-width: 260px; white-space: normal; }
.wc-chip {
    padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 12px; font-weight: 600;
    border: 1px solid #e5e7eb; color: #4b5563; background: #fff; cursor: pointer; transition: all .15s;
}
.wc-chip:hover { background: #f9fafb; }
.wc-chip.active { background: #991b1b; border-color: #991b1b; color: #fff; }
.wc-chip.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.wc-th-filter-btn {
    width: 100%; display: flex; align-items: center; gap: 5px; background: none; border: none;
    padding: 0; margin: 0; cursor: pointer; font: inherit; color: inherit; text-transform: inherit;
    letter-spacing: inherit; text-align: left;
}
.wc-th-filter-btn:hover { color: #991b1b; }
.wc-th-filter-icon { width: 11px; height: 11px; flex-shrink: 0; color: #d1d5db; transition: color .15s; }
.wc-th-filter-icon.active { color: #dc2626; }
.wc-col-panel {
    display: none; position: fixed; background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,.15); padding: 0.6rem; z-index: 9999; min-width: 200px;
}
.wc-col-panel.open { display: block; }
.wc-col-panel input {
    width: 100%; padding: 0.35rem 0.55rem; border: 1px solid #d1d5db; border-radius: 0.375rem;
    font-size: 0.8125rem; font-weight: 400; text-transform: none; letter-spacing: normal; color: #374151;
}
.wc-col-panel input:focus { outline: none; border-color: #f87171; box-shadow: 0 0 0 2px #fee2e2; }
.wc-col-panel button {
    margin-top: 0.4rem; font-size: 0.6875rem; font-weight: 600; text-transform: none; letter-spacing: normal;
    color: #6b7280; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.375rem;
    padding: 0.25rem 0.55rem; cursor: pointer;
}
.wc-col-panel button:hover { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    wcLoadModules();
    wcLoadHistory();
    wcInitColumnFilters();
});

let wcState = 'idle'; // 'idle' | 'preview' | 'batch'
let wcCurrentBatchId = null;
let wcCanCombine = false;   // role privileged? boleh pilih >1 modul / All
let wcAllModules = [];      // [{id,name}, ...] yang boleh diakses employee ini
let wcSelectedModuleIds = [];
let wcSelectedAll = false;
let wcAutoViewTimer = null;
let wcRowsById = {};
let wcAllRows = [];        // semua baris yang sedang dimuat (belum difilter)
let wcEditableMode = false;
let wcColumnFilters = {};  // { [columnKey]: 'search text lowercase' }
const wcNoteTimers = {};

// ── Edit mode (module composition + period on an already-saved batch) ──────
let wcEditMode = false;
let wcOriginalModuleIds = [];  // snapshot at wcEnterEditMode(), used to detect removals
let wcCurrentBatchData = null; // last data.* payload from wcApplyBatch(), used by Cancel

function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function wcShowMsg(text) {
    document.getElementById('wcMsgText').textContent = text;
    document.getElementById('wcMsg').classList.remove('hidden');
}
function wcClearMsg() {
    document.getElementById('wcMsg').classList.add('hidden');
}

function wcEmptyRow(text, cols) {
    return `<tr><td colspan="${cols}" class="text-center py-10 text-gray-400 text-sm">${escHtml(text)}</td></tr>`;
}

async function wcLoadModules() {
    const wrap = document.getElementById('wcModuleField');
    try {
        const res = await fetch('/api/reporting/weekly-consolidation/modules', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load modules');

        wcCanCombine = !!json.can_combine;
        wcAllModules = json.data || [];
        wcRenderModuleField();
    } catch (e) {
        console.error(e);
        wrap.innerHTML = '<span class="text-sm text-red-500">Failed to load modules</span>';
    }
}

// Viewing a saved batch (wcState === 'batch') WITHOUT having clicked Edit
// first must never let a stray click silently start building a brand-new
// recon from scratch — that's exactly the trap that bit a user testing this:
// opened an existing recon, clicked a module chip out of curiosity, and it
// quietly began a new preview instead of editing the one they had open.
function wcModuleFieldLocked() {
    return wcState === 'batch' && !wcEditMode;
}

function wcRenderModuleField() {
    const wrap = document.getElementById('wcModuleField');
    const locked = wcModuleFieldLocked();

    if (!wcAllModules.length) {
        wrap.innerHTML = '<span class="text-sm text-gray-400">No module available for you</span>';
        return;
    }

    const lockedHint = locked ? `<p class="text-xs text-gray-400 mt-1.5"><i class="fas fa-lock mr-1"></i>Viewing a saved recon — click Edit to change modules.</p>` : '';

    if (!wcCanCombine) {
        // Role biasa: satu modul per batch, dropdown polos seperti sebelumnya.
        wrap.innerHTML = `<select id="wcModuleSelect" onchange="wcOnSingleSelectChange()" ${locked ? 'disabled' : ''}
            class="w-full px-3 py-2 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400 disabled:bg-gray-100 disabled:text-gray-400">
            <option value="">Select a module...</option>
            ${wcAllModules.map(m => `<option value="${m.id}" ${wcSelectedModuleIds[0] === m.id ? 'selected' : ''}>${escHtml(m.name)}</option>`).join('')}
        </select>${lockedHint}`;
        return;
    }

    // Role privileged: search box (muncul kalau modulnya banyak) + chip
    // multi-select + All Modules. Search murni client-side, filter chip yang
    // sudah dirender — dibutuhkan karena atasan kesulitan mencari modul di
    // tengah rapat saat daftar modul panjang.
    const searchBox = wcAllModules.length > 6 ? `
        <input type="text" id="wcModuleSearch" placeholder="Search module..." oninput="wcFilterModuleChips(this.value)"
            class="w-full mb-2 px-3 py-1.5 bg-white border border-gray-300 rounded-md text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
    ` : '';

    wrap.innerHTML = `${searchBox}<div class="flex flex-wrap gap-2" id="wcChipGrid">
        <button type="button" ${locked ? 'disabled' : ''} class="wc-chip ${wcSelectedAll ? 'active' : ''} ${locked ? 'disabled' : ''}" onclick="wcToggleAllChip()">
            <i class="fas fa-layer-group mr-1"></i>All Modules
        </button>
        ${wcAllModules.map(m => `<button type="button" ${locked ? 'disabled' : ''} data-module-name="${escHtml(m.name.toLowerCase())}"
            class="wc-chip ${(!wcSelectedAll && wcSelectedModuleIds.includes(m.id)) ? 'active' : ''} ${(wcSelectedAll || locked) ? 'disabled' : ''}"
            onclick="wcToggleModuleChip(${m.id})">${escHtml(m.name)}</button>`).join('')}
    </div>${lockedHint}`;
}

function wcFilterModuleChips(query) {
    const q = query.trim().toLowerCase();
    document.querySelectorAll('#wcChipGrid .wc-chip[data-module-name]').forEach(chip => {
        chip.style.display = !q || chip.dataset.moduleName.includes(q) ? '' : 'none';
    });
}

// Shared confirm dialog for "this will remove a module (+ its ticket rows
// and Notes) from an already-saved recon" — used by both the single-select
// dropdown and the chip grid's edit-mode removal path.
function wcConfirmModuleRemoval(moduleName) {
    return Swal.fire({
        title: 'Remove this module?',
        text: `Removing "${moduleName}" will permanently remove its ticket rows and Notes from this recon. This can't be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, remove it'
    }).then(result => result.isConfirmed);
}

async function wcOnSingleSelectChange() {
    const val = document.getElementById('wcModuleSelect').value;
    const newId = val ? parseInt(val, 10) : null;

    if (wcEditMode) {
        const oldId = wcSelectedModuleIds[0];
        if (oldId && wcOriginalModuleIds.includes(oldId) && oldId !== newId) {
            const oldMod = wcAllModules.find(m => m.id === oldId);
            const confirmed = await wcConfirmModuleRemoval(oldMod ? oldMod.name : 'this module');
            if (!confirmed) {
                wcRenderModuleField(); // revert the <select> back to the current value
                return;
            }
        }
        wcSelectedModuleIds = newId ? [newId] : [];
        wcRenderModuleField();
        return;
    }

    wcSelectedModuleIds = newId ? [newId] : [];
    wcSelectedAll = false;
    wcOnSelectionChange();
}

function wcToggleAllChip() {
    if (wcEditMode) return; // switching an existing recon to "All Modules" isn't supported from Edit
    wcSelectedAll = !wcSelectedAll;
    if (wcSelectedAll) wcSelectedModuleIds = [];
    wcRenderModuleField();
    wcOnSelectionChange();
}

async function wcToggleModuleChip(id) {
    const idx = wcSelectedModuleIds.indexOf(id);
    const isRemoving = idx >= 0;

    if (wcEditMode) {
        if (isRemoving && wcOriginalModuleIds.includes(id)) {
            const mod = wcAllModules.find(m => m.id === id);
            const confirmed = await wcConfirmModuleRemoval(mod ? mod.name : 'this module');
            if (!confirmed) return;
        }
        if (isRemoving) wcSelectedModuleIds.splice(idx, 1);
        else wcSelectedModuleIds.push(id);
        wcRenderModuleField();
        return;
    }

    wcSelectedAll = false;
    if (isRemoving) wcSelectedModuleIds.splice(idx, 1);
    else wcSelectedModuleIds.push(id);
    wcRenderModuleField();
    wcOnSelectionChange();
}

// Dipanggil setiap kali pilihan modul berubah (select maupun chip). Auto-load
// preview setelah jeda singkat, supaya centang beberapa chip berturut-turut
// tidak menembak API berkali-kali — tombol "View Tickets" tetap ada untuk
// re-trigger manual.
function wcOnSelectionChange() {
    wcClearMsg();
    wcState = 'idle';
    wcCurrentBatchId = null;

    const hasSelection = wcSelectedAll || wcSelectedModuleIds.length > 0;
    document.getElementById('wcViewBtn').disabled = !hasSelection;
    document.getElementById('wcGenerateBar').classList.add('hidden');
    document.getElementById('wcBatchBar').classList.add('hidden');

    clearTimeout(wcAutoViewTimer);
    if (!hasSelection) {
        document.getElementById('wcResultSummary').textContent = 'Select a module and click View Tickets to see its open tickets.';
        document.getElementById('wcTableBody').innerHTML = wcEmptyRow('No tickets loaded yet.', 12);
        return;
    }

    document.getElementById('wcResultSummary').textContent = 'Loading preview...';
    wcAutoViewTimer = setTimeout(() => wcViewTickets(), 400);
}

const STATUS_BADGE_COLOR = {
    open: ['#dcfce7', '#166534'],
    inprocess: ['#dbeafe', '#1e40af'],
    waiting_on_customer: ['#fef9c3', '#854d0e'],
    waiting_to_confirmation: ['#fef9c3', '#854d0e'],
    waiting_on_3rd_party: ['#fef9c3', '#854d0e'],
    hold: ['#fee2e2', '#991b1b'],
    closed: ['#f3f4f6', '#4b5563'],
    cancelled: ['#f3f4f6', '#4b5563'],
};

function wcStatusBadge(status, label) {
    const [bg, fg] = STATUS_BADGE_COLOR[status] || ['#f3f4f6', '#4b5563'];
    return `<span class="wc-status-badge" style="background:${bg};color:${fg};">${escHtml(label || status || '—')}</span>`;
}

function wcBuildModuleParams() {
    const params = new URLSearchParams();
    if (wcSelectedAll) {
        params.set('all', '1');
    } else {
        wcSelectedModuleIds.forEach(id => params.append('module_ids[]', id));
    }
    return params;
}

async function wcViewTickets() {
    wcClearMsg();
    clearTimeout(wcAutoViewTimer);

    const hasSelection = wcSelectedAll || wcSelectedModuleIds.length > 0;
    if (!hasSelection) { wcShowMsg('Please select a module first.'); return; }

    const btn = document.getElementById('wcViewBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Loading...';

    const body = document.getElementById('wcTableBody');
    body.innerHTML = wcEmptyRow('Loading...', 12);

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/preview?${wcBuildModuleParams().toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load tickets.');

        wcState = 'preview';
        wcCurrentBatchId = null;

        wcLoadRowsIntoState(json.data.rows, false);
        document.getElementById('wcGenerateBar').classList.remove('hidden');
        document.getElementById('wcBatchBar').classList.add('hidden');
        document.getElementById('wcResultSummary').innerHTML =
            `<strong class="text-gray-900">${json.data.rows.length}</strong> open ticket${json.data.rows.length !== 1 ? 's' : ''} found. `
            + `Fill in the period above and click <strong>Generate Recon</strong> to save this batch.`;
    } catch (e) {
        console.error(e);
        wcShowMsg(e.message);
        body.innerHTML = `<tr><td colspan="12" class="text-center py-10 text-red-500 text-sm">${escHtml(e.message)}</td></tr>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-eye mr-1.5"></i>View Tickets';
    }
}

async function wcGenerate() {
    wcClearMsg();
    const periodStart = document.getElementById('wcPeriodStart').value;
    const periodEnd = document.getElementById('wcPeriodEnd').value;
    const periodLabel = document.getElementById('wcPeriodLabel').value;

    if (!periodStart || !periodEnd) { wcShowMsg('Period Start and Period End are required.'); return; }

    const btn = document.getElementById('wcGenerateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Saving...';

    try {
        const res = await fetch('/api/reporting/weekly-consolidation', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                module_ids: wcSelectedAll ? [] : wcSelectedModuleIds,
                all: wcSelectedAll,
                period_start: periodStart,
                period_end: periodEnd,
                period_label: periodLabel || null
            })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save recon.');

        wcApplyBatch(json.data);
        wcLoadHistory();
    } catch (e) {
        console.error(e);
        wcShowMsg(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-floppy-disk mr-1.5"></i>Generate Recon';
    }
}

async function wcRefresh() {
    if (!wcCurrentBatchId) return;
    wcClearMsg();

    const btn = document.getElementById('wcRefreshBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Refreshing...';

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${wcCurrentBatchId}/refresh`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to refresh recon.');

        wcApplyBatch(json.data);
        wcLoadHistory();
    } catch (e) {
        console.error(e);
        wcShowMsg(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-rotate mr-1.5"></i>Refresh';
    }
}

async function wcOpenBatch(id) {
    wcClearMsg();
    const body = document.getElementById('wcTableBody');
    body.innerHTML = wcEmptyRow('Loading...', 12);

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${id}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to open recon.');

        wcApplyBatch(json.data);
    } catch (e) {
        console.error(e);
        wcShowMsg(e.message);
        body.innerHTML = `<tr><td colspan="12" class="text-center py-10 text-red-500 text-sm">${escHtml(e.message)}</td></tr>`;
    }
}

function wcApplyBatch(data) {
    wcState = 'batch';
    wcCurrentBatchId = data.id;
    wcCurrentBatchData = data;
    wcEditMode = false;
    document.getElementById('wcEditBar').classList.add('hidden');

    if (data.is_all_modules) {
        wcSelectedAll = true;
        wcSelectedModuleIds = [];
    } else {
        wcSelectedAll = false;
        wcSelectedModuleIds = (data.module_ids && data.module_ids.length) ? data.module_ids : [data.module_id];
    }
    wcRenderModuleField();

    document.getElementById('wcViewBtn').disabled = false;
    document.getElementById('wcGenerateBar').classList.add('hidden');
    document.getElementById('wcBatchBar').classList.remove('hidden');

    const exportBtn = document.getElementById('wcExportBtn');
    exportBtn.href = `/reporting/weekly-consolidation/${data.id}/export`;

    document.getElementById('wcEditBtn').classList.toggle('hidden', !data.can_edit);
    document.getElementById('wcDeleteBtn').classList.toggle('hidden', !data.can_delete);

    const moduleLabel = data.module_names || data.module_name;
    const refreshedStr = data.last_refreshed_at
        ? `Last refreshed ${new Date(data.last_refreshed_at).toLocaleString('en-US')} by ${escHtml(data.last_refreshed_by || '—')}`
        : 'Never refreshed yet';
    document.getElementById('wcBatchBarText').textContent =
        `${moduleLabel} · ${data.period_label || '-'} · ${data.rows.length} ticket(s) · ${refreshedStr}`;

    document.getElementById('wcResultSummary').innerHTML =
        `<strong class="text-gray-900">${escHtml(moduleLabel)}</strong> &middot; ${escHtml(data.period_label || '-')} &middot; `
        + `<strong class="text-gray-900">${data.rows.length}</strong> ticket(s) saved in this recon.`;

    wcLoadRowsIntoState(data.rows, true);
}

// ── Edit / Save / Cancel / Delete (saved batch) ─────────────────────────────

function wcEnterEditMode() {
    if (!wcCurrentBatchData) return;
    wcEditMode = true;
    wcOriginalModuleIds = wcCurrentBatchData.is_all_modules
        ? []
        : (wcCurrentBatchData.module_ids && wcCurrentBatchData.module_ids.length ? [...wcCurrentBatchData.module_ids] : [wcCurrentBatchData.module_id]);

    document.getElementById('wcEditPeriodStart').value = wcCurrentBatchData.period_start || '';
    document.getElementById('wcEditPeriodEnd').value = wcCurrentBatchData.period_end || '';
    document.getElementById('wcEditPeriodLabel').value = wcCurrentBatchData.period_label || '';

    document.getElementById('wcEditBar').classList.remove('hidden');
    wcRenderModuleField(); // unlock the chips/select now that edit mode is on
    wcClearMsg();
}

function wcCancelEdit() {
    wcEditMode = false;
    document.getElementById('wcEditBar').classList.add('hidden');
    if (wcCurrentBatchData) wcApplyBatch(wcCurrentBatchData); // revert chip/period state
}

async function wcSaveEdit() {
    wcClearMsg();
    const periodStart = document.getElementById('wcEditPeriodStart').value;
    const periodEnd = document.getElementById('wcEditPeriodEnd').value;
    const periodLabel = document.getElementById('wcEditPeriodLabel').value;

    if (!periodStart || !periodEnd) { wcShowMsg('Period Start and Period End are required.'); return; }
    if (!wcSelectedAll && wcSelectedModuleIds.length === 0) { wcShowMsg('Select at least one module.'); return; }

    const btn = document.getElementById('wcSaveEditBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Saving...';

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${wcCurrentBatchId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                module_ids: wcSelectedAll ? [] : wcSelectedModuleIds,
                all: wcSelectedAll,
                period_start: periodStart,
                period_end: periodEnd,
                period_label: periodLabel || null
            })
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save changes.');

        wcApplyBatch(json.data);
        wcLoadHistory();
        showNotification('Changes saved.', 'success');
    } catch (e) {
        console.error(e);
        wcShowMsg(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-floppy-disk mr-1.5"></i>Save Changes';
    }
}

// Shared reset back to "pick a module and start fresh" — used after Delete
// succeeds, and by the Close button (view a saved recon, then back out
// without touching it). Also clears the module selection and re-renders the
// field, since it stays locked/pre-filled from whatever batch was open
// otherwise.
function wcResetToIdle(message) {
    wcState = 'idle';
    wcEditMode = false;
    wcCurrentBatchId = null;
    wcCurrentBatchData = null;
    wcSelectedModuleIds = [];
    wcSelectedAll = false;
    document.getElementById('wcBatchBar').classList.add('hidden');
    document.getElementById('wcEditBar').classList.add('hidden');
    document.getElementById('wcGenerateBar').classList.add('hidden');
    document.getElementById('wcViewBtn').disabled = true;
    document.getElementById('wcResultSummary').textContent = message;
    document.getElementById('wcTableBody').innerHTML = wcEmptyRow('No tickets loaded yet.', 12);
    wcRenderModuleField();
}

function wcCloseBatch() {
    wcResetToIdle('Select a module and click View Tickets to see its open tickets.');
}

async function wcDeleteBatch() {
    if (!wcCurrentBatchId) return;

    const result = await Swal.fire({
        title: 'Delete this recon?',
        text: 'It will be moved to Deleted (recoverable) until permanently removed.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete it'
    });
    if (!result.isConfirmed) return;

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${wcCurrentBatchId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete recon.');

        wcResetToIdle('Recon deleted. Select a module and click View Tickets to start a new one.');
        wcLoadHistory();
        showNotification('Recon deleted — recoverable from "Show Deleted".', 'success');
    } catch (e) {
        console.error(e);
        showNotification(e.message, 'error');
    }
}

// Dipanggil setiap kali batch/preview baru dimuat — set ulang "sumber data"
// lengkap (belum difilter), reset filter kolom yang mungkin masih aktif dari
// sesi sebelumnya, lalu render lewat wcApplyColumnFilters() supaya konsisten
// dengan satu jalur render (tidak ada dua tempat yang bisa beda hasil).
function wcLoadRowsIntoState(rows, editable) {
    wcAllRows = rows;
    wcEditableMode = editable;
    wcRowsById = {};
    rows.forEach(r => { wcRowsById[r.ticket_id] = r; });

    wcColumnFilters = {};
    document.querySelectorAll('#wcTicketTable .wc-col-panel input').forEach(el => { el.value = ''; });
    wcUpdateFilterIndicators();

    wcApplyColumnFilters();
}

function wcRenderRows(rows, editable) {
    const body = document.getElementById('wcTableBody');

    if (!rows.length) {
        const msg = wcAllRows.length ? 'No tickets match your filters.' : 'No open tickets found for the selected module(s).';
        body.innerHTML = wcEmptyRow(msg, 12);
        return;
    }

    body.innerHTML = rows.map(r => `
        <tr>
            <td class="wc-td text-xs text-gray-500 whitespace-nowrap">${r.last_update ? new Date(r.last_update).toLocaleString('en-US') : '—'}</td>
            <td class="wc-td">
                <a href="/ticket/${r.ticket_id}" onclick="return wcTicketClick(event, ${r.ticket_id})" class="font-semibold text-red-800 hover:underline">${escHtml(r.ticket_number)}</a>
            </td>
            <td class="wc-td wc-td-desc">${r.description ? escHtml(r.description) : '<span class="text-gray-300 italic">—</span>'}</td>
            <td class="wc-td text-xs text-gray-500 whitespace-nowrap">${r.start_date ? new Date(r.start_date).toLocaleString('en-US') : '—'}</td>
            <td class="wc-td">${r.ticket_type ? escHtml(r.ticket_type) : '<span class="text-gray-300 italic">—</span>'}</td>
            <td class="wc-td">${wcStatusBadge(r.status, r.status_label)}</td>
            <td class="wc-td">${r.module_name ? escHtml(r.module_name) : '<span class="text-gray-300 italic">—</span>'}</td>
            <td class="wc-td">${r.lead_member ? escHtml(r.lead_member) : '<span class="text-gray-300 italic">—</span>'}</td>
            <td class="wc-td">${r.pic ? escHtml(r.pic) : '<span class="text-gray-300 italic">—</span>'}</td>
            <td class="wc-td">
                ${r.progress_percentage !== null && r.progress_percentage !== undefined ? Math.round(r.progress_percentage) + '%' : '—'}
                ${r.progress_note ? `<div class="wc-progress-note">${escHtml(r.progress_note)}</div>` : ''}
            </td>
            <td class="wc-td">${wcDeliverableBadge(r.deliverable_status)}</td>
            <td class="wc-td">
                ${editable ? `
                    <div class="wc-note-wrap">
                        <textarea class="wc-note-input" id="wcNoteTextarea-${r.ticket_id}" rows="1" placeholder="Write a note..."
                            oninput="wcAutoResizeNote(this); wcOnNoteInput(${r.ticket_id}, this)"
                            onblur="wcSaveNote(${r.ticket_id}, this.value)">${escHtml(r.notes || '')}</textarea>
                        <button type="button" class="wc-note-expand-btn" onclick="wcOpenNoteModal(${r.ticket_id})" title="Expand note">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="wc-note-status" id="wcNoteStatus-${r.ticket_id}"></div>
                ` : `
                    <textarea class="wc-note-input" rows="1" placeholder="Available after Generate" disabled></textarea>
                `}
            </td>
        </tr>
    `).join('');

    body.querySelectorAll('.wc-note-input:not(:disabled)').forEach(wcAutoResizeNote);
}

function wcAutoResizeNote(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function wcDeliverableBadge(status) {
    if (status === 'ok') return '<span class="wc-status-badge" style="background:#dcfce7;color:#166534;">OK</span>';
    if (status === 'not_ok') return '<span class="wc-status-badge" style="background:#fee2e2;color:#991b1b;">Not OK</span>';
    return '<span class="text-gray-300 italic">—</span>';
}

// ── Per-column header filters (satu filter teks per kolom, sama pola dengan
// tabel "Ringkasan per Tiket" di Log Shifting) ──────────────────────────────

const WC_FILTER_KEYS = ['last_update', 'ticket_number', 'description', 'start_date', 'ticket_type', 'status', 'module_name', 'lead_member', 'pic', 'progress', 'deliverable', 'notes'];

function wcInitColumnFilters() {
    document.querySelectorAll('#wcTicketTable thead th[data-filter-key]').forEach(th => {
        const key = th.dataset.filterKey;
        const label = th.textContent.trim();

        const panel = document.createElement('div');
        panel.className = 'wc-col-panel';
        panel.id = `wcColPanel-${key}`;
        panel.innerHTML = `
            <input type="text" id="wcColInput-${key}" placeholder="Search ${escHtml(label)}..." oninput="wcOnColFilterInput('${key}')">
            <div><button type="button" onclick="wcClearColFilter('${key}')">Clear</button></div>
        `;
        document.body.appendChild(panel);

        th.innerHTML = '';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'wc-th-filter-btn';
        btn.onclick = (ev) => wcToggleColFilter(key, ev);
        btn.innerHTML = `<span>${escHtml(label)}</span><svg class="wc-th-filter-icon" id="wcColIcon-${key}" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd" /></svg>`;
        th.appendChild(btn);
    });

    document.addEventListener('click', (e) => {
        WC_FILTER_KEYS.forEach(key => {
            const panel = document.getElementById(`wcColPanel-${key}`);
            const btn = document.querySelector(`#wcTicketTable th[data-filter-key="${key}"] .wc-th-filter-btn`);
            if (panel && panel.classList.contains('open') && !panel.contains(e.target) && btn && !btn.contains(e.target)) {
                wcClosePanel(key);
            }
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') WC_FILTER_KEYS.forEach(wcClosePanel);
    });
}

function wcToggleColFilter(key, ev) {
    ev?.stopPropagation();
    const panel = document.getElementById(`wcColPanel-${key}`);
    const btn = document.querySelector(`#wcTicketTable th[data-filter-key="${key}"] .wc-th-filter-btn`);
    const wasOpen = panel.classList.contains('open');

    WC_FILTER_KEYS.forEach(wcClosePanel);
    if (wasOpen) return;

    const rect = btn.getBoundingClientRect();
    panel.style.top = (rect.bottom + 4) + 'px';
    panel.style.left = Math.min(rect.left, window.innerWidth - 220) + 'px';
    panel.classList.add('open');
    document.getElementById(`wcColInput-${key}`)?.focus();
}

function wcClosePanel(key) {
    document.getElementById(`wcColPanel-${key}`)?.classList.remove('open');
}

let wcFilterDebounce = null;
function wcOnColFilterInput(key) {
    clearTimeout(wcFilterDebounce);
    wcFilterDebounce = setTimeout(() => {
        wcColumnFilters[key] = (document.getElementById(`wcColInput-${key}`)?.value || '').trim().toLowerCase();
        wcUpdateFilterIndicators();
        wcApplyColumnFilters();
    }, 200);
}

function wcClearColFilter(key) {
    const input = document.getElementById(`wcColInput-${key}`);
    if (input) input.value = '';
    wcColumnFilters[key] = '';
    wcUpdateFilterIndicators();
    wcApplyColumnFilters();
}

function wcUpdateFilterIndicators() {
    WC_FILTER_KEYS.forEach(key => {
        const icon = document.getElementById(`wcColIcon-${key}`);
        if (icon) icon.classList.toggle('active', !!wcColumnFilters[key]);
    });
}

function wcGetFilterText(row, key) {
    switch (key) {
        case 'ticket_number': return row.ticket_number || '';
        case 'description':   return row.description || '';
        case 'start_date':    return row.start_date ? new Date(row.start_date).toLocaleString('en-US') : '';
        case 'last_update':   return row.last_update ? new Date(row.last_update).toLocaleString('en-US') : '';
        case 'ticket_type':   return row.ticket_type || '';
        case 'status':        return row.status_label || row.status || '';
        case 'module_name':   return row.module_name || '';
        case 'lead_member':   return row.lead_member || '';
        case 'pic':           return row.pic || '';
        case 'progress':      return (row.progress_percentage !== null && row.progress_percentage !== undefined) ? Math.round(row.progress_percentage) + '%' : '';
        case 'deliverable':   return row.deliverable_status === 'ok' ? 'OK' : (row.deliverable_status === 'not_ok' ? 'Not OK' : '');
        case 'notes':         return row.notes || '';
        default:              return '';
    }
}

function wcApplyColumnFilters() {
    const active = Object.entries(wcColumnFilters).filter(([, v]) => v);
    const filtered = active.length
        ? wcAllRows.filter(row => active.every(([key, val]) => wcGetFilterText(row, key).toLowerCase().includes(val)))
        : wcAllRows;

    wcRenderRows(filtered, wcEditableMode);
}

function wcOnNoteInput(ticketId, el) {
    clearTimeout(wcNoteTimers[ticketId]);
    wcNoteTimers[ticketId] = setTimeout(() => wcSaveNote(ticketId, el.value), 800);
}

async function wcSaveNote(ticketId, value) {
    if (!wcCurrentBatchId) return;
    clearTimeout(wcNoteTimers[ticketId]);

    const statusEl = document.getElementById(`wcNoteStatus-${ticketId}`);
    if (statusEl) statusEl.textContent = 'Saving...';

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${wcCurrentBatchId}/tickets/${ticketId}/notes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({ notes: value })
        });
        const json = await res.json();
        if (statusEl) statusEl.textContent = json.success ? 'Saved' : 'Failed to save';
        if (statusEl && json.success) setTimeout(() => { statusEl.textContent = ''; }, 1500);
        if (wcRowsById[ticketId]) wcRowsById[ticketId].notes = value;
    } catch (e) {
        console.error(e);
        if (statusEl) statusEl.textContent = 'Failed to save';
    }
}

// ── Ticket number click: left click → quick-view modal on this page,
// right click / ctrl|cmd|shift+click / middle click → let the browser open
// the real link (new tab, "open in new tab" context menu, etc.) normally. ──
function wcTicketClick(ev, ticketId) {
    if (ev.button !== 0 || ev.ctrlKey || ev.metaKey || ev.shiftKey) {
        return true;
    }
    ev.preventDefault();
    wcOpenTicketModal(ticketId);
    return false;
}

function wcOpenTicketModal(ticketId) {
    const r = wcRowsById[ticketId];
    if (!r) return;

    document.getElementById('wcModalTicketNumber').textContent = r.ticket_number || '—';
    document.getElementById('wcModalStatusBadge').innerHTML = wcStatusBadge(r.status, r.status_label);
    document.getElementById('wcModalFullLink').href = `/ticket/${ticketId}`;

    document.getElementById('wcModalBody').innerHTML = `
        ${r.description ? `<p class="text-gray-600">${escHtml(r.description)}</p>` : ''}
        <div class="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Start Date</span>${r.start_date ? new Date(r.start_date).toLocaleString('en-US') : '—'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Last Update</span>${r.last_update ? new Date(r.last_update).toLocaleString('en-US') : '—'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Type</span>${r.ticket_type ? escHtml(r.ticket_type) : '<span class="text-gray-300 italic">—</span>'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Module</span>${r.module_name ? escHtml(r.module_name) : '<span class="text-gray-300 italic">—</span>'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Lead &amp; Member</span>${r.lead_member ? escHtml(r.lead_member) : '<span class="text-gray-300 italic">—</span>'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">PIC</span>${r.pic ? escHtml(r.pic) : '<span class="text-gray-300 italic">—</span>'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Progress</span>${r.progress_percentage !== null && r.progress_percentage !== undefined ? Math.round(r.progress_percentage) + '%' : '—'}</div>
            <div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Deliverable</span>${wcDeliverableBadge(r.deliverable_status)}</div>
        </div>
        ${r.progress_note ? `<div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Progress Note</span>${escHtml(r.progress_note)}</div>` : ''}
        ${r.notes ? `<div><span class="block text-[11px] font-semibold text-gray-400 uppercase">Notes</span>${escHtml(r.notes)}</div>` : ''}
    `;

    const modal = document.getElementById('wcTicketModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function wcCloseTicketModal() {
    const modal = document.getElementById('wcTicketModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// ── Note editor modal: dibuka dari ikon expand di sebelah textarea Notes
// inline, dipakai untuk notes panjang atau saat mau menulis lebih leluasa.
// Menyimpan lewat wcSaveNote() yang sama dengan autosave inline, supaya tidak
// ada dua jalur simpan yang bisa saling menimpa — textarea inline di tabel
// ikut disinkronkan begini modal ditutup, jadi tidak perlu render ulang baris.
let wcNoteModalTicketId = null;

function wcOpenNoteModal(ticketId) {
    wcNoteModalTicketId = ticketId;
    const row = wcRowsById[ticketId];

    document.getElementById('wcNoteModalTicketNumber').textContent = row?.ticket_number || '—';
    document.getElementById('wcNoteModalTextarea').value = row?.notes || '';
    document.getElementById('wcNoteModalStatus').textContent = '';

    const modal = document.getElementById('wcNoteModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => document.getElementById('wcNoteModalTextarea').focus(), 50);
}

function wcCloseNoteModal() {
    const modal = document.getElementById('wcNoteModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    wcNoteModalTicketId = null;
}

async function wcSaveNoteFromModal() {
    if (!wcNoteModalTicketId) return;
    const ticketId = wcNoteModalTicketId;
    const value = document.getElementById('wcNoteModalTextarea').value;
    const statusEl = document.getElementById('wcNoteModalStatus');
    statusEl.textContent = 'Saving...';

    const inlineEl = document.getElementById(`wcNoteTextarea-${ticketId}`);
    if (inlineEl) {
        inlineEl.value = value;
        wcAutoResizeNote(inlineEl);
    }

    await wcSaveNote(ticketId, value);
    statusEl.textContent = 'Saved';
    setTimeout(() => { if (wcNoteModalTicketId === ticketId) wcCloseNoteModal(); }, 500);
}

document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    wcCloseTicketModal();
    wcCloseNoteModal();
});

let wcShowDeleted = false;
// Persists across toggles — re-deriving "can I see Show Deleted?" from just
// the CURRENT list's first row breaks the moment that list is empty (e.g. no
// deleted recons yet), which hid the only way back to History. Once we've
// seen it's true from any load, it stays true for the rest of the session.
let wcCanDeleteAny = false;

function wcToggleShowDeleted() {
    wcShowDeleted = !wcShowDeleted;
    document.getElementById('wcShowDeletedLabel').textContent = wcShowDeleted ? 'Back to History' : 'Show Deleted';
    document.getElementById('wcHistoryTitle').textContent = wcShowDeleted ? 'Deleted Recons' : 'Recon History';
    document.getElementById('wcHistorySubtitle').textContent = wcShowDeleted
        ? 'Soft-deleted batches — Restore to bring one back, or delete permanently.'
        : 'All Weekly Consolidation batches ever generated. Click "Open" to view or continue editing.';
    wcLoadHistory();
}

async function wcRestoreBatch(id) {
    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${id}/restore`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to restore recon.');
        wcLoadHistory();
        showNotification('Recon restored.', 'success');
    } catch (e) {
        console.error(e);
        showNotification(e.message, 'error');
    }
}

async function wcForceDeleteBatch(id) {
    const result = await Swal.fire({
        title: 'Permanently delete this recon?',
        text: 'This cannot be undone — the recon and its ticket rows will be gone for good.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, delete permanently'
    });
    if (!result.isConfirmed) return;

    try {
        const res = await fetch(`/api/reporting/weekly-consolidation/${id}/force`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to permanently delete recon.');
        wcLoadHistory();
        showNotification('Recon permanently deleted.', 'success');
    } catch (e) {
        console.error(e);
        showNotification(e.message, 'error');
    }
}

async function wcLoadHistory() {
    const body = document.getElementById('wcHistoryBody');
    try {
        const url = '/api/reporting/weekly-consolidation' + (wcShowDeleted ? '?trashed=1' : '');
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load history');

        const batches = json.data || [];

        // Tombol "Show Deleted" hanya berarti buat yang punya can_delete —
        // disembunyikan total dari yang lain, bukan cuma dinonaktifkan. Begitu
        // kita tahu true (dari list manapun), biarkan tetap true supaya
        // tombol/"Back to History" tidak hilang saat list yang SEDANG dilihat
        // kebetulan kosong.
        if (batches[0]?.can_delete) wcCanDeleteAny = true;
        document.getElementById('wcShowDeletedBtn').classList.toggle('hidden', !wcCanDeleteAny);

        if (!batches.length) {
            body.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-gray-400 text-sm">${wcShowDeleted ? 'No deleted recons.' : 'No recon has been generated yet.'}</td></tr>`;
            return;
        }

        body.innerHTML = batches.map(b => `
            <tr>
                <td class="wc-td font-semibold">${escHtml(b.module_names || b.module_name)}</td>
                <td class="wc-td">${escHtml(b.period_label || '-')}</td>
                <td class="wc-td">${b.ticket_count}</td>
                <td class="wc-td text-xs text-gray-500">${b.generated_at ? new Date(b.generated_at).toLocaleString('en-US') : '—'} ${b.generated_by ? 'by ' + escHtml(b.generated_by) : ''}</td>
                <td class="wc-td text-xs text-gray-500">${wcShowDeleted
                    ? (b.deleted_at ? new Date(b.deleted_at).toLocaleString('en-US') + (b.deleted_by ? ' by ' + escHtml(b.deleted_by) : '') : '—')
                    : (b.last_refreshed_at ? new Date(b.last_refreshed_at).toLocaleString('en-US') : '—')}</td>
                <td class="wc-td text-right whitespace-nowrap">
                    ${wcShowDeleted ? `
                        <button type="button" onclick="wcRestoreBatch(${b.id})" class="px-2.5 py-1 text-xs font-semibold text-green-700 border border-green-700 rounded-md hover:bg-green-50 mr-1.5">Restore</button>
                        <button type="button" onclick="wcForceDeleteBatch(${b.id})" class="px-2.5 py-1 text-xs font-semibold text-red-700 border border-red-700 rounded-md hover:bg-red-50">Delete Permanently</button>
                    ` : `
                        <button type="button" onclick="wcOpenBatch(${b.id})" class="px-2.5 py-1 text-xs font-semibold text-red-800 border border-red-800 rounded-md hover:bg-red-50 mr-1.5">Open</button>
                        <a href="/reporting/weekly-consolidation/${b.id}/export" class="px-2.5 py-1 text-xs font-semibold text-gray-600 border border-gray-300 rounded-md hover:bg-gray-100">Export</a>
                    `}
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error(e);
        body.innerHTML = `<tr><td colspan="6" class="text-center py-10 text-red-500 text-sm">${escHtml(e.message)}</td></tr>`;
    }
}
</script>
@endpush
