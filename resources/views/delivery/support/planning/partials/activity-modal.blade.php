{{-- ============================================================================ --}}
{{-- ACTIVITY MODAL - VANILLA JAVASCRIPT VERSION --}}
{{-- ============================================================================ --}}
<div id="activityModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg md:max-w-2xl lg:max-w-4xl flex flex-col max-h-[90vh]">
            <!-- Header -->
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

            <!-- Form Body -->
            <div class="px-4 sm:px-6 py-4 sm:py-5 overflow-y-auto flex-1">
                <form id="activityForm" onsubmit="saveActivity(event)">
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

                        <!-- Phase Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Phase <span class="text-red-500">*</span>
                            </label>
                            <select id="activityPhase" required
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                                <option value="">Select Phase</option>
                                @foreach($verticalPhases as $phase)
                                    <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Planned Dates -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Planned Start Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="activityStartDate" required
                                    placeholder="dd/mm/yyyy"
                                    pattern="\d{2}/\d{2}/\d{4}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Planned End Date <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="activityEndDate" required
                                    placeholder="dd/mm/yyyy"
                                    pattern="\d{2}/\d{2}/\d{4}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Actual Dates (Edit mode only) -->
                        <div id="activityActualDates" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual Start Date
                                </label>
                                <input type="text" id="activityActualStart"
                                    placeholder="dd/mm/yyyy"
                                    pattern="\d{2}/\d{2}/\d{4}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Actual End Date
                                </label>
                                <input type="text" id="activityActualEnd"
                                    placeholder="dd/mm/yyyy"
                                    pattern="\d{2}/\d{2}/\d{4}"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Weight, Status, Progress -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Weight (%)</label>
                                <input type="number" id="activityWeight"
                                    min="0" max="100" step="0.1" value="0"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                            <div id="activityStatusField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select id="activityStatus"
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
                                <input type="number" id="activityProgress"
                                    min="0" max="100" step="1" value="0"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base">
                            </div>
                        </div>

                        <!-- Module & Object -->
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

                        <!-- Incident Type & Complexity -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Incident Type</label>
                                <select id="activityIncidentType"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <option value="">Select type</option>
                                    <option value="Bug">Bug</option>
                                    <option value="Enhancement">Enhancement</option>
                                    <option value="Configuration">Configuration</option>
                                    <option value="Training">Training</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Complexity</label>
                                <select id="activityComplexity"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm">
                                    <option value="">Select complexity</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>

                        <!-- Description & Deliverable -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea id="activityDescription" rows="3"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                placeholder="Enter activity description..."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Deliverable</label>
                            <textarea id="activityDeliverable" rows="2"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 text-sm"
                                placeholder="Expected deliverables..."></textarea>
                        </div>

                        <!-- New Issue Checkbox -->
                        <div class="flex items-center">
                            <input type="checkbox" id="activityNewIssue"
                                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                            <label for="activityNewIssue" class="ml-2 block text-sm text-gray-700">New Issue</label>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                <button type="button" onclick="closeActivityModal()"
                    class="px-5 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">
                    Cancel
                </button>
                <button type="submit" id="activitySubmitBtn" onclick="saveActivity(event)"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Save Activity
                </button>
            </div>
        </div>
</div>


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

/* Date picker styling */
input[type="date"] {
    font-size: 16px;
}

/* Modal transitions */
#activityModal {
    transition: opacity 0.3s ease;
}
#activityModal .inline-block {
    animation: modalSlideIn 0.3s ease-out;
}
@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>


<script>
// ============================================================================
// ACTIVITY MODAL - VANILLA JAVASCRIPT FUNCTIONS
// ============================================================================

/**
 * ✅ Date Format Helpers (dd/mm/yyyy - Indonesian format)
 */
