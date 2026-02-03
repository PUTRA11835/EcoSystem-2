{{-- ✅ SUPER FIXED ACTIVITY MODAL - Better Error Handling --}}
<div id="activityModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeActivityModal()"></div>

        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full sm:max-w-lg md:max-w-2xl lg:max-w-4xl">
            <form id="activityForm" onsubmit="saveActivity(event)">
                {{-- Header - SAMA --}}
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-4 sm:px-6 py-3 sm:py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-white bg-opacity-20">
                                <svg class="h-5 w-5 sm:h-6 sm:w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-base sm:text-lg font-semibold text-white" id="activityModalTitle">
                                    Add Activity
                                </h3>
                                <p class="text-xs sm:text-sm text-indigo-100 mt-0.5" id="activityModalSubtitle">Stage Name</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeActivityModal()" class="text-white hover:text-gray-200 transition">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Form Body - SAMA SEPERTI SEBELUMNYA --}}
                <div class="bg-white px-4 sm:px-6 py-4 sm:py-5 max-h-[60vh] sm:max-h-[70vh] overflow-y-auto">
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

                        <!-- Planned Dates -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Planned Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="activityStartDate" required 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Planned End Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="activityEndDate" required 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- ✅ NEW: Actual Dates (Edit mode only) -->
                        <div id="activityActualDates" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual Start Date
                                </label>
                                <input type="date" id="activityActualStart" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual End Date
                                </label>
                                <input type="date" id="activityActualEnd" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Weight, Status, Progress -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Weight (%)</label>
                                <input type="number" id="activityWeight" required 
                                    min="0" max="100" step="0.1" value="10"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div id="activityStatusField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select id="activityStatus" required
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                                    <option value="not_started">Not Started</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="monitoring">Monitoring</option>
                                    <option value="completed">Completed</option>
                                    <option value="delayed">Delayed</option>
                                </select>
                            </div>
                            <div id="activityProgressField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Progress (%)</label>
                                <input type="number" id="activityProgress" required 
                                    min="0" max="100" step="1" value="0"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Module</label>
                                <input type="text" id="activityModule" 
                                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                                       placeholder="e.g., FI, MM, SD...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Object</label>
                                <input type="text" id="activityTcode" 
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

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Functional Sinergi</label>
                                        <input type="text" id="activityFunctionalSinergi" 
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                               placeholder="Functional synergy...">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Technical Sinergi</label>
                                        <input type="text" id="activityTechnicalSinergi" 
                                               class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                               placeholder="Technical synergy...">
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
                                    <select id="activityMemberRole"
                                            class="w-full sm:w-32 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        <option value="">Role</option>
                                        <option value="lead">Lead</option>
                                        <option value="member">Member</option>
                                        <option value="reviewer">Reviewer</option>
                                        <option value="support">Support</option>
                                    </select>
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

                        <div class="bg-blue-50 border border-blue-200 rounded-md p-3">
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-blue-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <div class="ml-3 flex-1">
                                    <p class="text-xs sm:text-sm text-blue-700">
                                        💡 <strong>Weight Distribution:</strong> Total weight of all activities in this stage should equal 100% for accurate progress calculation.
                                    </p>
                                    <p class="text-xs text-blue-600 mt-1" id="weightInfo">Current stage weight: Calculating...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="bg-gray-50 px-4 sm:px-6 py-3 sm:py-4 flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                    <button type="button" onclick="closeActivityModal()" 
                            class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <button type="submit" id="activitySubmitBtn"
                            class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        💾 Save Activity
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ✅ SUPER FIXED JAVASCRIPT --}}
<script>
// ============================================================================
// ✅ SUPER FIXED - Better Error Handling & Recovery
// ============================================================================

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
            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            💾 Save Activity
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

window.openActivityModal = function(stageId, groupId, activityId = null) {
    console.log('📝 Opening activity modal:', { stageId, groupId, activityId });
    
    if (!stageId || stageId === 'null' || stageId === 'undefined') {
        console.error('❌ Invalid stageId:', stageId);
        if (typeof showNotification === 'function') {
            showNotification('Error: Invalid stage ID', 'error');
        }
        return;
    }
    
    currentStageId = stageId;
    currentGroupId = groupId;
    
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
    
    // Reset team member section
    resetTeamMemberSection();

    // Collapse team member fields by default
    const teamMemberFields = document.getElementById('teamMemberFields');
    const teamMemberIcon = document.getElementById('teamMemberFieldsIcon');
    if (teamMemberFields) teamMemberFields.classList.add('hidden');
    if (teamMemberIcon) teamMemberIcon.classList.remove('rotate-180');

    if (activityId && activityId !== 'null' && activityId !== 'undefined') {
        activityFormMode = 'edit';
        currentActivityId = activityId;

        document.getElementById('activityModalTitle').textContent = 'Edit Activity';
        document.getElementById('activityStatusField').classList.remove('hidden');
        document.getElementById('activityProgressField').classList.remove('hidden');
        document.getElementById('activityActualDates').classList.remove('hidden'); // ✅ SHOW ACTUAL DATES

        loadActivityData(activityId);

        // Load assigned members for this activity
        loadAssignedMembers(activityId);
    } else {
        activityFormMode = 'create';
        currentActivityId = null;

        document.getElementById('activityModalTitle').textContent = 'Add New Activity';
        document.getElementById('activityStatusField').classList.add('hidden');
        document.getElementById('activityProgressField').classList.add('hidden');
        document.getElementById('activityActualDates').classList.add('hidden'); // ✅ HIDE ACTUAL DATES

        const today = new Date().toISOString().split('T')[0];
        document.getElementById('activityStartDate').value = today;

        const nextWeek = new Date();
        nextWeek.setDate(nextWeek.getDate() + 7);
        document.getElementById('activityEndDate').value = nextWeek.toISOString().split('T')[0];

        // Update dropdown with all team members
        updateMemberDropdown();
    }
    
    loadStageInfo(stageId);
    
    modal.classList.remove('hidden');
    document.getElementById('activityName').focus();
};

