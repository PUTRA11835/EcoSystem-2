<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Controllers\PasswordSetupController;
use Exception;

class AuthController extends Controller
{
    /** Nama cookie remember-me yang dikirim ke browser */
    public const REMEMBER_COOKIE = 'remember_ecosystem';

    /** Durasi remember-me dalam hari */
    private const REMEMBER_DAYS = 30;

    // =========================================================================
    // HELPER: Bangun session data dari employee_id
    // =========================================================================

    /**
     * Ambil data employee dari DB dan kembalikan array untuk disimpan ke session.
     * Dipakai saat login maupun saat auto-restore session via remember-me cookie.
     *
     * @return array|null  null jika employee tidak ditemukan atau tidak aktif
     */
    public static function buildEmployeeSessionData(int $employeeId): ?array
    {
        $employee = DB::table('employee as e')
            ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
            ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
            ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
            ->where('e.employee_id', $employeeId)
            ->select(
                'e.employee_id',
                'e.eci',
                'e.is_active',
                DB::raw("CONCAT(eb.first_name, ' ', COALESCE(eb.last_name, '')) as full_name"),
                'eb.nick_name',
                'ea.email_personal as email',
                'ea.cell_phone as phone_number',
                'eb.position',
                'eb.employee_subgroup as department',
                'r.id as role_id',
                'r.name as role_name'
            )
            ->first();

        if (!$employee || !$employee->is_active) {
            return null;
        }

        // Ambil email dari auth_users (bukan employee_address) agar konsisten
        $authEmail = DB::table('auth_users')
            ->where('employee_id', $employeeId)
            ->value('email');

        $tokenData = $employee->eci . '|' . time() . '|employee';
        $token     = base64_encode($tokenData);

        return [
            'token'    => $token,
            'userData' => [
                'id'         => (int) $employee->employee_id,
                'type'       => 'employee',
                'eci'        => $employee->eci,
                'name'       => $employee->full_name,
                'nick_name'  => $employee->nick_name ?: explode(' ', trim($employee->full_name))[0],
                'email'      => $authEmail ?? $employee->email,
                'phone'      => $employee->phone_number,
                'position'   => $employee->position,
                'department' => $employee->department,
                'role'       => [
                    'id'   => (int) $employee->role_id,
                    'name' => $employee->role_name,
                ],
            ],
        ];
    }

