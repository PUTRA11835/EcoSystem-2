// Calendar Timesheets JavaScript
let timesheets = [];
let filteredTimesheets = [];
let selectedTimesheetId = null;
let deleteTimesheetId = null;
let myTicketsCache = []; // cache for support ticket auto-fill
let _pendingTicketPreselect   = null; // ticket_id to preselect when edit opens support modal
let _pendingActivityPreselect = null; // activity_id to preselect when edit opens project modal
let _currentTicketRemainingMd = null; // remaining MD for the currently-selected support ticket (null = no quota tracked)

const TH = 'px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200';

// Default employee thead HTML (saved once DOM is ready)
let defaultTheadHTML = '';

// Definitive flag: true when support spreadsheet layout is active in the thead.
// Set synchronously wherever the thead is swapped — prevents renderTimesheetRows()
// from falling through to Branch 3 when condition flags mismatch.
let supportLayoutActive = false;

// Support-specific thead — exact same custom-dd / text-panel pattern as blade
// (keep in sync with @elseif($lockedType === 'support') section in timesheets.blade.php)
const CHEVRON_SVG = `<svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>`;
const DD_CHEVRON  = `<svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-500 transition-all duration-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>`;
const FUNNEL_SVG  = (id) => `<svg id="${id}" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 011 1v1.586a1 1 0 01-.293.707l-4.121 4.121A1 1 0 0012 12.121V15.5l-4 1.5v-4.879a1 1 0 00-.293-.707L3.586 7.293A1 1 0 013.293 6.586L3 5z" clip-rule="evenodd"/></svg>`;
const TH_PLAIN = 'px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap border-b border-gray-200';
const TH_FILT  = 'p-0 text-left whitespace-nowrap border-b border-gray-200 bg-gray-50';
const DD_ITEM  = 'custom-dd-item w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50';

const _STATUS_DD_ITEMS = `
    <button type="button" class="${DD_ITEM}" data-value="">All</button>
    <button type="button" class="${DD_ITEM}" data-value="draft">Draft</button>
    <button type="button" class="${DD_ITEM}" data-value="submitted">Submitted</button>
    <button type="button" class="${DD_ITEM}" data-value="approved">Approved</button>
    <button type="button" class="${DD_ITEM}" data-value="rejected">Rejected</button>`;

const _TYPE_DD_ITEMS = `
    <button type="button" class="${DD_ITEM}" data-value="">All</button>
    <button type="button" class="${DD_ITEM}" data-value="internal">Internal</button>
    <button type="button" class="${DD_ITEM}" data-value="non_internal">Non Internal</button>`;

const _ACT_TEXT_PANEL = `<div id="tsTextPanel_ActivityType" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search activity type</label>
    <input type="text" id="colFilterTsActivityType" placeholder="e.g. Development…" oninput="applyColFilter()" onclick="event.stopPropagation()"
           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
    <div class="flex justify-end gap-2 mt-2">
        <button type="button" onclick="clearTsTextPanel('ActivityType')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
    </div>
</div>`;

const _MONTH_DD_ITEMS = `
    <button type="button" class="${DD_ITEM}" data-value="">All</button>
    <button type="button" class="${DD_ITEM}" data-value="1">January</button>
    <button type="button" class="${DD_ITEM}" data-value="2">February</button>
    <button type="button" class="${DD_ITEM}" data-value="3">March</button>
    <button type="button" class="${DD_ITEM}" data-value="4">April</button>
    <button type="button" class="${DD_ITEM}" data-value="5">May</button>
    <button type="button" class="${DD_ITEM}" data-value="6">June</button>
    <button type="button" class="${DD_ITEM}" data-value="7">July</button>
    <button type="button" class="${DD_ITEM}" data-value="8">August</button>
    <button type="button" class="${DD_ITEM}" data-value="9">September</button>
    <button type="button" class="${DD_ITEM}" data-value="10">October</button>
    <button type="button" class="${DD_ITEM}" data-value="11">November</button>
    <button type="button" class="${DD_ITEM}" data-value="12">December</button>`;

const _EMP_TEXT_PANEL = `<div id="tsTextPanel_Employee" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search name</label>
    <input type="text" id="colFilterTsEmployee" placeholder="Type name…" oninput="applyColFilter()" onclick="event.stopPropagation()"
           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
    <p class="text-[10px] text-gray-400 mt-1.5">Use the ⇅ icon in the header to sort by name.</p>
    <div class="flex justify-end gap-2 mt-2">
        <button type="button" onclick="clearTsTextPanel('Employee')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
    </div>
</div>`;

const _TKT_TEXT_PANEL = `<div id="tsTextPanel_Ticket" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search ticket</label>
    <input type="text" id="colFilterTsTicket" placeholder="e.g. TKT-001…" oninput="applyColFilter()" onclick="event.stopPropagation()"
           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
    <div class="flex justify-end gap-2 mt-2">
        <button type="button" onclick="clearTsTextPanel('Ticket')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
    </div>
</div>`;

const _CUST_TEXT_PANEL = `<div id="tsTextPanel_Customer" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:220px;">
    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Search customer</label>
    <input type="text" id="colFilterTsCustomer" placeholder="Type customer…" oninput="applyColFilter()" onclick="event.stopPropagation()"
           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
    <div class="flex justify-end gap-2 mt-2">
        <button type="button" onclick="clearTsTextPanel('Customer')" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
    </div>
</div>`;

function _mkStatusDd() { return `<div class="custom-dd relative w-full" id="ddColFilterTsStatus" data-fixed="true" data-onchange="applyColFilter"><button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Status</span>${DD_CHEVRON}</button><input type="hidden" id="colFilterTsStatus" value=""><div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:220px;min-width:150px;">${_STATUS_DD_ITEMS}</div></div>`; }
function _mkTypeDd()   { return `<div class="custom-dd relative w-full" id="ddColFilterTsType" data-fixed="true" data-onchange="applyColFilter"><button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Type</span>${DD_CHEVRON}</button><input type="hidden" id="colFilterTsType" value=""><div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:150px;min-width:140px;">${_TYPE_DD_ITEMS}</div></div>`; }
function _mkMonthDd()  { return `<div class="custom-dd relative w-full" id="ddColFilterTsMonth" data-fixed="true" data-onchange="applyColFilter"><button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Month</span>${DD_CHEVRON}</button><input type="hidden" id="colFilterTsMonth" value=""><div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:120px;">${_MONTH_DD_ITEMS}</div></div>`; }
// Year dropdown: panel items are filled dynamically from the loaded data
// (years vary by dataset, unlike Month's fixed 12-item list) — see _populateTsYearDd().
function _mkYearDd()   { return `<div class="custom-dd relative w-full" id="ddColFilterTsYear" data-fixed="true" data-onchange="applyColFilter"><button type="button" class="custom-dd-btn w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Year</span>${DD_CHEVRON}</button><input type="hidden" id="colFilterTsYear" value=""><div class="custom-dd-panel hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] py-1.5 overflow-y-auto" style="max-height:240px;min-width:100px;"><button type="button" class="${DD_ITEM}" data-value="">All</button></div></div>`; }
function _mkActivityTextTh() { return `<th class="${TH_FILT}" style="min-width:130px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'ActivityType')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Activity</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_ActivityType')}</button>${_ACT_TEXT_PANEL}</th>`; }

// Date range filter panel — pola sama dengan view ticket (From/To + Clear/Apply).
const _DATE_FILTER_PANEL = `<div id="tsDateFilterPanel" class="hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 z-[9999] p-3" style="min-width:240px;">
    <div class="space-y-2">
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">From</label>
            <input type="date" id="tsDateFrom" onclick="event.stopPropagation()" class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">To</label>
            <input type="date" id="tsDateTo" onclick="event.stopPropagation()" class="w-full px-3 py-1.5 border border-gray-300 rounded-md text-sm font-normal text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-400">
        </div>
        <p id="tsDateFilterError" class="hidden text-xs text-red-500">"To" must be on/after "From".</p>
    </div>
    <div class="flex justify-end gap-2 mt-3">
        <button type="button" onclick="clearTsDateFilter()" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Clear</button>
        <button type="button" onclick="applyTsDateFilter()" class="px-3 py-1.5 text-xs text-white bg-red-700 hover:bg-red-800 rounded-md">Apply</button>
    </div>
</div>`;

const SUPPORT_THEAD_HTML = `<tr>
    <th class="${TH_PLAIN}" style="min-width:36px;"><input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300"></th>
    <th class="${TH_FILT}" style="min-width:110px; position:relative;"><button type="button" onclick="toggleTsDatePanel(event)" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Submit Date</span>${CHEVRON_SVG}${FUNNEL_SVG('tsDateFilterIcon')}<span id="tsSortDateIcon" onclick="event.stopPropagation(); toggleTsDateSort()" title="Click to toggle sort (descending ↔ ascending)" class="cursor-pointer text-[10px] text-red-500 font-bold shrink-0 ml-auto hover:text-red-700 transition-colors">↓</span></button>${_DATE_FILTER_PANEL}</th>
    <th class="${TH_PLAIN}" style="min-width:100px;">Activity Date</th>
    <th class="${TH_PLAIN}" style="min-width:110px;">Time</th>
    <th class="${TH_FILT}" style="min-width:85px;">${_mkMonthDd()}</th>
    <th class="${TH_FILT}" style="min-width:70px;">${_mkYearDd()}</th>
    <th class="${TH_FILT}" style="min-width:150px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'Employee')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Name</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_Employee')}<span id="tsSortEmpIcon" onclick="event.stopPropagation(); toggleTsEmpSort()" title="Click to toggle sort (A–Z ↔ Z–A)" class="cursor-pointer text-[10px] text-gray-300 font-bold shrink-0 ml-auto hover:text-red-500 transition-colors">⇅</span></button>${_EMP_TEXT_PANEL}</th>
    <th class="${TH_FILT}" style="min-width:120px;">${_mkStatusDd()}</th>
    <th class="${TH_FILT}" style="min-width:150px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'Ticket')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Ticket</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_Ticket')}</button>${_TKT_TEXT_PANEL}</th>
    <th class="${TH_PLAIN}" style="min-width:180px;">Description</th>
    <th class="${TH_FILT}" style="min-width:130px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'Customer')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_Customer')}</button>${_CUST_TEXT_PANEL}</th>
    <th class="${TH_FILT}" style="min-width:110px;">${_mkTypeDd()}</th>
    <th class="${TH_PLAIN}" style="min-width:80px;">Quota MD</th>
    ${_mkActivityTextTh()}
    <th class="${TH_PLAIN}" style="min-width:90px;">MD Consumed</th>
    <th class="${TH_PLAIN}" style="min-width:70px;">On Site</th>
</tr>`;
let currentFilters = {
    start_date: null,
    end_date: null,
    status: '',
    activity_type: '',
    type_filter: ''   // '' | 'project' | 'support' | 'office'
};
let tsSortKey = 'date';
let tsSortDir = 'desc';
let itemsPerPage = 200;
let currentPage = 1;

const activityTypeIcons = {
    development: 'fa-code',
    meeting: 'fa-users',
    documentation: 'fa-file-alt',
    testing: 'fa-vial',
    support: 'fa-headset',
    training: 'fa-graduation-cap',
    other: 'fa-ellipsis-h'
};

const statusColors = {
    draft: { bg: 'bg-gray-100', text: 'text-gray-700', badge: 'bg-gray-500' },
    submitted: { bg: 'bg-yellow-100', text: 'text-yellow-700', badge: 'bg-yellow-500' },
    approved: { bg: 'bg-green-100', text: 'text-green-700', badge: 'bg-green-500' },
    rejected: { bg: 'bg-red-100', text: 'text-red-700', badge: 'bg-red-500' }
};

async function loadTsPeriodBadge() {
    try {
        const res  = await fetch('/api/reporting/current-period', {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) return;
        const p      = json.data;
        const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const badge  = document.getElementById('tsPeriodBadge');
        const label  = document.getElementById('tsBadgeLabel');
        const status = document.getElementById('tsBadgeStatus');
        if (!badge) return;

        // No period is globally open at all (RPMO hasn't opened one yet) — distinct
        // from "open" and "closed", so it must not silently render as either.
        if (p.status === 'not_open' || !p.month) {
            label.textContent  = 'No active period';
            status.textContent = '';
            status.className   = 'font-semibold text-gray-500';
            badge.classList.remove('hidden');
            badge.classList.add('flex');
            return;
        }

        label.textContent  = `${MONTHS[p.month - 1]} ${p.year}`;
        status.textContent = p.is_closed ? '(Closed)' : '(Open)';
        status.className   = p.is_closed ? 'font-semibold text-red-500' : 'font-semibold text-green-500';
        badge.classList.remove('hidden');
        badge.classList.add('flex');
    } catch (e) {}
}

// Close text/date panels on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="tsTextPanel_"]') && !e.target.closest('#tsDateFilterPanel') &&
        !e.target.closest('[onclick*="toggleTsTextPanel"]') && !e.target.closest('[onclick*="toggleTsDatePanel"]')) {
        closeTsTextPanelAll();
    }
});

// Tutup panel saat scroll di luar panel atau resize — panel pakai posisi
// absolute terhadap header sehingga scroll bikin tidak sinkron dengan view.
window.addEventListener('scroll', function(e) {
    const t = e.target;
    if (t && t.nodeType === 1 && t.closest && (t.closest('[id^="tsTextPanel_"]') || t.closest('#tsDateFilterPanel'))) return;
    closeTsTextPanelAll();
}, true);
window.addEventListener('resize', closeTsTextPanelAll);

document.addEventListener('DOMContentLoaded', function() {
    if (typeof initCustomDropdowns === 'function') initCustomDropdowns();
    initializeDateFilters();
    loadTsPeriodBadge();
    _updateTsSortVisuals();

    // Save default thead so we can restore it after switching tabs (both modes)
    const thead = document.getElementById('timesheetTableHead');
    if (thead) {
        defaultTheadHTML = thead.innerHTML;
    }

    // Helper: attach selectAll listener (shared by both modes)
    function attachSelectAll() {
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.timesheet-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateBulkActionButtons();
            });
        }
    }

    // Auto-select locked type tab if role is restricted
    if (window.lockedType) {
        currentFilters.type_filter = window.lockedType;
    }

    // Force support spreadsheet layout for support-locked roles (HoS role_id=5 AND Delivery Support User role_id=2)
    if (window.isHoSMode || window.lockedType === 'support') {
        currentFilters.type_filter = 'support';
        supportLayoutActive = true;                                    // ← definitive flag
        const table = document.getElementById('timesheetTable');
        if (table) table.style.minWidth = '1200px';
        const thead = document.getElementById('timesheetTableHead');
        if (thead) {
            thead.innerHTML = SUPPORT_THEAD_HTML;
            const selectAllCb = document.getElementById('selectAll');
            if (selectAllCb) {
                selectAllCb.addEventListener('change', function () {
                    document.querySelectorAll('.timesheet-checkbox').forEach(cb => { cb.checked = this.checked; });
                    updateBulkActionButtons();
                });
            }
            // Re-init custom-dd dropdowns (Month/Year/Status) — the initCustomDropdowns()
            // call above ran before this innerHTML swap, so those handlers were lost.
            if (typeof initCustomDropdowns === 'function') initCustomDropdowns(thead);
        }
    }

    // Check if we are in approval mode (isHoSMode is also an approval mode)
    if (window.isApprovalMode || window.isHoSMode) {
        loadSubmittedTimesheets();
        attachSelectAll();
    } else {
        initializeTimePickers();
        loadTimesheets();
        loadStatistics();

        const form = document.getElementById('timesheetForm');
        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        attachSelectAll();
    }
});

// ==================== APPROVAL MODE FUNCTIONS ====================

// Load submitted timesheets for approval (for heads)
async function loadSubmittedTimesheets() {
    try {
        const params = new URLSearchParams();
        if (currentFilters.start_date) params.append('start_date', currentFilters.start_date);
        if (currentFilters.end_date) params.append('end_date', currentFilters.end_date);
        if (currentFilters.status) params.append('status', currentFilters.status);
        if (currentFilters.type_filter) params.append('type_filter', currentFilters.type_filter);

        const response = await fetch(`/api/timesheets/submitted-for-approval?${params}`);
        const data = await response.json();

        if (data.success) {
            timesheets = data.data;
            currentPage = 1;
            applyStatusFilter();
            updateStatCards(timesheets);
        } else {
            showEmptyState();
            showNotification('Failed to load timesheets', 'error');
        }
    } catch (error) {
        console.error('Error loading submitted timesheets:', error);
        showEmptyState();
        showNotification('An error occurred while loading timesheets', 'error');
    }
}

