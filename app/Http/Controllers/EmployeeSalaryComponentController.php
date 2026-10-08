<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\Recruitment\OfferComponent;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Salary Components box of Master Employee → Contract. Which components exist is decided in Offering
 * Letter → Settings; here HR sets each employee's amount and the date it applies from, and adds a component
 * the employee did not start with (a raise, a new allowance after a new contract).
 *
 * Gates (routes/api.php): view = employee.section.salary.view, create / delete = its Create / Delete boxes,
 * update = employee.section.salary.update.
 */
class EmployeeSalaryComponentController extends Controller
{
    public function index($employeeId)
    {
        Employee::findOrFail($employeeId);

        $rows = EmployeeSalaryComponent::where('employee_id', $employeeId)
            ->orderByRaw("case kind when 'base' then 0 when 'fixed' then 1 else 2 end")
            ->orderBy('effective_from')->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn (EmployeeSalaryComponent $row) => $this->present($row))->values(),
            'summary' => EmployeeSalaryComponent::summaryOf($rows),
            // What can be added: every component of the Offering Settings that is active or already on a row.
            'components' => OfferComponent::ordered()->get()
                ->filter(fn (OfferComponent $c) => $c->is_active || $rows->contains('component_id', $c->id))
                ->map(fn (OfferComponent $c) => [
                    'id' => $c->id, 'name' => $c->name, 'kind' => $c->kind,
                    'category' => EmployeeSalaryComponent::KIND_LABELS[$c->kind] ?? $c->kind,
                    'in_use' => $rows->contains('component_id', $c->id),
                ])->values(),
        ]);
    }

    public function store(Request $request, $employeeId)
    {
        Employee::findOrFail($employeeId);
        $data = $this->validated($request, (int) $employeeId);
        $component = OfferComponent::findOrFail($data['component_id']);

        $row = EmployeeSalaryComponent::create([
            ...$data,
            'employee_id' => $employeeId,
            'name'        => $component->name,
            'kind'        => $component->kind,
            'source'      => EmployeeSalaryComponent::SOURCE_MANUAL,
            'created_by'  => session('user.eci'),
        ]);

        return response()->json(['success' => true, 'message' => "{$row->name} added.", 'data' => $this->present($row)], 201);
    }

    public function update(Request $request, $employeeId, $componentId)
    {
        $row = EmployeeSalaryComponent::where('employee_id', $employeeId)->findOrFail($componentId);
        $data = $this->validated($request, (int) $employeeId, $row);

        // A different component renames the row; the same one keeps the name it was saved with.
        if ((int) $data['component_id'] !== (int) $row->component_id) {
            $component = OfferComponent::findOrFail($data['component_id']);
            $data += ['name' => $component->name, 'kind' => $component->kind];
        }

        $row->update($data);

        return response()->json(['success' => true, 'message' => "{$row->name} saved.", 'data' => $this->present($row)]);
    }

    public function destroy($employeeId, $componentId)
    {
        $row = EmployeeSalaryComponent::where('employee_id', $employeeId)->findOrFail($componentId);
        $row->delete();

        return response()->json(['success' => true, 'message' => "{$row->name} removed."]);
    }

    private function validated(Request $request, int $employeeId, ?EmployeeSalaryComponent $row = null): array
    {
        return $request->validate([
            'component_id'   => [
                'required',
                Rule::exists('recruitment_offer_components', 'id'),
                // One line per component: a raise edits the line, it does not add a second one.
                Rule::unique('employee_salary_components', 'component_id')->where('employee_id', $employeeId)->ignore($row?->id),
            ],
            'amount'         => 'required|numeric|min:0|max:9999999999999',
            'effective_from' => 'required|date',
            'notes'          => 'nullable|string|max:255',
        ], ['component_id.unique' => 'This employee already has that component — edit its amount instead.']);
    }

    private function present(EmployeeSalaryComponent $row): array
    {
        return [
            'id'             => $row->id,
            'component_id'   => $row->component_id,
            'name'           => $row->name,
            'kind'           => $row->kind,
            'category'       => EmployeeSalaryComponent::KIND_LABELS[$row->kind] ?? $row->kind,
            'amount'         => $row->amount,
            'effective_from' => $row->effective_from?->toDateString(),
            'source'         => $row->source,
            'notes'          => $row->notes,
        ];
    }
}
