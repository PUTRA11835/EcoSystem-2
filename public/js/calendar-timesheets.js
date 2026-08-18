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
    <th class="${TH_FILT}" style="min-width:85px;">${_mkMonthDd()}</th>
    <th class="${TH_FILT}" style="min-width:70px;">${_mkYearDd()}</th>
    <th class="${TH_FILT}" style="min-width:150px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'Employee')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Name</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_Employee')}<span id="tsSortEmpIcon" onclick="event.stopPropagation(); toggleTsEmpSort()" title="Click to toggle sort (A–Z ↔ Z–A)" class="cursor-pointer text-[10px] text-gray-300 font-bold shrink-0 ml-auto hover:text-red-500 transition-colors">⇅</span></button>${_EMP_TEXT_PANEL}</th>
    <th class="${TH_FILT}" style="min-width:120px;">${_mkStatusDd()}</th>
    <th class="${TH_FILT}" style="min-width:150px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'Ticket')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Ticket</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_Ticket')}</button>${_TKT_TEXT_PANEL}</th>
    <th class="${TH_PLAIN}" style="min-width:180px;">Description</th>
    <th class="${TH_FILT}" style="min-width:130px; position:relative;"><button type="button" onclick="toggleTsTextPanel(event,'Customer')" class="w-full flex items-center gap-1.5 px-3 py-2.5 cursor-pointer hover:bg-gray-100 transition-colors"><span class="text-[11px] font-semibold text-gray-500 uppercase tracking-widest whitespace-nowrap">Customer</span>${CHEVRON_SVG}${FUNNEL_SVG('tsTextIcon_Customer')}</button>${_CUST_TEXT_PANEL}</th>
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

// Global callbacks for the time-picker custom dropdowns (called via data-onchange)
function tsUpdateDuration() {
    const startH = parseInt(document.getElementById('timesheetStartHour')?.value || '0');
    const startM = parseInt(document.getElementById('timesheetStartMinute')?.value || '0');
    const endH   = parseInt(document.getElementById('timesheetEndHour')?.value || '0');
    const endM   = parseInt(document.getElementById('timesheetEndMinute')?.value || '0');

    let startMins = startH * 60 + startM;
    let endMins   = endH   * 60 + endM;
    if (endMins < startMins) endMins += 24 * 60;

    const dur   = endMins - startMins;
    const hours = Math.floor(dur / 60);
    const mins  = dur % 60;

    const durationField = document.getElementById('timesheetDuration');
    if (durationField) {
        if (durationField.tagName === 'INPUT') {
            durationField.value = `${hours}h ${mins}m`;
        } else {
            durationField.textContent = `${hours}h ${mins}m`;
        }
    }
}

function tsUpdateStartTime() {
    const h = document.getElementById('timesheetStartHour')?.value || '08';
    const m = document.getElementById('timesheetStartMinute')?.value || '00';
    const hiddenInput = document.getElementById('timesheetStartTime');
    if (hiddenInput) hiddenInput.value = `${h}:${m}`;
    tsUpdateDuration();
}

function tsUpdateEndTime() {
    const h = document.getElementById('timesheetEndHour')?.value || '17';
    const m = document.getElementById('timesheetEndMinute')?.value || '00';
    const hiddenInput = document.getElementById('timesheetEndTime');
    if (hiddenInput) hiddenInput.value = `${h}:${m}`;
    tsUpdateDuration();
}

// Initialize time picker dropdowns
function initializeTimePickers() {
    // Set default values (08:00 - 17:00) via the custom-dd setter
    setCustomDropdownValue('timesheetStartHour',   '08');
    setCustomDropdownValue('timesheetStartMinute', '00');
    setCustomDropdownValue('timesheetEndHour',     '17');
    setCustomDropdownValue('timesheetEndMinute',   '00');
    tsUpdateStartTime();
    tsUpdateEndTime();
}

