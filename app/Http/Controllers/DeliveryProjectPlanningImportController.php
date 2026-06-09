<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Bulk migration of an entire project planning structure from a CSV file.
 *
 * The CSV mirrors the hierarchical table view: each row carries its Phase /
 * Group context plus (optionally) an Activity. Phases and Groups are created
 * on demand (matched by name), Activities are appended under their Group (or
 * directly under the Phase when no Group is given). Nested groups are written
 * in the Group column with a ">" separator, e.g. "Realization > Configuration".
 *
 * Validation guards mirror ActivityManagementController@store — dates must stay
 * inside the project's contract window and an activity's weight may not push a
 * phase past its budgeted weight. Violations do not abort the import: the
 * offending row is skipped and recorded in the returned error log.
 */
class DeliveryProjectPlanningImportController extends Controller
{
    /** Canonical column => accepted header variants (case-insensitive). */
    private array $aliasMap = [
        'phase'        => ['phase', 'phase name', 'fase'],
        'phase_weight' => ['phase weight', 'phase_weight', 'bobot fase'],
        'group'        => ['group', 'group name', 'grup'],
        'activity'     => ['activity', 'activity name', 'aktivitas', 'task'],
        'weight'       => ['weight', 'weight %', 'bobot'],
        'module'       => ['module', 'modul'],
        'object'       => ['object', 'objek'],
        'complexity'   => ['complexity', 'kompleksitas'],
        'start_date'   => ['start date', 'start_date', 'start', 'tanggal mulai'],
        'end_date'     => ['end date', 'end_date', 'end', 'tanggal selesai'],
        'deliverable'  => ['deliverable', 'deliverables'],
        'notes'        => ['notes', 'note', 'catatan'],
    ];

    // ── Template ──────────────────────────────────────────────────────────────

