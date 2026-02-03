<div class="space-y-6">
    <!-- General Information -->
    <div>
        <div class="flex justify-between items-center mb-4 pb-2 border-b border-gray-200">
            <h3 class="text-base font-semibold text-gray-900">General Information</h3>
            <button onclick="saveCurrentSection()" class="inline-flex items-center gap-2 px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                </svg>
                Save Changes
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Title</label>
                <select id="title" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <option value="Mr.">Mr.</option>
                    <option value="Mrs.">Mrs.</option>
                    <option value="Ms.">Ms.</option>
                    <option value="Dr.">Dr.</option>
                </select>
                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                        <rect x="2" y="3" width="12" height="2" fill="#374151"/>
                        <rect x="2" y="7" width="12" height="2" fill="#374151"/>
                        <rect x="2" y="11" width="12" height="2" fill="#374151"/>
                    </svg>
                </div>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Nick Name</label>
                <input type="text" id="nickName" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Gender</label>
                <select id="gender" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Religion</label>
                <select id="religion" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <option value="Islam">Islam</option>
                    <option value="Christian">Christian</option>
                    <option value="Catholic">Catholic</option>
                    <option value="Hindu">Hindu</option>
                    <option value="Buddhist">Buddhist</option>
                    <option value="Confucian">Confucian</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">First Name <span class="text-red-600">*</span></label>
                <input type="text" id="firstName" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Last Name</label>
                <input type="text" id="lastName" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Marital Status</label>
                <select id="maritalStatus" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Divorced">Divorced</option>
                    <option value="Widow/Widower">Widow/Widower</option>
                </select>
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Birth Date</label>
                <input type="date" id="birthDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Search Term 1 <span class="text-gray-500 text-xs">(auto-generated)</span></label>
                <input type="text" id="searchTerm1" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:outline-none">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Search Term 2 <span class="text-gray-500 text-xs">(auto-generated)</span></label>
                <input type="text" id="searchTerm2" readonly class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm bg-gray-50 focus:outline-none">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Birth Place</label>
                <input type="text" id="birthPlace" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Since Date</label>
                <input type="date" id="sinceDate" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>
    </div>

    <!-- Employee Information -->
    <div>
        <h3 class="text-base font-semibold text-gray-900 mb-4 pb-2 border-b border-gray-200">Employee Information</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Personnel Area</label>
                <input type="text" id="personnelArea" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Personnel Subarea</label>
                <input type="text" id="personnelSubarea" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Employee Group</label>
                <input type="text" id="employeeGroup" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Employee Subgroup</label>
                <input type="text" id="employeeSubgroup" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Position</label>
                <input type="text" id="position" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Division</label>
                <input type="text" id="division" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Department</label>
                <input type="text" id="department" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex flex-col">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Authorization Group</label>
                <input type="text" id="authorizationGroup" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-4">
            <div class="flex flex-col col-span-2">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Direct Supervision</label>
                <input type="text" id="directSupervision" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent" placeholder="e.g., ECI001">
            </div>
            <div class="flex flex-col col-span-2">
                <label class="text-sm font-semibold text-gray-700 mb-1.5">Manager</label>
                <input type="text" id="manager" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent">
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="block" class="w-5 h-5 text-red-800 border-gray-300 rounded focus:ring-red-800">
                <label for="block" class="text-sm font-semibold text-gray-700">Block Employee</label>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="deletionFlag" class="w-5 h-5 text-red-800 border-gray-300 rounded focus:ring-red-800">
                <label for="deletionFlag" class="text-sm font-semibold text-gray-700">Deletion Flag</label>
            </div>
        </div>

        <!-- Created/Changed Info -->
        <div class="grid grid-cols-2 gap-6 mt-6 pt-4 border-t border-gray-200">
            <div class="space-y-2">
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Created By</span>
                    <span id="createdBy" class="text-sm text-gray-600">-</span>
                </div>
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Created On</span>
                    <span id="createdOn" class="text-sm text-gray-600">-</span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Last Changed By</span>
                    <span id="lastChangedBy" class="text-sm text-gray-600">-</span>
                </div>
                <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700 w-32">Last Changed On</span>
                    <span id="lastChangedOn" class="text-sm text-gray-600">-</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate search terms from names
document.getElementById('firstName')?.addEventListener('input', function() {
    const searchTerm1 = document.getElementById('searchTerm1');
    if (searchTerm1) {
        searchTerm1.value = this.value.toUpperCase();
    }
});

document.getElementById('lastName')?.addEventListener('input', function() {
    const searchTerm2 = document.getElementById('searchTerm2');
    if (searchTerm2) {
        searchTerm2.value = this.value.toUpperCase();
    }
});

