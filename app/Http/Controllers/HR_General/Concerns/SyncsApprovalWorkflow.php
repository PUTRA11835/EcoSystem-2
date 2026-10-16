<?php

namespace App\Http\Controllers\HR_General\Concerns;

use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The server side of the shared approval-workflow editor (resources/views/hr-general/partials/approval-workflow).
 *
 * One form, one Save: the page posts every row of the workflow (`steps[k][...]`) and the whole list is written in a
 * single transaction — added rows are created, changed rows updated, rows taken off the page deleted, and the order
 * is the order of the rows. Documents in progress hold their own copy of the steps they were created with, so none of
 * this rewrites an approval that already happened.
 *
 * Each module says which fields it has through `$cfg`:
 *
 *   name       bool   a free step name (Overtime, Purchase Request, Cash Advance); without it the name is taken from
 *                     the `select` label (Reimbursement: Accounting / Cashier / Approver)
 *   select     null | ['field' => 'signs_as'|'actor_role', 'label' => ..., 'options' => [value => label]]
 *   mandatory  bool   writes `requester_selectable` (= not mandatory); needs candidates the requester can choose from
 *
 * Names ticked → the step is `employee`; otherwise `role`. The chosen position is kept either way so the page shows
 * it again.
 */
trait SyncsApprovalWorkflow
{
    /** Lookups the editor needs besides the steps. @return array{employees: Collection, roleMembers: array, employeeRoles: array} */
    protected function workflowLookups(): array
    {
        return [
            'roles' => EmployeeRole::orderBy('name')->get(['id', 'name']),

            // Active employees only, and only the columns in use. FULL name (first + last), not the nickname.
            'employees' => Employee::with('basicData:basic_data_id,employee_id,nick_name,first_name,last_name,department')
                ->where('is_active', 1)
                ->get(['employee_id', 'eci'])
                ->sortBy(fn ($e) => mb_strtolower($e->fullNameLabel()))
                ->values(),

            // position => ids of the people holding it (narrows the name list of a step)
            'roleMembers' => DB::table('employee_role_assignment')
                ->select('role_id', 'employee_id')->get()
                ->groupBy('role_id')
                ->map(fn ($rows) => $rows->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
                ->all(),

            // employee => names of the positions they hold (shown under the Position column)
            'employeeRoles' => DB::table('employee_role_assignment')
                ->join('employee_role', 'employee_role.id', '=', 'employee_role_assignment.role_id')
                ->select('employee_role_assignment.employee_id', 'employee_role.name')->get()
                ->groupBy('employee_id')
                ->map(fn ($rows) => $rows->pluck('name')->unique()->values()->all())
                ->all(),
        ];
    }

    /**
     * Validate and write the posted workflow.
     *
     * @param  class-string  $stepModel  e.g. OvertimeApprovalStep::class (has `module`, `order_seq`, `is_active`)
     * @param  array         $cfg        see the class comment
     * @param  string        $redirect   route name of the Settings page
     * @param  callable|null $applyNew   fn (Collection $created): string — applies new steps to documents in progress,
     *                                   returns a sentence for the flash message (only called when "apply to open" is ticked)
     * @param  callable|null $guard      fn (array $built): ?string — a module rule over the whole list (error message or null)
     */
    protected function syncWorkflow(
        Request $request,
        string $stepModel,
        string $module,
        array $cfg,
        string $redirect,
        ?callable $applyNew = null,
        ?callable $guard = null,
        string $documents = 'documents',
    ) {
        $rows = array_values((array) $request->input('steps', []));

        if ($rows === []) {
            return back()->withInput()->with('error', 'The workflow needs at least one step.');
        }

        $errors = [];
        $built  = [];

        foreach ($rows as $i => $row) {
            $result = $this->workflowRowData((array) $row, $cfg);

            if (is_string($result)) {
                $errors['steps.' . $i] = 'Step ' . ($i + 1) . ': ' . $result;
                continue;
            }

            $built[] = ['id' => (int) ($row['id'] ?? 0), 'data' => $result];
        }

        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }

        if (!collect($built)->contains(fn ($b) => $b['data']['is_active'])) {
            return back()->withInput()->with('error',
                "At least one approval step must stay active, otherwise new {$documents} cannot be reviewed by anyone.");
        }

        if ($guard && ($message = $guard($built))) {
            return back()->withInput()->with('error', $message);
        }

        $created = collect();

        DB::transaction(function () use ($built, $stepModel, $module, &$created) {
            $existing = $stepModel::query()->where('module', $module)->get()->keyBy('id');

            // Move everything to a temporary range first so the new order cannot collide with the unique
            // (module, order_seq) index. The column is TINYINT (max 255), hence 200 and up.
            $temp = 200;
            foreach ($existing as $step) {
                $step->update(['order_seq' => $temp++]);
            }

            $keep = [];

            foreach ($built as $index => $item) {
                $payload = $item['data'] + ['order_seq' => $index + 1];

                if ($item['id'] && $existing->has($item['id'])) {
                    $existing[$item['id']]->update($payload);
                    $keep[] = $item['id'];
                } else {
                    $created->push($stepModel::create($payload + ['module' => $module]));
                }
            }

            // Rows taken off the page are deleted. Documents in progress keep their own copy.
            $existing->keys()->diff($keep)->each(fn ($id) => $existing[$id]->delete());
        });

        $note = ($request->boolean('apply_to_open') && $created->isNotEmpty() && $applyNew)
            ? $applyNew($created)
            : '';

        return redirect()
            ->route($redirect)
            ->with('success', 'Approval workflow saved successfully.' . $note)
            ->with('workflow_saved', $module);
    }

