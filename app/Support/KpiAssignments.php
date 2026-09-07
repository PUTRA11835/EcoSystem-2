<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\KpiEvaluation;
use App\Models\KpiEvaluationDetail;
use App\Models\KpiTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Keeps KPI evaluations in step with template targeting.
 *
 * "Who is this template for?" on the template form is the single control HR
 * touches. This makes the evaluation rows for a period mirror it:
 *   - a draft evaluation is created for every (employee, template) the template
 *     applies to;
 *   - a still-pristine auto-draft is removed when the pair no longer matches.
 *
 * Touched evaluations (self-assessed, reviewed, scored, or HR-decided) are never
 * deleted.
 */
class KpiAssignments
{
    /**
     * @return array{created:int, removed:int}
     */
    public static function syncPeriod(string $periodMonth, ?int $actorId = null, bool $prune = true): array
    {
        $templates = KpiTemplate::where('is_active', true)->with('indicators')->get();

        if ($templates->isEmpty() && !$prune) {
            return ['created' => 0, 'removed' => 0];
        }

        $employees = Employee::with(['basicData', 'roles', 'deliveryProjects'])
            ->where('is_active', true)
            ->get();

        // Desired set: "empId|tplId" => [empId, tplId, supervisorId]
        $desired       = [];
        $tplIndicators = [];
        foreach ($templates as $tpl) {
            $tplIndicators[$tpl->id] = $tpl->indicators->pluck('id')->all();
            foreach ($employees as $emp) {
                if ($tpl->appliesTo($emp)) {
                    $desired[$emp->employee_id . '|' . $tpl->id] = [
                        $emp->employee_id,
                        $tpl->id,
                        $emp->basicData?->direct_supervision ?: null,
                    ];
                }
            }
        }

        $existing = KpiEvaluation::where('period_month', $periodMonth)
            ->get(['id', 'employee_id', 'template_id', 'status', 'self_assessed_at', 'reviewed_at', 'overall_score']);
        $existingKeys = $existing->mapWithKeys(fn ($e) => [$e->employee_id . '|' . $e->template_id => $e])->all();

        $created = 0;
        $removed = 0;

        DB::transaction(function () use ($desired, $existingKeys, $periodMonth, $actorId, $tplIndicators, $prune, &$created, &$removed) {
            // ── create the missing ones ──────────────────────────────────────
            foreach ($desired as $key => [$empId, $tplId, $supId]) {
                if (isset($existingKeys[$key])) {
                    continue;
                }
                $eval = KpiEvaluation::create([
                    'employee_id'   => $empId,
                    'template_id'   => $tplId,
                    'period_month'  => $periodMonth,
                    'supervisor_id' => $supId,
                    'status'        => KpiEvaluation::STATUS_DRAFT,
                    'created_by'    => $actorId,
                ]);

                $rows = array_map(fn ($iid) => [
                    'evaluation_id' => $eval->id,
                    'indicator_id'  => $iid,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ], $tplIndicators[$tplId] ?? []);
                if ($rows) {
                    KpiEvaluationDetail::insert($rows);
                }
                $created++;
            }

            // ── drop pristine auto-drafts that no longer match ───────────────
            if ($prune) {
                foreach ($existingKeys as $key => $e) {
                    if (isset($desired[$key])) {
                        continue;
                    }
                    $untouched = $e->status === KpiEvaluation::STATUS_DRAFT
                        && is_null($e->self_assessed_at)
                        && is_null($e->reviewed_at)
                        && is_null($e->overall_score);
                    if ($untouched) {
                        KpiEvaluation::where('id', $e->id)->delete(); // details cascade (FK)
                        $removed++;
                    }
                }
            }
        });

        return ['created' => $created, 'removed' => $removed];
    }

    /**
     * Lightweight, create-only sync for a single employee — so "My KPI" never
     * lags behind template targeting even before HR opens the dashboard.
     */
    public static function syncEmployee(int $employeeId, string $periodMonth, ?int $actorId = null): int
    {
        $emp = Employee::with(['basicData', 'roles', 'deliveryProjects'])->find($employeeId);
        if (!$emp) {
            return 0;
        }

        $templates = KpiTemplate::where('is_active', true)->with('indicators')->get();
        if ($templates->isEmpty()) {
            return 0;
        }

        $haveTemplateIds = KpiEvaluation::where('employee_id', $employeeId)
            ->where('period_month', $periodMonth)
            ->pluck('template_id')->map(fn ($v) => (int) $v)->all();

        $created = 0;
        foreach ($templates as $tpl) {
            if (in_array((int) $tpl->id, $haveTemplateIds, true) || !$tpl->appliesTo($emp)) {
                continue;
            }
            $eval = KpiEvaluation::create([
                'employee_id'   => $employeeId,
                'template_id'   => $tpl->id,
                'period_month'  => $periodMonth,
                'supervisor_id' => $emp->basicData?->direct_supervision ?: null,
                'status'        => KpiEvaluation::STATUS_DRAFT,
                'created_by'    => $actorId,
            ]);
            $rows = $tpl->indicators->map(fn ($ind) => [
                'evaluation_id' => $eval->id,
                'indicator_id'  => $ind->id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ])->all();
            if ($rows) {
                KpiEvaluationDetail::insert($rows);
            }
            $created++;
        }

        return $created;
    }
}