// Load employee basic data
async function loadEmployeeBasicData(employeeId) {
    try {
        const response = await fetch(`/api/employees/${employeeId}/basic-data`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin'
        });

        const result = await response.json();
        
        if (result.success && result.data) {
            const basicData = result.data;
            
            // DEBUG: Lihat data yang diterima
            console.log('Raw birth_date from API:', basicData.birth_date);
            console.log('Raw since_date from API:', basicData.since_date);
            
            // General Information
            setValue('title', basicData.title);
            setValue('firstName', basicData.first_name);
            setValue('lastName', basicData.last_name);
            setValue('nickName', basicData.nick_name);
            setValue('gender', basicData.gender);
            setValue('religion', basicData.religion);
            setValue('searchTerm1', basicData.search_term_1);
            setValue('searchTerm2', basicData.search_term_2);
            setValue('maritalStatus', basicData.marital_status);
            
            // Format date dengan fungsi helper
            const formattedBirthDate = formatDateForInput(basicData.birth_date);
            const formattedSinceDate = formatDateForInput(basicData.since_date);
            
            console.log('Formatted birth_date:', formattedBirthDate);
            console.log('Formatted since_date:', formattedSinceDate);
            
            setValue('birthDate', formattedBirthDate);
            setValue('birthPlace', basicData.birth_place);
            setValue('sinceDate', formattedSinceDate);
            
            // Employee Information
            setValue('personnelArea', basicData.personnel_area);
            setValue('personnelSubarea', basicData.personnel_subarea);
            setValue('employeeGroup', basicData.employee_group);
            setValue('employeeSubgroup', basicData.employee_subgroup);
            setValue('position', basicData.position);
            setValue('division', basicData.division);
            setValue('department', basicData.department);
            setValue('directSupervision', basicData.direct_supervision);
            setValue('manager', basicData.manager);
            setValue('authorizationGroup', basicData.authorization_group);
            
            // Status & Flags
            setCheckbox('block', basicData.block);
            setCheckbox('deletionFlag', basicData.deletion_flag);
            
            // Audit Information
            setText('createdBy', basicData.created_by);
            setText('createdOn', formatDateTime(basicData.created_on));
            setText('lastChangedBy', basicData.last_changed_by);
            setText('lastChangedOn', formatDateTime(basicData.last_changed_on));
            
            console.log('Basic data loaded successfully');
        } else {
            console.log('No basic data found - form ready for new entry');
        }
    } catch (error) {
        console.error('Error loading basic data:', error);
        showNotification('Error loading basic data', 'error');
    }
}

// Save employee basic data
async function saveEmployeeBasicData(employeeId) {
    // Validate required fields
    const firstName = getValue('firstName').trim();
    if (!firstName) {
        showNotification('First Name is required', 'error');
        document.getElementById('firstName')?.focus();
        return;
    }

    const basicData = {
        // General Information
        title: getValue('title'),
        first_name: firstName,
        last_name: getValue('lastName'),
        nick_name: getValue('nickName'),
        gender: getValue('gender'),
        religion: getValue('religion'),
        search_term_1: getValue('searchTerm1'),
        search_term_2: getValue('searchTerm2'),
        marital_status: getValue('maritalStatus'),
        birth_date: getValue('birthDate'),
        birth_place: getValue('birthPlace'),
        since_date: getValue('sinceDate'),
        
        // Employee Information
        personnel_area: getValue('personnelArea'),
        personnel_subarea: getValue('personnelSubarea'),
        employee_group: getValue('employeeGroup'),
        employee_subgroup: getValue('employeeSubgroup'),
        position: getValue('position'),
        division: getValue('division'),
        department: getValue('department'),
        direct_supervision: getValue('directSupervision'),
        manager: getValue('manager'),
        authorization_group: getValue('authorizationGroup'),
        
        // Status & Flags
        block: getCheckbox('block'),
        deletion_flag: getCheckbox('deletionFlag')
    };

    try {
        showNotification('Saving...', 'info');
        
        const response = await fetch(`/api/employees/${employeeId}/basic-data`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify(basicData)
        });

        const data = await response.json();
        
        if (data.success) {
            showNotification('Basic data saved successfully!', 'success');
            // Reload data to get updated timestamps
            await loadEmployeeBasicData(employeeId);
        } else {
            showNotification('Failed to save: ' + (data.message || 'Unknown error'), 'error');
            if (data.errors) {
                console.error('Validation errors:', data.errors);
            }
        }
    } catch (error) {
        console.error('Error saving basic data:', error);
        showNotification('An error occurred while saving', 'error');
    }
}

// Helper functions
function getValue(id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value || '';
}

function getCheckbox(id) {
    const el = document.getElementById(id);
    return el ? el.checked : false;
}

function setCheckbox(id, value) {
    const el = document.getElementById(id);
    if (el) el.checked = value === true || value === 1;
}

function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value || '-';
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const seconds = String(date.getSeconds()).padStart(2, '0');
    return `${day}.${month}.${year} ${hours}:${minutes}:${seconds}`;
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
</script>