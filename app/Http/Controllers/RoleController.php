<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RoleController extends Controller
{
    // ── Page ────────────────────────────────────────────────────────────────────

    public function page()
    {
        return view('management.roles.index');
    }

    // ── CRUD Role ────────────────────────────────────────────────────────────────

    public function index()
    {
        $roles = EmployeeRole::withCount('employees')->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $roles]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:employee_role,name',
            'description' => 'nullable|string|max:255',
        ]);

        $role = EmployeeRole::create($data);
        return response()->json(['success' => true, 'data' => $role], 201);
    }

    public function show($id)
    {
        $role = EmployeeRole::withCount('employees')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $role]);
    }

    public function update(Request $request, $id)
    {
        $role = EmployeeRole::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:employee_role,name,' . $id,
            'description' => 'nullable|string|max:255',
        ]);

        $role->update($data);
        return response()->json(['success' => true, 'data' => $role]);
    }

    public function destroy($id)
    {
        $role = EmployeeRole::withCount('employees')->findOrFail($id);

        if ($role->employees_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'This role cannot be deleted because it is still used by ' . $role->employees_count . ' employee(s).',
            ], 422);
        }

        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role deleted successfully.']);
    }

    // ── Role Permissions (role ↔ menu) ───────────────────────────────────────────

    public function permissions($id)
    {
        $role = EmployeeRole::findOrFail($id);
        $menus = $role->menus()->orderBy('parent_id')->orderBy('order_seq')->get();

        return response()->json(['success' => true, 'data' => $menus]);
    }

    public function updatePermission(Request $request, $id, $menuId)
    {
        $role = EmployeeRole::findOrFail($id);
        $menu = Menu::findOrFail($menuId);

        $data = $request->validate([
            'can_view'   => 'boolean',
            'can_create' => 'boolean',
            'can_edit'   => 'boolean',
            'can_delete' => 'boolean',
        ]);

        // Pivot writes (syncWithoutDetaching/detach) never fire Eloquent model
        // events, so the Auditable trait on EmployeeRole never sees this -
        // logged explicitly, same reasoning as EmployeeController::changeRole().
        $before = $role->menus()->where('menu_id', $menuId)->first();

        $role->menus()->syncWithoutDetaching([
            $menuId => $data,
        ]);

        $this->flushPermCacheForRole($role);

        AuditLog::recordAction(
            module: 'Role Management',
            auditableType: EmployeeRole::class,
            auditableId: $role->id,
            event: 'updated',
            recordLabel: $role->name,
            description: "changed \"{$menu->name}\" permission for role \"{$role->name}\"",
            old: $before ? [
                'can_view'   => (bool) $before->pivot->can_view,
                'can_create' => (bool) $before->pivot->can_create,
                'can_edit'   => (bool) $before->pivot->can_edit,
                'can_delete' => (bool) $before->pivot->can_delete,
            ] : null,
            new: $data,
        );

        return response()->json(['success' => true, 'message' => 'Permission updated successfully.']);
    }

    public function removePermission($id, $menuId)
    {
        $role = EmployeeRole::findOrFail($id);
        $menu = Menu::find($menuId);
        $role->menus()->detach($menuId);

        $this->flushPermCacheForRole($role);

        AuditLog::recordAction(
            module: 'Role Management',
            auditableType: EmployeeRole::class,
            auditableId: $role->id,
            event: 'updated',
            recordLabel: $role->name,
            description: 'revoked role "' . $role->name . '" access to "' . ($menu->name ?? "menu #{$menuId}") . '"',
            old: null,
            new: null,
        );

        return response()->json(['success' => true, 'message' => 'Role access to the menu revoked successfully.']);
    }

    // ── Employee list for role ───────────────────────────────────────────────────

    public function employees($id)
    {
        $role = EmployeeRole::findOrFail($id);
        $employees = $role->employees()
            ->with('basicData:employee_id,first_name,last_name,nick_name')
            ->get()
            ->map(fn($e) => [
                'employee_id' => $e->employee_id,
                'eci'         => $e->eci,
                'full_name'   => $e->basicData ? trim($e->basicData->first_name . ' ' . $e->basicData->last_name) : null,
            ]);

        return response()->json(['success' => true, 'data' => $employees]);
    }

    // ── Employee ↔ Role assignment ───────────────────────────────────────────────

    public function employeeRoles($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        return response()->json(['success' => true, 'data' => $employee->roles]);
    }

    public function assignRoles(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'role_ids'   => 'required|array',
            'role_ids.*' => 'integer|exists:employee_role,id',
        ]);

        $before = $employee->roles()->pluck('name')->values()->all();

        $employee->roles()->syncWithoutDetaching($request->role_ids);

        Cache::forget("perm_slugs_{$employee->employee_id}");

        $after = $employee->roles()->pluck('name')->values()->all();
        $label = $employee->name ?? ('Employee #' . $employee->employee_id);

        // Same underlying action as EmployeeController::changeRole() (which
        // already logs under module "Employee Role") - kept on the same
        // module string so both entry points show up together in the Audit
        // Log filter instead of splitting one action across two module names.
        AuditLog::recordAction(
            module: 'Employee Role',
            auditableType: Employee::class,
            auditableId: $employee->employee_id,
            event: 'updated',
            recordLabel: $label,
            description: "added role(s) for {$label}",
            old: ['role_names' => $before],
            new: ['role_names' => $after],
        );

        return response()->json(['success' => true, 'message' => 'Role added successfully.']);
    }

    public function syncRoles(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'role_ids'   => 'required|array',
            'role_ids.*' => 'integer|exists:employee_role,id',
        ]);

        $before = $employee->roles()->pluck('name')->values()->all();

        $employee->roles()->sync($request->role_ids);

        Cache::forget("perm_slugs_{$employee->employee_id}");

        $after = $employee->roles()->pluck('name')->values()->all();
        $label = $employee->name ?? ('Employee #' . $employee->employee_id);

        AuditLog::recordAction(
            module: 'Employee Role',
            auditableType: Employee::class,
            auditableId: $employee->employee_id,
            event: 'updated',
            recordLabel: $label,
            description: "changed roles for {$label}",
            old: ['role_names' => $before],
            new: ['role_names' => $after],
        );

        return response()->json(['success' => true, 'message' => 'Employee roles updated successfully.']);
    }

    public function revokeRole($employeeId, $roleId)
    {
        $employee = Employee::findOrFail($employeeId);
        $roleName = EmployeeRole::find($roleId)->name ?? "Role #{$roleId}";
        $employee->roles()->detach($roleId);

        Cache::forget("perm_slugs_{$employee->employee_id}");

        $label = $employee->name ?? ('Employee #' . $employee->employee_id);

        AuditLog::recordAction(
            module: 'Employee Role',
            auditableType: Employee::class,
            auditableId: $employee->employee_id,
            event: 'updated',
            recordLabel: $label,
            description: "revoked role \"{$roleName}\" from {$label}",
            old: null,
            new: null,
        );

        return response()->json(['success' => true, 'message' => 'Role revoked from the employee successfully.']);
    }

    private function flushPermCacheForRole(EmployeeRole $role): void
    {
        $role->employees()->pluck('employee.employee_id')->each(function ($empId) {
            Cache::forget("perm_slugs_{$empId}");
        });
    }
}
