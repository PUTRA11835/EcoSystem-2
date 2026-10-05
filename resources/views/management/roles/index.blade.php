@extends('dashboard')

@section('title', 'Role Management')
@section('page-title', 'Role Management')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <h2 class="text-2xl font-bold text-gray-900">Role Management</h2>
        <button onclick="openCreateRoleModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <i class="fas fa-plus mr-2"></i> Add Role
        </button>
    </div>

    <!-- Filter -->
    <div class="mb-4">
        <input type="text" id="roleSearch" placeholder="Search role name or description..."
            oninput="filterRoles()"
            class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
    </div>

    <!-- Table -->
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Members</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody id="rolesTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Create / Edit Role ──────────────────────────────────────────── -->
<div id="roleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="roleModalTitle" class="text-lg font-bold text-gray-900">Add Role</h3>
            <button onclick="closeModal('roleModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="roleForm" onsubmit="submitRole(event)" class="p-6 space-y-4">
            <input type="hidden" id="roleId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Name <span class="text-red-500">*</span></label>
                <input type="text" id="roleName" required maxlength="100"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. Project Manager">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea id="roleDescription" maxlength="255" rows="3"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent resize-none"
                    placeholder="Optional description..."></textarea>
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeModal('roleModal')" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Members ─────────────────────────────────────────────────────── -->
<div id="membersModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="membersModalTitle" class="text-lg font-bold text-gray-900">Members</h3>
            <button onclick="closeModal('membersModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="membersModalBody" class="p-6 max-h-96 overflow-y-auto">
            <p class="text-gray-400 text-sm text-center">Loading...</p>
        </div>
    </div>
</div>

<!-- ── Modal: Custom Confirm ───────────────────────────────────────────────── -->
<div id="confirmModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm animate-in">
        <div class="p-6 text-center">
            <div id="confirmIconWrap" class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4">
                <i id="confirmIcon" class="text-xl"></i>
            </div>
            <h3 id="confirmTitle" class="text-base font-semibold text-gray-800 mb-2"></h3>
            <p id="confirmMessage" class="text-sm text-gray-500 mb-6 leading-relaxed"></p>
            <div class="flex gap-3">
                <button id="confirmCancelBtn"
                    class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button id="confirmOkBtn"
                    class="flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let rolesData = [];
let currentRoleId = null;

// ── Load ─────────────────────────────────────────────────────────────────────

async function loadRoles() {
    const res = await fetch('/api/roles');
    const json = await res.json();
    rolesData = json.data || [];
    renderRoles();
}

// ── Filter ────────────────────────────────────────────────────────────────────

