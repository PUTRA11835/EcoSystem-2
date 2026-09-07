<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * My KPI Controller (ESS)
 *
 * Employee self-service KPI experience:
 *   - View own KPI progress + approved evaluation history
 *   - Submit self-assessment for open evaluations
 *
 * Identity always comes from session('user') — employees cannot view
 * or edit other employees' KPI data through this controller.
 *
 * Self-assessment is mandatory but order-independent with supervisor scoring.
 */
class MyKpiController extends Controller
{
    /**
     * "My KPI" — Employee's KPI dashboard.
     *
     * Shows:
     *   - Current period summary card (score if approved, or pending status)
     *   - Evaluations pending self-assessment (CTA buttons)
     *   - History of all evaluations (any status, paginated)
     */
    public function index(Request $request)
    {
        $user       = session('user');
        $employeeId = $user['id'] ?? null;

        if (!$employeeId) {
            abort(403, 'Employee not found in session.');
        }

        $currentPeriod = Carbon::now()->format('Y-m');

        // Keep this employee's evaluations in step with template targeting so the
        // tabs below never lag behind what HR configured on the templates.
        \App\Support\KpiAssignments::syncEmployee((int) $employeeId, $currentPeriod, (int) $employeeId);

        // All evaluations for this employee, newest first
        $evaluations = KpiEvaluation::with(['template.indicators', 'template.scoringScales', 'supervisor.basicData', 'details.indicator'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('period_month')
            ->get();

        $selectedPeriod = $request->query('period', $currentPeriod);

        // Current period evaluation (if any)
        $currentEval = $evaluations->firstWhere('period_month', $currentPeriod);

        // Selected period evaluation for the detail card switcher
        $selectedEval = $evaluations->firstWhere('period_month', $selectedPeriod) ?: $currentEval;

        // Split by assessment kind. Self and Lead assessments are now separate
        // evaluation rows on separate templates (different indicators).
        $selfEvals = $evaluations->filter(fn($e) => $e->isSelfType())->values();
        $leadEvals = $evaluations->filter(fn($e) => $e->isLeadType())->values();

        // Self-assessments still awaiting the employee's input
        $pendingSelfAssessment = $selfEvals->filter(fn($e) =>
            !$e->hasSelfAssessment() &&
            !in_array($e->status, [KpiEvaluation::STATUS_HR_APPROVED])
        )->values();

        // Approved evaluations visible to employee
        $approvedEvaluations = $evaluations->where('status', KpiEvaluation::STATUS_HR_APPROVED);

        // Average approved score (all time)
        $avgScore = $approvedEvaluations->avg('overall_score');

        // Score trend (last 6 months)
        $scoreTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $period = Carbon::now()->subMonths($i)->format('Y-m');
            $eval   = $evaluations->firstWhere('period_month', $period);
            $scoreTrend[] = [
                'period'    => $period,
                'label'     => Carbon::createFromFormat('Y-m', $period)->format('M Y'),
                'score'     => ($eval && $eval->status === KpiEvaluation::STATUS_HR_APPROVED)
                                ? $eval->overall_score
                                : null,
                'status'    => $eval?->status ?? 'none',
            ];
        }

        $targetPeriod = $selectedPeriod ?: $currentPeriod;

        // Check if user is a supervisor and load direct team members & evaluations (strictly other employees, never self)
        $subordinates = \App\Models\Employee::with([
            'basicData',
            'kpiEvaluations' => fn($q) => $q->where('period_month', $targetPeriod)->with(['template', 'details'])
        ])
        ->where('is_active', true)
        ->where('employee.employee_id', '!=', $employeeId)
        ->whereHas('basicData', fn($b) => $b->where('direct_supervision', $employeeId))
        ->get();

        $assignedEvaluations = KpiEvaluation::with(['employee.basicData', 'template', 'details'])
            ->where('supervisor_id', $employeeId)
            ->where('employee_id', '!=', $employeeId)
            ->where('period_month', $targetPeriod)
            ->get();

        $hasAnySubordinate = \App\Models\EmployeeBasicData::where('direct_supervision', $employeeId)->where('employee_id', '!=', $employeeId)->exists();
        $hasAnySupervisedEval = KpiEvaluation::where('supervisor_id', $employeeId)->where('employee_id', '!=', $employeeId)->exists();

        $isSupervisor = $subordinates->isNotEmpty() || $assignedEvaluations->isNotEmpty() || $hasAnySubordinate || $hasAnySupervisedEval;
        $activeTemplates = $isSupervisor ? \App\Models\KpiTemplate::where('is_active', true)->get() : collect([]);

        return view('hr-general.kpi.my-kpi', compact(
            'user',
            'evaluations',
            'selfEvals',
            'leadEvals',
            'currentEval',
            'currentPeriod',
            'selectedPeriod',
            'selectedEval',
            'pendingSelfAssessment',
            'approvedEvaluations',
            'avgScore',
            'scoreTrend',
            'subordinates',
            'assignedEvaluations',
            'isSupervisor',
            'activeTemplates'
        ));
    }

    /**
     * Show the self-assessment form for a specific evaluation.
     */
    public function selfAssessmentForm(int $id)
    {
        $user       = session('user');
        $employeeId = $user['id'] ?? null;

        $evaluation = KpiEvaluation::with([
            'template',
            'template.indicators',
            'template.scoringScales',
            'supervisor.basicData',
            'details.indicator',
        ])
        ->where('employee_id', $employeeId) // ownership check
        ->findOrFail($id);

        // Only self-type rows are fillable by the employee. Lead-assessment rows
        // are scored by the manager and are read-only here.
        if (!$evaluation->isSelfType()) {
            return redirect()->route('general.my-kpi.index')
                ->with('error', 'This is a lead assessment and cannot be filled as a self-assessment.');
        }

        // The form is view-only once the employee has submitted it, or once HR
        // has approved. The employee can still open it to review their answers.
        $locked = $evaluation->hasSelfAssessment()
            || $evaluation->status === KpiEvaluation::STATUS_HR_APPROVED;

        return view('hr-general.kpi.self-assessment', compact(
            'user',
            'evaluation',
            'locked'
        ));
    }

    /**
     * POST: Submit or update self-assessment for an evaluation.
     * Self-assessment can be submitted/updated before or after supervisor scoring.
     * Once HR approves, changes are no longer allowed.
     */
    public function submitSelfAssessment(Request $request, int $id)
    {
        $user       = session('user');
        $employeeId = $user['id'] ?? null;

        $evaluation = KpiEvaluation::with(['details.indicator', 'template'])
            ->where('employee_id', $employeeId)
            ->findOrFail($id);

        if (!$evaluation->isSelfType()) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'This row is a lead assessment, not a self-assessment.'], 422);
            }
            return redirect()->route('general.my-kpi.index')->with('error', 'This row is a lead assessment.');
        }

        // Self-assessment is final: it locks the moment it is submitted, and
        // again once HR approves. No further edits from the employee.
        if ($evaluation->hasSelfAssessment() || $evaluation->status === KpiEvaluation::STATUS_HR_APPROVED) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Self-assessment already submitted and locked.'], 422);
            }
            return redirect()->route('general.my-kpi.index')
                ->with('error', 'Self-assessment has already been submitted and is locked.');
        }

        $request->validate([
            'achievements' => 'required|array',
        ]);

        $scaleMax = $evaluation->template?->scaleMax() ?: 5;

        DB::beginTransaction();
        try {
            $now = now();

            foreach ($request->achievements as $detailId => $data) {
                $detail = $evaluation->details->where('id', (int) $detailId)->first();
                if (!$detail) continue;

                // Paragraph indicators only carry a text answer — no rating / score.
                if ($detail->indicator && $detail->indicator->isParagraph()) {
                    $detail->self_notes        = $data['notes'] ?? null;
                    $detail->self_submitted_at = $now;
                    $detail->save();
                    continue;
                }

                $max = $detail->indicator?->effectiveMax() ?: $scaleMax;

                if (isset($data['rating']) && (int)$data['rating'] > 0) {
                    $detail->star_rating = (int) $data['rating'];
                    $detail->self_achievement = round($detail->star_rating / $max * 100, 2);
                } elseif (isset($data['achievement']) && $data['achievement'] !== '') {
                    $detail->self_achievement = (float) $data['achievement'];
                    $detail->star_rating = min($max, max(1, (int) round($detail->self_achievement / 100 * $max)));
                }

                if (isset($data['actual'])) {
                    $detail->actual_achievement = $data['actual'];
                }

                $detail->self_notes        = $data['notes'] ?? null;
                $detail->self_submitted_at = $now;
                $detail->save();
            }

            // Mark self-assessment timestamp on evaluation
            $evaluation->self_assessed_at = $now;
            $evaluation->save();
            $evaluation->recalculateScore(); // self rows score off self_achievement
            $evaluation->refreshStatus();

            DB::commit();

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Self-assessment submitted successfully.',
                    'status'  => $evaluation->fresh()->status,
                ]);
            }

            return redirect()->route('general.my-kpi.index')
                ->with('success', 'Self-assessment submitted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to submit self-assessment.');
        }
    }
}
