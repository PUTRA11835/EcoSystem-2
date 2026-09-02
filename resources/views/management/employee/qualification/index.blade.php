@extends('dashboard')

@section('title', 'Master Module')
@section('page-title', 'Master Module')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Master Module</h2>
            <p class="text-sm text-gray-500 mt-1">Manage the list of modules available for consultants</p>
        </div>
        <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
            <i class="fas fa-plus mr-2"></i> Add Module
        </button>
    </div>

    <!-- Search & Filter -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="text" id="moduleSearch" placeholder="Search module name..."
            oninput="filterModules()"
            class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
        <select id="statusFilter" onchange="filterModules()" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-12">No</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Module Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-40">Groups</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-40">Leads</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider w-40">Members</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Actions</th>
                </tr>
            </thead>
            <tbody id="moduleTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="mt-3 text-xs text-gray-400" id="moduleCount"></div>
</div>

<!-- Modal Create/Edit -->
<div id="moduleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Add Module</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <input type="hidden" id="editModuleId">
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Module Name <span class="text-red-600">*</span></label>
                <input type="text" id="moduleName" placeholder="e.g. FICO" maxlength="100"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Description</label>
                <input type="text" id="moduleDescription" placeholder="Short module description" maxlength="255"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="moduleIsActive" checked class="w-4 h-4 text-red-800 border-gray-300 rounded">
                <label for="moduleIsActive" class="text-sm font-medium text-gray-700">Active</label>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <button onclick="closeModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="saveModule()" class="flex-1 px-4 py-2.5 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-all">
                <span id="saveModuleText">Save</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Confirm Delete -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-trash text-red-600"></i>
        </div>
        <h3 class="text-lg font-bold text-gray-900 mb-2">Delete Module?</h3>
        <p class="text-sm text-gray-500 mb-1">Module <span id="deleteModuleName" class="font-semibold text-gray-800"></span> will be deleted.</p>
        <p class="text-xs text-red-500 mb-5">Qualification records using this module will lose their module reference.</p>
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" class="flex-1 px-4 py-2.5 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
            <button onclick="confirmDelete()" class="flex-1 px-4 py-2.5 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 transition-all">Delete</button>
        </div>
    </div>
</div>

<!-- Modal Manage Leads -->
<div id="leadsModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-lg font-bold text-gray-900">Manage Leads</h3>
            <button onclick="closeLeadsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="text-sm text-gray-500 mb-4" id="leadsModalModuleName"></p>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Add Lead</label>
            <div class="relative">
                <input type="text" id="leadEmployeeSearch" placeholder="Search employee name..." autocomplete="off"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
                <div id="leadEmployeeResults" class="hidden absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto z-10"></div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Current Leads</label>
            <div id="currentLeadsList" class="flex flex-wrap gap-2 min-h-[2.5rem]"></div>
        </div>

        <div class="flex justify-end mt-6">
            <button onclick="closeLeadsModal()" class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-all">Close</button>
        </div>
    </div>
</div>

<!-- Modal View Members (read-only — dikelola lewat tab Qualification per employee) -->
<div id="membersModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-lg font-bold text-gray-900">Module Members</h3>
            <button onclick="closeMembersModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="text-sm text-gray-500 mb-4" id="membersModalModuleName"></p>

        <div id="membersModalList" class="flex flex-col divide-y divide-gray-100 max-h-80 overflow-y-auto border border-gray-100 rounded-lg"></div>
        <p class="text-xs text-gray-400 mt-3">Membership diambil dari data Qualification masing-masing employee — untuk mengubahnya, buka profile employee terkait.</p>

        <div class="flex justify-end mt-4">
            <button onclick="closeMembersModal()" class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-all">Close</button>
        </div>
    </div>
</div>

