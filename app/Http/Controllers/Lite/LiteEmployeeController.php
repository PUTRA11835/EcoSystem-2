<?php

namespace App\Http\Controllers\Lite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiteEmployeeController extends Controller
{
    /**
     * Autocomplete @mention di internal note — mirror dari
     * EmployeeController::getMentionable() (aplikasi web), diadaptasi ke
     * konvensi auth/response Lite. Employee dibatasi 10 hasil (vs 20 di web).
     *
     * GET /api/lite/employees/mentionable?q={keyword}
     */
    public function mentionable(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $currentId = (int) $user['id'];
            $q         = $request->query('q', '');

            $employees = DB::table('employee as e')
                ->leftJoin('employee_basic_data as bd', 'e.employee_id', '=', 'bd.employee_id')
                ->leftJoin('auth_users as au', 'e.employee_id', '=', 'au.employee_id')
                ->leftJoin('employee_role_assignment as era', 'e.employee_id', '=', 'era.employee_id')
                ->leftJoin('employee_role as r', 'era.role_id', '=', 'r.id')
                ->where('e.employee_id', '!=', $currentId)
                ->where('e.is_active', true)
                ->where(function ($q2) {
                    $q2->whereNull('bd.block')->orWhere('bd.block', false);
                })
                ->where(function ($q2) {
                    $q2->whereNull('bd.deletion_flag')->orWhere('bd.deletion_flag', false);
                })
                ->when($q, function ($query) use ($q) {
                    $query->where(function ($inner) use ($q) {
                        $inner->where(DB::raw("CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))"), 'like', "%{$q}%")
                              ->orWhere('bd.nick_name', 'like', "%{$q}%");
                    });
                })
                ->select(
                    'e.employee_id as id',
                    DB::raw("TRIM(CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))) as name"),
                    'au.email as email',
                    DB::raw("GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') as role_name")
                )
                ->groupBy('e.employee_id', 'bd.first_name', 'bd.last_name', 'bd.nick_name', 'bd.block', 'bd.deletion_flag', 'au.email')
                ->orderBy('bd.first_name')
                ->limit(10)
                ->get()
                ->map(fn ($e) => [
                    'id'         => (int) $e->id,
                    'name'       => $e->name,
                    'email'      => $e->email,
                    // Belum ada kolom foto/avatar di employee/employee_basic_data — selalu null untuk saat ini.
                    'avatar_url' => null,
                    'role_name'  => $e->role_name,
                ]);

            $roles = DB::table('employee_role')
                ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'type' => 'role']);

            return response()->json([
                'success' => true,
                'data'    => [
                    'employees' => $employees,
                    'roles'     => $roles,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('LiteEmployeeController@mentionable error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve mentionable employees.'], 500);
        }
    }

    private function resolveUser(Request $request): ?array
    {
        return $request->session()->get('user')
            ?? $request->attributes->get('lite_user');
    }
}
