<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    public function page()
    {
        return view('management.employee.qualification.index');
    }

    /**
     * Employee dengan qualification record di module ini (Skill/Certification/
     * Training/Education yang di-tag ke module_id). Sumbernya sama dengan yang
     * dipakai form Qualification per employee — hanya dibaca di sini, dikelola
     * tetap lewat tab Qualification masing-masing employee.
     */
    public function members(int $id)
    {
        $module = Module::findOrFail($id);

        $members = DB::table('employee as e')
            ->join('employee_qualification as eq', 'eq.employee_id', '=', 'e.employee_id')
            ->leftJoin('employee_basic_data as bd', 'e.employee_id', '=', 'bd.employee_id')
            ->where('eq.module_id', $module->id)
            ->where('e.is_active', true)
            ->select(
                'e.employee_id as id',
                DB::raw("TRIM(CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))) as name")
            )
            ->distinct()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $members,
        ]);
    }

    public function index(Request $request)
    {
        $query = Module::query()->with('groups:id,name');

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
