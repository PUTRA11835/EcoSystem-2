<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman My Profile dengan tampilan lengkap seperti employee detail.
     */
    public function myProfile()
    {
        $sessionUser = session('user');

        if (!$sessionUser || ($sessionUser['type'] ?? null) !== 'employee') {
            return redirect()->route('login');
        }

        $employeeId = $sessionUser['id'];

        $employee = DB::table('employee as e')
            ->leftJoin('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
            ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
            ->where('e.employee_id', $employeeId)
            ->select(
                'e.employee_id as id',
                'e.eci',
                'e.is_active',
                'eb.basic_data_id',
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
                'eb.personnel_subarea',
                'eb.employee_group',
                'eb.employee_subgroup',
                'eb.position',
                'eb.division',
                'eb.department',
                'eb.direct_supervision',
                'eb.manager',
                'eb.authorization_group',
                'eb.block',
                'eb.deletion_flag',
                'eb.created_by',
                'eb.created_on',
                'eb.last_changed_by',
                'eb.last_changed_on',
                'ea.address_id',
                'ea.address_type',
                'ea.country',
                'ea.region',
                'ea.city',
                'ea.district',
                'ea.rural_urban_village',
                'ea.street',
                'ea.house_number',
                'ea.postal_code',
                'ea.language',
                'ea.cell_phone',
                'ea.telephone',
                'ea.telephone_extension',
                'ea.fax',
                'ea.email_personal',
                'ea.email_work',
                'ea.website',
                'ea.is_primary',
                'ea.valid_from',
                'ea.valid_to'
            )
            ->first();

        if (!$employee) {
            return redirect()->route('dashboard');
        }

        return view('master.employee.show', [
            'employee'     => $employee,
            'isOwnProfile' => true,
        ]);
    }

    /**
     * Ganti password — dipanggil via AJAX, kembalikan JSON.
     */
    public function changePassword(Request $request)
    {
        $sessionUser = session('user');

        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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

        try {
            $authUser = DB::table('auth_users')
                ->where('employee_id', $sessionUser['id'])
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

            Log::info('ProfileController: password changed successfully', [
                'auth_user_id' => $authUser->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('ProfileController@changePassword', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password. Please try again.',
            ], 500);
        }
    }
}
