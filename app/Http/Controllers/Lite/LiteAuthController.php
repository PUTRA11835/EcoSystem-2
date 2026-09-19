<?php

namespace App\Http\Controllers\Lite;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PasswordSetupController;
use App\Models\LoginActivity;
use App\Models\SecurityEvent;
use App\Services\TwoFactorAuthService;
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

            // Brute-force guard — identical logic to AuthController::login().
            $loginSecurity = app(\App\Services\LoginSecurityService::class);

            if ($loginSecurity->isIpBlocked($request->ip())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please try again later.',
                ], 403);
            }

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
                AuthController::recordActivity(
                    userId:    0,
                    roleId:    0,
                    userName:  $email,
                    userType:  'employee',
                    ip:        $request->ip(),
                    userAgent: $request->userAgent() ?? '',
                    status:    'failed'
                );
                $loginSecurity->recordFailedAttempt(null, $request->ip(), $email);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            if ($loginSecurity->isAccountLocked($authUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please try again later.',
                ], 403);
            }

            if (!Hash::check($password, $authUser->password)) {
                $isEmployeeAttempt = !is_null($authUser->employee_id);
                AuthController::recordActivity(
                    userId:    $isEmployeeAttempt ? $authUser->employee_id : ($authUser->customer_id ?? 0),
                    roleId:    0,
                    userName:  $email,
                    userType:  $isEmployeeAttempt ? 'employee' : 'customer',
                    ip:        $request->ip(),
                    userAgent: $request->userAgent() ?? '',
                    status:    'failed'
                );
                $loginSecurity->recordFailedAttempt($authUser, $request->ip(), $email);

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

            // ── Two-factor authentication gate ──────────────────────────
            // Same placement/rationale as AuthController::login() — every
            // other gate has already run, and no session/DB mutation
            // happens yet if 2FA is required.
            if (TwoFactorAuthService::isEnabled($authUser)) {
                $twoFactorToken = TwoFactorAuthService::issueChallengeToken($authUser->id, false);

                return response()->json([
                    'success'          => true,
                    'requires_2fa'     => true,
                    'two_factor_token' => $twoFactorToken,
                ]);
            }

            return $this->finalizeLiteLogin($authUser, $sessionData, $request, $requestId);

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
     * Finishes a Lite login after every gate (and, if enabled, 2FA) has
     * passed — same DB/session writes as AuthController::finalizeEmployeeLogin(),
     * minus remember-me handling (Lite never sets that cookie). Called both
     * directly from login() and from verifyTwoFactor().
     */
    private function finalizeLiteLogin(object $authUser, array $sessionData, Request $request, string $requestId)
    {
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
    }

    /**
     * Completes a Lite login after a 2FA challenge. Same contract as
     * AuthController::verifyTwoFactor() (accepts a 6-digit TOTP code or an
     * "XXXX-XXXX" recovery code), reusing the same TwoFactorAuthService —
     * duplicated here rather than shared across controllers because the two
     * finalize tails differ (no remember-me in Lite).
     * POST /api/lite/auth/2fa/verify
     */
    public function verifyTwoFactor(Request $request)
    {
        $requestId = uniqid('lite_2fa_', true);

        $validator = Validator::make($request->all(), [
            'two_factor_token' => 'required|string',
            'code'              => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $challenge = TwoFactorAuthService::resolveChallengeToken($request->input('two_factor_token'));

        if (!$challenge) {
            return response()->json([
                'success' => false,
                'message' => 'This verification step has expired. Please log in again.',
            ], 422);
        }

        $authUserId = $challenge['auth_user_id'];

        $authUser = DB::table('auth_users')->where('id', $authUserId)->first();

        if (!$authUser || !$authUser->is_active || is_null($authUser->employee_id)) {
            return response()->json([
                'success' => false,
                'message' => 'This verification step is no longer valid. Please log in again.',
            ], 422);
        }

        $loginSecurity = app(\App\Services\LoginSecurityService::class);

        if ($loginSecurity->isAccountLocked($authUser) || TwoFactorAuthService::isChallengeLocked($authUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.',
            ], 403);
        }

        $code             = trim((string) $request->input('code'));
        $verified         = false;
        $usedRecoveryCode = false;

        if (preg_match('/^\d{6}$/', $code)) {
            $secret = TwoFactorAuthService::decryptSecret($authUser->two_factor_secret);

            if ($secret) {
                DB::transaction(function () use ($authUserId, $secret, $code, &$verified) {
                    $row    = DB::table('auth_users')->where('id', $authUserId)->lockForUpdate()->first();
                    $result = TwoFactorAuthService::verifyCode($secret, $code, $row->two_factor_last_used_at);

                    if ($result['valid']) {
                        DB::table('auth_users')->where('id', $authUserId)->update([
                            'two_factor_last_used_at' => $result['timestamp'],
                        ]);
                        $verified = true;
                    }
                });
            }
        } else {
            DB::transaction(function () use ($authUserId, $code, &$verified, &$usedRecoveryCode) {
                $row         = DB::table('auth_users')->where('id', $authUserId)->lockForUpdate()->first();
                $hashedCodes = $row->two_factor_recovery_codes ? json_decode($row->two_factor_recovery_codes, true) : [];

                $remaining = TwoFactorAuthService::findAndConsumeRecoveryCode($hashedCodes ?? [], $code);

                if ($remaining !== null) {
                    DB::table('auth_users')->where('id', $authUserId)->update([
                        'two_factor_recovery_codes' => json_encode($remaining),
                    ]);
                    $verified         = true;
                    $usedRecoveryCode = true;
                }
            });
        }

        if (!$verified) {
            $attempts = TwoFactorAuthService::recordFailedChallenge($authUserId);

            if (TwoFactorAuthService::isChallengeLocked($authUserId) && !$loginSecurity->isAccountLocked($authUser)) {
                $lockedUntil = now()->addMinutes((int) config('security_center.two_factor.lockout_minutes', 30));
                DB::table('auth_users')->where('id', $authUserId)->update(['locked_until' => $lockedUntil]);

                $event = SecurityEvent::record([
                    'event_type'         => 'two_factor_bypass_attempt',
                    'severity'           => 'critical',
                    'module'             => 'Auth',
                    'status'             => 'open',
                    'title'              => 'Repeated 2FA failures - account locked',
                    'description'        => "Auth user #{$authUserId} had {$attempts} failed 2FA verification attempts (via Lite API) and was auto-locked until {$lockedUntil->format('d M Y H:i')} - this account's password is already known to whoever is attempting this.",
                    'target_employee_id' => $authUser->employee_id,
                    'ip_address'         => $request->ip(),
                    'payload'            => ['attempts' => $attempts, 'source' => 'lite'],
                ]);

                if ($event) {
                    $loginSecurity->notifyAdmins($event, "Repeated 2FA failures on auth user #{$authUserId} (Lite API) - account locked");
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid code. Please try again.',
            ], 401);
        }

        TwoFactorAuthService::clearFailedChallenge($authUserId);

        if ($usedRecoveryCode) {
            $event = SecurityEvent::record([
                'event_type'         => 'two_factor_recovery_code_used',
                'severity'           => 'medium',
                'module'             => 'Auth',
                'status'             => 'open',
                'title'              => 'Recovery code used to log in',
                'description'        => "Auth user #{$authUserId} logged in via Lite API using a 2FA recovery code instead of an authenticator code - often a sign they lost access to their device.",
                'target_employee_id' => $authUser->employee_id,
                'ip_address'         => $request->ip(),
            ]);

            if ($event) {
                $loginSecurity->notifyAdmins($event, "Recovery code used for auth user #{$authUserId} (Lite API)");
            }
        }

        $sessionData = AuthController::buildEmployeeSessionData($authUser->employee_id);

        if (!$sessionData) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive',
            ], 403);
        }

        return $this->finalizeLiteLogin($authUser, $sessionData, $request, $requestId);
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
