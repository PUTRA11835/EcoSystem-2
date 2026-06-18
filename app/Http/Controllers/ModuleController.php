<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function page()
    {
        return view('management.employee.qualification.index');
    }

    public function index(Request $request)
    {
        $query = Module::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $modules = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $modules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:modules,name',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $validated['name'] = strtoupper($validated['name']);

        $module = Module::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Module berhasil dibuat.',
            'data'    => $module,
        ], 201);
    }

    public function show(int $id)
    {
        $module = Module::findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $module,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $module = Module::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|string|max:100|unique:modules,name,' . $id,
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['name'] = strtoupper($validated['name']);
        }

        $module->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Module berhasil diupdate.',
            'data'    => $module->fresh(),
        ]);
    }

    public function destroy(int $id)
    {
        $module = Module::findOrFail($id);
        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module berhasil dihapus.',
        ]);
    }
}
