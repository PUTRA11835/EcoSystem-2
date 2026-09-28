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
        $templates = KpiTemplate::where('is_active', true)->startedBy($periodMonth)->with('indicators')->get();

        if ($templates->isEmpty() && !$prune) {
            return ['created' => 0, 'removed' => 0];
        }

        $employees = Employee::with(['basicData', 'roles', 'deliveryProjects'])
            ->where('is_active', true)
            ->get();

        $templatesById = $templates->keyBy('id');

        // Desired set, keyed "empId|tplId|subjectId" — the last segment is the
        // literal 'auto' marker when the counterpart is still derived from
        // master data, or the explicit subject's employee_id when the
        // template pins an explicit "for who"/"who fills" list. A template
        // with N explicit subjects produces N rows per matched employee (one
        // per counterpart), instead of the single auto-derived row.
        // => [empId, tplId, supervisorId, isAnonymous]
        $desired       = [];
        $tplIndicators = [];
        $activeIds     = $employees->pluck('employee_id')->flip()->all();
        foreach ($templates as $tpl) {
            $tplIndicators[$tpl->id] = $tpl->indicators->pluck('id')->all();

            // Peer templates with HR-picked pairs: each pair is exactly one
            // evaluation (reviewee = evaluated, reviewer = counterpart) and the
            // audience / subject lists are not consulted.
            if ($tpl->hasPeerPairs()) {
                foreach ($tpl->peerPairs() as [$reviewerId, $revieweeId]) {
                    if (!isset($activeIds[$reviewerId], $activeIds[$revieweeId])) {
                        continue;
                    }
                    $desired[$revieweeId . '|' . $tpl->id . '|' . $reviewerId] = [
                        $revieweeId, $tpl->id, $reviewerId, (bool) $tpl->is_anonymous,
                    ];
                }
                continue;
            }

            $explicitSubjects = $tpl->target_type !== 'self' && $tpl->hasExplicitSubjects()
                ? $tpl->subjectEmployeeIds()
                : null;

            foreach ($employees as $emp) {
                if (!$tpl->appliesTo($emp)) {
                    continue;
                }

                if ($explicitSubjects) {
                    foreach ($explicitSubjects as $subId) {
                        if ($subId === (int) $emp->employee_id) {
                            continue; // never let someone be their own counterpart
                        }
                        if (!self::subjectMatchesRater($tpl, $emp, $subId)) {
                            continue;
                        }
                        $desired[$emp->employee_id . '|' . $tpl->id . '|' . $subId] = [
                            $emp->employee_id, $tpl->id, $subId, (bool) $tpl->is_anonymous,
                        ];
                    }
                } else {
                    $supId = $tpl->target_type === 'self' ? null : $emp->basicData?->supervisorEmployeeId();
                    $desired[$emp->employee_id . '|' . $tpl->id . '|auto'] = [
                        $emp->employee_id, $tpl->id, $supId, (bool) $tpl->is_anonymous,
                    ];
                }
            }
        }

        $existing = KpiEvaluation::where('period_month', $periodMonth)
            ->get(['id', 'employee_id', 'template_id', 'supervisor_id', 'status', 'self_assessed_at', 'reviewed_at', 'overall_score']);

        // Key each existing row the same way "desired" is keyed, using the
        // template's CURRENT config rather than how the row was originally
        // created — so switching a template between auto and explicit-subject
        // mode is reconciled correctly on the next sync. Rows whose template
        // is no longer active/found get a unique key so they're never
        // considered desired and stay eligible for pruning, matching the
        // pre-existing behavior for deactivated templates.
        $existingKeys = [];
        foreach ($existing as $e) {
            $tpl = $templatesById->get($e->template_id);
            if (!$tpl) {
                $existingKeys['gone:' . $e->id] = $e;
                continue;
            }
            $explicit = $tpl->usesExplicitCounterparts();
            $key = $e->employee_id . '|' . $e->template_id . '|' . ($explicit ? ($e->supervisor_id ?? 'null') : 'auto');
            $existingKeys[$key] = $e;
        }

        $created = 0;
        $removed = 0;

        DB::transaction(function () use ($desired, $existingKeys, $periodMonth, $actorId, $tplIndicators, $templatesById, $prune, &$created, &$removed) {
            // ── create the missing ones ──────────────────────────────────────
            foreach ($desired as $key => [$empId, $tplId, $supId, $isAnon]) {
                if (isset($existingKeys[$key])) {
                    continue;
                }
                $eval = KpiEvaluation::create([
                    'employee_id'   => $empId,
                    'template_id'   => $tplId,
                    'period_month'  => $periodMonth,
                    'supervisor_id' => $supId,
                    'status'        => KpiEvaluation::STATUS_DRAFT,
                    'is_anonymous'  => $isAnon,
                    'created_by'    => $actorId,
                ] + self::deadlineColumns($templatesById->get($tplId), $periodMonth));

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
     * Upward assessments only pair a rater with their OWN direct supervisor:
     * even when the template lists many raters and many subjects, a rater is
     * matched to the subject that is their leader and to no one else (Siti
     * rates her lead, Budi rates his — never each other's). Other assessment
     * types keep the full rater × subject pairing.
     */
    private static function subjectMatchesRater(KpiTemplate $tpl, Employee $rater, int $subjectId): bool
    {
        if ($tpl->target_type !== 'upward') {
            return true;
        }

        return $rater->basicData?->supervisorEmployeeId() === $subjectId;
    }

    /**
     * Deadline column for a new evaluation, from the template's deadline
     * settings. Self and upward rows are filled by the employee (self_deadline);
     * lead and peer rows by the reviewer (supervisor_deadline).
     */
    private static function deadlineColumns(?KpiTemplate $tpl, string $periodMonth): array
    {
        $deadline = $tpl?->deadlineFor($periodMonth);
        if (!$deadline) {
            return [];
        }
        $column = in_array($tpl->target_type, ['self', 'upward'], true) ? 'self_deadline' : 'supervisor_deadline';
        return [$column => $deadline->toDateString()];
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

        $templates = KpiTemplate::where('is_active', true)->startedBy($periodMonth)->with('indicators')->get();
        if ($templates->isEmpty()) {
            return 0;
        }

        // Same "template_id|counterpart" pairing key as syncPeriod(), so an
        // explicit-subject template can produce more than one row for this
        // employee (one per subject) without re-creating rows that already exist.
        $existingPairKeys = KpiEvaluation::where('employee_id', $employeeId)
            ->where('period_month', $periodMonth)
            ->get(['template_id', 'supervisor_id'])
            ->map(function ($e) use ($templates) {
                $tpl = $templates->firstWhere('id', $e->template_id);
                $explicit = $tpl && $tpl->usesExplicitCounterparts();
                return $e->template_id . '|' . ($explicit ? ($e->supervisor_id ?? 'null') : 'auto');
            })->all();

        $created = 0;
        foreach ($templates as $tpl) {
            // Peer pairs: this employee is the reviewee of each pair naming them.
            if ($tpl->hasPeerPairs()) {
                foreach ($tpl->peerPairs() as [$reviewerId, $revieweeId]) {
                    if ($revieweeId !== $employeeId || in_array($tpl->id . '|' . $reviewerId, $existingPairKeys, true)) {
                        continue;
                    }
                    if (!Employee::where('employee_id', $reviewerId)->where('is_active', true)->exists()) {
                        continue;
                    }
                    $eval = KpiEvaluation::create([
                        'employee_id'   => $employeeId,
                        'template_id'   => $tpl->id,
                        'period_month'  => $periodMonth,
                        'supervisor_id' => $reviewerId,
                        'status'        => KpiEvaluation::STATUS_DRAFT,
                        'is_anonymous'  => (bool) $tpl->is_anonymous,
                        'created_by'    => $actorId,
                    ] + self::deadlineColumns($tpl, $periodMonth));
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
                continue;
            }

            if (!$tpl->appliesTo($emp)) {
                continue;
            }

            $explicitSubjects = $tpl->target_type !== 'self' && $tpl->hasExplicitSubjects()
                ? $tpl->subjectEmployeeIds()
                : null;

            $pairs = [];
            if ($explicitSubjects) {
                foreach ($explicitSubjects as $subId) {
                    if ($subId === $employeeId) {
                        continue; // never let someone be their own counterpart
                    }
                    if (!self::subjectMatchesRater($tpl, $emp, $subId)) {
                        continue;
                    }
                    $pairs[] = [$subId, $tpl->id . '|' . $subId];
                }
            } else {
                $supId = $tpl->target_type === 'self' ? null : $emp->basicData?->supervisorEmployeeId();
                $pairs[] = [$supId, $tpl->id . '|auto'];
            }

            foreach ($pairs as [$supId, $pairKey]) {
                if (in_array($pairKey, $existingPairKeys, true)) {
                    continue;
                }
                $eval = KpiEvaluation::create([
                    'employee_id'   => $employeeId,
                    'template_id'   => $tpl->id,
                    'period_month'  => $periodMonth,
                    'supervisor_id' => $supId,
                    'status'        => KpiEvaluation::STATUS_DRAFT,
                    'is_anonymous'  => (bool) $tpl->is_anonymous,
                    'created_by'    => $actorId,
                ] + self::deadlineColumns($tpl, $periodMonth));
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
        }

        return $created;
    }
}
