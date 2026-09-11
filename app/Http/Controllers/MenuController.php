<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuController extends Controller
{
    // ── Pages ────────────────────────────────────────────────────────────────────

    public function page()
    {
        return view('management.permissions.index');
    }

    // ── My menus (authenticated user) ────────────────────────────────────────────

    public function getMyMenus(Request $request)
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $employee = Employee::find($user['id']);
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Employee not found.'], 404);
        }

        $menus = $employee->accessibleMenus();
        $tree  = $this->buildTree($menus);

        return response()->json(['success' => true, 'data' => $tree]);
    }

    // ── CRUD Menu ────────────────────────────────────────────────────────────────

    public function index()
    {
        $menus = Menu::whereNull('parent_id')
            ->where('is_active', true)
            ->with('children.children')
            ->orderBy('order_seq')
            ->get();

        return response()->json(['success' => true, 'data' => $menus]);
    }

    public function allWithPermissions()
    {
        $menus = Menu::with(['roles' => function ($q) {
                $q->select('employee_role.id', 'employee_role.name')
                  ->withPivot('can_view', 'can_create', 'can_edit', 'can_delete');
            }])
            ->orderBy('parent_id')
            ->orderBy('order_seq')
            ->get();

        return response()->json(['success' => true, 'data' => $menus]);
    }

    public function withRoles()
    {
        $menus = Menu::with('roles:id,name')
            ->orderBy('parent_id')
            ->orderBy('order_seq')
            ->get();

        return response()->json(['success' => true, 'data' => $menus]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'slug'       => 'required|string|max:100|unique:menu,slug',
            'type'       => 'required|in:group,page,function',
            'parent_id'  => 'nullable|integer|exists:menu,id',
            'route_name' => 'nullable|string|max:150',
            'icon'       => 'nullable|string|max:100',
            'order_seq'  => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $menu = Menu::create($data);
        return response()->json(['success' => true, 'data' => $menu], 201);
    }

    public function update(Request $request, $menuId)
    {
        $menu = Menu::findOrFail($menuId);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'slug'       => 'required|string|max:100|unique:menu,slug,' . $menuId,
            'type'       => 'required|in:group,page,function',
            'parent_id'  => 'nullable|integer|exists:menu,id',
            'route_name' => 'nullable|string|max:150',
            'icon'       => 'nullable|string|max:100',
            'order_seq'  => 'nullable|integer',
            'is_active'  => 'boolean',
        ]);

        $menu->update($data);
        return response()->json(['success' => true, 'data' => $menu]);
    }

    public function destroy($menuId)
    {
        $menu = Menu::findOrFail($menuId);
        $menu->delete();
        return response()->json(['success' => true, 'message' => 'Menu deleted successfully.']);
    }

    // ── Menu ↔ Role permissions ──────────────────────────────────────────────────

    public function updateRolePermission(Request $request, $menuId, $roleId)
    {
        $menu = Menu::findOrFail($menuId);
        EmployeeRole::findOrFail($roleId);

        $data = $request->validate([
            'can_view'   => 'boolean',
            'can_create' => 'boolean',
            'can_edit'   => 'boolean',
            'can_delete' => 'boolean',
        ]);

        $menu->roles()->syncWithoutDetaching([
            $roleId => $data,
        ]);

        $this->flushPermCacheForRole((int) $roleId);

        return response()->json(['success' => true, 'message' => 'Permission updated successfully.']);
    }

    public function removeRolePermission($menuId, $roleId)
    {
        $menu = Menu::findOrFail($menuId);
        $menu->roles()->detach($roleId);

        $this->flushPermCacheForRole((int) $roleId);

        return response()->json(['success' => true, 'message' => 'Role access to the menu revoked successfully.']);
    }

    // ── Helper ───────────────────────────────────────────────────────────────────

    private function flushPermCacheForRole(int $roleId): void
    {
        $role = EmployeeRole::find($roleId);
        if ($role) {
            $role->employees()->pluck('employee.employee_id')->each(function ($empId) {
                Cache::forget("perm_slugs_{$empId}");
            });
        }
    }

    private function buildTree($menus, $parentId = null): array
    {
        return $menus->filter(fn($m) => $m->parent_id == $parentId)
            ->values()
            ->map(function ($menu) use ($menus) {
                $menu->children_tree = $this->buildTree($menus, $menu->id);
                return $menu;
            })
            ->toArray();
    }
}
