<?php

namespace App\Http\Controllers\Lite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LiteProfileController extends Controller
{
    /**
     * Kembalikan data profil user yang sedang login sebagai JSON.
     * Adaptasi dari ProfileController::myProfile() — mengembalikan JSON, bukan view.
     *
     * GET /api/lite/profile
     */
    public function show(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $employeeId = $user['id'];

            $employee = DB::table('employee as e')
                ->leftJoin('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                ->where('e.employee_id', $employeeId)
                ->select(
                    'e.employee_id as id',
                    'e.eci',
                    'e.is_active',
                    'eb.title',
                    'eb.first_name',
                    'eb.last_name',
                    'eb.nick_name',
                    'eb.gender',
                    'eb.religion',
                    'eb.marital_status',
                    'eb.birth_date',
                    'eb.birth_place',
                    'eb.since_date',
                    'eb.personnel_area',
                    'eb.employee_group',
                    'eb.employee_subgroup',
                    'eb.position',
                    'eb.division',
                    'eb.department',
                    'eb.direct_supervision',
                    'eb.manager',
                    'eb.employee_type',
                    'ea.country',
                    'ea.region',
                    'ea.city',
                    'ea.district',
                    'ea.street',
                    'ea.postal_code',
                    'ea.cell_phone',
                    'ea.telephone',
                    'ea.email_personal',
                    'ea.email_work'
                )
                ->first();

            if (!$employee) {
                return response()->json(['success' => false, 'message' => 'Profile not found.'], 404);
            }

            // Ambil semua roles karyawan
            $roles = DB::table('employee_role_assignment as era')
                ->join('employee_role as er', 'era.role_id', '=', 'er.id')
                ->where('era.employee_id', $employeeId)
                ->select('er.id', 'er.name')
                ->get();

            $profileData = (array) $employee;
            $profileData['roles'] = $roles->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name])->values();

            return response()->json([
                'success' => true,
                'data'    => $profileData,
            ]);

        } catch (\Exception $e) {
            Log::error('LiteProfileController@show error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve profile.'], 500);
        }
    }

    /**
     * Ganti password user yang sedang login.
     * Logic identik dengan ProfileController::changePassword().
     *
     * PATCH /api/lite/profile/change-password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $validator = Validator::make($request->all(), [
                'password'              => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password update data is invalid.',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $authUser = DB::table('auth_users')
                ->where('employee_id', $user['id'])
                ->first();

            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'No auth account found for this employee.',
                ], 404);
            }

            DB::table('auth_users')->where('id', $authUser->id)->update([
                'password'   => Hash::make($request->password),
                'updated_at' => now(),
            ]);

            Log::info('LiteProfileController: password changed', ['auth_user_id' => $authUser->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('LiteProfileController@changePassword error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to change password. Please try again.'], 500);
        }
    }

    private function resolveUser(Request $request): ?array
    {
        return $request->session()->get('user')
            ?? $request->attributes->get('lite_user');
    }
}
