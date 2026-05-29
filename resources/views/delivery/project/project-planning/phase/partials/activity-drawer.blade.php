{{-- =========================================================
     ACTIVITY DETAIL DRAWER — Table View only
     Slide-in panel from the right. Opened via openActivityDrawer(id).
     ========================================================= --}}

{{-- Backdrop --}}
<div id="activityDrawerBackdrop"
     class="hidden fixed inset-0 bg-black bg-opacity-30 z-40 transition-opacity duration-300"
     onclick="closeActivityDrawer()">
</div>

{{-- Drawer Panel --}}
<div id="activityDrawer"
     class="fixed top-0 right-0 h-full w-full sm:w-[420px] lg:w-[460px] bg-white shadow-2xl z-50
            transform translate-x-full transition-transform duration-300 ease-in-out
            flex flex-col overflow-hidden">

    {{-- ── HEADER ── --}}
    <div class="flex items-start justify-between px-5 py-4 border-b border-gray-200 bg-white flex-shrink-0">
        <div class="flex-1 min-w-0 pr-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span id="drawerStatusBadge"
                      class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                    Not Started
                </span>
                <span id="drawerNewReqBadge"
                      class="hidden inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-700">
                    New Req
                </span>
            </div>
            <h3 id="drawerActivityName"
                class="mt-1.5 text-base font-bold text-gray-900 leading-snug break-words">
                Activity Name
            </h3>
            <p id="drawerBreadcrumb" class="mt-0.5 text-xs text-gray-500 truncate"></p>
        </div>
        <button onclick="closeActivityDrawer()"
                class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg
                       bg-gray-100 text-gray-500 hover:bg-red-800 hover:text-white transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ── SCROLLABLE BODY ── --}}
    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

        {{-- Dates Card --}}
        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Timeline</h4>
            </div>
            <div class="px-4 py-3 space-y-3">
                {{-- Planned --}}
                <div class="flex items-center gap-2">
                    <span class="w-16 text-xs font-medium text-gray-500 flex-shrink-0">Planned</span>
                    <div class="flex items-center gap-1 flex-1 min-w-0">
                        <span id="drawerPlannedStart" class="text-xs text-gray-800 font-medium">-</span>
                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                        <span id="drawerPlannedEnd" class="text-xs text-gray-800 font-medium">-</span>
                        <span id="drawerPlannedDays" class="ml-auto text-xs text-gray-500 flex-shrink-0"></span>
                    </div>
                </div>
                {{-- Actual --}}
                <div class="flex items-center gap-2">
                    <span class="w-16 text-xs font-medium text-gray-500 flex-shrink-0">Actual</span>
                    <div class="flex items-center gap-1 flex-1 min-w-0">
                        <span id="drawerActualStart" class="text-xs text-gray-800 font-medium">-</span>
                        <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                        <span id="drawerActualEnd" class="text-xs text-gray-800 font-medium">-</span>
                        <span id="drawerActualDays" class="ml-auto text-xs text-gray-500 flex-shrink-0"></span>
                    </div>
                </div>
                {{-- Deviation --}}
                <div id="drawerDeviationRow" class="hidden pt-1 border-t border-gray-100">
                    <span class="text-xs text-gray-500">Deviation: </span>
                    <span id="drawerDeviation" class="text-xs font-semibold"></span>
                </div>
            </div>
        </div>

        {{-- Progress --}}
        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Progress</h4>
            </div>
            <div class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="flex-1 bg-gray-200 rounded-full h-2.5">
                        <div id="drawerProgressBar" class="bg-blue-600 h-2.5 rounded-full transition-all" style="width:0%"></div>
                    </div>
                    <span id="drawerProgressPct" class="text-sm font-bold text-gray-800 w-10 text-right">0%</span>
                </div>
                <div class="mt-2 flex items-center gap-3 text-xs text-gray-500">
                    <span>Weight: <strong id="drawerWeight" class="text-gray-800">0%</strong></span>
                </div>
            </div>
        </div>

        {{-- Details --}}
        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
                <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Details</h4>
            </div>
            <div class="px-4 py-3">
                <dl class="space-y-2.5">
                    <div class="flex items-start gap-3">
                        <dt class="w-28 text-xs font-medium text-gray-500 flex-shrink-0 pt-0.5">Module</dt>
                        <dd id="drawerModule" class="text-xs text-gray-800 font-medium">-</dd>
                    </div>
                    <div class="flex items-start gap-3">
                        <dt class="w-28 text-xs font-medium text-gray-500 flex-shrink-0 pt-0.5">Object</dt>
                        <dd id="drawerObject" class="text-xs text-gray-800 font-medium">-</dd>
                    </div>
                    <div class="flex items-start gap-3">
                        <dt class="w-28 text-xs font-medium text-gray-500 flex-shrink-0 pt-0.5">Complexity</dt>
                        <dd id="drawerComplexity">
                            <span class="text-xs text-gray-400 italic">-</span>
                        </dd>
                    </div>
                    <div class="flex items-start gap-3">
                        <dt class="w-28 text-xs font-medium text-gray-500 flex-shrink-0 pt-0.5">Receive Type</dt>
                        <dd id="drawerReceiveType" class="text-xs text-gray-800">-</dd>
                    </div>
                    <div id="drawerDeliverableRow" class="flex items-start gap-3">
                        <dt class="w-28 text-xs font-medium text-gray-500 flex-shrink-0 pt-0.5">Deliverable</dt>
                        <dd id="drawerDeliverable" class="text-xs text-gray-800 leading-relaxed whitespace-pre-wrap break-words">-</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Team Members --}}
        <div class="rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h4 class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Team Members</h4>
                <span id="drawerMemberCount" class="text-xs text-gray-400"></span>
            </div>
            <div id="drawerMembersList" class="px-4 py-3">
                <div class="flex items-center justify-center py-4">
                    <svg class="w-5 h-5 text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <span class="ml-2 text-xs text-gray-400">Loading members...</span>
                </div>
            </div>
        </div>

    </div>{{-- end scrollable body --}}

    {{-- ── FOOTER ACTIONS ── --}}
    <div class="flex items-center justify-between gap-3 px-5 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
        <button id="drawerDeleteBtn"
                onclick="drawerDeleteActivity()"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-red-700
                       bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Delete
        </button>
        <button id="drawerEditBtn"
                onclick="drawerEditActivity()"
                class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white
                       bg-blue-600 rounded-lg hover:bg-blue-700 transition-all shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Activity
        </button>
    </div>
