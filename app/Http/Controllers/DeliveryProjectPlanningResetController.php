<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wipe an entire project planning structure in one shot.
 *
 * Escape hatch for a botched CSV import or a restructure large enough that
 * deleting node by node is impractical. Everything below the project is removed
 * — phases, groups, activities, their stages and member assignments — leaving
 * the project with a blank planning slate.
 *
 * NOT touched: timesheets. They are real logged work, so the rows survive and
 * only their activity_id link is cleared by the FK (SET NULL). The confirmation
 * dialog reports how many will be unlinked before the user commits.
 *
 * Deletion runs through the query builder rather than Eloquent on purpose. The
 * model's deleting/saved hooks recurse into children and recompute project
 * aggregates per row — correct for a single node, but O(n²) and log-flooding for
 * a few hundred. The aggregates are recomputed once at the end instead.
 */
class DeliveryProjectPlanningResetController extends Controller
{
    /** Typed by the user to arm the action. */
    private const CONFIRM_PHRASE = 'DELETE';

    /**
     * Report what a reset would remove, so the confirmation dialog can state
     * real numbers instead of a vague warning.
     */
    public function preview(DeliveryProject $project)
    {
        return response()->json([
            'success' => true,
            'counts'  => $this->countScope($project),
        ]);
    }

    public function destroyAll(Request $request, DeliveryProject $project)
    {
        $request->validate(['confirm' => 'required|string']);

        if (strtoupper(trim($request->input('confirm'))) !== self::CONFIRM_PHRASE) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi tidak cocok. Ketik "' . self::CONFIRM_PHRASE . '" untuk melanjutkan.',
            ], 422);
        }

        $before = $this->countScope($project);

        try {
            $deleted = DB::transaction(function () use ($project) {
                $activityIds = DB::table('delivery_project_activities')
                    ->where('delivery_projects_id', $project->id)
                    ->pluck('id');

                $planningIds = DB::table('delivery_project_planning')
                    ->where('delivery_projects_id', $project->id)
                    ->pluck('id');

                $result = ['stages' => 0, 'members' => 0, 'planning' => 0, 'activities' => 0, 'phases' => 0];

                // ── 1. Stages ────────────────────────────────────────────────
                // activity_stages uses soft deletes; go through the query builder
                // so the rows are physically gone rather than lingering as
                // tombstones that later joins could still pick up.
                if ($planningIds->isNotEmpty() || $activityIds->isNotEmpty()) {
                    $result['stages'] = DB::table('activity_stages')
                        ->when($planningIds->isNotEmpty(), fn($q) => $q->orWhereIn('planning_id', $planningIds))
                        ->when($activityIds->isNotEmpty(), fn($q) => $q->orWhereIn('activity_id', $activityIds))
                        ->delete();
                }

                // ── 2. Member assignments ────────────────────────────────────
                // The FK cascades anyway; deleting explicitly gives an exact count
                // to report back and keeps the order independent of FK config.
                if ($activityIds->isNotEmpty()) {
                    $result['members'] = DB::table('activity_employee')
                        ->whereIn('delivery_project_activity_id', $activityIds)
                        ->delete();
                }

                // ── 3. Planning rows ─────────────────────────────────────────
                // parent_id is self-referencing with ON DELETE CASCADE. Deleting
                // a parent before its child would work, but the order of rows in
                // a bulk DELETE is not ours to control. Breaking every link first
                // makes the delete a flat, order-independent operation.
                DB::table('delivery_project_planning')
                    ->where('delivery_projects_id', $project->id)
                    ->update(['parent_id' => null, 'stage_id' => null, 'activity_id' => null]);

                $result['planning'] = DB::table('delivery_project_planning')
                    ->where('delivery_projects_id', $project->id)
                    ->delete();

                // ── 4. Activities ────────────────────────────────────────────
                // Clears timesheets.activity_id via SET NULL — timesheet rows stay.
                $result['activities'] = DB::table('delivery_project_activities')
                    ->where('delivery_projects_id', $project->id)
                    ->delete();

                // ── 5. Phases ────────────────────────────────────────────────
                // parent_phase_id is self-referencing (SET NULL); break the links
                // first for the same reason as planning.parent_id above.
                DB::table('delivery_project_phases')
                    ->where('delivery_projects_id', $project->id)
                    ->update(['parent_phase_id' => null]);

                $result['phases'] = DB::table('delivery_project_phases')
                    ->where('delivery_projects_id', $project->id)
                    ->delete();

                // ── 6. Verify ────────────────────────────────────────────────
                // The whole point of this feature is that nothing survives. Assert
                // it inside the transaction so a partial wipe rolls back instead of
                // leaving the project in a half-deleted state.
                $leftover = $this->countScope($project);
                $residual = array_filter([
                    'planning'   => $leftover['planning'],
                    'activities' => $leftover['activities'],
                    'stages'     => $leftover['stages'],
                    'members'    => $leftover['members'],
                    'phases'     => $leftover['phases'],
                ]);

                if (!empty($residual)) {
                    throw new \RuntimeException(
                        'Verifikasi gagal, masih tersisa: ' . json_encode($residual) . '. Perubahan dibatalkan.'
                    );
                }

                return $result;
            });
        } catch (\Throwable $e) {
            Log::error('PlanningReset failed', [
                'project_id' => $project->id,
                'error'      => $e->getMessage(),
                'at'         => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus planning: ' . $e->getMessage(),
            ], 500);
        }

        // Aggregates (go-live estimate, current phase, category) are derived from
        // planning. Recompute once now that the structure is empty — the per-row
        // model hooks that normally do this were bypassed above.
        $project->refresh()->updateFromPlanning();

        Log::warning('PlanningReset completed', [
            'project_id'   => $project->id,
            'project_name' => $project->name,
            'deleted'      => $deleted,
            'timesheets_unlinked' => $before['timesheets'],
            'by'           => session('user.eci') ?? session('user.name') ?? 'user',
        ]);

        $parts = [
            $deleted['phases'] . ' fase',
            $deleted['planning'] . ' baris planning',
            $deleted['activities'] . ' activity',
        ];
        if ($deleted['members']) $parts[] = $deleted['members'] . ' assignment member';
        if ($deleted['stages'])  $parts[] = $deleted['stages'] . ' stage';

        $message = 'Planning berhasil dihapus: ' . implode(', ', $parts) . '.';
        if ($before['timesheets'] > 0) {
            $message .= ' ' . $before['timesheets'] . ' timesheet tetap tersimpan namun tautannya ke activity dilepas.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Count everything a reset would touch. Used both for the confirmation
     * preview and for the post-delete verification.
     */
    private function countScope(DeliveryProject $project): array
    {
        $activityIds = DB::table('delivery_project_activities')
            ->where('delivery_projects_id', $project->id)
            ->pluck('id');

        $planningIds = DB::table('delivery_project_planning')
            ->where('delivery_projects_id', $project->id)
            ->pluck('id');

        return [
            'planning'   => $planningIds->count(),
            'groups'     => DB::table('delivery_project_planning')
                                ->where('delivery_projects_id', $project->id)
                                ->where('is_group', true)->count(),
            'activities' => $activityIds->count(),
            'phases'     => DB::table('delivery_project_phases')
                                ->where('delivery_projects_id', $project->id)->count(),
            'members'    => $activityIds->isEmpty() ? 0 : DB::table('activity_employee')
                                ->whereIn('delivery_project_activity_id', $activityIds)->count(),
            'stages'     => $planningIds->isEmpty() ? 0 : DB::table('activity_stages')
                                ->whereIn('planning_id', $planningIds)->count(),
            'timesheets' => $activityIds->isEmpty() ? 0 : DB::table('timesheets')
                                ->whereIn('activity_id', $activityIds)->count(),
        ];
    }
}
