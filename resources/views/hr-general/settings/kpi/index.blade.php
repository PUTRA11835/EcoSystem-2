@extends('dashboard')

@section('title', 'KPI Settings — Templates')
@section('page-title', 'KPI Settings')

@section('content')
@php
    $user = session('user');
@endphp

<div class="space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    KPI Templates
                </h1>
                <p class="text-xs text-gray-500 mt-1">
                    Manage KPI evaluation templates. Each template is role-based with weighted indicators that must sum to 100%.
                </p>
            </div>
            @if($canManage)
            <button onclick="openCreateModal()"
                class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90 transition-all">
                <i class="fas fa-plus text-xs"></i> New Template
            </button>
            @endif
        </div>
    </div>

    {{-- ── Summary --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-3xl font-bold text-gray-900">{{ $templates->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Total Templates</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-3xl font-bold text-green-600">{{ $templates->where('is_active', true)->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Active</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-3xl font-bold text-gray-400">{{ $templates->where('is_active', false)->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Inactive</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ $roles->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Roles Available</div>
        </div>
    </div>

    {{-- ── Template List & Search / Filter Toolbar ────────────────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                    <span>All Templates</span>
                    <span id="templateCountBadge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#00c5a2]/15 text-[#00a88a]">
                        {{ $templates->count() }}
                    </span>
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">Filter templates by keyword, indicators, status, or period type</p>
            </div>

            {{-- Filter & Search Toolbar (Big Search Box + Small Dropdown Filters) --}}
            <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap w-full lg:w-auto lg:flex-1 lg:max-w-2xl justify-end">
                {{-- 1. Large, Prominent Search Box --}}
                <div class="relative flex-1 w-full min-w-[260px]">
                    <input type="text" id="templateSearchInput" value="{{ $search ?? '' }}"
                           placeholder="Search templates, indicators, descriptions..."
                           oninput="filterTemplates()"
                           class="w-full h-11 pl-11 pr-10 text-sm bg-gray-50 hover:bg-white focus:bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-[#00c5a2] focus:border-[#00c5a2] transition-all shadow-sm">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 pointer-events-none">
                        <i class="fas fa-search text-base"></i>
                    </div>
                    <button type="button" id="clearSearchBtn" onclick="clearSearchInput()"
                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-gray-400 hover:text-gray-600 {{ empty($search) ? 'hidden' : '' }}">
                        <i class="fas fa-times-circle text-sm"></i>
                    </button>
                </div>

                {{-- 2. Status Filter (Visibly Smaller than Search Box) --}}
                <select id="templateStatusFilter" onchange="filterTemplates()"
                        class="h-8 px-2.5 text-xs font-medium text-gray-600 bg-gray-50 hover:bg-white border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#00c5a2] focus:border-[#00c5a2] transition-all cursor-pointer shadow-sm shrink-0">
                    <option value="">Status: All</option>
                    <option value="active" {{ ($statusFilter ?? '') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ ($statusFilter ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>

                {{-- 3. Period Type Filter (Visibly Smaller than Search Box) --}}
                <select id="templatePeriodFilter" onchange="filterTemplates()"
                        class="h-8 px-2.5 text-xs font-medium text-gray-600 bg-gray-50 hover:bg-white border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#00c5a2] focus:border-[#00c5a2] transition-all cursor-pointer shadow-sm shrink-0">
                    <option value="">Period: All</option>
                    <option value="monthly" {{ ($periodTypeFilter ?? '') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="quarterly" {{ ($periodTypeFilter ?? '') === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                    <option value="annual" {{ ($periodTypeFilter ?? '') === 'annual' ? 'selected' : '' }}>Annual</option>
                </select>
            </div>
        </div>

        @if($templates->count() > 0)
        <div class="divide-y divide-gray-50" id="templateListContainer">
            @foreach($templates as $tmpl)
            <div class="template-item p-5 hover:bg-gray-50/50 transition-colors"
                 id="tmpl-{{ $tmpl->id }}"
                 data-name="{{ strtolower($tmpl->name) }}"
                 data-desc="{{ strtolower($tmpl->description ?? '') }}"
                 data-period="{{ strtolower($tmpl->period_type) }}"
                 data-status="{{ $tmpl->is_active ? 'active' : 'inactive' }}"
                 data-indicators="{{ strtolower($tmpl->indicators->pluck('name')->implode(' ')) }}">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div class="flex items-start gap-3 flex-1">
                        <div class="w-10 h-10 rounded-xl {{ $tmpl->is_active ? 'primary-gradient' : 'bg-gray-200' }} flex items-center justify-center text-white text-sm shrink-0 shadow-sm">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-gray-900">{{ $tmpl->name }}</p>
                                @if(!$tmpl->is_active)
                                <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-medium">Inactive</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                                <span><i class="fas fa-calendar-alt text-gray-400 mr-1"></i>{{ $tmpl->period_type_label }}</span>
                                <span><i class="fas fa-list-check text-gray-400 mr-1"></i>{{ $tmpl->indicators->count() }} indicators</span>
                                <span class="{{ abs($tmpl->total_weight - 100) < 0.01 ? 'text-green-600' : 'text-red-600' }} font-semibold">
                                    <i class="fas {{ abs($tmpl->total_weight - 100) < 0.01 ? 'fa-check-circle' : 'fa-exclamation-circle' }} mr-1"></i>
                                    Weight: {{ $tmpl->total_weight }}%
                                </span>
                                <span><i class="fas fa-chart-bar text-gray-400 mr-1"></i>{{ $tmpl->evaluations_count }} evaluation(s)</span>
                            </div>
                            @if($tmpl->description)
                            <p class="text-xs text-gray-400 mt-1.5">{{ Str::limit($tmpl->description, 100) }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    @if($canManage)
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button onclick="openEditModal({{ $tmpl->id }})"
                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100 border border-indigo-200 transition-all">
                            <i class="fas fa-edit text-xs"></i> Edit
                        </button>
                        <button onclick="toggleTemplate({{ $tmpl->id }})"
                            class="inline-flex items-center gap-1 px-3 py-1.5 {{ $tmpl->is_active ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 'bg-green-50 text-green-700 border-green-200' }} text-xs font-medium rounded-lg border hover:opacity-80 transition-all">
                            <i class="fas {{ $tmpl->is_active ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                            {{ $tmpl->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                        @if($tmpl->evaluations_count === 0)
                        <button onclick="deleteTemplate({{ $tmpl->id }}, '{{ addslashes($tmpl->name) }}')"
                            class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 border border-red-200 transition-all">
                            <i class="fas fa-trash-alt text-xs"></i>
                        </button>
                        @endif
                    </div>
                    @endif
                </div>

                {{-- Indicator preview --}}
                @if($tmpl->indicators->count() > 0)
                <div class="mt-3 ml-13">
                    <div class="flex flex-wrap gap-2 mt-1.5">
                        @foreach($tmpl->indicators->take(5) as $ind)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 rounded-full text-xs text-gray-600">
                            {{ $ind->name }}
                            <span class="font-bold text-indigo-600">{{ $ind->weight }}%</span>
                        </span>
                        @endforeach
                        @if($tmpl->indicators->count() > 5)
                        <span class="px-2.5 py-1 bg-gray-100 rounded-full text-xs text-gray-400">
                            +{{ $tmpl->indicators->count() - 5 }} more
                        </span>
                        @endif
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Dynamic No Match Alert --}}
        <div id="noTemplateMatch" class="py-16 text-center hidden">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3 text-xl">
                <i class="fas fa-search"></i>
            </div>
            <p class="text-gray-700 text-sm font-bold">No matching KPI templates found</p>
            <p class="text-gray-400 text-xs mt-1">Try adjusting your search keywords or clearing filters.</p>
            <a href="{{ route('general.settings.kpi.index') }}"
               class="mt-3.5 inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-xs font-semibold bg-[#00c5a2]/15 text-[#008f75] hover:bg-[#00c5a2]/25 transition-all shadow-sm">
                <i class="fas fa-undo text-[10px]"></i> Reset filters
            </a>
        </div>
        @else
        <div class="py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-layer-group text-gray-300 text-2xl"></i>
            </div>
            <p class="text-gray-500 font-medium">No templates yet</p>
            <p class="text-sm text-gray-400 mt-1">Create your first KPI template to start evaluating employees.</p>
            @if($canManage)
            <button onclick="openCreateModal()"
                class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90 transition-all">
                <i class="fas fa-plus text-xs"></i> Create First Template
            </button>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- ── Create/Edit Template Modal ───────────────────────────────────────────── --}}
@if($canManage)
<div id="templateModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-start justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl my-8">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-900" id="modalTitle">
                <i class="fas fa-layer-group text-indigo-500 mr-2"></i>
                New KPI Template
            </h3>
            <button onclick="closeTemplateModal()" class="w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center">
                <i class="fas fa-times text-gray-500"></i>
            </button>
        </div>
        <form id="templateForm" onsubmit="submitTemplate(event)" class="p-5 space-y-4">
            @csrf
            <input type="hidden" id="editTemplateId" name="_edit_id">
            <input type="hidden" id="formMethod" value="create">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Template Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="tmplName" required maxlength="200"
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 focus:border-red-400"
                        placeholder="e.g. Engineering Staff Monthly KPI">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Period Type <span class="text-red-500">*</span></label>
                    <select name="period_type" id="tmplPeriodType" required
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" id="tmplDescription" rows="2"
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 resize-none"
                        placeholder="Optional description..."></textarea>
                </div>
            </div>

            {{-- Indicators --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold text-gray-700">
                        KPI Indicators <span class="text-red-500">*</span>
                        <span class="text-gray-400 font-normal ml-1">(weights must sum to 100%)</span>
                    </label>
                    <span id="weightSumDisplay" class="text-xs font-bold text-gray-400">Total: 0%</span>
                </div>
                <div id="indicatorList" class="space-y-2">
                    {{-- rows added dynamically --}}
                </div>
                <button type="button" onclick="addIndicatorRow()"
                    class="mt-2 inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100 transition-all border border-indigo-200">
                    <i class="fas fa-plus text-xs"></i> Add Indicator
                </button>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeTemplateModal()"
                    class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</button>
                <button type="submit" id="templateSubmitBtn"
                    class="inline-flex items-center gap-2 px-6 py-2.5 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90">
                    <i class="fas fa-save text-xs"></i> <span id="submitBtnLabel">Create Template</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
let indicatorSeq = 0;

function addIndicatorRow(data = {}) {
    const seq = indicatorSeq++;
    const html = `
        <div class="indicator-row grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
            <div class="col-span-12 sm:col-span-5">
                <input type="text" name="indicators[${seq}][name]" value="${data.name ?? ''}" required
                    placeholder="Indicator name *"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <input type="text" name="indicators[${seq}][measurement_unit]" value="${data.measurement_unit ?? ''}"
                    placeholder="Unit (%, count...)"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="col-span-6 sm:col-span-2">
                <input type="number" name="indicators[${seq}][target_value]" value="${data.target_value ?? ''}"
                    placeholder="Target value" step="0.01"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
            </div>
            <div class="col-span-10 sm:col-span-2">
                <input type="number" name="indicators[${seq}][weight]" value="${data.weight ?? ''}" required
                    placeholder="Weight %" min="0.01" max="100" step="0.01"
                    class="weight-input w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 text-center font-bold"
                    oninput="updateWeightSum()">
            </div>
            <div class="col-span-2 sm:col-span-1 flex items-center justify-center">
                <button type="button" onclick="this.closest('.indicator-row').remove(); updateWeightSum();"
                    class="w-7 h-7 flex items-center justify-center bg-red-100 text-red-500 rounded-lg hover:bg-red-200 transition-all">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
            <div class="col-span-12">
                <input type="text" name="indicators[${seq}][description]" value="${data.description ?? ''}"
                    placeholder="Description (optional)"
                    class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
            </div>
        </div>
    `;
    document.getElementById('indicatorList').insertAdjacentHTML('beforeend', html);
    updateWeightSum();
}

function updateWeightSum() {
    const inputs = document.querySelectorAll('.weight-input');
    let total = 0;
    inputs.forEach(i => { const v = parseFloat(i.value); if (!isNaN(v)) total += v; });
    const el = document.getElementById('weightSumDisplay');
    el.textContent = `Total: ${total.toFixed(2)}%`;
    el.className = `text-xs font-bold ${Math.abs(total - 100) < 0.01 ? 'text-green-600' : (total > 100 ? 'text-red-600' : 'text-amber-600')}`;
}

function openCreateModal() {
    document.getElementById('templateForm').reset();
    document.getElementById('indicatorList').innerHTML = '';
    document.getElementById('editTemplateId').value = '';
    document.getElementById('formMethod').value = 'create';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-layer-group text-indigo-500 mr-2"></i> New KPI Template';
    document.getElementById('submitBtnLabel').textContent = 'Create Template';
    indicatorSeq = 0;
    addIndicatorRow(); // start with one row
    updateWeightSum();
    document.getElementById('templateModal').classList.remove('hidden');
}

async function openEditModal(id) {
    const res  = await fetch(`{{ url('/general/settings/kpi') }}/${id}/indicators`);
    const data = await res.json();

    if (data.template) {
        document.getElementById('tmplName').value        = data.template.name || '';
        document.getElementById('tmplPeriodType').value  = data.template.period_type || 'monthly';
        document.getElementById('tmplDescription').value = data.template.description || '';
    }
    document.getElementById('editTemplateId').value  = id;
    document.getElementById('formMethod').value      = 'edit';
    document.getElementById('modalTitle').innerHTML  = '<i class="fas fa-edit text-indigo-500 mr-2"></i> Edit KPI Template';
    document.getElementById('submitBtnLabel').textContent = 'Update Template';

    indicatorSeq = 0;
    document.getElementById('indicatorList').innerHTML = '';
    (data.indicators || []).forEach(ind => addIndicatorRow(ind));
    if ((data.indicators || []).length === 0) addIndicatorRow();
    updateWeightSum();
    document.getElementById('templateModal').classList.remove('hidden');
}

function closeTemplateModal() {
    document.getElementById('templateModal').classList.add('hidden');
}

async function submitTemplate(e) {
    e.preventDefault();
    const isEdit = document.getElementById('formMethod').value === 'edit';
    const editId = document.getElementById('editTemplateId').value;

    const btn = document.getElementById('templateSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-circle-notch fa-spin text-xs"></i> Saving...`;

    const url = isEdit
        ? `{{ url('/general/settings/kpi') }}/${editId}/update`
        : `{{ route('general.settings.kpi.store') }}`;

    const form = document.getElementById('templateForm');
    const res  = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: new FormData(form),
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) {
        setTimeout(() => location.reload(), 1000);
    } else {
        btn.disabled = false;
        btn.innerHTML = `<i class="fas fa-save text-xs"></i> <span id="submitBtnLabel">${isEdit ? 'Update Template' : 'Create Template'}</span>`;
    }
}

async function toggleTemplate(id) {
    const res  = await fetch(`{{ url('/general/settings/kpi') }}/${id}/toggle`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}

async function deleteTemplate(id, name) {
    if (!confirm(`Delete template "${name}"? This cannot be undone.`)) return;
    const res  = await fetch(`{{ url('/general/settings/kpi') }}/${id}/delete`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}

// ── Real-Time Client-Side Template Search & Filtering ─────────────────────────
function filterTemplates() {
    const q = (document.getElementById('templateSearchInput')?.value || '').trim().toLowerCase();
    const status = (document.getElementById('templateStatusFilter')?.value || '').toLowerCase();
    const period = (document.getElementById('templatePeriodFilter')?.value || '').toLowerCase();

    const clearBtn = document.getElementById('clearSearchBtn');
    if (clearBtn) clearBtn.classList.toggle('hidden', q.length === 0);

    const items = document.querySelectorAll('.template-item');
    let visibleCount = 0;

    items.forEach(item => {
        const name = item.getAttribute('data-name') || '';
        const desc = item.getAttribute('data-desc') || '';
        const inds = item.getAttribute('data-indicators') || '';
        const itemStatus = item.getAttribute('data-status') || '';
        const itemPeriod = item.getAttribute('data-period') || '';

        const matchesQuery = !q || name.includes(q) || desc.includes(q) || inds.includes(q);
        const matchesStatus = !status || itemStatus === status;
        const matchesPeriod = !period || itemPeriod === period;

        if (matchesQuery && matchesStatus && matchesPeriod) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    const badge = document.getElementById('templateCountBadge');
    if (badge) badge.textContent = visibleCount;

    const noMatch = document.getElementById('noTemplateMatch');
    if (noMatch) {
        noMatch.classList.toggle('hidden', visibleCount > 0);
    }
}

function clearSearchInput() {
    const sInput = document.getElementById('templateSearchInput');
    if (sInput) sInput.value = '';
    filterTemplates();
}

function resetAllFilters() {
    window.location.href = "{{ route('general.settings.kpi.index') }}";
}

function clearTemplateSearch() {
    resetAllFilters();
}

// Ensure filters are applied on initial page load if any query values are preset
document.addEventListener('DOMContentLoaded', function() {
    filterTemplates();
});
</script>
@endsection
