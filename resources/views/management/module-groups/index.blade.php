@extends('dashboard')

@section('title', 'Module Group')
@section('page-title', 'Module Group')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b-2 border-gray-100">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Module Group</h2>
            <p class="text-sm text-gray-500 mt-1">Kelompokkan module yang saling terkait (mis. Group "Logistik" berisi module ABAP, FI, MM, dll).</p>
        </div>
        <button onclick="openCreateGroupModal()" class="inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all duration-200">
            <i class="fas fa-plus mr-2"></i> Add Group
        </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-col sm:flex-row gap-3 mb-4">
        <input type="text" id="groupSearch" placeholder="Search group name..."
            oninput="renderGroups()"
            class="w-full sm:w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
        <select id="statusFilter" onchange="renderGroups()"
            class="w-full sm:w-44 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800">
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
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Group Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Modules</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Actions</th>
                </tr>
            </thead>
            <tbody id="groupsTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Modal: Create / Edit Group ──────────────────────────────────────────── -->
<div id="groupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center p-6 border-b border-gray-100">
            <h3 id="groupModalTitle" class="text-lg font-bold text-gray-900">Add Group</h3>
            <button onclick="closeModal('groupModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="groupForm" onsubmit="submitGroup(event)" class="p-6 space-y-4 overflow-y-auto">
            <input type="hidden" id="groupId">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Group Name <span class="text-red-500">*</span></label>
                <input type="text" id="groupName" required maxlength="100"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="e.g. Logistik">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Description</label>
                <textarea id="groupDescription" maxlength="255" rows="2"
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-red-800 focus:border-transparent"
                    placeholder="Optional"></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Modules</label>
                <div id="groupModuleList" class="border border-gray-200 rounded-lg p-3 max-h-52 overflow-y-auto space-y-1.5">
                    <p class="text-xs text-gray-400">Loading modules...</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="groupActive" checked
                    class="w-4 h-4 rounded cursor-pointer accent-red-800">
                <label for="groupActive" class="text-sm text-gray-700 cursor-pointer">Active</label>
            </div>
            <div class="flex gap-3 justify-end pt-2">
                <button type="button" onclick="closeModal('groupModal')" class="px-4 py-2 bg-white text-gray-700 text-sm font-semibold rounded-lg border border-gray-300 hover:bg-gray-50 transition-all">Cancel</button>
                <button type="submit" class="px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-lg hover:opacity-90 transition-all">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Custom Confirm ───────────────────────────────────────────────── -->
<div id="confirmModal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
        <div class="p-6 text-center">
            <div id="confirmIconWrap" class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4">
                <i id="confirmIcon" class="text-xl"></i>
            </div>
            <h3 id="confirmTitle" class="text-base font-semibold text-gray-800 mb-2"></h3>
            <p id="confirmMessage" class="text-sm text-gray-500 mb-6 leading-relaxed"></p>
            <div class="flex gap-3">
                <button id="confirmCancelBtn" class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                <button id="confirmOkBtn" class="flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
let groupsData = [];
let allModulesForPicker = [];

// ── Load ─────────────────────────────────────────────────────────────────────

async function loadGroups() {
    const res  = await fetch('/api/module-groups');
    const json = await res.json();
    groupsData = json.data || [];
    renderGroups();
}

async function loadModulesForPicker() {
    const res  = await fetch('/api/modules?is_active=1');
    const json = await res.json();
    allModulesForPicker = json.data || [];
}

// ── Render ────────────────────────────────────────────────────────────────────

