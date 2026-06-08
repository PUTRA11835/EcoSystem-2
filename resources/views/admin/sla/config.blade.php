@extends('dashboard')

@section('title', 'SLA Configuration')
@section('page-title', 'SLA Configuration')
@section('page-subtitle', 'Manage Service Level Agreement policies per customer, priority and scale')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="space-y-5">

    {{-- ── KPI Cards ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
            $kpiCards = [
                ['id'=>'kpiTotal',  'icon'=>'fa-file-contract', 'bg'=>'bg-red-50',    'color'=>'text-red-600',    'label'=>'Total Policies'],
                ['id'=>'kpiActive', 'icon'=>'fa-check-circle',  'bg'=>'bg-green-50',  'color'=>'text-green-600',  'label'=>'Active'],
                ['id'=>'kpiGlobal', 'icon'=>'fa-globe',         'bg'=>'bg-purple-50', 'color'=>'text-purple-600', 'label'=>'Global Defaults'],
                ['id'=>'kpi24h',    'icon'=>'fa-infinity',      'bg'=>'bg-blue-50',   'color'=>'text-blue-600',   'label'=>'24/7 Policies'],
            ];
        @endphp
        @foreach($kpiCards as $k)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-3">
                <div class="w-9 h-9 rounded-xl {{ $k['bg'] }} flex items-center justify-center">
                    <i class="fas {{ $k['icon'] }} {{ $k['color'] }} text-sm"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800" id="{{ $k['id'] }}">—</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $k['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── Table Card ───────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        {{-- Toolbar --}}
        <div class="px-5 py-4 border-b border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                {{-- Left: title --}}
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-stopwatch text-red-600 text-sm"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">SLA Policies</p>
                        <p class="text-xs text-gray-400">Response &amp; resolution time targets per customer</p>
                    </div>
                </div>

                {{-- Right: filter + action --}}
                <div class="flex items-center gap-2">

                    {{-- Customer filter (custom-dd) --}}
                    <div class="custom-dd relative" data-onchange="loadPolicies" data-fixed="true" style="min-width:160px">
                        <button type="button" class="custom-dd-btn w-full flex items-center justify-between pl-3 pr-2.5 py-1.5 bg-white border border-gray-200 rounded-lg text-xs text-gray-500 hover:border-gray-400 transition-all text-left gap-2">
                            <span class="custom-dd-label">All Customers</span>
                            <svg class="custom-dd-arrow w-3.5 h-3.5 text-gray-400 transition-transform duration-200 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <input type="hidden" id="filterCustomer" value="">
                        <div class="custom-dd-panel hidden absolute top-full right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 py-1.5 overflow-y-auto" style="max-height:260px; min-width:200px;">
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm font-medium text-gray-900 bg-gray-50 hover:bg-gray-50 transition-colors" data-value="">All Customers</button>
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="global">
                                <span class="inline-flex items-center gap-1.5"><i class="fas fa-globe text-purple-500 text-xs"></i>Global (Default)</span>
                            </button>
                            <div class="border-t border-gray-100 my-1"></div>
                            @foreach($customers as $c)
                            <button type="button" class="custom-dd-item w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-50 transition-colors" data-value="{{ $c->customer_id }}">
                                {{ optional($c->basicData)->name_1 ?? 'Customer #'.$c->customer_id }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    @if($canManage)
                    <button onclick="openAddModal()"
                        class="inline-flex items-center gap-1.5 bg-red-700 hover:bg-red-800 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg transition whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> Add Policy
                    </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Policy</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Customer</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="text-left px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Scale</th>
                        <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Response</th>
                        <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider whitespace-nowrap">Resolution</th>
                        <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Mode</th>
                        <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                        @if($canManage)
                        <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody id="policyTableBody">
                    <tr>
                        <td colspan="{{ $canManage ? 9 : 8 }}" class="py-16 text-center">
                            <div class="flex flex-col items-center gap-2 text-gray-300">
                                <i class="fas fa-spinner fa-spin text-3xl"></i>
                                <p class="text-sm">Loading policies...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Modal: Add Policy ────────────────────────────────────────────────────── --}}
<div id="addModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeAddModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                        <i class="fas fa-plus text-red-600 text-xs"></i>
                    </div>
                    <h3 class="text-sm font-semibold text-gray-800">Add SLA Policy</h3>
                </div>
                <button onclick="closeAddModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <form id="addPolicyForm" onsubmit="submitAddPolicy(event)" class="p-6 space-y-4">

                {{-- Label preview --}}
                <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 flex items-center gap-2">
                    <span class="text-xs text-gray-400 flex-shrink-0">Label preview:</span>
                    <span id="labelPreview" class="text-sm font-mono font-semibold text-red-700">—</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Customer</label>
                    <select name="customer_id" id="addCustomer" onchange="updatePreview()"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                        <option value="">Global (applies to all customers)</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->customer_id }}" data-name="{{ optional($c->basicData)->name_1 ?? 'Customer' }}">
                                {{ optional($c->basicData)->name_1 ?? 'Customer #'.$c->customer_id }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Priority</label>
                        <select name="priority" id="addPriority" required onchange="updatePreview()"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="Very High">1: Very High</option>
                            <option value="High">2: High</option>
                            <option value="Medium" selected>3: Medium</option>
                            <option value="Low">4: Low</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Scale</label>
                        <select name="scale" id="addScale" required onchange="updatePreview()"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                            <option value="Simple" selected>Simple</option>
                            <option value="Medium">Medium</option>
                            <option value="Complex">Complex</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Response (Hour)</label>
                        <input type="number" name="response_hours" step="0.5" min="0.5" required placeholder="e.g. 4"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Resolution (Hour)</label>
                        <input type="number" name="resolution_hours" step="0.5" min="0.5" required placeholder="e.g. 24"
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-300">
                    </div>
                </div>

                <label id="add24hLabel" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" name="is_24_hours" id="add24h" class="mt-0.5 w-4 h-4 rounded accent-red-700">
                    <div>
                        <p class="text-sm font-medium text-gray-700">24/7 Calendar Hours</p>
                        <p class="text-xs text-gray-400 mt-0.5" id="add24hNote">Count all hours; otherwise business hours only (Mon–Fri 09:00–18:00)</p>
                    </div>
                </label>

                <div id="addError" class="hidden items-center gap-2 bg-red-50 rounded-xl px-4 py-3 border border-red-100">
                    <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0 text-xs"></i>
                    <span id="addErrorText" class="text-xs text-red-600"></span>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeAddModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="addSubmitBtn"
                        class="flex-1 bg-red-700 hover:bg-red-800 text-white rounded-xl py-2.5 text-sm font-semibold transition">Save Policy</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Edit Policy ───────────────────────────────────────────────────── --}}
