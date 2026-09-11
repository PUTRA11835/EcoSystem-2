<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Module;
use Illuminate\Http\Request;

class EmployeeModuleController extends Controller
{
    public function index(int $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        return response()->json([
            'success' => true,
            'data'    => $employee->modules()->orderBy('name')->get(),
        ]);
    }

    public function sync(Request $request, int $employeeId)
    {
        $request->validate([
            'module_ids'   => 'required|array',
            'module_ids.*' => 'integer|exists:modules,id',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $employee->modules()->sync($request->module_ids);

        return response()->json([
            'success' => true,
            'message' => 'Modul consultant berhasil disimpan.',
            'data'    => $employee->modules()->orderBy('name')->get(),
        ]);
    }

    public function attach(Request $request, int $employeeId)
    {
        $request->validate([
            'module_id' => 'required|integer|exists:modules,id',
        ]);

        $employee = Employee::findOrFail($employeeId);
        $employee->modules()->syncWithoutDetaching([$request->module_id]);

        return response()->json([
            'success' => true,
            'message' => 'Modul berhasil ditambahkan.',
            'data'    => $employee->modules()->orderBy('name')->get(),
        ]);
    }

    public function detach(int $employeeId, int $moduleId)
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->modules()->detach($moduleId);

        return response()->json([
            'success' => true,
            'message' => 'Modul berhasil dihapus.',
            'data'    => $employee->modules()->orderBy('name')->get(),
        ]);
    }
}
