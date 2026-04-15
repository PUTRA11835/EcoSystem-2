// Calendar Timesheets JavaScript
let timesheets = [];
let filteredTimesheets = [];
let selectedTimesheetId = null;
let deleteTimesheetId = null;
let myTicketsCache = []; // cache for support ticket auto-fill

const TH = 'px-3 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide whitespace-nowrap border-b border-gray-200';

// Default employee thead HTML (saved once DOM is ready)
let defaultTheadHTML = '';

// Support-specific thead
const SUPPORT_THEAD_HTML = `<tr>
    <th class="${TH}" style="min-width:36px;"><input type="checkbox" id="selectAll" class="w-4 h-4 rounded border-gray-300"></th>
    <th class="${TH}" style="min-width:100px;">Date</th>
    <th class="${TH}" style="min-width:55px;">Month</th>
    <th class="${TH}" style="min-width:55px;">Year</th>
    <th class="${TH}" style="min-width:130px;">Name</th>
    <th class="${TH}" style="min-width:130px;">Ticket</th>
    <th class="${TH}" style="min-width:180px;">Description</th>
    <th class="${TH}" style="min-width:120px;">Customer</th>
    <th class="${TH}" style="min-width:80px;">Quota MD</th>
    <th class="${TH}" style="min-width:180px;">Activity</th>
    <th class="${TH}" style="min-width:90px;">MD Consumed</th>
    <th class="${TH}" style="min-width:70px;">On Site</th>
    <th class="${TH}" style="min-width:100px;">Status</th>
</tr>`;
let currentFilters = {
    start_date: null,
    end_date: null,
    status: '',
    activity_type: '',
    type_filter: ''   // '' | 'project' | 'support' | 'office'
};
let itemsPerPage = 20;
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

document.addEventListener('DOMContentLoaded', function() {
    initializeDateFilters();

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
        // Set filter directly — thead already rendered correctly by server for locked types
        currentFilters.type_filter = window.lockedType;
        if (window.lockedType === 'support') {
            const table = document.getElementById('timesheetTable');
            if (table) table.style.minWidth = '1200px';
        }
    }

    // Check if we are in approval mode
    if (window.isApprovalMode) {
        loadSubmittedTimesheets();
        loadApprovalStatistics();
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

// Load statistics for approval mode
async function loadApprovalStatistics() {
    // Stats are computed from the already-loaded timesheets array
    updateStatCards(timesheets);
}

// Render timesheets for approval mode (uses shared renderTimesheetRows)
function renderApprovalTimesheets() {
    renderTimesheetRows();
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
            await loadApprovalStatistics();
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
            await loadApprovalStatistics();
        } else {
            showNotification('Failed to reject timesheet: ' + data.message, 'error');
        }
    } catch (error) {
        console.error('Error rejecting timesheet:', error);
        showNotification('An error occurred while rejecting timesheet', 'error');
    }
}

// Override applyFilters for approval mode
const originalApplyFilters = typeof applyFilters === 'function' ? applyFilters : null;

// ==================== END APPROVAL MODE FUNCTIONS ====================

// Initialize time picker dropdowns
function initializeTimePickers() {
    const startHour = document.getElementById('timesheetStartHour');
    const startMinute = document.getElementById('timesheetStartMinute');
    const endHour = document.getElementById('timesheetEndHour');
    const endMinute = document.getElementById('timesheetEndMinute');

    // Sync dropdowns to hidden inputs
    function updateStartTime() {
        const h = startHour?.value || '08';
        const m = startMinute?.value || '00';
        const hiddenInput = document.getElementById('timesheetStartTime');
        if (hiddenInput) hiddenInput.value = `${h}:${m}`;
        updateDurationDisplay();
    }

    function updateEndTime() {
        const h = endHour?.value || '17';
        const m = endMinute?.value || '00';
        const hiddenInput = document.getElementById('timesheetEndTime');
        if (hiddenInput) hiddenInput.value = `${h}:${m}`;
        updateDurationDisplay();
    }

    function updateDurationDisplay() {
        const startH = parseInt(startHour?.value || '0');
        const startM = parseInt(startMinute?.value || '0');
        const endH = parseInt(endHour?.value || '0');
        const endM = parseInt(endMinute?.value || '0');

        let startMinutes = startH * 60 + startM;
        let endMinutes = endH * 60 + endM;

        // Handle overnight (end before start)
        if (endMinutes < startMinutes) {
            endMinutes += 24 * 60;
        }

        const durationMinutes = endMinutes - startMinutes;
        const hours = Math.floor(durationMinutes / 60);
        const mins = durationMinutes % 60;

        const durationField = document.getElementById('timesheetDuration');
        if (durationField) {
            durationField.value = `${hours}h ${mins}m`;
        }
    }

    if (startHour) startHour.addEventListener('change', updateStartTime);
    if (startMinute) startMinute.addEventListener('change', updateStartTime);
    if (endHour) endHour.addEventListener('change', updateEndTime);
    if (endMinute) endMinute.addEventListener('change', updateEndTime);

    // Set default values (08:00 - 17:00)
    if (startHour) startHour.value = '08';
    if (startMinute) startMinute.value = '00';
    if (endHour) endHour.value = '17';
    if (endMinute) endMinute.value = '00';

    updateStartTime();
    updateEndTime();
}