<div id="editModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeEditModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <i class="fas fa-edit text-blue-600 text-xs"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Edit SLA Policy</h3>
                        <p id="editPolicyLabel" class="text-xs font-mono text-red-600 mt-0.5 font-semibold"></p>
                    </div>
                </div>
                <button onclick="closeEditModal()" class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <form id="editPolicyForm" onsubmit="submitEditPolicy(event)" class="p-6 space-y-4">
                <input type="hidden" id="editPolicyId">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Response (Hour)</label>
                        <input type="number" id="editResponseHours" step="0.5" min="0.5" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Resolution (Hour)</label>
                        <input type="number" id="editResolutionHours" step="0.5" min="0.5" required
                            class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                </div>

                <label id="edit24hLabel" class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" id="edit24h" class="mt-0.5 w-4 h-4 rounded accent-red-700">
                    <div>
                        <p class="text-sm font-medium text-gray-700">24/7 Calendar Hours</p>
                        <p class="text-xs text-gray-400 mt-0.5" id="edit24hNote">Count all hours instead of business hours</p>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3 rounded-xl border border-gray-200 bg-gray-50/80 cursor-pointer hover:bg-gray-100 transition">
                    <input type="checkbox" id="editIsActive" class="mt-0.5 w-4 h-4 rounded accent-green-600" checked>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Policy Active</p>
                        <p class="text-xs text-gray-400 mt-0.5">Inactive policies are not applied to new tickets</p>
                    </div>
                </label>

                <div id="editError" class="hidden items-center gap-2 bg-red-50 rounded-xl px-4 py-3 border border-red-100">
                    <i class="fas fa-exclamation-circle text-red-500 flex-shrink-0 text-xs"></i>
                    <span id="editErrorText" class="text-xs text-red-600"></span>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="button" onclick="closeEditModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" id="editSubmitBtn"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ── Modal: Delete Confirmation ───────────────────────────────────────────── --}}
