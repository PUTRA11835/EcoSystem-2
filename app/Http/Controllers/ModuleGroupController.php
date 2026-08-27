<?php

namespace App\Http\Controllers;

use App\Models\ModuleGroup;
use Illuminate\Http\Request;

class ModuleGroupController extends Controller
{
    public function page()
    {
        return view('management.module-groups.index');
    }

    public function index(Request $request)
    {
        $query = ModuleGroup::query()->with('modules:id,name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $groups = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $groups,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:module_groups,name',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
            'module_ids'  => 'nullable|array',
            'module_ids.*' => 'integer|exists:modules,id',
        ]);

        $moduleIds = $validated['module_ids'] ?? [];
        unset($validated['module_ids']);

        $group = ModuleGroup::create($validated);
        $group->modules()->sync($moduleIds);

        return response()->json([
            'success' => true,
            'message' => 'Module group berhasil dibuat.',
            'data'    => $group->load('modules:id,name'),
        ], 201);
    }

    public function show(int $id)
    {
        $group = ModuleGroup::with('modules:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $group,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $group = ModuleGroup::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:module_groups,name,' . $id,
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
            'module_ids'  => 'nullable|array',
            'module_ids.*' => 'integer|exists:modules,id',
        ]);

        if (array_key_exists('module_ids', $validated)) {
            $group->modules()->sync($validated['module_ids'] ?? []);
        }
        unset($validated['module_ids']);

        $group->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Module group berhasil diupdate.',
            'data'    => $group->fresh()->load('modules:id,name'),
        ]);
    }

    public function destroy(int $id)
    {
        $group = ModuleGroup::findOrFail($id);
        $group->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module group berhasil dihapus.',
        ]);
    }
}
