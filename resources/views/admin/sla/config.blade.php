@extends('dashboard')
@section('title', 'SLA Configuration')
@section('page-title', 'SLA Configuration')
@section('page-subtitle', 'Manage SLA time targets per customer, priority, and scale')

@section('page-actions')
<button onclick="openAddModal()"
    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium text-white transition"
    style="background-color: var(--primary-color);">
    <i class="fas fa-plus"></i>
    <span>Add Policy</span>
</button>
@endsection

@section('content')
<div class="space-y-5">

    {{-- ── FILTER ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Filter by Customer</label>
                <select id="filterCustomer" onchange="loadPolicies()"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300 min-w-[200px]">
                    <option value="">All Customers</option>
                    <option value="global">Default Global</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->customer_id }}">
                            {{ $c->basicData->name_1 ?? $c->customer_code ?? 'Customer #'.$c->customer_id }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="ml-auto flex items-end gap-2">
                <div class="text-xs text-gray-400 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                    <i class="fas fa-info-circle mr-1"></i>
                    <strong>Default Global</strong> applies to customers without a specific policy
                </div>
            </div>
        </div>
    </div>

    {{-- ── LEGEND ── --}}
    <div class="flex flex-wrap gap-3 text-xs">
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500 inline-block"></span>
            <span class="text-gray-500">Very High</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-orange-400 inline-block"></span>
            <span class="text-gray-500">High</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 inline-block"></span>
            <span class="text-gray-500">Medium</span>
        </div>
        <div class="flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-blue-400 inline-block"></span>
            <span class="text-gray-500">Low</span>
        </div>
        <div class="flex items-center gap-1.5 ml-4">
            <i class="fas fa-clock text-purple-500 text-xs"></i>
            <span class="text-gray-500">24 Hours (non-stop)</span>
        </div>
        <div class="flex items-center gap-1.5">
            <i class="fas fa-briefcase text-gray-400 text-xs"></i>
            <span class="text-gray-500">Business Hours (08:00–17:00)</span>
        </div>
    </div>

    {{-- ── POLICY TABLE ── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">
                SLA Policies
                <span id="policyCount" class="ml-2 text-xs font-normal text-gray-400"></span>
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scale</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Response (hrs)</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Resolution (hrs)</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Time Calculation</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody id="policyTableBody" class="divide-y divide-gray-100">
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- ── ADD POLICY MODAL ── --}}
<div id="addModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Add SLA Policy</h3>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            {{-- Customer --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1.5">
                    Customer <span class="text-gray-400 font-normal">(leave blank for global default)</span>
                </label>
                <select id="addCustomer"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                    <option value="">Default Global</option>
                    @foreach($customers as $c)
                        <option value="{{ $c->customer_id }}">
                            {{ $c->basicData->name_1 ?? $c->customer_code ?? 'Customer #'.$c->customer_id }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Priority & Scale --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Priority <span class="text-red-500">*</span></label>
                    <select id="addPriority"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        <option value="">Select priority</option>
                        <option value="Very High">1: Very High</option>
                        <option value="High">2: High</option>
                        <option value="Medium">3: Medium</option>
                        <option value="Low">4: Low</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Scale <span class="text-red-500">*</span></label>
                    <select id="addScale"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                        <option value="">Select scale</option>
                        <option value="Simple">Simple</option>
                        <option value="Medium">Medium</option>
                        <option value="Complex">Complex</option>
                    </select>
                </div>
            </div>

            {{-- Response & Resolution --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Response Target (hrs) <span class="text-red-500">*</span></label>
                    <input type="number" id="addResponse" min="0.1" step="0.5" placeholder="e.g: 1"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Resolution Target (hrs) <span class="text-red-500">*</span></label>
                    <input type="number" id="addResolution" min="0.1" step="0.5" placeholder="e.g: 8"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
            </div>

            {{-- 24h toggle --}}
            <div class="flex items-center gap-3 p-3 bg-purple-50 rounded-lg border border-purple-100">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="add24Hours" class="sr-only peer">
                    <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-purple-500
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all
                                peer-checked:after:translate-x-5"></div>
                </label>
                <div>
                    <p class="text-sm font-medium text-gray-700">Count Full 24 Hours</p>
                    <p class="text-xs text-gray-500">Enable for Very High priority (does not skip nights/weekends)</p>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex gap-2 justify-end">
            <button onclick="closeAddModal()"
                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition">Cancel</button>
            <button onclick="submitAddPolicy()"
                class="px-4 py-2 text-sm font-medium text-white rounded-lg transition"
                style="background-color: var(--primary-color);">
                <i class="fas fa-save mr-1.5"></i>Save Policy
            </button>
        </div>
    </div>
</div>

{{-- ── EDIT POLICY MODAL ── --}}
<div id="editModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">Edit SLA Policy</h3>
                <p class="text-xs text-gray-400 mt-0.5" id="editModalSubtitle"></p>
            </div>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <input type="hidden" id="editPolicyId">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Response Target (hrs) <span class="text-red-500">*</span></label>
                    <input type="number" id="editResponse" min="0.1" step="0.5"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Resolution Target (hrs) <span class="text-red-500">*</span></label>
                    <input type="number" id="editResolution" min="0.1" step="0.5"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>
            </div>

            <div class="flex items-center gap-3 p-3 bg-purple-50 rounded-lg border border-purple-100">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="edit24Hours" class="sr-only peer">
                    <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-purple-500
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all
                                peer-checked:after:translate-x-5"></div>
                </label>
                <div>
                    <p class="text-sm font-medium text-gray-700">Count Full 24 Hours</p>
                    <p class="text-xs text-gray-500">Enable for Very High priority</p>
                </div>
            </div>

            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" id="editIsActive" class="sr-only peer">
                    <div class="w-10 h-5 bg-gray-200 rounded-full peer peer-checked:bg-green-500
                                after:content-[''] after:absolute after:top-0.5 after:left-0.5
                                after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all
                                peer-checked:after:translate-x-5"></div>
                </label>
                <div>
                    <p class="text-sm font-medium text-gray-700">Policy Active</p>
                    <p class="text-xs text-gray-500">Inactive policy will not be applied to new tickets</p>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex gap-2 justify-end">
            <button onclick="closeEditModal()"
                class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900 transition">Cancel</button>
            <button onclick="submitEditPolicy()"
                class="px-4 py-2 text-sm font-medium text-white rounded-lg transition"
                style="background-color: var(--primary-color);">
                <i class="fas fa-save mr-1.5"></i>Save Changes
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const PRIORITY_COLORS = {
    'Very High': { dot: 'bg-red-500',    badge: 'bg-red-50 text-red-700 border-red-200' },
    'High':      { dot: 'bg-orange-400', badge: 'bg-orange-50 text-orange-700 border-orange-200' },
    'Medium':    { dot: 'bg-yellow-400', badge: 'bg-yellow-50 text-yellow-700 border-yellow-200' },
    'Low':       { dot: 'bg-blue-400',   badge: 'bg-blue-50 text-blue-700 border-blue-200' },
};
const SCALE_BADGE = {
    'Simple':  'bg-green-50 text-green-700 border-green-200',
    'Medium':  'bg-sky-50 text-sky-700 border-sky-200',
    'Complex': 'bg-violet-50 text-violet-700 border-violet-200',
};

let allPolicies = [];

async function loadPolicies() {
    const customer = document.getElementById('filterCustomer').value;
    const params   = customer ? `?customer_id=${customer}` : '';

    try {
        const res  = await fetch(`/api/admin/sla/policies${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        allPolicies = json.data;
        renderTable(allPolicies);
    } catch (e) {
        showToast('Failed to load policies: ' + e.message, 'error');
    }
}

function renderTable(data) {
    const tbody = document.getElementById('policyTableBody');
    document.getElementById('policyCount').textContent = `(${data.length} policies)`;

    if (!data.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">
            <i class="fas fa-inbox mr-2"></i>No SLA policies yet
        </td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(p => {
        const pc    = PRIORITY_COLORS[p.priority] ?? { dot: 'bg-gray-400', badge: 'bg-gray-50 text-gray-600 border-gray-200' };
        const sc    = SCALE_BADGE[p.scale] ?? 'bg-gray-50 text-gray-600 border-gray-200';
        const hours = p.is_24_hours
            ? `<span class="text-xs text-purple-600 font-medium"><i class="fas fa-clock mr-1"></i>24 hrs</span>`
            : `<span class="text-xs text-gray-500"><i class="fas fa-briefcase mr-1"></i>Business Hours</span>`;
        const status = p.is_active
            ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Active</span>`
            : `<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500 border border-gray-200">Inactive</span>`;

        return `<tr class="hover:bg-gray-50 transition ${!p.is_active ? 'opacity-50' : ''}">
            <td class="px-4 py-3">
                <span class="font-medium text-gray-800 text-sm">${p.customer_name}</span>
                ${p.customer_id === null ? '<span class="ml-1.5 text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">global</span>' : ''}
            </td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${pc.badge}">
                    <span class="w-1.5 h-1.5 rounded-full ${pc.dot}"></span>
                    ${p.priority}
                </span>
            </td>
            <td class="px-4 py-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium border ${sc}">${p.scale}</span>
            </td>
            <td class="px-4 py-3 text-center">
                <span class="text-sm font-semibold text-gray-800">${p.response_hours}</span>
                <span class="text-xs text-gray-400 ml-0.5">hrs</span>
            </td>
            <td class="px-4 py-3 text-center">
                <span class="text-sm font-semibold text-gray-800">${p.resolution_hours}</span>
                <span class="text-xs text-gray-400 ml-0.5">hrs</span>
            </td>
            <td class="px-4 py-3 text-center">${hours}</td>
            <td class="px-4 py-3 text-center">${status}</td>
            <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button onclick="openEditModal(${JSON.stringify(p).replace(/"/g, '&quot;')})"
                        class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center transition" title="Edit">
                        <i class="fas fa-pen text-xs"></i>
                    </button>
                    <button onclick="deletePolicy(${p.id}, '${p.customer_name} • ${p.priority} • ${p.scale}')"
                        class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition" title="Delete">
                        <i class="fas fa-trash text-xs"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ── MODAL ADD ──────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('addCustomer').value  = '';
    document.getElementById('addPriority').value  = '';
    document.getElementById('addScale').value     = '';
    document.getElementById('addResponse').value  = '';
    document.getElementById('addResolution').value = '';
    document.getElementById('add24Hours').checked = false;
    document.getElementById('addModal').classList.remove('hidden');
}
function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }

async function submitAddPolicy() {
    const priority   = document.getElementById('addPriority').value;
    const scale      = document.getElementById('addScale').value;
    const response   = document.getElementById('addResponse').value;
    const resolution = document.getElementById('addResolution').value;

    if (!priority || !scale || !response || !resolution) {
        showToast('Priority, Scale, Response, and Resolution are required.', 'warning');
        return;
    }

    const body = {
        customer_id:      document.getElementById('addCustomer').value || null,
        priority,
        scale,
        response_hours:   parseFloat(response),
        resolution_hours: parseFloat(resolution),
        is_24_hours:      document.getElementById('add24Hours').checked,
    };

    try {
        const res  = await fetch('/api/admin/sla/policies', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        showToast(json.message, 'success');
        closeAddModal();
        loadPolicies();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ── MODAL EDIT ─────────────────────────────────────────────────────────────
function openEditModal(p) {
    document.getElementById('editPolicyId').value       = p.id;
    document.getElementById('editModalSubtitle').textContent = `${p.customer_name} • ${p.priority} • ${p.scale}`;
    document.getElementById('editResponse').value       = p.response_hours;
    document.getElementById('editResolution').value     = p.resolution_hours;
    document.getElementById('edit24Hours').checked      = p.is_24_hours;
    document.getElementById('editIsActive').checked     = p.is_active;
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

async function submitEditPolicy() {
    const id         = document.getElementById('editPolicyId').value;
    const response   = document.getElementById('editResponse').value;
    const resolution = document.getElementById('editResolution').value;

    if (!response || !resolution) {
        showToast('Response and Resolution are required.', 'warning');
        return;
    }

    const body = {
        response_hours:   parseFloat(response),
        resolution_hours: parseFloat(resolution),
        is_24_hours:      document.getElementById('edit24Hours').checked,
        is_active:        document.getElementById('editIsActive').checked,
    };

    try {
        const res  = await fetch(`/api/admin/sla/policies/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        showToast(json.message, 'success');
        closeEditModal();
        loadPolicies();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// ── DELETE ─────────────────────────────────────────────────────────────────
async function deletePolicy(id, label) {
    if (!confirm(`Delete policy "${label}"?\nMake sure it has not been used by any ticket.`)) return;

    try {
        const res  = await fetch(`/api/admin/sla/policies/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        showToast(json.message, 'success');
        loadPolicies();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// Close modal on backdrop click
['addModal','editModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});

document.addEventListener('DOMContentLoaded', () => loadPolicies());
</script>
@endpush