</div>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// ACTIVITY DRAWER — JavaScript
// ─────────────────────────────────────────────────────────────────────────────

window._activityDataMap  = {};  // populated by table-view.blade.php during render
window._drawerActivityId = null;
window._drawerStageId    = null;
window._drawerGroupId    = null;

/**
 * Open the drawer for a given activity ID.
 * The activity data should already be in window._activityDataMap.
 */
window.openActivityDrawer = function(activityId, stageId, groupId) {
    const activity = window._activityDataMap[activityId];
    if (!activity) {
        console.error('❌ Activity data not found in map for id:', activityId);
        return;
    }

    window._drawerActivityId = activityId;
    window._drawerStageId    = stageId  || null;
    window._drawerGroupId    = groupId  || null;

    _populateDrawer(activity, stageId, groupId);
    _showDrawer();
    _loadDrawerMembers(activityId);
};

window.closeActivityDrawer = function() {
    const drawer   = document.getElementById('activityDrawer');
    const backdrop = document.getElementById('activityDrawerBackdrop');

    drawer.classList.add('translate-x-full');
    backdrop.classList.remove('bg-opacity-30');
    backdrop.classList.add('bg-opacity-0');

    setTimeout(function() {
        drawer.classList.remove('translate-x-0');
        backdrop.classList.add('hidden');
        backdrop.classList.remove('bg-opacity-0');
        backdrop.classList.add('bg-opacity-30');
    }, 300);
};

window.drawerEditActivity = function() {
    const id      = window._drawerActivityId;
    const stageId = window._drawerStageId;
    const groupId = window._drawerGroupId;

    closeActivityDrawer();

    setTimeout(function() {
        if (stageId) {
            if (typeof window.openActivityModal === 'function') {
                window.openActivityModal(stageId, null, id);
            }
        } else if (groupId) {
            if (typeof window.openActivityModalForGroup === 'function') {
                window.openActivityModalForGroup(groupId, null, null, id);
            }
        }
    }, 320);
};