// Helper to set time picker from HH:mm:ss or HH:mm string
function setTimePicker(type, timeString) {
    if (!timeString) return;

    const parts  = timeString.split(':');
    const hour   = (parts[0] || '00').padStart(2, '0');
    const minute = parts[1] || '00';

    // Snap minute to nearest 5 for the display picker
    const roundedMins = Math.round(parseInt(minute) / 5) * 5;
    const minuteVal   = String(roundedMins % 60).padStart(2, '0');

    setCustomDropdownValue(`timesheet${type}Hour`,   hour);
    setCustomDropdownValue(`timesheet${type}Minute`, minuteVal);

    // Set the combined hidden time input directly (exact minute, not rounded)
    const hiddenInput = document.getElementById(`timesheet${type}Time`);
    if (hiddenInput) hiddenInput.value = `${hour}:${minute}`;

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
    const billableSection = document.getElementById('billableSection');

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
                    Activity <span class="text-red-500">*</span>
                </label>
                <div class="custom-dd w-full" data-fixed="true" data-onchange="onActivitySelected">
                    <button type="button" class="custom-dd-btn w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm bg-gray-50 hover:bg-white transition-colors flex items-center justify-between gap-2">
                        <span class="custom-dd-label text-gray-500 truncate flex-1 text-left">Select an Activity</span>
                        ${CHEVRON}
                    </button>
                    <div class="custom-dd-panel hidden bg-white border border-gray-200 rounded-md shadow-lg overflow-y-auto max-h-56">
                        <button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select an Activity</button>
                    </div>
                    <input type="hidden" id="timesheetActivity">
                </div>
                <p class="mt-1 text-xs text-gray-400">Only activities assigned to you</p>
                <input type="hidden" id="timesheetProjectId" value="">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                    Activity Type <span class="text-red-500">*</span>
                </label>
                <input type="text" id="timesheetActivityType" required
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-md text-sm focus:ring-2 focus:ring-red-700 focus:border-transparent bg-gray-50"
                       placeholder="e.g. Development, Meeting, Training…">
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
        `;

        if (billableSection) billableSection.classList.remove('hidden');

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

        if (billableSection) billableSection.classList.add('hidden');

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

        if (billableSection) billableSection.classList.add('hidden');
    }

    // Show/hide the entire time block (support type doesn't use start/end time)
    const timeBlock = document.getElementById('timesheetTimeBlock');
    const isSupport = selectedType === 'support';
    if (timeBlock) timeBlock.style.display = isSupport ? 'none' : '';

    // Inject HTML and init custom dropdowns
    dynamicFieldsContainer.innerHTML = fieldsHTML;
    if (typeof initCustomDropdowns === 'function') {
        initCustomDropdowns(dynamicFieldsContainer);
    }

    // Update description label and placeholder based on type
    const descLabel = document.querySelector('label[for="timesheetDescription"]');
    if (descLabel) {
        descLabel.innerHTML = selectedType === 'support'
            ? 'Activity <span class="text-red-500">*</span>'
            : 'Description <span class="text-red-500">*</span>';
    }
    const timesheetDescEl = document.getElementById('timesheetDescription');
    if (timesheetDescEl) {
        timesheetDescEl.placeholder = selectedType === 'support'
            ? 'Describe what you did in this session'
            : 'What did you work on?';
    }

    // Now load data based on type (after DOM is updated)
    if (selectedType === 'project') {
        loadAllMyActivities();
    } else if (selectedType === 'support') {
        loadTicketsForDropdown();
    }
}

// Store activities data for lookup
let allActivitiesData = [];

// Load ALL activities assigned to the logged-in employee (across all projects)
async function loadAllMyActivities() {
    const hidden = document.getElementById('timesheetActivity');
    const dd     = hidden?.closest('.custom-dd');
    const panel  = dd?.querySelector('.custom-dd-panel') || dd?._ddPanel;

    if (!hidden || !dd || !panel) {
        console.error('Activity custom-dd not found');
        return;
    }

    panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-400 cursor-default" data-value="">Loading activities…</button>';

    try {
        const response = await fetch('/api/timesheets/my-activities/all', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const data = await response.json();

        if (!response.ok) {
            console.error('Failed to load activities:', data.message);
            panel.innerHTML = `<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-red-500 cursor-default" data-value="">Error: ${data.message || 'Failed to load'}</button>`;
            return;
        }

        if (data.success && data.data && data.data.length > 0) {
            allActivitiesData = data.data;

            // Group activities by project
            const groupedByProject = {};
            data.data.forEach(activity => {
                const projectName = activity.project_name || 'Unknown Project';
                if (!groupedByProject[projectName]) groupedByProject[projectName] = [];
                groupedByProject[projectName].push(activity);
            });

            let html = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" data-value="">Select an Activity</button>';

            Object.keys(groupedByProject).forEach(projectName => {
                html += `<div class="px-3 py-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide bg-gray-50 border-t border-gray-100 pointer-events-none select-none">${projectName}</div>`;
                groupedByProject[projectName].forEach(activity => {
                    const phaseName = activity.phase_name || '';
                    const stageName = activity.stage_name || '';
                    const status    = activity.status ? ` [${activity.status}]` : '';
                    let label       = activity.name;
                    if (phaseName) label += ` - ${phaseName}`;
                    if (stageName) label += ` > ${stageName}`;
                    label += status;
                    html += `<button type="button" class="custom-dd-item w-full pl-5 pr-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                        data-value="${activity.id}"
                        data-project-id="${activity.delivery_projects_id}">${label}</button>`;
                });
            });

            panel.innerHTML = html;

            // H-4 fix: pre-select activity if coming from editTimesheet()
            if (_pendingActivityPreselect) {
                const preselectId = String(_pendingActivityPreselect);
                _pendingActivityPreselect = null;
                setCustomDropdownValue('timesheetActivity', preselectId);
                if (hidden.value === preselectId) {
                    onActivitySelected();
                }
            }
        } else {
            panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-gray-500 cursor-default" data-value="">No activities assigned to you</button>';
        }
    } catch (error) {
        console.error('Error loading all assigned activities:', error);
        panel.innerHTML = '<button type="button" class="custom-dd-item w-full px-3 py-2 text-left text-sm text-red-500 cursor-default" data-value="">Failed to load activities</button>';
    }
}

