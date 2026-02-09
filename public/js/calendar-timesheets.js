// Calendar Timesheets JavaScript - UPDATED: User tickets only, no activity dropdown
let timesheets = [];
let selectedTimesheetId = null;
let deleteTimesheetId = null;
let currentFilters = {
    start_date: null,
    end_date: null,
    status: '',
    activity_type: ''
};

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
    loadTimesheets();
    loadStatistics();
    
    const form = document.getElementById('timesheetForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.timesheet-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActionButtons();
        });
    }
});

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
        // ✅ Support type: Ticket dropdown + Presence + Location (NO Activity)
        fieldsHTML = `
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Ticket <span class="text-red-600">*</span>
                </label>
                <select id="timesheetTicket" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <option value="">Select a Ticket</option>
                </select>
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
        // ✅ Use /api/tickets/my to get only user's tickets
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
                select.innerHTML = '<option value="">Select a Ticket</option>';
                
                // ✅ Filter active tickets only (not closed or cancelled)
                const activeTickets = data.data.filter(t => 
                    t.status !== 'closed' && 
                    t.status !== 'cancel' &&
                    t.jarvies_status !== 'closed'
                );
                
                if (activeTickets.length === 0) {
                    select.innerHTML = '<option value="">No active tickets assigned to you</option>';
                    select.disabled = true;
                    return;
                }
                
                // ✅ Sort by ticket_id descending (newest first)
                activeTickets.sort((a, b) => b.ticket_id - a.ticket_id);
                
                activeTickets.forEach(ticket => {
                    const option = document.createElement('option');
                    option.value = ticket.ticket_id;
                    
                    const customerName = ticket.customer?.customer_name || 'Unknown';
                    const description = ticket.description ? ticket.description.substring(0, 50) : 'No description';
                    
                    // ✅ Format: #ID - Customer - Description - [Status]
                    option.textContent = `#${ticket.ticket_id} - ${customerName} - ${description}`;
                    
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
            renderTimesheets();
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
    try {
        const params = new URLSearchParams();
        if (currentFilters.start_date) params.append('start_date', currentFilters.start_date);
        if (currentFilters.end_date) params.append('end_date', currentFilters.end_date);
        
        const response = await fetch(`/api/timesheets/statistics?${params}`);
        const data = await response.json();
        
        if (data.success) {
            updateStatistics(data.data);
        }
    } catch (error) {
        // Silently fail
    }
}

function updateStatistics(stats) {
    const statTotalHours = document.getElementById('statTotalHours');
    const statBillableHours = document.getElementById('statBillableHours');
    const statWeekHours = document.getElementById('statWeekHours');
    const statPendingCount = document.getElementById('statPendingCount');
    
    if (statTotalHours) statTotalHours.textContent = stats.total_hours.toFixed(2);
    if (statBillableHours) statBillableHours.textContent = stats.billable_hours.toFixed(2);
    
    const weekAgo = new Date();
    weekAgo.setDate(weekAgo.getDate() - 7);
    const weekTimesheets = timesheets.filter(t => new Date(t.date) >= weekAgo);
    const weekHours = weekTimesheets.reduce((sum, t) => sum + (t.duration_minutes / 60), 0);
    if (statWeekHours) statWeekHours.textContent = weekHours.toFixed(2);
    
    const pendingCount = timesheets.filter(t => t.status === 'submitted').length;
    if (statPendingCount) statPendingCount.textContent = pendingCount;
}

