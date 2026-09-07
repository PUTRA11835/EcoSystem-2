<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProject;
use App\Models\Employee;
use App\Models\EmployeeBasicData;
use App\Models\EmployeeRole;
use App\Models\KpiIndicator;
use App\Models\KpiScoringScale;
use App\Models\KpiTemplate;
use App\Support\KpiAssignments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KPI Template Controller
 *
 * Manages the KPI template library:
 *   - Role-based templates (one template → one role)
 *   - Dynamic indicator management (add/remove rows with weight validation)
 *   - Toggle active/inactive without deleting
 *
 * Accessible via Management → HR & General → KPI Settings.
 */
class KpiTemplateController extends Controller
{
    /**
     * List all KPI templates.
     */
    public function index(Request $request)
    {
        $user = session('user');
        $search = $request->query('search', '');
        $statusFilter = $request->query('status', '');
        $periodTypeFilter = $request->query('period_type', '');

        $templates = KpiTemplate::with(['role', 'indicators', 'scoringScales'])
            ->withCount('evaluations')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $roles = EmployeeRole::orderBy('name')->get();

        $canManage = $this->can('general.kpi-evaluation.templates.manage');
        $canCreate = $this->canDo('general.kpi-evaluation.templates', 'create') || $canManage;
        $canEdit   = $this->canDo('general.kpi-evaluation.templates', 'edit')   || $canManage;
        $canDelete = $this->canDo('general.kpi-evaluation.templates', 'delete') || $canManage;

        return view('hr-general.kpi.templates', compact(
            'user',
            'templates',
            'roles',
            'canManage',
            'canCreate',
            'canEdit',
            'canDelete',
            'search',
            'statusFilter',
            'periodTypeFilter'
        ));
    }

    /**
     * Full-page create form.
     */
    public function create()
    {
        $template   = new KpiTemplate(['target_type' => 'supervisor', 'period_type' => 'monthly', 'score_divisor' => 5]);
        $indicators = collect();
        $scales     = collect(KpiScoringScale::defaultRows())->map(fn ($r) => (object) $r);
        $mode       = 'create';

        return view('hr-general.kpi.template-form', array_merge(
            compact('template', 'indicators', 'scales', 'mode'),
            $this->formOptions()
        ));
    }

    /**
     * Full-page edit form.
     */
    public function edit(int $id)
    {
        $template   = KpiTemplate::with(['indicators', 'scoringScales'])->findOrFail($id);
        $indicators = $template->indicators->sortBy('order_seq')->values();
        $scales     = $template->scoringScales->isNotEmpty()
            ? $template->scoringScales->sortByDesc('scale_value')->values()
            : collect(KpiScoringScale::defaultRows())->map(fn ($r) => (object) $r);
        $mode       = 'edit';

        return view('hr-general.kpi.template-form', array_merge(
            compact('template', 'indicators', 'scales', 'mode'),
            $this->formOptions()
        ));
    }

    /** Roles, positions, employees and projects offered as targeting options on the form. */
    private function formOptions(): array
    {
        $roles = EmployeeRole::orderBy('name')->get(['id', 'name']);

        $positions = EmployeeBasicData::whereNotNull('position')
            ->where('position', '!=', '')
            ->distinct()
            ->orderBy('position')
            ->pluck('position')
            ->values();

        $employees = Employee::with('basicData')
            ->where('is_active', true)
            ->get()
            ->map(fn ($e) => [
                'id'    => (string) $e->employee_id,
                'name'  => $e->basicData?->full_name ?: $e->eci,
                'meta'  => trim(($e->eci ?? '') . ' · ' . ($e->basicData?->position ?? ''), ' ·'),
            ])
            ->sortBy('name')
            ->values();

        $projects = DeliveryProject::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => ['id' => (string) $p->id, 'name' => $p->name])
            ->values();

        $unitOptions = ['%', 'score', 'points', 'count', 'days', 'hours', 'rating', 'IDR', 'ratio'];

