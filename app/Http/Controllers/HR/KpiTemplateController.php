<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRole;
use App\Models\KpiEvaluation;
use App\Models\KpiIndicator;
use App\Models\KpiTemplate;
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

        $templates = KpiTemplate::with(['role', 'indicators'])
            ->withCount('evaluations')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $roles = EmployeeRole::orderBy('name')->get();
        $canManage = $this->can('general.settings.kpi.manage');

        return view('hr-general.settings.kpi.index', compact(
            'user',
            'templates',
            'roles',
            'canManage',
            'search',
            'statusFilter',
            'periodTypeFilter'
        ));
    }

    /**
     * POST: Create a new template with its indicators.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string',
            'role_id'            => 'nullable|integer|exists:employee_role,id',
            'period_type'        => 'required|in:monthly,quarterly,annual',
            'target_type'        => 'nullable|in:self,supervisor,peer',
            'indicators'         => 'required|array|min:1',
            'indicators.*.name'  => 'required|string|max:300',
            'indicators.*.weight'=> 'required|numeric|min:0.01|max:100',
        ]);

        // Validate total weight
        $totalWeight = collect($request->indicators)->sum('weight');
        if (abs($totalWeight - 100) > 0.01) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Indicator weights must sum to 100%. Current total: {$totalWeight}%",
                ], 422);
            }
            return redirect()->back()->withInput()
                ->with('error', "Indicator weights must sum to 100%. Current total: {$totalWeight}%");
        }

        DB::beginTransaction();
        try {
            $user = session('user');

            $template = KpiTemplate::create([
                'name'        => $request->name,
                'description' => $request->description,
                'role_id'     => $request->role_id,
                'period_type' => $request->period_type,
                'target_type' => $request->target_type ?? 'supervisor',
                'is_active'   => true,
                'created_by'  => $user['id'] ?? null,
                'updated_by'  => $user['id'] ?? null,
            ]);

            foreach ($request->indicators as $seq => $ind) {
                KpiIndicator::create([
                    'template_id'      => $template->id,
                    'name'             => $ind['name'],
                    'description'      => $ind['description'] ?? null,
                    'measurement_unit' => $ind['measurement_unit'] ?? null,
                    'target_value'     => $ind['target_value'] ?? null,
                    'weight'           => $ind['weight'],
                    'order_seq'        => $seq + 1,
                ]);
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'KPI template created successfully.',
                    'template_id' => $template->id,
                ]);
            }

            return redirect()->route('general.settings.kpi.index')
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
            'name'               => 'required|string|max:200',
            'description'        => 'nullable|string',
            'role_id'            => 'nullable|integer|exists:employee_role,id',
            'period_type'        => 'required|in:monthly,quarterly,annual',
            'indicators'         => 'required|array|min:1',
            'indicators.*.name'  => 'required|string|max:300',
            'indicators.*.weight'=> 'required|numeric|min:0.01|max:100',
        ]);

        $totalWeight = collect($request->indicators)->sum('weight');
        if (abs($totalWeight - 100) > 0.01) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => "Indicator weights must sum to 100%. Current total: {$totalWeight}%",
                ], 422);
            }
            return redirect()->back()->withInput()
                ->with('error', "Weights must sum to 100%. Current total: {$totalWeight}%");
        }

        DB::beginTransaction();
        try {
            $user = session('user');

            $template->update([
                'name'        => $request->name,
                'description' => $request->description,
                'role_id'     => $request->role_id,
                'period_type' => $request->period_type,
                'updated_by'  => $user['id'] ?? null,
            ]);

            // Delete old indicators and re-create (avoids complex diffing)
            $template->indicators()->delete();

            foreach ($request->indicators as $seq => $ind) {
                KpiIndicator::create([
                    'template_id'      => $template->id,
                    'name'             => $ind['name'],
                    'description'      => $ind['description'] ?? null,
                    'measurement_unit' => $ind['measurement_unit'] ?? null,
                    'target_value'     => $ind['target_value'] ?? null,
                    'weight'           => $ind['weight'],
                    'order_seq'        => $seq + 1,
                ]);
            }

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Template updated successfully.']);
            }

            return redirect()->route('general.settings.kpi.index')
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
        return redirect()->route('general.settings.kpi.index')->with('success', 'Template deleted.');
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
                'description'      => $i->description,
                'measurement_unit' => $i->measurement_unit,
                'target_value'     => $i->target_value,
                'weight'           => $i->weight,
                'order_seq'        => $i->order_seq,
            ]),
        ]);
    }

    private function can(string $slug): bool
    {
        $shared = \Illuminate\Support\Facades\View::getShared();
        $slugs  = $shared['permSlugs'] ?? [];
        return in_array($slug, $slugs);
    }
}
