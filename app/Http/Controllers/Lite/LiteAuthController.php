<?php

namespace App\Http\Controllers\Lite;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PasswordSetupController;
use App\Models\LoginActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LiteAuthController extends Controller
{
    /**
     * Login untuk Lite API.
     *
     * Logic identik dengan AuthController::login() pada aplikasi utama.
     * Mengembalikan Bearer token yang dapat digunakan untuk request berikutnya.
     *
     * POST /api/lite/auth/login
     */
    public function login(Request $request)
    {
        $requestId = uniqid('lite_login_', true);

        try {
            $validator = Validator::make($request->all(), [
                'email'    => 'required|string',
                'password' => 'required|string|min:6',
                'remember' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $email    = trim($request->email);
            $password = $request->password;

            // Cari user di auth_users: email, username (ECI), atau phone
            $authUser = DB::table('auth_users')
                ->where(function ($q) use ($email) {
                    $q->where('email', $email)
                      ->orWhere('username', $email)
                      ->orWhere('phone', $email);
                })
                ->where('is_active', true)
                ->first();

            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            if (!Hash::check($password, $authUser->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            // Customer tidak punya akses ke sistem ini
            if (!is_null($authUser->customer_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Please use the Jarvies customer portal.',
                ], 403);
            }

            // Akun belum selesai setup password
            if (!$authUser->is_already_cp) {
                if (empty($authUser->email)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account does not have a registered email. Please contact your administrator.',
                    ], 403);
                }

                PasswordSetupController::generateAndSendToken($authUser);

                [$local, $domain] = explode('@', $authUser->email, 2);
                $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;

                return response()->json([
                    'success'                 => true,
                    'require_password_change' => true,
                    'message'                 => 'Please check your email to set up your new password.',
                    'email'                   => $maskedEmail,
                ]);
            }

            if (is_null($authUser->employee_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 404);
            }

            // Bangun session/token data via helper yang sama dengan aplikasi utama
            $sessionData = AuthController::buildEmployeeSessionData($authUser->employee_id);

            if (!$sessionData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive',
                ], 403);
            }

            // Cek akses EcoSystem
            $hasSystemAccess = DB::table('employee_role_assignment as era')
                ->join('employee_role as er', 'era.role_id', '=', 'er.id')
                ->where('era.employee_id', $authUser->employee_id)
                ->where('er.name', 'User System Registered')
                ->exists();

            if (!$hasSystemAccess) {
                AuthController::recordActivity(
                    userId:     $authUser->employee_id,
                    roleId:     0,
                    userName:   $sessionData['userData']['name'] ?? $email,
                    userType:   'employee',
                    ip:         $request->ip(),
                    userAgent:  $request->userAgent() ?? '',
                    status:     'failed',
                    chModel:    $request->header('Sec-CH-UA-Model', ''),
                    chPlatform: $request->header('Sec-CH-UA-Platform', '')
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Your account does not have access to this system. Please contact your administrator.',
                ], 403);
            }

            $token    = $sessionData['token'];
            $userData = $sessionData['userData'];

            DB::table('auth_users')->where('id', $authUser->id)->update([
                'last_login_at' => now(),
            ]);

            // Simpan ke session (untuk web app yang mendukung cookie)
            $request->session()->put('auth_token', $token);
            $request->session()->put('user', $userData);
            $request->session()->regenerate();
            $request->session()->save();

            DB::table('sessions')
                ->where('id', $request->session()->getId())
                ->update(['user_id' => $authUser->id]);

            AuthController::recordActivity(
                userId:     $userData['id'],
                roleId:     $userData['role']['id'] ?? 0,
                userName:   $userData['name'] ?? $userData['eci'] ?? 'Unknown',
                userType:   'employee',
                ip:         $request->ip(),
                userAgent:  $request->userAgent() ?? '',
                status:     'success',
                chModel:    $request->header('Sec-CH-UA-Model', ''),
                chPlatform: $request->header('Sec-CH-UA-Platform', '')
            );

            Log::info('Lite API login successful', [
                'request_id'  => $requestId,
                'employee_id' => $userData['id'],
                'eci'         => $userData['eci'],
                'ip'          => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data'    => [
                    'token' => $token,
                    'user'  => $userData,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Lite API login error', [
                'request_id' => $requestId,
                'error'      => $e->getMessage(),
                'error_at'   => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout — hapus session dan catat aktivitas.
     *
     * POST /api/lite/auth/logout
     */
    public function logout(Request $request)
    {
        try {
            $userData = $request->session()->get('user')
                ?? $request->attributes->get('lite_user');

            if (!empty($userData['id'])) {
                AuthController::recordActivity(
                    userId:     $userData['id'],
                    roleId:     $userData['role']['id'] ?? 0,
                    userName:   $userData['name'] ?? 'Unknown',
                    userType:   'employee',
                    ip:         $request->ip(),
                    userAgent:  $request->userAgent() ?? '',
                    status:     'logout',
                    chModel:    $request->header('Sec-CH-UA-Model', ''),
                    chPlatform: $request->header('Sec-CH-UA-Platform', '')
                );

                DB::table('auth_users')
                    ->where('employee_id', $userData['id'])
                    ->update(['remember_token' => null]);
            }

            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
            ]);

        } catch (\Exception $e) {
            Log::error('Lite API logout error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
            ], 500);
        }
    }

    /**
     * Kembalikan data user yang sedang login.
     * Mendukung session maupun Bearer token.
     *
     * GET /api/lite/auth/me
     */
    public function me(Request $request)
    {
        try {
            // Prioritas: session → Bearer token (sudah di-set oleh middleware)
            $user = $request->session()->get('user')
                ?? $request->attributes->get('lite_user');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data'    => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile. Please try again.',
            ], 500);
        }
    }
}
