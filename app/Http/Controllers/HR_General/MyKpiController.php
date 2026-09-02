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

        // All evaluations for this employee, newest first
        $evaluations = KpiEvaluation::with(['template.indicators', 'supervisor.basicData', 'details.indicator'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('period_month')
            ->get();

        $selectedPeriod = $request->query('period', $currentPeriod);

        // Current period evaluation (if any)
        $currentEval = $evaluations->firstWhere('period_month', $currentPeriod);

        // Selected period evaluation for the detail card switcher
        $selectedEval = $evaluations->firstWhere('period_month', $selectedPeriod) ?: $currentEval;

        // Evaluations awaiting self-assessment
        $pendingSelfAssessment = $evaluations->filter(fn($e) =>
            !$e->hasSelfAssessment() &&
            !in_array($e->status, [KpiEvaluation::STATUS_HR_APPROVED])
        );

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
            'supervisor.basicData',
            'details.indicator',
        ])
        ->where('employee_id', $employeeId) // ownership check
        ->findOrFail($id);

        // Cannot re-submit self-assessment if already approved by HR
        if ($evaluation->status === KpiEvaluation::STATUS_HR_APPROVED) {
            return redirect()->route('general.my-kpi.index')
                ->with('info', 'This evaluation has already been approved. Self-assessment cannot be modified.');
        }

        return view('hr-general.kpi.self-assessment', compact(
            'user',
            'evaluation'
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

        $evaluation = KpiEvaluation::with('details.indicator')
            ->where('employee_id', $employeeId)
            ->findOrFail($id);

        // Block changes after HR approval
        if ($evaluation->status === KpiEvaluation::STATUS_HR_APPROVED) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Evaluation already approved by HR.'], 422);
            }
            return redirect()->route('general.my-kpi.index')
                ->with('error', 'Evaluation already approved. Self-assessment cannot be modified.');
        }

        $request->validate([
            'achievements' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $now = now();

            foreach ($request->achievements as $detailId => $data) {
                $detail = $evaluation->details->where('id', (int) $detailId)->first();
                if (!$detail) continue;

                if (isset($data['rating']) && (int)$data['rating'] > 0) {
                    $detail->star_rating = (int) $data['rating'];
                    $detail->self_achievement = $detail->star_rating * 20;
                } elseif (isset($data['achievement']) && $data['achievement'] !== '') {
                    $detail->self_achievement = (float) $data['achievement'];
                    $detail->star_rating = min(5, max(1, (int) round($detail->self_achievement / 20)));
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