// Open approve confirmation modal
function openApproveModal(id) {
    const modal = document.getElementById('approveModal');
    const approveTimesheetId = document.getElementById('approveTimesheetId');

    if (approveTimesheetId) approveTimesheetId.value = id;

    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

// Close approve modal
function closeApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Switch from approve modal to reject modal (same timesheet ID)
function switchToRejectModal() {
    const id = document.getElementById('approveTimesheetId')?.value;
    closeApproveModal();
    if (id) openRejectModal(id);
}

// Confirm approve
async function confirmApprove() {
    const approveTimesheetId = document.getElementById('approveTimesheetId');
    const id = approveTimesheetId?.value;

    if (!id) return;

    try {
        const response = await fetch(`/api/timesheets/${id}/approve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Timesheet approved successfully!', 'success');
            closeApproveModal();
            await loadSubmittedTimesheets();
        } else {
            showNotification('Failed to approve timesheet: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error approving timesheet:', error);
        showNotification('An error occurred while approving timesheet', 'error');
    }
}

// Open reject modal
function openRejectModal(id) {
    const modal = document.getElementById('rejectModal');
    const rejectTimesheetId = document.getElementById('rejectTimesheetId');
    const rejectionReason = document.getElementById('rejectionReason');

    if (rejectTimesheetId) rejectTimesheetId.value = id;
    if (rejectionReason) rejectionReason.value = '';

    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

// Close reject modal
function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Confirm reject
async function confirmReject() {
    const rejectTimesheetId = document.getElementById('rejectTimesheetId');
    const rejectionReason = document.getElementById('rejectionReason');
    const id = rejectTimesheetId?.value;
    const reason = rejectionReason?.value?.trim();

    if (!id) return;

    if (!reason) {
        showNotification('Please provide a rejection reason', 'error');
        return;
    }

    try {
        const response = await fetch(`/api/timesheets/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ rejection_reason: reason })
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Timesheet rejected successfully!', 'success');
            closeRejectModal();
            await loadSubmittedTimesheets();
        } else {
            showNotification('Failed to reject timesheet: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error rejecting timesheet:', error);
        showNotification('An error occurred while rejecting timesheet', 'error');
    }
}

// ==================== END APPROVAL MODE FUNCTIONS ====================

// ── Timesheet time inputs ─────────────────────────────────────────────────────
// #timesheetStartTime / #timesheetEndTime are plain text fields with a custom
// (app-styled) dropdown of preset times — see initTsTimePickers() below and the
// [data-ts-timepicker] markup in timesheets.blade.php. The user can type an HH:MM
// value or pick one from the dropdown; tsNormalizeTimeInput() tidies loose input
// ("8", "830", "8:5") into "HH:MM" on change/blur.

// Parse "HH:MM" / "HH:MM:SS" → { h, m }, or null when malformed / out of range.
function _tsParseTime(v) {
    const match = String(v || '').match(/^(\d{1,2}):(\d{2})/);
    if (!match) return null;
    const h = parseInt(match[1], 10);
    const m = parseInt(match[2], 10);
    if (isNaN(h) || isNaN(m) || h > 23 || m > 59) return null;
    return { h, m };
}

// Time ranges (minutes since midnight, [s, e)) already used by the user's other
// project timesheets on the form's date — set by loadProjectFormContext() in
// project mode, empty otherwise. Ranges that only touch are allowed
// (08:00–12:00 then 12:00–…).
let _tsBooked = [];

const _tsToMins = p => p.h * 60 + p.m;
const _tsFmtMins = t => `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;

// A start time is taken when it falls inside a booked range.
function _tsStartBlocked(t) {
    return _tsBooked.some(b => t >= b.s && t < b.e);
}

// An end time is invalid when it is not after the start, or when [start, t)
// would swallow (part of) a booked range.
function _tsEndBlocked(t, start) {
    if (start == null) return _tsBooked.some(b => t > b.s && t <= b.e);
    if (t <= start) return true;
    return _tsBooked.some(b => b.s < t && b.e > start);
}

// The booked range [start, end) collides with, or null.
function _tsFindOverlap(start, end) {
    return _tsBooked.find(b => b.s < end && b.e > start) || null;
}

// Flag when end ≤ start or the range overlaps an already-used slot: toggles the
// inline error + native validity so the form cannot be submitted.
// Returns true when the range is valid.
function _tsValidateTimeOrder() {
    const startEl = document.getElementById('timesheetStartTime');
    const endEl   = document.getElementById('timesheetEndTime');
    const errEl   = document.getElementById('timesheetTimeError');
    if (!startEl || !endEl) return true;

    const s = _tsParseTime(startEl.value);
    const e = _tsParseTime(endEl.value);

    let msg = '';
    if (s && e) {
        if (_tsToMins(e) <= _tsToMins(s)) {
            msg = 'End time must be later than start time.';
        } else {
            const clash = _tsFindOverlap(_tsToMins(s), _tsToMins(e));
            if (clash) msg = `This time overlaps another project timesheet (${_tsFmtMins(clash.s)}–${_tsFmtMins(clash.e)}${clash.label ? ', ' + clash.label : ''}).`;
        }
    }
    const bad = !!msg;

    endEl.setCustomValidity(msg);
    endEl.classList.toggle('border-red-400', bad);
    endEl.classList.toggle('border-gray-200', !bad);
    if (errEl) {
        errEl.textContent = msg;
        errEl.classList.toggle('hidden', !bad);
    }
    return !bad;
}

function tsUpdateDuration() {
    const s = _tsParseTime(document.getElementById('timesheetStartTime')?.value);
    const e = _tsParseTime(document.getElementById('timesheetEndTime')?.value);

    const durationField = document.getElementById('timesheetDuration');
    if (!durationField) return;

    if (!s || !e) {
        if (durationField.tagName === 'INPUT') durationField.value = '—';
        else durationField.textContent = '—';
        return;
    }

    let startMins = s.h * 60 + s.m;
    let endMins   = e.h * 60 + e.m;
    if (endMins < startMins) endMins += 24 * 60;

    const dur   = endMins - startMins;
    const hours = Math.floor(dur / 60);
    const mins  = dur % 60;
    const text  = `${hours}h ${mins}m`;

    if (durationField.tagName === 'INPUT') durationField.value = text;
    else durationField.textContent = text;
}

function tsUpdateStartTime() {
    _tsValidateTimeOrder();
    tsUpdateDuration();
}

function tsUpdateEndTime() {
    _tsValidateTimeOrder();
    tsUpdateDuration();
}

// Coerce loose input into "HH:MM": "8" → "08:00", "830" → "08:30", "8:5" → "08:05".
// Leaves the field untouched when it can't make sense of it (validation flags that).
function tsNormalizeTimeInput(el) {
    if (!el) return;
    const raw = String(el.value || '').trim();
    if (!raw) return;

    let h, m;
    const colon = raw.match(/^(\d{1,2})\s*:\s*(\d{1,2})$/);
    if (colon) {
        h = parseInt(colon[1], 10);
        m = parseInt(colon[2], 10);
    } else {
        const digits = raw.replace(/\D/g, '');
        if (!digits) return;
        if (digits.length <= 2)      { h = parseInt(digits, 10); m = 0; }
        else if (digits.length === 3) { h = parseInt(digits.slice(0, 1), 10); m = parseInt(digits.slice(1), 10); }
        else                          { h = parseInt(digits.slice(0, 2), 10); m = parseInt(digits.slice(2, 4), 10); }
    }
    if (isNaN(h) || isNaN(m) || h > 23 || m > 59) return;
    el.value = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

// Render a picker panel: an hour column (00–23) and a minute column (00–59).
// Times already used (project mode) are disabled; an hour is disabled when all
// of its minutes are. Picking an hour keeps the panel open for the minute;
// picking a minute commits and closes.
function _tsRenderTimePanel(wrap) {
    const input = wrap.querySelector('input[type="text"]');
    const panel = wrap.querySelector('.ts-tp-panel');
    const side  = wrap.dataset.tsTimepicker; // 'start' | 'end'
    if (!input || !panel) return;

    const startP   = _tsParseTime(document.getElementById('timesheetStartTime')?.value);
    const startMin = startP ? _tsToMins(startP) : null;
    const blocked  = t => side === 'end' ? _tsEndBlocked(t, startMin) : _tsStartBlocked(t);

    const cur    = _tsParseTime(input.value);
    const selH   = wrap._tsPendingHour ?? (cur ? cur.h : null);
    const selM   = cur && cur.h === selH ? cur.m : null;

    const base    = 'ts-tp-item block w-full text-center px-2 py-1 text-sm font-mono';
    const enabled = 'text-gray-700 hover:bg-gray-50';
    const active  = 'bg-red-700 text-white';
    const off     = 'text-gray-300 line-through cursor-not-allowed';

    let hours = '';
    for (let h = 0; h < 24; h++) {
        let all = true;
        for (let m = 0; m < 60 && all; m++) all = blocked(h * 60 + m);
        const cls = all ? off : (h === selH ? active : enabled);
        hours += `<button type="button" data-hour="${h}" ${all ? 'disabled' : ''} class="${base} ${cls}">${String(h).padStart(2, '0')}</button>`;
    }

    let mins = '';
    if (selH == null) {
        mins = '<div class="px-2 py-3 text-xs text-gray-400 text-center">Pick an hour</div>';
    } else {
        for (let m = 0; m < 60; m++) {
            const off_ = blocked(selH * 60 + m);
            const cls  = off_ ? off : (m === selM ? active : enabled);
            mins += `<button type="button" data-minute="${m}" ${off_ ? 'disabled' : ''} class="${base} ${cls}">${String(m).padStart(2, '0')}</button>`;
        }
    }

    panel.innerHTML = `
        <div class="flex divide-x divide-gray-100">
            <div class="ts-tp-hours flex-1 overflow-y-auto py-1" style="max-height:13rem;">${hours}</div>
            <div class="ts-tp-mins flex-1 overflow-y-auto py-1" style="max-height:13rem;">${mins}</div>
        </div>`;

    panel.querySelector('.ts-tp-hours .bg-red-700')?.scrollIntoView({ block: 'nearest' });
    panel.querySelector('.ts-tp-mins .bg-red-700')?.scrollIntoView({ block: 'nearest' });
}

// Wire the custom time dropdowns: a toggle button + an hour/minute panel that
// writes the picked value into the sibling text input. Idempotent.
function initTsTimePickers() {
    document.querySelectorAll('[data-ts-timepicker]').forEach(wrap => {
        if (wrap._tsTpInit) return;
        wrap._tsTpInit = true;

        const input  = wrap.querySelector('input[type="text"]');
        const toggle = wrap.querySelector('.ts-tp-toggle');
        const panel  = wrap.querySelector('.ts-tp-panel');
        if (!input || !panel) return;

        const openPanel = () => {
            _tsCloseAllTimePanels();
            wrap._tsPendingHour = null;
            _tsRenderTimePanel(wrap);
            panel.classList.remove('hidden');
        };
        const closePanel = () => { panel.classList.add('hidden'); wrap._tsPendingHour = null; };

        if (toggle) toggle.addEventListener('click', e => {
            e.preventDefault();
            e.stopPropagation();
            panel.classList.contains('hidden') ? (input.focus(), openPanel()) : closePanel();
        });

        input.addEventListener('focus', openPanel);

        // Typing re-renders the panel so it follows the typed hour.
        input.addEventListener('input', () => {
            wrap._tsPendingHour = null;
            _tsRenderTimePanel(wrap);
            if (panel.classList.contains('hidden')) panel.classList.remove('hidden');
        });

        input.addEventListener('keydown', e => {
            if (e.key === 'Escape' || e.key === 'Enter') closePanel();
        });

        panel.addEventListener('click', e => {
            e.stopPropagation();
            const btn = e.target.closest('button');
            if (!btn || btn.disabled) return;

            if (btn.dataset.hour != null) {
                wrap._tsPendingHour = parseInt(btn.dataset.hour, 10);
                _tsRenderTimePanel(wrap);
                return;
            }
            if (btn.dataset.minute != null) {
                const cur = _tsParseTime(input.value);
                const h   = wrap._tsPendingHour ?? (cur ? cur.h : 0);
                input.value = _tsFmtMins(h * 60 + parseInt(btn.dataset.minute, 10));
                closePanel();
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    if (!window._tsTpDocListener) {
        window._tsTpDocListener = true;
        document.addEventListener('click', e => {
            if (!e.target.closest('[data-ts-timepicker]')) _tsCloseAllTimePanels();
        });
    }
}

function _tsCloseAllTimePanels() {
    document.querySelectorAll('.ts-tp-panel:not(.hidden)').forEach(p => p.classList.add('hidden'));
}

// Initialize time inputs with the default working day (08:00 – 17:00)
function initializeTimePickers() {
    const startEl = document.getElementById('timesheetStartTime');
    const endEl   = document.getElementById('timesheetEndTime');
    if (startEl && !startEl.value) startEl.value = '08:00';
    if (endEl && !endEl.value) endEl.value = '17:00';
    initTsTimePickers();
    _tsValidateTimeOrder();
    tsUpdateDuration();
}

// Helper to set a time input from an "HH:mm:ss" / "HH:mm" string
function setTimePicker(type, timeString) {
    const el = document.getElementById(`timesheet${type}Time`);
    if (!el) return;

    const p = _tsParseTime(timeString);
    el.value = p
        ? `${String(p.h).padStart(2, '0')}:${String(p.m).padStart(2, '0')}`
        : '';

    _tsValidateTimeOrder();
    tsUpdateDuration();
}

const TS_MONTHS_SHORT = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function tsCurrentPeriod() {
    const now  = new Date();
    const day  = now.getDate();
    const m0   = now.getMonth();   // 0-indexed: Jan=0 … Dec=11
    const year = now.getFullYear();

    if (day >= 21) {
        // On/after the 21st we are in NEXT month's period
        // e.g. Apr 21+ → period ending May 20 → "May period" → month=5
        // +1 converts 0-indexed to 1-indexed; +1 again for next month
        const nextM = m0 + 2;
        return nextM > 12
            ? { month: 1, year: year + 1 }  // Dec 21+ wraps to January of next year
            : { month: nextM, year };
    } else {
        // Before the 21st we are in the CURRENT month's period
        // e.g. Apr 1–20 → period ending Apr 20 → "April period" → month=4
        return { month: m0 + 1, year };
    }
}

function tsPeriodToDateRange(month, year) {
    const sm = month === 1 ? 12 : month - 1;
    const sy = month === 1 ? year - 1 : year;
    return {
        start: `${sy}-${String(sm).padStart(2,'0')}-21`,
        end:   `${year}-${String(month).padStart(2,'0')}-20`,
    };
}

function updateTsPeriodLabel() {
    const month = parseInt(document.getElementById('filterMonth')?.value);
    const year  = parseInt(document.getElementById('filterYear')?.value);
    if (!month || !year) return;
    const sm = month === 1 ? 12 : month - 1;
    const sy = month === 1 ? year - 1 : year;
    const el = document.getElementById('tsPeriodRange');
    if (el) el.textContent = `${21} ${TS_MONTHS_SHORT[sm - 1]} ${sy} – ${20} ${TS_MONTHS_SHORT[month - 1]} ${year}`;
}

function initializeDateFilters() {
    const p = tsCurrentPeriod();
    const yearEl = document.getElementById('filterYear');
    if (yearEl) yearEl.value = p.year;
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('filterMonth', String(p.month));
    } else {
        const monthEl = document.getElementById('filterMonth');
        if (monthEl) monthEl.value = p.month;
    }
    updateTsPeriodLabel();

    // Do NOT restrict by date — load all timesheets by default
    currentFilters.start_date = null;
    currentFilters.end_date   = null;
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// ✅ UPDATED: Dynamic field handling - WITH activity dropdown for Projects
function handleTimesheetTypeChange() {
    const selectedRadio = document.querySelector('input[name="timesheetType"]:checked');
    const selectedType = selectedRadio ? selectedRadio.value : 'support';

    const dynamicFieldsContainer = document.getElementById('dynamicFields');

    if (!dynamicFieldsContainer) {
        console.warn('dynamicFields element not found - skipping type change');
        return;
    }

    let fieldsHTML = '';

    const CHEVRON = `<svg class="custom-dd-arrow w-4 h-4 text-gray-400 transition-transform flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>`;
    const PRESENCE_ITEMS = `
        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select...</button>
        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-value="onsite">On-site</button>
        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-value="remote">Remote</button>
        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-value="hybrid">Hybrid</button>`;

    if (selectedType === 'project') {
        fieldsHTML = `
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                    Project <span class="text-red-500">*</span>
                </label>
                <div class="custom-dd w-full" data-fixed="true" data-onchange="onProjectSelected">
                    <button type="button" class="custom-dd-btn w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-2">
                        <span class="custom-dd-label text-gray-500 truncate flex-1 text-left">Select a Project</span>
                        ${CHEVRON}
                    </button>
                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-56">
                        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select a Project</button>
                    </div>
                    <input type="hidden" id="timesheetProjectId">
                </div>
                <p class="mt-1 text-xs text-gray-400">Active projects you are a member of</p>
            </div>

            <div id="projectActivityField">
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                    Activity <span class="text-red-500">*</span>
                </label>
                <div class="custom-dd w-full" data-fixed="true">
                    <button type="button" class="custom-dd-btn w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-2">
                        <span class="custom-dd-label text-gray-500 truncate flex-1 text-left">Select a project first</span>
                        ${CHEVRON}
                    </button>
                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-56">
                        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select a project first</button>
                    </div>
                    <input type="hidden" id="timesheetActivity">
                </div>
                <p id="projectActivityHint" class="mt-1 text-xs text-gray-400">Only activities assigned to you and running on this date</p>
            </div>

            <div id="projectNonWorkingBox" class="hidden p-3 bg-amber-50 border border-amber-200 rounded-md">
                <p class="text-xs text-amber-800">
                    <i class="fas fa-calendar-times mr-1"></i>
                    <span id="projectNonWorkingText">Today is a non-working day, so no project activity is running.</span>
                    You can still log this timesheet without an activity — describe the work in <b>Activity Detail</b>.
                </p>
                <label class="mt-2 flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="timesheetWithoutActivity" class="w-4 h-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500">
                    <span class="text-xs font-semibold text-amber-900">Log without activity</span>
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Presence <span class="text-red-500">*</span>
                    </label>
                    <div class="custom-dd w-full" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-2">
                            <span class="custom-dd-label text-gray-500 flex-1 text-left">Select...</span>
                            ${CHEVRON}
                        </button>
                        <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-48">${PRESENCE_ITEMS}</div>
                        <input type="hidden" id="timesheetPresence">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Location <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="timesheetLocation" required class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50" placeholder="e.g. Client office">
                </div>
            </div>

            <div id="projectGpsStatus" class="text-xs"></div>
        `;

    } else if (selectedType === 'support') {
        fieldsHTML = `
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                    Ticket <span class="text-red-500">*</span>
                </label>
                <div class="custom-dd w-full" data-fixed="true" data-onchange="onSupportTicketSelected" data-searchable="true" data-search-placeholder="Search ticket number, customer, or description…">
                    <button type="button" class="custom-dd-btn w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-2">
                        <span class="custom-dd-label text-gray-500 truncate flex-1 text-left">Select a Ticket</span>
                        ${CHEVRON}
                    </button>
                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-56">
                        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select a Ticket</button>
                    </div>
                    <input type="hidden" id="timesheetTicket">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                    Activity Date <span class="text-red-500">*</span>
                </label>
                <input type="date" id="supportActivityDate" required value="${formatDate(new Date())}"
                    class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50 hover:bg-white transition-colors">
                <p class="mt-1 text-xs text-gray-400">When the work actually happened — any date, no period restriction.</p>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="bg-gray-50 rounded-lg border border-gray-200 p-2.5">
                    <p class="text-xs font-semibold text-gray-400 mb-0.5">Customer</p>
                    <p class="text-xs font-semibold text-gray-700 truncate" id="supportCustomer">—</p>
                </div>
                <div class="bg-gray-50 rounded-lg border border-gray-200 p-2.5">
                    <p class="text-xs font-semibold text-gray-400 mb-0.5">Quota MD</p>
                    <p class="text-xs font-semibold text-gray-700" id="supportJatahMd">—</p>
                </div>
                <div class="bg-gray-50 rounded-lg border border-gray-200 p-2.5">
                    <p class="text-xs font-semibold text-gray-400 mb-0.5">Remaining</p>
                    <p class="text-xs font-bold text-gray-700" id="supportRemainingMd">—</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        MD Consumed <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="supportMdConsumed" required step="0.01" min="0"
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50"
                        placeholder="e.g. 0.5">
                </div>
                <div class="flex items-end pb-0.5">
                    <label class="flex items-center gap-2.5 p-2.5 bg-gray-50 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors w-full">
                        <input type="checkbox" id="supportOnSite"
                            class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-600">
                        <span class="text-xs font-semibold text-gray-700">On Site</span>
                    </label>
                </div>
            </div>
        `;


    } else if (selectedType === 'office') {
        fieldsHTML = `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Presence <span class="text-red-500">*</span>
                    </label>
                    <div class="custom-dd w-full" data-fixed="true">
                        <button type="button" class="custom-dd-btn w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-2">
                            <span class="custom-dd-label text-gray-500 flex-1 text-left">Select...</span>
                            ${CHEVRON}
                        </button>
                        <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-48">${PRESENCE_ITEMS}</div>
                        <input type="hidden" id="timesheetPresence">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Location <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="timesheetLocation" required class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50" placeholder="e.g. Office / Remote">
                </div>
            </div>
        `;

    }

    // Start/end time is now mandatory for every timesheet type (project, support, office).
    const timeBlock = document.getElementById('timesheetTimeBlock');
    if (timeBlock) timeBlock.style.display = '';

    // Inject HTML and init custom dropdowns
    dynamicFieldsContainer.innerHTML = fieldsHTML;
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns(dynamicFieldsContainer);
    }

    // Update description label and placeholder based on type
    const descLabel = document.querySelector('label[for="timesheetDescription"]');
    if (descLabel) {
        const labelText = { support: 'Activity', project: 'Activity Detail' }[selectedType] || 'Description';
        descLabel.innerHTML = `${labelText} <span class="text-red-500">*</span>`;
    }
    const timesheetDescEl = document.getElementById('timesheetDescription');
    if (timesheetDescEl) {
        timesheetDescEl.placeholder = {
            support: 'Describe what you did in this session',
            project: 'Describe the work you did (logging for an earlier day? mention the date here)',
        }[selectedType] || 'What did you work on?';
    }

    // Now load data based on type (after DOM is updated)
    if (selectedType === 'project') {
        initProjectForm();
    } else {
        resetProjectFormState();
        if (selectedType === 'support') loadTicketsForDropdown();
    }
}

