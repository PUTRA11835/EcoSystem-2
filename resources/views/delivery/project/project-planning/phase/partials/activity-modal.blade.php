{{-- ✅ SUPER FIXED ACTIVITY MODAL - Better Error Handling --}}
<div id="activityModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg md:max-w-2xl lg:max-w-4xl flex flex-col max-h-[90vh]">
            <form id="activityForm" onsubmit="saveActivity(event)" class="flex flex-col flex-1 min-h-0">
                {{-- Header --}}
                <div class="flex justify-between items-center px-6 py-5 border-b border-gray-200 flex-shrink-0">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900" id="activityModalTitle">Add Activity</h3>
                        <p class="text-sm text-gray-500 mt-0.5" id="activityModalSubtitle">Stage Name</p>
                    </div>
                    <button type="button" onclick="closeActivityModal()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 text-gray-600 hover:bg-red-800 hover:text-white transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Form Body --}}
                <div class="px-4 sm:px-6 py-4 sm:py-5 overflow-y-auto flex-1">
                    <div class="space-y-4">
                        <!-- Activity Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Activity Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="activityName" required 
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                placeholder="Enter activity name...">
                        </div>

                        <!-- Duration (working days) -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Duration <span class="text-xs text-gray-500 font-normal">(working days)</span>
                                </label>
                                <input type="number" id="activityDuration" min="1" step="1" value="1"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                                <p class="text-xs text-gray-500 mt-1">Skips weekends &amp; tanggal merah</p>
                            </div>
                        </div>

                        <!-- Planned Dates -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Planned Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="activityStartDate" required
                                    placeholder="dd/mm/yyyy"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Planned End Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="activityEndDate" required
                                    placeholder="dd/mm/yyyy"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Actual Dates -->
                        <div id="activityActualDates" class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual Start Date
                                </label>
                                <input type="text" id="activityActualStart"
                                    placeholder="dd/mm/yyyy"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual End Date <span id="activityActualEndRequired" class="hidden text-red-500">*</span>
                                </label>
                                <input type="text" id="activityActualEnd"
                                    placeholder="dd/mm/yyyy"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                                <p id="activityActualEndHint" class="hidden mt-1 text-xs text-amber-600">⚠ Required when progress is 100%</p>
                            </div>
                        </div>

                        <!-- Weight, Status, Progress -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Weight (%)</label>
                                <input type="number" id="activityWeight" required
                                    min="0" max="100" step="0.1" value="10"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                    oninput="updatePhaseWeightDisplay()">
                            </div>
                            <div id="activityStatusField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-xs text-gray-400 font-normal">(auto)</span></label>
                                <div id="activityStatusBadge" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                    <span id="activityStatusBadgeText">Not Started</span>
                                </div>
                                {{-- Hidden input to keep status in form submission (kept for compatibility) --}}
                                <input type="hidden" id="activityStatus" value="not_started">
                            </div>
                            <div id="activityProgressField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Progress (%)</label>
                                <input type="number" id="activityProgress" required
                                    min="0" max="100" step="1" value="0"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                    oninput="updateAutoStatus()">
                            </div>
                        </div>

                        <!-- Go-Live Activity marker -->
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" id="activityIsGolive"
                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Mark as Go-Live activity</span>
                        </label>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Module</label>
                                <input type="text" id="activityModule" 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                       placeholder="e.g., FI, MM, SD...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Object</label>
                                <input type="text" id="activityObject" 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                       placeholder="e.g., FB50, ME21N...">
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <button type="button" onclick="toggleExtendedFields()" 
                                    class="flex items-center justify-between w-full text-left text-sm font-medium text-gray-700 hover:text-gray-900">
                                <span>📋 Additional Details</span>
                                <svg id="extendedFieldsIcon" class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <div id="extendedFields" class="mt-4 space-y-4 hidden">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Complexity</label>
                                        <select id="activityComplexity"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">Select...</option>
                                            <option value="low">Low</option>
                                            <option value="medium">Medium</option>
                                            <option value="high">High</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Receive Type</label>
                                        <input type="text" id="activityReceiveType" 
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                               placeholder="e.g., Email, Portal...">
                                    </div>
                                    <div>
                                        <label class="flex items-center space-x-2 mt-6">
                                            <input type="checkbox" id="activityNewRequirement" 
                                                   class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                            <span class="text-sm text-gray-700">New Requirement</span>
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Deliverable</label>
                                    <textarea id="activityDeliverable" rows="2"
                                              class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                              placeholder="Expected deliverables..."></textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Team Member Assignment Section --}}
                        <div class="border-t pt-4" id="teamMemberSection">
                            <button type="button" onclick="toggleTeamMemberFields()"
                                    class="flex items-center justify-between w-full text-left text-sm font-medium text-gray-700 hover:text-gray-900">
                                <span>👥 Team Member Assignment</span>
                                <svg id="teamMemberFieldsIcon" class="w-4 h-4 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <div id="teamMemberFields" class="mt-4 space-y-4 hidden">
                                {{-- Add Member Form --}}
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <select id="activityMemberSelect"
                                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        <option value="">Select team member...</option>
                                    </select>
                                    <input type="number" id="activityMemberDuration" min="1" step="1"
                                           placeholder="Duration (days)"
                                           class="w-full sm:w-40 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <button type="button" onclick="addActivityMember()"
                                            class="w-full sm:w-auto inline-flex justify-center items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Add
                                    </button>
                                </div>

                                {{-- Assigned Members List --}}
                                <div id="assignedMembersList" class="space-y-2">
                                    <p class="text-sm text-gray-500 italic">No team members assigned yet</p>
                                </div>

                                {{-- Info Box --}}
                                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-2">
                                    <p class="text-xs text-yellow-700">
                                        💡 Assigned members can fill timesheets for this activity. Only project team members can be assigned.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Phase Weight Limit Panel --}}
                        <div id="phaseWeightPanel" class="hidden border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <p class="text-xs font-semibold text-gray-700 mb-3">
                                Phase Weight Limit — <span id="phaseWeightPhaseName" class="text-gray-500 font-normal"></span>
                            </p>
                            <div class="flex items-center justify-between text-xs mb-2">
                                <span class="text-gray-500">Used (others): <strong id="phaseUsedDisplay" class="text-gray-800">0%</strong></span>
                                <span class="text-gray-500">This activity: <strong id="phaseThisDisplay" class="text-blue-700">0%</strong></span>
                                <span class="text-gray-500">Limit: <strong id="phaseLimitDisplay" class="text-gray-800">100%</strong></span>
                            </div>
                            {{-- Progress bar --}}
                            <div class="w-full bg-gray-200 rounded-full h-2.5 relative overflow-hidden">
                                <div id="phaseWeightBarUsed" class="absolute left-0 top-0 h-2.5 bg-blue-400 transition-all duration-300" style="width:0%"></div>
                                <div id="phaseWeightBarNew" class="absolute top-0 h-2.5 bg-blue-600 transition-all duration-300" style="left:0%;width:0%"></div>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs" id="phaseRemainingLabel">
                                    Remaining after save: <strong id="phaseRemainingDisplay" class="text-green-700">—</strong>
                                </span>
                                <span id="phaseWeightWarning" class="hidden text-xs font-semibold text-red-600">⚠ Exceeds phase limit!</span>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-blue-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3 flex-1">
                                    <p class="text-xs sm:text-sm text-blue-700">
                                        💡 <strong>Weight Distribution:</strong> Activity weights are absolute percentages of the overall project. The sum of all activities in a phase must equal that phase's configured weight.
                                    </p>
                                    <p class="text-xs text-blue-600 mt-1" id="weightInfo">Group weight will auto-update to the sum of its activities.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                    <button type="button" onclick="closeActivityModal()"
                            class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                        Cancel
                    </button>
                    <button type="submit" id="activitySubmitBtn"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Activity
                    </button>
                </div>
            </form>
        </div>
