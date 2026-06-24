<?php

namespace App\Http\Controllers;

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
                'message' => 'Role tidak dapat dihapus karena masih digunakan oleh ' . $role->employees_count . ' employee.',
            ], 422);
        }

        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role berhasil dihapus.']);
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
        Menu::findOrFail($menuId);

        $data = $request->validate([
            'can_view'   => 'boolean',
            'can_create' => 'boolean',
            'can_edit'   => 'boolean',
            'can_delete' => 'boolean',
        ]);

        $role->menus()->syncWithoutDetaching([
            $menuId => $data,
        ]);

        $this->flushPermCacheForRole($role);

        return response()->json(['success' => true, 'message' => 'Permission berhasil diperbarui.']);
    }

    public function removePermission($id, $menuId)
    {
        $role = EmployeeRole::findOrFail($id);
        $role->menus()->detach($menuId);

        $this->flushPermCacheForRole($role);

        return response()->json(['success' => true, 'message' => 'Akses role ke menu berhasil dicabut.']);
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

        $employee->roles()->syncWithoutDetaching($request->role_ids);

        Cache::forget("perm_slugs_{$employee->employee_id}");

        return response()->json(['success' => true, 'message' => 'Role berhasil ditambahkan.']);
    }

    public function syncRoles(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'role_ids'   => 'required|array',
            'role_ids.*' => 'integer|exists:employee_role,id',
        ]);

        $employee->roles()->sync($request->role_ids);

        Cache::forget("perm_slugs_{$employee->employee_id}");

        return response()->json(['success' => true, 'message' => 'Role employee berhasil diperbarui.']);
    }

    public function revokeRole($employeeId, $roleId)
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->roles()->detach($roleId);

        Cache::forget("perm_slugs_{$employee->employee_id}");

        return response()->json(['success' => true, 'message' => 'Role berhasil dicabut dari employee.']);
    }

    private function flushPermCacheForRole(EmployeeRole $role): void
    {
        $role->employees()->pluck('employee_id')->each(function ($empId) {
            Cache::forget("perm_slugs_{$empId}");
        });
    }
}