// ── Project timesheet form ────────────────────────────────────────────────────
// Project timesheets are always dated today (an edit keeps its original date).
// Flow: context (non-working day + used time slots) → Project → Activity (only
// ones assigned to the user AND running on that date). On a weekend / public
// holiday no activity runs, so the user ticks "Log without activity" instead —
// likewise on a working day when the user is not assigned to any activity of
// the chosen project.
// The device GPS location is mandatory for a new project timesheet; an edit
// keeps the location captured at creation.

let _tsEditing       = null;   // timesheet object being edited (null = new)
let _tsProjectCtx    = null;   // { date, is_non_working_day, non_working_reason, booked }
let _tsProjectCtxReq = null;   // pending context promise (onProjectSelected awaits it)
let _tsNotAssigned   = false;  // selected project has no activity assigned to the user (working day)
let _tsGps           = null;   // { lat, lng, accuracy } from the device
let _tsGpsState      = 'idle'; // idle | pending | ok | denied | error | unsupported | kept

function _tsCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content;
}

// Project mode needs a fresh device location unless we're editing a timesheet
// that already is a project one (its original location is kept).
function _tsNeedsGps() {
    return !(_tsEditing && _tsEditing.delivery_projects_id);
}

function _tsDdPanel(hiddenId) {
    const dd = document.getElementById(hiddenId)?.closest('.custom-dd');
    return dd ? (dd.querySelector('.custom-dd-panel') || dd._ddPanel) : null;
}

function _tsDdPlaceholder(hiddenId, text, cls = 'text-gray-500') {
    const panel = _tsDdPanel(hiddenId);
    if (panel) panel.innerHTML = `<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm ${cls} hover:bg-gray-50" data-value="">${escapeHtml(text)}</button>`;
    setCustomDropdownValue(hiddenId, '');
}

// Called by handleTimesheetTypeChange when the Project type is active.
function initProjectForm() {
    const dateField = document.getElementById('timesheetDate');
    const date = _tsEditing?.delivery_projects_id ? _tsEditing.date : formatDate(new Date());
    if (dateField) {
        dateField.value = date;
        dateField.readOnly = true;
        dateField.classList.add('cursor-not-allowed', 'text-gray-500');
    }
    document.getElementById('timesheetDateHint')?.classList.toggle('hidden', !!_tsEditing?.delivery_projects_id);

    // A late-exception period choice would move the date — not for project.
    const periodSel = document.getElementById('timesheetPeriodSelect');
    if (periodSel) periodSel.value = '';

    loadProjectFormContext(date);
    loadMyProjectsForTimesheet();

    if (_tsNeedsGps()) {
        requestTimesheetGps();
    } else {
        _tsGpsState = 'kept';
        renderGpsStatus();
    }
}

// Leaving project mode: unlock the date, forget used time slots.
function resetProjectFormState() {
    const dateField = document.getElementById('timesheetDate');
    if (dateField) {
        dateField.readOnly = false;
        dateField.classList.remove('cursor-not-allowed', 'text-gray-500');
    }
    document.getElementById('timesheetDateHint')?.classList.add('hidden');
    _tsBooked = [];
    _tsProjectCtx = null;
    _tsProjectCtxReq = null;
    _tsNotAssigned = false;
    renderBookedTimes();
    _tsValidateTimeOrder();
}

