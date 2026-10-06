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
    $vAnonymous = old('is_anonymous', $template->is_anonymous ?? false);
    $vRoles     = collect(old('target_roles', $template->target_roles ?? []))->map(fn($v) => (string) $v)->all();
    $vPositions = collect(old('target_positions', $template->target_positions ?? []))->map(fn($v) => (string) $v)->all();
    $vEmployees = collect(old('target_employees', $template->target_employees ?? []))->map(fn($v) => (string) $v)->all();
    $vProjects  = collect(old('target_projects', $template->target_projects ?? []))->map(fn($v) => (string) $v)->all();
    $vSubjects  = collect(old('subject_employees', $template->subject_employees ?? []))->map(fn($v) => (string) $v)->all();
    $vGroups    = old('peer_groups') !== null
        ? collect(old('peer_groups'))->map(fn($g) => [
            'name' => (string) ($g['name'] ?? ''), 'basis' => (string) ($g['basis'] ?? ''),
            'value' => (string) ($g['value'] ?? ''), 'members' => array_map('strval', (array) ($g['members'] ?? [])),
        ])->values()->all()
        : collect($template->peerGroups())->map(fn($g) => $g + ['members' => array_map('strval', $g['members'])])->values()->all();
    $vDivisor   = old('score_divisor', $template->score_divisor ?? 5);
    $vDlDay     = old('deadline_day', $template->deadline_day);
    $vDlMonth   = old('deadline_month', $template->deadline_month);
    $vDlYear    = old('deadline_year', $template->deadline_year ?: now()->year);

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

    // Pre-selected chips for the "for who / who fills" counterpart picker
    // (employee-only, since a counterpart is always a specific person or two).
    $preSubjectChips = [];
    foreach ($vSubjects as $id) { $preSubjectChips[] = [$id, optional(collect($targetPools['employee'])->firstWhere('id', $id))['name'] ?? ('Employee #' . $id)]; }

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
                Set the indicators, audience, and assessment type.
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
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Template Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required maxlength="200" value="{{ $vName }}"
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 focus:border-red-400"
                        placeholder="e.g. Engineering Staff Monthly KPI">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Assessment Type <span class="text-red-500">*</span></label>
                        <select name="target_type" id="targetTypeSelect" required onchange="toggleAnonymousVisibility(); updateAudienceCopy();"
                            class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                            <option value="self" {{ $vType === 'self' ? 'selected' : '' }}>Self-Assessment</option>
                            <option value="supervisor" {{ $vType === 'supervisor' ? 'selected' : '' }}>Lead Assessment</option>
                            <option value="peer" {{ $vType === 'peer' ? 'selected' : '' }}>Peer Assessment</option>
                            <option value="upward" {{ $vType === 'upward' ? 'selected' : '' }}>Upward Assessment</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Period Type <span class="text-red-500">*</span></label>
                        <select name="period_type" id="periodTypeSelect" required onchange="updateDeadlineFields()"
                            class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                            <option value="monthly" {{ $vPeriod === 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="quarterly" {{ $vPeriod === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                            <option value="annual" {{ $vPeriod === 'annual' ? 'selected' : '' }}>Annual</option>
                        </select>
                    </div>
                    {{-- Deadline — the pickers shown depend on the period type:
                         monthly = date, quarterly = month + date, annual = date + month + year. --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Deadline <span class="text-gray-400 font-normal" id="deadlineHint"></span>
                        </label>
                        <div class="flex gap-1.5">
                            <select name="deadline_day" id="dlDay" class="min-w-0 flex-1 px-2.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                                <option value="">Date</option>
                                @for($d = 1; $d <= 31; $d++)
                                <option value="{{ $d }}" {{ (int) $vDlDay === $d ? 'selected' : '' }}>{{ $d }}</option>
                                @endfor
                            </select>
                            <select name="deadline_month" id="dlMonth" class="min-w-0 flex-[1.4] px-2.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                                <option value="">Month</option>
                                @foreach(range(1, 12) as $m)
                                <option value="{{ $m }}" {{ (int) $vDlMonth === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000, $m, 1)->format('F') }}</option>
                                @endforeach
                            </select>
                            <select name="deadline_year" id="dlYear" class="min-w-0 flex-1 px-2.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300">
                                @foreach(range(now()->year - 1, now()->year + 5) as $y)
                                <option value="{{ $y }}" {{ (int) $vDlYear === $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @if($isEdit && $template->updated_at)
                <p class="text-[11px] text-gray-400 -mt-2">
                    <i class="fas fa-clock-rotate-left mr-1"></i>
                    Last changed {{ $template->updated_at->format('d M Y, H:i') }} ({{ $template->updated_at->diffForHumans() }})
                </p>
                @endif
                {{-- Who fills it vs. who it's about — flips per assessment type, so this
                     box always states it explicitly instead of leaving it implied. --}}
                <div id="flowDirectionBox" class="p-3.5 rounded-xl border flex items-start gap-3"></div>

                <div id="anonymousToggleWrap" class="hidden">
                    <label class="flex items-start gap-3 p-3.5 rounded-xl border border-gray-200 bg-gray-50/60 cursor-pointer hover:bg-gray-50 transition-all">
                        <input type="hidden" name="is_anonymous" value="0">
                        <input type="checkbox" name="is_anonymous" id="isAnonymousChk" value="1" {{ $vAnonymous ? 'checked' : '' }}
                            class="mt-0.5 w-4 h-4 rounded text-indigo-600 focus:ring-2 focus:ring-indigo-400">
                        <span>
                            <span class="block text-xs font-bold text-gray-800">Anonymous evaluation</span>
                            <span class="block text-[11px] text-gray-500 mt-0.5">
                                Raters stay hidden. HR reviews submissions and publishes only the average score.
                            </span>
                        </span>
                    </label>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-red-300 resize-none"
                        placeholder="Optional description...">{{ $vDesc }}</textarea>
                </div>
            </div>
        </div>

        {{-- ── Audience targeting — flexible criteria builder ─────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <h3 class="text-sm font-bold text-gray-800" id="audienceHeading">Who is this template for?</h3>
            <p class="text-xs text-gray-400 mt-0.5 mb-4" id="audienceSubcopy">
                Match by role, position, employee, or project. Leave empty for everyone.
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
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="tgtSelectAllVisible()" class="text-[11px] text-indigo-600 font-medium hover:underline">Select all</button>
                            <button type="button" id="tgtClear" onclick="tgtClearAll()" class="text-[11px] text-red-500 font-medium hover:underline hidden">Clear all</button>
                        </div>
                    </div>
                    <div id="tgtChosen" class="border border-gray-200 rounded-xl bg-white max-h-64 overflow-y-auto p-2 space-y-1.5"></div>
                    <p id="tgtEmpty" class="text-[11px] text-gray-400 mt-2">No criteria — offered to <strong>everyone</strong>.</p>
                </div>
            </div>

            {{-- Hidden inputs (populated by JS) --}}
            <div id="tgtInputs"></div>
        </div>

        {{-- ── Counterpart picker — "who fills" (Lead/Peer) or "for who" (Upward) ── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100" id="subjectPanel">
            <h3 class="text-sm font-bold text-gray-800" id="subjectHeading">Who fills this?</h3>
            <p class="text-xs text-gray-400 mt-0.5 mb-4" id="subjectSubcopy"></p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <div class="p-3 bg-gray-50 border-b border-gray-200">
                        <div class="relative">
                            <input type="text" id="subSearch" autocomplete="off" placeholder="Search employees…"
                                oninput="subRenderList()"
                                class="w-full pl-11 pr-3 py-3.5 text-sm border border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-amber-400">
                            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    <div id="subList" class="bg-gray-50/40 max-h-72 overflow-y-auto p-1.5 space-y-0.5"></div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-700">Selected <span id="subCount" class="text-gray-400 font-normal"></span></span>
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="subSelectAllVisible()" class="text-[11px] text-amber-700 font-medium hover:underline">Select all</button>
                            <button type="button" id="subClear" onclick="subClearAll()" class="text-[11px] text-red-500 font-medium hover:underline hidden">Clear all</button>
                        </div>
                    </div>
                    <div id="subChosen" class="border border-gray-200 rounded-xl bg-white max-h-64 overflow-y-auto p-2 space-y-1.5"></div>
                    <p id="subEmpty" class="text-[11px] text-gray-400 mt-2">
                        None picked — falls back to each employee's direct supervisor.
                    </p>
                    <p id="subOverlapWarning" class="text-[11px] text-red-600 mt-2 hidden">
                        <i class="fas fa-triangle-exclamation mr-1"></i> Same employee can't be on both sides.
                    </p>
                </div>
            </div>

            <div id="subInputs"></div>
        </div>

        {{-- ── Peer pairs (groups) — mutual evaluation inside a role / position / project ── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hidden" id="pairsPanel">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-1">
                <h3 class="text-sm font-bold text-gray-800">Peer pairs <span class="text-gray-400 font-normal text-xs">— who evaluates each other</span></h3>
                <button type="button" onclick="groupAdd()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-cyan-600 text-white text-xs font-bold rounded-xl hover:bg-cyan-700 transition-all">
                    <i class="fas fa-plus text-[10px]"></i> Add pair
                </button>
            </div>
            <p class="text-xs text-gray-400 mb-4">
                Each pair takes <strong>one</strong> role, position or project and up to <strong>10 employees</strong> from it. Everyone in a pair
                rates every other member — never themselves. Too many people? Add another pair for the same role / position / project.
            </p>
            <div id="groupList" class="space-y-4"></div>
            <p id="groupEmpty" class="text-[11px] text-gray-400">No pairs yet — click <strong>Add pair</strong>.</p>
            <div id="groupInputs"></div>
        </div>

        <script>
            window.__kpiGroups0 = @json($vGroups);
            window.__kpiPools  = @json($targetPools);
            window.__kpiChips0 = @json($preChips);
            window.__kpiSubjectChips0 = @json($preSubjectChips);
        </script>

        {{-- ── Scoring Scale (SKALA PENILAIAN) ───────────────────────────────── --}}
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-1">
                <h3 class="text-sm font-bold text-gray-800">
                    Scoring Scale <span class="text-gray-400 font-normal ml-1 text-xs">(SKALA PENILAIAN)</span>
                </h3>
                <div class="flex items-center gap-2">
                    <label class="text-[11px] font-semibold text-gray-500">Score divisor</label>
                    <input type="number" name="score_divisor" id="scoreDivisor" value="{{ $vDivisor }}" min="1" max="100" readonly
                        class="w-16 px-2 py-1.5 text-xs text-center font-bold border border-gray-200 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">
                    <span class="text-[10px] text-gray-400">Weighted Score = Score ÷ divisor × Bobot. Auto-synced to the number of scale rows below.</span>
                </div>
            </div>
            <p class="text-xs text-gray-400 mb-2">
                Define each rating value, lowest to highest. Employees rate with one star per scale row.
            </p>
            <div class="flex flex-wrap items-center gap-2 mb-3 text-xs text-gray-500">
                <span>Rating preview:</span>
                <span id="starPreview" class="text-amber-400 text-base tracking-wider break-all"></span>
                <span id="starPreviewCount" class="font-semibold text-gray-700"></span>
            </div>

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
                            oninput="syncScoreDivisor()"
                            placeholder="5" class="scale-value-input w-full px-2 py-2 text-xs text-center font-bold border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400">
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
                            onclick="this.closest('.scale-row').remove(); reindexScales(); syncScoreDivisor();"
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
                <div class="flex items-center gap-3">
                    <span id="indicatorCountDisplay" class="text-xs font-bold text-indigo-600">0 indicators</span>
                    <span id="weightSumDisplay" class="text-xs font-bold text-gray-400">Total: 0%</span>
                    <button type="button" onclick="autoDistributeWeights(true)"
                        class="px-2.5 py-1 text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all">
                        <i class="fas fa-equals text-[9px] mr-1"></i>Split evenly
                    </button>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mb-3">
                <strong>Rating</strong> rows are scored against the scale above. <strong>Paragraph</strong> rows just collect text — no weight.
                Weights are split evenly (100% ÷ number of rating rows) until you edit one by hand.
            </p>
            <div id="weightWarning" class="hidden mb-3 p-3 rounded-xl border border-amber-200 bg-amber-50 text-xs text-amber-800 items-start gap-2">
                <i class="fas fa-triangle-exclamation mt-0.5 shrink-0"></i>
                <span id="weightWarningText"></span>
            </div>

            {{-- Column headers --}}
            <div class="hidden sm:grid grid-cols-12 gap-2 px-1 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                <div class="col-span-1 text-center">No</div>
                <div class="col-span-3">Indicator name / question</div>
                <div class="col-span-2">Answer type</div>
                <div class="col-span-2 text-center">Weight (%)</div>
                <div class="col-span-1 text-center">Scale max</div>
                <div class="col-span-2 text-center">Target</div>
                <div class="col-span-1"></div>
            </div>

            <div id="indicatorList" class="space-y-2">
                @foreach($rows as $r)
                @php $isPara = ($r->answer_type ?? 'rating') === 'paragraph'; @endphp
                <div class="indicator-row grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-xl border border-gray-200">
                    <div class="col-span-2 sm:col-span-1 flex sm:justify-center">
                        <span class="indicator-no inline-flex w-7 h-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 text-xs font-bold">{{ $loop->iteration }}</span>
                    </div>
                    <div class="col-span-10 sm:col-span-3">
                        <textarea name="indicators[__I__][name]" required rows="2"
                            placeholder="Indicator name / question *"
                            class="indicator-name-input w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 resize-y">{{ $r->name ?? '' }}</textarea>
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
                            oninput="weightsManual = true; updateWeightSum()">
                    </div>
                    <div class="col-span-4 sm:col-span-1">
                        <input type="number" value="{{ $vDivisor ?: 5 }}" readonly tabindex="-1"
                            class="rating-max-input w-full px-1 py-2 text-xs text-center border border-gray-200 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed">
                    </div>
                    <div class="col-span-5 sm:col-span-2">
                        {{-- Target the employee is expected to reach (free number; the unit goes in the description). Rating rows only. --}}
                        <input type="number" name="indicators[__I__][target_value]" value="{{ $r->target_value ?? '' }}"
                            placeholder="Target" min="0" step="0.01" {{ $isPara ? 'disabled' : '' }}
                            class="target-input w-full px-2.5 py-2 text-xs border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-400 text-center {{ $isPara ? 'bg-gray-100 text-gray-400' : '' }}">
                    </div>
                    <div class="col-span-3 sm:col-span-1 flex items-center justify-center">
                        <button type="button" title="Remove indicator"
                            onclick="this.closest('.indicator-row').remove(); reindexRows(); autoDistributeWeights();"
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
// Employee → their direct supervisor (leader), from master data. Drives the
// cross-filtering between the audience and counterpart pickers below: Lead
// narrows the reviewer pool to leads of the picked employees, Upward narrows
// the rater pool to subordinates of the picked subject, Peer narrows the
// reviewer pool to colleagues sharing the same leader.
const EMP_LEADER = {};
(window.__kpiPools?.employee || []).forEach(o => { EMP_LEADER[o.id] = o.leader_id || null; });

// Rows are re-indexed on every add/remove so indicators[] stays a clean 0..n
// array, and the "No" badge always reflects the row's current position.
function reindexRows() {
    document.querySelectorAll('#indicatorList .indicator-row').forEach((row, i) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/indicators\[[^\]]*\]/, `indicators[${i}]`);
        });
        const noEl = row.querySelector('.indicator-no');
        if (noEl) noEl.textContent = i + 1;
    });
}

function addIndicatorRow() {
    const tpl = document.querySelector('#indicatorList .indicator-row');
    const clone = tpl ? tpl.cloneNode(true) : null;
    if (!clone) return;
    clone.querySelectorAll('input:not(.rating-max-input)').forEach(el => { el.value = ''; el.disabled = false; el.classList.remove('bg-gray-100', 'text-gray-400'); });
    clone.querySelectorAll('textarea').forEach(el => { el.value = ''; });
    clone.querySelectorAll('.rating-max-input').forEach(el => el.classList.remove('opacity-50'));
    const sel = clone.querySelector('.answer-type'); if (sel) sel.value = 'rating';
    clone.querySelectorAll('.unit-dd-menu').forEach(m => m.classList.add('hidden'));
    document.getElementById('indicatorList').appendChild(clone);
    reindexRows();
    autoDistributeWeights();
    syncScoreDivisor();
    clone.querySelector('.indicator-name-input')?.focus();
}

// Paragraph indicators carry no weight / unit / target — grey those out.
function toggleIndicatorType(sel) {
    const row = sel.closest('.indicator-row');
    const para = sel.value === 'paragraph';
    row.querySelectorAll('.target-input').forEach(el => {
        el.disabled = para;
        el.classList.toggle('bg-gray-100', para);
        el.classList.toggle('text-gray-400', para);
        if (para) el.value = '';
    });
    row.querySelectorAll('.weight-input').forEach(el => {
        el.disabled = para;
        el.classList.toggle('bg-gray-100', para);
        el.classList.toggle('text-gray-400', para);
        if (para) el.value = '';
    });
    row.querySelectorAll('.rating-max-input').forEach(el => {
        el.classList.toggle('opacity-50', para);
    });
    autoDistributeWeights();
}

// Splits 100% evenly across the rating (non-paragraph) rows; the last row
// absorbs the rounding remainder so the total is exactly 100. Runs on
// add / remove / type change unless the user has typed a weight by hand
// (then only the "Split evenly" button, force=true, overrides them).
let weightsManual = false;
function autoDistributeWeights(force = false) {
    if (force) weightsManual = false;
    if (!weightsManual) {
        const inputs = [...document.querySelectorAll('#indicatorList .weight-input')].filter(i => !i.disabled);
        const n = inputs.length;
        if (n) {
            const base = Math.floor(10000 / n) / 100;
            let used = 0;
            inputs.forEach((inp, idx) => {
                const v = idx === n - 1 ? +(100 - used).toFixed(2) : base;
                inp.value = v;
                used += v;
            });
        }
    }
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

// Score divisor is derived, never typed: it always equals the number of
// Scoring Scale rows defined (e.g. 5 rows = a 5-point scale), falling back
// to 5 when the table is empty, so it can never silently drift out of sync
// with the scale itself. Every place that echoes that number — the
// indicators' "Scale max" placeholder and hint text — is refreshed here too,
// so nothing shows a stale value from page load.
function syncScoreDivisor() {
    let count = 0;
    document.querySelectorAll('.scale-value-input').forEach(i => {
        if (i.value !== '') count++;
    });
    const divisor = count || 5;
    document.getElementById('scoreDivisor').value = divisor;

    document.querySelectorAll('.rating-max-input').forEach(i => {
        i.value = divisor;
    });
    const hint = document.getElementById('templateScaleHint');
    if (hint) hint.textContent = divisor;

    // Live preview: one star per scale row (5 rows = 5 stars, 7 = 7, ...).
    const preview = document.getElementById('starPreview');
    if (preview) preview.textContent = '★'.repeat(divisor);
    const previewCount = document.getElementById('starPreviewCount');
    if (previewCount) previewCount.textContent = `${divisor} star${divisor > 1 ? 's' : ''}`;
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

    const warn = document.getElementById('weightWarning');
    if (warn) {
        const bad = Math.abs(total - 100) >= 0.01;
        warn.classList.toggle('hidden', !bad);
        warn.classList.toggle('flex', bad);
        if (bad) {
            const diff = Math.abs(100 - total).toFixed(2);
            document.getElementById('weightWarningText').innerHTML =
                `Weights total <strong>${total.toFixed(2)}%</strong>, not 100%. ` +
                (total > 100 ? `Reduce by ${diff}%` : `Add ${diff}% more`) + ' before saving.';
        }
    }

    const countEl = document.getElementById('indicatorCountDisplay');
    if (countEl) {
        const n = document.querySelectorAll('#indicatorList .indicator-row').length;
        countEl.textContent = `${n} indicator${n === 1 ? '' : 's'}`;
    }
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
        window.subRefreshOverlap?.();
        window.subRenderList?.(); // re-filter Lead/Peer counterpart options as the audience changes
    }

    // Left-hand list: checkboxes for the current type, filtered by search —
    // plus, for Upward templates, narrowed to subordinates of whoever is
    // picked as the subject ("for who") in the counterpart panel below.
    window.tgtRenderList = function () {
        const type = currentType;
        const q = ($search.value || '').trim().toLowerCase();
        let pool = POOLS[type] || [];

        let filterNote = '';
        if (type === 'employee' && document.getElementById('targetTypeSelect')?.value === 'upward') {
            const subjectIds = window.subGetIds ? window.subGetIds() : [];
            if (subjectIds.length) {
                const subjectSet = new Set(subjectIds);
                pool = pool.filter(o => o.leader_id && subjectSet.has(o.leader_id));
                filterNote = `<p class="px-2 py-2 text-[10px] text-amber-700 bg-amber-50 border-b border-amber-100">Showing only employees who report to the selected subject.</p>`;
            }
        }

        const hits = pool.filter(o =>
            !q || (o.name || '').toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q)
        ).slice(0, 200);

        if (!hits.length) {
            $list.innerHTML = filterNote + `<p class="px-2 py-3 text-[11px] text-gray-400">${pool.length ? 'No match' : 'Nothing to choose'}</p>`;
            return;
        }

        // Once a leader is used to derive the counterpart (Lead/Peer/Upward),
        // flag employees with no leader on file — informational only, picking
        // them is still allowed, the counterpart just needs to be set by hand.
        const evalType = document.getElementById('targetTypeSelect')?.value;
        const leaderMatters = type === 'employee' && evalType !== 'self';

        $list.innerHTML = filterNote + hits.map(o => {
            const checked = selected.has(key(type, o.id)) ? 'checked' : '';
            const noLeader = leaderMatters && !o.leader_id;
            return `<label class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-white cursor-pointer text-xs">
                <input type="checkbox" class="tgt-cb rounded text-indigo-600" data-id="${esc(o.id)}" data-label="${esc(o.name)}" ${checked}>
                <span class="font-medium text-gray-800">${esc(o.name)}</span>
                ${o.meta ? `<span class="text-gray-400">${esc(o.meta)}</span>` : ''}
                ${noLeader ? `<i class="fas fa-triangle-exclamation text-amber-500 ml-auto" title="No leader on file"></i>` : ''}
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

    // Checks every entry currently matching the search box for the active
    // type tab (or the whole pool when the search is empty) — lets HR pick
    // "everyone" in a role/position/project/employee list in one click.
    // Respects the Upward subordinate filter when it's active.
    window.tgtSelectAllVisible = function () {
        const type = currentType;
        const q = ($search.value || '').trim().toLowerCase();
        let pool = POOLS[type] || [];
        if (type === 'employee' && document.getElementById('targetTypeSelect')?.value === 'upward') {
            const subjectIds = window.subGetIds ? window.subGetIds() : [];
            if (subjectIds.length) {
                const subjectSet = new Set(subjectIds);
                pool = pool.filter(o => o.leader_id && subjectSet.has(o.leader_id));
            }
        }
        const hits = pool.filter(o => !q || (o.name || '').toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q));
        hits.forEach(o => selected.set(key(type, o.id), { type, id: o.id, label: o.name }));
        renderChosen();
        tgtRenderList();
    };

    // Exposed so the counterpart picker below can warn/refuse overlap with
    // explicitly-picked audience employees (role/position/project overlap is
    // caught server-side, where employee membership in those pools is known).
    window.tgtGetEmployeeIds = () => [...selected.values()].filter(s => s.type === 'employee').map(s => s.id);

    // Seed from edit mode / validation bounce
    (window.__kpiChips0 || []).forEach(([type, id, label]) => {
        if (FIELD[type]) selected.set(key(type, String(id)), { type, id: String(id), label: label || String(id) });
    });
    renderChosen();
    tgtRenderList();
})();

// Deadline pickers follow the period type. Hidden pickers are disabled so
// they aren't submitted (the server also drops parts that don't apply).
function updateDeadlineFields() {
    const type = document.getElementById('periodTypeSelect').value;
    const show = { dlDay: true, dlMonth: type !== 'monthly', dlYear: type === 'annual' };
    Object.entries(show).forEach(([id, on]) => {
        const el = document.getElementById(id);
        el.classList.toggle('hidden', !on);
        el.disabled = !on;
    });
    document.getElementById('deadlineHint').textContent =
        type === 'monthly' ? '(day of each month)' : type === 'quarterly' ? '(month & date)' : '(date, month & year)';
}
updateDeadlineFields();

// Anonymous evaluation only makes sense when raters are distinct from the
// subject and identity should be shielded — Peer and Upward assessments.
function toggleAnonymousVisibility() {
    const type = document.getElementById('targetTypeSelect').value;
    const wrap = document.getElementById('anonymousToggleWrap');
    const chk  = document.getElementById('isAnonymousChk');
    const show = type === 'peer' || type === 'upward';
    wrap.classList.toggle('hidden', !show);
    if (!show) chk.checked = false;
}
toggleAnonymousVisibility();

// The employees picked below always land in the same DB column regardless of
// type — only the meaning of "who does what" changes per assessment type.
// This keeps the picker's heading, helper copy, and a flow-direction note in
// sync with the selected type so template authors never have to guess it.
const AUDIENCE_COPY = {
    self: {
        heading: 'Who fills this out?',
        sub: 'Match by role, position, employee, or project. Leave empty for everyone.',
        flow: {
            cls: 'border-purple-200 bg-purple-50',
            icon: 'fa-user text-purple-500',
            html: 'Each matched employee assesses <strong>themselves</strong>.',
        },
    },
    supervisor: {
        heading: 'Who gets evaluated?',
        sub: 'Match by role, position, employee, or project. Leave empty for everyone.',
        flow: {
            cls: 'border-indigo-200 bg-indigo-50',
            icon: 'fa-user-tie text-indigo-500',
            html: 'Each matched employee is scored by their <strong>direct supervisor</strong>.',
        },
    },
    peer: {
        heading: 'Who gets evaluated?',
        sub: 'Match by role, position, employee, or project. Leave empty for everyone.',
        flow: {
            cls: 'border-cyan-200 bg-cyan-50',
            icon: 'fa-user-group text-cyan-600',
            html: 'Mutual evaluation: build pairs below — each pair is one role, position or project, and its members rate <strong>each other</strong> (never themselves).',
        },
    },
    upward: {
        heading: 'Who does the rating?',
        sub: 'Match by role, position, employee, or project — these are the raters, not the person being scored.',
        flow: {
            cls: 'border-amber-200 bg-amber-50',
            icon: 'fa-arrow-up text-amber-500',
            html: 'Each matched employee rates <strong>their own supervisor</strong>. HR reviews all raters and publishes only the average.',
        },
    },
};

const SUBJECT_COPY = {
    supervisor: { heading: 'Reviewer (optional)', sub: 'Pick specific reviewer(s) instead of the direct supervisor.' },
    peer:       { heading: 'Peer reviewer', sub: 'Pick who reviews the audience above. Leave empty to fall back to the direct supervisor.' },
    upward:     { heading: 'Who is being rated (optional)', sub: 'Pick the supervisor(s) to be rated. Each rater is matched only to their own direct supervisor — e.g. Siti rates her lead, Budi rates his, never each other\'s.' },
};

function updateAudienceCopy() {
    const type = document.getElementById('targetTypeSelect').value;
    const copy = AUDIENCE_COPY[type] || AUDIENCE_COPY.supervisor;

    const heading = document.getElementById('audienceHeading');
    const sub     = document.getElementById('audienceSubcopy');
    if (heading) heading.textContent = copy.heading;
    if (sub) sub.innerHTML = copy.sub;

    const box = document.getElementById('flowDirectionBox');
    if (box) {
        box.className = 'p-3.5 rounded-xl border flex items-start gap-3 ' + copy.flow.cls;
        box.innerHTML = `<i class="fas ${copy.flow.icon} text-base mt-0.5 shrink-0"></i>
            <span class="text-xs text-gray-700 leading-relaxed">${copy.flow.html}</span>`;
    }

    // Peer replaces both pickers with the pairs table.
    document.getElementById('audienceHeading')?.parentElement.classList.toggle('hidden', type === 'peer');

    // Counterpart picker only makes sense once there's someone other than
    // the matched employee involved — Self never shows it.
    const panel = document.getElementById('subjectPanel');
    if (panel) {
        const show = type !== 'self' && type !== 'peer'; // Peer uses the pairs table instead
        panel.classList.toggle('hidden', !show);
        if (show) {
            const sc = SUBJECT_COPY[type] || SUBJECT_COPY.supervisor;
            document.getElementById('subjectHeading').textContent = sc.heading;
            document.getElementById('subjectSubcopy').textContent = sc.sub;
        }
    }

    // Cross-filter rules (Lead → leads, Peer → colleagues, Upward →
    // subordinates) depend on the assessment type, so both lists must
    // re-render whenever it changes.
    window.tgtRenderList?.();
    window.subRenderList?.();
    window.pairsRefresh?.();
}
updateAudienceCopy();

// ── Counterpart picker: employee-only multi-select, mirrors the audience
//    builder above but simpler (one pool, no type tabs). "For who cannot
//    choose the same who fill" is enforced two ways: a live warning here
//    plus a hard block on submit (the deeper role/position/project overlap
//    is still caught server-side, since it needs each employee's actual
//    role/position/project membership). ─────────────────────────────────────
(function () {
    const POOL = (window.__kpiPools || {}).employee || [];
    const subSelected = new Map(); // id -> label

    const $search = document.getElementById('subSearch');
    const $list   = document.getElementById('subList');
    const $chosen = document.getElementById('subChosen');
    const $inputs = document.getElementById('subInputs');
    const $empty  = document.getElementById('subEmpty');
    const $count  = document.getElementById('subCount');
    const $clear  = document.getElementById('subClear');
    const $warn   = document.getElementById('subOverlapWarning');
    if (!$search) return;

    const esc = s => (s || '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function overlapIds() {
        const audienceIds = new Set((window.tgtGetEmployeeIds ? window.tgtGetEmployeeIds() : []));
        return [...subSelected.keys()].filter(id => audienceIds.has(id));
    }

    window.subRefreshOverlap = function () {
        const bad = new Set(overlapIds());
        $warn.classList.toggle('hidden', bad.size === 0);
        $chosen.querySelectorAll('[data-sub-id]').forEach(chip => {
            chip.classList.toggle('ring-2', bad.has(chip.dataset.subId));
            chip.classList.toggle('ring-red-400', bad.has(chip.dataset.subId));
        });
    };

    function renderChosen() {
        $chosen.innerHTML = '';
        $inputs.innerHTML = '';

        subSelected.forEach((label, id) => {
            const chip = document.createElement('div');
            chip.dataset.subId = id;
            chip.className = 'flex items-center justify-between gap-2 px-2 py-1 rounded-lg text-[11px] font-medium bg-amber-100 text-amber-800';
            chip.innerHTML = `<span class="truncate">${esc(label)}</span>
                <button type="button" class="shrink-0 w-4 h-4 flex items-center justify-center rounded hover:bg-black/10" aria-label="Remove">&times;</button>`;
            chip.querySelector('button').onclick = () => { subSelected.delete(id); renderChosen(); subRenderList(); };
            $chosen.appendChild(chip);

            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'subject_employees[]';
            inp.value = id;
            $inputs.appendChild(inp);
        });

        const n = subSelected.size;
        $count.textContent = n ? `(${n})` : '';
        $empty.classList.toggle('hidden', n > 0);
        $clear.classList.toggle('hidden', n === 0);
        window.subRefreshOverlap();
        window.tgtRenderList?.(); // re-filter Upward's rater options as the subject changes
    }

    // Filtered per assessment type once the audience has explicit employee
    // picks: Lead and Upward narrow to their direct leads (Upward's "who is
    // being rated" is, by definition, the leader of the raters picked as the
    // audience), Peer narrows to colleagues sharing the same leader. An empty
    // audience — or an audience that resolved to zero leaders on file —
    // leaves the full pool available so HR can still assign one by hand.
    function filteredPool() {
        const type = document.getElementById('targetTypeSelect')?.value;
        const audienceIds = window.tgtGetEmployeeIds ? window.tgtGetEmployeeIds() : [];
        if (!audienceIds.length) return { pool: POOL, note: '', noLeaderFound: false };

        if (type === 'supervisor' || type === 'upward') {
            const leaderIds = new Set(audienceIds.map(id => EMP_LEADER[id]).filter(Boolean));
            if (!leaderIds.size) {
                return { pool: POOL, note: '', noLeaderFound: true };
            }
            const label = type === 'upward' ? 'raters' : 'employees';
            return {
                pool: POOL.filter(o => leaderIds.has(o.id)),
                note: `<p class="px-2 py-2 text-[10px] text-amber-700 bg-amber-50 border-b border-amber-100">Showing only the direct leads of the selected ${label}.</p>`,
                noLeaderFound: false,
            };
        }

        if (type === 'peer') {
            const leaderIds = new Set(audienceIds.map(id => EMP_LEADER[id]).filter(Boolean));
            if (!leaderIds.size) {
                return { pool: POOL, note: '', noLeaderFound: true };
            }
            const audienceSet = new Set(audienceIds);
            return {
                pool: POOL.filter(o => !audienceSet.has(o.id) && EMP_LEADER[o.id] && leaderIds.has(EMP_LEADER[o.id])),
                note: `<p class="px-2 py-2 text-[10px] text-cyan-700 bg-cyan-50 border-b border-cyan-100">Showing only colleagues who share the same leader as the selected employees.</p>`,
                noLeaderFound: false,
            };
        }

        return { pool: POOL, note: '', noLeaderFound: false };
    }

    window.subGetIds = () => [...subSelected.keys()];

    function renderSubRow(o) {
        const checked = subSelected.has(o.id) ? 'checked' : '';
        return `<label class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-white cursor-pointer text-xs">
            <input type="checkbox" class="sub-cb rounded text-amber-600" data-id="${esc(o.id)}" data-label="${esc(o.name)}" ${checked}>
            <span class="font-medium text-gray-800">${esc(o.name)}</span>
            ${o.meta ? `<span class="text-gray-400">${esc(o.meta)}</span>` : ''}
        </label>`;
    }

    function wireSubCheckboxes() {
        $list.querySelectorAll('.sub-cb').forEach(cb => {
            cb.addEventListener('change', () => {
                if (cb.checked) subSelected.set(cb.dataset.id, cb.dataset.label);
                else subSelected.delete(cb.dataset.id);
                renderChosen();
            });
        });
    }

    window.subRenderList = function () {
        const q = ($search.value || '').trim().toLowerCase();
        const { pool, note, noLeaderFound } = filteredPool();

        if (noLeaderFound) {
            $list.innerHTML = `<p class="px-3 py-3 text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg m-1.5 flex items-start gap-2">
                <i class="fas fa-triangle-exclamation mt-0.5 shrink-0"></i>
                <span>There's no lead position on file for the employee(s) picked above. Pick a counterpart manually from the full list below.</span>
            </p>` + pool.map(renderSubRow).join('');
            wireSubCheckboxes();
            return;
        }

        const hits = pool.filter(o => !q || (o.name || '').toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q)).slice(0, 200);

        if (!hits.length) {
            $list.innerHTML = note + `<p class="px-2 py-3 text-[11px] text-gray-400">${pool.length ? 'No match' : 'Nothing to choose'}</p>`;
            return;
        }

        $list.innerHTML = note + hits.map(renderSubRow).join('');
        wireSubCheckboxes();
    };

    window.subClearAll = function () {
        subSelected.clear();
        renderChosen();
        subRenderList();
    };

    window.subSelectAllVisible = function () {
        const q = ($search.value || '').trim().toLowerCase();
        const { pool } = filteredPool();
        const hits = pool.filter(o => !q || (o.name || '').toLowerCase().includes(q) || (o.meta || '').toLowerCase().includes(q));
        hits.forEach(o => subSelected.set(o.id, o.name));
        renderChosen();
        subRenderList();
    };

    (window.__kpiSubjectChips0 || []).forEach(([id, label]) => {
        subSelected.set(String(id), label || String(id));
    });
    renderChosen();
    subRenderList();
})();

// ── Peer pairs (groups): mutual evaluation. A pair = ONE basis (role | position |
//    project) + ONE value + up to GROUP_MAX members who all rate each other. A
//    basis value can have many pairs. ────────────────────────────────────────────
(function () {
    const GROUP_MAX = 10;
    const POOLS = window.__kpiPools || {};
    const EMPS = POOLS.employee || [];
    const BASIS = [
        { id: 'role', name: 'Role' },
        { id: 'position', name: 'Position' },
        { id: 'project', name: 'Project' },
    ];
    const groups = []; // { name, basis, value, members:Set<string>, el, ... }

    const $panel = document.getElementById('pairsPanel');
    const $list = document.getElementById('groupList');
    const $empty = document.getElementById('groupEmpty');
    const $inputs = document.getElementById('groupInputs');
    if (!$panel) return;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const isPeer = () => document.getElementById('targetTypeSelect')?.value === 'peer';

    const valueItems = basis => (POOLS[basis] || []).map(o => ({ id: String(o.id), name: o.name }));
    const belongs = (e, basis, value) =>
        basis === 'role' ? (e.role_ids || []).includes(String(value))
        : basis === 'position' ? (e.position || '') === value
        : basis === 'project' ? (e.project_ids || []).includes(String(value))
        : false;

    // Small searchable dropdown: button + panel with a search box.
    function combo({ placeholder, getItems, onPick }) {
        const wrap = document.createElement('div');
        wrap.className = 'relative';
        wrap.innerHTML = `
            <button type="button" class="cb-btn w-full flex items-center justify-between gap-2 px-3 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-left hover:border-gray-300 focus:ring-2 focus:ring-cyan-300">
                <span class="cb-label truncate text-gray-400">${esc(placeholder)}</span>
                <i class="fas fa-chevron-down text-[10px] text-gray-400 shrink-0"></i>
            </button>
            <div class="cb-panel hidden absolute left-0 right-0 top-full mt-1 z-40 bg-white rounded-xl shadow-xl ring-1 ring-black/5 overflow-hidden">
                <div class="p-2 border-b border-gray-100"><input type="text" placeholder="Search…" autocomplete="off"
                    class="cb-search w-full px-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-300"></div>
                <div class="cb-items max-h-56 overflow-y-auto py-1"></div>
            </div>`;
        const btn = wrap.querySelector('.cb-btn'), label = wrap.querySelector('.cb-label');
        const panel = wrap.querySelector('.cb-panel'), search = wrap.querySelector('.cb-search'), box = wrap.querySelector('.cb-items');
        let disabled = false;

        function draw() {
            const q = search.value.trim().toLowerCase();
            const hits = getItems().filter(i => !q || i.name.toLowerCase().includes(q));
            box.innerHTML = hits.length
                ? hits.map(i => `<button type="button" data-id="${esc(i.id)}" class="cb-item w-full text-left px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50">${esc(i.name)}</button>`).join('')
                : '<p class="px-3 py-2 text-[11px] text-gray-400">No results</p>';
            box.querySelectorAll('.cb-item').forEach(b => b.onclick = () => {
                const item = hits.find(i => i.id === b.dataset.id);
                api.set(item);
                panel.classList.add('hidden');
                onPick(item);
            });
        }
        btn.onclick = e => {
            e.stopPropagation();
            if (disabled) return;
            const open = panel.classList.contains('hidden');
            document.querySelectorAll('.cb-panel').forEach(p => p.classList.add('hidden'));
            if (open) { panel.classList.remove('hidden'); search.value = ''; draw(); search.focus(); }
        };
        panel.onclick = e => e.stopPropagation();
        search.oninput = draw;

        const api = {
            el: wrap,
            set(item) {
                label.textContent = item ? item.name : placeholder;
                label.classList.toggle('text-gray-400', !item);
                label.classList.toggle('text-gray-800', !!item);
            },
            disable(v) { disabled = v; btn.classList.toggle('opacity-50', v); btn.classList.toggle('cursor-not-allowed', v); },
        };
        return api;
    }
    document.addEventListener('click', () => document.querySelectorAll('.cb-panel').forEach(p => p.classList.add('hidden')));

    function syncInputs() {
        $inputs.innerHTML = groups.map((g, i) =>
            `<input type="hidden" name="peer_groups[${i}][name]" value="${esc(g.name)}">` +
            `<input type="hidden" name="peer_groups[${i}][basis]" value="${esc(g.basis)}">` +
            `<input type="hidden" name="peer_groups[${i}][value]" value="${esc(g.value)}">` +
            [...g.members].map(m => `<input type="hidden" name="peer_groups[${i}][members][]" value="${esc(m)}">`).join('')
        ).join('');
        $empty.classList.toggle('hidden', groups.length > 0);
    }

    function buildCard(g, index) {
        const card = document.createElement('div');
        card.className = 'border border-gray-200 rounded-2xl p-4 bg-gray-50/40';
        card.innerHTML = `
            <div class="flex items-center gap-2 mb-3">
                <span class="g-no inline-flex w-6 h-6 items-center justify-center rounded-lg bg-cyan-100 text-cyan-700 text-xs font-bold"></span>
                <input type="text" maxlength="100" placeholder="Pair name (e.g. Developers – Squad 1)"
                    class="g-name flex-1 px-3 py-2 text-sm border border-gray-200 rounded-xl bg-white focus:ring-2 focus:ring-cyan-300">
                <button type="button" class="g-del w-8 h-8 rounded-lg bg-red-50 text-red-500 border border-red-200 hover:bg-red-100" title="Remove pair"><i class="fas fa-trash text-[11px]"></i></button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                <div><label class="block text-[11px] font-semibold text-gray-600 mb-1">1 · Choose by</label><div class="g-basis"></div></div>
                <div><label class="block text-[11px] font-semibold text-gray-600 mb-1">2 · <span class="g-value-lbl">Value</span></label><div class="g-value"></div></div>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[11px] font-semibold text-gray-600">3 · Employees <span class="g-count text-gray-400 font-normal"></span></label>
                    <div class="flex items-center gap-3">
                        <button type="button" class="g-all text-[11px] text-cyan-700 font-semibold hover:underline">Select all (max ${GROUP_MAX})</button>
                        <button type="button" class="g-none text-[11px] text-red-500 font-medium hover:underline">Clear</button>
                    </div>
                </div>
                <div class="relative mb-2">
                    <input type="text" placeholder="Search employees…" autocomplete="off"
                        class="g-search w-full pl-11 pr-3 py-3.5 text-sm border border-gray-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-cyan-300">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="g-warn hidden mb-2 p-2.5 rounded-lg border border-red-200 bg-red-50 text-[11px] text-red-700">
                    <i class="fas fa-triangle-exclamation mr-1"></i> Only ${GROUP_MAX} employees per pair — create a new pair for the rest.
                </div>
                <div class="g-emps border border-gray-200 rounded-xl bg-white max-h-56 overflow-y-auto p-1.5 space-y-0.5"></div>
            </div>`;

        const q = sel => card.querySelector(sel);
        q('.g-no').textContent = index + 1;
        const nameEl = q('.g-name'); nameEl.value = g.name;
        nameEl.oninput = () => { g.name = nameEl.value; syncInputs(); };
        q('.g-del').onclick = () => { groups.splice(groups.indexOf(g), 1); card.remove(); renumber(); syncInputs(); };

        const valueLbl = q('.g-value-lbl');
        const basisCb = combo({
            placeholder: 'Role / Position / Project',
            getItems: () => BASIS,
            onPick: item => {
                if (g.basis !== item.id) { g.basis = item.id; g.value = ''; g.members.clear(); }
                refresh(true);
            },
        });
        const valueCb = combo({
            placeholder: 'Choose basis first',
            getItems: () => valueItems(g.basis),
            onPick: item => {
                if (g.value !== item.id) { g.value = item.id; g.members.clear(); }
                refresh(false);
            },
        });
        q('.g-basis').appendChild(basisCb.el);
        q('.g-value').appendChild(valueCb.el);

        const search = q('.g-search');
        search.oninput = drawEmps;

        // Ticks people currently listed (respecting the search) until the pair is
        // full at GROUP_MAX; warns when more are left over.
        q('.g-all').onclick = () => {
            if (!g.basis || !g.value) return;
            const term = search.value.trim().toLowerCase();
            const hits = candidates().filter(e => !term || (e.name + ' ' + (e.meta || '')).toLowerCase().includes(term));
            let left = 0;
            hits.forEach(e => {
                if (g.members.has(e.id)) return;
                if (g.members.size < GROUP_MAX) g.members.add(e.id); else left++;
            });
            drawEmps();
            syncInputs();
            if (left) q('.g-warn').classList.remove('hidden');
        };
        q('.g-none').onclick = () => { g.members.clear(); drawEmps(); syncInputs(); };

        function candidates() {
            if (!g.basis || !g.value) return [];
            return EMPS.filter(e => belongs(e, g.basis, g.value));
        }

        function drawEmps() {
            const box = q('.g-emps');
            if (!g.basis || !g.value) {
                box.innerHTML = '<p class="px-2 py-3 text-[11px] text-gray-400">Choose a role, position or project first.</p>';
                return updateCount();
            }
            const term = search.value.trim().toLowerCase();
            const hits = candidates().filter(e => !term || (e.name + ' ' + (e.meta || '')).toLowerCase().includes(term))
                .sort((a, b) => (g.members.has(b.id) ? 1 : 0) - (g.members.has(a.id) ? 1 : 0)); // chosen first (stable)
            box.innerHTML = hits.length
                ? hits.map(e => `<label class="flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-gray-50 cursor-pointer text-xs">
                        <input type="checkbox" class="g-cb rounded text-cyan-600" data-id="${esc(e.id)}" ${g.members.has(e.id) ? 'checked' : ''}>
                        <span class="font-medium text-gray-800">${esc(e.name)}</span>
                        ${e.meta ? `<span class="text-gray-400">${esc(e.meta)}</span>` : ''}
                    </label>`).join('')
                : '<p class="px-2 py-3 text-[11px] text-gray-400">No employee matches this value.</p>';
            box.querySelectorAll('.g-cb').forEach(cb => cb.onchange = () => {
                if (cb.checked) {
                    if (g.members.size >= GROUP_MAX) {
                        cb.checked = false;
                        q('.g-warn').classList.remove('hidden');
                        return;
                    }
                    g.members.add(cb.dataset.id);
                } else {
                    g.members.delete(cb.dataset.id);
                }
                updateCount();
                syncInputs();
                const keep = box.scrollTop;
                drawEmps(); // re-sort so chosen people stay on top
                box.scrollTop = keep;
            });
            updateCount();
        }

        function updateCount() {
            const n = g.members.size;
            q('.g-count').textContent = `(${n}/${GROUP_MAX} selected)`;
            if (n < GROUP_MAX) q('.g-warn').classList.add('hidden'); // shown only when a pick is refused
        }

        function refresh(basisChanged) {
            const b = BASIS.find(x => x.id === g.basis);
            basisCb.set(b || null);
            valueLbl.textContent = b ? `Which ${b.name.toLowerCase()}` : 'Value';
            valueCb.disable(!g.basis);
            const item = g.value ? valueItems(g.basis).find(i => i.id === g.value) : null;
            valueCb.set(item || null);
            if (!g.value) valueCb.set(null);
            search.value = '';
            drawEmps();
            syncInputs();
        }

        g.el = card;
        g.setNo = n => { q('.g-no').textContent = n; };
        refresh();
        return card;
    }

    function renumber() { groups.forEach((g, i) => g.setNo(i + 1)); }

    window.groupAdd = function (data) {
        const g = { name: data?.name || '', basis: data?.basis || '', value: data?.value || '', members: new Set((data?.members || []).map(String)) };
        groups.push(g);
        $list.appendChild(buildCard(g, groups.length - 1));
        syncInputs();
    };

    // Validation summary used by the submit handler; returns an error string or null.
    window.groupsError = function () {
        if (!groups.length) return 'Add at least one pair for a Peer template.';
        for (const [i, g] of groups.entries()) {
            const label = g.name ? `"${g.name}"` : `Pair ${i + 1}`;
            if (!g.basis || !g.value) return `${label}: choose a role, position or project.`;
            if (g.members.size < 2) return `${label}: pick at least 2 employees.`;
        }
        return null;
    };
    window.pairsCount = () => (isPeer() ? groups.length : 0);
    window.pairsRefresh = function () { $panel.classList.toggle('hidden', !isPeer()); syncInputs(); };

    (window.__kpiGroups0 || []).forEach(g => window.groupAdd(g));
    window.pairsRefresh();
})();

document.getElementById('templateForm').addEventListener('submit', function (e) {
    if (document.getElementById('targetTypeSelect').value === 'peer') {
        const err = window.groupsError?.();
        if (err) {
            e.preventDefault();
            showToast(err, 'error');
            return;
        }
        // Audience / reviewer pickers are hidden for Peer — don't submit their stale picks.
        document.getElementById('tgtInputs').innerHTML = '';
        document.getElementById('subInputs').innerHTML = '';
        return;
    }
    document.getElementById('groupInputs').innerHTML = ''; // pairs only apply to Peer
    const audienceIds = new Set(window.tgtGetEmployeeIds ? window.tgtGetEmployeeIds() : []);
    const subjectInputs = document.querySelectorAll('input[name="subject_employees[]"]');
    const overlap = !window.pairsCount?.() && [...subjectInputs].some(inp => audienceIds.has(inp.value));
    if (overlap) {
        e.preventDefault();
        showToast('An employee can\'t be picked as both the audience and the counterpart. Remove the overlapping name from one side.', 'error');
    }
});

reindexRows();
reindexScales();
// A stored template keeps its weights; only fill in evenly when none are set yet.
(function () {
    const inputs = [...document.querySelectorAll('#indicatorList .weight-input')].filter(i => !i.disabled);
    if (inputs.length && inputs.every(i => i.value === '')) autoDistributeWeights();
    else weightsManual = true;
})();
updateWeightSum();
syncScoreDivisor();
</script>
@endsection
