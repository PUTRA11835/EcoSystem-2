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
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider w-28">Actions</th>
                </tr>
            </thead>
            <tbody id="moduleTableBody" class="divide-y divide-gray-100">
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Loading...</td></tr>
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

<script>
    let allModules = [];
    let deleteTargetId = null;

    async function loadModules() {
        try {
            const res = await fetch('/api/modules', { credentials: 'same-origin' });
            const data = await res.json();
            if (data.success) {
                allModules = data.data;
                renderTable(allModules);
            }
        } catch (e) {
            console.error(e);
        }
    }

    function renderTable(modules) {
        const tbody = document.getElementById('moduleTableBody');
        document.getElementById('moduleCount').textContent = modules.length + ' module(s)';

        if (!modules.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No modules yet</td></tr>`;
            return;
        }

        tbody.innerHTML = modules.map((m, i) => `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-gray-400 text-xs">${i + 1}</td>
                <td class="px-4 py-3 font-semibold text-gray-900">${m.name}</td>
                <td class="px-4 py-3 text-gray-500">${m.description || '<span class="text-gray-300">—</span>'}</td>
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
        if (!name) { alert('Module name is required.'); return; }

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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
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
                alert(err);
            }
        } catch (e) {
            alert('An error occurred. Please try again.');
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
            const res  = await fetch(`/api/modules/${deleteTargetId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                closeDeleteModal();
                loadModules();
                showNotification('Module deleted successfully.', 'success');
            } else {
                alert(data.message);
            }
        } catch (e) {
            alert('An error occurred. Please try again.');
        }
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeModal(); closeDeleteModal(); }
    });

    document.addEventListener('DOMContentLoaded', loadModules);
</script>
@endsection