    /** One posted row → the columns to write, or a sentence saying what is wrong with it. @return array<string,mixed>|string */
    protected function workflowRowData(array $row, array $cfg): array|string
    {
        $data = [];

        if (!empty($cfg['name'])) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                return 'give the step a name.';
            }
            if (mb_strlen($name) > 100) {
                return 'the step name is longer than 100 characters.';
            }

            $data['name'] = $name;
        }

        if (!empty($cfg['select'])) {
            $field   = $cfg['select']['field'];
            $options = $cfg['select']['options'];
            $value   = (string) ($row[$field] ?? '');

            if (!array_key_exists($value, $options)) {
                return 'choose ' . strtolower($cfg['select']['label']) . ' — ' . implode(', ', $options) . '.';
            }

            $data[$field] = $value;

            if (empty($cfg['name'])) {
                $data['name'] = $options[$value];
            }
        }

        $names = array_values(array_unique(array_filter(array_map('intval', (array) ($row['approver_employee_ids'] ?? [])))));
        $role  = (int) ($row['approver_role_id'] ?? 0);

        if ($names !== [] && Employee::whereIn('employee_id', $names)->count() !== count($names)) {
            return 'one of the chosen names no longer exists.';
        }

        if ($role && !EmployeeRole::whereKey($role)->exists()) {
            return 'the chosen position no longer exists.';
        }

        if ($names === [] && !$role) {
            return 'choose a position, or at least one name.';
        }

        // Names without a position: when they all share exactly one position, that is the step's position.
        if (!$role && $names !== []) {
            $shared = null;

            foreach ($names as $id) {
                $mine   = DB::table('employee_role_assignment')->where('employee_id', $id)->pluck('role_id')->all();
                $shared = $shared === null ? $mine : array_values(array_intersect($shared, $mine));
            }

            if (is_array($shared) && count($shared) === 1) {
                $role = (int) $shared[0];
            }
        }

        if (!empty($cfg['mandatory'])) {
            $selectable = empty($row['mandatory']);

            if ($selectable && $names === []
                && !DB::table('employee_role_assignment')->where('role_id', $role)->exists()) {

                return 'nobody holds that position, so the requester would have nobody to choose.';
            }

            $data['requester_selectable'] = $selectable;
        }

        return $data + [
            'approver_type'         => $names !== [] ? 'employee' : 'role',
            'approver_role_id'      => $role ?: null,
            'approver_employee_ids' => $names !== [] ? $names : null,
            'is_active'             => !empty($row['is_active']),
        ];
    }
}