<div id="deleteModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="closeDeleteModal()"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm">
            <div class="p-6 text-center">
                <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-trash text-red-500 text-xl"></i>
                </div>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Delete Policy?</h3>
                <p class="text-sm text-gray-500 mb-1">You are about to delete:</p>
                <p id="deletePolicyLabel" class="text-sm font-mono font-bold text-red-600 mb-4"></p>
                <p class="text-xs text-gray-400 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mb-5">
                    <i class="fas fa-exclamation-triangle text-amber-500 mr-1"></i>
                    This cannot be undone. Make sure no active tickets use this policy.
                </p>
                <div class="flex gap-3">
                    <button onclick="closeDeleteModal()"
                        class="flex-1 border border-gray-200 rounded-xl py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                    <button id="deleteConfirmBtn" onclick="confirmDelete()"
                        class="flex-1 bg-red-600 hover:bg-red-700 text-white rounded-xl py-2.5 text-sm font-semibold transition">
                        <i class="fas fa-trash mr-1.5"></i>Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const CAN_MANAGE = {{ $canManage ? 'true' : 'false' }};
const COL_COUNT  = CAN_MANAGE ? 9 : 8;

const PRIO_NUM   = { 'Very High': 1, 'High': 2, 'Medium': 3, 'Low': 4 };
const PRIO_LABEL = { 'Very High': '1: Very High', 'High': '2: High', 'Medium': '3: Medium', 'Low': '4: Low' };
const PRIO_CFG   = {
    'Very High': { bg: 'bg-red-50',    text: 'text-red-700',    dot: 'bg-red-500'    },
    'High':      { bg: 'bg-orange-50', text: 'text-orange-700', dot: 'bg-orange-500' },
    'Medium':    { bg: 'bg-yellow-50', text: 'text-yellow-700', dot: 'bg-yellow-500' },
    'Low':       { bg: 'bg-blue-50',   text: 'text-blue-700',   dot: 'bg-blue-400'   },
};

function makeLabel(customerName, priority, scale) {
    const short = customerName ? customerName.toUpperCase().replace(/\s+/g,'').substring(0,10) : 'GLOBAL';
    return `${short}${PRIO_NUM[priority] || '?'}: ${priority} / ${scale}`;
}

// ── Preview (Add modal) ───────────────────────────────────────────────────────
function updatePreview() {
    const custSel  = document.getElementById('addCustomer');
    const custName = custSel.selectedIndex > 0 ? custSel.options[custSel.selectedIndex].dataset.name : null;
    const priority = document.getElementById('addPriority').value;
    const scale    = document.getElementById('addScale').value;
    const is24hEl  = document.getElementById('add24h');
    const labelEl  = document.getElementById('add24hLabel');
    const noteEl   = document.getElementById('add24hNote');

    if (priority === 'Very High') {
        is24hEl.checked  = true;
        is24hEl.disabled = true;
        labelEl.classList.add('opacity-75', 'cursor-not-allowed');
        labelEl.classList.remove('cursor-pointer', 'hover:bg-gray-100');
        noteEl.textContent = 'Wajib 24/7 — priority Very High selalu menggunakan kalender penuh.';
    } else {
        is24hEl.disabled = false;
        labelEl.classList.remove('opacity-75', 'cursor-not-allowed');
        labelEl.classList.add('cursor-pointer', 'hover:bg-gray-100');
        noteEl.textContent = 'Count all hours; otherwise business hours only (Mon–Fri 09:00–18:00)';
    }
    document.getElementById('labelPreview').textContent = makeLabel(custName, priority, scale);
}

// ── Load & Render ─────────────────────────────────────────────────────────────
async function loadPolicies() {
    const cid   = document.getElementById('filterCustomer').value;
    const url   = '/api/admin/sla/policies' + (cid ? '?customer_id=' + cid : '');
    const tbody = document.getElementById('policyTableBody');

    tbody.innerHTML = `<tr><td colspan="${COL_COUNT}" class="py-16 text-center">
        <div class="flex flex-col items-center gap-2 text-gray-300">
            <i class="fas fa-spinner fa-spin text-3xl"></i>
            <p class="text-sm">Loading...</p>
        </div></td></tr>`;

    try {
        const res  = await fetch(url, { credentials: 'include' });
        const json = await res.json();
        renderPolicies(json.data || []);
    } catch {
        tbody.innerHTML = `<tr><td colspan="${COL_COUNT}" class="py-12 text-center">
            <div class="flex flex-col items-center gap-2 text-red-400">
                <i class="fas fa-exclamation-triangle text-3xl"></i>
                <p class="text-sm">Failed to load policies.</p>
            </div></td></tr>`;
    }
}