function isoToIndonesian(isoDate) {
    if (!isoDate) return '';
    const parts = isoDate.split(' ')[0].split('-');
    if (parts.length !== 3) return isoDate;
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function indonesianToIso(indoDate) {
    if (!indoDate) return '';
    const parts = indoDate.split('/');
    if (parts.length !== 3) return indoDate;
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
}

function getTodayIndonesian() {
    const today = new Date();
    const day = String(today.getDate()).padStart(2, '0');
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = today.getFullYear();
    return `${day}/${month}/${year}`;
}

function getDateFromNowIndonesian(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    return `${day}/${month}/${year}`;
}

function parseIndonesianDate(indoDate) {
    if (!indoDate) return null;
    const parts = indoDate.split('/');
    if (parts.length !== 3) return null;
    return new Date(parseInt(parts[2]), parseInt(parts[1]) - 1, parseInt(parts[0]));
}

/**
 * ✅ Open Activity Modal
 */
window.openActivityModal = function(activityId = null) {
    
    const modal = document.getElementById('activityModal');
    const form = document.getElementById('activityForm');
    
    if (!modal || !form) {
        console.error('❌ Activity modal elements not found');
        return;
    }
    
    // Reset form
    form.reset();
    resetSubmitButton();
    
    // Set default dates
    document.getElementById('activityStartDate').value = getTodayIndonesian();
    document.getElementById('activityEndDate').value = getDateFromNowIndonesian(7);
    
    // Clear all fields
    document.getElementById('activityName').value = '';
    document.getElementById('activityPhase').value = '';
    document.getElementById('activityWeight').value = '0';
    document.getElementById('activityModule').value = '';
    document.getElementById('activityObject').value = '';
    document.getElementById('activityIncidentType').value = '';
    document.getElementById('activityComplexity').value = '';
    document.getElementById('activityDescription').value = '';
    document.getElementById('activityDeliverable').value = '';
    document.getElementById('activityNewIssue').checked = false;
    
    // Hide edit-only fields
    document.getElementById('activityActualDates').classList.add('hidden');
    document.getElementById('activityStatusField').classList.add('hidden');
    document.getElementById('activityProgressField').classList.add('hidden');
    
    // Set title
    document.getElementById('activityModalTitle').textContent = 'Add New Activity';
    
    // Load activity data if editing
    if (activityId && activityId !== 'null' && activityId !== 'undefined') {
        loadActivityData(activityId);
    }
    
    // Show modal
    modal.classList.remove('hidden');
    document.getElementById('activityName').focus();
};

/**
 * ✅ Close Activity Modal
 */
window.closeActivityModal = function() {
    const modal = document.getElementById('activityModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    resetSubmitButton();
};

/**
 * ✅ Load Activity Data for Edit Mode
 */
function loadActivityData(activityId) {
    
    const form = document.getElementById('activityForm');
    form.classList.add('opacity-50', 'pointer-events-none');
    
    axios.get(`/delivery/support/{{ $support->id }}/activities/${activityId}`)
        .then(response => {
            const activity = response.data;
            
            // Set form values
            document.getElementById('activityName').value = activity.name || '';
            document.getElementById('activityPhase').value = activity.delivery_support_phase_id || '';
            document.getElementById('activityStartDate').value = isoToIndonesian(activity.start_date);
            document.getElementById('activityEndDate').value = isoToIndonesian(activity.end_date);
            document.getElementById('activityActualStart').value = isoToIndonesian(activity.actual_start_date);
            document.getElementById('activityActualEnd').value = isoToIndonesian(activity.actual_end_date);
            document.getElementById('activityWeight').value = parseFloat(activity.weight) || 0;
            document.getElementById('activityStatus').value = activity.status || 'not_started';
            document.getElementById('activityProgress').value = parseFloat(activity.progress_percentage) || 0;
            document.getElementById('activityModule').value = activity.module || '';
            document.getElementById('activityObject').value = activity.object || '';
            document.getElementById('activityIncidentType').value = activity.incident_type || '';
            document.getElementById('activityComplexity').value = activity.complexity || '';
            document.getElementById('activityDescription').value = activity.description || '';
            document.getElementById('activityDeliverable').value = activity.deliverable || '';
            document.getElementById('activityNewIssue').checked = activity.new_issue || false;
            
            // Show edit-only fields
            document.getElementById('activityActualDates').classList.remove('hidden');
            document.getElementById('activityStatusField').classList.remove('hidden');
            document.getElementById('activityProgressField').classList.remove('hidden');
            
            // Update title
            document.getElementById('activityModalTitle').textContent = 'Edit Activity';
            
            form.classList.remove('opacity-50', 'pointer-events-none');
        })
        .catch(error => {
            console.error('❌ Error loading activity:', error);
            form.classList.remove('opacity-50', 'pointer-events-none');
            closeActivityModal();
            if (typeof showNotification === 'function') {
                showNotification('Failed to load activity: ' + (error.response?.data?.message || error.message), 'error');
            }
        });
}

/**
 * ✅ Save Activity
 */
window.saveActivity = function(event) {
    event.preventDefault();
    
    setSubmitButtonLoading();
    
    // Get form values
    const activityName = document.getElementById('activityName').value.trim();
    const phaseId = document.getElementById('activityPhase').value;
    const startDateIndo = document.getElementById('activityStartDate').value;
    const endDateIndo = document.getElementById('activityEndDate').value;
    
    // Validation
    if (!activityName) {
        resetSubmitButton();
        if (typeof showNotification === 'function') {
            showNotification('Activity name is required', 'error');
        }
        return;
    }
    
    if (!phaseId) {
        resetSubmitButton();
        if (typeof showNotification === 'function') {
            showNotification('Please select a phase', 'error');
        }
        return;
    }
    
    if (!startDateIndo || !endDateIndo) {
        resetSubmitButton();
        if (typeof showNotification === 'function') {
            showNotification('Start date and end date are required', 'error');
        }
        return;
    }
    
    // Validate dates
    const startDate = parseIndonesianDate(startDateIndo);
    const endDate = parseIndonesianDate(endDateIndo);
    
    if (endDate < startDate) {
        resetSubmitButton();
        if (typeof showNotification === 'function') {
            showNotification('End date must be after start date', 'error');
        }
        return;
    }
    
    // Convert dates to ISO format
    const startDateIso = indonesianToIso(startDateIndo);
    const endDateIso = indonesianToIso(endDateIndo);
    const actualStartIso = document.getElementById('activityActualStart').value 
        ? indonesianToIso(document.getElementById('activityActualStart').value) 
        : null;
    const actualEndIso = document.getElementById('activityActualEnd').value 
        ? indonesianToIso(document.getElementById('activityActualEnd').value) 
        : null;
    
    // Prepare data
    const formData = {
        name: activityName,
        delivery_support_phase_id: parseInt(phaseId),
        start_date: startDateIso,
        end_date: endDateIso,
        actual_start_date: actualStartIso,
        actual_end_date: actualEndIso,
        weight: parseFloat(document.getElementById('activityWeight').value) || 0,
        module: document.getElementById('activityModule').value || null,
        object: document.getElementById('activityObject').value || null,
        incident_type: document.getElementById('activityIncidentType').value || null,
        complexity: document.getElementById('activityComplexity').value || null,
        status: document.getElementById('activityStatus').value || 'not_started',
        progress_percentage: parseFloat(document.getElementById('activityProgress').value) || 0,
        description: document.getElementById('activityDescription').value || null,
        deliverable: document.getElementById('activityDeliverable').value || null,
        new_issue: document.getElementById('activityNewIssue').checked,
        _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
    };
    
    
    // Determine URL and method
    const activityId = window.currentActivityId;
    let url, method;
    
    if (activityId && activityId !== 'null' && activityId !== 'undefined') {
        url = `/delivery/support/{{ $support->id }}/activities/${activityId}`;
        method = 'PUT';
    } else {
        url = `/delivery/support/{{ $support->id }}/activities`;
        method = 'POST';
    }
    
    
    // Send request
    axios({ method: method, url: url, data: formData })
        .then(response => {
            if (typeof showNotification === 'function') {
                showNotification('Activity saved successfully', 'success');
            }
            closeActivityModal();
            setTimeout(() => window.location.reload(), 500);
        })
        .catch(error => {
            console.error('❌ Error saving activity:', error);
            resetSubmitButton();
            
            let errorMsg = 'Failed to save activity';
            if (error.response) {
                if (error.response.data?.message) {
                    errorMsg = error.response.data.message;
                } else if (error.response.data?.errors) {
                    const errors = Object.values(error.response.data.errors).flat();
                    errorMsg = errors.join(', ');
                }
            }
            
            if (typeof showNotification === 'function') {
                showNotification(errorMsg, 'error');
            }
        });
};

/**
 * ✅ Helper: Reset Submit Button
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
 * ✅ Helper: Set Submit Button to Loading State
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

</script>