window.drawerDeleteActivity = function() {
    const id       = window._drawerActivityId;
    const activity = window._activityDataMap[id];
    const name     = activity ? activity.name : 'this activity';

    if (typeof deleteActivity === 'function') {
        closeActivityDrawer();
        setTimeout(function() { deleteActivity(id, name); }, 320);
    }
};

// ─────────────────────────────────────────────────────────────────────────────
// PRIVATE HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function _showDrawer() {
    const drawer   = document.getElementById('activityDrawer');
    const backdrop = document.getElementById('activityDrawerBackdrop');

    backdrop.classList.remove('hidden');
    // Force reflow so the transition fires
    void backdrop.offsetWidth;
    drawer.classList.remove('translate-x-full');
    drawer.classList.add('translate-x-0');
}

function _populateDrawer(activity, stageId, groupId) {

    // ── Status badge ──
    const statusStyles = {
        not_started : 'bg-gray-100 text-gray-700',
        in_progress : 'bg-blue-100 text-blue-700',
        completed   : 'bg-green-100 text-green-700',
        delayed     : 'bg-red-100 text-red-700',
        on_hold     : 'bg-yellow-100 text-yellow-700',
    };
    const statusLabels = {
        not_started : 'Not Started',
        in_progress : 'In Progress',
        completed   : 'Completed',
        delayed     : 'Delayed',
        on_hold     : 'On Hold',
    };
    const status = activity.status || 'not_started';
    const statusBadge = document.getElementById('drawerStatusBadge');
    statusBadge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ' +
                            (statusStyles[status] || statusStyles.not_started);
    statusBadge.textContent = statusLabels[status] || 'Not Started';

    // ── New Requirement badge ──
    const newReqBadge = document.getElementById('drawerNewReqBadge');
    if (activity.new_requirement) {
        newReqBadge.classList.remove('hidden');
    } else {
        newReqBadge.classList.add('hidden');
    }

    // ── Name & breadcrumb ──
    document.getElementById('drawerActivityName').textContent = activity.name || 'Unnamed Activity';

    // Build breadcrumb from stageId / groupId context
    // (simple: just show "Direct" or "Via Stage")
    const breadcrumbEl = document.getElementById('drawerBreadcrumb');
    if (stageId) {
        breadcrumbEl.textContent = 'Activity in Stage';
    } else if (groupId) {
        breadcrumbEl.textContent = 'Direct activity in Group';
    } else {
        breadcrumbEl.textContent = '';
    }

    // ── Timeline ──
    document.getElementById('drawerPlannedStart').textContent = activity.start_date || '-';
    document.getElementById('drawerPlannedEnd').textContent   = activity.end_date   || '-';
    const plannedDays = activity.duration_in_days;
    document.getElementById('drawerPlannedDays').textContent  = plannedDays ? plannedDays + ' days' : '';

    document.getElementById('drawerActualStart').textContent = activity.actual_start_date || '-';
    document.getElementById('drawerActualEnd').textContent   = activity.actual_end_date   || '-';
    const actualDays = activity.actual_duration_in_days;
    document.getElementById('drawerActualDays').textContent  = actualDays ? actualDays + ' days' : '';

    // Deviation
    const devRow = document.getElementById('drawerDeviationRow');
    const devEl  = document.getElementById('drawerDeviation');
    if (plannedDays && actualDays) {
        const diff = actualDays - plannedDays;
        devRow.classList.remove('hidden');
        if (diff > 0) {
            devEl.textContent = '+' + diff + ' days (late)';
            devEl.className   = 'text-xs font-semibold text-red-600';
        } else if (diff < 0) {
            devEl.textContent = diff + ' days (early)';
            devEl.className   = 'text-xs font-semibold text-green-600';
        } else {
            devEl.textContent = 'On time';
            devEl.className   = 'text-xs font-semibold text-green-600';
        }
    } else {
        devRow.classList.add('hidden');
    }

    // ── Progress ──
    const pct = parseFloat(activity.progress_percentage) || 0;
    const progressBar = document.getElementById('drawerProgressBar');
    progressBar.style.width = pct + '%';
    // Color by status
    const barColors = { not_started:'bg-gray-400', in_progress:'bg-blue-600', completed:'bg-green-500', delayed:'bg-red-500' };
    progressBar.className = 'h-2.5 rounded-full transition-all ' + (barColors[status] || 'bg-blue-600');
    document.getElementById('drawerProgressPct').textContent = Math.round(pct) + '%';
    document.getElementById('drawerWeight').textContent = (parseFloat(activity.weight) || 0).toFixed(1) + '%';

    // ── Details ──
    document.getElementById('drawerModule').textContent      = activity.module       || '-';
    document.getElementById('drawerObject').textContent      = activity.object       || '-';
    document.getElementById('drawerReceiveType').textContent = activity.receive_type || '-';
    document.getElementById('drawerDeliverable').textContent = activity.deliverable  || '-';

    // Complexity badge
    const complexityEl = document.getElementById('drawerComplexity');
    const complexityMap = {
        low    : { cls: 'bg-green-100 text-green-700',  label: 'Low'    },
        medium : { cls: 'bg-yellow-100 text-yellow-700', label: 'Medium' },
        high   : { cls: 'bg-red-100 text-red-700',      label: 'High'   },
    };
    const c = complexityMap[activity.complexity];
    if (c) {
        complexityEl.innerHTML = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${c.cls}">${c.label}</span>`;
    } else {
        complexityEl.innerHTML = '<span class="text-xs text-gray-400 italic">-</span>';
    }

    // ── Reset members section ──
    document.getElementById('drawerMembersList').innerHTML = `
        <div class="flex items-center justify-center py-4">
            <svg class="w-5 h-5 text-gray-300 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span class="ml-2 text-xs text-gray-400">Loading members...</span>
        </div>`;
    document.getElementById('drawerMemberCount').textContent = '';
}