function loadProjectFormContext(date) {
    const params = new URLSearchParams({ date });
    if (_tsEditing?.id) params.set('exclude_id', _tsEditing.id);

    _tsProjectCtx = null;
    _tsProjectCtxReq = fetch(`/api/timesheets/project-form-context?${params}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _tsCsrf() },
        credentials: 'same-origin'
    })
        .then(r => r.json())
        .then(json => {
            _tsProjectCtx = json.success ? json.data : null;
            _tsBooked = (_tsProjectCtx?.booked || []).map(b => {
                const s = _tsParseTime(b.start), e = _tsParseTime(b.end);
                const label = [b.project_name, b.activity_name].filter(Boolean).join(' · ');
                return s && e ? { s: _tsToMins(s), e: _tsToMins(e), label } : null;
            }).filter(Boolean);
            renderBookedTimes();
            _tsValidateTimeOrder();
            return _tsProjectCtx;
        })
        .catch(err => {
            console.error('Failed to load project form context', err);
            _tsProjectCtx = null;
            return null;
        });

    return _tsProjectCtxReq;
}

// "Already used: 08:00–12:00 (Project A · Activity) …" under the time picker.
function renderBookedTimes() {
    const el = document.getElementById('timesheetBookedTimes');
    if (!el) return;
    if (!_tsBooked.length) {
        el.classList.add('hidden');
        el.innerHTML = '';
        return;
    }
    el.innerHTML = '<span class="font-semibold text-gray-600"><i class="fas fa-lock mr-1 text-gray-400"></i>Already used:</span> '
        + _tsBooked.map(b => `<span class="inline-block mr-2">${_tsFmtMins(b.s)}–${_tsFmtMins(b.e)}${b.label ? ` <span class="text-gray-400">(${escapeHtml(b.label)})</span>` : ''}</span>`).join('');
    el.classList.remove('hidden');
}

async function loadMyProjectsForTimesheet() {
    const panel = _tsDdPanel('timesheetProjectId');
    if (!panel) return;
    panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-400 cursor-default" data-value="">Loading projects…</button>';

    const includeId = _tsEditing?.delivery_projects_id;
    const url = '/api/timesheets/my-projects' + (includeId ? `?include_project_id=${encodeURIComponent(includeId)}` : '');

    try {
        const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _tsCsrf() }, credentials: 'same-origin' });
        const json = await res.json();
        const projects = (res.ok && json.success) ? (json.data || []) : [];

        if (!projects.length) {
            _tsDdPlaceholder('timesheetProjectId', 'No active project you are a member of');
            return;
        }

        panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select a Project</button>'
            + projects.map(p => {
                const client = p.client?.basic_data?.name_1 || '';
                return `<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-value="${p.id}">${escapeHtml(p.name || ('Project #' + p.id))}${client ? ` <span class="text-gray-400">— ${escapeHtml(client)}</span>` : ''}</button>`;
            }).join('');

        if (includeId) {
            setCustomDropdownValue('timesheetProjectId', String(includeId));
            onProjectSelected();
        }
    } catch (err) {
        console.error('Failed to load projects', err);
        _tsDdPlaceholder('timesheetProjectId', 'Failed to load projects', 'text-red-500');
    }
}

// Project chosen → either list its activities running on the date, or (weekend /
// public holiday) offer "Log without activity".
async function onProjectSelected() {
    const projectId = document.getElementById('timesheetProjectId')?.value;
    const actField  = document.getElementById('projectActivityField');
    const nwBox     = document.getElementById('projectNonWorkingBox');
    const actHint   = document.getElementById('projectActivityHint');
    if (!actField || !nwBox) return;

    _tsNotAssigned = false;

    if (!projectId) {
        actField.classList.remove('hidden');
        nwBox.classList.add('hidden');
        _tsDdPlaceholder('timesheetActivity', 'Select a project first');
        return;
    }

    const ctx = _tsProjectCtx || await _tsProjectCtxReq;

    if (ctx?.is_non_working_day) {
        actField.classList.add('hidden');
        setCustomDropdownValue('timesheetActivity', '');
        const txt = document.getElementById('projectNonWorkingText');
        if (txt) txt.textContent = `${formatDisplayDate(ctx.date)} is a non-working day (${ctx.non_working_reason || 'holiday'}), so no project activity is running.`;
        nwBox.classList.remove('hidden');
        const cb = document.getElementById('timesheetWithoutActivity');
        if (cb && _tsEditing?.is_without_activity) cb.checked = true;
        return;
    }

    nwBox.classList.add('hidden');
    actField.classList.remove('hidden');

    const panel = _tsDdPanel('timesheetActivity');
    if (!panel) return;
    panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-400 cursor-default" data-value="">Loading activities…</button>';
    setCustomDropdownValue('timesheetActivity', '');

    const date = document.getElementById('timesheetDate')?.value || formatDate(new Date());
    try {
        const res  = await fetch(`/api/timesheets/my-activities/${encodeURIComponent(projectId)}?date=${encodeURIComponent(date)}`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': _tsCsrf() },
            credentials: 'same-origin'
        });
        const json = await res.json();
        // The user may have switched project while this was loading.
        if (document.getElementById('timesheetProjectId')?.value !== projectId) return;

        const activities = (res.ok && json.success) ? (json.data || []) : [];

        // Not assigned to any activity of this project → no activity to pick,
        // so offer "Log without activity" (same as a non-working day).
        if (res.ok && json.success && json.has_assignment === false) {
            _tsNotAssigned = true;
            actField.classList.add('hidden');
            setCustomDropdownValue('timesheetActivity', '');
            const txt = document.getElementById('projectNonWorkingText');
            if (txt) txt.textContent = 'You are not assigned to any activity in this project.';
            nwBox.classList.remove('hidden');
            const cb = document.getElementById('timesheetWithoutActivity');
            if (cb && _tsEditing?.is_without_activity) cb.checked = true;
            return;
        }

        if (!activities.length) {
            _tsDdPlaceholder('timesheetActivity', 'No activity assigned to you is running on this date');
            if (actHint) actHint.textContent = `No activity of this project assigned to you runs on ${formatDisplayDate(date)}.`;
            return;
        }

        if (actHint) actHint.textContent = `Only activities assigned to you and running on ${formatDisplayDate(date)}`;
        panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select an Activity</button>'
            + activities.map(a => {
                let label = escapeHtml(a.name);
                if (a.phase?.name) label += ` - ${escapeHtml(a.phase.name)}`;
                if (a.stage?.name) label += ` &gt; ${escapeHtml(a.stage.name)}`;
                return `<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-value="${a.id}">${label}</button>`;
            }).join('');

        if (_pendingActivityPreselect) {
            setCustomDropdownValue('timesheetActivity', String(_pendingActivityPreselect));
            _pendingActivityPreselect = null;
        }
    } catch (err) {
        console.error('Failed to load activities', err);
        _tsDdPlaceholder('timesheetActivity', 'Failed to load activities', 'text-red-500');
    }
}

// Ask the browser for the device position (HTTPS + user permission required).
function requestTimesheetGps() {
    _tsGps = null;
    if (!window.isSecureContext || !navigator.geolocation) {
        _tsGpsState = 'unsupported';
        renderGpsStatus();
        return;
    }
    _tsGpsState = 'pending';
    renderGpsStatus();

    navigator.geolocation.getCurrentPosition(
        pos => {
            _tsGps = {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
                accuracy: pos.coords.accuracy != null ? Math.round(pos.coords.accuracy) : null,
            };
            _tsGpsState = 'ok';
            renderGpsStatus();
        },
        err => {
            _tsGpsState = err.code === err.PERMISSION_DENIED ? 'denied' : 'error';
            renderGpsStatus();
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 }
    );
}

function renderGpsStatus() {
    const el = document.getElementById('projectGpsStatus');
    if (!el) return;

    const retry = '<button type="button" onclick="requestTimesheetGps()" class="font-semibold underline hover:no-underline">try again</button>';
    let html = '';
    switch (_tsGpsState) {
        case 'pending':
            html = '<div class="flex items-center gap-1.5 text-gray-500"><i class="fas fa-spinner fa-spin"></i>Getting your device location…</div>';
            break;
        case 'ok':
            html = `<div class="flex items-center gap-1.5 text-green-700"><i class="fas fa-map-marker-alt"></i>Device location captured${_tsGps?.accuracy != null ? ` (±${_tsGps.accuracy} m)` : ''}</div>`;
            break;
        case 'kept': {
            const addr = _tsEditing?.gps_address;
            html = `<div class="flex items-start gap-1.5 text-gray-500"><i class="fas fa-map-marker-alt mt-0.5"></i><span>Using the location captured when this timesheet was created${addr ? `: ${escapeHtml(addr)}` : ''}</span></div>`;
            break;
        }
        case 'denied':
            html = `<div class="p-2.5 bg-red-50 border border-red-200 rounded-md text-red-700"><i class="fas fa-map-marker-alt mr-1"></i>
                Location access is blocked. Allow location for this site in your browser (click the lock icon next to the address bar → Location → Allow), then ${retry}.
                <div class="mt-1 text-red-600">A project timesheet cannot be saved without your device location.</div></div>`;
            break;
        case 'unsupported':
            html = `<div class="p-2.5 bg-red-50 border border-red-200 rounded-md text-red-700"><i class="fas fa-map-marker-alt mr-1"></i>
                This browser cannot provide your location (location needs a secure HTTPS connection). A project timesheet cannot be saved without it.</div>`;
            break;
        case 'error':
            html = `<div class="p-2.5 bg-amber-50 border border-amber-200 rounded-md text-amber-800"><i class="fas fa-exclamation-triangle mr-1"></i>
                Could not get your location. Make sure location (GPS) is turned on for your device, then ${retry}.</div>`;
            break;
    }
    el.innerHTML = html;
}

// Load only USER'S tickets (like support.blade.php)
async function loadTicketsForDropdown() {
    const hidden = document.getElementById('timesheetTicket');
    const dd     = hidden?.closest('.custom-dd');
    const panel  = dd?.querySelector('.custom-dd-panel') || dd?._ddPanel;

    try {
        // Keep the currently-edited ticket selectable even if its remaining MD is now 0
        // (its own consumption is part of that 0) — the endpoint always includes it.
        const includeParam = _pendingTicketPreselect ? `?include_ticket_id=${encodeURIComponent(_pendingTicketPreselect)}` : '';
        const response = await fetch(`/api/tickets/my-for-timesheet${includeParam}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (response.ok) {
            const data = await response.json();

            if (panel && data.success && data.data) {
                const allTickets = data.data;
                myTicketsCache = allTickets;

                // Sort by ticket_id descending (newest first)
                allTickets.sort((a, b) => b.ticket_id - a.ticket_id);

                let itemsHtml;
                if (allTickets.length === 0) {
                    itemsHtml = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 cursor-default" data-value="">No tickets with remaining MD quota</button>';
                } else {
                    itemsHtml = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select a Ticket</button>';
                    allTickets.forEach(ticket => {
                        const ticketLabel  = ticket.ticket_number || `#${ticket.ticket_id}`;
                        const customerCode = ticket.customer?.customer_code || ticket.customer?.customer_name || '';
                        const description  = ticket.description || '';
                        const labelText    = `${ticketLabel} - ${customerCode} - ${description}`;
                        itemsHtml += `<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" data-value="${ticket.ticket_id}">${labelText}</button>`;
                    });
                }

                // Rebuild only the item buttons — a plain `panel.innerHTML = itemsHtml`
                // would also wipe out the sticky search box + empty-state that
                // _injectSearch() wired up at dropdown init, silently killing search
                // the moment tickets finish loading. Preserve those nodes instead.
                const searchWrap = panel.querySelector('.custom-dd-search-wrap');
                const emptyEl    = panel.querySelector('.custom-dd-empty');
                panel.innerHTML = '';
                if (searchWrap) panel.appendChild(searchWrap);
                panel.insertAdjacentHTML('beforeend', itemsHtml);
                if (emptyEl) panel.appendChild(emptyEl);

                if (allTickets.length === 0) return;

                // Pre-select ticket if coming from editTimesheet()
                if (_pendingTicketPreselect) {
                    const preselectId = String(_pendingTicketPreselect);
                    _pendingTicketPreselect = null;
                    setCustomDropdownValue('timesheetTicket', preselectId);
                    if (hidden && hidden.value === preselectId) {
                        onSupportTicketSelected();
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error loading tickets:', error);
        if (panel) {
            panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-red-500 cursor-default" data-value="">Failed to load tickets</button>';
        }
    }
}

// Auto-fill Customer, Jatah MD, and Remaining MD when a support ticket is selected.
// Called via data-onchange (no argument) — reads ticket ID from the hidden input.
async function onSupportTicketSelected() {
    const ticketId = document.getElementById('timesheetTicket')?.value || null;

    const customerEl  = document.getElementById('supportCustomer');
    const jatahMdEl   = document.getElementById('supportJatahMd');
    const remainingEl = document.getElementById('supportRemainingMd');

    const setText = (el, val) => { if (el) el.textContent = val; };

    // Reset
    setText(customerEl,  '—');
    setText(jatahMdEl,   '—');
    setText(remainingEl, '—');
    if (remainingEl) remainingEl.className = 'text-xs font-bold text-gray-700';
    _currentTicketRemainingMd = null;

    if (!ticketId) return;

    // Customer from cache
    const ticket = myTicketsCache.find(t => String(t.ticket_id) === String(ticketId));
    if (ticket && customerEl) {
        customerEl.textContent = ticket.customer?.customer_name || '—';
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    // Single call: remaining-md returns per-user quota AND remaining in one shot
    fetch(`/api/timesheets/remaining-md?ticket_id=${ticketId}`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin'
    }).then(r => r.ok ? r.json() : null).then(data => {
        if (!data?.success) return;
        const d = data.data;

        // Quota MD — per-user allocation (approved_mandays + approved_additional for this employee)
        setText(jatahMdEl, d.quota !== null ? formatMdTrim(d.quota) : '—');

        // Remaining MD
        if (!remainingEl) return;
        const rem = d.remaining;
        if (rem === null) { remainingEl.textContent = '—'; return; }
        remainingEl.textContent = formatMdTrim(rem);
        remainingEl.className   = rem < 0
            ? 'text-xs font-bold text-red-600'
            : 'text-xs font-bold text-green-600';
        _currentTicketRemainingMd = rem;
    });
}

// ── Period selector for late-exception users ──────────────────────────────────

let _cachedLateExceptions = null; // null = not yet fetched

/**
 * Fetch late exceptions for the current user (once per page load) and populate
 * the #periodFieldRow container in the timesheet modal.
 *
 * - If the user has active late exceptions: show a <select> listing each
 *   exception period + the active period (if any). Picking a period pre-fills
 *   the date to the last day of that period so the submission is valid.
 * - Otherwise: show the active period label as read-only info.
 */
async function loadPeriodSelector() {
    const container = document.getElementById('periodFieldRow');
    if (!container) return;

    // Show loading state
    container.innerHTML = '';
    container.classList.add('hidden');

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    try {
        // Fetch exceptions (cache after first load)
        if (_cachedLateExceptions === null) {
            const res  = await fetch('/api/timesheets/my-late-exceptions', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin'
            });
            const json = await res.json();
            _cachedLateExceptions = (res.ok && json.success) ? json.data : [];
        }

        const exceptions = _cachedLateExceptions;

        if (exceptions.length > 0) {
            // Build dropdown: active period + exception periods
            const activePeriod = await fetch('/api/periods/active', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin'
            }).then(r => r.ok ? r.json() : null).then(d => d?.data ?? null).catch(() => null);

            let options = '';
            if (activePeriod) {
                const label = new Date(activePeriod.year, activePeriod.month - 1, 1)
                    .toLocaleString('en', { month: 'long', year: 'numeric' });
                options += `<option value="" data-start="${activePeriod.start_date ?? ''}" data-end="${activePeriod.end_date ?? ''}">
                    ${label} (Active Period)
                </option>`;
            }
            exceptions.forEach(ex => {
                options += `<option value="${ex.period_id}" data-start="${ex.period_start ?? ''}" data-end="${ex.period_end ?? ''}">
                    ${ex.period_label} (Late Access)
                </option>`;
            });

            container.innerHTML = `
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Reporting Period
                    <span class="ml-1.5 text-xs font-normal text-amber-600 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">Late access granted</span>
                </label>
                <select id="timesheetPeriodSelect"
                    class="w-full px-3 py-2.5 border border-amber-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent bg-amber-50"
                    onchange="onPeriodSelected(this)">
                    ${options}
                </select>
                <p class="mt-1 text-xs text-gray-500">Select the period you want to log hours into. The date will be adjusted automatically.</p>
            `;
            container.classList.remove('hidden');

        } else {
            // No exceptions — show active period as info
            const activePeriod = await fetch('/api/periods/active', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin'
            }).then(r => r.ok ? r.json() : null).then(d => d?.data ?? null).catch(() => null);

            if (activePeriod) {
                const label = new Date(activePeriod.year, activePeriod.month - 1, 1)
                    .toLocaleString('en', { month: 'long', year: 'numeric' });
                container.innerHTML = `
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Reporting Period</label>
                    <div class="w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                        <i class="fas fa-calendar-alt mr-1.5 text-gray-400"></i>${label}
                    </div>
                `;
                container.classList.remove('hidden');
            }
        }
    } catch (e) {
        console.error('Failed to load period selector', e);
    }
}

/**
 * When a period is selected from the dropdown, pre-fill the date to
 * the end of that period (since the user is submitting late).
 */
function onPeriodSelected(select) {
    const opt       = select.options[select.selectedIndex];
    const endDate   = opt?.dataset?.end;
    const startDate = opt?.dataset?.start;
    const dateField = document.getElementById('timesheetDate');
    if (!dateField || !endDate) return;

    // Project timesheets are always dated today — a late period doesn't apply.
    if (document.querySelector('input[name="timesheetType"]:checked')?.value === 'project') {
        select.value = '';
        showNotification('Project timesheets are always logged on today\'s date.', 'info');
        return;
    }

    const today    = new Date().toISOString().split('T')[0];
    const isActive = !select.value; // empty value = active period

    if (isActive) {
        // Active period — keep today's date if it falls in the range, else use today
        dateField.value = today;
    } else {
        // Late exception period — set to end date of that period
        dateField.value = endDate;
    }
}

async function loadTimesheets() {
    try {
        const params = new URLSearchParams();
        if (currentFilters.start_date) params.append('start_date', currentFilters.start_date);
        if (currentFilters.end_date) params.append('end_date', currentFilters.end_date);
        if (currentFilters.status) params.append('status', currentFilters.status);
        
        const response = await fetch(`/api/timesheets?${params}`);
        const data = await response.json();
        
        if (data.success) {
            timesheets = data.data;
            currentPage = 1;
            applyStatusFilter();
            updateStatCards(timesheets);
        } else {
            showEmptyState();
            showNotification('Failed to load timesheets', 'error');
        }
    } catch (error) {
        showEmptyState();
        showNotification('An error occurred while loading timesheets', 'error');
    }
}

async function loadStatistics() {
    // Stats are computed client-side from the loaded timesheets array
    updateStatCards(timesheets);
}

// Update all stat cards from the full timesheets array (not filtered)
function updateStatCards(all) {
    const total = all.length;
    const draft = all.filter(t => t.status === 'draft').length;
    const submitted = all.filter(t => t.status === 'submitted').length;
    const approved = all.filter(t => t.status === 'approved').length;
    const rejected = all.filter(t => t.status === 'rejected').length;

    const el = id => document.getElementById(id);
    if (el('statTotal'))         el('statTotal').textContent         = total;
    if (el('statDraftCount'))    el('statDraftCount').textContent    = draft;
    if (el('statSubmittedCount'))el('statSubmittedCount').textContent= submitted;
    if (el('statApprovedCount')) el('statApprovedCount').textContent = approved;
    if (el('statRejectedCount')) el('statRejectedCount').textContent = rejected;
}

// Fill the Year column dropdown with the distinct years present in the loaded
// data (newest first). Month is a fixed 12-item list, but the available years
// depend on the dataset — so they are built here and refreshed on each render.
// Rebuild is skipped when the year set is unchanged to avoid churn while the
// user interacts with other filters. Any prior selection is preserved.
let _tsYearDdSig = '';
function _populateTsYearDd() {
    const panel  = document.querySelector('#ddColFilterTsYear .custom-dd-panel');
    const hidden = document.getElementById('colFilterTsYear');
    if (!panel || !hidden) return; // Year dropdown only exists in the support layout

    const years = [...new Set((timesheets || [])
        .map(t => t.period_year)
        .filter(y => y != null && y !== '')
        .map(Number))]
        .sort((a, b) => b - a);

    // Skip rebuild only when both the year set AND the rendered options are
    // already current — a fresh thead swap leaves just the "All" item, which
    // must still be repopulated even if the underlying year set is unchanged.
    const sig = years.join(',');
    if (sig === _tsYearDdSig && panel.querySelectorAll('.custom-dd-item').length === years.length + 1) return;
    _tsYearDdSig = sig;

    const prev = hidden.value;
    let html = `<button type="button" class="${DD_ITEM}" data-value="">All</button>`;
    years.forEach(y => { html += `<button type="button" class="${DD_ITEM}" data-value="${y}">${y}</button>`; });
    panel.innerHTML = html;

    // Restore prior selection if it still exists in the new set; otherwise reset to All.
    setCustomDropdownValue('colFilterTsYear', (prev && years.map(String).includes(prev)) ? prev : '');
}

// Apply all client-side filters and re-render
function applyStatusFilter() {
    _populateTsYearDd();   // keep Year dropdown options in sync with the loaded data
    let result = timesheets;

    // 1. Type tab filter
    const activeType = currentFilters.type_filter || window.lockedType || '';
    if (activeType === 'project') {
        result = result.filter(t => !!t.delivery_projects_id);
    } else if (activeType === 'support') {
        result = result.filter(t => !t.delivery_projects_id && !!t.ticket_id);
    } else if (activeType === 'office') {
        result = result.filter(t => !t.delivery_projects_id && !t.ticket_id);
    }

    // 2. Stat card status filter (overridden by column Status filter when set)
    if (currentFilters.status) {
        result = result.filter(t => t.status === currentFilters.status);
    }

    // 3. Column filters — read from new IDs
    const colEmp      = (document.getElementById('colFilterTsEmployee')?.value    || '').toLowerCase().trim();
    const colStatus   =  document.getElementById('colFilterTsStatus')?.value      || '';
    const colActType  = (document.getElementById('colFilterTsActivityType')?.value || '').toLowerCase().trim();
    const colTicket   = (document.getElementById('colFilterTsTicket')?.value      || '').toLowerCase().trim();
    const colCustomer = (document.getElementById('colFilterTsCustomer')?.value    || '').toLowerCase().trim();
    const colMonth    =  document.getElementById('colFilterTsMonth')?.value        || '';
    const colYear     =  document.getElementById('colFilterTsYear')?.value         || '';
    const colType     =  document.getElementById('colFilterTsType')?.value         || '';

    if (colEmp)      result = result.filter(t => (t.employee_name || '').toLowerCase().includes(colEmp));
    if (colStatus)   result = result.filter(t => t.status === colStatus);
    if (colActType)  result = result.filter(t => (t.activity_type || '').toLowerCase().includes(colActType));
    if (colTicket)   result = result.filter(t => (t.ticket_number || '').toLowerCase().includes(colTicket));
    if (colCustomer) result = result.filter(t => (t.customer_name || '').toLowerCase().includes(colCustomer));
    if (colMonth)    result = result.filter(t => String(t.period_month) === colMonth);
    if (colYear)     result = result.filter(t => String(t.period_year) === colYear);
    if (colType)     result = result.filter(t => t.ticket_id && (colType === 'internal' ? t.ticket_type === 'Internal' : t.ticket_type !== 'Internal'));

    // Date range filter (Date column From/To — sama seperti view ticket)
    const dateFrom = document.getElementById('tsDateFrom')?.value || '';
    const dateTo   = document.getElementById('tsDateTo')?.value   || '';
    if (dateFrom) result = result.filter(t => (t.date || '').slice(0, 10) >= dateFrom);
    if (dateTo)   result = result.filter(t => (t.date || '').slice(0, 10) <= dateTo);

    // 4. Sort
    if (tsSortKey === 'date') {
        result = [...result].sort((a, b) => {
            const da = a.date ? new Date(a.date).getTime() : 0;
            const db = b.date ? new Date(b.date).getTime() : 0;
            return tsSortDir === 'asc' ? da - db : db - da;
        });
    } else if (tsSortKey === 'employee') {
        result = [...result].sort((a, b) => {
            const na = (a.employee_name || '').toLowerCase();
            const nb = (b.employee_name || '').toLowerCase();
            return tsSortDir === 'asc' ? na.localeCompare(nb) : nb.localeCompare(na);
        });
    }

    filteredTimesheets = result;
    updateSupportMdSummary(activeType);
    renderTimesheetRows();
}

// Support-only summary cards: Total Quota MD (unique per ticket+employee, so a
// ticket with several timesheet rows doesn't get its quota counted more than once)
// and Total MD Consumed (summed across every filtered row). Rejected rows are
// excluded from both — always recomputed from filteredTimesheets, so it tracks
// whatever the table's current filters (search/status/date/etc.) are showing.
function updateSupportMdSummary(activeType) {
    const wrap = document.getElementById('supportMdSummary');
    if (!wrap) return;

    if (activeType !== 'support') {
        wrap.classList.add('hidden');
        return;
    }
    wrap.classList.remove('hidden');

    const rows = filteredTimesheets.filter(t => t.status !== 'rejected');

    let consumed = 0;
    let quota = 0;
    const quotaSeen = new Set();
    rows.forEach(t => {
        consumed += Number(t.md_consumed) || 0;
        if (t.ticket_id != null && t.jatah_md != null) {
            const key = `${t.ticket_id}_${t.employee_id}`;
            if (!quotaSeen.has(key)) {
                quotaSeen.add(key);
                quota += Number(t.jatah_md) || 0;
            }
        }
    });

    const quotaEl    = document.getElementById('statSupportQuotaMd');
    const consumedEl = document.getElementById('statSupportConsumedMd');
    if (quotaEl)    quotaEl.textContent    = formatMdTrim(quota);
    if (consumedEl) consumedEl.textContent = formatMdTrim(consumed);
}

// 12 → "12", 12.5 → "12.5", 12.25 → "12.25" — round to 2 decimals first (avoids
// floating-point noise like 12.299999999996) then drop trailing zeros.
function formatMdTrim(num) {
    return parseFloat((Number(num) || 0).toFixed(2)).toString();
}

// Called by custom-dd data-onchange and text panel oninput
function applyColFilter() {
    currentPage = 1;
    // Sync stat card highlight with Status column filter
    const colStatus = document.getElementById('colFilterTsStatus')?.value || '';
    if (colStatus !== currentFilters.status) {
        currentFilters.status = '';
        const cardIds = ['cardAll', 'cardDraft', 'cardSubmitted', 'cardApproved', 'cardRejected'];
        cardIds.forEach(id => {
            const c = document.getElementById(id);
            if (!c) return;
            c.classList.remove('border-2', 'border-red-600');
            c.classList.add('border', 'border-gray-200');
        });
        const mapToCard = { draft: 'cardDraft', submitted: 'cardSubmitted', approved: 'cardApproved', rejected: 'cardRejected' };
        const activeCard = document.getElementById(colStatus ? (mapToCard[colStatus] || 'cardAll') : 'cardAll');
        if (activeCard) {
            activeCard.classList.remove('border', 'border-gray-200');
            activeCard.classList.add('border-2', 'border-red-600');
        }
    }
    _updateTsFilterIcons();
    applyStatusFilter();
}

// ── Sort & panel helpers ────────────────────────────────────────────────────

// Klik header → toggle langsung antara descending (default) ↔ ascending.
function toggleTsSort(key) {
    // Ganti kolom sort → mulai dari ascending; kolom sama → toggle arah.
    if (tsSortKey === key) {
        tsSortDir = tsSortDir === 'asc' ? 'desc' : 'asc';
    } else {
        tsSortKey = key;
        tsSortDir = 'asc';
    }
    _updateTsSortVisuals();
    closeTsTextPanelAll();
    currentPage = 1;
    applyStatusFilter();
}

function toggleTsDateSort() { toggleTsSort('date'); }
function toggleTsEmpSort()  { toggleTsSort('employee'); }

function _updateTsSortVisuals() {
    const dateIcon = document.getElementById('tsSortDateIcon');
    if (dateIcon) {
        dateIcon.textContent = tsSortKey === 'date' ? (tsSortDir === 'asc' ? '↑' : '↓') : '↓';
        dateIcon.classList.toggle('text-red-500', tsSortKey === 'date');
        dateIcon.classList.toggle('text-gray-300', tsSortKey !== 'date');
    }
    const empIcon = document.getElementById('tsSortEmpIcon');
    if (empIcon) {
        empIcon.textContent = tsSortKey === 'employee' ? (tsSortDir === 'asc' ? '↑' : '↓') : '⇅';
        empIcon.classList.toggle('text-red-500', tsSortKey === 'employee');
        empIcon.classList.toggle('text-gray-300', tsSortKey !== 'employee');
    }
}

function _updateTsFilterIcons() {
    [['Employee', 'colFilterTsEmployee'], ['Ticket', 'colFilterTsTicket'], ['Customer', 'colFilterTsCustomer'], ['ActivityType', 'colFilterTsActivityType']].forEach(([key, id]) => {
        const icon  = document.getElementById('tsTextIcon_' + key);
        const input = document.getElementById(id);
        if (!icon) return;
        const active = !!(input?.value);
        icon.classList.toggle('text-red-500', active);
        icon.classList.toggle('text-gray-300', !active);
    });
    // Date range indicator
    const dateIcon = document.getElementById('tsDateFilterIcon');
    if (dateIcon) {
        const active = !!(document.getElementById('tsDateFrom')?.value || document.getElementById('tsDateTo')?.value);
        dateIcon.classList.toggle('text-red-500', active);
        dateIcon.classList.toggle('text-gray-300', !active);
    }
}

// ── Date Range Filter (Date column) ──────────────────────────────────────────
function toggleTsDatePanel(event) {
    event.stopPropagation();
    const panel = document.getElementById('tsDateFilterPanel');
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeTsTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) panel.classList.remove('hidden');
}