// Handle activity selection - set the project ID automatically
function onActivitySelected() {
    const hidden         = document.getElementById('timesheetActivity');
    const projectIdInput = document.getElementById('timesheetProjectId');
    if (!hidden || !projectIdInput) return;

    const dd   = hidden.closest('.custom-dd');
    const val  = hidden.value;
    const panel = dd?.querySelector('.custom-dd-panel') || dd?._ddPanel;
    const item  = panel?.querySelector(`.custom-dd-item[data-value="${CSS.escape(val)}"]`);
    projectIdInput.value = item?.dataset.projectId || '';
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

    if (colEmp)      result = result.filter(t => (t.employee_name || '').toLowerCase().includes(colEmp));
    if (colStatus)   result = result.filter(t => t.status === colStatus);
    if (colActType)  result = result.filter(t => (t.activity_type || '').toLowerCase().includes(colActType));
    if (colTicket)   result = result.filter(t => (t.ticket_number || '').toLowerCase().includes(colTicket));
    if (colCustomer) result = result.filter(t => (t.customer_name || '').toLowerCase().includes(colCustomer));
    if (colMonth)    result = result.filter(t => String(t.period_month) === colMonth);
    if (colYear)     result = result.filter(t => String(t.period_year) === colYear);

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
            if (table) table.style.minWidth = '1200px';
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
                 'colFilterTsStatus', 'colFilterTsMonth', 'colFilterTsYear', 'tsDateFrom', 'tsDateTo'];
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
            var nam   = escapeHtml(ts.employee_name || '-');
            var tkt   = ts.ticket_number ? ('#' + escapeHtml(ts.ticket_number)) : (ts.ticket_id ? ('#' + ts.ticket_id) : '-');
            var tdesc = escapeHtml(ts.ticket_description || '-');
            var cust  = escapeHtml(ts.customer_name || '-');
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
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">' + bln + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">' + thn + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-800 font-medium">' + nam + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap">' + statusCell + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs font-semibold text-purple-700"><i class="fas fa-ticket-alt mr-1 opacity-60"></i>' + tkt + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-600 max-w-[180px]" title="' + escapeHtml(ts.ticket_description || '') + '">' + tdesc + '</td>'
                + '<td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700">' + cust + '</td>'
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

            let projectTicketCell;
            if (isProject) {
                const actName = timesheet.activity?.name || '';
                projectTicketCell = `
                    <div class="text-sm text-gray-900"><i class="fas fa-project-diagram mr-1 text-blue-500"></i>Project #${timesheet.delivery_projects_id}</div>
                    ${actName ? `<div class="text-xs text-gray-500 mt-0.5"><i class="fas fa-tasks mr-1"></i>${escapeHtml(actName)}</div>` : ''}`;
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
                const actType = timesheet.activity_type || '';
                activityCell = `
                    <div class="flex items-center gap-1.5">
                        <i class="fas ${activityTypeIcons[actType] || 'fa-circle'} text-blue-400 text-xs"></i>
                        <span class="text-sm text-gray-700">${actType ? actType.charAt(0).toUpperCase() + actType.slice(1) : '-'}</span>
                    </div>`;
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
                        <div class="text-sm text-gray-600">${timesheet.start_time} – ${timesheet.end_time}</div>
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
        let projectTicketCell;
        if (isProject) {
            const actName = timesheet.activity?.name || '';
            projectTicketCell = `
                <div class="text-sm text-gray-900">
                    <i class="fas fa-project-diagram mr-1 text-blue-500"></i>Project #${timesheet.delivery_projects_id}
                </div>
                ${actName ? `<div class="text-xs text-gray-500 mt-0.5"><i class="fas fa-tasks mr-1"></i>${escapeHtml(actName)}</div>` : ''}`;
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
            const actType = timesheet.activity_type || '';
            activityCell = `
                <div class="flex items-center gap-1.5">
                    <i class="fas ${activityTypeIcons[actType] || 'fa-circle'} text-blue-400 text-xs"></i>
                    <span class="text-sm text-gray-700">${actType ? actType.charAt(0).toUpperCase() + actType.slice(1) : '-'}</span>
                </div>
                ${timesheet.is_billable ? '<div class="text-xs text-green-600 font-semibold mt-0.5"><i class="fas fa-tag mr-1"></i>Billable</div>' : ''}`;
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
                    <div class="text-sm text-gray-600">${timesheet.start_time} – ${timesheet.end_time}</div>
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

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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
        const billable  = document.getElementById('timesheetBillable');

        if (location) location.value  = timesheet.location || '';
        if (billable) billable.checked = timesheet.is_billable || false;

        // Presence custom-dd (present in both project and office types)
        if (timesheet.presence) {
            setCustomDropdownValue('timesheetPresence', timesheet.presence);
        }

        if (timesheetType === 'project') {
            // activity_type free-text pre-fill
            const actTypeInput = document.getElementById('timesheetActivityType');
            if (actTypeInput && timesheet.activity_type) actTypeInput.value = timesheet.activity_type;
            // project_id hidden input — set as fallback in case _pendingActivityPreselect is consumed
            const projectIdInput = document.getElementById('timesheetProjectId');
            if (projectIdInput && timesheet.delivery_projects_id) {
                projectIdInput.value = timesheet.delivery_projects_id;
            }
        }

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
    
    // Construct time from dropdowns (support type uses fixed values — time is not relevant)
    let startTime, endTime;
    if (selectedType === 'support') {
        startTime = '00:00';
        endTime   = '23:59';
    } else {
        const startHour   = document.getElementById('timesheetStartHour')?.value   || '08';
        const startMinute = document.getElementById('timesheetStartMinute')?.value || '00';
        const endHour     = document.getElementById('timesheetEndHour')?.value     || '17';
        const endMinute   = document.getElementById('timesheetEndMinute')?.value   || '00';
        startTime = `${startHour}:${startMinute}`;
        endTime   = `${endHour}:${endMinute}`;
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
        // Get project ID from hidden input (set when activity is selected)
        timesheetData.delivery_projects_id = document.getElementById('timesheetProjectId')?.value || null;
        timesheetData.activity_id = document.getElementById('timesheetActivity')?.value || null;
        timesheetData.ticket_id = null;
        timesheetData.activity_type = document.getElementById('timesheetActivityType')?.value || 'development';
        timesheetData.presence = document.getElementById('timesheetPresence')?.value || null;
        timesheetData.location = document.getElementById('timesheetLocation')?.value || null;
        timesheetData.is_billable = document.getElementById('timesheetBillable')?.checked || false;

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