function renderGroups() {
    const tbody = document.getElementById('groupsTableBody');
    const query  = (document.getElementById('groupSearch')?.value || '').toLowerCase().trim();
    const status = document.getElementById('statusFilter')?.value ?? '';

    const filtered = groupsData.filter(g => {
        const matchQ = !query || g.name.toLowerCase().includes(query);
        const matchStatus = status === '' || String(g.is_active ? 1 : 0) === status;
        return matchQ && matchStatus;
    });

    if (!filtered.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">${query || status ? 'No groups match your filter.' : 'No module groups yet. Click "Add Group".'}</td></tr>`;
        return;
    }

    tbody.innerHTML = filtered.map((g, i) => `
        <tr class="hover:bg-gray-50 transition-colors ${g.is_active ? '' : 'opacity-50'}">
            <td class="px-4 py-3 text-gray-400 text-xs">${i + 1}</td>
            <td class="px-4 py-3 font-semibold text-gray-900">${escHtml(g.name)}</td>
            <td class="px-4 py-3 text-gray-500">${escHtml(g.description || '') || '<span class="text-gray-300">—</span>'}</td>
            <td class="px-4 py-3">
                ${(g.modules && g.modules.length)
                    ? g.modules.map(m => `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 mr-1 mb-1">${escHtml(m.name)}</span>`).join('')
                    : '<span class="text-xs text-gray-400">No modules</span>'}
            </td>
            <td class="px-4 py-3 text-center">
                ${g.is_active
                    ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700">Active</span>'
                    : '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>'}
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-2">
                    <button onclick="openEditGroupModal(${g.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-pen mr-1"></i> Edit
                    </button>
                    <button onclick="deleteGroup(${g.id})"
                        class="px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                        <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                </div>
            </td>
        </tr>`).join('');
}

function renderModulePicker(selectedIds) {
    const wrap = document.getElementById('groupModuleList');
    if (!allModulesForPicker.length) {
        wrap.innerHTML = '<p class="text-xs text-gray-400">No modules available.</p>';
        return;
    }
    const selected = new Set(selectedIds || []);
    wrap.innerHTML = allModulesForPicker.map(m => `
        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" value="${m.id}" ${selected.has(m.id) ? 'checked' : ''}
                class="group-module-checkbox w-4 h-4 rounded cursor-pointer accent-red-800">
            ${escHtml(m.name)}
        </label>`).join('');
}

function selectedModuleIds() {
    return Array.from(document.querySelectorAll('.group-module-checkbox:checked')).map(cb => parseInt(cb.value, 10));
}

// ── Create / Edit ──────────────────────────────────────────────────────────────

async function openCreateGroupModal() {
    document.getElementById('groupModalTitle').textContent = 'Add Group';
    document.getElementById('groupId').value = '';
    document.getElementById('groupName').value = '';
    document.getElementById('groupDescription').value = '';
    document.getElementById('groupActive').checked = true;
    if (!allModulesForPicker.length) await loadModulesForPicker();
    renderModulePicker([]);
    openModal('groupModal');
}

async function openEditGroupModal(id) {
    const g = groupsData.find(x => x.id === id);
    if (!g) return;
    document.getElementById('groupModalTitle').textContent = 'Edit Group';
    document.getElementById('groupId').value = g.id;
    document.getElementById('groupName').value = g.name;
    document.getElementById('groupDescription').value = g.description || '';
    document.getElementById('groupActive').checked = !!g.is_active;
    if (!allModulesForPicker.length) await loadModulesForPicker();
    renderModulePicker((g.modules || []).map(m => m.id));
    openModal('groupModal');
}

async function submitGroup(e) {
    e.preventDefault();
    const id = document.getElementById('groupId').value;
    const payload = {
        name:        document.getElementById('groupName').value.trim(),
        description: document.getElementById('groupDescription').value.trim(),
        is_active:   document.getElementById('groupActive').checked,
        module_ids:  selectedModuleIds(),
    };
    const url    = id ? `/api/module-groups/${id}` : '/api/module-groups';
    const method = id ? 'PUT' : 'POST';

    const res  = await fetch(url, { method, headers: jsonHeaders(), body: JSON.stringify(payload) });
    const json = await res.json();

    if (json.success) {
        closeModal('groupModal');
        showToast(id ? 'Group updated successfully.' : 'Group added successfully.', 'success');
        loadGroups();
    } else {
        showToast(json.message || 'Failed to save group.', 'error');
    }
}

async function deleteGroup(id) {
    const g = groupsData.find(x => x.id === id);
    const ok = await customConfirm({
        title:     'Delete Group?',
        message:   `You are about to delete "${g?.name ?? ''}". Modules in this group will not be deleted, only the grouping. This action cannot be undone.`,
        okLabel:   'Yes, Delete',
        okClass:   'bg-red-600 hover:bg-red-700',
        icon:      'fas fa-trash',
        iconBg:    'bg-red-50',
        iconColor: 'text-red-500',
    });
    if (!ok) return;
    const res  = await fetch(`/api/module-groups/${id}/delete`, { method: 'POST', headers: jsonHeaders() });
    const json = await res.json();
    if (json.success) {
        showToast('Group deleted successfully.', 'success');
        loadGroups();
    } else {
        showToast(json.message || 'Failed to delete group.', 'error');
    }
}

// ── Custom Confirm ──────────────────────────────────────────────────────────────

function customConfirm({ title, message, okLabel = 'Confirm', okClass = 'bg-red-600 hover:bg-red-700', icon = 'fas fa-exclamation-triangle', iconBg = 'bg-red-50', iconColor = 'text-red-500' } = {}) {
    return new Promise(resolve => {
        const modal     = document.getElementById('confirmModal');
        const okBtn     = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');
        document.getElementById('confirmTitle').textContent   = title || '';
        document.getElementById('confirmMessage').textContent = message || '';
        okBtn.textContent  = okLabel;
        okBtn.className    = `flex-1 rounded-xl py-2.5 text-sm font-semibold text-white transition ${okClass}`;
        document.getElementById('confirmIconWrap').className = `w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4 ${iconBg}`;
        document.getElementById('confirmIcon').className     = `${icon} text-xl ${iconColor}`;
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

document.getElementById('groupModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('groupModal');
});

// Init
loadGroups();
</script>
@endsection
