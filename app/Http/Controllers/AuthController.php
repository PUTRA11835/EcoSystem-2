<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PasswordSetupController;
use Exception;

class AuthController extends Controller
{
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
                    'user_data' => session('user')
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
            // Log raw input untuk debugging
            Log::channel('daily')->debug('Raw request input', [
                'request_id' => $requestId,
                'all_input' => $request->all(),
                'only_email' => $request->input('email'),
                'has_password' => $request->has('password'),
                'password_length' => strlen($request->input('password') ?? '')
            ]);

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
                    'request_id' => $requestId
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
                    'request_id' => $requestId
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
                    'request_id' => $requestId
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
                    'request_id' => $requestId,
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
                    'request_id'              => $requestId,
                ]);
            }

            if ($isEmployee) {
                // Login sebagai Employee
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
                    return response()->json([
                        'success' => false,
                        'message' => 'Your account is inactive',
                        'request_id' => $requestId
                    ], 403);
                }

                // Update last_login_at
                DB::table('auth_users')->where('id', $authUser->id)->update(['last_login_at' => now()]);

                $tokenData = $employee->eci . '|' . time() . '|employee';
                $token = base64_encode($tokenData);

                $userData = [
                    'id'         => (int) $employee->employee_id,
                    'type'       => 'employee',
                    'eci'        => $employee->eci,
                    'name'       => $employee->full_name,
                    'nick_name'  => $employee->nick_name ?: explode(' ', $employee->full_name)[0],
                    'email'      => $authUser->email,
                    'phone'      => $employee->phone_number,
                    'position'   => $employee->position,
                    'department' => $employee->department,
                    'role' => [
                        'id'   => (int) $employee->role_id,
                        'name' => $employee->role_name
                    ]
                ];

                $request->session()->put('auth_token', $token);
                $request->session()->put('user', $userData);
                $request->session()->regenerate();
                $request->session()->save();

                Log::channel('daily')->info('=== EMPLOYEE LOGIN SUCCESSFUL ===', [
                    'request_id' => $requestId,
                    'employee_id' => $employee->employee_id,
                    'eci' => $employee->eci,
                    'ip_address' => $request->ip(),
                    'timestamp' => now()->toDateTimeString()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'token' => $token,
                        'user' => $userData
                    ],
                    'request_id' => $requestId
                ], 200);

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
                'request_id' => $requestId
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
                'request_id' => $requestId,
                'error_reference' => substr($requestId, -8)
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
            'user_data' => $request->session()->get('user'),
            'full_url' => $request->fullUrl()
        ]);

        try {
            if ($request->isMethod('get')) {
                Log::channel('daily')->info('GET request to logout, redirecting', [
                    'request_id' => $requestId
                ]);
                return redirect()->route('login')->with('info', 'Please use logout button');
            }
            
            // Proses logout
            $userData = $request->session()->get('user');
            
            $request->session()->flush();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            Log::channel('daily')->info('=== LOGOUT SUCCESSFUL ===', [
                'request_id' => $requestId,
                'user_data' => $userData,
                'ip_address' => $request->ip(),
                'timestamp' => now()->toDateTimeString()
            ]);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logout successful',
                    'request_id' => $requestId
                ], 200);
            }

            return redirect()->route('login')->with('success', 'You have been logged out');

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
                'request_id' => $requestId
            ], 500);
        }
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        $requestId = uniqid('me_', true);
        
        Log::channel('daily')->info('=== GET USER INFO REQUEST ===', [
            'request_id' => $requestId,
            'timestamp' => now()->toDateTimeString(),
            'ip_address' => $request->ip(),
            'session_id' => $request->session()->getId(),
            'has_session_token' => $request->session()->has('auth_token')
        ]);

        try {
            $token = $request->bearerToken();
            
            Log::channel('daily')->info('Bearer token check', [
                'request_id' => $requestId,
                'has_token' => $token ? 'YES' : 'NO',
                'token_preview' => $token ? substr($token, 0, 30) . '...' : null
            ]);
            
            if (!$token) {
                Log::channel('daily')->warning('No bearer token in request', [
                    'request_id' => $requestId,
                    'headers' => $request->headers->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Token not found',
                    'request_id' => $requestId
                ], 401);
            }

            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            Log::channel('daily')->info('Token decoded', [
                'request_id' => $requestId,
                'decoded_raw' => $decoded,
                'parts_count' => count($parts),
                'parts' => $parts
            ]);
            
            if (count($parts) < 3) {
                Log::channel('daily')->warning('Invalid token format', [
                    'request_id' => $requestId,
                    'parts_count' => count($parts),
                    'decoded_value' => $decoded
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token',
                    'request_id' => $requestId
                ], 401);
            }

            $identifier = $parts[0];
            $timestamp = $parts[1];
            $type = $parts[2];

            Log::channel('daily')->info('Token parsed successfully', [
                'request_id' => $requestId,
                'identifier' => $identifier,
                'timestamp' => $timestamp,
                'type' => $type,
                'age_seconds' => time() - $timestamp
            ]);

            if ($type === 'employee') {
                Log::channel('daily')->info('Fetching employee data', [
                    'request_id' => $requestId,
                    'eci' => $identifier
                ]);

                $employee = DB::table('employee as e')
                    ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                    ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                    ->leftJoin('employee_role as r', 'e.role_id', '=', 'r.id')
                    ->where('e.eci', $identifier)
                    ->select(
                        'e.employee_id',
                        'e.eci',
                        'e.is_active',
                        DB::raw("CONCAT(eb.first_name, ' ', COALESCE(eb.last_name, '')) as full_name"),
                        'ea.email_personal as email',
                        'ea.cell_phone as phone_number',
                        'eb.position',
                        'eb.employee_subgroup as department',
                        'r.id as role_id',
                        'r.name as role_name'
                    )
                    ->first();

                Log::channel('daily')->info('Employee lookup result', [
                    'request_id' => $requestId,
                    'found' => $employee ? 'YES' : 'NO',
                    'employee_id' => $employee->employee_id ?? null
                ]);

                if (!$employee) {
                    Log::channel('daily')->warning('Employee not found', [
                        'request_id' => $requestId,
                        'identifier' => $identifier
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found',
                        'request_id' => $requestId
                    ], 404);
                }

                $responseData = [
                    'id' => $employee->employee_id,
                    'type' => 'employee',
                    'eci' => $employee->eci,
                    'name' => $employee->full_name,
                    'email' => $employee->email,
                    'phone' => $employee->phone_number,
                    'position' => $employee->position,
                    'department' => $employee->department,
                    'role' => [
                        'id' => $employee->role_id,
                        'name' => $employee->role_name
                    ]
                ];

                Log::channel('daily')->info('Employee data retrieved successfully', [
                    'request_id' => $requestId,
                    'employee_id' => $employee->employee_id
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $responseData,
                    'request_id' => $requestId
                ], 200);
                
            }

        } catch (Exception $e) {
            Log::channel('daily')->error('=== ERROR IN ME METHOD ===', [
                'request_id' => $requestId,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace_summary' => substr($e->getTraceAsString(), 0, 500),
                'timestamp' => now()->toDateTimeString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred',
                'request_id' => $requestId
            ], 500);
        }
    }
}