function applyTsDateFilter() {
    const from = document.getElementById('tsDateFrom')?.value || '';
    const to   = document.getElementById('tsDateTo')?.value   || '';
    const err  = document.getElementById('tsDateFilterError');
    if (from && to && to < from) { if (err) err.classList.remove('hidden'); return; }
    if (err) err.classList.add('hidden');
    document.getElementById('tsDateFilterPanel')?.classList.add('hidden');
    _updateTsFilterIcons();
    currentPage = 1;
    applyStatusFilter();
}

function clearTsDateFilter() {
    const from = document.getElementById('tsDateFrom'); if (from) from.value = '';
    const to   = document.getElementById('tsDateTo');   if (to)   to.value   = '';
    const err  = document.getElementById('tsDateFilterError'); if (err) err.classList.add('hidden');
    _updateTsFilterIcons();
    currentPage = 1;
    applyStatusFilter();
}

function toggleTsTextPanel(event, key) {
    event.stopPropagation();
    const panel = document.getElementById('tsTextPanel_' + key);
    if (!panel) return;
    const wasHidden = panel.classList.contains('hidden');
    closeTsTextPanelAll();
    if (typeof _closeAllDropdowns === 'function') _closeAllDropdowns();
    if (wasHidden) {
        panel.classList.remove('hidden');
        const inp = panel.querySelector('input[type="text"]');
        if (inp) setTimeout(() => inp.focus(), 30);
    }
}

function closeTsTextPanelAll() {
    document.querySelectorAll('[id^="tsTextPanel_"]').forEach(p => p.classList.add('hidden'));
    document.getElementById('tsDateFilterPanel')?.classList.add('hidden');
}

function clearTsTextPanel(key) {
    const panel = document.getElementById('tsTextPanel_' + key);
    if (!panel) return;
    const inp = document.getElementById('colFilterTs' + key);
    if (inp) { inp.value = ''; applyColFilter(); }
}

// Type tab click handler
function filterByType(type) {
    currentFilters.type_filter = type;
    currentPage = 1;

    // Update tab visuals
    const tabs = {
        '':        { id: 'typeTabAll',     active: 'border-red-600 bg-red-600 text-white',       inactive: 'border-gray-200 bg-white text-gray-600 hover:border-red-400 hover:text-red-600' },
        'project': { id: 'typeTabProject', active: 'border-blue-600 bg-blue-600 text-white',     inactive: 'border-gray-200 bg-white text-gray-600 hover:border-blue-400 hover:text-blue-600' },
        'support': { id: 'typeTabSupport', active: 'border-purple-600 bg-purple-600 text-white', inactive: 'border-gray-200 bg-white text-gray-600 hover:border-purple-400 hover:text-purple-600' },
        'office':  { id: 'typeTabOffice',  active: 'border-gray-600 bg-gray-600 text-white',     inactive: 'border-gray-200 bg-white text-gray-600 hover:border-gray-400 hover:text-gray-700' },
    };

    Object.entries(tabs).forEach(([key, cfg]) => {
        const btn = document.getElementById(cfg.id);
        if (!btn) return;
        if (key === type) {
            btn.className = `type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 transition-all duration-150 ${cfg.active}`;
        } else {
            btn.className = `type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 transition-all duration-150 ${cfg.inactive}`;
        }
    });

    // Swap thead and table min-width for support (both employee and approval modes)
    const thead = document.getElementById('timesheetTableHead');
    const table = document.getElementById('timesheetTable');
    if (thead) {
        if (type === 'support') {
            supportLayoutActive = true;                                // ← definitive flag ON
            thead.innerHTML = SUPPORT_THEAD_HTML;
            if (table) table.style.minWidth = '1320px';
        } else {
            supportLayoutActive = false;                               // ← definitive flag OFF
            thead.innerHTML = defaultTheadHTML;
            if (table) table.style.minWidth = '900px';
        }
        // Re-attach selectAll listener after thead swap
        const selectAllCb = document.getElementById('selectAll');
        if (selectAllCb) {
            selectAllCb.addEventListener('change', function () {
                const checkboxes = document.querySelectorAll('.timesheet-checkbox');
                checkboxes.forEach(cb => { cb.checked = this.checked; });
                updateBulkActionButtons();
            });
        }
        // Init custom-dd dropdowns in the new thead
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns(thead);
        // Restore sort visuals
        _updateTsSortVisuals();
    }

    applyStatusFilter();
}

// Stat card click handler
function filterByStatus(status) {
    currentFilters.status = status;
    currentPage = 1;

    // Update active card visual
    const cardIds = ['cardAll', 'cardDraft', 'cardSubmitted', 'cardApproved', 'cardRejected'];
    const statusMap = { '': 'cardAll', draft: 'cardDraft', submitted: 'cardSubmitted', approved: 'cardApproved', rejected: 'cardRejected' };
    cardIds.forEach(id => {
        const card = document.getElementById(id);
        if (!card) return;
        card.classList.remove('border-2', 'border-red-600');
        card.classList.add('border', 'border-gray-200');
    });
    const activeCard = document.getElementById(statusMap[status] || 'cardAll');
    if (activeCard) {
        activeCard.classList.remove('border', 'border-gray-200');
        activeCard.classList.add('border-2', 'border-red-600');
    }

    // Sync the column Status custom-dd in the thead
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('colFilterTsStatus', status);
    }

    applyStatusFilter();
}

function resetFilters() {
    // 1. Reset current filters state
    currentFilters.status        = '';
    currentFilters.activity_type = '';
    currentFilters.type_filter   = window.lockedType || '';
    currentPage = 1;

    // 2. Reset custom-dd column filters
    if (typeof setCustomDropdownValue === 'function') {
        setCustomDropdownValue('colFilterTsStatus', '');
        setCustomDropdownValue('colFilterTsMonth', '');
        setCustomDropdownValue('colFilterTsYear', '');
        setCustomDropdownValue('colFilterTsType', '');
    }

    // 3. Clear text search inputs
    ['Employee', 'Ticket', 'Customer', 'ActivityType'].forEach(k => {
        const inp = document.getElementById(k === 'ActivityType' ? 'colFilterTsActivityType' : 'colFilterTs' + k);
        if (inp) inp.value = '';
    });
    // 3b. Clear date range filter
    const tsdFrom = document.getElementById('tsDateFrom'); if (tsdFrom) tsdFrom.value = '';
    const tsdTo   = document.getElementById('tsDateTo');   if (tsdTo)   tsdTo.value   = '';
    const tsdErr  = document.getElementById('tsDateFilterError'); if (tsdErr) tsdErr.classList.add('hidden');

    // 4. Reset sort to date desc
    tsSortKey = 'date';
    tsSortDir = 'desc';
    _updateTsSortVisuals();
    _updateTsFilterIcons();

    // 5. Close any open panels
    closeTsTextPanelAll();

    // 2. Reset thead / supportLayoutActive flag without triggering a render
    if (!window.lockedType) {
        // Restore default thead and clear support flag
        const thead = document.getElementById('timesheetTableHead');
        const table = document.getElementById('timesheetTable');
        if (thead) thead.innerHTML = defaultTheadHTML;
        if (table) table.style.minWidth = '900px';
        supportLayoutActive = false;

        // Re-attach selectAll listener after thead swap
        const selectAllCb = document.getElementById('selectAll');
        if (selectAllCb) {
            selectAllCb.addEventListener('change', function () {
                document.querySelectorAll('.timesheet-checkbox').forEach(cb => { cb.checked = this.checked; });
                updateBulkActionButtons();
            });
        }
        // Re-init custom-dd in restored thead
        if (typeof initCustomDropdowns === 'function') initCustomDropdowns(thead);
        _updateTsSortVisuals();

        // Reset type tab visual to "All"
        const tabs = {
            '':        { id: 'typeTabAll',     active: 'border-red-600 bg-red-600 text-white',       inactive: 'border-gray-200 bg-white text-gray-600 hover:border-red-400 hover:text-red-600' },
            'project': { id: 'typeTabProject', active: 'border-blue-600 bg-blue-600 text-white',     inactive: 'border-gray-200 bg-white text-gray-600 hover:border-blue-400 hover:text-blue-600' },
            'support': { id: 'typeTabSupport', active: 'border-purple-600 bg-purple-600 text-white', inactive: 'border-gray-200 bg-white text-gray-600 hover:border-purple-400 hover:text-purple-600' },
            'office':  { id: 'typeTabOffice',  active: 'border-gray-600 bg-gray-600 text-white',     inactive: 'border-gray-200 bg-white text-gray-600 hover:border-gray-400 hover:text-gray-700' },
        };
        Object.entries(tabs).forEach(([key, cfg]) => {
            const btn = document.getElementById(cfg.id);
            if (!btn) return;
            btn.className = `type-tab-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold border-2 transition-all duration-150 ${key === '' ? cfg.active : cfg.inactive}`;
        });
    } else if (window.lockedType !== 'support') {
        supportLayoutActive = false;
    }

    // 3. Reset stat card visual to "Total"
    const cardIds  = ['cardAll', 'cardDraft', 'cardSubmitted', 'cardApproved', 'cardRejected'];
    const statusMap = { '': 'cardAll', draft: 'cardDraft', submitted: 'cardSubmitted', approved: 'cardApproved', rejected: 'cardRejected' };
    cardIds.forEach(id => {
        const card = document.getElementById(id);
        if (!card) return;
        card.classList.remove('border-2', 'border-red-600');
        card.classList.add('border', 'border-gray-200');
    });
    const activeCard = document.getElementById('cardAll');
    if (activeCard) {
        activeCard.classList.remove('border', 'border-gray-200');
        activeCard.classList.add('border-2', 'border-red-600');
    }

    // 4. Single fetch — triggers exactly one render via applyStatusFilter()
    if (window.isApprovalMode || window.isHoSMode) {
        loadSubmittedTimesheets();
    } else {
        loadTimesheets();
    }
}

// Apakah ada filter/search kolom yang sedang aktif? Dipakai untuk memilih pesan
// "no result" — kalau ada filter, user butuh tombol Clear Filters, bukan ajakan
// membuat timesheet baru.
function tsHasActiveFilters() {
    const ids = ['colFilterTsEmployee', 'colFilterTsTicket', 'colFilterTsCustomer', 'colFilterTsActivityType',
                 'colFilterTsStatus', 'colFilterTsMonth', 'colFilterTsYear', 'colFilterTsType', 'tsDateFrom', 'tsDateTo'];
    if (ids.some(id => !!(document.getElementById(id)?.value))) return true;
    return !!(currentFilters.status || currentFilters.activity_type);
}

