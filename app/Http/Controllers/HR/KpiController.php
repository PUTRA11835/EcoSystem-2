<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBasicData;
use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationDetail;
use App\Models\KpiTeam;
use App\Models\KpiTemplate;
use App\Support\KpiAssignments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * HR KPI Controller
 *
 * Handles the HR-side of KPI management:
 *   - Dashboard with aggregated performance analytics
 *   - Evaluation list (create, review, approve, reject)
 *   - Excel export
 *
 * All data-mutation routes use POST (no PUT/DELETE per project convention).
 */
class KpiController extends Controller
{
    // ── Dashboard ────────────────────────────────────────────────────────────

    /**
     * KPI Evaluation Dashboard (HR view).
     * Period is selected via month/year picker; defaults to current month.
     */
    public function dashboard(Request $request)
    {
        $user = session('user');

        $periodMonth = $request->query('period', Carbon::now()->format('Y-m'));

        try {
            $period = Carbon::createFromFormat('Y-m', $periodMonth);
        } catch (\Exception $e) {
            $period = Carbon::now();
            $periodMonth = $period->format('Y-m');
        }

        $employeeId       = $user['id'] ?? null;
        $canCreate        = $this->can('general.kpi-evaluation.create');
        $canReview        = $this->can('general.kpi-evaluation.review');
        $canApprove       = $this->can('general.kpi-evaluation.approve');
        $isSupervisorOnly = !($canCreate || $canApprove);

        // Coverage follows template targeting: materialise / prune the period's
        // evaluations so the table only ever *shows* what the templates dictate.
        if ($canCreate) {
            KpiAssignments::syncPeriod($periodMonth, $employeeId, true);
        }

        $filterType     = $request->query('filter_type', 'all');
        $search         = $request->query('search', '');
        $statusFilter   = $request->query('status', '');
        $projectId      = $request->query('project_id', '');
        $positionFilter = $request->query('position', '');
        $roleId         = $request->query('role_id', '');
        $templateId     = $request->query('template_id', '');
        $supervisorId   = $request->query('supervisor', $request->query('supervisor_id', ''));
        $typeFilter     = $request->query('type', ''); // '' | self | lead

        // Scope determination (Supervisor defaults to 'my_team')
        if ($filterType === 'my_team' || $request->query('scope') === 'my_team') {
            $scope = 'my_team';
        } elseif ($isSupervisorOnly) {
            $scope = 'my_team';
        } else {
            $scope = $request->query('scope', 'all');
        }

        // ── Summary counts ───────────────────────────────────────────────────
        $totalEmployees = Employee::where('is_active', true)->count();

        $evaluations = KpiEvaluation::where('period_month', $periodMonth)->get();

        $countCreated     = $evaluations->count();
        $countNotCreated  = max(0, $totalEmployees - $countCreated);
        $countDraft       = $evaluations->where('status', KpiEvaluation::STATUS_DRAFT)->count();
        $countSelfAssessed = $evaluations->where('status', KpiEvaluation::STATUS_SELF_ASSESSED)->count();
        $countReviewed    = $evaluations->where('status', KpiEvaluation::STATUS_REVIEWED)->count();
        $countCompleted   = $evaluations->where('status', KpiEvaluation::STATUS_COMPLETED)->count();
        $countApproved    = $evaluations->where('status', KpiEvaluation::STATUS_HR_APPROVED)->count();
        $countRejected    = $evaluations->where('status', KpiEvaluation::STATUS_HR_REJECTED)->count();
        $countSubmitted   = $countCompleted + $countSelfAssessed + $countReviewed;

        // Average score of approved evaluations
        $avgScore = KpiEvaluation::where('period_month', $periodMonth)
            ->where('status', KpiEvaluation::STATUS_HR_APPROVED)
            ->whereNotNull('overall_score')
            ->avg('overall_score');

        // ── Score by department (for bar chart) ──────────────────────────────
        $scoreByDept = $this->getScoreByDepartment($periodMonth);

        // ── Monthly trend (last 6 months) ─────────────────────────────────────
        $monthlyTrend = $this->getMonthlyTrend(6);

        // ── All evaluations for the period (for lookup map) ───────────────────
        $recentEvaluations = KpiEvaluation::with(['employee.basicData', 'supervisor.basicData', 'template', 'details'])
            ->where('period_month', $periodMonth)
            ->get();

        // ── Active templates ─────────────────────────────────────────────────
        $activeTemplates = KpiTemplate::where('is_active', true)->withCount('indicators')->get();

        // ── Active employees query for coverage table (with multi-filter support) ──
        $empQuery = Employee::with(['basicData', 'deliveryProjects', 'kpiEvaluations', 'roles'])
            ->where('is_active', true);

        // Scope filter
        if ($scope === 'my_team' && $employeeId) {
            $empQuery->where(function ($q) use ($employeeId, $periodMonth) {
                $q->whereHas('basicData', fn($b) => $b->where('direct_supervision', $employeeId))
                  ->orWhereHas('kpiEvaluations', fn($k) => $k->where('period_month', $periodMonth)->where('supervisor_id', $employeeId));
            });
        }

        // Filter by Employee Search (Name or ECI)
        if ($search) {
            $empQuery->where(function ($q) use ($search) {
                $q->where('eci', 'like', "%{$search}%")
                  ->orWhereHas('basicData', function ($b) use ($search) {
                      $b->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('nick_name', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by Position
        if ($positionFilter) {
            $empQuery->whereHas('basicData', fn($b) => $b->where('position', $positionFilter));
        }

        // Filter by Supervisor (text search by name, ECI, or employee_id)
        if ($supervisorId) {
            $matchedSupIds = Employee::where(function ($sq) use ($supervisorId) {
                $sq->where('eci', 'like', "%{$supervisorId}%")
                   ->orWhere('employee_id', $supervisorId)
                   ->orWhereHas('basicData', function ($b) use ($supervisorId) {
                       $b->where('first_name', 'like', "%{$supervisorId}%")
                         ->orWhere('last_name', 'like', "%{$supervisorId}%")
                         ->orWhere('nick_name', 'like', "%{$supervisorId}%");
                   });
            })->pluck('employee_id');

            $empQuery->where(function ($q) use ($matchedSupIds, $periodMonth) {
                $q->whereHas('basicData', fn($b) => $b->whereIn('direct_supervision', $matchedSupIds))
                  ->orWhereHas('kpiEvaluations', fn($k) => $k->where('period_month', $periodMonth)->whereIn('supervisor_id', $matchedSupIds));
            });
        }

        // Filter by Template
        if ($templateId) {
            $empQuery->whereHas('kpiEvaluations', fn($k) => $k->where('period_month', $periodMonth)->where('template_id', $templateId));
        }

        // Filter by Assessment Type (self = Evaluasi Mandiri, lead = Penilaian Atasan)
        if ($typeFilter === 'self' || $typeFilter === 'lead') {
            $targetTypes = $typeFilter === 'self' ? ['self'] : ['supervisor', 'peer'];
            $empQuery->whereHas('kpiEvaluations', fn($k) => $k->where('period_month', $periodMonth)
                ->whereHas('template', fn($t) => $t->whereIn('target_type', $targetTypes)));
        }

        // Filter by Role
        if ($roleId) {
            $empQuery->whereHas('roles', fn($rq) => $rq->where('employee_role.id', $roleId));
        }

        // Filter by Project
        if ($projectId) {
            $empQuery->whereHas('deliveryProjects', fn($pq) => $pq->where('delivery_projects.id', $projectId));
        }

        // Filter by Status
        if ($statusFilter) {
            if ($statusFilter === 'not_created') {
                $empQuery->whereDoesntHave('kpiEvaluations', fn($k) => $k->where('period_month', $periodMonth));
            } else {
                $empQuery->whereHas('kpiEvaluations', fn($k) => $k->where('period_month', $periodMonth)->where('status', $statusFilter));
            }
        }

        // Rows per page configuration (default 10 as per design)
        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [5, 10, 15, 20, 25, 50])) {
            $perPage = 10;
        }

        // Paginate active employees for coverage table (with sorting by basicData name)
        $activeEmployees = (clone $empQuery)
            ->leftJoin('employee_basic_data as bd', 'employee.employee_id', '=', 'bd.employee_id')
            ->select('employee.*')
            ->orderBy('bd.first_name', 'asc')
            ->orderBy('bd.last_name', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        // ── Templates offered to each employee on this page ──────────────────
        // Targeting (roles / positions) now lives on the template itself, so the
        // dashboard just hands every employee the templates that match them.
        $eligibleTemplates = [];
        foreach ($activeEmployees as $emp) {
            $eligibleTemplates[$emp->employee_id] = $activeTemplates
                ->filter(fn($tmpl) => $tmpl->appliesTo($emp))
                ->values();
        }

        // ── Resolve each employee's reporting-line supervisor to a name ──────
        $supNameIds = collect($activeEmployees->items())
            ->map(fn($e) => $e->basicData?->direct_supervision)
            ->filter()->unique()->values();
        $supervisorNames = Employee::with('basicData')->whereIn('employee_id', $supNameIds)->get()
            ->mapWithKeys(fn($e) => [$e->employee_id => ($e->basicData?->full_name ?: $e->eci)]);

        $hasActiveFilters = $search !== '' || $positionFilter !== '' || $statusFilter !== ''
            || $supervisorId !== '' || $templateId !== '' || $typeFilter !== '';

        // ── All active employees for bulk assignment checklist modal ─────────
        $allActiveEmployees = Employee::with('basicData')
            ->where('is_active', true)
            ->get()
            ->sortBy(fn($e) => $e->basicData?->full_name ?? '');

        // ── Active supervisors ──────────────────────────────────────────────
        $supervisors = Employee::with('basicData')->where('is_active', true)->get()->sortBy(fn($e) => $e->basicData?->full_name ?? '');

        // ── Employee roles for role-based multi-select ───────────────────────────
        $employeeRoles = \App\Models\EmployeeRole::orderBy('name')->get();

        // ── Delivery Projects for filter dropdown ─────────────────────────────
        $projects = \App\Models\DeliveryProject::select('id', 'name')->orderBy('name')->get();

        // ── Employee Positions for position dropdown ──────────────────────────
        $positions = \App\Models\EmployeeBasicData::whereNotNull('position')
            ->where('position', '!=', '')
            ->distinct()
            ->orderBy('position')
            ->pluck('position');

        return view('hr-general.kpi.dashboard', compact(
            'user',
            'periodMonth',
            'period',
            'totalEmployees',
            'countCreated',
            'countNotCreated',
            'countDraft',
            'countSelfAssessed',
            'countReviewed',
            'countCompleted',
            'countApproved',
            'countRejected',
            'countSubmitted',
            'avgScore',
            'scoreByDept',
            'monthlyTrend',
            'recentEvaluations',
            'activeTemplates',
            'eligibleTemplates',
            'supervisorNames',
            'hasActiveFilters',
            'activeEmployees',
            'allActiveEmployees',
            'supervisors',
            'employeeRoles',
            'scope',
            'filterType',
            'search',
            'statusFilter',
            'projectId',
            'positionFilter',
            'roleId',
            'templateId',
            'supervisorId',
            'typeFilter',
            'perPage',
            'projects',
            'positions',
            'isSupervisorOnly',
            'canCreate',
            'canReview',
            'canApprove'
        ));
    }

    // ── Evaluation List (Redirected to Unified Dashboard) ────────────────────

    /**
     * Evaluation list is now unified inside dashboard. Redirect to main KPI Evaluation index.
     */
    public function evaluationList(Request $request)
    {
        return redirect()->route('general.kpi-evaluation.index', $request->query());
    }

    // ── Create Evaluation ─────────────────────────────────────────────────────

    /**
     * POST: Create a new KPI evaluation record for an employee.
     * HR selects employee, template, period, and assigns a supervisor.
     */
    public function storeEvaluation(Request $request)
    {
        $request->validate([
            'template_id'        => 'required|integer|exists:kpi_templates,id',
            'period_month'       => 'required|string|regex:/^\d{4}-\d{2}$/',
            'supervisor_id'      => 'nullable|integer',
            'self_deadline'      => 'nullable|date',
            'supervisor_deadline'=> 'nullable|date',
        ]);

        $user = session('user');

        // Resolve target employee IDs (single employee_id, array of employee_ids, or array of role_ids)
        $employeeIds = [];

        if ($request->filled('employee_ids') && is_array($request->employee_ids)) {
            $employeeIds = array_filter(array_map('intval', $request->employee_ids));
        } elseif ($request->filled('employee_id')) {
            $employeeIds = [(int) $request->employee_id];
        } elseif ($request->filled('role_ids') && is_array($request->role_ids)) {
            $roleIds = array_filter(array_map('intval', $request->role_ids));
            $employeeIds = Employee::where('is_active', true)
                ->whereHas('roles', fn($q) => $q->whereIn('employee_role.id', $roleIds))
                ->pluck('employee_id')
                ->toArray();
        }

        if (empty($employeeIds)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Please select at least one employee or role.'], 422);
            }
            return redirect()->back()->with('error', 'Please select at least one employee or role.');
        }

        $template = KpiTemplate::with('indicators')->findOrFail($request->template_id);
        $createdCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($employeeIds as $empId) {
                // Duplicate = same employee already holds THIS template for the period.
                // A different template (e.g. Self vs Lead) is a separate assignment
                // and is allowed alongside existing ones.
                $exists = KpiEvaluation::where('employee_id', $empId)
                    ->where('period_month', $request->period_month)
                    ->where('template_id', $template->id)
                    ->exists();

                if ($exists) {
                    $skippedCount++;
                    continue;
                }

                // Default supervisor_id if not explicitly passed
                $supId = $request->supervisor_id;
                if (!$supId) {
                    $empModel = Employee::with('basicData')->find($empId);
                    $supId = $empModel?->basicData?->direct_supervision ?: ($user['id'] ?? 1);
                }

                $evaluation = KpiEvaluation::create([
                    'employee_id'        => $empId,
                    'template_id'        => $template->id,
                    'period_month'       => $request->period_month,
                    'supervisor_id'      => $supId,
                    'status'             => KpiEvaluation::STATUS_DRAFT,
                    'self_deadline'      => $request->self_deadline ?: null,
                    'supervisor_deadline'=> $request->supervisor_deadline ?: null,
                    'created_by'         => $user['id'] ?? null,
                ]);

                foreach ($template->indicators as $indicator) {
                    KpiEvaluationDetail::create([
                        'evaluation_id' => $evaluation->id,
                        'indicator_id'  => $indicator->id,
                    ]);
                }
                $createdCount++;
            }

            DB::commit();

            $msg = "KPI evaluation created for {$createdCount} employee(s).";
            if ($skippedCount > 0) {
                $msg .= " ({$skippedCount} skipped as already existing for this period).";
            }

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => $msg]);
            }
            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to create evaluations: ' . $e->getMessage()], 500);
            }
return redirect()->back()->with('error', 'Failed to create evaluations.');
        }
    }

    /**
     * POST: Update self/supervisor deadline for an existing evaluation.
     * HR can call this to extend missed deadlines without re-assigning.
     */
    public function updateDeadline(Request $request, int $id)
    {
        $request->validate([
            'self_deadline'       => 'nullable|date',
            'supervisor_deadline' => 'nullable|date',
        ]);

        $evaluation = KpiEvaluation::findOrFail($id);

        if ($request->filled('self_deadline')) {
            $evaluation->self_deadline = $request->self_deadline;
        }
        if ($request->filled('supervisor_deadline')) {
            $evaluation->supervisor_deadline = $request->supervisor_deadline;
        }
        $evaluation->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Deadline updated.',
                'self_deadline'       => $evaluation->self_deadline?->format('d M Y'),
                'supervisor_deadline' => $evaluation->supervisor_deadline?->format('d M Y'),
            ]);
        }
        return redirect()->back()->with('success', 'Deadline updated successfully.');
    }

    /**
     * POST: Update evaluation template ID.
     */
    public function updateTemplate(Request $request, int $id)
    {
        $request->validate([
            'template_id' => 'required|integer|exists:kpi_templates,id',
        ]);

        $eval = KpiEvaluation::findOrFail($id);
        $eval->template_id = $request->template_id;
        $eval->save();

        // If draft/rejected and self-assessment not yet submitted, re-sync indicators from new template
        if (in_array($eval->status, [KpiEvaluation::STATUS_DRAFT, KpiEvaluation::STATUS_HR_REJECTED]) && !$eval->hasSelfAssessment()) {
            KpiEvaluationDetail::where('evaluation_id', $eval->id)->delete();
            $template = KpiTemplate::with('indicators')->find($request->template_id);
            if ($template) {
                foreach ($template->indicators as $indicator) {
                    KpiEvaluationDetail::create([
                        'evaluation_id' => $eval->id,
                        'indicator_id'  => $indicator->id,
                    ]);
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'KPI Template updated successfully.',
            ]);
        }
        return redirect()->back()->with('success', 'KPI Template updated successfully.');
    }

    // ── Review / Supervisor Scoring ───────────────────────────────────────────

    /**
     * Show the supervisor scoring form.
     */
    public function reviewEvaluation(int $id)
    {
        $user = session('user');

        $evaluation = KpiEvaluation::with([
            'employee.basicData',
            'supervisor.basicData',
            'template.indicators',
            'template.scoringScales',
            'details.indicator',
        ])->findOrFail($id);

        $canApprove = $this->can('general.kpi-evaluation.approve');

        return view('hr-general.kpi.review', compact(
            'user',
            'evaluation',
            'canApprove'
        ));
    }

    /**
     * POST: Save supervisor scores for an evaluation.
     */
    public function submitReview(Request $request, int $id)
    {
        $user = session('user');
        $userId = (int) ($user['id'] ?? 0);

        $evaluation = KpiEvaluation::with(['details.indicator', 'template'])->findOrFail($id);

        // Security check: employees cannot evaluate themselves as supervisor
        if ($userId === (int) $evaluation->employee_id && empty($user['is_admin'])) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employees cannot evaluate their own KPI as a supervisor.',
                ], 403);
            }
            return redirect()->back()->with('error', 'Employees cannot evaluate their own KPI as a supervisor.');
        }

        // Validate scores array
        $request->validate([
            'scores'          => 'required|array',
            'scores.*.rating' => 'nullable|numeric|min:0|max:10',
            'scores.*.score'  => 'nullable|numeric|min:0|max:100',
            'scores.*.notes'  => 'nullable|string|max:500',
            'general_notes'   => 'nullable|string|max:2000',
        ]);

        $scaleMax = $evaluation->template?->scaleMax() ?: 5;

        DB::beginTransaction();
        try {
            $now = now();

            foreach ($request->scores as $detailId => $data) {
                $detail = $evaluation->details->where('id', (int) $detailId)->first();
                if (!$detail) continue;

                // Paragraph indicators: keep only the note.
                if ($detail->indicator && $detail->indicator->isParagraph()) {
                    $detail->supervisor_notes        = $data['notes'] ?? null;
                    $detail->supervisor_submitted_at = $now;
                    $detail->save();
                    continue;
                }

                $max = $detail->indicator?->effectiveMax() ?: $scaleMax;

                if (isset($data['rating']) && (int) $data['rating'] > 0) {
                    $detail->star_rating      = (int) $data['rating'];
                    $detail->supervisor_score = round($detail->star_rating / $max * 100, 2);
                } elseif (isset($data['score']) && $data['score'] !== '') {
                    $detail->supervisor_score = (float) $data['score'];
                    $detail->star_rating      = min($max, max(1, (int) round($detail->supervisor_score / 100 * $max)));
                } else {
                    $detail->supervisor_score = null;
                }

                if (isset($data['actual'])) {
                    $detail->actual_achievement = $data['actual'];
                }

                $detail->supervisor_notes    = $data['notes'] ?? null;
                $detail->supervisor_submitted_at = $now;
                $detail->save();
                $detail->computeWeightedScore();
            }

            // Mark review timestamp, overall comment, and recalculate overall score
            $evaluation->reviewed_at = $now;
            if ($request->has('general_notes')) {
                $evaluation->general_notes = $request->input('general_notes');
            }
            $evaluation->save();
            $evaluation->recalculateScore();
            $evaluation->refreshStatus();

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success'       => true,
                    'message'       => 'Supervisor review submitted successfully.',
                    'overall_score' => $evaluation->fresh()->overall_score,
                    'status'        => $evaluation->fresh()->status,
                ]);
            }

            return redirect()->route('general.kpi-evaluation.list')
                ->with('success', 'Supervisor review submitted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to submit review.');
        }
    }

    // ── Approve / Reject ──────────────────────────────────────────────────────

    /**
     * POST: HR approves a completed evaluation → visible to employee.
     */
    public function approveEvaluation(Request $request, int $id)
    {
        $evaluation = KpiEvaluation::findOrFail($id);
        $user       = session('user');

        if (!$evaluation->isReadyForApproval()) {
            $need = $evaluation->isSelfType()
                ? 'the employee\'s self-assessment must be submitted first.'
                : 'the lead\'s review must be completed first.';
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Evaluation cannot be approved yet — ' . $need,
                ], 422);
            }
            return redirect()->back()->with('error', 'Evaluation cannot be approved yet — ' . $need);
        }

        $evaluation->status          = KpiEvaluation::STATUS_HR_APPROVED;
        $evaluation->hr_approved_at  = now();
        $evaluation->hr_approved_by  = $user['id'] ?? null;
        $evaluation->hr_notes        = $request->input('hr_notes');
        $evaluation->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Evaluation approved successfully.']);
        }
        return redirect()->back()->with('success', 'Evaluation approved successfully. The employee can now view their results.');
    }

    /**
     * POST: HR rejects an evaluation (sends back for revision).
     */
    public function rejectEvaluation(Request $request, int $id)
    {
        $evaluation = KpiEvaluation::findOrFail($id);
        $user       = session('user');

        $evaluation->status    = KpiEvaluation::STATUS_HR_REJECTED;
        $evaluation->hr_notes  = $request->input('hr_notes', '');
        $evaluation->save();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Evaluation rejected.']);
        }
        return redirect()->back()->with('success', 'Evaluation rejected. HR notes have been saved.');
    }

    /**
     * POST: Delete an evaluation (HR only, draft/rejected status).
     */
    public function deleteEvaluation(Request $request, int $id)
    {
        $evaluation = KpiEvaluation::findOrFail($id);

        if (!in_array($evaluation->status, [KpiEvaluation::STATUS_DRAFT, KpiEvaluation::STATUS_HR_REJECTED])) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Only draft or rejected evaluations can be deleted.'], 422);
            }
            return redirect()->back()->with('error', 'Only draft or rejected evaluations can be deleted.');
        }

        $evaluation->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Evaluation deleted.']);
        }
        return redirect()->back()->with('success', 'Evaluation deleted.');
    }

    // ── AJAX: Dashboard Data ──────────────────────────────────────────────────

    /**
     * Returns chart data for the HR dashboard (JSON).
     */
    public function getDashboardData(Request $request)
    {
        $periodMonth = $request->query('period', Carbon::now()->format('Y-m'));
        $view        = $request->query('view', 'monthly'); // weekly | monthly | annual

        return response()->json([
            'scoreByDept'  => $this->getScoreByDepartment($periodMonth),
            'trend'        => $this->getTrendData($view),
            'statusCounts' => $this->getStatusCounts($periodMonth),
        ]);
    }

    /**
     * POST: force the coverage table back in step with template targeting for a
     * period (creates missing draft evaluations, drops pristine orphans).
     */
    public function syncAssignments(Request $request)
    {
        $period = $request->input('period', Carbon::now()->format('Y-m'));
        try {
            Carbon::createFromFormat('Y-m', $period);
        } catch (\Exception $e) {
            $period = Carbon::now()->format('Y-m');
        }

        $res = KpiAssignments::syncPeriod($period, session('user')['id'] ?? null, true);

        return response()->json([
            'success' => true,
            'message' => "Assignments synced — {$res['created']} added, {$res['removed']} removed.",
            'created' => $res['created'],
            'removed' => $res['removed'],
        ]);
    }

    // ── Team & Leads ─────────────────────────────────────────────────────────

    /**
     * "Team & Leads" tab — HR/admin set which leader each employee reports to.
     * The leader is stored on employee_basic_data.direct_supervision, which the
     * rest of the KPI flow reads (lead-assessment filling, "My Team", the
     * supervisor auto-fill on assignment).
     */
    public function teams(Request $request)
    {
        $user           = session('user');
        $search         = trim((string) $request->query('search', ''));
        $positionFilter = $request->query('position', '');
        $leadFilter     = $request->query('lead', '');
        $teamFilter     = $request->query('team', '');
        $noLeadOnly     = $leadFilter === 'none';

        $perPage = (int) $request->query('per_page', 15);
        if (!in_array($perPage, [10, 15, 25, 50])) {
            $perPage = 15;
        }

        $q = Employee::with(['basicData.kpiTeam.lead.basicData', 'deliveryProjects:id,name,project_manager_id,delivery_manager_id,delivery_owner_id'])
            ->where('is_active', true);

        if ($search !== '') {
            $q->where(function ($w) use ($search) {
                $w->where('eci', 'like', "%{$search}%")
                  ->orWhereHas('basicData', function ($b) use ($search) {
                      $b->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('nick_name', 'like', "%{$search}%");
                  });
            });
        }
        if ($positionFilter) {
            $q->whereHas('basicData', fn($b) => $b->where('position', $positionFilter));
        }
        if ($noLeadOnly) {
            $q->whereHas('basicData', fn($b) => $b->whereNull('direct_supervision')->orWhere('direct_supervision', ''));
        } elseif ($leadFilter) {
            $q->whereHas('basicData', fn($b) => $b->where('direct_supervision', $leadFilter));
        }
        if ($teamFilter === 'none') {
            $q->whereHas('basicData', fn($b) => $b->whereNull('kpi_team_id'));
        } elseif ($teamFilter !== '') {
            $q->whereHas('basicData', fn($b) => $b->where('kpi_team_id', $teamFilter));
        }

        $employees = $q->leftJoin('employee_basic_data as bd', 'employee.employee_id', '=', 'bd.employee_id')
            ->select('employee.*')
            ->orderBy('bd.first_name')->orderBy('bd.last_name')
            ->paginate($perPage)->withQueryString();

        // Resolve the leader names shown on this page.
        $leadIds = collect($employees->items())
            ->map(fn($e) => $e->basicData?->direct_supervision)
            ->filter()->unique()->values();
        $leadMap = Employee::with('basicData')->whereIn('employee_id', $leadIds)->get()->keyBy('employee_id');

        // Direct-report counts across the whole org (cheap grouped count).
        $reportCounts = EmployeeBasicData::whereNotNull('direct_supervision')
            ->where('direct_supervision', '!=', '')
            ->selectRaw('direct_supervision, COUNT(*) as c')
            ->groupBy('direct_supervision')
            ->pluck('c', 'direct_supervision');

        // Options for every leader picker + the header "Lead" filter.
        $leadOptions = Employee::with('basicData')->where('is_active', true)->get()
            ->map(fn($e) => [
                'id'       => (string) $e->employee_id,
                'name'     => $e->basicData?->full_name ?: $e->eci,
                'meta'     => trim(($e->eci ?? '') . ' · ' . ($e->basicData?->position ?? ''), ' ·'),
                'is_lead'  => (int) ($reportCounts[$e->employee_id] ?? 0) > 0,
            ])
            ->sortBy('name')->values();

        // KPI teams (with resolved lead names) + a project → lead map for the
        // "use project lead" shortcut.
        $teams = KpiTeam::with('lead.basicData')->withCount('memberBasicData')->orderBy('name')->get();

        $projIds = collect($employees->items())->flatMap(fn($e) => $e->deliveryProjects->pluck('id'))->unique()->values();
        $projRows = \App\Models\DeliveryProject::whereIn('id', $projIds)
            ->get(['id', 'name', 'project_manager_id', 'delivery_manager_id', 'delivery_owner_id']);
        $pmIds = $projRows->flatMap(fn($p) => [$p->project_manager_id, $p->delivery_manager_id, $p->delivery_owner_id])->filter()->unique();
        $pmNames = Employee::with('basicData')->whereIn('employee_id', $pmIds)->get()
            ->mapWithKeys(fn($e) => [$e->employee_id => ($e->basicData?->full_name ?: $e->eci)]);
        $projectLeadMap = $projRows->mapWithKeys(function ($p) use ($pmNames) {
            $lid = $p->project_manager_id ?: ($p->delivery_manager_id ?: $p->delivery_owner_id);
            return [(string) $p->id => [
                'name'      => $p->name,
                'lead_id'   => $lid ? (string) $lid : null,
                'lead_name' => $lid ? ($pmNames[$lid] ?? ('#' . $lid)) : null,
            ]];
        });

        $positions = EmployeeBasicData::whereNotNull('position')->where('position', '!=', '')
            ->distinct()->orderBy('position')->pluck('position');

        $totalEmployees = Employee::where('is_active', true)->count();
        $withLead = Employee::where('is_active', true)
            ->whereHas('basicData', fn($b) => $b->whereNotNull('direct_supervision')->where('direct_supervision', '!=', ''))
            ->count();

        $stats = [
            'total'    => $totalEmployees,
            'withLead' => $withLead,
            'noLead'   => max(0, $totalEmployees - $withLead),
            'teams'    => $teams->count(),
        ];

        $canEdit = $this->can('general.kpi-evaluation.create');

        $viewData = compact(
            'user', 'employees', 'leadMap', 'reportCounts', 'leadOptions',
            'teams', 'projectLeadMap', 'positions', 'stats',
            'search', 'positionFilter', 'leadFilter', 'teamFilter',
            'perPage', 'canEdit'
        );

        // Realtime filtering: return just the rows + pager for XHR replacement.
        if ($request->boolean('partial')) {
            return response()->json([
                'rows'  => view('hr-general.kpi.partials._teams-rows', $viewData)->render(),
                'pager' => view('hr-general.kpi.partials._teams-pager', $viewData)->render(),
                'total' => $employees->total(),
            ]);
        }

        return view('hr-general.kpi.teams', $viewData);
    }

    /**
     * Re-point the open (un-reviewed) current lead-assessment rows for a set of
     * employees at a new leader, so a re-org never leaves a stale evaluator.
     */
    private function syncOpenLeadEvals(array $employeeIds, $leadId): int
    {
        if (empty($employeeIds)) {
            return 0;
        }
        return KpiEvaluation::whereIn('employee_id', $employeeIds)
            ->whereNull('reviewed_at')
            ->whereHas('template', fn($t) => $t->whereIn('target_type', ['supervisor', 'peer']))
            ->update(['supervisor_id' => $leadId]);
    }

    /**
     * POST: set (or clear) an employee's leader manually.
     */
    public function updateLead(Request $request, int $employeeId)
    {
        $request->validate([
            'lead_id'  => 'nullable|integer',
            'sync_kpi' => 'nullable|boolean',
        ]);

        $bd = EmployeeBasicData::where('employee_id', $employeeId)->first();
        if (!$bd) {
            return response()->json(['success' => false, 'message' => 'Employee basic data not found.'], 404);
        }

        $leadId = $request->input('lead_id') ?: null;

        if ($leadId && (int) $leadId === $employeeId) {
            return response()->json(['success' => false, 'message' => 'An employee cannot be their own leader.'], 422);
        }
        if ($leadId && !Employee::where('employee_id', $leadId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Selected leader was not found.'], 422);
        }

        $bd->direct_supervision = $leadId;
        $bd->lead_source        = $leadId ? 'manual' : null;
        $bd->save();

        $synced = $request->boolean('sync_kpi') ? $this->syncOpenLeadEvals([$employeeId], $leadId) : 0;

        $leadName = $leadId
            ? (Employee::with('basicData')->find($leadId)?->basicData?->full_name ?? ('#' . $leadId))
            : null;

        return response()->json([
            'success'   => true,
            'message'   => $leadId
                ? "Leader set to {$leadName}." . ($synced ? " {$synced} open KPI evaluation(s) updated." : '')
                : 'Leader cleared.',
            'lead_id'   => $leadId,
            'lead_name' => $leadName,
            'source'    => $leadId ? 'manual' : null,
            'reports'   => EmployeeBasicData::where('direct_supervision', $employeeId)->count(),
        ]);
    }

    /**
     * POST: put an employee on a KPI team. If the team has a lead, that becomes
     * the employee's leader (lead_source = 'team'). Clearing the team leaves the
     * current leader in place but drops the 'team' source.
     */
    public function updateTeam(Request $request, int $employeeId)
    {
        $request->validate(['team_id' => 'nullable|integer']);

        $bd = EmployeeBasicData::where('employee_id', $employeeId)->first();
        if (!$bd) {
            return response()->json(['success' => false, 'message' => 'Employee basic data not found.'], 404);
        }

        $teamId = $request->input('team_id') ?: null;
        $team   = $teamId ? KpiTeam::find($teamId) : null;
        if ($teamId && !$team) {
            return response()->json(['success' => false, 'message' => 'Team not found.'], 422);
        }

        $bd->kpi_team_id = $teamId;

        $leadName = null;
        $synced   = 0;
        if ($team && $team->lead_employee_id && (int) $team->lead_employee_id !== $employeeId) {
            $bd->direct_supervision = $team->lead_employee_id;
            $bd->lead_source        = 'team';
            $leadName = $team->lead?->basicData?->full_name ?? ('#' . $team->lead_employee_id);
            $synced   = $this->syncOpenLeadEvals([$employeeId], $team->lead_employee_id);
        } elseif (!$teamId && $bd->lead_source === 'team') {
            $bd->lead_source = 'manual';
        }
        $bd->save();

        return response()->json([
            'success'   => true,
            'message'   => $team
                ? "Assigned to “{$team->name}”." . ($leadName ? " Leader → {$leadName}." : ' (team has no lead yet)')
                . ($synced ? " {$synced} open KPI evaluation(s) updated." : '')
                : 'Removed from team.',
            'team_id'   => $teamId,
            'team_name' => $team?->name,
            'lead_id'   => $team?->lead_employee_id ? (string) $team->lead_employee_id : ($bd->direct_supervision ? (string) $bd->direct_supervision : null),
            'lead_name' => $leadName,
            'source'    => $bd->lead_source,
        ]);
    }

    /**
     * POST: copy a delivery project's manager onto the employee as their leader
     * (lead_source = 'project'). Does not change project membership.
     */
    public function applyProjectLead(Request $request, int $employeeId)
    {
        $request->validate(['project_id' => 'required|integer']);

        $bd = EmployeeBasicData::where('employee_id', $employeeId)->first();
        if (!$bd) {
            return response()->json(['success' => false, 'message' => 'Employee basic data not found.'], 404);
        }

        $project = \App\Models\DeliveryProject::find($request->input('project_id'));
        if (!$project) {
            return response()->json(['success' => false, 'message' => 'Project not found.'], 422);
        }

        $leadId = $project->project_manager_id ?: ($project->delivery_manager_id ?: $project->delivery_owner_id);
        if (!$leadId) {
            return response()->json(['success' => false, 'message' => "“{$project->name}” has no project manager set."], 422);
        }
        if ((int) $leadId === $employeeId) {
            return response()->json(['success' => false, 'message' => 'That project is managed by this employee.'], 422);
        }

        $bd->direct_supervision = $leadId;
        $bd->lead_source        = 'project';
        $bd->save();

        $synced   = $this->syncOpenLeadEvals([$employeeId], $leadId);
        $leadName = Employee::with('basicData')->find($leadId)?->basicData?->full_name ?? ('#' . $leadId);

        return response()->json([
            'success'   => true,
            'message'   => "Leader → {$leadName} (from “{$project->name}”)." . ($synced ? " {$synced} open KPI evaluation(s) updated." : ''),
            'lead_id'   => (string) $leadId,
            'lead_name' => $leadName,
            'source'    => 'project',
        ]);
    }

    /**
     * POST: create or update a KPI team definition. Changing a team's lead
     * re-points every member whose leader currently comes from the team.
     */
    public function saveTeam(Request $request)
    {
        $request->validate([
            'id'               => 'nullable|integer|exists:kpi_teams,id',
            'name'             => 'required|string|max:150',
            'lead_employee_id' => 'nullable|integer',
        ]);

        $leadId = $request->input('lead_employee_id') ?: null;
        if ($leadId && !Employee::where('employee_id', $leadId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Selected lead was not found.'], 422);
        }

        $team = $request->filled('id') ? KpiTeam::find($request->input('id')) : new KpiTeam();
        $leadChanged = $team->exists && (int) $team->lead_employee_id !== (int) $leadId;

        $team->name             = $request->input('name');
        $team->lead_employee_id = $leadId;
        $team->is_active        = true;
        $team->save();

        $repointed = 0;
        if ($leadChanged || !$team->wasRecentlyCreated) {
            $memberIds = EmployeeBasicData::where('kpi_team_id', $team->id)
                ->where('lead_source', 'team')
                ->pluck('employee_id');
            if ($memberIds->isNotEmpty()) {
                EmployeeBasicData::whereIn('employee_id', $memberIds)->update(['direct_supervision' => $leadId]);
                $repointed = $this->syncOpenLeadEvals($memberIds->all(), $leadId);
            }
        }

        $team->load('lead.basicData')->loadCount('memberBasicData');

        return response()->json([
            'success'   => true,
            'message'   => ($request->filled('id') ? 'Team updated.' : 'Team created.')
                . ($repointed ? " {$repointed} evaluation(s) re-pointed." : ''),
            'team'      => [
                'id'         => $team->id,
                'name'       => $team->name,
                'lead_id'    => $team->lead_employee_id ? (string) $team->lead_employee_id : '',
                'lead_name'  => $team->lead?->basicData?->full_name ?? ($team->lead_employee_id ? ('#' . $team->lead_employee_id) : null),
                'members'    => $team->member_basic_data_count,
            ],
        ]);
    }

    /**
     * POST: delete a KPI team. Members are detached (kpi_team_id nulled) but keep
     * whatever leader they already have.
     */
    public function deleteTeam(Request $request, int $id)
    {
        $team = KpiTeam::find($id);
        if (!$team) {
            return response()->json(['success' => false, 'message' => 'Team not found.'], 404);
        }

        EmployeeBasicData::where('kpi_team_id', $team->id)->update([
            'kpi_team_id' => null,
            'lead_source' => DB::raw("CASE WHEN lead_source = 'team' THEN 'manual' ELSE lead_source END"),
        ]);
        $team->delete();

        return response()->json(['success' => true, 'message' => 'Team deleted.']);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    /**
     * Export evaluations as CSV (simple approach without Maatwebsite/Excel dependency).
     */
    public function exportEvaluations(Request $request)
    {
        $periodMonth = $request->query('period', Carbon::now()->format('Y-m'));

        $evaluations = KpiEvaluation::with(['employee.basicData', 'supervisor.basicData', 'template'])
            ->where('period_month', $periodMonth)
            ->get();

        $filename = "kpi-evaluation-{$periodMonth}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($evaluations) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'No', 'Employee ID', 'Employee Name', 'Department', 'Position',
                'Template', 'Period', 'Supervisor', 'Status', 'Overall Score',
                'Self-Assessed At', 'Reviewed At', 'Approved At',
            ]);

            foreach ($evaluations as $i => $eval) {
                $bd = $eval->employee?->basicData;
                $supBd = $eval->supervisor?->basicData;

                fputcsv($handle, [
                    $i + 1,
                    $eval->employee?->eci ?? '',
                    $bd?->full_name ?? '-',
                    $bd?->department ?? '-',
                    $bd?->position ?? '-',
                    $eval->template?->name ?? '-',
                    $eval->period_month,
                    $supBd?->full_name ?? '-',
                    $eval->status_label,
                    $eval->overall_score ?? '-',
                    $eval->self_assessed_at?->format('Y-m-d H:i') ?? '-',
                    $eval->reviewed_at?->format('Y-m-d H:i') ?? '-',
                    $eval->hr_approved_at?->format('Y-m-d H:i') ?? '-',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Private Helpers ───────────────────────────────────────────────────────

    private function getScoreByDepartment(string $periodMonth): array
    {
        $evals = KpiEvaluation::with(['employee.basicData', 'details'])
            ->where('period_month', $periodMonth)
            ->get();

        $groupData = [];
        foreach ($evals as $eval) {
            $bd   = $eval->employee?->basicData;
            $name = $bd?->position ?: ($bd?->department ?: 'Unassigned');

            $score = $eval->overall_score;
            if ($score === null && $eval->details->isNotEmpty()) {
                $score = $eval->details->whereNotNull('supervisor_score')->avg('supervisor_score')
                    ?? $eval->details->whereNotNull('self_achievement')->avg('self_achievement');
            }

            if ($score !== null) {
                if (!isset($groupData[$name])) {
                    $groupData[$name] = ['sum' => 0, 'count' => 0];
                }
                $groupData[$name]['sum'] += (float) $score;
                $groupData[$name]['count'] += 1;
            }
        }

        $result = [];
        foreach ($groupData as $name => $d) {
            $result[] = [
                'position'   => $name,
                'department' => $name,
                'avg_score'  => round($d['sum'] / $d['count'], 1),
                'count'      => $d['count'],
            ];
        }

        usort($result, fn($a, $b) => $b['avg_score'] <=> $a['avg_score']);
        return $result;
    }

    private function getMonthlyTrend(int $months = 6): array
    {
        $trend = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $period = Carbon::now()->subMonths($i)->format('Y-m');
            $evals  = KpiEvaluation::with('details')
                ->where('period_month', $period)
                ->get();

            $scores = [];
            foreach ($evals as $e) {
                $sc = $e->overall_score;
                if ($sc === null && $e->details->isNotEmpty()) {
                    $sc = $e->details->whereNotNull('supervisor_score')->avg('supervisor_score')
                        ?? $e->details->whereNotNull('self_achievement')->avg('self_achievement');
                }
                if ($sc !== null) {
                    $scores[] = (float) $sc;
                }
            }

            $avg = count($scores) > 0 ? (array_sum($scores) / count($scores)) : null;

            $trend[] = [
                'period'    => $period,
                'label'     => Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                'avg_score' => $avg ? round((float) $avg, 1) : null,
            ];
        }
        return $trend;
    }

    private function getStatusCounts(string $periodMonth): array
    {
        return KpiEvaluation::where('period_month', $periodMonth)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
    }

    private function getTrendData(string $view): array
    {
        if ($view === 'annual') {
            // Last 5 years
            $data = [];
            for ($y = 4; $y >= 0; $y--) {
                $year  = Carbon::now()->subYears($y)->year;
                $evals = KpiEvaluation::with('details')
                    ->where('period_month', 'like', "{$year}-%")
                    ->get();

                $scores = [];
                foreach ($evals as $e) {
                    $sc = $e->overall_score;
                    if ($sc === null && $e->details->isNotEmpty()) {
                        $sc = $e->details->whereNotNull('supervisor_score')->avg('supervisor_score')
                            ?? $e->details->whereNotNull('self_achievement')->avg('self_achievement');
                    }
                    if ($sc !== null) {
                        $scores[] = (float) $sc;
                    }
                }

                $avg = count($scores) > 0 ? (array_sum($scores) / count($scores)) : null;
                $data[] = ['label' => (string) $year, 'avg_score' => $avg ? round((float) $avg, 1) : null];
            }
            return $data;
        }

        // Default: last 6 months
        return $this->getMonthlyTrend(6);
    }

    private function can(string $slug): bool
    {
        $shared = \Illuminate\Support\Facades\View::getShared();
        $slugs  = $shared['permSlugs'] ?? [];
        return in_array($slug, $slugs);
    }
}