function renderPolicies(policies) {
    document.getElementById('kpiTotal').textContent  = policies.length;
    document.getElementById('kpiActive').textContent = policies.filter(p => p.is_active).length;
    document.getElementById('kpiGlobal').textContent = policies.filter(p => !p.customer_id).length;
    document.getElementById('kpi24h').textContent    = policies.filter(p => p.is_24_hours).length;

    const tbody = document.getElementById('policyTableBody');

    if (!policies.length) {
        tbody.innerHTML = `<tr><td colspan="${COL_COUNT}" class="py-16 text-center">
            <div class="flex flex-col items-center gap-2">
                <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mb-1">
                    <i class="fas fa-file-contract text-gray-300 text-2xl"></i>
                </div>
                <p class="text-sm font-medium text-gray-400">No policies found</p>
                <p class="text-xs text-gray-300">Click "Add Policy" to create your first SLA policy</p>
            </div></td></tr>`;
        return;
    }

    // Group rows by customer
    let lastCustomer = '__init__';
    let rowBg = 'bg-white';

    tbody.innerHTML = policies.map(p => {
        if (p.customer_name !== lastCustomer) {
            lastCustomer = p.customer_name;
            rowBg = rowBg === 'bg-white' ? 'bg-gray-50/50' : 'bg-white';
        }

        const prioConf = PRIO_CFG[p.priority] || { bg:'bg-gray-100', text:'text-gray-600', dot:'bg-gray-400' };
        const label    = makeLabel(p.customer_name, p.priority, p.scale);

        const custCell = p.customer_name
            ? `<div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-700">${p.customer_name}</span>
               </div>`
            : `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full">
                <i class="fas fa-globe text-[9px]"></i>Global
               </span>`;

        const modeCell = p.is_24_hours
            ? `<span class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full"><i class="fas fa-infinity text-[9px]"></i>24/7</span>`
            : `<span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">Business</span>`;

        const statusCell = p.is_active
            ? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700 bg-green-50 px-2.5 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Active
               </span>`
            : `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 bg-gray-100 px-2.5 py-0.5 rounded-full">
                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Inactive
               </span>`;

        const actionsCell = CAN_MANAGE
            ? `<div class="flex items-center justify-center gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                <button onclick='openEditModal(${JSON.stringify(p)})' title="Edit"
                    class="w-7 h-7 rounded-lg border border-gray-200 hover:border-blue-300 hover:bg-blue-50 flex items-center justify-center text-gray-400 hover:text-blue-600 transition">
                    <i class="fas fa-edit text-xs"></i>
                </button>
                <button onclick="deletePolicy(${p.id}, '${label.replace(/'/g, "\\'")}')" title="Delete"
                    class="w-7 h-7 rounded-lg border border-gray-200 hover:border-red-300 hover:bg-red-50 flex items-center justify-center text-gray-400 hover:text-red-500 transition">
                    <i class="fas fa-trash text-xs"></i>
                </button>
               </div>`
            : '';

        return `
        <tr class="${rowBg} border-b border-gray-100 hover:bg-red-50/20 transition-colors group">
            <td class="px-4 py-3">
                <span class="text-xs font-mono font-semibold text-gray-500 whitespace-nowrap">${label}</span>
            </td>
            <td class="px-4 py-3">${custCell}</td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold ${prioConf.text} ${prioConf.bg} px-2.5 py-1 rounded-full whitespace-nowrap">
                    <span class="w-1.5 h-1.5 rounded-full ${prioConf.dot}"></span>${PRIO_LABEL[p.priority] || p.priority}
                </span>
            </td>
            <td class="px-4 py-3">
                <span class="text-xs font-medium text-gray-600 bg-gray-100 px-2 py-0.5 rounded">${p.scale}</span>
            </td>
            <td class="px-4 py-3 text-center">
                <div class="leading-tight">
                    <p class="text-sm font-bold text-gray-800">${p.response_hours}</p>
                    <p class="text-[10px] text-gray-400">hours</p>
                </div>
            </td>
            <td class="px-4 py-3 text-center">
                <div class="leading-tight">
                    <p class="text-sm font-bold text-gray-800">${p.resolution_hours}</p>
                    <p class="text-[10px] text-gray-400">hours</p>
                </div>
            </td>
            <td class="px-4 py-3 text-center">${modeCell}</td>
            <td class="px-4 py-3 text-center">${statusCell}</td>
            ${CAN_MANAGE ? `<td class="px-4 py-3 text-center">${actionsCell}</td>` : ''}
        </tr>`;
    }).join('');
}

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('addPolicyForm').reset();
    hideErr('add');
    updatePreview();
    document.getElementById('addModal').classList.remove('hidden');
}
function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }

function openEditModal(p) {
    document.getElementById('editPolicyId').value        = p.id;
    document.getElementById('editResponseHours').value   = p.response_hours;
    document.getElementById('editResolutionHours').value = p.resolution_hours;
    document.getElementById('editIsActive').checked      = p.is_active;
    document.getElementById('editPolicyLabel').textContent = makeLabel(p.customer_name, p.priority, p.scale);

    const el24    = document.getElementById('edit24h');
    const lbl24   = document.getElementById('edit24hLabel');
    const note24  = document.getElementById('edit24hNote');

    if (p.priority === 'Very High') {
        el24.checked  = true; el24.disabled = true;
        lbl24.classList.add('opacity-75','cursor-not-allowed');
        lbl24.classList.remove('cursor-pointer','hover:bg-gray-100');
        note24.textContent = 'Wajib 24/7 — priority Very High selalu menggunakan kalender penuh.';
    } else {
        el24.checked  = p.is_24_hours; el24.disabled = false;
        lbl24.classList.remove('opacity-75','cursor-not-allowed');
        lbl24.classList.add('cursor-pointer','hover:bg-gray-100');
        note24.textContent = 'Count all hours instead of business hours';
    }

    hideErr('edit');
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

function showErr(pfx, msg) {
    const box = document.getElementById(pfx + 'Error');
    box.classList.remove('hidden'); box.classList.add('flex');
    document.getElementById(pfx + 'ErrorText').textContent = msg;
}
function hideErr(pfx) {
    const box = document.getElementById(pfx + 'Error');
    box.classList.add('hidden'); box.classList.remove('flex');
}

// ── API ───────────────────────────────────────────────────────────────────────
function csrf() { return document.querySelector('meta[name=csrf-token]').content; }

async function submitAddPolicy(e) {
    e.preventDefault();
    hideErr('add');
    const form = e.target;
    const btn  = document.getElementById('addSubmitBtn');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
        const res  = await fetch('/api/admin/sla/policies', {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                customer_id:      form.customer_id.value || null,
                priority:         form.priority.value,
                scale:            form.scale.value,
                response_hours:   parseFloat(form.response_hours.value),
                resolution_hours: parseFloat(form.resolution_hours.value),
                is_24_hours:      form.is_24_hours.checked,
            }),
        });
        const json = await res.json();
        if (!json.success) { showErr('add', json.message || 'Failed to save.'); return; }
        closeAddModal();
        loadPolicies();
    } catch { showErr('add', 'A network error occurred.'); }
    finally { btn.disabled = false; btn.textContent = 'Save Policy'; }
}

async function submitEditPolicy(e) {
    e.preventDefault();
    hideErr('edit');
    const id  = document.getElementById('editPolicyId').value;
    const btn = document.getElementById('editSubmitBtn');
    btn.disabled = true; btn.textContent = 'Saving...';
    try {
        const res  = await fetch('/api/admin/sla/policies/' + id, {
            method: 'PUT', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                response_hours:   parseFloat(document.getElementById('editResponseHours').value),
                resolution_hours: parseFloat(document.getElementById('editResolutionHours').value),
                is_24_hours:      document.getElementById('edit24h').checked,
                is_active:        document.getElementById('editIsActive').checked,
            }),
        });
        const json = await res.json();
        if (!json.success) { showErr('edit', json.message || 'Failed to update.'); return; }
        closeEditModal();
        loadPolicies();
    } catch { showErr('edit', 'A network error occurred.'); }
    finally { btn.disabled = false; btn.textContent = 'Save Changes'; }
}

let _deleteId = null, _deleteLabel = null;

function deletePolicy(id, label) {
    _deleteId    = id;
    _deleteLabel = label;
    document.getElementById('deletePolicyLabel').textContent = label;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    _deleteId = _deleteLabel = null;
}
async function confirmDelete() {
    if (!_deleteId) return;
    const btn = document.getElementById('deleteConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1.5"></i>Deleting...';
    try {
        const res  = await fetch('/api/admin/sla/policies/' + _deleteId, {
            method: 'DELETE', credentials: 'include',
            headers: { 'X-CSRF-TOKEN': csrf() },
        });
        const json = await res.json();
        closeDeleteModal();
        if (!json.success) { showToast(json.message || 'Failed to delete.', 'error'); return; }
        showToast('Policy deleted successfully.', 'success');
        loadPolicies();
    } catch {
        closeDeleteModal();
        showToast('A network error occurred.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-trash mr-1.5"></i>Yes, Delete';
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeAddModal(); closeEditModal(); closeDeleteModal(); }
});

loadPolicies();
updatePreview();
</script>

@php $customDdVer = file_exists(public_path('js/custom-dropdown.js')) ? filemtime(public_path('js/custom-dropdown.js')) : time(); @endphp
<script src="/js/custom-dropdown.js?v={{ $customDdVer }}"></script>
<script>document.addEventListener('DOMContentLoaded', () => initCustomDropdowns());</script>

@endsection