async function _loadDrawerMembers(activityId) {
    const pid = window.projectId || window.currentProjectId;
    if (!pid) return;

    try {
        const res = await axios.get(`/planning/${pid}/activities/${activityId}/members`);
        const members = res.data.data || res.data.members || res.data || [];

        const container = document.getElementById('drawerMembersList');
        const countEl   = document.getElementById('drawerMemberCount');

        countEl.textContent = members.length ? members.length + ' member(s)' : '';

        if (members.length === 0) {
            container.innerHTML = '<p class="py-3 text-xs text-gray-400 italic text-center">No team members assigned.</p>';
            return;
        }

        const roleColors = {
            lead    : 'bg-purple-100 text-purple-700',
            member  : 'bg-blue-100 text-blue-700',
            reviewer: 'bg-orange-100 text-orange-700',
            support : 'bg-green-100 text-green-700',
        };

        container.innerHTML = members.map(function(m) {
            const name = _getMemberName(m);
            const role = m.role || m.pivot?.role || 'member';
            const roleCls = roleColors[role] || 'bg-gray-100 text-gray-700';
            const initial = name.charAt(0).toUpperCase();

            return `
                <div class="flex items-center gap-3 py-2 border-b border-gray-50 last:border-0">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                        <span class="text-sm font-semibold text-indigo-700">${initial}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-900 truncate">${name}</p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold flex-shrink-0 ${roleCls}">
                        ${role}
                    </span>
                </div>`;
        }).join('');

    } catch (err) {
        console.error('❌ Failed to load drawer members:', err);
        document.getElementById('drawerMembersList').innerHTML =
            '<p class="py-3 text-xs text-red-400 text-center">Failed to load members.</p>';
    }
}

function _getMemberName(m) {
    if (m.name && m.name !== 'N/A') return m.name;
    if (m.basicData)  return m.basicData.full_name  || (m.basicData.first_name  + ' ' + (m.basicData.last_name  || '')).trim();
    if (m.basic_data) return m.basic_data.full_name || (m.basic_data.first_name + ' ' + (m.basic_data.last_name || '')).trim();
    return m.eci || 'Employee #' + m.employee_id;
}

// Close drawer on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && window._drawerActivityId) {
        closeActivityDrawer();
    }
});
</script>

<style>
/* Smooth drawer slide */
#activityDrawer {
    will-change: transform;
}
#activityDrawerBackdrop {
    will-change: opacity;
}
</style>