function filterRoles() {
    renderRoles();
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderRoles() {
    const tbody = document.getElementById('rolesTableBody');
    const query = (document.getElementById('roleSearch')?.value || '').toLowerCase().trim();
    const filtered = query
        ? rolesData.filter(r =>
            r.name.toLowerCase().includes(query) ||
            (r.description || '').toLowerCase().includes(query))
        : rolesData;

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">${query ? 'No roles match your search.' : 'No roles found.'}</td></tr>`;
        return;
    }
    tbody.innerHTML = filtered.map(role => `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-gray-500">${role.id}</td>
            <td class="px-4 py-3 font-semibold text-gray-900">${escHtml(role.name)}</td>
            <td class="px-4 py-3 text-gray-500">${escHtml(role.description || '-')}</td>
            <td class="px-4 py-3">
                <button onclick="openEmployeesModal(${role.id})"
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors">
                    ${role.employees_count} member${role.employees_count !== 1 ? 's' : ''}
                </button>
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                    <a href="/management/roles/${role.id}/menu-access"
                        class="px-3 py-1.5 text-xs font-semibold bg-green-50 text-green-700 border border-green-200 rounded-lg hover:bg-green-100 transition-colors">
                        <i class="fas fa-key mr-1"></i> Menu Access
                    </a>
                    <button onclick="openEditRoleModal(${role.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </button>
                    <button onclick="deleteRole(${role.id}, '${escHtml(role.name)}')"
                        class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ── Create / Edit Role ────────────────────────────────────────────────────────

function openCreateRoleModal() {
    document.getElementById('roleModalTitle').textContent = 'Add Role';
    document.getElementById('roleId').value = '';
    document.getElementById('roleName').value = '';
    document.getElementById('roleDescription').value = '';
    openModal('roleModal');
}

function openEditRoleModal(id) {
    const role = rolesData.find(r => r.id === id);
    if (!role) return;
    document.getElementById('roleModalTitle').textContent = 'Edit Role';
    document.getElementById('roleId').value = role.id;
    document.getElementById('roleName').value = role.name;
    document.getElementById('roleDescription').value = role.description || '';
    openModal('roleModal');
}

async function submitRole(e) {
    e.preventDefault();
    const id = document.getElementById('roleId').value;
    const payload = {
        name:        document.getElementById('roleName').value.trim(),
        description: document.getElementById('roleDescription').value.trim(),
    };
    const url    = id ? `/api/roles/${id}` : '/api/roles';
    const method = id ? 'PUT' : 'POST';

    const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
    const json = await res.json();

    if (json.success) {
        closeModal('roleModal');
        showToast(id ? 'Role updated successfully.' : 'Role added successfully.', 'success');
        loadRoles();
    } else {
        showToast(json.message || 'Failed to save role.', 'error');
    }
}

async function deleteRole(id, name) {
    const ok = await customConfirm({
        title:    `Delete Role?`,
        message:  `You are about to delete the role "${name}". This action cannot be undone.`,
        okLabel:  'Yes, Delete',
        okClass:  'bg-red-600 hover:bg-red-700',
        icon:     'fas fa-trash',
        iconBg:   'bg-red-50',
        iconColor:'text-red-500',
    });
    if (!ok) return;
    const res  = await fetch(`/api/roles/${id}/delete`, { method: 'POST', headers: jsonHeaders() });
    const json = await res.json();
    if (json.success) {
        showToast(`Role "${name}" deleted successfully.`, 'success');
        loadRoles();
    } else {
        showToast(json.message || 'Failed to delete role.', 'error');
    }
}

// ── Members Modal ─────────────────────────────────────────────────────────────

async function openEmployeesModal(id) {
    const role = rolesData.find(r => r.id === id);
    document.getElementById('membersModalTitle').textContent = `Members — ${role?.name ?? ''}`;
    document.getElementById('membersModalBody').innerHTML = '<p class="text-gray-400 text-sm text-center">Loading...</p>';
    openModal('membersModal');

    let members = [];
    try {
        const res  = await fetch(`/api/roles/${id}/employees`);
        const json = await res.json();
        members = json.data || [];
    } catch (e) {
        document.getElementById('membersModalBody').innerHTML = '<p class="text-red-500 text-sm text-center">Failed to load data.</p>';
        return;
    }

    if (!members.length) {
        document.getElementById('membersModalBody').innerHTML = '<p class="text-gray-400 text-sm text-center">No members.</p>';
        return;
    }

    document.getElementById('membersModalBody').innerHTML = `
        <ul class="divide-y divide-gray-100">
            ${members.map(m => `
                <li class="py-2.5 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-red-100 text-red-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                        ${(m.full_name || m.eci || '?').charAt(0).toUpperCase()}
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">${escHtml(m.full_name || '-')}</p>
                        <p class="text-xs text-gray-500">${escHtml(m.eci || '')}</p>
                    </div>
                </li>
            `).join('')}
        </ul>
    `;
}

// ── Custom Confirm Dialog ─────────────────────────────────────────────────────

function customConfirm({ title, message, okLabel = 'Confirm', okClass = 'bg-red-600 hover:bg-red-700', icon = 'fas fa-exclamation-triangle', iconBg = 'bg-red-50', iconColor = 'text-red-500' } = {}) {
    return new Promise(resolve => {
        const modal      = document.getElementById('confirmModal');
        const titleEl    = document.getElementById('confirmTitle');
        const msgEl      = document.getElementById('confirmMessage');
        const okBtn      = document.getElementById('confirmOkBtn');
        const cancelBtn  = document.getElementById('confirmCancelBtn');
        const iconWrap   = document.getElementById('confirmIconWrap');
        const iconEl     = document.getElementById('confirmIcon');

        titleEl.textContent  = title || '';
        msgEl.textContent    = message || '';
        okBtn.textContent    = okLabel;
        okBtn.className      = `flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition ${okClass}`;
        iconWrap.className   = `w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 ${iconBg}`;
        iconEl.className     = `${icon} text-xl ${iconColor}`;

        modal.classList.remove('hidden');

        function done(val) {
            modal.classList.add('hidden');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(val);
        }
        const onOk     = () => done(true);
        const onCancel = () => done(false);

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
    });
}

// ── Utilities ─────────────────────────────────────────────────────────────────

function openModal(id)  { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

function jsonHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    };
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

// Close modal on backdrop click
['roleModal','membersModal'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});

// Init
loadRoles();
</script>
@endsection