window.closeActivityModal = function() {
    console.log('❌ Closing activity modal');
    const modal = document.getElementById('activityModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    
    resetSubmitButton(); // ✅ Reset button when closing
    
    activityFormMode = 'create';
    currentActivityId = null;
    currentStageId = null;
    currentGroupId = null;
};

function loadStageInfo(stageId) {
    axios.get(`/planning/${window.projectId}/stages/${stageId}`)
        .then(response => {
            const stage = response.data.data || response.data;
            document.getElementById('activityModalSubtitle').textContent = `Stage: ${stage.name}`;
            updateWeightInfo(stage);
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
    
    console.log('📥 Loading activity data for ID:', activityId);

    axios.get(`/planning/${window.projectId}/activities/${activityId}`)
        .then(response => {
            const activity = response.data;

            console.log('✅ Activity data loaded:', activity);
            
            document.getElementById('activityName').value = activity.name || '';
            document.getElementById('activityStartDate').value = activity.start_date?.split(' ')[0] || '';
            document.getElementById('activityEndDate').value = activity.end_date?.split(' ')[0] || '';
            
            // ✅ LOAD ACTUAL DATES
            document.getElementById('activityActualStart').value = activity.actual_start_date?.split(' ')[0] || '';
            document.getElementById('activityActualEnd').value = activity.actual_end_date?.split(' ')[0] || '';
            
            document.getElementById('activityWeight').value = parseFloat(activity.weight) || 0;
            document.getElementById('activityStatus').value = activity.status || 'not_started';
            document.getElementById('activityProgress').value = parseFloat(activity.progress_percentage) || 0;
            
            document.getElementById('activityModule').value = activity.module || '';
            document.getElementById('activityTcode').value = activity.tcode || '';
            document.getElementById('activityComplexity').value = activity.complexity || '';
            document.getElementById('activityReceiveType').value = activity.receive_type || '';
            document.getElementById('activityNewRequirement').checked = activity.new_requirement || false;
            document.getElementById('activityFunctionalSinergi').value = activity.functional_sinergi || '';
            document.getElementById('activityTechnicalSinergi').value = activity.technical_sinergi || '';
            document.getElementById('activityDeliverable').value = activity.deliverable || '';
            
            form.classList.remove('opacity-50', 'pointer-events-none');
            document.getElementById('activityName').focus();
        })
        .catch(error => {
            console.error('❌ Error loading activity:', error);
            form.classList.remove('opacity-50', 'pointer-events-none');
            
            closeActivityModal();
            
            if (typeof showNotification === 'function') {
                const errorMsg = error.response?.data?.message || error.message;
                showNotification('Failed to load activity: ' + errorMsg, 'error');
            } else {
                alert('Failed to load activity: ' + (error.response?.data?.message || error.message));
            }
        });
}

/**
 * ✅ SUPER FIXED: Save Activity dengan comprehensive error handling
 */
window.saveActivity = function(event) {
    event.preventDefault();
    console.log('💾 Saving activity...', activityFormMode);
    
    setSubmitButtonLoading();
    
    const formData = {
        stage_id: currentStageId,
        parent_id: currentGroupId,
        is_group: false,
        name: document.getElementById('activityName').value,
        start_date: document.getElementById('activityStartDate').value,
        end_date: document.getElementById('activityEndDate').value,
        weight: parseFloat(document.getElementById('activityWeight').value) || 0,
        
        module: document.getElementById('activityModule')?.value || null,
        tcode: document.getElementById('activityTcode')?.value || null,
        complexity: document.getElementById('activityComplexity')?.value || null,
        receive_type: document.getElementById('activityReceiveType')?.value || null,
        new_requirement: document.getElementById('activityNewRequirement')?.checked || false,
        functional_sinergi: document.getElementById('activityFunctionalSinergi')?.value || null,
        technical_sinergi: document.getElementById('activityTechnicalSinergi')?.value || null,
        deliverable: document.getElementById('activityDeliverable')?.value || null,
    };
    
    if (new Date(formData.end_date) < new Date(formData.start_date)) {
        resetSubmitButton();
        
        if (typeof showNotification === 'function') {
            showNotification('End date must be after start date', 'error');
        } else {
            alert('End date must be after start date');
        }
        return;
    }
    
    if (activityFormMode === 'edit') {
        // ✅ ADD ACTUAL DATES
        const actualStartInput = document.getElementById('activityActualStart');
        const actualEndInput = document.getElementById('activityActualEnd');
        
        formData.actual_start_date = actualStartInput.value || null;
        formData.actual_end_date = actualEndInput.value || null;
        formData.status = document.getElementById('activityStatus')?.value;
        formData.progress_percentage = parseFloat(document.getElementById('activityProgress')?.value) || 0;
        
        console.log('📅 Activity actual dates being sent:', {
            actual_start_date: formData.actual_start_date,
            actual_end_date: formData.actual_end_date
        });
    }
    
    console.log('📤 Sending data:', formData);
    
    let url, method;
    if (activityFormMode === 'create') {
        url = `/planning/${window.projectId}/activities`;
        method = 'POST';
    } else {
        url = `/planning/${window.projectId}/activities/${currentActivityId}`;
        method = 'PUT';
    }
    
    console.log(`  → ${method} ${url}`);
    
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
            
            console.log('✅ Activity saved:', response.data);
            
            if (typeof showNotification === 'function') {
                showNotification('Activity saved successfully', 'success');
            }

            closeActivityModal();

            // Reload page to update both table and progress overview
            console.log('🔄 Reloading page to update all sections...');
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
            
            if (typeof showNotification === 'function') {
                showNotification(errorMsg, 'error');
            } else {
                alert(errorMsg);
            }
        });
};

console.log('✅ Activity modal script loaded (Super Fixed - Auto Recovery)');

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
        console.log('✅ Project team members loaded:', projectTeamMembers);
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
        assignedActivityMembers = response.data.members || response.data || [];
        console.log('✅ Assigned members loaded:', assignedActivityMembers);
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

    const assignedIds = assignedActivityMembers.map(m => m.employee_id);

    select.innerHTML = '<option value="">Select team member...</option>';

    projectTeamMembers.forEach(member => {
        if (!assignedIds.includes(member.employee_id)) {
            const name = member.basic_data?.full_name || member.eci || `Employee #${member.employee_id}`;
            const module = member.pivot?.module ? ` (${member.pivot.module})` : '';
            select.innerHTML += `<option value="${member.employee_id}">${name}${module}</option>`;
        }
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
        const name = member.basic_data?.full_name || member.eci || `Employee #${member.employee_id}`;
        const role = member.pivot?.role || 'member';
        const roleClass = roleColors[role] || 'bg-gray-100 text-gray-800';

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
    const roleSelect = document.getElementById('activityMemberRole');

    const employeeId = select?.value;
    const role = roleSelect?.value || 'member';

    if (!employeeId) {
        showNotification('Please select a team member', 'warning');
        return;
    }

    if (!currentActivityId) {
        // Activity not saved yet, add to local list
        const member = projectTeamMembers.find(m => m.employee_id == employeeId);
        if (member) {
            assignedActivityMembers.push({
                ...member,
                pivot: { role: role }
            });
            renderAssignedMembers();
            updateMemberDropdown();
            select.value = '';
            roleSelect.value = '';
            showNotification('Member added. Save activity to confirm.', 'info');
        }
        return;
    }

    try {
        const response = await axios.post(`/planning/${window.projectId}/activities/${currentActivityId}/members`, {
            employee_id: employeeId,
            role: role
        });

        console.log('✅ Member assigned:', response.data);
        showNotification('Team member assigned successfully', 'success');

        // Reload assigned members
        await loadAssignedMembers(currentActivityId);

        select.value = '';
        roleSelect.value = '';
    } catch (error) {
        console.error('❌ Error assigning member:', error);
        const msg = error.response?.data?.message || 'Failed to assign member';
        showNotification(msg, 'error');
    }
}

/**
 * Remove team member from activity
 */
async function removeActivityMember(employeeId) {
    if (!currentActivityId) {
        // Activity not saved yet, remove from local list
        assignedActivityMembers = assignedActivityMembers.filter(m => m.employee_id != employeeId);
        renderAssignedMembers();
        updateMemberDropdown();
        return;
    }

    if (!confirm('Remove this member from the activity?')) return;

    try {
        await axios.delete(`/planning/${window.projectId}/activities/${currentActivityId}/members/${employeeId}`);

        console.log('✅ Member unassigned');
        showNotification('Team member removed', 'success');

        // Reload assigned members
        await loadAssignedMembers(currentActivityId);
    } catch (error) {
        console.error('❌ Error removing member:', error);
        const msg = error.response?.data?.message || 'Failed to remove member';
        showNotification(msg, 'error');
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
    const roleSelect = document.getElementById('activityMemberRole');
    if (select) select.value = '';
    if (roleSelect) roleSelect.value = '';
}

// Load team members on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.projectId) {
        loadProjectTeamMembers();
    }
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