// Helper to set time picker from HH:mm:ss or HH:mm string
function setTimePicker(type, timeString) {
    if (!timeString) return;

    const parts = timeString.split(':');
    const hour = parts[0] || '00';
    const minute = parts[1] || '00';

    const hourSelect = document.getElementById(`timesheet${type}Hour`);
    const minuteSelect = document.getElementById(`timesheet${type}Minute`);
    const hiddenInput = document.getElementById(`timesheet${type}Time`);

    if (hourSelect) hourSelect.value = hour.padStart(2, '0');

    // Find closest minute (rounded to 5)
    if (minuteSelect) {
        const mins = parseInt(minute);
        const roundedMins = Math.round(mins / 5) * 5;
        minuteSelect.value = String(roundedMins % 60).padStart(2, '0');
    }

    if (hiddenInput) hiddenInput.value = `${hour}:${minute}`;
}

function initializeDateFilters() {
    const today = new Date();
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay());
    const endOfWeek = new Date(startOfWeek);
    endOfWeek.setDate(startOfWeek.getDate() + 6);
    
    const filterStartDate = document.getElementById('filterStartDate');
    const filterEndDate = document.getElementById('filterEndDate');
    
    if (filterStartDate) filterStartDate.value = formatDate(startOfWeek);
    if (filterEndDate) filterEndDate.value = formatDate(endOfWeek);
    
    currentFilters.start_date = formatDate(startOfWeek);
    currentFilters.end_date = formatDate(endOfWeek);
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

    if (selectedType === 'project') {
        // Project type: Activity dropdown (grouped by project) + Presence + Location
        fieldsHTML = `
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Activity <span class="text-red-600">*</span>
                </label>
                <select id="timesheetActivity" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent" onchange="onActivitySelected()">
                    <option value="">Select an Activity</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Only activities assigned to you</p>
                <input type="hidden" id="timesheetProjectId" value="">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Presence <span class="text-red-600">*</span>
                    </label>
                    <select id="timesheetPresence" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <option value="">Select a Presence type</option>
                        <option value="onsite">On-site</option>
                        <option value="remote">Remote</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Lokasi <span class="text-red-600">*</span>
                    </label>
                    <textarea id="timesheetLocation" required rows="1" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none" placeholder="Write location here"></textarea>
                </div>
            </div>
        `;

        if (billableSection) {
            billableSection.classList.remove('hidden');
        }

    } else if (selectedType === 'support') {
        // Support type: Ticket dropdown + auto-fill Customer/Jatah MD + MD Consumed + On Site
        fieldsHTML = `
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Ticket <span class="text-red-600">*</span>
                </label>
                <select id="timesheetTicket" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent" onchange="onSupportTicketSelected(this.value)">
                    <option value="">Select a Ticket</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Customer</label>
                    <input type="text" id="supportCustomer" readonly
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-600"
                        placeholder="Auto-fill from ticket">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Jatah MD</label>
                    <input type="text" id="supportJatahMd" readonly
                        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm bg-gray-50 text-gray-600"
                        placeholder="—">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        MD Consumed <span class="text-red-600">*</span>
                    </label>
                    <input type="number" id="supportMdConsumed" required step="0.1" min="0"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-800 focus:border-transparent"
                        placeholder="e.g. 0.5">
                </div>
                <div class="flex items-end pb-2.5">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="supportOnSite"
                            class="w-4 h-4 text-red-800 border-gray-300 rounded focus:ring-red-800">
                        <span class="text-sm font-semibold text-gray-700">On Site</span>
                    </label>
                </div>
            </div>
        `;

        if (billableSection) {
            billableSection.classList.add('hidden');
        }

    } else if (selectedType === 'office') {
        // ✅ Office type: Only Presence + Location
        fieldsHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Presence <span class="text-red-600">*</span>
                    </label>
                    <select id="timesheetPresence" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent">
                        <option value="">Select a Presence type</option>
                        <option value="onsite">On-site</option>
                        <option value="remote">Remote</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Lokasi <span class="text-red-600">*</span>
                    </label>
                    <textarea id="timesheetLocation" required rows="1" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none" placeholder="Write location here"></textarea>
                </div>
            </div>
        `;
        
        if (billableSection) {
            billableSection.classList.add('hidden');
        }
    }

    // Set the HTML first
    dynamicFieldsContainer.innerHTML = fieldsHTML;

    // Update "Activity Description" label to "Activity" for support type
    const descLabel = document.querySelector('label[for="timesheetDescription"]');
    if (descLabel) {
        descLabel.innerHTML = selectedType === 'support'
            ? 'Activity <span class="text-red-600">*</span>'
            : 'Activity Description <span class="text-red-600">*</span>';
    }
    const timesheetDescEl = document.getElementById('timesheetDescription');
    if (timesheetDescEl) {
        timesheetDescEl.placeholder = selectedType === 'support'
            ? 'Describe what you did in this session'
            : 'Write description activity here';
    }

    // Now load data based on type (after DOM is updated)
    if (selectedType === 'project') {
        loadAllMyActivities();
    } else if (selectedType === 'support') {
        loadTicketsForDropdown();
    }
}

async function loadProjectsForDropdown() {
    try {
        const response = await fetch('/api/projects', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        if (response.ok) {
            const data = await response.json();
            const select = document.getElementById('timesheetProject');

            if (select && data.success && data.data) {
                select.innerHTML = '<option value="">Select a Project Title</option>';
                data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.project_id;
                    option.textContent = project.project_name || `Project #${project.project_id}`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.log('Projects API not available yet');
        const select = document.getElementById('timesheetProject');
        if (select) {
            select.innerHTML = '<option value="">Projects module coming soon</option>';
            select.disabled = true;
        }
    }
}