        return compact('roles', 'positions', 'employees', 'projects', 'unitOptions');
    }

    /**
     * POST: Create a new template with its indicators.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'                => 'required|string|max:200',
            'description'         => 'nullable|string',
            'role_id'             => 'nullable|integer|exists:employee_role,id',
            'period_type'         => 'required|in:monthly,quarterly,annual',
            'target_type'         => 'nullable|in:self,supervisor,peer',
            'target_roles'        => 'nullable|array',
            'target_roles.*'      => 'integer|exists:employee_role,id',
            'target_positions'    => 'nullable|array',
            'target_positions.*'  => 'string|max:150',
            'target_employees'    => 'nullable|array',
            'target_employees.*'  => 'integer',
            'target_projects'     => 'nullable|array',
            'target_projects.*'   => 'integer',
            'score_divisor'       => 'nullable|integer|min:1|max:100',
            'indicators'                => 'required|array|min:1',
            'indicators.*.name'         => 'required|string|max:300',
            'indicators.*.answer_type'  => 'nullable|in:rating,paragraph',
            'indicators.*.rating_max'   => 'nullable|integer|min:1|max:100',
            'indicators.*.weight'       => 'nullable|numeric|min:0|max:100',
            'scales'                    => 'nullable|array',
            'scales.*.scale_value'      => 'required_with:scales|integer|min:1|max:100',
            'scales.*.category'         => 'nullable|string|max:100',
        ]);

        if ($msg = $this->weightError($request)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withInput()->with('error', $msg);
        }

        DB::beginTransaction();
        try {
            $user = session('user');

            $template = KpiTemplate::create([
                'name'             => $request->name,
                'description'      => $request->description,
                'role_id'         => $request->role_id,
                'period_type'     => $request->period_type,
                'target_type'     => $request->target_type ?? 'supervisor',
                'target_roles'    => $this->cleanList($request->input('target_roles', [])),
                'target_positions'=> $this->cleanList($request->input('target_positions', [])),
                'target_employees'=> $this->cleanList($request->input('target_employees', [])),
                'target_projects' => $this->cleanList($request->input('target_projects', [])),
                'score_divisor'   => $this->resolveDivisor($request),
                'is_active'       => true,
                'created_by'      => $user['id'] ?? null,
                'updated_by'      => $user['id'] ?? null,
            ]);

            $this->syncIndicators($template, $request);
            $this->syncScales($template, $request);

            DB::commit();

            // Materialise this template's assignments for the current period so the
            // coverage table + My KPI reflect it immediately — no per-employee clicks.
            KpiAssignments::syncPeriod(Carbon::now()->format('Y-m'), $user['id'] ?? null, true);

            if ($request->wantsJson()) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'KPI template created successfully.',
                    'template_id' => $template->id,
                ]);
            }

            return redirect()->route('general.kpi-evaluation.templates.index')
                ->with('success', 'KPI template "' . $template->name . '" created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', 'Failed to create template.');
        }
    }

    /**
     * POST: Update an existing template and its indicators.
     * Indicators are replaced completely (delete + re-insert) to avoid drift.
     * Cannot edit if the template has approved evaluations.
     */
    public function update(Request $request, int $id)
    {
        $template = KpiTemplate::with('indicators')->findOrFail($id);

        $request->validate([
            'name'                => 'required|string|max:200',
            'description'         => 'nullable|string',
            'role_id'             => 'nullable|integer|exists:employee_role,id',
            'period_type'         => 'required|in:monthly,quarterly,annual',
            'target_type'         => 'nullable|in:self,supervisor,peer',
            'target_roles'        => 'nullable|array',
            'target_roles.*'      => 'integer|exists:employee_role,id',
            'target_positions'    => 'nullable|array',
            'target_positions.*'  => 'string|max:150',
            'target_employees'    => 'nullable|array',
            'target_employees.*'  => 'integer',
            'target_projects'     => 'nullable|array',
            'target_projects.*'   => 'integer',
            'score_divisor'       => 'nullable|integer|min:1|max:100',
            'indicators'                => 'required|array|min:1',
            'indicators.*.name'         => 'required|string|max:300',
            'indicators.*.answer_type'  => 'nullable|in:rating,paragraph',
            'indicators.*.rating_max'   => 'nullable|integer|min:1|max:100',
            'indicators.*.weight'       => 'nullable|numeric|min:0|max:100',
            'scales'                    => 'nullable|array',
            'scales.*.scale_value'      => 'required_with:scales|integer|min:1|max:100',
            'scales.*.category'         => 'nullable|string|max:100',
        ]);

        if ($msg = $this->weightError($request)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withInput()->with('error', $msg);
        }

        DB::beginTransaction();
        try {
            $user = session('user');

            $template->update([
                'name'             => $request->name,
                'description'      => $request->description,
                'role_id'         => $request->role_id,
                'period_type'     => $request->period_type,
                'target_type'     => $request->target_type ?? $template->target_type ?? 'supervisor',
                'target_roles'    => $this->cleanList($request->input('target_roles', [])),
                'target_positions'=> $this->cleanList($request->input('target_positions', [])),
                'target_employees'=> $this->cleanList($request->input('target_employees', [])),
                'target_projects' => $this->cleanList($request->input('target_projects', [])),
                'score_divisor'   => $this->resolveDivisor($request),
                'updated_by'      => $user['id'] ?? null,
            ]);

            // Replace indicators + scale rows wholesale (avoids complex diffing)
            $template->indicators()->delete();
            $this->syncIndicators($template, $request);

            $template->scoringScales()->delete();
            $this->syncScales($template, $request);

            DB::commit();

            // Materialise this template's assignments for the current period so the
            // coverage table + My KPI reflect it immediately — no per-employee clicks.
            KpiAssignments::syncPeriod(Carbon::now()->format('Y-m'), $user['id'] ?? null, true);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Template updated successfully.']);
            }

            return redirect()->route('general.kpi-evaluation.templates.index')
                ->with('success', 'KPI template updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->withInput()->with('error', 'Failed to update template.');
        }
    }

    /**
     * POST: Toggle active/inactive.
     */
    public function toggleActive(Request $request, int $id)
    {
        $template = KpiTemplate::findOrFail($id);
        $template->is_active = !$template->is_active;
        $template->save();

        // Activating fills in the period's assignments; deactivating prunes the
        // untouched ones.
        KpiAssignments::syncPeriod(Carbon::now()->format('Y-m'), session('user')['id'] ?? null, true);

        $label = $template->is_active ? 'activated' : 'deactivated';

        if ($request->wantsJson()) {
            return response()->json([
                'success'   => true,
                'message'   => "Template {$label}.",
                'is_active' => $template->is_active,
            ]);
        }
        return redirect()->back()->with('success', "Template {$label}.");
    }

    /**
     * POST: Delete a template (only if no evaluations reference it).
     */
    public function delete(Request $request, int $id)
    {
        $template = KpiTemplate::withCount('evaluations')->findOrFail($id);

        if ($template->evaluations_count > 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete: {$template->evaluations_count} evaluation(s) reference this template. Deactivate it instead.",
                ], 422);
            }
            return redirect()->back()
                ->with('error', "Cannot delete: this template is used by {$template->evaluations_count} evaluation(s). Deactivate it instead.");
        }

        $template->indicators()->delete();
        $template->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Template deleted.']);
        }
        return redirect()->route('general.kpi-evaluation.templates.index')->with('success', 'Template deleted.');
    }

    /**
     * AJAX: Return indicators for a given template (for dynamic form population).
     */
    public function getIndicators(int $id)
    {
        $template = KpiTemplate::with('indicators')->findOrFail($id);
        return response()->json([
            'template'   => [
                'id'                => $template->id,
                'name'              => $template->name,
                'description'       => $template->description,
                'role_id'           => $template->role_id,
                'period_type'       => $template->period_type,
                'target_type'       => $template->target_type ?? 'supervisor',
                'target_type_label' => $template->target_type_label,
            ],
            'indicators' => $template->indicators->map(fn($i) => [
                'id'               => $i->id,
                'name'             => $i->name,
                'answer_type'      => $i->answer_type ?? 'rating',
                'rating_max'       => $i->rating_max,
                'description'      => $i->description,
                'measurement_unit' => $i->measurement_unit,
                'target_value'     => $i->target_value,
                'weight'           => $i->weight,
                'order_seq'        => $i->order_seq,
            ]),
        ]);
    }

    /** Rating indicators must sum to 100%. Paragraph rows are ignored. Returns an error string or null. */
    private function weightError(Request $request): ?string
    {
        $total = collect($request->input('indicators', []))
            ->reject(fn ($ind) => ($ind['answer_type'] ?? 'rating') === 'paragraph')
            ->sum(fn ($ind) => (float) ($ind['weight'] ?? 0));

        if (abs($total - 100) > 0.01) {
            $shown = rtrim(rtrim(number_format($total, 2), '0'), '.');
            return "Scored indicator weights must sum to 100%. Current total: {$shown}%";
        }
        return null;
    }

    /** Divisor for the weighted-score formula — explicit input, else the top scale value, else 5. */
    private function resolveDivisor(Request $request): int
    {
        if ($request->filled('score_divisor')) {
            return max(1, (int) $request->input('score_divisor'));
        }
        $top = collect($request->input('scales', []))->max('scale_value');
        return (int) ($top ?: 5);
    }

    /** (Re)create indicator rows from the submitted repeater. */
    private function syncIndicators(KpiTemplate $template, Request $request): void
    {
        foreach (array_values($request->input('indicators', [])) as $seq => $ind) {
            $isPara = ($ind['answer_type'] ?? 'rating') === 'paragraph';
            KpiIndicator::create([
                'template_id'      => $template->id,
                'name'             => $ind['name'],
                'answer_type'      => $isPara ? 'paragraph' : 'rating',
                'rating_max'       => (!$isPara && !empty($ind['rating_max'])) ? (int) $ind['rating_max'] : null,
                'description'      => $ind['description'] ?? null,
                'measurement_unit' => $ind['measurement_unit'] ?? null,
                'target_value'     => ($ind['target_value'] ?? '') === '' ? null : $ind['target_value'],
                'weight'           => $isPara ? 0 : (float) ($ind['weight'] ?? 0),
                'order_seq'        => $seq + 1,
            ]);
        }
    }

    /** (Re)create the scoring-scale rows; skip entirely blank rows. */
    private function syncScales(KpiTemplate $template, Request $request): void
    {
        $rows = collect($request->input('scales', []))
            ->filter(fn ($r) => ($r['scale_value'] ?? '') !== '' || ($r['category'] ?? '') !== '')
            ->values();

        foreach ($rows as $seq => $r) {
            KpiScoringScale::create([
                'template_id'       => $template->id,
                'scale_value'       => (int) $r['scale_value'],
                'category'          => $r['category'] ?? null,
                'definition'        => $r['definition'] ?? null,
                'achievement_label' => $r['achievement_label'] ?? null,
                'achievement_min'   => ($r['achievement_min'] ?? '') === '' ? null : $r['achievement_min'],
                'achievement_max'   => ($r['achievement_max'] ?? '') === '' ? null : $r['achievement_max'],
                'description'        => $r['description'] ?? null,
                'order_seq'         => $seq + 1,
            ]);
        }
    }

    /** Drop empty/blank entries; return null when nothing is left. */
    private function cleanList($list): ?array
    {
        $clean = array_values(array_filter(
            is_array($list) ? $list : [],
            fn($v) => $v !== null && $v !== ''
        ));
        return $clean ?: null;
    }

    private function can(string $slug): bool
    {
        $shared = \Illuminate\Support\Facades\View::getShared();
        $slugs  = $shared['permSlugs'] ?? [];
        return in_array($slug, $slugs);
    }

    /**
     * Granular capability check (view|create|edit|delete) for a menu slug,
     * backed by the permMatrix shared from ShareMenuPermissions.
     */
    private function canDo(string $slug, string $action = 'view'): bool
    {
        $shared = \Illuminate\Support\Facades\View::getShared();
        $matrix = $shared['permMatrix'] ?? [];
        return (bool) ($matrix[$slug][$action] ?? false);
    }
}