// Baris "kosong" di dalam <tbody>, lebarnya mengikuti jumlah kolom thead yang
// sedang aktif (thead di-swap saat mode Support / approval).
function renderTimesheetEmptyRow() {
    const colCount = document.querySelectorAll('#timesheetTable thead th').length || 1;
    const filtered = tsHasActiveFilters();

    let title, subtitle, action = '';
    if (filtered) {
        title = 'No timesheets found';
        subtitle = 'Try adjusting your filters or search terms';
        action = `
            <button onclick="resetFilters()"
                class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-sm">
                <i class="fas fa-times text-xs"></i>Clear Filters
            </button>`;
    } else if (window.isApprovalMode || window.isHoSMode) {
        title = 'No Timesheets Pending Approval';
        subtitle = 'All employee timesheets have been reviewed';
    } else {
        title = 'No Timesheets Found';
        subtitle = 'Try adjusting your filters or create a new timesheet';
        if (window.canCreateTimesheet) {
            action = `
                <button onclick="openTimesheetModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-xl hover:opacity-90 transition-all shadow-sm">
                    <i class="fas fa-plus text-xs"></i>Create Timesheet
                </button>`;
        }
    }

    return `
        <tr>
            <td colspan="${colCount}" class="px-4 py-16 text-center">
                <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4 mx-auto">
                    <i class="fas fa-${filtered ? 'search' : 'clock'} text-gray-300 text-2xl"></i>
                </div>
                <p class="text-gray-700 font-semibold mb-1">${title}</p>
                <p class="text-gray-400 text-xs ${action ? 'mb-5' : ''}">${subtitle}</p>
                ${action}
            </td>
        </tr>`;
}