function renderTimesheets() {
    const tbody = document.getElementById('timesheetsTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (!tbody) return;
    
    if (timesheets.length === 0) {
        tbody.innerHTML = '';
        if (emptyState) emptyState.classList.remove('hidden');
        return;
    }
    
    if (emptyState) emptyState.classList.add('hidden');
    
    let filteredTimesheets = timesheets;
    if (currentFilters.activity_type) {
        filteredTimesheets = timesheets.filter(t => t.activity_type === currentFilters.activity_type);
    }
    
    tbody.innerHTML = filteredTimesheets.map(timesheet => {
        const statusColor = statusColors[timesheet.status] || statusColors.draft;
        const duration = (timesheet.duration_minutes / 60).toFixed(2);
        const canEdit = ['draft', 'rejected'].includes(timesheet.status);
        
        let typeInfo = '';
        if (timesheet.delivery_projects_id) {
            typeInfo = '<span class="text-blue-600 text-xs font-medium">Project</span>';
        } else if (timesheet.ticket_id) {
            typeInfo = '<span class="text-purple-600 text-xs font-medium">Support</span>';
        } else {
            typeInfo = '<span class="text-gray-600 text-xs font-medium">Office</span>';
        }
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    ${canEdit ? `<input type="checkbox" class="timesheet-checkbox w-4 h-4 rounded border-gray-300" data-id="${timesheet.id}" onchange="updateBulkActionButtons()">` : ''}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">${formatDisplayDate(timesheet.date)}</div>
                    <div class="text-xs text-gray-500">${typeInfo}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${timesheet.start_time} - ${timesheet.end_time}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-semibold text-gray-900">${duration}h</div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900">
                        ${timesheet.delivery_projects_id ? `<i class="fas fa-project-diagram mr-1"></i>Project #${timesheet.delivery_projects_id}` : ''}
                        ${timesheet.ticket_id ? `<i class="fas fa-ticket-alt mr-1"></i>Ticket #${timesheet.ticket_id}` : ''}
                        ${!timesheet.delivery_projects_id && !timesheet.ticket_id ? '<i class="fas fa-building mr-1"></i>Office/Idle' : ''}
                    </div>
                    ${timesheet.activity ? `<div class="text-xs text-gray-500 mt-1"><i class="fas fa-tasks mr-1"></i>${timesheet.activity.name}</div>` : ''}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center gap-2">
                        <i class="fas ${activityTypeIcons[timesheet.activity_type] || 'fa-circle'} text-gray-500"></i>
                        <span class="text-sm text-gray-900 capitalize">${timesheet.activity_type || '-'}</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-sm text-gray-900 truncate max-w-xs" title="${timesheet.description || ''}">
                        ${timesheet.description || '-'}
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusColor.bg} ${statusColor.text}">
                        ${timesheet.status.charAt(0).toUpperCase() + timesheet.status.slice(1)}
                    </span>
                    ${timesheet.is_billable ? '<i class="fas fa-dollar-sign text-green-600 ml-2" title="Billable"></i>' : ''}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center gap-2">
                        ${canEdit ? `
                            <button onclick="editTimesheet(${timesheet.id})" class="text-blue-600 hover:text-blue-900" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="openDeleteModal(${timesheet.id})" class="text-red-600 hover:text-red-900" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                        ${timesheet.status === 'draft' ? `
                            <button onclick="submitTimesheet(${timesheet.id})" class="text-green-600 hover:text-green-900" title="Submit">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        ` : ''}
                        ${timesheet.status === 'rejected' ? `
                            <button onclick="showRejectionReason(${timesheet.id})" class="text-yellow-600 hover:text-yellow-900" title="View Reason">
                                <i class="fas fa-info-circle"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
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
    
    loadTimesheets();
    loadStatistics();
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
    const timesheetStartTime = document.getElementById('timesheetStartTime');
    const timesheetEndTime = document.getElementById('timesheetEndTime');
    const timesheetDescription = document.getElementById('timesheetDescription');
    
    if (timesheetId) timesheetId.value = timesheet.id;
    if (timesheetDate) timesheetDate.value = timesheet.date;
    if (timesheetStartTime) timesheetStartTime.value = timesheet.start_time;
    if (timesheetEndTime) timesheetEndTime.value = timesheet.end_time;
    if (timesheetDescription) timesheetDescription.value = timesheet.description || '';
    
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

        if (ticketSelect) ticketSelect.value = timesheet.ticket_id || '';
        if (presence) presence.value = timesheet.presence || '';
        if (location) location.value = timesheet.location || '';
        if (billable) billable.checked = timesheet.is_billable || false;

        // For project type: wait for activities to load, then set the selected activity
        if (timesheet.delivery_projects_id && activitySelect) {
            // Wait for activities to be loaded
            setTimeout(() => {
                if (activitySelect && timesheet.activity_id) {
                    activitySelect.value = timesheet.activity_id;
                    // Trigger change to set the hidden project ID
                    onActivitySelected();
                }
                // Also set hidden project ID directly as backup
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

async function submitTimesheet(id) {
    if (!confirm('Submit this timesheet for approval?')) {
        return;
    }
    
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
        if (selectedCount) {
            selectedCount.textContent = checkboxes.length;
        }
    } else {
        bulkActions.classList.add('hidden');
        bulkActions.classList.remove('flex');
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
    
    alert(`Rejection Reason:\n\n${timesheet.rejection_reason}`);
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
    
    const timesheetData = {
        employee_id: employeeId,
        date: document.getElementById('timesheetDate')?.value,
        start_time: document.getElementById('timesheetStartTime')?.value,
        end_time: document.getElementById('timesheetEndTime')?.value,
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
        timesheetData.delivery_projects_id = null;
        timesheetData.ticket_id = document.getElementById('timesheetTicket')?.value || null;
        timesheetData.activity_type = 'support'; // Default for support
        timesheetData.presence = document.getElementById('timesheetPresence')?.value || null;
        timesheetData.location = document.getElementById('timesheetLocation')?.value || null;
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

function showNotification(message, type = 'info') {
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const timesheetModal = document.getElementById('timesheetModal');
        const confirmDeleteModal = document.getElementById('confirmDeleteModal');
        
        if (timesheetModal && !timesheetModal.classList.contains('hidden')) {
            closeTimesheetModal();
        }
        if (confirmDeleteModal && !confirmDeleteModal.classList.contains('hidden')) {
            closeConfirmDelete();
        }
    }
});