// ✅ NEW: Load only projects where the logged-in employee is a team member
async function loadMyProjectsForDropdown() {
    try {
        const response = await fetch('/api/timesheets/my-projects', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        if (response.ok) {
            const data = await response.json();
            const select = document.getElementById('timesheetProject');

            if (select && data.success && data.data) {
                select.innerHTML = '<option value="">Select a Project Title</option>';

                if (data.data.length === 0) {
                    select.innerHTML = '<option value="">No projects assigned to you</option>';
                    select.disabled = true;
                    return;
                }

                data.data.forEach(project => {
                    const option = document.createElement('option');
                    option.value = project.id;
                    option.textContent = project.name || `Project #${project.id}`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.log('My Projects API not available yet, falling back to all projects');
        // Fallback to all projects
        loadProjectsForDropdown();
    }
}

// ✅ NEW: Load activities assigned to the logged-in employee for the selected project
async function loadAssignedActivities() {
    const projectSelect = document.getElementById('timesheetProject');
    const activityContainer = document.getElementById('activityFieldContainer');
    const activitySelect = document.getElementById('timesheetActivity');

    if (!projectSelect || !activityContainer || !activitySelect) return;

    const projectId = projectSelect.value;

    if (!projectId) {
        activityContainer.classList.add('hidden');
        activitySelect.innerHTML = '<option value="">Select an Activity</option>';
        activitySelect.required = false;
        return;
    }

    try {
        const response = await fetch(`/api/timesheets/my-activities/${projectId}`, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        if (response.ok) {
            const data = await response.json();

            activitySelect.innerHTML = '<option value="">Select an Activity</option>';

            if (data.success && data.data && data.data.length > 0) {
                activityContainer.classList.remove('hidden');
                activitySelect.required = true;

                data.data.forEach(activity => {
                    const option = document.createElement('option');
                    option.value = activity.id;

                    // Show activity name with phase and status
                    const phaseName = activity.phase?.name || '';
                    const status = activity.status ? ` [${activity.status}]` : '';
                    option.textContent = `${activity.name}${phaseName ? ' - ' + phaseName : ''}${status}`;

                    activitySelect.appendChild(option);
                });
            } else {
                // No activities assigned - show warning but don't require
                activityContainer.classList.remove('hidden');
                activitySelect.innerHTML = '<option value="">No activities assigned to you for this project</option>';
                activitySelect.required = false;
            }
        }
    } catch (error) {
        console.error('Error loading assigned activities:', error);
        activityContainer.classList.add('hidden');
        activitySelect.required = false;
    }
}

// Store activities data for lookup
let allActivitiesData = [];

// Load ALL activities assigned to the logged-in employee (across all projects)
async function loadAllMyActivities() {
    const activitySelect = document.getElementById('timesheetActivity');

    if (!activitySelect) {
        console.error('Activity select element not found');
        return;
    }

    console.log('Loading all my activities...');

    try {
        const response = await fetch('/api/timesheets/my-activities/all', {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        console.log('Response status:', response.status);
        const data = await response.json();
        console.log('Response data:', data);

        if (!response.ok) {
            console.error('API error:', data.message);
            activitySelect.innerHTML = `<option value="">Error: ${data.message || 'Failed to load'}</option>`;
            return;
        }

        activitySelect.innerHTML = '<option value="">Select an Activity</option>';

        if (data.success && data.data && data.data.length > 0) {
            allActivitiesData = data.data;
            console.log('Found activities:', data.data.length);

            // Group activities by project
            const groupedByProject = {};
            data.data.forEach(activity => {
                const projectName = activity.project_name || 'Unknown Project';
                if (!groupedByProject[projectName]) {
                    groupedByProject[projectName] = [];
                }
                groupedByProject[projectName].push(activity);
            });

            // Create optgroups for each project
            Object.keys(groupedByProject).forEach(projectName => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = projectName;

                groupedByProject[projectName].forEach(activity => {
                    const option = document.createElement('option');
                    option.value = activity.id;
                    option.dataset.projectId = activity.delivery_projects_id;

                    // Show activity name with phase/stage and status
                    const phaseName = activity.phase_name || '';
                    const stageName = activity.stage_name || '';
                    const status = activity.status ? ` [${activity.status}]` : '';
                    let label = activity.name;
                    if (phaseName) label += ` - ${phaseName}`;
                    if (stageName) label += ` > ${stageName}`;
                    label += status;

                    option.textContent = label;
                    optgroup.appendChild(option);
                });

                activitySelect.appendChild(optgroup);
            });
        } else {
            console.log('No activities found or data.success is false');
            activitySelect.innerHTML = '<option value="">No activities assigned to you</option>';
        }
    } catch (error) {
        console.error('Error loading all assigned activities:', error);
        activitySelect.innerHTML = '<option value="">Failed to load activities</option>';
    }
}

// Handle activity selection - set the project ID automatically
function onActivitySelected() {
    const activitySelect = document.getElementById('timesheetActivity');
    const projectIdInput = document.getElementById('timesheetProjectId');

    if (!activitySelect || !projectIdInput) return;

    const selectedOption = activitySelect.options[activitySelect.selectedIndex];

    if (selectedOption && selectedOption.dataset.projectId) {
        projectIdInput.value = selectedOption.dataset.projectId;
    } else {
        projectIdInput.value = '';
    }
}

// Load only USER'S tickets (like support.blade.php)
async function loadTicketsForDropdown() {
    try {
        const response = await fetch('/api/tickets/my', {
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
            const select = document.getElementById('timesheetTicket');

            if (select && data.success && data.data) {
                // Cache tickets for auto-fill use
                const activeTickets = data.data.filter(t =>
                    t.status !== 'closed' &&
                    t.status !== 'cancel' &&
                    t.jarvies_status !== 'closed'
                );
                myTicketsCache = activeTickets;

                select.innerHTML = '<option value="">Select a Ticket</option>';

                if (activeTickets.length === 0) {
                    select.innerHTML = '<option value="">No active tickets assigned to you</option>';
                    select.disabled = true;
                    return;
                }

                // Sort by ticket_id descending (newest first)
                activeTickets.sort((a, b) => b.ticket_id - a.ticket_id);

                activeTickets.forEach(ticket => {
                    const option = document.createElement('option');
                    option.value = ticket.ticket_id;
                    const customerName = ticket.customer?.customer_name || 'Unknown';
                    const ticketLabel = ticket.ticket_number || `#${ticket.ticket_id}`;
                    option.textContent = `${ticketLabel} - ${customerName}`;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Error loading tickets:', error);
        const select = document.getElementById('timesheetTicket');
        if (select) {
            select.innerHTML = '<option value="">Failed to load tickets</option>';
            select.disabled = true;
        }
    }
}

// Auto-fill Customer and Jatah MD when a support ticket is selected
async function onSupportTicketSelected(ticketId) {
    const customerEl = document.getElementById('supportCustomer');
    const jatahMdEl  = document.getElementById('supportJatahMd');

    // Clear previous values
    if (customerEl) customerEl.value = '';
    if (jatahMdEl) jatahMdEl.value = '—';

    if (!ticketId) return;

    // Auto-fill customer from cache
    const ticket = myTicketsCache.find(t => String(t.ticket_id) === String(ticketId));
    if (ticket && customerEl) {
        customerEl.value = ticket.customer?.customer_name || '';
    }

    // Fetch latest approved mandays
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const res = await fetch(`/api/tickets/${ticketId}/mandays/approved`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin'
        });
        if (res.ok) {
            const data = await res.json();
            if (jatahMdEl) {
                jatahMdEl.value = data.data ? Number(data.data.total_mandays).toFixed(1) : '—';
            }
        }
    } catch (e) {
        console.error('Failed to fetch approved mandays', e);
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

// Apply status-based client-side filter and re-render
function applyStatusFilter() {
    let result = timesheets;

    // 1. Filter by type tab (project / support / office) — also respect window.lockedType
    const activeType = currentFilters.type_filter || window.lockedType || '';
    if (activeType === 'project') {
        result = result.filter(t => !!t.delivery_projects_id);
    } else if (activeType === 'support') {
        result = result.filter(t => !t.delivery_projects_id && !!t.ticket_id);
    } else if (activeType === 'office') {
        result = result.filter(t => !t.delivery_projects_id && !t.ticket_id);
    }

    // 2. Filter by status or activity_type (from filter bar)
    if (currentFilters.status) {
        result = result.filter(t => t.status === currentFilters.status);
    } else if (currentFilters.activity_type) {
        result = result.filter(t => t.activity_type === currentFilters.activity_type);
    }

    filteredTimesheets = result;
    renderTimesheetRows();
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
            thead.innerHTML = SUPPORT_THEAD_HTML;
            if (table) table.style.minWidth = '1200px';
        } else {
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

    // Sync the filter select
    const filterStatus = document.getElementById('filterStatus');
    if (filterStatus) filterStatus.value = status;

    applyStatusFilter();
}

function resetFilters() {
    const filterStartDate = document.getElementById('filterStartDate');
    const filterEndDate = document.getElementById('filterEndDate');
    const filterStatus = document.getElementById('filterStatus');
    const filterActivityType = document.getElementById('filterActivityType');

    initializeDateFilters();
    if (filterStatus) filterStatus.value = '';
    if (filterActivityType) filterActivityType.value = '';

    currentFilters.status = '';
    currentFilters.activity_type = '';
    currentFilters.type_filter = '';
    currentPage = 1;

    if (window.lockedType) {
        currentFilters.type_filter = window.lockedType;
        applyStatusFilter();
    } else {
        filterByType('');   // reset type tab to All
    }
    filterByStatus(''); // reset active card to Total

    if (window.isApprovalMode) {
        loadSubmittedTimesheets();
    } else {
        loadTimesheets();
    }
}

function renderTimesheets() {
    applyStatusFilter();
}

function renderTimesheetRows() {
    const tbody = document.getElementById('timesheetsTableBody');
    const emptyState = document.getElementById('emptyState');

    if (!tbody) return;

    if (filteredTimesheets.length === 0) {
        tbody.innerHTML = '';
        if (emptyState) emptyState.classList.remove('hidden');
        updatePagination(0);
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');

    // Pagination
    const total = filteredTimesheets.length;
    const start = (currentPage - 1) * itemsPerPage;
    const end = Math.min(start + itemsPerPage, total);
    const pageItems = filteredTimesheets.slice(start, end);
    updatePagination(total, start + 1, end);

    // ── Approval mode: support tab — spreadsheet layout with Employee col ──
    if (window.isApprovalMode && (currentFilters.type_filter === 'support' || window.lockedType === 'support')) {
        tbody.innerHTML = pageItems.map(timesheet => {
            const statusColor = statusColors[timesheet.status] || statusColors.draft;
            const canSelect = timesheet.status === 'submitted';

            const dateObj   = timesheet.date ? new Date(timesheet.date + 'T00:00:00') : null;
            const dateFmt   = dateObj ? dateObj.toLocaleDateString('en-GB', { day:'2-digit', month:'2-digit', year:'numeric' }).replace(/\//g, '/') : '-';
            const period    = dateObj ? getPeriodInfo(dateObj) : null;
            const bulan     = period ? period.month : '-';
            const tahun     = period ? period.year  : '-';
            const nama      = escapeHtml(timesheet.employee_name || '-');
            const tiket     = timesheet.ticket_number ? `#${escapeHtml(timesheet.ticket_number)}` : (timesheet.ticket_id ? `#${timesheet.ticket_id}` : '-');
            const ticketDesc = escapeHtml(timesheet.ticket_description || '-');
            const customer  = escapeHtml(timesheet.customer_name || '-');
            const jatahMd   = timesheet.jatah_md != null ? Number(timesheet.jatah_md).toFixed(1) : '-';
            const aktivitas = escapeHtml(timesheet.description || '-');
            const mdConsumed = timesheet.md_consumed != null ? Number(timesheet.md_consumed).toFixed(1) : '-';
            const onSite    = timesheet.presence === 'onsite' ? 'X' : '';

            return `
                <tr class="hover:bg-purple-50/30 transition-colors ${canSelect ? 'cursor-pointer' : ''}" ${canSelect ? `onclick="toggleRowSelection(event, ${timesheet.id})"` : ''}>
                    <td class="px-3 py-2 border-b border-gray-100">
                        ${canSelect ? `<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="${timesheet.id}" data-status="${timesheet.status}" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">` : `<span class="text-gray-300"><i class="fas fa-lock text-xs" title="${timesheet.status}"></i></span>`}
                    </td>
                    <td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs text-gray-700">${dateFmt}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">${bulan}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">${tahun}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-800 font-medium">${nama}</td>
                    <td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs font-semibold text-purple-700">
                        <i class="fas fa-ticket-alt mr-1 opacity-60"></i>${tiket}
                    </td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-600 max-w-[180px]" title="${escapeHtml(timesheet.ticket_description || '')}">${ticketDesc}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700">${customer}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-semibold text-gray-800">${jatahMd}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700 max-w-[180px]" title="${escapeHtml(timesheet.description || '')}">${aktivitas}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-semibold text-gray-800">${mdConsumed}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-bold text-green-700">${onSite}</td>
                    <td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap">
                        <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full ${statusColor.bg} ${statusColor.text}">
                            ${timesheet.status.charAt(0).toUpperCase() + timesheet.status.slice(1)}
                        </span>
                        ${timesheet.status === 'rejected' && timesheet.rejection_reason ? `<i class="fas fa-info-circle text-yellow-500 ml-1 cursor-pointer text-xs" title="${escapeHtml(timesheet.rejection_reason)}" onclick="event.stopPropagation(); showRejectionReason(${timesheet.id})"></i>` : ''}
                    </td>
                </tr>
            `;
        }).join('');
        return;
    }

    // ── Approval mode: All / Project / Office tabs ────────────────────────
    if (window.isApprovalMode) {
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
                const mdVal = timesheet.md_consumed != null ? Number(timesheet.md_consumed).toFixed(1) : '—';
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
                            ${timesheet.description || '-'}
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
        return;
    }

    // ── Support tab: dedicated spreadsheet-style layout ────────────
    if (currentFilters.type_filter === 'support') {
        tbody.innerHTML = pageItems.map(timesheet => {
            const statusColor = statusColors[timesheet.status] || statusColors.draft;
            const canEdit = ['draft', 'rejected'].includes(timesheet.status);

            const dateObj   = timesheet.date ? new Date(timesheet.date + 'T00:00:00') : null;
            const dateFmt   = dateObj ? dateObj.toLocaleDateString('en-GB', { day:'2-digit', month:'2-digit', year:'numeric' }).replace(/\//g, '/') : '-';
            const period    = dateObj ? getPeriodInfo(dateObj) : null;
            const bulan     = period ? period.month : '-';
            const tahun     = period ? period.year  : '-';
            const nama      = escapeHtml(timesheet.employee_name || '-');
            const tiket     = timesheet.ticket_number ? `#${escapeHtml(timesheet.ticket_number)}` : (timesheet.ticket_id ? `#${timesheet.ticket_id}` : '-');
            const ticketDesc = escapeHtml(timesheet.ticket_description || '-');
            const customer  = escapeHtml(timesheet.customer_name || '-');
            const jatahMd   = timesheet.jatah_md != null ? Number(timesheet.jatah_md).toFixed(1) : '-';
            const aktivitas = escapeHtml(timesheet.description || '-');
            const mdConsumed = timesheet.md_consumed != null ? Number(timesheet.md_consumed).toFixed(1) : '-';
            const onSite    = timesheet.presence === 'onsite' ? 'X' : '';

            return `
                <tr class="hover:bg-purple-50/30 transition-colors ${canEdit ? 'cursor-pointer' : ''}" ${canEdit ? `onclick="toggleRowSelection(event, ${timesheet.id})"` : ''}>
                    <td class="px-3 py-2 border-b border-gray-100">
                        ${canEdit ? `<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="${timesheet.id}" data-status="${timesheet.status}" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">` : `<span class="text-gray-300"><i class="fas fa-lock text-xs" title="Cannot edit (${timesheet.status})"></i></span>`}
                    </td>
                    <td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs text-gray-700">${dateFmt}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">${bulan}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs text-gray-700">${tahun}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-800 font-medium">${nama}</td>
                    <td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap text-xs font-semibold text-purple-700">
                        <i class="fas fa-ticket-alt mr-1 opacity-60"></i>${tiket}
                    </td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-600 max-w-[180px]" title="${escapeHtml(timesheet.ticket_description || '')}">${ticketDesc}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700">${customer}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-semibold text-gray-800">${jatahMd}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-xs text-gray-700 max-w-[180px]" title="${escapeHtml(timesheet.description || '')}">${aktivitas}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-semibold text-gray-800">${mdConsumed}</td>
                    <td class="px-3 py-2 border-b border-gray-100 text-center text-xs font-bold text-green-700">${onSite}</td>
                    <td class="px-3 py-2 border-b border-gray-100 whitespace-nowrap">
                        <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full ${statusColor.bg} ${statusColor.text}">
                            ${timesheet.status.charAt(0).toUpperCase() + timesheet.status.slice(1)}
                        </span>
                        ${timesheet.status === 'rejected' && timesheet.rejection_reason ? `<i class="fas fa-info-circle text-yellow-500 ml-1 cursor-pointer text-xs" title="${escapeHtml(timesheet.rejection_reason)}" onclick="event.stopPropagation(); showRejectionReason(${timesheet.id})"></i>` : ''}
                    </td>
                </tr>
            `;
        }).join('');
        return;
    }

    // Employee mode (All / Project / Office)
    tbody.innerHTML = pageItems.map(timesheet => {
        const statusColor = statusColors[timesheet.status] || statusColors.draft;
        const duration = (timesheet.duration_minutes / 60).toFixed(2);
        const canEdit = ['draft', 'rejected'].includes(timesheet.status);

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
            const mdVal      = timesheet.md_consumed != null ? Number(timesheet.md_consumed).toFixed(1) : '—';
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
                    ${canEdit ? `<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="${timesheet.id}" data-status="${timesheet.status}" onchange="updateBulkActionButtons()" onclick="event.stopPropagation()">` : `<span class="text-gray-300"><i class="fas fa-lock text-xs" title="Cannot edit (${timesheet.status})"></i></span>`}
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
                        ${timesheet.description || '-'}
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
    const date = new Date(dateStr);
    const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
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
    const filterStartDate = document.getElementById('filterStartDate');
    const filterEndDate = document.getElementById('filterEndDate');
    const filterStatus = document.getElementById('filterStatus');
    const filterActivityType = document.getElementById('filterActivityType');

    if (filterStartDate) currentFilters.start_date = filterStartDate.value;
    if (filterEndDate) currentFilters.end_date = filterEndDate.value;
    if (filterStatus) currentFilters.status = filterStatus.value;
    if (filterActivityType) currentFilters.activity_type = filterActivityType.value;

    currentPage = 1;

    // Sync active stat card with dropdown selection
    if (filterStatus) filterByStatus(filterStatus.value);

    // Date range change requires re-fetch
    if (window.isApprovalMode) {
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

    if (title) title.innerHTML = '<i class="fas fa-clock"></i> Log Working Hours';
    if (form) form.reset();
    if (idField) idField.value = '';

    const today = formatDate(new Date());
    if (dateField) dateField.value = today;

    // Set default time (08:00 - 17:00)
    setTimePicker('Start', '08:00');
    setTimePicker('End', '17:00');

    const supportRadio = document.querySelector('input[name="timesheetType"][value="support"]');
    if (supportRadio) {
        supportRadio.checked = true;
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        handleTimesheetTypeChange();
    }, 50);
}

function closeTimesheetModal() {
    const modal = document.getElementById('timesheetModal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
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

    if (title) title.innerHTML = '<i class="fas fa-edit"></i> Edit Timesheet';

    const timesheetId = document.getElementById('timesheetId');
    const timesheetDate = document.getElementById('timesheetDate');
    const timesheetDescription = document.getElementById('timesheetDescription');

    if (timesheetId) timesheetId.value = timesheet.id;
    if (timesheetDate) timesheetDate.value = timesheet.date;
    if (timesheetDescription) timesheetDescription.value = timesheet.description || '';

    // Set time pickers using helper function
    setTimePicker('Start', timesheet.start_time);
    setTimePicker('End', timesheet.end_time);
    
    const timesheetType = timesheet.delivery_projects_id ? 'project' :
                         (timesheet.ticket_id ? 'support' : 'office');
    const typeRadio = document.querySelector(`input[name="timesheetType"][value="${timesheetType}"]`);
    if (typeRadio) {
        typeRadio.checked = true;
    }
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    handleTimesheetTypeChange();
    
    setTimeout(async () => {
        const activitySelect = document.getElementById('timesheetActivity');
        const projectIdInput = document.getElementById('timesheetProjectId');
        const ticketSelect = document.getElementById('timesheetTicket');
        const presence = document.getElementById('timesheetPresence');
        const location = document.getElementById('timesheetLocation');
        const billable = document.getElementById('timesheetBillable');

        if (presence) presence.value = timesheet.presence || '';
        if (location) location.value = timesheet.location || '';
        if (billable) billable.checked = timesheet.is_billable || false;

        if (timesheetType === 'support') {
            // Set ticket and trigger auto-fill
            if (ticketSelect && timesheet.ticket_id) {
                ticketSelect.value = timesheet.ticket_id;
                await onSupportTicketSelected(timesheet.ticket_id);
            }
            // On Site checkbox: checked if presence === 'onsite'
            const onSiteEl = document.getElementById('supportOnSite');
            if (onSiteEl) onSiteEl.checked = timesheet.presence === 'onsite';
            // MD Consumed
            const mdEl = document.getElementById('supportMdConsumed');
            if (mdEl) mdEl.value = timesheet.md_consumed != null ? timesheet.md_consumed : '';
        }

        // For project type: wait for activities to load, then set the selected activity
        if (timesheet.delivery_projects_id && activitySelect) {
            setTimeout(() => {
                if (activitySelect && timesheet.activity_id) {
                    activitySelect.value = timesheet.activity_id;
                    onActivitySelected();
                }
                if (projectIdInput) {
                    projectIdInput.value = timesheet.delivery_projects_id;
                }
            }, 300);
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
        const response = await fetch(`/api/timesheets/${deleteTimesheetId}`, {
            method: 'DELETE',
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
function openSubmitModal(id) {
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

async function submitTimesheetDirect(id) {
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
            await loadTimesheets();
            await loadStatistics();
        } else {
            showNotification('Failed to submit timesheet: ' + data.message, 'error');
        }
    } catch (error) {
        showNotification('An error occurred while submitting timesheet', 'error');
    }
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

        if (window.isApprovalMode) {
            // Approval mode: Approve / Reject buttons (always show when items selected)
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
            checkboxes.forEach(cb => {
                if (cb.getAttribute('data-status') === 'draft') hasDraft = true;
            });

            if (btnEdit)   btnEdit.classList.toggle('hidden', checkboxes.length !== 1);
            if (btnSubmit) btnSubmit.classList.toggle('hidden', !hasDraft);
            if (btnDelete) btnDelete.classList.remove('hidden');
        }
    } else {
        bulkActions.classList.add('hidden');
        bulkActions.classList.remove('flex');
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
            const response = await fetch(`/api/timesheets/${id}`, {
                method: 'DELETE',
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

function openBulkSubmitModal() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    
    if (checkboxes.length === 0) {
        showNotification('Please select timesheets to submit', 'error');
        return;
    }
    
    const bulkSubmitCount = document.getElementById('bulkSubmitCount');
    if (bulkSubmitCount) bulkSubmitCount.textContent = checkboxes.length;
    
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
            }
        } catch (error) {
            failCount++;
        }
    }
    
    closeBulkSubmitModal();
    await loadTimesheets();
    await loadStatistics();
    
    if (successCount > 0) {
        showNotification(`Submitted ${successCount} timesheet(s) successfully${failCount > 0 ? `, ${failCount} failed` : ''}!`, 'success');
    } else {
        showNotification('Failed to submit timesheets', 'error');
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

async function submitAllTimesheets() {
    const checkboxes = document.querySelectorAll('.timesheet-checkbox:checked');
    
    if (checkboxes.length === 0) {
        showNotification('Please select timesheets to submit', 'error');
        return;
    }
    
    if (!confirm(`Submit ${checkboxes.length} timesheet(s) for approval?`)) {
        return;
    }
    
    let successCount = 0;
    let failCount = 0;
    
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
            }
        } catch (error) {
            failCount++;
        }
    }
    
    await loadTimesheets();
    await loadStatistics();
    
    if (successCount > 0) {
        showNotification(`Submitted ${successCount} timesheet(s) successfully${failCount > 0 ? `, ${failCount} failed` : ''}!`, 'success');
    } else {
        showNotification('Failed to submit timesheets', 'error');
    }
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
        employeeId = 1;
    }
    
    const selectedRadio = document.querySelector('input[name="timesheetType"]:checked');
    const selectedType = selectedRadio ? selectedRadio.value : 'support';
    
    // Construct time from dropdowns
    const startHour = document.getElementById('timesheetStartHour')?.value || '08';
    const startMinute = document.getElementById('timesheetStartMinute')?.value || '00';
    const endHour = document.getElementById('timesheetEndHour')?.value || '17';
    const endMinute = document.getElementById('timesheetEndMinute')?.value || '00';

    const timesheetData = {
        employee_id: employeeId,
        date: document.getElementById('timesheetDate')?.value,
        start_time: `${startHour}:${startMinute}`,
        end_time: `${endHour}:${endMinute}`,
        description: document.getElementById('timesheetDescription')?.value,
    };
    
    // Type-specific data
    if (selectedType === 'project') {
        // Get project ID from hidden input (set when activity is selected)
        timesheetData.delivery_projects_id = document.getElementById('timesheetProjectId')?.value || null;
        timesheetData.activity_id = document.getElementById('timesheetActivity')?.value || null;
        timesheetData.ticket_id = null;
        timesheetData.activity_type = 'development'; // Default for project
        timesheetData.presence = document.getElementById('timesheetPresence')?.value || null;
        timesheetData.location = document.getElementById('timesheetLocation')?.value || null;
        timesheetData.is_billable = document.getElementById('timesheetBillable')?.checked || false;

    } else if (selectedType === 'support') {
        const onSite = document.getElementById('supportOnSite')?.checked;
        const mdConsumedVal = document.getElementById('supportMdConsumed')?.value;
        timesheetData.delivery_projects_id = null;
        timesheetData.ticket_id = document.getElementById('timesheetTicket')?.value || null;
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
        const url = timesheetId?.value ? `/api/timesheets/${timesheetId.value}` : '/api/timesheets';
        const method = timesheetId?.value ? 'PUT' : 'POST';
        
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
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred while saving timesheet', 'error');
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

// Approval modals click outside to close (for heads)
const approveModal = document.getElementById('approveModal');
if (approveModal) {
    approveModal.addEventListener('click', function(e) {
        if (e.target === this) closeApproveModal();
    });
}

const bulkApproveModal = document.getElementById('bulkApproveModal');
if (bulkApproveModal) {
    bulkApproveModal.addEventListener('click', function(e) {
        if (e.target === this) closeBulkApproveModal();
    });
}

const bulkRejectModal = document.getElementById('bulkRejectModal');
if (bulkRejectModal) {
    bulkRejectModal.addEventListener('click', function(e) {
        if (e.target === this) closeBulkRejectModal();
    });
}

const rejectModal = document.getElementById('rejectModal');
if (rejectModal) {
    rejectModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeRejectModal();
        }
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const timesheetModal = document.getElementById('timesheetModal');
        const confirmDeleteModal = document.getElementById('confirmDeleteModal');
        const confirmSubmitModal = document.getElementById('confirmSubmitModal');
        const confirmBulkSubmitModal = document.getElementById('confirmBulkSubmitModal');
        const confirmBulkDeleteModal = document.getElementById('confirmBulkDeleteModal');
        const approveModal = document.getElementById('approveModal');
        const rejectModal = document.getElementById('rejectModal');

        if (timesheetModal && !timesheetModal.classList.contains('hidden')) {
            closeTimesheetModal();
        }
        if (confirmDeleteModal && !confirmDeleteModal.classList.contains('hidden')) {
            closeConfirmDelete();
        }
        if (confirmSubmitModal && !confirmSubmitModal.classList.contains('hidden')) {
            closeSubmitModal();
        }
        if (confirmBulkSubmitModal && !confirmBulkSubmitModal.classList.contains('hidden')) {
            closeBulkSubmitModal();
        }
        if (confirmBulkDeleteModal && !confirmBulkDeleteModal.classList.contains('hidden')) {
            closeBulkDeleteModal();
        }
        if (approveModal && !approveModal.classList.contains('hidden')) {
            closeApproveModal();
        }
        if (rejectModal && !rejectModal.classList.contains('hidden')) {
            closeRejectModal();
        }
    }
});