<script>
    let allModules = [];
    let deleteTargetId = null;
    let leadsByModule = {};
    let membersByModule = {};
    let currentLeadsModuleId = null;
    let leadSearchTimeout = null;

    async function loadModules() {
        try {
            const res = await fetch('/api/modules', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                allModules = data.data;
                renderTable(allModules);
                loadAllLeads();
                loadAllMembers();
            }
        } catch (e) {
            console.error(e);
        }
    }

    /**
     * Leads dimuat terpisah per module (bukan lewat /api/modules) supaya
     * endpoint /api/modules tetap ringan untuk consumer lain (mis. dropdown
     * module di form Qualification) yang tidak butuh data lead sama sekali.
     */
    async function loadAllLeads() {
        try {
            const results = await Promise.all(allModules.map(m =>
                fetch(`/api/modules/${m.id}/leads`, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => ({ id: m.id, leads: d.success ? d.data : [] }))
                    .catch(() => ({ id: m.id, leads: [] }))
            ));
            leadsByModule = {};
            results.forEach(r => { leadsByModule[r.id] = r.leads; });
            filterModules();
        } catch (e) {
            console.error('Failed to load module leads', e);
        }
    }

    /**
     * Members = employee dengan qualification record di module ini. Read-only
     * di halaman ini, dikelola lewat tab Qualification masing-masing employee.
     */
    async function loadAllMembers() {
        try {
            const results = await Promise.all(allModules.map(m =>
                fetch(`/api/modules/${m.id}/members`, { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => ({ id: m.id, members: d.success ? d.data : [] }))
                    .catch(() => ({ id: m.id, members: [] }))
            ));
            membersByModule = {};
            results.forEach(r => { membersByModule[r.id] = r.members; });
            filterModules();
        } catch (e) {
            console.error('Failed to load module members', e);
        }
    }

    function renderTable(modules) {
        const tbody = document.getElementById('moduleTableBody');
        document.getElementById('moduleCount').textContent = modules.length + ' module(s)';

        if (!modules.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No modules yet</td></tr>`;
            return;
        }

        tbody.innerHTML = modules.map((m, i) => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-gray-400 text-xs">${i + 1}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">${m.name}</td>
                <td class="px-4 py-3 text-gray-500">${m.description || '<span class="text-gray-300">—</span>'}</td>
                <td class="px-4 py-3">
                    ${m.groups && m.groups.length
                        ? m.groups.map(g => `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 mr-1 mb-1">${g.name}</span>`).join('')
                        : '<span class="text-xs text-gray-300">—</span>'}
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        ${renderCountBadge(leadsByModule[m.id], 'bg-red-50 text-red-800', 'lead')}
                        <button onclick="openLeadsModal(${m.id}, '${m.name.replace(/'/g, "\\'")}')" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-red-800 hover:text-red-900 hover:bg-red-50 rounded-lg transition-colors" title="Manage Leads">
                            <i class="fas fa-user-shield text-[10px]"></i> Manage
                        </button>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        ${renderCountBadge(membersByModule[m.id], 'bg-blue-50 text-blue-700', 'member')}
                        <button onclick="openMembersModal(${m.id}, '${m.name.replace(/'/g, "\\'")}')" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-blue-700 hover:text-blue-800 hover:bg-blue-50 rounded-lg transition-colors" title="View Members">
                            <i class="fas fa-users text-[10px]"></i> View
                        </button>
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    ${m.is_active
                        ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Active</span>`
                        : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>`}
                </td>
                <td class="px-4 py-3 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <button onclick="openEditModal(${m.id})" class="p-1.5 text-gray-400 hover:text-blue-600 transition-colors" title="Edit">
                            <i class="fas fa-pencil-alt text-xs"></i>
                        </button>
                        <button onclick="openDeleteModal(${m.id}, '${m.name.replace(/'/g, "\\'")}')" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Delete">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /**
     * Badge angka generik dipakai untuk kolom Leads & Members. `list` bisa
     * berupa array (sudah dimuat) atau undefined (masih loading).
     */
    function renderCountBadge(list, colorClass, noun) {
        if (!list) return `<span class="text-xs text-gray-300">…</span>`;
        if (!list.length) return `<span class="text-xs text-gray-400">No ${noun}</span>`;
        return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${colorClass}">${list.length} ${noun}${list.length > 1 ? 's' : ''}</span>`;
    }

    function filterModules() {
        const q = document.getElementById('moduleSearch').value.toLowerCase();
        const status = document.getElementById('statusFilter').value;
        const filtered = allModules.filter(m => {
            const matchQ = !q || m.name.toLowerCase().includes(q) || (m.description || '').toLowerCase().includes(q);
            const matchStatus = status === '' || String(m.is_active ? 1 : 0) === status;
            return matchQ && matchStatus;
        });
        renderTable(filtered);
    }

    function openCreateModal() {
        document.getElementById('editModuleId').value = '';
        document.getElementById('moduleName').value = '';
        document.getElementById('moduleDescription').value = '';
        document.getElementById('moduleIsActive').checked = true;
        document.getElementById('modalTitle').textContent = 'Add Module';
        document.getElementById('saveModuleText').textContent = 'Save';
        document.getElementById('moduleModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('moduleName').focus(), 100);
    }

    function openEditModal(id) {
        const m = allModules.find(x => x.id === id);
        if (!m) return;
        document.getElementById('editModuleId').value = m.id;
        document.getElementById('moduleName').value = m.name;
        document.getElementById('moduleDescription').value = m.description || '';
        document.getElementById('moduleIsActive').checked = m.is_active;
        document.getElementById('modalTitle').textContent = 'Edit Module';
        document.getElementById('saveModuleText').textContent = 'Update';
        document.getElementById('moduleModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('moduleName').focus(), 100);
    }

    function closeModal() {
        document.getElementById('moduleModal').classList.add('hidden');
    }

    async function saveModule() {
        const id   = document.getElementById('editModuleId').value;
        const name = document.getElementById('moduleName').value.trim().toUpperCase();
        if (!name) { showNotification('Module name is required.', 'warning'); return; }

        const payload = {
            name,
            description: document.getElementById('moduleDescription').value.trim() || null,
            is_active: document.getElementById('moduleIsActive').checked,
        };

        const url    = id ? `/api/modules/${id}` : '/api/modules';
        const method = id ? 'PUT' : 'POST';

        try {
            const res  = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.success) {
                closeModal();
                loadModules();
                showNotification(id ? 'Module updated successfully.' : 'Module added successfully.', 'success');
            } else {
                const err = data.errors ? Object.values(data.errors).flat().join('\n') : data.message;
                showNotification(err, 'error');
            }
        } catch (e) {
            showNotification('An error occurred. Please try again.', 'error');
        }
    }

    function openDeleteModal(id, name) {
        deleteTargetId = id;
        document.getElementById('deleteModuleName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        deleteTargetId = null;
        document.getElementById('deleteModal').classList.add('hidden');
    }

    async function confirmDelete() {
        if (!deleteTargetId) return;
        try {
            const res  = await fetch(`/api/modules/${deleteTargetId}/delete`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                closeDeleteModal();
                loadModules();
                showNotification('Module deleted successfully.', 'success');
            } else {
                showNotification(data.message, 'error');
            }
        } catch (e) {
            showNotification('An error occurred. Please try again.', 'error');
        }
    }

    // ---------------------------------------------------------------
    // Manage Leads modal
    // ---------------------------------------------------------------

    function openLeadsModal(moduleId, moduleName) {
        currentLeadsModuleId = moduleId;
        document.getElementById('leadsModalModuleName').textContent = moduleName;
        document.getElementById('leadEmployeeSearch').value = '';
        document.getElementById('leadEmployeeResults').classList.add('hidden');
        renderCurrentLeadsList();
        document.getElementById('leadsModal').classList.remove('hidden');
        document.getElementById('leadsModal').classList.add('flex');
        setTimeout(() => document.getElementById('leadEmployeeSearch').focus(), 100);
    }

    function closeLeadsModal() {
        document.getElementById('leadsModal').classList.add('hidden');
        document.getElementById('leadsModal').classList.remove('flex');
        document.getElementById('leadEmployeeResults').classList.add('hidden');
        currentLeadsModuleId = null;
    }

    function renderCurrentLeadsList() {
        const list = document.getElementById('currentLeadsList');
        const leads = leadsByModule[currentLeadsModuleId] || [];
        if (!leads.length) {
            list.innerHTML = `<span class="text-sm text-gray-400">No leads assigned yet.</span>`;
            return;
        }
        list.innerHTML = leads.map(l => `
            <span class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full text-sm font-medium bg-red-50 text-red-800">
                ${l.name}
                <button onclick="removeLead(${l.employee_id})" class="w-4 h-4 flex items-center justify-center rounded-full hover:bg-red-200 transition-colors" title="Remove">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            </span>
        `).join('');
    }

    function handleLeadSearchInput() {
        clearTimeout(leadSearchTimeout);
        const q = document.getElementById('leadEmployeeSearch').value.trim();
        leadSearchTimeout = setTimeout(() => searchLeadEmployees(q), 250);
    }

    async function searchLeadEmployees(q) {
        const resultsBox = document.getElementById('leadEmployeeResults');
        try {
            const res = await fetch(`/api/module-leads/search-employees?q=${encodeURIComponent(q)}`, { credentials: 'same-origin' });
            const data = await res.json();

            if (!data.success || !data.data.length) {
                resultsBox.innerHTML = `<div class="px-3 py-2 text-sm text-gray-400">No employee found</div>`;
                resultsBox.classList.remove('hidden');
                return;
            }

            const existingIds = new Set((leadsByModule[currentLeadsModuleId] || []).map(l => l.employee_id));

            resultsBox.innerHTML = data.data.map(e => {
                const already = existingIds.has(e.id);
                const safeName = e.name.replace(/'/g, "\\'");
                return `
                    <button type="button" ${already ? 'disabled' : `onclick="addLead(${e.id}, '${safeName}', event)"`}
                        class="w-full text-left px-3 py-2 text-sm ${already ? 'text-gray-300 cursor-not-allowed' : 'text-gray-700 hover:bg-gray-50'} transition-colors disabled:opacity-70">
                        ${e.name}${already ? ' <span class="text-xs">(already a lead)</span>' : ''}
                    </button>
                `;
            }).join('');
            resultsBox.classList.remove('hidden');
        } catch (e) {
            console.error('Search employee failed', e);
        }
    }

    async function addLead(employeeId, employeeName, event) {
        if (!currentLeadsModuleId) return;

        const btn = event?.currentTarget || null;
        const originalHtml = btn?.innerHTML;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin text-xs mr-2"></i>Adding ${employeeName}...`;
        }

        try {
            const res = await fetch(`/api/modules/${currentLeadsModuleId}/leads`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ employee_id: employeeId }),
            });
            const data = await res.json();
            if (data.success) {
                if (!leadsByModule[currentLeadsModuleId]) leadsByModule[currentLeadsModuleId] = [];
                leadsByModule[currentLeadsModuleId].push(data.data);
                renderCurrentLeadsList();
                filterModules();
                document.getElementById('leadEmployeeSearch').value = '';
                document.getElementById('leadEmployeeResults').classList.add('hidden');
                showNotification('Module lead added.', 'success');
            } else {
                showNotification(data.message || 'Failed to add lead.', 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
            }
        } catch (e) {
            showNotification('An error occurred while adding lead.', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
        }
    }

    async function removeLead(employeeId) {
        if (!currentLeadsModuleId) return;
        try {
            const res = await fetch(`/api/modules/${currentLeadsModuleId}/leads/${employeeId}/delete`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                leadsByModule[currentLeadsModuleId] = (leadsByModule[currentLeadsModuleId] || []).filter(l => l.employee_id !== employeeId);
                renderCurrentLeadsList();
                filterModules();
                showNotification('Module lead removed.', 'success');
            } else {
                showNotification(data.message || 'Failed to remove lead.', 'error');
            }
        } catch (e) {
            showNotification('An error occurred while removing lead.', 'error');
        }
    }

    // ---------------------------------------------------------------
    // View Members modal (read-only)
    // ---------------------------------------------------------------

    function openMembersModal(moduleId, moduleName) {
        document.getElementById('membersModalModuleName').textContent = moduleName;

        const members = membersByModule[moduleId] || [];
        const list = document.getElementById('membersModalList');
        list.innerHTML = members.length
            ? members.map(m => `<div class="px-3 py-2 text-sm text-gray-700">${m.name}</div>`).join('')
            : `<div class="px-3 py-6 text-sm text-gray-400 text-center">No members yet.</div>`;

        document.getElementById('membersModal').classList.remove('hidden');
        document.getElementById('membersModal').classList.add('flex');
    }

    function closeMembersModal() {
        document.getElementById('membersModal').classList.add('hidden');
        document.getElementById('membersModal').classList.remove('flex');
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeModal(); closeDeleteModal(); closeLeadsModal(); closeMembersModal(); }
    });

    document.addEventListener('click', e => {
        const resultsBox = document.getElementById('leadEmployeeResults');
        const searchInput = document.getElementById('leadEmployeeSearch');
        if (resultsBox && !resultsBox.classList.contains('hidden') && e.target !== searchInput && !resultsBox.contains(e.target)) {
            resultsBox.classList.add('hidden');
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        loadModules();
        document.getElementById('leadEmployeeSearch').addEventListener('input', handleLeadSearchInput);
        document.getElementById('leadEmployeeSearch').addEventListener('focus', () => {
            searchLeadEmployees(document.getElementById('leadEmployeeSearch').value.trim());
        });
    });
</script>
@endsection