    /**
     * Stream a CSV template: header row + a few example rows demonstrating the
     * Phase → Group → Activity hierarchy.
     */
    public function template(DeliveryProject $project)
    {
        $filename = 'project-planning-import-template.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

            fputcsv($handle, [
                'Phase', 'Phase Weight', 'Group', 'Activity', 'Weight',
                'Module', 'Object', 'Complexity', 'Start Date', 'End Date',
                'Deliverable', 'Notes',
            ]);

            // Example rows. NOTE on Weight: the activity weights within ONE phase
            // must sum to AT MOST that phase's weight (e.g. Analysis & Design = 30
            // → its activities sum to 10 + 20 = 30). Dates must fall inside the
            // project's Contract Start/End window. Accepts YYYY-MM-DD or DD/MM/YYYY.
            fputcsv($handle, [
                'Analysis & Design', '30', 'Requirement Gathering', 'Kickoff Meeting', '10',
                'FI', 'Document', 'low', '2026-06-10', '2026-06-12',
                'Minutes of Meeting', 'Initial kickoff',
            ]);
            fputcsv($handle, [
                'Analysis & Design', '30', 'Requirement Gathering', 'Business Blueprint', '20',
                'FI', 'Document', 'medium', '2026-06-13', '2026-06-20',
                'Blueprint Document', '',
            ]);
            fputcsv($handle, [
                'Realization', '50', 'Configuration', 'Module Setup', '50',
                'MM', 'Config', 'high', '2026-06-21', '2026-06-28',
                'Configuration Document', '',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function import(Request $request, DeliveryProject $project)
    {
        set_time_limit(300);

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:20480']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'File CSV kosong atau tidak valid'], 422);
        }

        // Build colIndex: canonical_field => column index in CSV
        $colIndex = [];
        foreach ($this->aliasMap as $field => $aliases) {
            foreach ($rawHeaders as $i => $header) {
                $h = strtolower(trim($header));
                foreach ($aliases as $alias) {
                    if (strtolower(trim($alias)) === $h) {
                        $colIndex[$field] = $i;
                        break 2;
                    }
                }
            }
        }

        if (!isset($colIndex['phase'])) {
            fclose($handle);
            return response()->json(['success' => false, 'message' => 'Kolom "Phase" wajib ada di file'], 422);
        }

        $row = []; // referenced in closure
        $get = function (string $field) use ($colIndex, &$row): ?string {
            if (!isset($colIndex[$field])) return null;
            $val = trim($row[$colIndex[$field]] ?? '');
            return $val !== '' ? $val : null;
        };

        // Caches so repeated Phase/Group context across rows resolves to one record.
        $phaseCache = []; // lower(name) => DeliveryProjectPhase
        $groupCache = []; // "phaseId|lower(path)" => DeliveryProjectPlanning (group)

        $phasesCreated     = 0;
        $groupsCreated     = 0;
        $activitiesCreated = 0;
        $activitiesUpdated = 0;
        $errors            = [];
        $rowNum            = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) === 1 && trim((string) $row[0]) === '') continue; // blank line

            $phaseName = $get('phase');
            if (!$phaseName) {
                $errors[] = "Baris {$rowNum}: kolom Phase wajib diisi — baris dilewati";
                continue;
            }

            try {
                // ── Resolve / create Phase ─────────────────────────────────────
                $phase = $this->resolvePhase($project, $phaseName, $get('phase_weight'), $phaseCache, $phasesCreated);

                // ── Resolve / create Group(s) ──────────────────────────────────
                $group = null;
                if ($groupName = $get('group')) {
                    $group = $this->resolveGroup($project, $phase, $groupName, $groupCache, $groupsCreated);
                }

                // ── Create Activity (when present) ─────────────────────────────
                $activityName = $get('activity');
                if (!$activityName) {
                    // Structure-only row (Phase and/or Group). Nothing more to do.
                    continue;
                }

                $startDate = $get('start_date');
                $endDate   = $get('end_date');

                // Contract window guard
                if ($rangeError = $this->contractRangeViolation($project, $startDate, $endDate)) {
                    $errors[] = "Baris {$rowNum} ({$activityName}): {$rangeError} — baris dilewati";
                    continue;
                }

                // end >= start sanity
                $startC = $startDate ? $this->parseDate($startDate) : null;
                $endC   = $endDate ? $this->parseDate($endDate) : null;
                if ($startC && $endC && $endC->lt($startC)) {
                    $errors[] = "Baris {$rowNum} ({$activityName}): End Date lebih awal dari Start Date — baris dilewati";
                    continue;
                }

                // Read weight, distinguishing a blank cell from an explicit 0.
                $weightRaw      = $get('weight');
                $weightProvided = ($weightRaw !== null && is_numeric($weightRaw));
                $weight         = $weightProvided ? (float) $weightRaw : 0.0;

                $complexity = $get('complexity');
                if ($complexity !== null && !in_array(strtolower($complexity), ['low', 'medium', 'high'], true)) {
                    $complexity = null; // ignore unknown complexity rather than fail the row
                }

                // Reuse an existing activity of the same name under this phase/group;
                // when found, the CSV row replaces its details (upsert, no duplicate).
                $existingPlanning = $this->findExistingActivity($project, $phase, $group, $activityName);

                // Phase weight budget guard (only when the phase carries a budget).
                // On update, exclude the activity's own current weight from the tally.
                if ($weightProvided && $weight > 0 && $phase->weight > 0) {
                    $usedQuery = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                        ->where('phase_id', $phase->id)
                        ->where('is_group', false);
                    if ($existingPlanning) {
                        $usedQuery->where('id', '!=', $existingPlanning->id);
                    }
                    $usedWeight = (float) $usedQuery->sum('weight');
                    if (($usedWeight + $weight) > $phase->weight + 0.01) {
                        $errors[] = "Baris {$rowNum} ({$activityName}): bobot melebihi batas fase \"{$phase->name}\" "
                            . "(batas {$phase->weight}%, terpakai " . round($usedWeight, 2) . "%, sisa "
                            . round($phase->weight - $usedWeight, 2) . "%) — baris dilewati";
                        continue;
                    }
                }

                $payload = [
                    'name'        => $activityName,
                    'weight'      => $weight,
                    'weight_set'  => $weightProvided,
                    'module'      => $get('module'),
                    'object'      => $get('object'),
                    'complexity'  => $complexity ? strtolower($complexity) : null,
                    'deliverable' => $get('deliverable'),
                    'notes'       => $get('notes'),
                    'start_date'  => $startC,
                    'end_date'    => $endC,
                ];

                if ($existingPlanning) {
                    // Activity already exists → overwrite its details with the CSV
                    // values (only non-empty cells overwrite; blanks keep old value).
                    $this->updateActivity($project, $group, $existingPlanning, $payload);
                    $activitiesUpdated++;
                } else {
                    $this->createActivity($project, $phase, $group, $payload);
                    $activitiesCreated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Baris {$rowNum}: " . $e->getMessage();
                Log::error('PlanningImport row error', [
                    'project_id' => $project->id,
                    'row'        => $rowNum,
                    'error'      => $e->getMessage(),
                    'at'         => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        fclose($handle);

        Log::info('PlanningImport completed', [
            'project_id'         => $project->id,
            'phases_created'     => $phasesCreated,
            'groups_created'     => $groupsCreated,
            'activities_created' => $activitiesCreated,
            'activities_updated' => $activitiesUpdated,
            'errors'             => count($errors),
            'by'                 => session('user.eci') ?? session('user.name') ?? 'user',
        ]);

        $summary = "Import selesai: {$activitiesCreated} activity baru, {$activitiesUpdated} activity diperbarui, "
            . "{$groupsCreated} group, {$phasesCreated} fase dibuat"
            . (count($errors) ? ', ' . count($errors) . ' baris error' : '');

        return response()->json([
            'success'            => true,
            'message'            => $summary,
            'phases_created'     => $phasesCreated,
            'groups_created'     => $groupsCreated,
            'activities_created' => $activitiesCreated,
            'activities_updated' => $activitiesUpdated,
            'errors'             => $errors,
        ]);
    }

    // ── Resolvers ─────────────────────────────────────────────────────────────

    /**
     * Find a visible phase of this project by name (case-insensitive), or create
     * a new vertical phase on demand. Weight is taken from the CSV on creation only.
     */
    private function resolvePhase(DeliveryProject $project, string $name, ?string $phaseWeight, array &$cache, int &$created): DeliveryProjectPhase
    {
        $key = strtolower($name);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $phase = $project->phases()
            ->whereRaw('LOWER(name) = ?', [$key])
            ->first();

        if (!$phase) {
            $weight = ($phaseWeight !== null && is_numeric($phaseWeight)) ? (float) $phaseWeight : 0.0;

            $phase = $project->phases()->create([
                'name'              => $name,
                'order_sequence'    => ($project->phases()->where('orientation', 'vertical')->max('order_sequence') ?? 0) + 1,
                'color'             => '#6366f1',
                'weight'            => $weight,
                'is_visible'        => true,
                'orientation'       => 'vertical',
                'is_optional'       => false,
                'is_active'         => true,
                'is_system_default' => false,
            ]);
            $created++;
        }

        return $cache[$key] = $phase;
    }

    /**
     * Resolve a (possibly nested) group path under a phase, creating any missing
     * level. Nested levels are split on ">" — e.g. "Realization > Configuration".
     */
    private function resolveGroup(DeliveryProject $project, DeliveryProjectPhase $phase, string $groupPath, array &$cache, int &$created): DeliveryProjectPlanning
    {
        $segments = array_values(array_filter(array_map('trim', explode('>', $groupPath)), fn($s) => $s !== ''));

        $parent = null;
        $pathSoFar = '';

        foreach ($segments as $segment) {
            $pathSoFar = $pathSoFar === '' ? strtolower($segment) : $pathSoFar . '>' . strtolower($segment);
            $cacheKey = $phase->id . '|' . $pathSoFar;

            if (isset($cache[$cacheKey])) {
                $parent = $cache[$cacheKey];
                continue;
            }

            $query = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                ->where('phase_id', $phase->id)
                ->where('is_group', true)
                ->whereRaw('LOWER(group_name) = ?', [strtolower($segment)]);

            if ($parent) {
                $query->where('parent_id', $parent->id);
            } else {
                $query->whereNull('parent_id');
            }

            $group = $query->first();

            if (!$group) {
                $maxSequence = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                    ->where('phase_id', $phase->id)
                    ->when($parent, fn($q) => $q->where('parent_id', $parent->id), fn($q) => $q->whereNull('parent_id'))
                    ->max('order_sequence') ?? 0;

                $group = new DeliveryProjectPlanning();
                $group->delivery_projects_id = $project->id;
                $group->phase_id            = $phase->id;
                $group->parent_id           = $parent?->id;
                $group->is_group            = true;
                $group->group_name          = $segment;
                $group->level               = $parent ? ($parent->level + 1) : 0;
                $group->order_sequence      = $maxSequence + 1;
                $group->status              = 'not_started';
                $group->progress_percentage = 0;
                $group->weight              = 0;
                $group->save();
                $created++;
            }

            $cache[$cacheKey] = $group;
            $parent = $group;
        }

        return $parent;
    }

    /**
     * Create an activity (delivery_project_activities row) and its linked planning
     * record, mirroring ActivityManagementController@store. Recalculates the parent
     * group's weight & status afterwards.
     */
    private function createActivity(DeliveryProject $project, DeliveryProjectPhase $phase, ?DeliveryProjectPlanning $group, array $data): void
    {
        DB::transaction(function () use ($project, $phase, $group, $data) {
            $maxActivitySequence = DeliveryProjectActivity::where('delivery_projects_id', $project->id)
                ->where('delivery_project_phase_id', $phase->id)
                ->max('order_sequence') ?? 0;

            $activity = DeliveryProjectActivity::create([
                'delivery_projects_id'      => $project->id,
                'delivery_project_phase_id' => $phase->id,
                'stage_id'                  => null,
                'name'                      => $data['name'],
                'description'               => $data['notes'] ?? '',
                'order_sequence'            => $maxActivitySequence + 1,
                'start_date'                => $data['start_date'],
                'end_date'                  => $data['end_date'],
                'weight'                    => $data['weight'],
                'progress_percentage'       => 0,
                'status'                    => 'not_started',
                'module'                    => $data['module'],
                'object'                    => $data['object'],
                'complexity'                => $data['complexity'],
                'new_requirement'           => false,
                'deliverable'               => $data['deliverable'],
                'notes'                     => $data['notes'],
            ]);

            $query = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                ->where('phase_id', $phase->id);
            $group ? $query->where('parent_id', $group->id) : $query->whereNull('parent_id');
            $maxSequence = $query->max('order_sequence') ?? 0;

            $planning = new DeliveryProjectPlanning();
            $planning->delivery_projects_id = $project->id;
            $planning->phase_id            = $phase->id;
            $planning->parent_id           = $group?->id;
            $planning->stage_id            = null;
            $planning->is_group            = false;
            $planning->level               = $group ? ($group->level + 1) : 0;
            $planning->order_sequence      = $maxSequence + 1;
            $planning->status              = 'not_started';
            $planning->progress_percentage = 0;
            $planning->notes               = $data['notes'];
            $planning->start_date          = $activity->start_date;
            $planning->end_date            = $activity->end_date;
            $planning->weight              = $activity->weight;
            $planning->activity_id         = $activity->id;
            $planning->save();

            if ($group && $group->is_group) {
                $group->recalculateGroupWeight();
                $group->updateGroupStatus();
            }
        });
    }

    /**
     * Find an existing activity planning row of the same name (case-insensitive)
     * under the same phase and group. Returns null when none exists — that path
     * creates a new activity instead.
     */
    private function findExistingActivity(DeliveryProject $project, DeliveryProjectPhase $phase, ?DeliveryProjectPlanning $group, string $name): ?DeliveryProjectPlanning
    {
        return DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', false)
            ->when($group, fn($q) => $q->where('parent_id', $group->id), fn($q) => $q->whereNull('parent_id'))
            ->whereHas('activity', function ($q) use ($name) {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);
            })
            ->with('activity')
            ->first();
    }

    /**
     * Replace an existing activity's details with the CSV row values. Only fields
     * the CSV actually supplied (non-empty cells) overwrite the stored values —
     * blank cells leave the old data intact. Recalculates the group afterwards.
     */
    private function updateActivity(DeliveryProject $project, ?DeliveryProjectPlanning $group, DeliveryProjectPlanning $planning, array $data): void
    {
        DB::transaction(function () use ($group, $planning, $data) {
            $activity = $planning->activity;

            if ($activity) {
                if ($data['start_date'] !== null)   $activity->start_date  = $data['start_date'];
                if ($data['end_date'] !== null)     $activity->end_date    = $data['end_date'];
                if ($data['weight_set'])            $activity->weight      = $data['weight'];
                if ($data['module'] !== null)       $activity->module      = $data['module'];
                if ($data['object'] !== null)       $activity->object      = $data['object'];
                if ($data['complexity'] !== null)   $activity->complexity  = $data['complexity'];
                if ($data['deliverable'] !== null)  $activity->deliverable = $data['deliverable'];
                if ($data['notes'] !== null) {
                    $activity->notes       = $data['notes'];
                    $activity->description = $data['notes'];
                }
                $activity->save();
            }

            // Mirror the relevant fields onto the planning row (kept in sync).
            if ($data['start_date'] !== null) $planning->start_date = $data['start_date'];
            if ($data['end_date'] !== null)   $planning->end_date   = $data['end_date'];
            if ($data['weight_set'])          $planning->weight     = $data['weight'];
            if ($data['notes'] !== null)      $planning->notes      = $data['notes'];
            $planning->save();

            if ($group && $group->is_group) {
                $group->recalculateGroupWeight();
                $group->updateGroupStatus();
            }
        });
    }

    // ── Helpers (mirrored from ActivityManagementController) ───────────────────

    /** Parse a date from DD/MM/YYYY or any standard format. */
    private function parseDate($dateString): ?Carbon
    {
        if (empty($dateString)) {
            return null;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
            return Carbon::createFromFormat('d/m/Y', $dateString);
        }
        return Carbon::parse($dateString);
    }

    /**
     * Returns an error message when the given dates fall outside the project's
     * contract window, or null when valid / when no window is set.
     */
    private function contractRangeViolation(DeliveryProject $project, $startDate, $endDate): ?string
    {
        $cStart = $project->contract_start_date;
        $cEnd   = $project->contract_end_date;
        if (!$cStart && !$cEnd) {
            return null;
        }

        $start = $startDate ? $this->parseDate($startDate) : null;
        $end   = $endDate ? $this->parseDate($endDate) : null;

        $fmt = fn ($d) => Carbon::parse($d)->format('d M Y');

        if ($cStart && $start && $start->lt(Carbon::parse($cStart))) {
            return 'Start date (' . $fmt($start) . ') lebih awal dari Contract Start Date (' . $fmt($cStart) . ')';
        }
        if ($cEnd && $end && $end->gt(Carbon::parse($cEnd))) {
            return 'End date (' . $fmt($end) . ') melewati Contract End Date (' . $fmt($cEnd) . ')';
        }
        if ($cEnd && $start && $start->gt(Carbon::parse($cEnd))) {
            return 'Start date (' . $fmt($start) . ') melewati Contract End Date (' . $fmt($cEnd) . ')';
        }
        if ($cStart && $end && $end->lt(Carbon::parse($cStart))) {
            return 'End date (' . $fmt($end) . ') lebih awal dari Contract Start Date (' . $fmt($cStart) . ')';
        }

        return null;
    }
}
