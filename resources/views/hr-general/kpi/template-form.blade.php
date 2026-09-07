@extends('dashboard')

@section('title', ($mode === 'edit' ? 'Edit' : 'New') . ' KPI Template')
@section('page-title', 'KPI Evaluation')

@section('content')
@php
    $isEdit    = $mode === 'edit';
    $action    = $isEdit
        ? route('general.kpi-evaluation.templates.update', $template->id)
        : route('general.kpi-evaluation.templates.store');

    // Prefer old() on validation bounce, else the stored template.
    $vName      = old('name', $template->name);
    $vType      = old('target_type', $template->target_type ?? 'supervisor');
    $vPeriod    = old('period_type', $template->period_type ?? 'monthly');
    $vDesc      = old('description', $template->description);
    $vRoles     = collect(old('target_roles', $template->target_roles ?? []))->map(fn($v) => (string) $v)->all();
    $vPositions = collect(old('target_positions', $template->target_positions ?? []))->map(fn($v) => (string) $v)->all();
    $vEmployees = collect(old('target_employees', $template->target_employees ?? []))->map(fn($v) => (string) $v)->all();
    $vProjects  = collect(old('target_projects', $template->target_projects ?? []))->map(fn($v) => (string) $v)->all();
    $vDivisor   = old('score_divisor', $template->score_divisor ?? 5);

    // Option pools for the criteria builder, keyed by criterion type.
    $targetPools = [
        'role'     => collect($roles)->map(fn($r) => ['id' => (string) $r->id, 'name' => $r->name, 'meta' => ''])->values(),
        'position' => collect($positions)->map(fn($p) => ['id' => (string) $p, 'name' => $p, 'meta' => ''])->values(),
        'employee' => collect($employees)->values(),
        'project'  => collect($projects)->map(fn($p) => ['id' => (string) ($p['id'] ?? $p->id), 'name' => $p['name'] ?? $p->name, 'meta' => ''])->values(),
    ];
    // Pre-selected chips: [type, id, label]
    $preChips = [];
    foreach ($vRoles as $id)     { $preChips[] = ['role', $id, optional(collect($targetPools['role'])->firstWhere('id', $id))['name'] ?? ('Role #' . $id)]; }
    foreach ($vPositions as $id) { $preChips[] = ['position', $id, $id]; }
    foreach ($vEmployees as $id) { $preChips[] = ['employee', $id, optional(collect($targetPools['employee'])->firstWhere('id', $id))['name'] ?? ('Employee #' . $id)]; }
    foreach ($vProjects as $id)  { $preChips[] = ['project', $id, optional(collect($targetPools['project'])->firstWhere('id', $id))['name'] ?? ('Project #' . $id)]; }

    $oldIndicators = old('indicators');
    $rows = $oldIndicators
        ? collect($oldIndicators)->map(fn($r) => (object) $r)
        : $indicators->map(fn($i) => (object) [
            'name' => $i->name, 'answer_type' => $i->answer_type ?? 'rating', 'rating_max' => $i->rating_max,
            'measurement_unit' => $i->measurement_unit,
            'target_value' => $i->target_value, 'weight' => $i->weight, 'description' => $i->description,
        ]);
    if ($rows->isEmpty()) $rows = collect([(object) ['name'=>'','answer_type'=>'rating','rating_max'=>'','measurement_unit'=>'','target_value'=>'','weight'=>'','description'=>'']]);

    $oldScales = old('scales');
    $scaleRows = $oldScales
        ? collect($oldScales)->map(fn($r) => (object) $r)
        : collect($scales)->map(fn($s) => (object) ((is_object($s) && method_exists($s, 'toArray')) ? $s->toArray() : (array) $s));
    if ($scaleRows->isEmpty()) $scaleRows = collect([(object) ['scale_value'=>'','category'=>'','definition'=>'','achievement_label'=>'','achievement_min'=>'','achievement_max'=>'','description'=>'']]);
@endphp