    /**
     * Show login page (untuk web)
     */
    public function showLogin()
    {
        try {
            $sessionId = session()->getId();
            $hasToken = session()->has('auth_token');
            
            Log::channel('daily')->info('=== SHOW LOGIN PAGE ===', [
                'timestamp' => now()->toDateTimeString(),
                'session_id' => $sessionId,
                'has_session' => $hasToken,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'referer' => request()->header('referer')
            ]);

            if ($hasToken) {
                Log::channel('daily')->info('User already authenticated, redirecting to dashboard', [
                    'session_id' => $sessionId,
                ]);
                return redirect()->route('dashboard');
            }

            return view('auth.login');
            
        } catch (Exception $e) {
            Log::channel('daily')->error('Error in showLogin method', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('login')->with('error', 'A system error occurred');
        }
    }

    /**
     * Login API (untuk AJAX/fetch)
     */
    public function login(Request $request)
    {
        // Generate unique request ID untuk tracking
        $requestId = uniqid('login_', true);
        
        Log::channel('daily')->info('=== LOGIN REQUEST START ===', [
            'request_id' => $requestId,
            'timestamp' => now()->toDateTimeString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'session_id' => $request->session()->getId()
        ]);

        try {
            // Validasi input
            $validator = Validator::make($request->all(), [
                'email' => 'required|string',
                'password' => 'required|string|min:6',
                'remember' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                Log::channel('daily')->warning('Validation failed', [
                    'request_id' => $requestId,
                    'errors' => $validator->errors()->toArray(),
                    'input_email' => $request->input('email')
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = trim($request->email);
            $password = $request->password;
            $remember = $request->boolean('remember');

            Log::channel('daily')->info('Validation passed', [
                'request_id' => $requestId,
                'email' => $email,
                'password_length' => strlen($password),
                'remember' => $remember
            ]);

            // CEK AUTH_USERS TABLE (centralized auth)
            Log::channel('daily')->info('=== CHECKING AUTH_USERS TABLE ===', [
                'request_id' => $requestId,
                'search_email' => $email
            ]);

            // Cek auth_users: email, username (ECI / customer_code), atau phone
            $authUser = DB::table('auth_users')
                ->where(function($query) use ($email) {
                    $query->where('email', $email)
                          ->orWhere('username', $email)
                          ->orWhere('phone', $email);
                })
                ->where('is_active', true)
                ->first();

            Log::channel('daily')->info('Auth user query executed', [
                'request_id' => $requestId,
                'auth_user_found' => $authUser ? 'YES' : 'NO',
                'auth_user_id' => $authUser->id ?? null,
            ]);

            if (!$authUser) {
                Log::channel('daily')->warning('=== USER NOT FOUND IN AUTH_USERS ===', [
                    'request_id' => $requestId,
                    'email_searched' => $email,
                    'ip_address' => $request->ip(),
                    'timestamp' => now()->toDateTimeString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            // Verifikasi password dari auth_users
            $passwordValid = Hash::check($password, $authUser->password);

            Log::channel('daily')->info('Password verification result', [
                'request_id' => $requestId,
                'auth_user_id' => $authUser->id,
                'password_valid' => $passwordValid ? 'YES' : 'NO'
            ]);

            if (!$passwordValid) {
                Log::channel('daily')->warning('Invalid password attempt', [
                    'request_id' => $requestId,
                    'auth_user_id' => $authUser->id,
                    'email' => $email,
                    'ip_address' => $request->ip()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password',
                ], 401);
            }

            // Tentukan tipe user dari FK
            $isEmployee = !is_null($authUser->employee_id);
            $isCustomer = !is_null($authUser->customer_id);

            // Customer tidak memiliki akses ke EcoSystem — gunakan portal Jarvies
            if ($isCustomer) {
                Log::channel('daily')->warning('=== CUSTOMER LOGIN ATTEMPT BLOCKED ===', [
                    'request_id'    => $requestId,
                    'auth_user_id'  => $authUser->id,
                    'ip_address'    => $request->ip(),
                    'timestamp'     => now()->toDateTimeString(),
                ]);

                return response()->json([
                    'success'    => false,
                    'message'    => 'Access denied. Please use the Jarvies customer portal.',
                ], 403);
            }

            // Cek is_already_cp — user baru wajib verifikasi email & ganti password dulu
            if (!$authUser->is_already_cp) {
                if (empty($authUser->email)) {
                    Log::channel('daily')->warning('=== USER REQUIRES PASSWORD SETUP BUT HAS NO EMAIL ===', [
                        'request_id'   => $requestId,
                        'auth_user_id' => $authUser->id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Your account does not have a registered email. Please contact your administrator.',
                        'request_id' => $requestId,
                    ], 403);
                }

                PasswordSetupController::generateAndSendToken($authUser);

                [$local, $domain] = explode('@', $authUser->email, 2);
                $maskedEmail = substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $domain;

                Log::channel('daily')->info('=== USER REQUIRES PASSWORD SETUP ===', [
                    'request_id'   => $requestId,
                    'auth_user_id' => $authUser->id,
                    'is_employee'  => $isEmployee,
                ]);

                return response()->json([
                    'success'                 => true,
                    'require_password_change' => true,
                    'message'                 => 'Please check your email to set up your new password.',
                    'email'                   => $maskedEmail,
                ]);
            }

            if ($isEmployee) {
                // Bangun session data via helper (reusable oleh remember-me restore)
                $sessionData = self::buildEmployeeSessionData($authUser->employee_id);

                if (!$sessionData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is inactive',
                    ], 403);
                }

                $token    = $sessionData['token'];
                $userData = $sessionData['userData'];

                // Update last_login_at
                $rememberUpdates = ['last_login_at' => now()];

                // ── Remember Me ──────────────────────────────────────────────
                $responseCookies = [];
                if ($remember) {
                    $rawRememberToken = Str::random(60);
                    $rememberUpdates['remember_token'] = hash('sha256', $rawRememberToken);

                    // Cookie HttpOnly, SameSite=Lax, 30 hari
                    // secure: ikut SESSION_SECURE_COOKIE (.env) — false lokal, true production HTTPS
                    $responseCookies[] = cookie(
                        self::REMEMBER_COOKIE,
                        $rawRememberToken,
                        self::REMEMBER_DAYS * 24 * 60, // menit
                        '/',
                        null,
                        config('session.secure'),
                        true   // httpOnly
                    );
                } else {
                    // Hapus remember token jika tidak centang
                    $rememberUpdates['remember_token'] = null;
                }

                DB::table('auth_users')->where('id', $authUser->id)->update($rememberUpdates);

                $request->session()->put('auth_token', $token);
                $request->session()->put('user', $userData);
                $request->session()->regenerate();
                $request->session()->save();

                Log::channel('daily')->info('=== EMPLOYEE LOGIN SUCCESSFUL ===', [
                    'request_id'  => $requestId,
                    'employee_id' => $userData['id'],
                    'eci'         => $userData['eci'],
                    'remember_me' => $remember,
                    'ip_address'  => $request->ip(),
                    'timestamp'   => now()->toDateTimeString()
                ]);

                $response = response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data'    => [
                        'token' => $token,
                        'user'  => $userData
                    ],
                ], 200);

                foreach ($responseCookies as $cookie) {
                    $response->withCookie($cookie);
                }

                return $response;
            }

            // auth_user tanpa employee_id maupun customer_id
            Log::channel('daily')->warning('=== AUTH USER HAS NO LINKED ACCOUNT ===', [
                'request_id' => $requestId,
                'auth_user_id' => $authUser->id,
                'email_searched' => $email,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toDateTimeString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 404);

        } catch (Exception $e) {
            Log::channel('daily')->error('=== CRITICAL LOGIN ERROR ===', [
                'request_id' => $requestId,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace_summary' => substr($e->getTraceAsString(), 0, 500),
                'email' => $email ?? 'N/A',
                'ip_address' => $request->ip(),
                'timestamp' => now()->toDateTimeString()
            ]);

            // Log tambahan untuk debugging
            Log::channel('daily')->error('Exception details', [
                'request_id' => $requestId,
                'previous_exception' => $e->getPrevious() ? $e->getPrevious()->getMessage() : 'None',
                'exception_trace' => $e->getTrace()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'A system error occurred. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $requestId = uniqid('logout_', true);
        
        Log::channel('daily')->info('=== LOGOUT REQUEST ===', [
            'request_id' => $requestId,
            'timestamp' => now()->toDateTimeString(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'session_id' => $request->session()->getId(),
            'has_token' => $request->session()->has('auth_token'),
        ]);

        try {
            if ($request->isMethod('get')) {
                return redirect()->route('login')->with('info', 'Please use logout button');
            }

            // Proses logout
            $userData = $request->session()->get('user');

            // Hapus remember_token dari DB agar cookie lama tidak bisa restore session
            if (!empty($userData['id'])) {
                DB::table('auth_users')
                    ->where('employee_id', $userData['id'])
                    ->update(['remember_token' => null]);
            }

            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Log::channel('daily')->info('=== LOGOUT SUCCESSFUL ===', [
                'request_id' => $requestId,
                'employee_id' => $userData['id'] ?? null,
                'ip_address'  => $request->ip(),
                'timestamp'   => now()->toDateTimeString()
            ]);

            // Hapus remember-me cookie dari browser
            $expiredCookie = cookie(self::REMEMBER_COOKIE, '', -1);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logout successful',
                ], 200)->withCookie($expiredCookie);
            }

            return redirect()->route('login')
                ->with('success', 'You have been logged out')
                ->withCookie($expiredCookie);

        } catch (Exception $e) {
            Log::channel('daily')->error('Error during logout', [
                'request_id' => $requestId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
            ], 500);
        }
    }

    /**
     * Kembalikan data user yang sedang login.
     * Menggunakan session sebagai sumber data — lebih aman dari bearer token parsing.
     */
    public function me(Request $request)
    {
        try {
            $user = $request->session()->get('user');

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data'    => $user,
            ], 200);

        } catch (Exception $e) {
            Log::channel('daily')->error('Error in me()', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
            ], 500);
        }
    }
}

