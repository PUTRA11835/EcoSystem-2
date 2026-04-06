<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Tampilkan halaman profil user yang sedang login.
     */
    public function edit(Request $request)
    {
        $sessionUser = session('user');

        if (!$sessionUser) {
            return redirect()->route('login');
        }

        $type    = $sessionUser['type'] ?? null;
        $profile = null;

        if ($type === 'employee') {
            $profile = DB::table('employee as e')
                ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
                ->where('e.employee_id', $sessionUser['id'])
                ->select(
                    'e.employee_id',
                    'e.eci',
                    'e.is_active',
                    'eb.title',
                    'eb.first_name',
                    'eb.last_name',
                    'eb.nick_name',
                    'eb.gender',
                    'eb.birth_date',
                    'eb.birth_place',
                    'eb.religion',
                    'eb.marital_status',
                    'eb.since_date',
                    'eb.position',
                    'eb.division',
                    'eb.department',
                    'eb.employee_group',
                    'eb.employee_subgroup',
                    'ea.street',
                    'ea.city',
                    'ea.region',
                    'ea.country',
                    'ea.postal_code',
                    'ea.cell_phone',
                    'ea.telephone',
                    'ea.email_personal',
                    'ea.email_work',
                    'r.name as role_name'
                )
                ->first();

        }

        // Email & phone dari auth_users (sumber utama login)
        $authUser = DB::table('auth_users')
            ->where('employee_id', $sessionUser['id'])
            ->select('id', 'email', 'phone', 'username', 'last_login_at', 'created_at')
            ->first();

        return view('profile.edit', compact('profile', 'authUser', 'sessionUser'));
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

        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $authUser = DB::table('auth_users')
            ->where('employee_id', $sessionUser['id'])
            ->first();

        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Account not found'], 404);
        }

        if (!Hash::check($request->current_password, $authUser->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'errors'  => ['current_password' => ['Current password is incorrect']],
            ], 422);
        }

        DB::table('auth_users')->where('id', $authUser->id)->update([
            'password'   => Hash::make($request->password),
            'updated_at' => now(),
        ]);

        Log::info('ProfileController: password berhasil diubah', [
            'auth_user_id' => $authUser->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }
}
