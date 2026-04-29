<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;

class EmployeeAuthController extends Controller
{
    /**
     * Login — khusus employee
     *
     * Hanya user dengan employee_id (bukan customer_id) yang boleh login.
     * Menghasilkan access_token (15 menit) dan refresh_token (7 hari).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $identifier = trim($request->email);

        // Cari di auth_users berdasarkan email / username (ECI) / phone
        $authUser = AuthUser::where(function ($q) use ($identifier) {
                $q->where('email', $identifier)
                  ->orWhere('username', $identifier)
                  ->orWhere('phone', $identifier);
            })
            ->where('is_active', true)
            ->first();

        // Cek user ada dan password benar
        if (!$authUser || !Hash::check($request->password, $authUser->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // ============================================================
        // PENGECEKAN ROLE — hanya employee yang boleh masuk
        // ============================================================
        if (!$authUser->isEmployee()) {
            Log::channel('daily')->warning('Non-employee login attempt on mobile employee API', [
                'auth_user_id' => $authUser->id,
                'user_type'    => $authUser->user_type,
                'ip'           => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Access denied. This application is restricted to employees only.',
                'code'    => 'NOT_EMPLOYEE',
            ], 403);
        }

        // Ambil data employee beserta role
        $employee = DB::table('employee as e')
            ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
            ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
            ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
            ->where('e.employee_id', $authUser->employee_id)
            ->select(
                'e.employee_id',
                'e.eci',
                'e.is_active',
                DB::raw("CONCAT(eb.first_name, ' ', COALESCE(eb.last_name, '')) as full_name"),
                'ea.email_personal as email_personal',
                'ea.cell_phone as phone_number',
                'eb.position',
                'eb.employee_subgroup as department',
                'r.id as role_id',
                'r.name as role_name'
            )
            ->first();

        if (!$employee || !$employee->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Hubungi administrator.',
                'code'    => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        // Hapus token lama agar tidak menumpuk
        $authUser->tokens()->delete();

        // Buat access token — berlaku 15 menit
        $accessToken = $authUser->createToken(
            'mobile-employee-access',
            ['*'],
            now()->addMinutes(15)
        );

        // Buat refresh token — berlaku 7 hari
        $refreshToken = $authUser->createToken(
            'mobile-employee-refresh',
            ['refresh'],
            now()->addDays(7)
        );

        // Update waktu login terakhir
        DB::table('auth_users')
            ->where('id', $authUser->id)
            ->update(['last_login_at' => now()]);

        Log::channel('daily')->info('Mobile employee login successful', [
            'auth_user_id' => $authUser->id,
            'employee_id'  => $employee->employee_id,
            'eci'          => $employee->eci,
            'role'         => $employee->role_name,
            'ip'           => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'data'    => [
                'access_token'  => $accessToken->plainTextToken,
                'refresh_token' => $refreshToken->plainTextToken,
                'token_type'    => 'Bearer',
                'expires_in'    => 15 * 60, // dalam detik
                'user'          => [
                    'id'         => $employee->employee_id,
                    'eci'        => $employee->eci,
                    'name'       => $employee->full_name,
                    'email'      => $authUser->email,
                    'phone'      => $employee->phone_number,
                    'position'   => $employee->position,
                    'department' => $employee->department,
                    'role'       => [
                        'id'   => $employee->role_id,
                        'name' => $employee->role_name,
                    ],
                ],
            ],
        ], 200);
    }

    /**
     * Refresh Token
     *
     * Terima refresh_token di body, validasi, lalu terbitkan
     * access_token baru dan refresh_token baru (token rotation).
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        // Format Sanctum token: "{id}|{plaintext}"
        $parts = explode('|', $request->refresh_token, 2);

        if (count($parts) !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'Format refresh token tidak valid.',
            ], 401);
        }

        [$tokenId, $rawToken] = $parts;

        $tokenRecord = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->first();

        // Verifikasi hash token
        if (!$tokenRecord || !hash_equals($tokenRecord->token, hash('sha256', $rawToken))) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token tidak valid.',
            ], 401);
        }

        // Pastikan ini benar-benar refresh token
        $abilities = json_decode($tokenRecord->abilities, true) ?? [];
        if (!in_array('refresh', $abilities)) {
            return response()->json([
                'success' => false,
                'message' => 'Token yang dikirim bukan refresh token.',
            ], 401);
        }

        // Cek apakah refresh token sudah expired
        if ($tokenRecord->expires_at && now()->isAfter($tokenRecord->expires_at)) {
            DB::table('personal_access_tokens')->where('id', $tokenId)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your refresh token has expired. Please log in again.',
                'code'    => 'REFRESH_TOKEN_EXPIRED',
            ], 401);
        }

        // Load user
        $authUser = AuthUser::find($tokenRecord->tokenable_id);

        if (!$authUser || !$authUser->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak ditemukan atau tidak aktif.',
            ], 403);
        }

        if (!$authUser->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Your account does not have employee privileges.',
                'code'    => 'NOT_EMPLOYEE',
            ], 403);
        }

        // Hapus semua token lama (token rotation untuk keamanan)
        $authUser->tokens()->delete();

        // Terbitkan token baru
        $accessToken = $authUser->createToken(
            'mobile-employee-access',
            ['*'],
            now()->addMinutes(15)
        );

        $refreshToken = $authUser->createToken(
            'mobile-employee-refresh',
            ['refresh'],
            now()->addDays(7)
        );

        Log::channel('daily')->info('Mobile employee token refreshed', [
            'auth_user_id' => $authUser->id,
            'ip'           => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token berhasil diperbarui.',
            'data'    => [
                'access_token'  => $accessToken->plainTextToken,
                'refresh_token' => $refreshToken->plainTextToken,
                'token_type'    => 'Bearer',
                'expires_in'    => 15 * 60,
            ],
        ], 200);
    }

    /**
     * Logout — hapus semua token employee yang sedang login
     */
    public function logout(Request $request)
    {
        $authUser = $request->user();
        $authUser->tokens()->delete();

        Log::channel('daily')->info('Mobile employee logout', [
            'auth_user_id' => $authUser->id,
            'ip'           => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil.',
        ], 200);
    }

    /**
     * Me — data employee yang sedang login
     */
    public function me(Request $request)
    {
        $authUser = $request->user();

        $employee = DB::table('employee as e')
            ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
            ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
            ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
            ->where('e.employee_id', $authUser->employee_id)
            ->select(
                'e.employee_id',
                'e.eci',
                DB::raw("CONCAT(eb.first_name, ' ', COALESCE(eb.last_name, '')) as full_name"),
                'ea.email_personal as email_personal',
                'ea.cell_phone as phone_number',
                'eb.position',
                'eb.employee_subgroup as department',
                'r.id as role_id',
                'r.name as role_name'
            )
            ->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Data employee tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $employee->employee_id,
                'eci'        => $employee->eci,
                'name'       => $employee->full_name,
                'email'      => $authUser->email,
                'phone'      => $employee->phone_number,
                'position'   => $employee->position,
                'department' => $employee->department,
                'role'       => [
                    'id'   => $employee->role_id,
                    'name' => $employee->role_name,
                ],
            ],
        ], 200);
    }
}