</div>

{{-- Confirm Remove Member Modal --}}
<div id="activityMemberDeleteModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeRemoveMemberModal()"></div>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm">
        <div class="px-6 py-5">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Remove Team Member</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Remove <span id="removeMemberName" class="font-medium text-gray-700">this member</span> from the activity?
                    </p>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
            <button type="button" onclick="closeRemoveMemberModal()"
                    class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition">
                Cancel
            </button>
            <button type="button" id="confirmRemoveMemberBtn" onclick="confirmRemoveActivityMember()"
                    class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition">
                Remove
            </button>
        </div>
    </div>
</div>

{{-- ✅ SUPER FIXED JAVASCRIPT --}}
<script>
// ============================================================================
// ✅ SUPER FIXED - Better Error Handling & Recovery
// ============================================================================

// ============================================================================
// ✅ DATE FORMAT HELPERS (dd/mm/yyyy - Indonesian format)
// ============================================================================

/**
 * Convert ISO date (yyyy-mm-dd) to Indonesian format (dd/mm/yyyy)
 */
function isoToIndonesian(isoDate) {
    if (!isoDate) return '';
    const parts = isoDate.split(' ')[0].split('-');
    if (parts.length !== 3) return isoDate;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

/**
 * Convert Indonesian date (dd/mm/yyyy) to ISO format (yyyy-mm-dd)
 */
function indonesianToIso(indoDate) {
    if (!indoDate) return '';
    const parts = indoDate.split('/');
    if (parts.length !== 3) return indoDate;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

/**
 * Get today's date in Indonesian format
 */
function getTodayIndonesian() {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = today.getFullYear();
    return `${day}/${month}/${year}`;
}

/**
 * Get date N days from now in Indonesian format
 */
function getDateFromNowIndonesian(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

/**
 * Parse Indonesian date to Date object
 */
function parseIndonesianDate(indoDate) {
    if (!indoDate) return null;
    const parts = indoDate.split('/');
    if (parts.length !== 3) return null;
    return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
}

/**
 * Get employee name from member object (handles multiple response formats)
 */
function getEmployeeName(member) {
    // ✅ Direct name property (from getAssignedMembers API)
    if (member.name && member.name !== 'N/A') {
        return member.name;
    }
    // Try basicData (camelCase - from Laravel eager loading)
    if (member.basicData) {
        if (member.basicData.full_name) return member.basicData.full_name;
        if (member.basicData.first_name) {
            return `${member.basicData.first_name} ${member.basicData.last_name || ''}`.trim();
        }
    }
    // Try basic_data (snake_case - from API/JSON)
    if (member.basic_data) {
        if (member.basic_data.full_name) return member.basic_data.full_name;
        if (member.basic_data.first_name) {
            return `${member.basic_data.first_name} ${member.basic_data.last_name || ''}`.trim();
        }
    }
    // Fallback to ECI or Employee ID
    return member.eci || `Employee #${member.employee_id}`;
}

function toggleExtendedFields() {
    const fields = document.getElementById('extendedFields');
    const icon = document.getElementById('extendedFieldsIcon');
    
    if (fields && icon) {
        fields.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }
}

/**
 * ✅ HELPER: Reset submit button to normal state
 */
function resetSubmitButton() {
    const submitBtn = document.getElementById('activitySubmitBtn');
    if (submitBtn) {
        submitBtn.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            Save Activity
        `;
        submitBtn.disabled = false;
    }
}

/**
 * ✅ HELPER: Set submit button to loading state
 */
function setSubmitButtonLoading() {
    const submitBtn = document.getElementById('activitySubmitBtn');
    if (submitBtn) {
        submitBtn.innerHTML = `
            <svg class="animate-spin h-5 w-5 mr-2 inline-block" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Saving...
        `;
        submitBtn.disabled = true;
    }
}

// Global variables for group-direct activities
let currentPhaseIdForActivity = null;
let isGroupDirectActivity = false;

// Phase weight state
let phaseWeightLimit = 0;
let phaseWeightUsed  = 0;

async function loadPhaseWeightInfo(phaseId, excludeActivityId = null) {
    const panel = document.getElementById('phaseWeightPanel');
    if (!phaseId) { panel.classList.add('hidden'); return; }

    try {
        const pid = window.projectId || window.currentProjectId;
        let url = `/planning/${pid}/phases/${phaseId}/weight-info`;
        if (excludeActivityId) url += `?exclude_activity_id=${excludeActivityId}`;
        const res = await axios.get(url);
        const d   = res.data;

        phaseWeightLimit = d.phase_weight  || 0;
        phaseWeightUsed  = d.used_weight   || 0;

        document.getElementById('phaseWeightPhaseName').textContent = d.phase_name || '';
        panel.classList.remove('hidden');
        updatePhaseWeightDisplay();
    } catch (e) {
        console.warn('Could not load phase weight info', e);
        panel.classList.add('hidden');
    }
}

function updatePhaseWeightDisplay() {
    if (phaseWeightLimit <= 0) return;

    const thisWeight = parseFloat(document.getElementById('activityWeight')?.value) || 0;
    const totalAfter = phaseWeightUsed + thisWeight;
    const pct        = v => (v / phaseWeightLimit * 100).toFixed(1);

    document.getElementById('phaseUsedDisplay').textContent    = phaseWeightUsed.toFixed(1) + '%';
    document.getElementById('phaseThisDisplay').textContent    = thisWeight.toFixed(1) + '%';
    document.getElementById('phaseLimitDisplay').textContent   = phaseWeightLimit.toFixed(1) + '%';

    const usedPct = Math.min(100, pct(phaseWeightUsed));
    const newPct  = Math.min(100 - usedPct, pct(thisWeight));

    document.getElementById('phaseWeightBarUsed').style.width = usedPct + '%';
    const barNew = document.getElementById('phaseWeightBarNew');
    barNew.style.left  = usedPct + '%';
    barNew.style.width = newPct  + '%';

    const remaining = phaseWeightLimit - totalAfter;
    const remEl  = document.getElementById('phaseRemainingDisplay');
    const warnEl = document.getElementById('phaseWeightWarning');

    if (totalAfter > phaseWeightLimit + 0.01) {
        remEl.textContent  = '—';
        remEl.className    = 'text-red-600 font-semibold';
        barNew.className   = 'absolute top-0 h-2.5 bg-red-500 transition-all duration-300';
        warnEl.classList.remove('hidden');
    } else {
        remEl.textContent = remaining.toFixed(1) + '%';
        remEl.className   = remaining < 5 ? 'text-amber-600 font-semibold' : 'text-green-700 font-semibold';
        barNew.className  = 'absolute top-0 h-2.5 bg-blue-600 transition-all duration-300';
        warnEl.classList.add('hidden');
    }
}

// ============================================================================
// FLATPICKR + DURATION 3-WAY BINDING + AUTO-STATUS
// ============================================================================

let _suppressDateSync = false;

function destroyActivityPickers() {
    ['_fpStartDate', '_fpEndDate', '_fpActualStart', '_fpActualEnd'].forEach(k => {
        if (window[k]) {
            try { window[k].destroy(); } catch (e) {}
            window[k] = null;
        }
    });
}

async function initActivityModalPickers() {
    // Ensure holidays loaded before creating pickers
    await HolidayCalendar.load();

    destroyActivityPickers();

    // Contract window bounds — planning dates may not fall outside the contract period.
    // PENTING: contract dates datang sebagai string ISO ('Y-m-d'), tapi dateFormat
    // picker ini 'd/m/Y'. Flatpickr mem-parse minDate/maxDate STRING memakai dateFormat,
    // sehingga '2026-06-04' salah di-parse → bounds ngawur → SEMUA tanggal ke-disable.
    // Solusi: kirim sebagai objek Date (Flatpickr menerima Date langsung tanpa parsing).
    var _contract = window.projectContractDates || {};
    var _planBounds = {};
    if (_contract.start) _planBounds.minDate = new Date(_contract.start + 'T00:00:00');
    if (_contract.end)   _planBounds.maxDate = new Date(_contract.end + 'T00:00:00');

    window._fpStartDate = HolidayCalendar.initPicker(
        document.getElementById('activityStartDate'),
        Object.assign({ onChange: function(dates) { onActivityStartChange(dates); updateAutoStatus(); } }, _planBounds)
    );
    window._fpEndDate = HolidayCalendar.initPicker(
        document.getElementById('activityEndDate'),
        Object.assign({ onChange: function(dates) { onActivityEndChange(dates); updateAutoStatus(); } }, _planBounds)
    );
    window._fpActualStart = HolidayCalendar.initPicker(
        document.getElementById('activityActualStart'),
        { onChange: function() { updateAutoStatus(); } }
    );
    window._fpActualEnd = HolidayCalendar.initPicker(
        document.getElementById('activityActualEnd'),
        { onChange: function() { updateAutoStatus(); } }
    );
}

function onActivityDurationChange() {
    if (_suppressDateSync) return;
    if (!window._fpStartDate) return;

    const start = window._fpStartDate.selectedDates[0];
    if (!start) return;

    const duration = parseInt(document.getElementById('activityDuration').value) || 0;
    if (duration < 1) return;

    const newEnd = HolidayCalendar.addWorkingDays(start, duration);
    _suppressDateSync = true;
    window._fpEndDate.setDate(newEnd, false);
    _suppressDateSync = false;
}

function onActivityStartChange(selectedDates) {
    if (_suppressDateSync) return;
    const start = selectedDates[0];
    if (!start || !window._fpEndDate) return;

    const duration = parseInt(document.getElementById('activityDuration').value) || 0;

    if (duration >= 1) {
        const newEnd = HolidayCalendar.addWorkingDays(start, duration);
        _suppressDateSync = true;
        window._fpEndDate.setDate(newEnd, false);
        _suppressDateSync = false;
    } else {
        // No duration yet: derive duration from start..end if end exists
        const end = window._fpEndDate.selectedDates[0];
        if (end && end >= start) {
            const d = HolidayCalendar.countWorkingDays(start, end);
            _suppressDateSync = true;
            document.getElementById('activityDuration').value = Math.max(1, d);
            _suppressDateSync = false;
        }
    }
}

function onActivityEndChange(selectedDates) {
    if (_suppressDateSync) return;
    const end = selectedDates[0];
    if (!end || !window._fpStartDate) return;

    const start = window._fpStartDate.selectedDates[0];
    if (!start) return;

    if (end < start) return; // ignored (validated separately on save)

    // Manual end change → recompute duration
    const d = HolidayCalendar.countWorkingDays(start, end);
    _suppressDateSync = true;
    document.getElementById('activityDuration').value = Math.max(1, d);
    _suppressDateSync = false;
}

function computeActivityStatus({ progress, plannedStart, plannedEnd, actualStart, actualEnd }) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    progress = parseFloat(progress) || 0;

    const ps = plannedStart ? parseIndonesianDate(plannedStart) : null;
    const pe = plannedEnd   ? parseIndonesianDate(plannedEnd)   : null;
    const as = actualStart  ? parseIndonesianDate(actualStart)  : null;
    const ae = actualEnd    ? parseIndonesianDate(actualEnd)    : null;

    // === DELAYED (precedence) ===
    if (ae && pe && ae > pe)                              return 'delayed';
    if (progress < 100 && pe && today > pe)               return 'delayed';
    if (progress === 0 && ps && today > ps && !as)        return 'delayed';

    // === COMPLETED ===
    if (progress >= 100 && ae)                            return 'completed';

    // === IN_PROGRESS / NOT_STARTED ===
    if (progress >= 100 && !ae)                           return 'in_progress';
    if (progress === 0)                                   return 'not_started';
    return 'in_progress';
}

function updateAutoStatus() {
    const start    = document.getElementById('activityStartDate').value;
    const end      = document.getElementById('activityEndDate').value;
    const actStart = document.getElementById('activityActualStart').value;
    const actEnd   = document.getElementById('activityActualEnd').value;
    const progress = document.getElementById('activityProgress')?.value || 0;

    const status = computeActivityStatus({
        progress: progress,
        plannedStart: start, plannedEnd: end,
        actualStart: actStart, actualEnd: actEnd,
    });

    const styles = {
        not_started: { cls: 'bg-gray-100 text-gray-800 border-gray-200',   label: 'Not Started' },
        in_progress: { cls: 'bg-blue-100 text-blue-800 border-blue-200',   label: 'In Progress' },
        completed:   { cls: 'bg-green-100 text-green-800 border-green-200', label: 'Completed' },
        delayed:     { cls: 'bg-red-100  text-red-800 border-red-200',     label: 'Delayed' },
    };
    const s = styles[status];
    const badge = document.getElementById('activityStatusBadge');
    if (badge) badge.className = 'inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium border ' + s.cls;
    const txt = document.getElementById('activityStatusBadgeText');
    if (txt) txt.textContent = s.label;
    const hidden = document.getElementById('activityStatus');
    if (hidden) hidden.value = status;

    // Show "actual_end required when progress 100" hint
    const progNum = parseFloat(progress) || 0;
    const needsActualEnd = progNum >= 100 && !actEnd;
    const reqMark = document.getElementById('activityActualEndRequired');
    const hint    = document.getElementById('activityActualEndHint');
    if (reqMark) reqMark.classList.toggle('hidden', !needsActualEnd);
    if (hint)    hint.classList.toggle('hidden', !needsActualEnd);
}

/**
 * Open activity modal for activities directly under a GROUP (without stage)
 */
window.openActivityModalForGroup = function(groupId, groupName, phaseId, activityId = null) {

    if (!groupId || groupId === 'null' || groupId === 'undefined') {
        console.error('❌ Invalid groupId:', groupId);
        if (typeof showNotification === 'function') {
            showNotification('Error: Invalid group ID', 'error');
        }
        return;
    }

    isGroupDirectActivity = true;
    currentStageId = null;
    currentGroupId = groupId;
    currentPhaseIdForActivity = phaseId;

    const modal = document.getElementById('activityModal');
    const form = document.getElementById('activityForm');

    if (!modal || !form) {
        console.error('❌ Activity modal elements not found');
        return;
    }

    form.reset();
    resetSubmitButton();

    const extendedFields = document.getElementById('extendedFields');
    if (extendedFields && window.innerWidth < 640) {
        extendedFields.classList.add('hidden');
    }

    resetTeamMemberSection();

    // Collapse team member fields by default
    const teamMemberFields = document.getElementById('teamMemberFields');
    const teamMemberIcon = document.getElementById('teamMemberFieldsIcon');
    if (teamMemberFields) teamMemberFields.classList.add('hidden');
    if (teamMemberIcon) teamMemberIcon.classList.remove('rotate-180');

    // Always show status badge (it's auto-computed, read-only)
    document.getElementById('activityStatusField').classList.remove('hidden');

    if (activityId && activityId !== 'null' && activityId !== 'undefined') {
        activityFormMode = 'edit';
        currentActivityId = activityId;

        document.getElementById('activityModalTitle').textContent = 'Edit Activity';
        document.getElementById('activityModalSubtitle').textContent = `Group: ${groupName || 'Direct Activity'}`;
        document.getElementById('activityProgressField').classList.remove('hidden');

        // Init pickers BEFORE loadActivityData so setDate works on Flatpickr instances
        initActivityModalPickers().then(() => loadActivityData(activityId));
        loadAssignedMembers(activityId);
    } else {
        activityFormMode = 'create';
        currentActivityId = null;

        document.getElementById('activityModalTitle').textContent = 'Add Activity to Group';
        document.getElementById('activityModalSubtitle').textContent = `Group: ${groupName || 'Direct Activity'}`;
        document.getElementById('activityProgressField').classList.add('hidden');

        initActivityModalPickers().then(() => {
            // Default: today as start, duration 1 → end same day
            const today = new Date();
            document.getElementById('activityDuration').value = 1;
            _suppressDateSync = true;
            window._fpStartDate.setDate(today, false);
            window._fpEndDate.setDate(today, false);
            _suppressDateSync = false;
            updateAutoStatus();
        });

        updateMemberDropdown();
    }

    // Update weight info for group
    const weightInfoEl = document.getElementById('weightInfo');
    if (weightInfoEl) {
        weightInfoEl.innerHTML = '💡 This activity will be added directly to the group (without stage)';
        weightInfoEl.className = 'text-xs text-blue-600 mt-1';
    }

    // Reset & load phase weight info
    phaseWeightLimit = 0;
    phaseWeightUsed  = 0;
    document.getElementById('phaseWeightPanel').classList.add('hidden');
    if (phaseId) {
        loadPhaseWeightInfo(phaseId, (activityId && activityId !== 'null') ? activityId : null);
    }

    modal.classList.remove('hidden');
    document.getElementById('activityName').focus();
};

window.openActivityModal = function(stageId, groupId, activityId = null, groupNameForDirect = null) {
    // Check if this is a group-direct activity call (stageId is null)
    if ((!stageId || stageId === 'null' || stageId === 'undefined') && groupId) {
        window.openActivityModalForGroup(groupId, groupNameForDirect, null, activityId);
        return;
    }


    if (!stageId || stageId === 'null' || stageId === 'undefined') {
        console.error('❌ Invalid stageId:', stageId);
        if (typeof showNotification === 'function') {
            showNotification('Error: Invalid stage ID', 'error');
        }
        return;
    }

    isGroupDirectActivity = false;
    currentStageId = stageId;
    currentGroupId = groupId;
    currentPhaseIdForActivity = null;
    
    const modal = document.getElementById('activityModal');
    const form = document.getElementById('activityForm');
    
    if (!modal || !form) {
        console.error('❌ Activity modal elements not found');
        return;
    }
    
    form.reset();
    resetSubmitButton();
    
    const extendedFields = document.getElementById('extendedFields');
    if (extendedFields && window.innerWidth < 640) {
        extendedFields.classList.add('hidden');
    }

    resetTeamMemberSection();

    // Collapse team member fields by default
    const teamMemberFields = document.getElementById('teamMemberFields');
    const teamMemberIcon = document.getElementById('teamMemberFieldsIcon');
    if (teamMemberFields) teamMemberFields.classList.add('hidden');
    if (teamMemberIcon) teamMemberIcon.classList.remove('rotate-180');

    // Always show status badge (it's auto-computed, read-only)
    document.getElementById('activityStatusField').classList.remove('hidden');

    if (activityId && activityId !== 'null' && activityId !== 'undefined') {
        activityFormMode = 'edit';
        currentActivityId = activityId;

        document.getElementById('activityModalTitle').textContent = 'Edit Activity';
        document.getElementById('activityProgressField').classList.remove('hidden');

        initActivityModalPickers().then(() => loadActivityData(activityId));
        loadAssignedMembers(activityId);
    } else {
        activityFormMode = 'create';
        currentActivityId = null;

        document.getElementById('activityModalTitle').textContent = 'Add New Activity';
        document.getElementById('activityProgressField').classList.add('hidden');

        initActivityModalPickers().then(() => {
            const today = new Date();
            document.getElementById('activityDuration').value = 1;
            _suppressDateSync = true;
            window._fpStartDate.setDate(today, false);
            window._fpEndDate.setDate(today, false);
            _suppressDateSync = false;
            updateAutoStatus();
        });

        updateMemberDropdown();
    }
    
    // Reset phase weight panel; loadStageInfo will populate it once phase_id is known
    phaseWeightLimit = 0;
    phaseWeightUsed  = 0;
    document.getElementById('phaseWeightPanel').classList.add('hidden');

    loadStageInfo(stageId);

    modal.classList.remove('hidden');
    document.getElementById('activityName').focus();
};

window.closeActivityModal = function() {
    const modal = document.getElementById('activityModal');
    if (modal) {
        modal.classList.add('hidden');
    }

    destroyActivityPickers();
    resetSubmitButton();

    activityFormMode = 'create';
    currentActivityId = null;
    currentStageId = null;
    currentGroupId = null;
};

function loadStageInfo(stageId) {
    const pid = window.projectId || window.currentProjectId;
    axios.get(`/planning/${pid}/stages/${stageId}`)
        .then(response => {
            const stage = response.data.data || response.data;
            document.getElementById('activityModalSubtitle').textContent = `Stage: ${stage.name}`;
            updateWeightInfo(stage);

            // Load phase weight info using phase_id from stage
            if (stage.phase_id) {
                const excludeId = (activityFormMode === 'edit' && currentActivityId) ? currentActivityId : null;
                loadPhaseWeightInfo(stage.phase_id, excludeId);
            }
        })
        .catch(error => {
            console.error('❌ Error loading stage:', error);
            document.getElementById('activityModalSubtitle').textContent = 'Stage Information';
        });
}

function updateWeightInfo(stage) {
    const weightInfoEl = document.getElementById('weightInfo');
    if (!weightInfoEl) return;
    
    const activities = stage.activities || [];
    const totalWeight = activities.reduce((sum, act) => sum + (parseFloat(act.weight) || 0), 0);
    const remainingWeight = 100 - totalWeight;
    
    if (remainingWeight > 0) {
        weightInfoEl.innerHTML = `✅ Available weight: <strong>${remainingWeight.toFixed(1)}%</strong> of 100%`;
        weightInfoEl.className = 'text-xs text-green-600 mt-1';
    } else if (remainingWeight === 0) {
        weightInfoEl.innerHTML = `✅ Stage weight is complete: <strong>100%</strong>`;
        weightInfoEl.className = 'text-xs text-green-600 mt-1';
    } else {
        weightInfoEl.innerHTML = `⚠️ Weight exceeded by: <strong>${Math.abs(remainingWeight).toFixed(1)}%</strong>`;
        weightInfoEl.className = 'text-xs text-red-600 mt-1 font-semibold';
    }
}

function loadActivityData(activityId) {
    const form = document.getElementById('activityForm');
    form.classList.add('opacity-50', 'pointer-events-none');


    axios.get(`/planning/${window.projectId}/activities/${activityId}?type=activity`)
        .then(response => {
            const activity = response.data;

            
            document.getElementById('activityName').value = activity.name || '';

            // Set dates via Flatpickr (suppress sync to avoid recompute loops)
            // NOTE: pass 'Y-m-d' as 3rd arg so Flatpickr parses ISO strings correctly
            // (default dateFormat is 'd/m/Y'; without explicit format, ISO "2026-05-25" fails)
            _suppressDateSync = true;
            if (activity.start_date && window._fpStartDate) window._fpStartDate.setDate(activity.start_date, false, 'Y-m-d');
            if (activity.end_date   && window._fpEndDate)   window._fpEndDate.setDate(activity.end_date, false, 'Y-m-d');
            if (activity.actual_start_date && window._fpActualStart) window._fpActualStart.setDate(activity.actual_start_date, false, 'Y-m-d');
            if (activity.actual_end_date   && window._fpActualEnd)   window._fpActualEnd.setDate(activity.actual_end_date, false, 'Y-m-d');
            _suppressDateSync = false;

            // Derive duration from existing planned dates
            if (activity.start_date && activity.end_date) {
                const s = new Date(activity.start_date);
                const e = new Date(activity.end_date);
                document.getElementById('activityDuration').value = Math.max(1, HolidayCalendar.countWorkingDays(s, e));
            }

            document.getElementById('activityWeight').value = parseFloat(activity.weight) || 0;
            document.getElementById('activityStatus').value = activity.status || 'not_started';
            document.getElementById('activityProgress').value = parseFloat(activity.progress_percentage) || 0;

            updateAutoStatus();
            
            document.getElementById('activityModule').value = activity.module || '';
            document.getElementById('activityObject').value = activity.object || '';
            document.getElementById('activityComplexity').value = activity.complexity || '';
            document.getElementById('activityReceiveType').value = activity.receive_type || '';
            document.getElementById('activityNewRequirement').checked = activity.new_requirement || false;
            document.getElementById('activityDeliverable').value = activity.deliverable || '';
            document.getElementById('activityIsGolive').checked = activity.is_golive || false;
            
            form.classList.remove('opacity-50', 'pointer-events-none');
            document.getElementById('activityName').focus();
        })
        .catch(error => {
            console.error('❌ Error loading activity:', error);
            form.classList.remove('opacity-50', 'pointer-events-none');
            
            closeActivityModal();
            
            const errorMsg = error.response?.data?.message || error.message;
                showNotification('Failed to load activity: ' + errorMsg, 'error');
        });
}

/**
 * ✅ SUPER FIXED: Save Activity dengan comprehensive error handling
 */
window.saveActivity = function(event) {
    event.preventDefault();

    setSubmitButtonLoading();

    // Get dates in Indonesian format and convert to ISO
    const startDateIndo = document.getElementById('activityStartDate').value;
    const endDateIndo = document.getElementById('activityEndDate').value;
    const startDateIso = indonesianToIso(startDateIndo);
    const endDateIso = indonesianToIso(endDateIndo);

    const actualStartIndo = document.getElementById('activityActualStart').value;
    const actualEndIndo = document.getElementById('activityActualEnd').value;

    const formData = {
        stage_id: isGroupDirectActivity ? null : currentStageId,
        parent_id: currentGroupId,
        is_group: false,
        name: document.getElementById('activityName').value,
        start_date: startDateIso,
        end_date: endDateIso,
        actual_start_date: actualStartIndo ? indonesianToIso(actualStartIndo) : null,
        actual_end_date: actualEndIndo ? indonesianToIso(actualEndIndo) : null,
        weight: parseFloat(document.getElementById('activityWeight').value) || 0,

        module: document.getElementById('activityModule')?.value || null,
        object: document.getElementById('activityObject')?.value || null,
        complexity: document.getElementById('activityComplexity')?.value || null,
        receive_type: document.getElementById('activityReceiveType')?.value || null,
        new_requirement: document.getElementById('activityNewRequirement')?.checked || false,
        deliverable: document.getElementById('activityDeliverable')?.value || null,
        is_golive: document.getElementById('activityIsGolive')?.checked || false,
    };

    // ✅ For group-direct activities, include phase_id since stage_id is null
    if (isGroupDirectActivity && currentPhaseIdForActivity) {
        formData.phase_id = currentPhaseIdForActivity;
    }

    // Validate dates
    const startDate = parseIndonesianDate(startDateIndo);
    const endDate = parseIndonesianDate(endDateIndo);

    if (endDate < startDate) {
        resetSubmitButton();
        showNotification('End date must be after start date', 'error');
        return;
    }

    // Validate phase weight limit
    if (phaseWeightLimit > 0) {
        const thisWeight = parseFloat(formData.weight) || 0;
        if ((phaseWeightUsed + thisWeight) > phaseWeightLimit + 0.01) {
            resetSubmitButton();
            showNotification(
                `Bobot melebihi batas fase (${phaseWeightLimit}%). Sudah terpakai: ${phaseWeightUsed.toFixed(1)}%, sisa: ${Math.max(0, phaseWeightLimit - phaseWeightUsed).toFixed(1)}%.`,
                'error'
            );
            return;
        }
    }

    if (activityFormMode === 'edit') {
        // Status is auto-computed (read from hidden input updated by updateAutoStatus)
        formData.status = document.getElementById('activityStatus')?.value;
        formData.progress_percentage = parseFloat(document.getElementById('activityProgress')?.value) || 0;

        // Validate: progress 100 requires actual_end_date
        if (formData.progress_percentage >= 100 && !actualEndIndo) {
            resetSubmitButton();
            showNotification('Actual End Date wajib diisi saat progress 100%.', 'error');
            document.getElementById('activityActualEnd')?.focus();
            return;
        }
    }
    
    
    let url, method;
    if (activityFormMode === 'create') {
        url = `/planning/${window.projectId}/activities`;
        method = 'POST';
    } else {
        // ✅ FIX: Add ?type=activity to ensure correct table is updated
        url = `/planning/${window.projectId}/activities/${currentActivityId}?type=activity`;
        method = 'PUT';
    }
    
    
    const timeoutId = setTimeout(() => {
        console.warn('⏱️ Request timeout - resetting button');
        resetSubmitButton();
        if (typeof showNotification === 'function') {
            showNotification('Request is taking too long. Please try again.', 'warning');
        }
    }, 30000);
    
    axios({ method: method, url: url, data: formData })
        .then(response => {
            clearTimeout(timeoutId);
            
            
            if (typeof showNotification === 'function') {
                showNotification('Activity saved successfully', 'success');
            }

            closeActivityModal();

            setTimeout(() => window.location.reload(), 500);
        })
        .catch(error => {
            clearTimeout(timeoutId);
            
            console.error('❌ Error saving activity:', error);
            console.error('Response:', error.response?.data);
            
            resetSubmitButton();
            
            let errorMsg = 'Failed to save activity';
            
            if (error.response) {
                if (error.response.data?.message) {
                    errorMsg = error.response.data.message;
                } else if (error.response.data?.errors) {
                    const errors = Object.values(error.response.data.errors).flat();
                    errorMsg = errors.join(', ');
                } else if (error.response.statusText) {
                    errorMsg = error.response.statusText;
                }
                
                console.error('Server Error Details:', {
                    status: error.response.status,
                    data: error.response.data,
                    headers: error.response.headers
                });
            } else if (error.request) {
                errorMsg = 'No response from server. Please check your connection.';
                console.error('No Response:', error.request);
            } else {
                errorMsg = error.message;
                console.error('Error:', error.message);
            }
            
            showNotification(errorMsg, 'error');
        });
};


// ============================================================================
// ✅ TEAM MEMBER ASSIGNMENT FUNCTIONS
// ============================================================================

let projectTeamMembers = [];
let assignedActivityMembers = [];

/**
 * Toggle team member fields visibility
 */
function toggleTeamMemberFields() {
    const fields = document.getElementById('teamMemberFields');
    const icon = document.getElementById('teamMemberFieldsIcon');

    if (fields && icon) {
        fields.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }
}

/**
 * Load project team members for dropdown
 */
async function loadProjectTeamMembers() {
    try {
        const response = await axios.get(`/projects/${window.projectId}/team-members`);
        projectTeamMembers = response.data.team_members || response.data || [];
        updateMemberDropdown();
    } catch (error) {
        console.error('❌ Error loading team members:', error);
        projectTeamMembers = [];
    }
}

/**
 * Load assigned members for an activity
 */
async function loadAssignedMembers(activityId) {
    try {
        const response = await axios.get(`/planning/${window.projectId}/activities/${activityId}/members`);
        // ✅ FIX: API returns { success: true, data: [...] }
        assignedActivityMembers = response.data.data || response.data.members || response.data || [];
        renderAssignedMembers();
        updateMemberDropdown();
    } catch (error) {
        console.error('❌ Error loading assigned members:', error);
        assignedActivityMembers = [];
        renderAssignedMembers();
    }
}

/**
 * Update member dropdown (exclude already assigned members)
 */
function updateMemberDropdown() {
    const select = document.getElementById('activityMemberSelect');
    if (!select) return;

    const assignedIds = assignedActivityMembers.map(m => String(m.employee_id));

    select.innerHTML = '<option value="">Select team member...</option>';

    // Show every team-member entry (one per role), e.g. someone who is both
    // Project Manager AND an MM Member appears twice. Label = Name - Role - Module.
    // Role is carried on the option so a separate role field is no longer needed.
    projectTeamMembers.forEach(member => {
        if (assignedIds.includes(String(member.employee_id))) return;

        const name   = getEmployeeName(member);
        const role   = member.role || member.pivot?.role || '';
        const module = member.module || member.pivot?.module || '';

        const labelParts = [name];
        if (role)   labelParts.push(role);
        if (module) labelParts.push(module);
        const label = labelParts.join(' - ');

        const opt = document.createElement('option');
        opt.value = member.employee_id;
        opt.textContent = label;
        opt.dataset.role   = role;
        opt.dataset.module = module;
        opt.dataset.name   = name;
        select.appendChild(opt);
    });
}

/**
 * Render assigned members list
 */
function renderAssignedMembers() {
    const container = document.getElementById('assignedMembersList');
    if (!container) return;

    if (assignedActivityMembers.length === 0) {
        container.innerHTML = '<p class="text-sm text-gray-500 italic">No team members assigned yet</p>';
        return;
    }

    const roleColors = {
        'lead': 'bg-purple-100 text-purple-800',
        'member': 'bg-blue-100 text-blue-800',
        'reviewer': 'bg-orange-100 text-orange-800',
        'support': 'bg-green-100 text-green-800',
    };

    container.innerHTML = assignedActivityMembers.map(member => {
        const name = getEmployeeName(member);
        // ✅ FIX: API returns role directly, not in pivot
        const role = member.role || member.pivot?.role || 'member';
        const roleClass = roleColors[role.toLowerCase()] || 'bg-gray-100 text-gray-800';
        const duration = member.duration ?? member.pivot?.duration ?? null;

        return `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded-md border">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-gray-600">${name.charAt(0).toUpperCase()}</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">${name}</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${roleClass}">
                            ${role}
                        </span>
                        ${duration ? `<span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">${duration} day${duration > 1 ? 's' : ''}</span>` : ''}
                    </div>
                </div>
                <button type="button" onclick="removeActivityMember(${member.employee_id})"
                        class="text-red-600 hover:text-red-800 p-1 rounded hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
    }).join('');
}

/**
 * Add team member to activity
 */
async function addActivityMember() {
    const select = document.getElementById('activityMemberSelect');
    const durationInput = document.getElementById('activityMemberDuration');

    const employeeId = select?.value;
    // Role comes from the selected team-member entry (no separate role field).
    const opt = select?.options[select.selectedIndex];
    const role = opt?.dataset.role || 'Member';
    const name = opt?.dataset.name || getEmployeeName({ employee_id: employeeId });
    const module = opt?.dataset.module || '';

    if (!employeeId) {
        showNotification('Please select a team member', 'warning');
        return;
    }

    // Validate member duration ≤ activity duration
    const durationRaw = durationInput?.value;
    let duration = null;
    if (durationRaw !== '' && durationRaw != null) {
        duration = parseInt(durationRaw, 10);
        if (isNaN(duration) || duration < 1) {
            showNotification('Duration must be a positive number of working days.', 'warning');
            return;
        }
        const activityDuration = parseInt(document.getElementById('activityDuration')?.value, 10) || 0;
        if (activityDuration > 0 && duration > activityDuration) {
            showNotification(`Member duration (${duration} days) cannot exceed the activity duration (${activityDuration} days).`, 'error');
            return;
        }
    }

    if (!currentActivityId) {
        assignedActivityMembers.push({
            employee_id: employeeId,
            name: name,
            role: role,
            module: module,
            duration: duration,
        });
        renderAssignedMembers();
        updateMemberDropdown();
        select.value = '';
        if (durationInput) durationInput.value = '';
        showNotification('Member added. Save activity to confirm.', 'info');
        return;
    }

    try {
        const response = await axios.post(`/planning/${window.projectId}/activities/${currentActivityId}/members`, {
            employee_id: employeeId,
            role: role,
            duration: duration,
        });

        showNotification('Team member assigned successfully', 'success');

        await loadAssignedMembers(currentActivityId);

        select.value = '';
        if (durationInput) durationInput.value = '';
    } catch (error) {
        console.error('❌ Error assigning member:', error);
        const msg = error.response?.data?.message || 'Failed to assign member';
        showNotification(msg, 'error');
    }
}

/**
 * Remove team member from activity
 */
let _pendingRemoveMemberId = null;

/**
 * Open the confirmation modal for removing a member (consistent with other modals)
 */
function removeActivityMember(employeeId) {
    _pendingRemoveMemberId = employeeId;

    const member = assignedActivityMembers.find(m => String(m.employee_id) === String(employeeId));
    const nameEl = document.getElementById('removeMemberName');
    if (nameEl) nameEl.textContent = member ? getEmployeeName(member) : 'this member';

    document.getElementById('activityMemberDeleteModal')?.classList.remove('hidden');
}

function closeRemoveMemberModal() {
    document.getElementById('activityMemberDeleteModal')?.classList.add('hidden');
    _pendingRemoveMemberId = null;
}

async function confirmRemoveActivityMember() {
    const employeeId = _pendingRemoveMemberId;
    if (employeeId === null || employeeId === undefined) return;

    // Unsaved activity: just drop from the local list
    if (!currentActivityId) {
        assignedActivityMembers = assignedActivityMembers.filter(m => String(m.employee_id) !== String(employeeId));
        renderAssignedMembers();
        updateMemberDropdown();
        closeRemoveMemberModal();
        return;
    }

    const btn = document.getElementById('confirmRemoveMemberBtn');
    const orig = btn?.innerHTML;
    if (btn) { btn.disabled = true; btn.innerHTML = 'Removing…'; }

    try {
        await axios.post(`/planning/${window.projectId}/activities/${currentActivityId}/members/${employeeId}/delete`, {});

        showNotification('Team member removed', 'success');
        await loadAssignedMembers(currentActivityId);
        closeRemoveMemberModal();
    } catch (error) {
        console.error('❌ Error removing member:', error);
        const msg = error.response?.data?.message || 'Failed to remove member';
        showNotification(msg, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = orig; }
    }
}

/**
 * Reset team member section
 */
function resetTeamMemberSection() {
    assignedActivityMembers = [];
    renderAssignedMembers();
    updateMemberDropdown();

    const select = document.getElementById('activityMemberSelect');
    const durationInput = document.getElementById('activityMemberDuration');
    if (select) select.value = '';
    if (durationInput) durationInput.value = '';
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.projectId) {
        loadProjectTeamMembers();
    }
    // Wire duration input listener (input element is in markup, listener added once)
    const dur = document.getElementById('activityDuration');
    if (dur) dur.addEventListener('input', onActivityDurationChange);
});

</script>

<style>
@media (max-width: 640px) {
    #activityModal .inline-block {
        margin: 1rem;
        max-height: calc(100vh - 2rem);
    }
    
    #activityModal input,
    #activityModal select,
    #activityModal textarea {
        font-size: 16px;
    }
}

#extendedFields {
    transition: all 0.3s ease;
}

#extendedFieldsIcon {
    transition: transform 0.3s ease;
}
</style>