function renderTimesheetRows() {
    const tbody = document.getElementById('timesheetsTableBody');
    const emptyState = document.getElementById('emptyState');

    if (!tbody) return;

    // Hasil kosong TIDAK menyembunyikan tabel (pola halaman Ticket): header, toolbar,
    // dan popup search/filter tetap tampil supaya filter yang bikin kosong masih bisa
    // dilihat & dihapus. #emptyState hanya untuk kegagalan load (lihat showEmptyState()).
    if (emptyState) emptyState.classList.add('hidden');

    if (filteredTimesheets.length === 0) {
        tbody.innerHTML = renderTimesheetEmptyRow();
        updatePagination(0, 0, 0);
        updateBulkActionButtons();
        return;
    }

    // Pagination
    const total = filteredTimesheets.length;
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, total);
    const pageItems = filteredTimesheets.slice(start, end);
    updatePagination(total, start + 1, end);

    // Resolve active type — same logic as applyStatusFilter() so they always agree
    const activeType = currentFilters.type_filter || window.lockedType || '';

    // ── Support spreadsheet layout ──
    // supportLayoutActive is the definitive flag set whenever the support thead is active.
    // The other checks are fallbacks. If ANY is true → use support spreadsheet rendering.
    if (supportLayoutActive || window.isHoSMode || activeType === 'support' || window.lockedType === 'support') {
        var approvalMode = !!(window.isHoSMode || window.isApprovalMode);

        var rows = '';
        for (var si = 0; si < pageItems.length; si++) {
            var ts = pageItems[si];
            var sc = statusColors[ts.status] || statusColors.draft;
            var isSubmitted = ts.status === 'submitted';
            var canAct    = approvalMode ? isSubmitted : (['draft','rejected'].indexOf(ts.status) !== -1);
            var canDelete = !approvalMode && (['draft','rejected','submitted'].indexOf(ts.status) !== -1);

            var dObj  = ts.date ? new Date(ts.date + 'T00:00:00') : null;
            var dFmt  = dObj ? dObj.toLocaleDateString('en-GB', { day:'2-digit', month:'2-digit', year:'numeric' }).replace(/\//g, '/') : '-';
            var adObj = ts.activity_date ? new Date(ts.activity_date + 'T00:00:00') : null;
            var adFmt = adObj ? adObj.toLocaleDateString('en-GB', { day:'2-digit', month:'2-digit', year:'numeric' }).replace(/\//g, '/') : '-';
            // Use server-assigned period if available (handles overridden closed periods), else compute client-side
            var bln, thn;
            if (ts.period_month != null && ts.period_year != null) {
                bln = ts.period_month;
                thn = ts.period_year;
            } else {
                var per = dObj ? getPeriodInfo(dObj) : null;
                bln = per ? per.month : '-';
                thn = per ? per.year  : '-';
            }
            var tim   = tsTimeRange(ts);
            var nam   = escapeHtml(ts.employee_name || '-');
            var tktLabel = ts.ticket_number ? ('#' + escapeHtml(ts.ticket_number)) : (ts.ticket_id ? ('#' + ts.ticket_id) : '-');
            var tkt   = ts.ticket_id
                ? '<a href="/ticket/' + ts.ticket_id + '" onclick="event.stopPropagation()" class="hover:underline hover:text-purple-900">' + tktLabel + '</a>'
                : tktLabel;
            var tdesc = escapeHtml(ts.ticket_description || '-');
            var cust  = escapeHtml(ts.customer_name || '-');
            var typeCell = '-';
            if (ts.ticket_id) {
                var isInternalTkt = ts.ticket_type === 'Internal';
                typeCell = '<span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full ' + (isInternalTkt ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700') + '">' + (isInternalTkt ? 'Internal' : 'Non Internal') + '</span>';
            }
            var jmd   = ts.jatah_md   != null ? formatMdTrim(ts.jatah_md)   : '-';
            var akt   = escapeHtml(ts.description || '-');
            var mdc   = ts.md_consumed != null ? formatMdTrim(ts.md_consumed) : '-';
            var ons   = ts.presence === 'onsite' ? 'X' : '';

            // Row click and first cell
            var trClick = '';
            var firstTd = '';
            if (approvalMode) {
                // In approval mode: submitted rows show checkbox (for bulk approve/reject)
                // and row click → single approve modal. Non-submitted rows show lock icon.
                if (isSubmitted) {
                    trClick = 'onclick="openApproveModal(' + ts.id + ')"';
                    firstTd = '<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="' + ts.id + '" data-status="submitted" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">';
                } else {
                    firstTd = '<span class="text-gray-300"><i class="fas fa-lock text-xs" title="' + ts.status + '"></i></span>';
                }
            } else {
                // Employee mode: draft/rejected rows are editable; submitted rows can only be deleted
                if (canAct) {
                    trClick = 'onclick="editTimesheet(' + ts.id + ')"';
                    firstTd = '<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="' + ts.id + '" data-status="' + ts.status + '" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">';
                } else if (canDelete) {
                    firstTd = '<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="' + ts.id + '" data-status="' + ts.status + '" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">';
                } else {
                    firstTd = '<span class="text-gray-300"><i class="fas fa-lock text-xs" title="Cannot edit (' + ts.status + ')"></i></span>';
                }
            }

            // Status cell
            var statusCell = '';
            var rejIcon = '';
            if (ts.status === 'rejected' && ts.rejection_reason) {
                rejIcon = ' <i class="fas fa-info-circle text-yellow-500 cursor-pointer text-xs" title="' + escapeHtml(ts.rejection_reason) + '" onclick="event.stopPropagation();showRejectionReason(' + ts.id + ')"></i>';
            }
            var approverNote = '';
            if (ts.status === 'approved' && ts.approver_name) {
                approverNote = '<div class="text-[10px] text-gray-400 mt-0.5">by ' + escapeHtml(ts.approver_name) + '</div>';
            }
            statusCell = '<span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full ' + sc.bg + ' ' + sc.text + '">'
                + (ts.status.charAt(0).toUpperCase() + ts.status.slice(1))
                + '</span>' + rejIcon + approverNote;

            var rowClass = 'hover:bg-purple-50/30 transition-colors' + ((approvalMode && isSubmitted) ? ' cursor-pointer' : ((!approvalMode && canAct) ? ' cursor-pointer' : ''));
            rows += '<tr class="' + rowClass + '" ' + trClick + '>'
                + '<td class="px-3 py-2 border-b border-gray-100">' + firstTd + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs text-gray-700">' + dFmt + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs text-gray-700">' + adFmt + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs text-gray-700">' + tim + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">' + bln + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">' + thn + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-800 font-medium">' + nam + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap">' + statusCell + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs font-semibold text-purple-700"><i class="fas fa-ticket-alt mr-1 opacity-60"></i>' + tkt + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-600 max-w-[180px]" title="' + escapeHtml(ts.ticket_description || '') + '">' + tdesc + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700">' + cust + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap">' + typeCell + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-semibold text-gray-800">' + jmd + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700 max-w-[180px]" title="' + escapeHtml(ts.description || '') + '">' + akt + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-semibold text-gray-800">' + mdc + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-bold text-green-700">' + ons + '</td>'
                + '</tr>';
        }
        tbody.innerHTML = rows;
        updateBulkActionButtons();
        return;
    }

    // ── Approval mode: All / Project / Office tabs ────────────────────────
    if (window.isApprovalMode && activeType !== 'support') {
        tbody.innerHTML = pageItems.map(timesheet => {
            const statusColor = statusColors[timesheet.status] || statusColors.draft;
            const duration = (timesheet.duration_minutes / 60).toFixed(2);
            const canSelect = timesheet.status === 'submitted';

            const isProject = !!timesheet.delivery_projects_id;
            const isSupport = !isProject && !!timesheet.ticket_id;

            let typeInfo;
            if (isProject)      typeInfo = '<span class="text-blue-600 text-xs font-medium">Project</span>';
            else if (isSupport) typeInfo = '<span class="text-purple-600 text-xs font-medium">Support</span>';
            else                typeInfo = '<span class="text-gray-500 text-xs font-medium">Office</span>';

            const projCells = isProject ? _tsProjectCells(timesheet) : null;

            let projectTicketCell;
            if (isProject) {
                projectTicketCell = projCells.projectCell;
            } else if (isSupport) {
                const ticketLabel = timesheet.ticket_number ? `#${timesheet.ticket_number}` : `#${timesheet.ticket_id}`;
                const customerName = timesheet.customer_name || '';
                projectTicketCell = `
                    <div class="text-sm font-medium text-gray-900"><i class="fas fa-ticket-alt mr-1 text-purple-500"></i>${escapeHtml(ticketLabel)}</div>
                    ${customerName ? `<div class="text-xs text-gray-500 mt-0.5">${escapeHtml(customerName)}</div>` : ''}`;
            } else {
                const presenceLabel = timesheet.presence ? timesheet.presence.charAt(0).toUpperCase() + timesheet.presence.slice(1) : '';
                const locationText  = timesheet.location  ? ` · ${timesheet.location}` : '';
                projectTicketCell = `
                    <div class="text-sm text-gray-600"><i class="fas fa-building mr-1 text-gray-400"></i>Office</div>
                    ${presenceLabel ? `<div class="text-xs text-gray-400 mt-0.5">${escapeHtml(presenceLabel + locationText)}</div>` : ''}`;
            }

            let activityCell;
            if (isProject) {
                activityCell = projCells.activityCell;
            } else if (isSupport) {
                const mdVal = timesheet.md_consumed != null ? formatMdTrim(timesheet.md_consumed) : '—';
                const onSiteBadge = timesheet.presence === 'onsite'
                    ? '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-semibold"><i class="fas fa-map-marker-alt"></i>On Site</span>'
                    : '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-semibold"><i class="fas fa-wifi"></i>Remote</span>';
                activityCell = `<div class="flex items-center gap-1.5 mb-0.5">${onSiteBadge}</div><div class="text-xs text-gray-600">MD: <span class="font-semibold">${mdVal}</span></div>`;
            } else {
                const presenceLabel = timesheet.presence ? timesheet.presence.charAt(0).toUpperCase() + timesheet.presence.slice(1) : '-';
                activityCell = `<span class="text-sm text-gray-600">${escapeHtml(presenceLabel)}</span>`;
            }

            return `
                <tr class="hover:bg-gray-50 transition-colors ${canSelect ? 'cursor-pointer' : ''}" ${canSelect ? `onclick="toggleRowSelection(event, ${timesheet.id})"` : ''}>
                    <td class="px-3 py-2.5">
                        ${canSelect ? `<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="${timesheet.id}" data-status="${timesheet.status}" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">` : `<span class="text-gray-300"><i class="fas fa-lock text-xs" title="${timesheet.status}"></i></span>`}
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${escapeHtml(timesheet.employee_name || 'Unknown')}</div>
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${formatDisplayDate(timesheet.date)}</div>
                        <div class="text-xs mt-0.5">${typeInfo}</div>
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        <div class="text-sm text-gray-600">${tsTimeRange(timesheet)}</div>
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        <div class="text-sm font-semibold text-gray-900">${duration}h</div>
                    </td>
                    <td class="px-3 py-2.5">${projectTicketCell}</td>
                    <td class="px-3 py-2.5">${activityCell}</td>
                    <td class="px-3 py-2.5">
                        <div class="text-sm text-gray-900 truncate max-w-xs" title="${escapeHtml(timesheet.description || '')}">
                            ${escapeHtml(timesheet.description || '-')}
                        </div>
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full ${statusColor.bg} ${statusColor.text}">
                            ${timesheet.status.charAt(0).toUpperCase() + timesheet.status.slice(1)}
                        </span>
                        ${timesheet.status === 'approved' && timesheet.approver_name ? `<div class="text-xs text-gray-500 mt-0.5">by ${escapeHtml(timesheet.approver_name)}</div>` : ''}
                        ${timesheet.status === 'rejected' && timesheet.rejection_reason ? `<i class="fas fa-info-circle text-yellow-500 ml-1 cursor-pointer text-xs" title="${escapeHtml(timesheet.rejection_reason)}" onclick="event.stopPropagation(); showRejectionReason(${timesheet.id})"></i>` : ''}
                    </td>
                </tr>
            `;
        }).join('');
        updateBulkActionButtons();
        return;
    }

    // Employee mode (All / Project / Office)
    tbody.innerHTML = pageItems.map(timesheet => {
        const statusColor = statusColors[timesheet.status] || statusColors.draft;
        const duration = (timesheet.duration_minutes / 60).toFixed(2);
        const canEdit   = ['draft', 'rejected'].includes(timesheet.status);
        const canDelete = ['draft', 'rejected', 'submitted'].includes(timesheet.status);

        // ── Determine type ──────────────────────────────────────────
        const isProject = !!timesheet.delivery_projects_id;
        const isSupport = !isProject && !!timesheet.ticket_id;
        const isOffice  = !isProject && !isSupport;

        // ── Date cell type badge ─────────────────────────────────────
        let typeInfo;
        if (isProject)      typeInfo = '<span class="text-blue-600 text-xs font-medium">Project</span>';
        else if (isSupport) typeInfo = '<span class="text-purple-600 text-xs font-medium">Support</span>';
        else                typeInfo = '<span class="text-gray-500 text-xs font-medium">Office</span>';

        // ── Project/Ticket cell ──────────────────────────────────────
        const projCells = isProject ? _tsProjectCells(timesheet) : null;

        let projectTicketCell;
        if (isProject) {
            projectTicketCell = projCells.projectCell;
        } else if (isSupport) {
            const ticketLabel = timesheet.ticket_number ? `#${timesheet.ticket_number}` : `#${timesheet.ticket_id}`;
            const customerName = timesheet.customer_name || '';
            projectTicketCell = `
                <div class="text-sm font-medium text-gray-900">
                    <i class="fas fa-ticket-alt mr-1 text-purple-500"></i>${escapeHtml(ticketLabel)}
                </div>
                ${customerName ? `<div class="text-xs text-gray-500 mt-0.5">${escapeHtml(customerName)}</div>` : ''}`;
        } else {
            const presenceLabel = timesheet.presence ? timesheet.presence.charAt(0).toUpperCase() + timesheet.presence.slice(1) : '';
            const locationText  = timesheet.location  ? ` · ${timesheet.location}` : '';
            projectTicketCell = `
                <div class="text-sm text-gray-600">
                    <i class="fas fa-building mr-1 text-gray-400"></i>Office
                </div>
                ${presenceLabel ? `<div class="text-xs text-gray-400 mt-0.5">${escapeHtml(presenceLabel + locationText)}</div>` : ''}`;
        }

        // ── Activity cell ────────────────────────────────────────────
        let activityCell;
        if (isProject) {
            activityCell = projCells.activityCell;
        } else if (isSupport) {
            const mdVal      = timesheet.md_consumed != null ? formatMdTrim(timesheet.md_consumed) : '—';
            const onSite     = timesheet.presence === 'onsite';
            const presenceBadge = onSite
                ? '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-[10px] font-semibold"><i class="fas fa-map-marker-alt"></i>On Site</span>'
                : '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded text-[10px] font-semibold"><i class="fas fa-wifi"></i>Remote</span>';
            activityCell = `
                <div class="flex items-center gap-1.5 mb-0.5">${presenceBadge}</div>
                <div class="text-xs text-gray-600">MD: <span class="font-semibold">${mdVal}</span></div>`;
        } else {
            const presenceLabel = timesheet.presence ? timesheet.presence.charAt(0).toUpperCase() + timesheet.presence.slice(1) : '-';
            activityCell = `<span class="text-sm text-gray-600">${escapeHtml(presenceLabel)}</span>`;
        }

        return `
            <tr class="hover:bg-gray-50 transition-colors ${canEdit ? 'cursor-pointer' : ''}" ${canEdit ? `onclick="toggleRowSelection(event, ${timesheet.id})"` : ''}>
                <td class="px-3 py-2.5">
                    ${canDelete ? `<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="${timesheet.id}" data-status="${timesheet.status}" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">` : `<span class="text-gray-300"><i class="fas fa-lock text-xs" title="Cannot edit (${timesheet.status})"></i></span>`}
                </td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${formatDisplayDate(timesheet.date)}</div>
                    <div class="text-xs mt-0.5">${typeInfo}</div>
                </td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                    <div class="text-sm text-gray-600">${tsTimeRange(timesheet)}</div>
                </td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">${duration}h</div>
                </td>
                <td class="px-3 py-2.5">${projectTicketCell}</td>
                <td class="px-3 py-2.5">${activityCell}</td>
                <td class="px-3 py-2.5">
                    <div class="text-sm text-gray-900 truncate max-w-xs" title="${escapeHtml(timesheet.description || '')}">
                        ${escapeHtml(timesheet.description || '-')}
                    </div>
                </td>
                <td class="px-3 py-2.5 whitespace-nowrap">
                    <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full ${statusColor.bg} ${statusColor.text}">
                        ${timesheet.status.charAt(0).toUpperCase() + timesheet.status.slice(1)}
                    </span>
                    ${timesheet.status === 'rejected' && timesheet.rejection_reason ? `<i class="fas fa-info-circle text-yellow-500 ml-1 cursor-pointer text-xs" title="${escapeHtml(timesheet.rejection_reason)}" onclick="event.stopPropagation(); showRejectionReason(${timesheet.id})"></i>` : ''}
                </td>
            </tr>
        `;
    }).join('');
    updateBulkActionButtons();
}

// Project row cells (employee + approval tables):
//  - project cell: project name + activity, or a "No activity" badge for a
//    timesheet logged on a weekend / public holiday;
//  - activity cell: presence, free-text location and the device GPS location
//    (reverse-geocoded address, or coordinates) linking to Google Maps.
function _tsProjectCells(ts) {
    const projectName = ts.project_name || `Project #${ts.delivery_projects_id}`;
    const actName     = ts.activity?.name || ts.activity_name || '';
    const actLine = ts.is_without_activity
        ? '<div class="mt-0.5"><span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-amber-100 text-amber-800 rounded text-[10px] font-semibold" title="Logged without a project activity (weekend / public holiday, or not assigned to any activity of the project)"><i class="fas fa-calendar-times"></i>No activity</span></div>'
        : (actName ? `<div class="text-xs text-gray-500 mt-0.5"><i class="fas fa-tasks mr-1"></i>${escapeHtml(actName)}</div>` : '');

    const projectCell = `
        <div class="text-sm text-gray-900"><i class="fas fa-project-diagram mr-1 text-blue-500"></i>${escapeHtml(projectName)}</div>
        ${actLine}`;

    const presence = ts.presence ? ts.presence.charAt(0).toUpperCase() + ts.presence.slice(1) : '';
    const place    = [presence, ts.location].filter(Boolean).join(' · ');

    let gps = '';
    if (ts.gps_latitude != null && ts.gps_longitude != null) {
        const lat = Number(ts.gps_latitude), lng = Number(ts.gps_longitude);
        const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
        const text    = ts.gps_address || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        const acc     = ts.gps_accuracy != null ? ` (±${Math.round(ts.gps_accuracy)} m)` : '';
        gps = `<a href="${mapsUrl}" target="_blank" rel="noopener" onclick="event.stopPropagation()"
                  class="mt-0.5 flex items-start gap-1 text-xs text-blue-600 hover:underline max-w-xs" title="${escapeHtml(text + acc)}">
                  <i class="fas fa-map-marker-alt mt-0.5 flex-shrink-0"></i><span class="line-clamp-2">${escapeHtml(text)}</span></a>`;
    }

    const activityCell = `
        ${place ? `<div class="text-sm text-gray-700">${escapeHtml(place)}</div>` : ''}
        ${gps}
        ${!place && !gps ? '<span class="text-sm text-gray-400">-</span>' : ''}`;

    return { projectCell, activityCell };
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Trim a stored time value ("08:00:00" | "08:00") down to "HH:MM" for display.
function tsFmtTime(t) {
    return t ? String(t).slice(0, 5) : '';
}

// "HH:MM – HH:MM" for a timesheet's start/end, or "-" when either side is missing.
function tsTimeRange(ts) {
    const s = tsFmtTime(ts && ts.start_time);
    const e = tsFmtTime(ts && ts.end_time);
    return (s && e) ? `${s} – ${e}` : '-';
}

/**
 * Compute the reporting period month/year from a date object.
 * Rule: day 21 of month M → day 20 of month M+1 = Period M.
 * Dates 1–20 belong to the previous month's period.
 */
function getPeriodInfo(d) {
    if (d.getDate() >= 21) {
        return { month: d.getMonth() + 1, year: d.getFullYear() };
    }
    // Dates 1–20 → previous month's period
    if (d.getMonth() === 0) {
        return { month: 12, year: d.getFullYear() - 1 };
    }
    return { month: d.getMonth(), year: d.getFullYear() };
}

function updatePagination(total, start, end) {
    const elStart = document.getElementById('currentRangeStart');
    const elEnd = document.getElementById('currentRangeEnd');
    const elTotal = document.getElementById('totalItems');
    const btnPrev = document.getElementById('btnPrevPage');
    const btnNext = document.getElementById('btnNextPage');

    if (elStart) elStart.textContent = total > 0 ? start : 0;
    if (elEnd) elEnd.textContent = total > 0 ? end : 0;
    if (elTotal) elTotal.textContent = total;
    if (btnPrev) btnPrev.disabled = currentPage <= 1;
    if (btnNext) btnNext.disabled = end >= total;
}

function previousPage() {
    if (currentPage > 1) {
        currentPage--;
        renderTimesheetRows();
    }
}

function nextPage() {
    const maxPage = Math.ceil(filteredTimesheets.length / itemsPerPage);
    if (currentPage < maxPage) {
        currentPage++;
        renderTimesheetRows();
    }
}

// Toggle row selection when clicking on the row
function toggleRowSelection(event, id) {
    // Don't toggle if clicking on a link or button
    if (event.target.tagName === 'A' || event.target.tagName === 'BUTTON' || event.target.tagName === 'I') {
        return;
    }

    const checkbox = document.querySelector(`.timesheet-checkbox[data-id="${id}"]`);
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        updateBulkActionButtons();
    }
}

function formatDisplayDate(dateStr) {
    if (!dateStr) return '-';
    // Append T00:00:00 to force local-time parsing (avoids UTC midnight → previous day shift)
    const date = new Date(dateStr.length === 10 ? dateStr + 'T00:00:00' : dateStr);
    const options = { timeZone: 'Asia/Jakarta', weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-GB', options);
}

function showEmptyState() {
    const tbody = document.getElementById('timesheetsTableBody');
    const emptyState = document.getElementById('emptyState');
    if (tbody) tbody.innerHTML = '';
    if (emptyState) emptyState.classList.remove('hidden');
    filteredTimesheets = [];
    updatePagination(0, 0, 0);
}

function applyFilters() {
    const filterMonth        = document.getElementById('filterMonth');
    const filterYear         = document.getElementById('filterYear');
    const filterStatus       = document.getElementById('filterStatus');
    const filterActivityType = document.getElementById('filterActivityType');

    if (filterMonth && filterYear) {
        const { start, end } = tsPeriodToDateRange(parseInt(filterMonth.value), parseInt(filterYear.value));
        currentFilters.start_date = start;
        currentFilters.end_date   = end;
    }
    if (filterStatus) currentFilters.status = filterStatus.value;
    if (filterActivityType) currentFilters.activity_type = filterActivityType.value;

    currentPage = 1;

    // Sync active stat card with dropdown selection
    if (filterStatus) filterByStatus(filterStatus.value);

    // Date range change requires re-fetch
    if (window.isApprovalMode || window.isHoSMode) {
        loadSubmittedTimesheets();
    } else {
        loadTimesheets();
    }
}

function openTimesheetModal() {
    const modal = document.getElementById('timesheetModal');
    const form = document.getElementById('timesheetForm');
    const title = document.getElementById('timesheetModalTitle');
    const idField = document.getElementById('timesheetId');
    const dateField = document.getElementById('timesheetDate');

    if (!modal) {
        console.error('Timesheet modal not found');
        return;
    }

    if (title) title.textContent = 'Log Working Hours';
    if (form) form.reset();
    if (idField) idField.value = '';
    _tsEditing = null;
    _pendingActivityPreselect = null;

    const today = formatDate(new Date());
    if (dateField) dateField.value = today;

    // Set default time (08:00 - 17:00)
    setTimePicker('Start', '08:00');
    setTimePicker('End', '17:00');

    // Select the correct default type: locked type, first allowed type, or 'support'
    const allowed     = window.allowedTypes || ['project', 'support', 'office'];
    const defaultType = window.lockedType || allowed[0] || 'support';
    const defaultRadio = document.querySelector(`input[name="timesheetType"][value="${defaultType}"]`);
    if (defaultRadio) defaultRadio.checked = true;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        handleTimesheetTypeChange();
    }, 50);

    // Load period selector (async — shows after modal is visible)
    loadPeriodSelector();
}

function closeTimesheetModal() {
    const modal = document.getElementById('timesheetModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    const saveBtn = document.getElementById('btnSaveTimesheet');
    if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
}

function editTimesheet(id) {
    const timesheet = timesheets.find(t => t.id === id);
    if (!timesheet) {
        showNotification('Timesheet not found', 'error');
        return;
    }

    const modal = document.getElementById('timesheetModal');
    const title = document.getElementById('timesheetModalTitle');

    if (!modal) {
        console.error('Timesheet modal not found');
        return;
    }

    if (title) title.textContent = 'Edit Timesheet';

    const timesheetId = document.getElementById('timesheetId');
    const timesheetDate = document.getElementById('timesheetDate');
    const timesheetDescription = document.getElementById('timesheetDescription');

    if (timesheetId) timesheetId.value = timesheet.id;
    if (timesheetDate) timesheetDate.value = timesheet.date;
    if (timesheetDescription) timesheetDescription.value = timesheet.description || '';
    const timesheetNotes = document.getElementById('timesheetNotes');
    if (timesheetNotes) timesheetNotes.value = timesheet.notes || '';

    // Set time pickers using helper function
    setTimePicker('Start', timesheet.start_time);
    setTimePicker('End', timesheet.end_time);
    
    const timesheetType = timesheet.delivery_projects_id ? 'project' :
                         (timesheet.ticket_id ? 'support' : 'office');
    const typeRadio = document.querySelector(`input[name="timesheetType"][value="${timesheetType}"]`);
    if (typeRadio) {
        typeRadio.checked = true;
    }

    // Set preselect flags BEFORE handleTimesheetTypeChange so async loaders pick them up
    // (_tsEditing drives the project preselect, kept date/GPS and the time-slot exclusion)
    _tsEditing = timesheet;
    _pendingActivityPreselect = null;
    if (timesheetType === 'support' && timesheet.ticket_id) {
        _pendingTicketPreselect = timesheet.ticket_id;
    }
    if (timesheetType === 'project' && timesheet.activity_id) {
        _pendingActivityPreselect = timesheet.activity_id;
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    handleTimesheetTypeChange();
    loadPeriodSelector();

    setTimeout(() => {
        const location = document.getElementById('timesheetLocation');
        if (location) location.value  = timesheet.location || '';

        // Presence custom-dd (present in both project and office types)
        if (timesheet.presence) {
            setCustomDropdownValue('timesheetPresence', timesheet.presence);
        }

        // Project: project/activity preselect is handled by loadMyProjectsForTimesheet()
        // (via _tsEditing) and onProjectSelected() (via _pendingActivityPreselect).

        if (timesheetType === 'support') {
            // Ticket selection is handled by _pendingTicketPreselect in loadTicketsForDropdown.
            // Restore On Site and MD Consumed after onSupportTicketSelected completes.
            setTimeout(() => {
                const onSiteEl = document.getElementById('supportOnSite');
                if (onSiteEl) onSiteEl.checked = timesheet.presence === 'onsite';
                const mdEl = document.getElementById('supportMdConsumed');
                if (mdEl) mdEl.value = timesheet.md_consumed != null ? timesheet.md_consumed : '';
                const activityDateEl = document.getElementById('supportActivityDate');
                if (activityDateEl) activityDateEl.value = timesheet.activity_date || '';
            }, 400);
        }
    }, 150);
}

function openDeleteModal(id) {
    deleteTimesheetId = id;
    const modal = document.getElementById('confirmDeleteModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeConfirmDelete() {
    const modal = document.getElementById('confirmDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    deleteTimesheetId = null;
}

async function confirmDelete() {
    if (!deleteTimesheetId) return;
    
    try {
        const response = await fetch(`/api/timesheets/${deleteTimesheetId}/delete`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Timesheet deleted successfully!', 'success');
            closeConfirmDelete();
            await loadTimesheets();
            await loadStatistics();
        } else {
            showNotification('Failed to delete timesheet: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('An error occurred while deleting timesheet', 'error');
    }
}

// Open single submit confirmation modal
async function openSubmitModal(id) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const timesheet = timesheets.find(t => String(t.id) === String(id));

    // For support timesheets, enforce mandays quota before allowing submit
    if (timesheet?.ticket_id) {
        try {
            const res  = await fetch(`/api/timesheets/remaining-md?ticket_id=${timesheet.ticket_id}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data?.success) {
                const remaining = data.data?.remaining;
                if (remaining === null || remaining === undefined) {
                    showNotification(
                        'Cannot submit: no approved mandays proposal found for this ticket. Contact your Head.',
                        'error'
                    );
                    return;
                }
                const rem = Number(remaining);
                if (rem < 0) {
                    showNotification(
                        `Cannot submit: quota exceeded (remaining MD: ${formatMdTrim(rem)}). Save as draft only until quota is increased.`,
                        'error'
                    );
                    return;
                }
            }
        } catch (e) {
            // Network error — backend will validate
        }
    }

    const modal = document.getElementById('confirmSubmitModal');
    const submitTimesheetId = document.getElementById('submitTimesheetId');
    if (submitTimesheetId) submitTimesheetId.value = id;
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

// Close single submit modal
function closeSubmitModal() {
    const modal = document.getElementById('confirmSubmitModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Confirm single submit
async function confirmSubmit() {
    const submitTimesheetId = document.getElementById('submitTimesheetId');
    const id = submitTimesheetId?.value;

    if (!id) return;

    try {
        const response = await fetch(`/api/timesheets/${id}/submit`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (data.success) {
            showNotification('Timesheet submitted for approval!', 'success');
            closeSubmitModal();
            await loadTimesheets();
            await loadStatistics();
        } else {
            showNotification('Failed to submit timesheet: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('An error occurred while submitting timesheet', 'error');
    }
}

// Legacy function - now opens modal
function submitTimesheet(id) {
    openSubmitModal(id);
}


function updateBulkActionButtons() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');

    if (!bulkActions) return;

    if (checkboxes.length > 0) {
        bulkActions.classList.remove('hidden');
        bulkActions.classList.add('flex');
        if (selectedCount) selectedCount.textContent = checkboxes.length;

        if (window.isApprovalMode || window.isHoSMode) {
            // Approval mode (or HoS): Approve / Reject buttons
            const btnApprove = document.getElementById('btnBulkApprove');
            const btnReject  = document.getElementById('btnBulkReject');
            if (btnApprove) btnApprove.classList.remove('hidden');
            if (btnReject)  btnReject.classList.remove('hidden');
        } else {
            // Employee mode: Edit / Submit / Delete buttons
            const btnEdit   = document.getElementById('btnBulkEdit');
            const btnSubmit = document.getElementById('btnBulkSubmit');
            const btnDelete = document.getElementById('btnBulkDelete');

            let hasDraft = false;
            let hasEditable = false;
            checkboxes.forEach(cb => {
                const st = cb.getAttribute('data-status');
                if (st === 'draft') hasDraft = true;
                if (st === 'draft' || st === 'rejected') hasEditable = true;
            });

            if (btnEdit)   btnEdit.classList.toggle('hidden', checkboxes.length !== 1 || !hasEditable);
            if (btnSubmit) btnSubmit.classList.toggle('hidden', !hasDraft);
            if (btnDelete) btnDelete.classList.remove('hidden');
        }
    } else {
        bulkActions.classList.add('hidden');
        bulkActions.classList.remove('flex');
        // Table body is rebuilt fresh on every reload — its row checkboxes are already
        // unchecked, but the header "select all" checkbox lives outside tbody and keeps
        // its own state, so it can be left showing checked with nothing actually selected.
        const selectAllCb = document.getElementById('selectAll');
        if (selectAllCb) selectAllCb.checked = false;
    }

    const noBulkActions = document.getElementById('noBulkActions');
    if (noBulkActions) {
        noBulkActions.classList.toggle('hidden', checkboxes.length > 0);
    }
}

function openBulkDeleteModal() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    
    if (checkboxes.length === 0) {
        showNotification('Please select timesheets to delete', 'error');
        return;
    }
    
    const bulkActionCount = document.getElementById('bulkActionCount');
    if (bulkActionCount) bulkActionCount.textContent = checkboxes.length;
    
    const modal = document.getElementById('confirmBulkDeleteModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeBulkDeleteModal() {
    const modal = document.getElementById('confirmBulkDeleteModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

async function confirmBulkDelete() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    
    let successCount = 0;
    let failCount = 0;
    
    for (const checkbox of checkboxes) {
        const id = checkbox.getAttribute('data-id');
        
        try {
            const response = await fetch(`/api/timesheets/${id}/delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                successCount++;
            } else {
                failCount++;
            }
        } catch (error) {
            failCount++;
        }
    }
    
    closeBulkDeleteModal();
    await loadTimesheets();
    await loadStatistics();
    
    if (successCount > 0) {
        showNotification(`Deleted ${successCount} timesheet(s) successfully${failCount > 0 ? `, ${failCount} failed` : ''}!`, 'success');
    } else {
        showNotification('Failed to delete timesheets', 'error');
    }
}

async function openBulkSubmitModal() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');

    if (checkboxes.length === 0) {
        showNotification('Please select timesheets to submit', 'error');
        return;
    }

    const csrf        = document.querySelector('meta[name="csrf-token"]')?.content;
    const selectedIds = [...checkboxes].map(cb => cb.getAttribute('data-id'));

    // Collect unique ticket IDs from selected support timesheets
    const supportTs       = selectedIds.map(id => timesheets.find(t => String(t.id) === String(id))).filter(t => t?.ticket_id);
    const uniqueTicketIds = [...new Set(supportTs.map(t => String(t.ticket_id)))];

    const overQuotaTickets = new Set();
    if (uniqueTicketIds.length > 0) {
        // Fetch remaining per ticket and store for per-timesheet comparison below
        const remainingByTicket = {};
        await Promise.all(uniqueTicketIds.map(async ticketId => {
            try {
                const res  = await fetch(`/api/timesheets/remaining-md?ticket_id=${ticketId}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data?.success) {
                    const rem = data.data?.remaining;
                    // null means no approved proposal — store -Infinity so it is treated as over-quota
                    remainingByTicket[ticketId] = (rem !== null && rem !== undefined) ? Number(rem) : -Infinity;
                }
            } catch (e) {}
        }));

        // A ticket is over-quota when remaining is strictly negative
        supportTs.forEach(t => {
            const rem = remainingByTicket[String(t.ticket_id)];
            if (rem !== undefined && rem < 0) {
                overQuotaTickets.add(String(t.ticket_id));
            }
        });
    }

    // Uncheck over-quota support timesheets so they are excluded from bulk submit
    if (overQuotaTickets.size > 0) {
        const blockedIds = new Set(
            supportTs.filter(t => overQuotaTickets.has(String(t.ticket_id))).map(t => String(t.id))
        );
        document.querySelectorAll('.timesheet-checkbox:checked').forEach(cb => {
            if (blockedIds.has(cb.getAttribute('data-id'))) cb.checked = false;
        });
        updateBulkActionButtons();

        const remaining = document.querySelectorAll('.timesheet-checkbox:checked').length;
        if (remaining === 0) {
            showNotification('Cannot submit: all selected support timesheets have exceeded their MD quota.', 'error');
            return;
        }
        showNotification(
            `${blockedIds.size} support timesheet(s) excluded (MD quota exceeded). Submitting ${remaining} remaining.`,
            'warning'
        );
    }

    const finalCount = document.querySelectorAll('.timesheet-checkbox:checked').length;
    if (finalCount === 0) return;

    const bulkSubmitCount = document.getElementById('bulkSubmitCount');
    if (bulkSubmitCount) bulkSubmitCount.textContent = finalCount;

    const modal = document.getElementById('confirmBulkSubmitModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeBulkSubmitModal() {
    const modal = document.getElementById('confirmBulkSubmitModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

async function confirmBulkSubmit() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');

    let successCount = 0;
    let failCount = 0;
    const failReasons = new Set();

    for (const checkbox of checkboxes) {
        const id = checkbox.getAttribute('data-id');

        try {
            const response = await fetch(`/api/timesheets/${id}/submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            const data = await response.json();

            if (data.success) {
                successCount++;
            } else {
                failCount++;
                failReasons.add(data.message || 'Unknown reason');
            }
        } catch (error) {
            failCount++;
            failReasons.add('A network error occurred');
        }
    }

    closeBulkSubmitModal();
    await loadTimesheets();
    await loadStatistics();

    if (successCount > 0) {
        showNotification(`Submitted ${successCount} timesheet(s) successfully${failCount > 0 ? `, ${failCount} failed` : ''}!`, 'success');
    }
    if (failCount > 0) {
        // Surface the actual backend reason(s) instead of a blank generic message —
        // e.g. "Customer Mandays status is not approved yet" — so users know what to
        // fix instead of just seeing a dead-end failure.
        const reasonText = Array.from(failReasons).join(' — ');
        showNotification(`Failed to submit ${failCount} timesheet(s): ${reasonText}`, 'error');
    }
}

function editSelectedTimesheet() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    
    if (checkboxes.length === 0) {
        showNotification('Please select a timesheet to edit', 'error');
        return;
    }
    
    if (checkboxes.length > 1) {
        showNotification('Please select only one timesheet to edit', 'error');
        return;
    }
    
    const id = checkboxes[0].getAttribute('data-id');
    editTimesheet(parseInt(id));
}

function showRejectionReason(id) {
    const timesheet = timesheets.find(t => t.id === id);
    if (!timesheet || !timesheet.rejection_reason) {
        showNotification('No rejection reason found', 'error');
        return;
    }
    
    showNotification(`Rejected: ${timesheet.rejection_reason}`, 'error');
}

async function handleFormSubmit(e) {
    e.preventDefault();

    const saveBtn = document.getElementById('btnSaveTimesheet');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

    const timesheetId = document.getElementById('timesheetId');
    
    const userMeta = document.querySelector('meta[name="user-data"]');
    let employeeId = null;
    
    if (userMeta) {
        try {
            const userData = JSON.parse(userMeta.content);
            employeeId = userData.employee_id || userData.id || null;
        } catch (e) {
            // Silent fail
        }
    }
    
    if (!employeeId) {
        showNotification('Session error: gagal mendapatkan data user. Silakan refresh halaman.', 'error');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
        return;
    }
    
    const selectedRadio = document.querySelector('input[name="timesheetType"]:checked');
    const selectedType = selectedRadio ? selectedRadio.value : 'support';
    
    // Start/end time is mandatory for every timesheet type.
    const startTimeEl = document.getElementById('timesheetStartTime');
    const endTimeEl   = document.getElementById('timesheetEndTime');
    tsNormalizeTimeInput(startTimeEl);
    tsNormalizeTimeInput(endTimeEl);
    const startParsed = _tsParseTime(startTimeEl?.value);
    const endParsed   = _tsParseTime(endTimeEl?.value);

    if (!startParsed || !endParsed) {
        showNotification('Please enter a valid start and end time (HH:MM).', 'error');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
        return;
    }

    const startTime = `${String(startParsed.h).padStart(2, '0')}:${String(startParsed.m).padStart(2, '0')}`;
    const endTime   = `${String(endParsed.h).padStart(2, '0')}:${String(endParsed.m).padStart(2, '0')}`;

    if ((endParsed.h * 60 + endParsed.m) <= (startParsed.h * 60 + startParsed.m)) {
        showNotification('End time must be later than start time.', 'error');
        _tsValidateTimeOrder();
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
        return;
    }

    const timesheetData = {
        employee_id: employeeId,
        date: document.getElementById('timesheetDate')?.value,
        start_time: startTime,
        end_time: endTime,
        description: document.getElementById('timesheetDescription')?.value,
        notes: document.getElementById('timesheetNotes')?.value || null,
    };
    
    // Type-specific data
    if (selectedType === 'project') {
        const fail = msg => {
            showNotification(msg, 'error');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
        };

        const projectId       = document.getElementById('timesheetProjectId')?.value || null;
        const activityId      = document.getElementById('timesheetActivity')?.value || null;
        const nonWorkingDay   = !!_tsProjectCtx?.is_non_working_day;
        const noActivityMode  = nonWorkingDay || _tsNotAssigned;
        const withoutActivity = noActivityMode && !!document.getElementById('timesheetWithoutActivity')?.checked;

        if (!projectId) return fail('Please select a project.');
        if (nonWorkingDay && !withoutActivity) return fail('Today is a non-working day — tick "Log without activity" to continue.');
        if (_tsNotAssigned && !withoutActivity) return fail('You are not assigned to any activity in this project — tick "Log without activity" to continue.');
        if (!noActivityMode && !activityId) return fail('Please select an activity.');
        if (!_tsValidateTimeOrder()) return fail(document.getElementById('timesheetTimeError')?.textContent || 'Invalid time range.');

        if (_tsNeedsGps() && !_tsGps) {
            if (_tsGpsState !== 'pending') requestTimesheetGps();
            return fail(_tsGpsState === 'pending'
                ? 'Still getting your device location — please wait a moment and save again.'
                : 'Your device location is required. Please enable location access for this site.');
        }

        timesheetData.date = _tsProjectCtx?.date || timesheetData.date;
        timesheetData.delivery_projects_id = projectId;
        timesheetData.activity_id = withoutActivity ? null : activityId;
        timesheetData.is_without_activity = withoutActivity;
        timesheetData.ticket_id = null;
        timesheetData.presence = document.getElementById('timesheetPresence')?.value || null;
        timesheetData.location = document.getElementById('timesheetLocation')?.value || null;
        if (_tsNeedsGps() && _tsGps) {
            timesheetData.gps_latitude  = _tsGps.lat;
            timesheetData.gps_longitude = _tsGps.lng;
            timesheetData.gps_accuracy  = _tsGps.accuracy;
        }

        if (!timesheetData.presence) return fail('Please select a presence.');

    } else if (selectedType === 'support') {
        const onSite = document.getElementById('supportOnSite')?.checked;
        const mdConsumedVal = document.getElementById('supportMdConsumed')?.value;

        // Client-side fast-fail for NEW timesheets only — edit mode leaves this to the
        // backend, since remaining shown there already includes this draft's own MD
        // consumption and a correct client-side re-check would need to add it back.
        if (!timesheetId?.value && _currentTicketRemainingMd !== null) {
            const mdVal = parseFloat(mdConsumedVal || 0);
            if (mdVal > _currentTicketRemainingMd) {
                showNotification(`MD Consumed (${mdVal}) exceeds the remaining quota (${formatMdTrim(_currentTicketRemainingMd)}) for this ticket.`, 'error');
                if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
                return;
            }
        }

        timesheetData.delivery_projects_id = null;
        timesheetData.ticket_id = document.getElementById('timesheetTicket')?.value || null;
        timesheetData.activity_date = document.getElementById('supportActivityDate')?.value || null;
        timesheetData.activity_type = 'support';
        timesheetData.presence = onSite ? 'onsite' : 'remote';
        timesheetData.location = null;
        timesheetData.md_consumed = mdConsumedVal ? parseFloat(mdConsumedVal) : null;
        timesheetData.is_billable = false;

    } else if (selectedType === 'office') {
        timesheetData.delivery_projects_id = null;
        timesheetData.ticket_id = null;
        timesheetData.activity_type = 'other'; // Default for office
        timesheetData.presence = document.getElementById('timesheetPresence')?.value || null;
        timesheetData.location = document.getElementById('timesheetLocation')?.value || null;
        timesheetData.is_billable = false;
    }
    
    try {
        const url = timesheetId?.value ? `/api/timesheets/${timesheetId.value}/update` : '/api/timesheets';
        const method = 'POST';
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(timesheetData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(timesheetId?.value ? 'Timesheet updated successfully!' : 'Timesheet created successfully!', 'success');
            closeTimesheetModal();
            await loadTimesheets();
            await loadStatistics();
        } else {
            showNotification('Failed to save timesheet: ' + (data.message || 'Unknown error'), 'error');
            if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
            // Another save may have taken the slot meanwhile — refresh the used times.
            if (selectedType === 'project' && timesheetData.date) loadProjectFormContext(timesheetData.date);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred while saving timesheet', 'error');
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Timesheet'; }
    }
}

// ==================== BULK APPROVE / REJECT ====================

function openBulkApproveModal() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    if (checkboxes.length === 0) {
        showNotification('Please select timesheets to approve', 'error');
        return;
    }
    const countEl = document.getElementById('bulkApproveCount');
    if (countEl) countEl.textContent = checkboxes.length;
    const modal = document.getElementById('bulkApproveModal');
    if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
}

function closeBulkApproveModal() {
    const modal = document.getElementById('bulkApproveModal');
    if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
}

async function confirmBulkApprove() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    let successCount = 0, failCount = 0;

    for (const cb of checkboxes) {
        const id = cb.getAttribute('data-id');
        try {
            const res = await fetch(`/api/timesheets/${id}/approve`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
            });
            const data = await res.json();
            if (data.success) successCount++;
            else failCount++;
        } catch (e) { failCount++; }
    }

    closeBulkApproveModal();
    await loadSubmittedTimesheets();
    await loadApprovalStatistics();

    if (successCount > 0) {
        showNotification(`Approved ${successCount} timesheet(s) successfully${failCount > 0 ? `, ${failCount} failed` : ''}!`, 'success');
    } else {
        showNotification('Failed to approve timesheets', 'error');
    }
}

function openBulkRejectModal() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    if (checkboxes.length === 0) {
        showNotification('Please select timesheets to reject', 'error');
        return;
    }
    const countEl = document.getElementById('bulkRejectCount');
    if (countEl) countEl.textContent = checkboxes.length;
    const reasonEl = document.getElementById('bulkRejectionReason');
    if (reasonEl) reasonEl.value = '';
    const modal = document.getElementById('bulkRejectModal');
    if (modal) { modal.classList.remove('hidden'); modal.classList.add('flex'); }
}

function closeBulkRejectModal() {
    const modal = document.getElementById('bulkRejectModal');
    if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
}

async function confirmBulkReject() {
    const reason = document.getElementById('bulkRejectionReason')?.value?.trim();
    if (!reason) {
        showNotification('Please provide a rejection reason', 'error');
        return;
    }

    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    let successCount = 0, failCount = 0;

    for (const cb of checkboxes) {
        const id = cb.getAttribute('data-id');
        try {
            const res = await fetch(`/api/timesheets/${id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ rejection_reason: reason })
            });
            const data = await res.json();
            if (data.success) successCount++;
            else failCount++;
        } catch (e) { failCount++; }
    }

    closeBulkRejectModal();
    await loadSubmittedTimesheets();
    await loadApprovalStatistics();

    if (successCount > 0) {
        showNotification(`Rejected ${successCount} timesheet(s) successfully${failCount > 0 ? `, ${failCount} failed` : ''}!`, 'success');
    } else {
        showNotification('Failed to reject timesheets', 'error');
    }
}

// showNotification is provided globally by dashboard.blade.php → showToast()

const timesheetModal = document.getElementById('timesheetModal');
if (timesheetModal) {
    timesheetModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeTimesheetModal();
        }
    });
}

const confirmDeleteModal = document.getElementById('confirmDeleteModal');
if (confirmDeleteModal) {
    confirmDeleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeConfirmDelete();
        }
    });
}

// Submit modal click outside to close
const confirmSubmitModal = document.getElementById('confirmSubmitModal');
if (confirmSubmitModal) {
    confirmSubmitModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeSubmitModal();
        }
    });
}

// Bulk submit modal click outside to close
const confirmBulkSubmitModal = document.getElementById('confirmBulkSubmitModal');
if (confirmBulkSubmitModal) {
    confirmBulkSubmitModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeBulkSubmitModal();
        }
    });
}

// Bulk delete modal click outside to close
const confirmBulkDeleteModal = document.getElementById('confirmBulkDeleteModal');
if (confirmBulkDeleteModal) {
    confirmBulkDeleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeBulkDeleteModal();
        }
    });
}


document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = [
            { id: 'timesheetModal',          close: closeTimesheetModal },
            { id: 'confirmDeleteModal',      close: closeConfirmDelete },
            { id: 'confirmSubmitModal',      close: closeSubmitModal },
            { id: 'confirmBulkSubmitModal',  close: closeBulkSubmitModal },
            { id: 'confirmBulkDeleteModal',  close: closeBulkDeleteModal },
            { id: 'approveModal',            close: closeApproveModal },
            { id: 'rejectModal',             close: closeRejectModal },
            { id: 'bulkApproveModal',        close: closeBulkApproveModal },
            { id: 'bulkRejectModal',         close: closeBulkRejectModal },
        ];
        modals.forEach(({ id, close }) => {
            const el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) close();
        });
    }
});