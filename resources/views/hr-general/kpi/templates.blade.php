@extends('dashboard')

@section('title', 'KPI Evaluation — Assessment Templates')
@section('page-title', 'KPI Evaluation')

@section('content')
@php
    $user = session('user');
    $canCreate = $canCreate ?? ($canManage ?? false);
    $canEdit   = $canEdit   ?? ($canManage ?? false);
    $canDelete = $canDelete ?? ($canManage ?? false);
    $selfCount = $templates->filter(fn($t) => ($t->target_type ?? 'supervisor') === 'self')->count();
    $leadCount = $templates->count() - $selfCount;
@endphp

<div class="space-y-5">

    {{-- ── Page Tab Strip (Dashboard | Assessment Templates) ─────────────────── --}}
    <div class="bg-white rounded-2xl p-1.5 shadow-sm border border-gray-100 flex items-center gap-1.5 w-full sm:w-auto">
        <a href="{{ route('general.kpi-evaluation.index') }}"
           class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-chart-bar mr-1.5"></i> Dashboard
        </a>
        <span class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold primary-gradient text-white shadow">
            <i class="fas fa-layer-group mr-1.5"></i> Assessment Templates
        </span>
        <a href="{{ route('general.kpi-evaluation.teams') }}"
           class="flex-1 sm:flex-none text-center px-4 py-2 rounded-xl text-xs font-bold text-gray-500 hover:bg-gray-50 transition-all">
            <i class="fas fa-sitemap mr-1.5"></i> Lead &amp; Project
        </a>
    </div>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                    <span class="w-9 h-9 rounded-xl primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                        <i class="fas fa-layer-group"></i>
                    </span>
                    Assessment Templates
                </h1>
                <p class="text-xs text-gray-500 mt-1">
                    Separate templates power the two tracks: <strong>Self-Assessment</strong> (filled by the
                    employee) and <strong>Lead Assessment</strong> (filled by the direct manager). Each template
                    carries its own scoring scale; indicator weights must sum to 100%.
                </p>
            </div>
            @if($canCreate)
            <a href="{{ route('general.kpi-evaluation.templates.create') }}"
                class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90 transition-all">
                <i class="fas fa-plus text-xs"></i> New Template
            </a>
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
            <div class="text-3xl font-bold text-purple-600">{{ $selfCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Self-Assessment</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-3xl font-bold text-indigo-600">{{ $leadCount }}</div>
            <div class="text-xs text-gray-500 mt-1">Lead Assessment</div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div class="text-3xl font-bold text-green-600">{{ $templates->where('is_active', true)->count() }}</div>
            <div class="text-xs text-gray-500 mt-1">Active</div>
        </div>
    </div>

    {{-- ── Template Table — filters live in the column headers ────────────────── --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-5 border-b border-gray-100 flex items-center gap-2">
            <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                <span>All Templates</span>
                <span id="templateCountBadge" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#00c5a2]/15 text-[#00a88a]">
                    {{ $templates->count() }}
                </span>
            </h3>
            <p class="text-xs text-gray-400">Use the <i class="fas fa-filter text-[10px]"></i> icons in the header to filter.</p>
        </div>

        {{-- filter state (client-side) --}}
        <input type="hidden" id="fSearch" value="">
        <input type="hidden" id="fType" value="">
        <input type="hidden" id="fPeriod" value="">
        <input type="hidden" id="fStatus" value="">

        @if($templates->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50/90 border-b border-gray-100 select-none">
                    <tr>
                        <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider w-10">No</th>

                        {{-- Template + search --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[260px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Template</span>
                                <button type="button" onclick="toggleHF(event,'hfSearch')" id="hfSearchBtn"
                                    class="p-1 rounded-md hover:bg-gray-200/70 text-gray-400 hover:text-gray-600 transition-all" title="Search">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            <div id="hfSearch" class="hf-pop hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 p-2.5 z-50 min-w-[240px] normal-case" onclick="event.stopPropagation()">
                                <div class="relative">
                                    <input type="text" id="hfSearchInput" placeholder="Search name, description, indicator..."
                                        oninput="setHF('fSearch', this.value)"
                                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#00c5a2] font-normal">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 pointer-events-none">
                                        <i class="fas fa-search text-[10px]"></i>
                                    </div>
                                </div>
                            </div>
                        </th>

                        {{-- Type --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[130px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Type</span>
                                <button type="button" onclick="toggleHF(event,'hfType')" id="hfTypeBtn"
                                    class="p-1 rounded-md hover:bg-gray-200/70 text-gray-400 hover:text-gray-600 transition-all" title="Filter type">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            <div id="hfType" class="hf-pop hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[170px] normal-case font-normal" onclick="event.stopPropagation()">
                                @foreach(['' => 'All types', 'self' => 'Self-Assessment', 'lead' => 'Lead Assessment'] as $v => $l)
                                <button type="button" onclick="setHF('fType','{{ $v }}')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">{{ $l }}</button>
                                @endforeach
                            </div>
                        </th>

                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Scale</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Indicators</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-24">Weight</th>

                        {{-- Period --}}
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[120px]">
                            <div class="flex items-center justify-between gap-1.5">
                                <span>Period</span>
                                <button type="button" onclick="toggleHF(event,'hfPeriod')" id="hfPeriodBtn"
                                    class="p-1 rounded-md hover:bg-gray-200/70 text-gray-400 hover:text-gray-600 transition-all" title="Filter period">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            <div id="hfPeriod" class="hf-pop hidden absolute top-full left-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[150px] normal-case font-normal" onclick="event.stopPropagation()">
                                @foreach(['' => 'All periods', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'] as $v => $l)
                                <button type="button" onclick="setHF('fPeriod','{{ $v }}')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">{{ $l }}</button>
                                @endforeach
                            </div>
                        </th>

                        {{-- Status --}}
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider relative min-w-[120px]">
                            <div class="flex items-center justify-center gap-1.5">
                                <span>Status</span>
                                <button type="button" onclick="toggleHF(event,'hfStatus')" id="hfStatusBtn"
                                    class="p-1 rounded-md hover:bg-gray-200/70 text-gray-400 hover:text-gray-600 transition-all" title="Filter status">
                                    <i class="fas fa-filter text-[10px]"></i>
                                </button>
                            </div>
                            <div id="hfStatus" class="hf-pop hidden absolute top-full right-0 mt-1 bg-white rounded-xl shadow-2xl border border-gray-100 py-1.5 z-50 min-w-[150px] text-left normal-case font-normal" onclick="event.stopPropagation()">
                                @foreach(['' => 'All status', 'active' => 'Active', 'inactive' => 'Inactive'] as $v => $l)
                                <button type="button" onclick="setHF('fStatus','{{ $v }}')" class="w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">{{ $l }}</button>
                                @endforeach
                            </div>
                        </th>

                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider w-40">
                            <button type="button" id="hfResetBtn" onclick="resetHF()" class="hidden inline-flex items-center px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-600 text-xs font-semibold rounded-lg normal-case">Reset</button>
                            <span id="hfActionLbl">Action</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50" id="templateRows">
                    @foreach($templates as $i => $tmpl)
                    @php
                        $ttype = ($tmpl->target_type ?? 'supervisor') === 'self' ? 'self' : 'lead';
                        $scaleMax = $tmpl->score_divisor ?: ($tmpl->relationLoaded('scoringScales') && $tmpl->scoringScales->isNotEmpty() ? $tmpl->scoringScales->max('scale_value') : 5);
                        $weightOk = abs($tmpl->total_weight - 100) < 0.01;
                    @endphp
                    <tr class="template-row hover:bg-gray-50/60 transition-colors"
                        data-name="{{ strtolower($tmpl->name) }}"
                        data-desc="{{ strtolower($tmpl->description ?? '') }}"
                        data-indicators="{{ strtolower($tmpl->indicators->pluck('name')->implode(' ')) }}"
                        data-type="{{ $ttype }}"
                        data-period="{{ strtolower($tmpl->period_type) }}"
                        data-status="{{ $tmpl->is_active ? 'active' : 'inactive' }}">
                        <td class="px-5 py-3.5 text-gray-400 text-xs font-medium align-top">{{ $i + 1 }}</td>
                        <td class="px-4 py-3.5 align-top">
                            <p class="font-bold text-gray-900 text-sm">{{ $tmpl->name }}</p>
                            @if($tmpl->description)
                            <p class="text-xs text-gray-400 mt-0.5">{{ Str::limit($tmpl->description, 90) }}</p>
                            @endif
                            @if($tmpl->indicators->count())
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                @foreach($tmpl->indicators->take(4) as $ind)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-100 rounded-full text-[11px] text-gray-600">
                                    {{ Str::limit($ind->name, 28) }}
                                    @if(($ind->answer_type ?? 'rating') === 'paragraph')
                                        <span class="text-gray-400">¶</span>
                                    @else
                                        <span class="font-bold text-indigo-600">{{ rtrim(rtrim(number_format($ind->weight, 2), '0'), '.') }}%</span>
                                    @endif
                                </span>
                                @endforeach
                                @if($tmpl->indicators->count() > 4)
                                <span class="px-2 py-0.5 bg-gray-100 rounded-full text-[11px] text-gray-400">+{{ $tmpl->indicators->count() - 4 }}</span>
                                @endif
                            </div>
                            @endif
                        </td>
                        <td class="px-4 py-3.5 align-top">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $ttype === 'self' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700' }}">
                                {{ $ttype === 'self' ? 'Self' : 'Lead' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center align-top text-xs font-semibold text-gray-600">1&ndash;{{ $scaleMax }}</td>
                        <td class="px-4 py-3.5 text-center align-top text-xs text-gray-600">{{ $tmpl->indicators->count() }}</td>
                        <td class="px-4 py-3.5 text-center align-top text-xs font-bold {{ $weightOk ? 'text-green-600' : 'text-red-600' }}">
                            {{ rtrim(rtrim(number_format($tmpl->total_weight, 2), '0'), '.') }}%
                        </td>
                        <td class="px-4 py-3.5 align-top text-xs text-gray-600">{{ $tmpl->period_type_label }}</td>
                        <td class="px-4 py-3.5 text-center align-top">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $tmpl->is_active ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                                {{ $tmpl->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-center align-top">
                            <div class="flex items-center justify-center gap-1.5">
                                @if($canEdit)
                                <a href="{{ route('general.kpi-evaluation.templates.edit', $tmpl->id) }}"
                                    class="inline-flex items-center px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100 border border-indigo-200 transition-all">Edit</a>
                                <button onclick="toggleTemplate({{ $tmpl->id }})"
                                    class="inline-flex items-center px-2.5 py-1.5 {{ $tmpl->is_active ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : 'bg-green-50 text-green-700 border-green-200' }} text-xs font-medium rounded-lg border hover:opacity-80 transition-all"
                                    title="{{ $tmpl->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="fas {{ $tmpl->is_active ? 'fa-pause' : 'fa-play' }} text-[10px]"></i>
                                </button>
                                @endif
                                @if($canDelete && $tmpl->evaluations_count === 0)
                                <button onclick="deleteTemplate({{ $tmpl->id }}, '{{ addslashes($tmpl->name) }}')"
                                    class="inline-flex items-center px-2.5 py-1.5 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 border border-red-200 text-xs font-medium transition-all">
                                    <i class="fas fa-trash text-[10px]"></i>
                                </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="noTemplateMatch" class="py-16 text-center hidden">
            <div class="w-14 h-14 rounded-2xl bg-gray-100 text-gray-400 flex items-center justify-center mx-auto mb-3 text-xl">
                <i class="fas fa-search"></i>
            </div>
            <p class="text-gray-700 text-sm font-bold">No matching KPI templates found</p>
            <button type="button" onclick="resetHF()" class="mt-3.5 inline-flex items-center px-4 py-1.5 rounded-xl text-xs font-semibold bg-[#00c5a2]/15 text-[#008f75] hover:bg-[#00c5a2]/25 transition-all">Reset filters</button>
        </div>
        @else
        <div class="py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-layer-group text-gray-300 text-2xl"></i>
            </div>
            <p class="text-gray-500 font-medium">No templates yet</p>
            <p class="text-sm text-gray-400 mt-1">Create your first KPI template to start evaluating employees.</p>
            @if($canCreate)
            <a href="{{ route('general.kpi-evaluation.templates.create') }}"
                class="mt-4 inline-flex items-center px-4 py-2 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90 transition-all">
                Create First Template
            </a>
            @endif
        </div>
        @endif
    </div>
</div>

<script>
const TPL_BASE = "{{ url('/general/kpi-evaluation/templates') }}";

async function toggleTemplate(id) {
    const res  = await fetch(`${TPL_BASE}/${id}/toggle`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}

async function deleteTemplate(id, name) {
    if (!await showConfirm(`Delete template "${name}"? This cannot be undone.`, 'Delete Template', 'danger', { okText: 'Delete' })) return;
    const res  = await fetch(`${TPL_BASE}/${id}/delete`, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    });
    const data = await res.json();
    showToast(data.message, data.success ? 'success' : 'error');
    if (data.success) setTimeout(() => location.reload(), 800);
}

// ── In-header filter popovers ────────────────────────────────────────────────
function toggleHF(e, id) {
    e.stopPropagation();
    const el = document.getElementById(id);
    const wasHidden = el.classList.contains('hidden');
    document.querySelectorAll('.hf-pop').forEach(p => p.classList.add('hidden'));
    if (wasHidden) {
        el.classList.remove('hidden');
        el.querySelector('input')?.focus();
    }
}
document.addEventListener('click', e => {
    if (!e.target.closest('.hf-pop')) document.querySelectorAll('.hf-pop').forEach(p => p.classList.add('hidden'));
});

function setHF(field, val) {
    document.getElementById(field).value = val;
    if (field !== 'fSearch') document.querySelectorAll('.hf-pop').forEach(p => p.classList.add('hidden'));
    filterTemplates();
}
function resetHF() {
    ['fSearch', 'fType', 'fPeriod', 'fStatus'].forEach(f => document.getElementById(f).value = '');
    const si = document.getElementById('hfSearchInput'); if (si) si.value = '';
    filterTemplates();
}

function filterTemplates() {
    const q      = (document.getElementById('fSearch').value || '').trim().toLowerCase();
    const type   = document.getElementById('fType').value;
    const period = document.getElementById('fPeriod').value;
    const status = document.getElementById('fStatus').value;

    let visible = 0;
    document.querySelectorAll('.template-row').forEach(row => {
        const matchQ = !q
            || (row.dataset.name || '').includes(q)
            || (row.dataset.desc || '').includes(q)
            || (row.dataset.indicators || '').includes(q);
        const ok = matchQ
            && (!type   || row.dataset.type   === type)
            && (!period || row.dataset.period === period)
            && (!status || row.dataset.status === status);
        row.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });

    const badge = document.getElementById('templateCountBadge');
    if (badge) badge.textContent = visible;
    document.getElementById('noTemplateMatch')?.classList.toggle('hidden', visible > 0);

    const active = q || type || period || status;
    document.getElementById('hfResetBtn')?.classList.toggle('hidden', !active);
    document.getElementById('hfActionLbl')?.classList.toggle('hidden', !!active);
    ['hfSearchBtn','hfTypeBtn','hfPeriodBtn','hfStatusBtn'].forEach(id => {
        const map = { hfSearchBtn: q, hfTypeBtn: type, hfPeriodBtn: period, hfStatusBtn: status };
        document.getElementById(id)?.classList.toggle('text-[var(--primary-color)]', !!map[id]);
    });
}
document.addEventListener('DOMContentLoaded', filterTemplates);
</script>
@endsection
