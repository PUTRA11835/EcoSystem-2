<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Module;
use App\Models\ModuleLead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModuleLeadController extends Controller
{
    /**
     * Lead untuk satu module.
     */
    public function index(int $moduleId)
    {
        $module = Module::findOrFail($moduleId);

        $leads = $module->leads()
            ->with('employee.basicData')
            ->get()
            ->map(fn (ModuleLead $lead) => $this->formatLead($lead));

        return response()->json([
            'success' => true,
            'data'    => $leads,
        ]);
    }

    /**
     * Tandai satu employee sebagai lead untuk module ini.
     */
    public function store(Request $request, int $moduleId)
    {
        $module = Module::findOrFail($moduleId);

        $request->validate([
            'employee_id' => 'required|integer|exists:employee,employee_id',
        ]);

        $exists = ModuleLead::where('module_id', $module->id)
            ->where('employee_id', $request->employee_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Employee ini sudah menjadi lead untuk module tersebut.',
            ], 422);
        }

        $lead = ModuleLead::create([
            'module_id'   => $module->id,
            'employee_id' => $request->employee_id,
        ]);

        $lead->load('employee.basicData');

        return response()->json([
            'success' => true,
            'message' => 'Module lead berhasil ditambahkan.',
            'data'    => $this->formatLead($lead),
        ], 201);
    }

    /**
     * Cabut status lead employee ini dari module.
     */
    public function destroy(int $moduleId, int $employeeId)
    {
        $deleted = ModuleLead::where('module_id', $moduleId)
            ->where('employee_id', $employeeId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Module lead tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Module lead berhasil dihapus.',
        ]);
    }

    /**
     * Employee aktif untuk dipilih sebagai lead (dipakai search box di modal).
     */
    public function searchEmployees(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $employees = DB::table('employee as e')
            ->leftJoin('employee_basic_data as bd', 'e.employee_id', '=', 'bd.employee_id')
            ->where('e.is_active', true)
            ->where(function ($query) {
                $query->whereNull('bd.block')->orWhere('bd.block', false);
            })
            ->where(function ($query) {
                $query->whereNull('bd.deletion_flag')->orWhere('bd.deletion_flag', false);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where(DB::raw("CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))"), 'like', "%{$q}%")
                          ->orWhere('bd.nick_name', 'like', "%{$q}%");
                });
            })
            ->select(
                'e.employee_id as id',
                DB::raw("TRIM(CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))) as name")
            )
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $employees,
        ]);
    }

    private function formatLead(ModuleLead $lead): array
    {
        $employee = $lead->employee;
        $basicData = $employee?->basicData;

        $name = $basicData
            ? trim(($basicData->first_name ?? '') . ' ' . ($basicData->last_name ?? ''))
            : null;

        return [
            'module_id'   => $lead->module_id,
            'employee_id' => $lead->employee_id,
            'name'        => $name ?: ('Employee #' . $lead->employee_id),
            'created_at'  => $lead->created_at?->format('Y-m-d'),
        ];
    }
}