<div class="space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl primary-gradient text-white flex items-center justify-center text-sm shadow-sm">
                    <i class="fas fa-layer-group"></i>
                </span>
                {{ $isEdit ? 'Edit KPI Template' : 'New KPI Template' }}
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                Define the indicators (weights must total 100%) and choose who this template is offered to — by role, position, employee, or project.
            </p>
        </div>
        <a href="{{ route('general.kpi-evaluation.templates.index') }}"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-gray-100 text-gray-700 text-xs font-semibold rounded-xl hover:bg-gray-200 transition-all">
            <i class="fas fa-chevron-left text-xs"></i> Back to Templates
        </a>
    </div>

    @if(isset($errors) && $errors->any())
    <div class="bg-red-50 border border-red-200 rounded-2xl p-4 text-xs text-red-700">
        <p class="font-bold mb-1">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ $action }}" id="templateForm" class="space-y-5">
        @csrf

        {{-- ── Basic details ─────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h3 class="text-sm font-bold text-gray-800 mb-4">Template Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Template Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required maxlength="200" value="{{ $vName }}"
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 focus:border-red-400"
                        placeholder="e.g. Engineering Staff Monthly KPI">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Assessment Type <span class="text-red-500">*</span></label>
                    <select name="target_type" required
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                        <option value="self" {{ $vType === 'self' ? 'selected' : '' }}>Self-Assessment (Evaluasi Mandiri)</option>
                        <option value="supervisor" {{ $vType === 'supervisor' ? 'selected' : '' }}>Lead Assessment (Penilaian Atasan)</option>
                        <option value="peer" {{ $vType === 'peer' ? 'selected' : '' }}>Peer Assessment</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Period Type <span class="text-red-500">*</span></label>
                    <select name="period_type" required
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                        <option value="monthly" {{ $vPeriod === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ $vPeriod === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="annual" {{ $vPeriod === 'annual' ? 'selected' : '' }}>Annual</option>
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 resize-none"
                        placeholder="Optional description...">{{ $vDesc }}</textarea>
                </div>
            </div>
        </div>

        {{-- ── Audience targeting — flexible criteria builder ─────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Who is this template for?</h3>
            <p class="text-xs text-gray-400 mt-0.5 mb-4">
                Tick any mix of criteria — by role, position, employee, or project. An employee is offered this
                template if they match <strong>any one</strong> of them. Leave empty to offer it to everyone.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="tgtBuilder">
                {{-- Left: row 1 = big full-width search, row 2 = type tabs, then scrollable list --}}
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200 space-y-2.5">
                        <div class="relative">
                            <input type="text" id="tgtSearch" autocomplete="off" placeholder="Search to add…"
                                oninput="tgtRenderList()"
                                class="w-full pl-11 pr-3 py-3.5 text-sm border border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-indigo-400">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div class="flex gap-1" id="tgtTypeTabs">
                            @foreach(['role' => 'Role', 'position' => 'Position', 'employee' => 'Employee', 'project' => 'Project'] as $tv => $tl)
                            <button type="button" data-type="{{ $tv }}"
                                class="tgt-tab flex-1 px-2 py-1.5 text-[11px] font-semibold rounded-lg border transition-all
                                    {{ $tv === 'role' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-100' }}">
                                {{ $tl }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    <div id="tgtList" class="bg-gray-50/40 max-h-72 overflow-y-auto p-1.5 space-y-0.5"></div>
                </div>

                {{-- Right: chosen criteria --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-700">Selected <span id="tgtCount" class="text-gray-400 font-normal"></span></span>
                        <button type="button" id="tgtClear" onclick="tgtClearAll()" class="text-[11px] text-red-500 font-medium hover:underline hidden">Clear all</button>
                    </div>
                    <div id="tgtChosen" class="border border-gray-200 rounded-xl bg-white max-h-64 overflow-y-auto p-2 space-y-1.5"></div>
                    <p id="tgtEmpty" class="text-[11px] text-gray-400 mt-2">No criteria — offered to <strong>everyone</strong>.</p>
                </div>
            </div>

            {{-- Hidden inputs (populated by JS) --}}
            <div id="tgtInputs"></div>
        </div>

        <script>
            window.__kpiPools  = @json($targetPools);
            window.__kpiChips0 = @json($preChips);
        </script>

        {{-- ── Scoring Scale (SKALA PENILAIAN) ───────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-1">
                <h3 class="text-sm font-bold text-gray-800">
                    Scoring Scale <span class="text-gray-400 font-normal ml-1 text-xs">(SKALA PENILAIAN)</span>
                </h3>
                <div class="flex items-center gap-2">
                    <label class="text-[11px] font-semibold text-gray-500">Score divisor</label>
                    <input type="number" name="score_divisor" id="scoreDivisor" value="{{ $vDivisor }}" min="1" max="100"
                        class="w-16 px-2 py-1.5 text-xs text-center font-bold border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    <span class="text-[10px] text-gray-400">Weighted Score = Score ÷ divisor × Bobot. Leave = highest scale value.</span>
                </div>
            </div>
            <p class="text-xs text-gray-400 mb-3">Define what each rating value means. This table is shown to evaluators on the self-assessment and review screens.</p>

            <div class="hidden sm:grid grid-cols-12 gap-2 px-1 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                <div class="col-span-1 text-center">Value</div>
                <div class="col-span-2">Category</div>
                <div class="col-span-3">Definition</div>
                <div class="col-span-2">Achievement band</div>
                <div class="col-span-3">Description / note</div>
                <div class="col-span-1"></div>
            </div>

            <div id="scaleList" class="space-y-2">
                @foreach($scaleRows as $s)
                <div class="scale-row grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="col-span-3 sm:col-span-1">
                        <input type="number" name="scales[__S__][scale_value]" value="{{ $s->scale_value ?? '' }}" min="1" max="100"
                            placeholder="5" class="w-full px-2 py-2 text-xs text-center font-bold border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="col-span-9 sm:col-span-2">
                        <input type="text" name="scales[__S__][category]" value="{{ $s->category ?? '' }}"
                            placeholder="Outstanding" class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="col-span-12 sm:col-span-3">
                        <input type="text" name="scales[__S__][definition]" value="{{ $s->definition ?? '' }}"
                            placeholder="Jauh melampaui ekspektasi" class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <input type="text" name="scales[__S__][achievement_label]" value="{{ $s->achievement_label ?? '' }}"
                            placeholder=">= 120%" class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                        <input type="text" name="scales[__S__][description]" value="{{ $s->description ?? '' }}"
                            placeholder="Kinerja sangat istimewa" class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <input type="hidden" name="scales[__S__][achievement_min]" value="{{ $s->achievement_min ?? '' }}">
                    <input type="hidden" name="scales[__S__][achievement_max]" value="{{ $s->achievement_max ?? '' }}">
                    <div class="col-span-12 sm:col-span-1 flex items-center justify-center">
                        <button type="button" title="Remove scale row"
                            onclick="this.closest('.scale-row').remove(); reindexScales();"
                            class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 border border-red-200 transition-all">
                            <i class="fas fa-trash text-[11px]"></i>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>

            <button type="button" onclick="addScaleRow()"
                class="mt-3 inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100 transition-all border border-indigo-200">
                <i class="fas fa-plus text-[10px]"></i> Add Scale Row
            </button>
        </div>

        {{-- ── Indicators ────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-sm font-bold text-gray-800">
                    KPI Indicators <span class="text-red-500">*</span>
                    <span class="text-gray-400 font-normal ml-1 text-xs">(scored rows must sum to 100%)</span>
                </h3>
                <span id="weightSumDisplay" class="text-xs font-bold text-gray-400">Total: 0%</span>
            </div>
            <p class="text-[11px] text-gray-400 mb-3">
                Each <strong>Rating</strong> row is scored on the scale above:
                <span class="font-mono">indicator score = (stars &divide; scale max) &times; weight</span>.
                A <strong>Paragraph</strong> row just collects text — no weight, not scored.
                “Scale max” is optional; leave blank to use the template scale ({{ $vDivisor ?: 5 }}), or set e.g. 3 for a shorter scale on that row.
            </p>

            {{-- Column headers --}}
            <div class="hidden sm:grid grid-cols-12 gap-2 px-1 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                <div class="col-span-4">Indicator name</div>
                <div class="col-span-2">Answer type</div>
                <div class="col-span-2 text-center">Weight (%)</div>
                <div class="col-span-3 text-center">Scale max</div>
                <div class="col-span-1"></div>
            </div>

            <div id="indicatorList" class="space-y-2">
                @foreach($rows as $r)
                @php $isPara = ($r->answer_type ?? 'rating') === 'paragraph'; @endphp
                <div class="indicator-row grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="col-span-12 sm:col-span-4">
                        <input type="text" name="indicators[__I__][name]" value="{{ $r->name ?? '' }}" required
                            placeholder="Indicator name *"
                            class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <select name="indicators[__I__][answer_type]" onchange="toggleIndicatorType(this)"
                            class="answer-type w-full px-2 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 bg-white">
                            <option value="rating" {{ !$isPara ? 'selected' : '' }}>Rating</option>
                            <option value="paragraph" {{ $isPara ? 'selected' : '' }}>Paragraph</option>
                        </select>
                    </div>
                    <div class="col-span-6 sm:col-span-2">
                        <input type="number" name="indicators[__I__][weight]" value="{{ $r->weight ?? '' }}"
                            placeholder="Weight %" min="0" max="100" step="0.01" {{ $isPara ? 'disabled' : '' }}
                            class="weight-input w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 text-center font-bold {{ $isPara ? 'bg-gray-100 text-gray-400' : '' }}"
                            oninput="updateWeightSum()">
                    </div>
                    <div class="col-span-4 sm:col-span-3">
                        <input type="number" name="indicators[__I__][rating_max]" value="{{ $r->rating_max ?? '' }}"
                            placeholder="scale ({{ $vDivisor ?: 5 }})" min="1" max="100" {{ $isPara ? 'disabled' : '' }}
                            class="w-full px-2.5 py-2 text-xs text-center border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 {{ $isPara ? 'bg-gray-100 text-gray-400' : '' }}">
                    </div>
                    <div class="col-span-2 sm:col-span-1 flex items-center justify-center">
                        <button type="button" title="Remove indicator"
                            onclick="this.closest('.indicator-row').remove(); reindexRows(); updateWeightSum();"
                            class="w-7 h-7 flex items-center justify-center bg-red-50 text-red-500 rounded-lg hover:bg-red-100 border border-red-200 transition-all">
                            <i class="fas fa-trash text-[11px]"></i>
                        </button>
                    </div>
                    <div class="col-span-12">
                        <input type="text" name="indicators[__I__][description]" value="{{ $r->description ?? '' }}"
                            placeholder="Description / cara ukur (optional)"
                            class="w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
                    </div>
                </div>
                @endforeach
            </div>

            <button type="button" onclick="addIndicatorRow()"
                class="mt-3 inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-50 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-100 transition-all border border-indigo-200">
                <i class="fas fa-plus text-[10px]"></i> Add Indicator
            </button>
        </div>

        {{-- ── Actions ───────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('general.kpi-evaluation.templates.index') }}"
               class="px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-200">Cancel</a>
            <button type="submit" id="submitBtn"
                class="inline-flex items-center gap-2 px-6 py-2.5 primary-gradient text-white text-sm font-semibold rounded-xl shadow hover:opacity-90">
                <i class="fas fa-save text-xs"></i> {{ $isEdit ? 'Update Template' : 'Create Template' }}
            </button>
        </div>
    </form>
</div>

<script>
// Rows are re-indexed on every add/remove so indicators[] stays a clean 0..n array.
function reindexRows() {
    document.querySelectorAll('#indicatorList .indicator-row').forEach((row, i) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/indicators\[[^\]]*\]/, `indicators[${i}]`);
        });
    });
}

function addIndicatorRow() {
    const tpl = document.querySelector('#indicatorList .indicator-row');
    const clone = tpl ? tpl.cloneNode(true) : null;
    if (!clone) return;
    clone.querySelectorAll('input').forEach(el => { el.value = ''; el.disabled = false; el.classList.remove('bg-gray-100', 'text-gray-400'); });
    const sel = clone.querySelector('.answer-type'); if (sel) sel.value = 'rating';
    clone.querySelectorAll('.unit-dd-menu').forEach(m => m.classList.add('hidden'));
    document.getElementById('indicatorList').appendChild(clone);
    reindexRows();
    updateWeightSum();
    clone.querySelector('input')?.focus();
}

// Paragraph indicators carry no weight / unit / target — grey those out.
function toggleIndicatorType(sel) {
    const row = sel.closest('.indicator-row');
    const para = sel.value === 'paragraph';
    row.querySelectorAll('.weight-input, [name*="[rating_max]"]').forEach(el => {
        el.disabled = para;
        el.classList.toggle('bg-gray-100', para);
        el.classList.toggle('text-gray-400', para);
        if (para) el.value = '';
    });
    updateWeightSum();
}

// ── Scale rows ───────────────────────────────────────────────────────────────
function reindexScales() {
    document.querySelectorAll('#scaleList .scale-row').forEach((row, i) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/scales\[[^\]]*\]/, `scales[${i}]`);
        });
    });
}
function addScaleRow() {
    const tpl = document.querySelector('#scaleList .scale-row');
    const clone = tpl ? tpl.cloneNode(true) : null;
    if (!clone) return;
    clone.querySelectorAll('input').forEach(el => { el.value = ''; });
    document.getElementById('scaleList').appendChild(clone);
    reindexScales();
    clone.querySelector('input')?.focus();
}

function updateWeightSum() {
    let total = 0;
    document.querySelectorAll('.weight-input').forEach(i => {
        if (i.disabled) return;
        const v = parseFloat(i.value); if (!isNaN(v)) total += v;
    });
    const el = document.getElementById('weightSumDisplay');
    el.textContent = `Total: ${total.toFixed(2)}%`;
    el.className = `text-xs font-bold ${Math.abs(total - 100) < 0.01 ? 'text-green-600' : (total > 100 ? 'text-red-600' : 'text-amber-600')}`;
}

function filterBox(boxId, q) {
    q = (q || '').toLowerCase();
    document.querySelectorAll(`#${boxId} .chk-item`).forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}
function toggleAll(boxId, checked) {
    document.querySelectorAll(`#${boxId} input[type="checkbox"]`).forEach(c => {
        if (c.closest('.chk-item').style.display !== 'none') c.checked = checked;
    });
}

document.getElementById('templateForm').addEventListener('submit', function (e) {
    let total = 0;
    document.querySelectorAll('.weight-input').forEach(i => {
        if (i.disabled) return;
        const v = parseFloat(i.value); if (!isNaN(v)) total += v;
    });
    if (Math.abs(total - 100) > 0.01) {
        e.preventDefault();
        showToast(`Scored indicator weights must sum to 100%. Current total: ${total.toFixed(2)}%`, 'error');
    }
});

// ── Custom "Unit" dropdown (styled menu, still accepts a free-typed value) ────
function closeAllUnitDD(except) {
    document.querySelectorAll('.unit-dd-menu').forEach(m => { if (m !== except) m.classList.add('hidden'); });
}
function openUnitDD(input) {
    const menu = input.closest('.unit-dd').querySelector('.unit-dd-menu');
    closeAllUnitDD(menu);
    menu.querySelectorAll('.unit-dd-opt').forEach(o => { o.style.display = ''; });
    menu.classList.remove('hidden');
}
function toggleUnitDD(btn) {
    const menu = btn.closest('.unit-dd').querySelector('.unit-dd-menu');
    if (menu.classList.contains('hidden')) openUnitDD(btn.closest('.unit-dd').querySelector('.unit-dd-input'));
    else menu.classList.add('hidden');
}
function filterUnitDD(input) {
    const q = input.value.trim().toLowerCase();
    const menu = input.closest('.unit-dd').querySelector('.unit-dd-menu');
    let any = false;
    menu.querySelectorAll('.unit-dd-opt').forEach(o => {
        const show = o.textContent.toLowerCase().includes(q);
        o.style.display = show ? '' : 'none';
        if (show) any = true;
    });
    menu.classList.toggle('hidden', !any);
}
function pickUnit(opt, value) {
    const wrap = opt.closest('.unit-dd');
    wrap.querySelector('.unit-dd-input').value = value;
    wrap.querySelector('.unit-dd-menu').classList.add('hidden');
}
document.addEventListener('click', function (e) {
    if (!e.target.closest('.unit-dd')) closeAllUnitDD(null);
});

// ── Audience targeting: multi-select criteria builder ──────────────────────
(function () {
    const POOLS = window.__kpiPools || {};
    const FIELD = { role: 'target_roles', position: 'target_positions', employee: 'target_employees', project: 'target_projects' };
    const BADGE = { role: 'bg-indigo-100 text-indigo-700', position: 'bg-purple-100 text-purple-700', employee: 'bg-emerald-100 text-emerald-700', project: 'bg-amber-100 text-amber-700' };
    const TYPE_LABEL = { role: 'Role', position: 'Position', employee: 'Employee', project: 'Project' };
    const selected = new Map(); // `${type}:${id}` -> {type,id,label}

    const $tabs   = document.getElementById('tgtTypeTabs');
    const $search  = document.getElementById('tgtSearch');
    const $list    = document.getElementById('tgtList');
    const $chosen  = document.getElementById('tgtChosen');
    const $inputs  = document.getElementById('tgtInputs');
    const $empty   = document.getElementById('tgtEmpty');
    const $count   = document.getElementById('tgtCount');
    const $clear   = document.getElementById('tgtClear');
    if (!$tabs) return;

    let currentType = 'role';
    const ACTIVE_TAB = 'bg-indigo-600 text-white border-indigo-600';
    const IDLE_TAB   = 'bg-white text-gray-500 border-gray-200 hover:bg-gray-100';
    $tabs.querySelectorAll('.tgt-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            currentType = btn.dataset.type;
            $tabs.querySelectorAll('.tgt-tab').forEach(b => {
                b.className = 'tgt-tab flex-1 px-2 py-1.5 text-[11px] font-semibold rounded-lg border transition-all '
                    + (b === btn ? ACTIVE_TAB : IDLE_TAB);
            });
            tgtRenderList();
        });
    });

    const key = (t, id) => t + ':' + id;
    const esc = s => (s || '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    // Right-hand panel: everything picked so far, grouped by type, each removable.
    function renderChosen() {
        $chosen.innerHTML = '';
        $inputs.innerHTML = '';

        Object.keys(FIELD).forEach(type => {
            const rows = [...selected.values()].filter(s => s.type === type);
            if (!rows.length) return;

            const head = document.createElement('p');
            head.className = 'text-[10px] font-bold uppercase tracking-wider text-gray-400 pt-1';
            head.textContent = TYPE_LABEL[type];
            $chosen.appendChild(head);

            rows.forEach(({ id, label }) => {
                const chip = document.createElement('div');
                chip.className = 'flex items-center justify-between gap-2 px-2 py-1 rounded-lg text-[11px] font-medium ' + (BADGE[type] || 'bg-gray-100 text-gray-700');
                chip.innerHTML = `<span class="truncate">${esc(label)}</span>
                    <button type="button" class="shrink-0 w-4 h-4 flex items-center justify-center rounded hover:bg-black/10" aria-label="Remove">&times;</button>`;
                chip.querySelector('button').onclick = () => { selected.delete(key(type, id)); renderChosen(); tgtRenderList(); };
                $chosen.appendChild(chip);

                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = FIELD[type] + '[]';
                inp.value = id;
                $inputs.appendChild(inp);
            });
        });

        const n = selected.size;
        $count.textContent = n ? `(${n})` : '';
        $empty.classList.toggle('hidden', n > 0);
        $clear.classList.toggle('hidden', n === 0);
    }

    // Left-hand list: checkboxes for the current type, filtered by search.
    window.tgtRenderList = function () {
        const type = currentType;
        const q = ($search.value || '').trim().toLowerCase();
        const pool = POOLS[type] || [];
        const hits = pool.filter(o =>
            !q || (o.name || '').toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q)
        ).slice(0, 200);

        if (!hits.length) {
            $list.innerHTML = `<p class="px-2 py-3 text-[11px] text-gray-400">${pool.length ? 'No match' : 'Nothing to choose'}</p>`;
            return;
        }

        $list.innerHTML = hits.map(o => {
            const checked = selected.has(key(type, o.id)) ? 'checked' : '';
            return `<label class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-white cursor-pointer text-xs">
                <input type="checkbox" class="tgt-cb rounded text-indigo-600" data-id="${esc(o.id)}" data-label="${esc(o.name)}" ${checked}>
                <span class="font-medium text-gray-800">${esc(o.name)}</span>
                ${o.meta ? `<span class="text-gray-400">${esc(o.meta)}</span>` : ''}
            </label>`;
        }).join('');

        $list.querySelectorAll('.tgt-cb').forEach(cb => {
            cb.addEventListener('change', () => {
                const k = key(type, cb.dataset.id);
                if (cb.checked) selected.set(k, { type, id: cb.dataset.id, label: cb.dataset.label });
                else selected.delete(k);
                renderChosen();
            });
        });
    };

    window.tgtClearAll = function () {
        selected.clear();
        renderChosen();
        tgtRenderList();
    };

    // Seed from edit mode / validation bounce
    (window.__kpiChips0 || []).forEach(([type, id, label]) => {
        if (FIELD[type]) selected.set(key(type, String(id)), { type, id: String(id), label: label || String(id) });
    });
    renderChosen();
    tgtRenderList();
})();

reindexRows();
reindexScales();
updateWeightSum();
</script>
@endsection
