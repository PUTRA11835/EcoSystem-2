<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Show login page (untuk web)
     */
    public function showLogin()
    {
        Log::info('Showing login page', [
            'has_session' => session()->has('auth_token'),
            'ip' => request()->ip()
        ]);

        if (session()->has('auth_token')) {
            Log::info('User already has auth token, redirecting to dashboard');
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Login API (untuk AJAX/fetch)
     */
    public function login(Request $request)
    {
        Log::info('=== LOGIN REQUEST START ===');
        Log::info('API Login attempt', [
            'email' => $request->input('email'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'all_input' => $request->except(['password'])
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string|min:6',
            'remember' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->email;
        $password = $request->password;

        Log::info('Validation passed, searching for user', [
            'email' => $email
        ]);

        try {
            // Cek apakah login sebagai Employee
            Log::info('Checking employee table');
            
            $employee = DB::table('employee as e')
                ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                ->join('role as r', 'e.role_id', '=', 'r.role_id')
                ->where(function($query) use ($email) {
                    $query->where('e.eci', $email)
                          ->orWhere('ea.email_personal', $email)
                          ->orWhere('ea.email_work', $email);
                })
                ->select(
                    'e.employee_id',
                    'e.eci',
                    'e.password',
                    'e.is_active',
                    DB::raw("CONCAT(eb.first_name, ' ', COALESCE(eb.last_name, '')) as full_name"),
                    'ea.email_personal as email',
                    'ea.cell_phone as phone_number',
                    'eb.position',
                    'eb.employee_subgroup as department',
                    'r.role_id',
                    'r.name as role_name'
                )
                ->first();

            Log::info('Employee query result', [
                'found' => $employee ? 'yes' : 'no',
                'employee_id' => $employee->employee_id ?? null
            ]);

            if ($employee) {
                Log::info('Employee found, verifying password');
                
                if (!Hash::check($password, $employee->password)) {
                    Log::warning('Invalid password for employee', [
                        'email' => $email,
                        'employee_id' => $employee->employee_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Email atau password salah'
                    ], 401);
                }

                Log::info('Password verified for employee');

                if (!$employee->is_active) {
                    Log::warning('Inactive employee tried to login', [
                        'email' => $email,
                        'employee_id' => $employee->employee_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun Anda tidak aktif'
                    ], 403);
                }

                $token = base64_encode($employee->eci . '|' . time() . '|employee');

                Log::info('Employee login successful', [
                    'employee_id' => $employee->employee_id,
                    'email' => $employee->email,
                    'token_generated' => substr($token, 0, 20) . '...'
                ]);

                // PERBAIKAN: Simpan session dan regenerate
                session([
                    'auth_token' => $token,
                    'user' => [
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
                    ]
                ]);

                // PENTING: Regenerate session untuk security
                $request->session()->regenerate();
                
                // PENTING: Save session sebelum response
                $request->session()->save();

                Log::info('Session saved', [
                    'session_id' => session()->getId(),
                    'has_token' => session()->has('auth_token')
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Login berhasil',
                    'data' => [
                        'token' => $token,
                        'user' => [
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
                        ]
                    ]
                ], 200);
            }

            // Cek apakah login sebagai Customer
            // UPDATED: Tambah customer_code untuk login
            Log::info('Employee not found, checking customer table');
            
            $customer = DB::table('customer as c')
                ->join('customer_basic_data as cb', 'c.customer_id', '=', 'cb.customer_id')
                ->join('role as r', 'c.role_id', '=', 'r.role_id')
                ->where(function($query) use ($email) {
                    $query->where('c.customer_code', $email)
                          ->orWhere('c.email', $email);
                })
                ->select(
                    'c.customer_id',
                    'c.customer_code',
                    'c.email',
                    'c.password',
                    'c.is_active',
                    'cb.title',
                    'cb.name_1',
                    'cb.name_2',
                    'cb.customer_category',
                    'cb.customer_group',
                    'r.role_id',
                    'r.name as role_name'
                )
                ->first();

            Log::info('Customer query result', [
                'found' => $customer ? 'yes' : 'no',
                'customer_id' => $customer->customer_id ?? null
            ]);

            if ($customer) {
                Log::info('Customer found, verifying password');
                
                if (!Hash::check($password, $customer->password)) {
                    Log::warning('Invalid password for customer', [
                        'email' => $email,
                        'customer_id' => $customer->customer_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Email atau password salah'
                    ], 401);
                }

                Log::info('Password verified for customer');

                if (!$customer->is_active) {
                    Log::warning('Inactive customer tried to login', [
                        'email' => $email,
                        'customer_id' => $customer->customer_id
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Akun Anda tidak aktif'
                    ], 403);
                }

                $token = base64_encode($customer->customer_code . '|' . time() . '|customer');
                $companyName = trim($customer->title . ' ' . $customer->name_1 . ' ' . ($customer->name_2 ?? ''));

                Log::info('Customer login successful', [
                    'customer_id' => $customer->customer_id,
                    'customer_code' => $customer->customer_code,
                    'email' => $customer->email,
                    'token_generated' => substr($token, 0, 20) . '...'
                ]);

                session([
                    'auth_token' => $token,
                    'user' => [
                        'id' => $customer->customer_id,
                        'type' => 'customer',
                        'customer_code' => $customer->customer_code,
                        'company_name' => $companyName,
                        'email' => $customer->email,
                        'category' => $customer->customer_category,
                        'group' => $customer->customer_group,
                        'role' => [
                            'id' => $customer->role_id,
                            'name' => $customer->role_name
                        ]
                    ]
                ]);

                // PENTING: Regenerate dan save session
                $request->session()->regenerate();
                $request->session()->save();

                Log::info('Session saved', [
                    'session_id' => session()->getId(),
                    'has_token' => session()->has('auth_token')
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Login berhasil',
                    'data' => [
                        'token' => $token,
                        'user' => [
                            'id' => $customer->customer_id,
                            'type' => 'customer',
                            'customer_code' => $customer->customer_code,
                            'company_name' => $companyName,
                            'email' => $customer->email,
                            'category' => $customer->customer_category,
                            'group' => $customer->customer_group,
                            'role' => [
                                'id' => $customer->role_id,
                                'name' => $customer->role_name
                            ]
                        ]
                    ]
                ], 200);
            }

            Log::warning('User not found in both employee and customer tables', [
                'email' => $email
            ]);
            Log::info('=== LOGIN REQUEST END (USER NOT FOUND) ===');
            
            return response()->json([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 404);

        } catch (\Exception $e) {
            Log::error('=== LOGIN ERROR ===');
            Log::error('Login exception occurred', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'email' => $email ?? 'N/A',
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'
            ], 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Log::info('=== LOGOUT REQUEST ===', [
            'method' => $request->method(),
            'ip' => $request->ip(),
            'has_session' => session()->has('auth_token'),
            'user' => session('user'),
            'full_url' => $request->fullUrl()
        ]);

        // Jika GET request, redirect ke login
        if ($request->isMethod('get')) {
            Log::info('GET request to logout, redirecting to login');
            return redirect()->route('login')->with('info', 'Please use logout button');
        }
        
        // Proses logout untuk POST request
        session()->flush();
        session()->invalidate();
        $request->session()->regenerateToken();
        
        Log::info('Session invalidated, logout successful');
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ], 200);
        }

        return redirect()->route('login')->with('success', 'Anda telah logout');
    }

    /**
     * Get authenticated user info
     */
    public function me(Request $request)
    {
        Log::info('=== GET USER INFO REQUEST ===');
        
        try {
            $token = $request->bearerToken();
            
            Log::info('Checking bearer token', [
                'has_token' => $token ? 'yes' : 'no',
                'token_preview' => $token ? substr($token, 0, 20) . '...' : null
            ]);
            
            if (!$token) {
                Log::warning('No bearer token found in request');
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak ditemukan'
                ], 401);
            }

            $decoded = base64_decode($token);
            $parts = explode('|', $decoded);
            
            Log::info('Token decoded', [
                'parts_count' => count($parts),
                'identifier' => $parts[0] ?? 'N/A',
                'type' => $parts[2] ?? 'N/A'
            ]);
            
            if (count($parts) < 3) {
                Log::warning('Invalid token format', [
                    'parts_count' => count($parts)
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token tidak valid'
                ], 401);
            }

            $identifier = $parts[0];
            $type = $parts[2];

            Log::info('Fetching user data', [
                'identifier' => $identifier,
                'type' => $type
            ]);

            if ($type === 'employee') {
                $employee = DB::table('employee as e')
                    ->join('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                    ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                    ->join('role as r', 'e.role_id', '=', 'r.role_id')
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
                        'r.role_id',
                        'r.name as role_name'
                    )
                    ->first();

                Log::info('Employee lookup result', [
                    'found' => $employee ? 'yes' : 'no',
                    'employee_id' => $employee->employee_id ?? null
                ]);

                if (!$employee) {
                    Log::warning('Employee not found', [
                        'identifier' => $identifier
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'User tidak ditemukan'
                    ], 404);
                }

                Log::info('Employee data retrieved successfully');

                return response()->json([
                    'success' => true,
                    'data' => [
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
                    ]
                ], 200);
            } else {
                // UPDATED: Gunakan customer_code untuk identifier
                $customer = DB::table('customer as c')
                    ->join('customer_basic_data as cb', 'c.customer_id', '=', 'cb.customer_id')
                    ->join('role as r', 'c.role_id', '=', 'r.role_id')
                    ->where('c.customer_code', $identifier)
                    ->select(
                        'c.customer_id',
                        'c.customer_code',
                        'c.email',
                        'c.is_active',
                        'cb.title',
                        'cb.name_1',
                        'cb.name_2',
                        'cb.customer_category',
                        'cb.customer_group',
                        'r.role_id',
                        'r.name as role_name'
                    )
                    ->first();

                Log::info('Customer lookup result', [
                    'found' => $customer ? 'yes' : 'no',
                    'customer_id' => $customer->customer_id ?? null
                ]);

                if (!$customer) {
                    Log::warning('Customer not found', [
                        'identifier' => $identifier
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'User tidak ditemukan'
                    ], 404);
                }

                $companyName = trim($customer->title . ' ' . $customer->name_1 . ' ' . ($customer->name_2 ?? ''));

                Log::info('Customer data retrieved successfully');

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $customer->customer_id,
                        'type' => 'customer',
                        'customer_code' => $customer->customer_code,
                        'company_name' => $companyName,
                        'email' => $customer->email,
                        'category' => $customer->customer_category,
                        'group' => $customer->customer_group,
                        'role' => [
                            'id' => $customer->role_id,
                            'name' => $customer->role_name
                        ]
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('=== GET USER INFO ERROR ===');
            Log::error('Exception in me() method', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan'
            ], 500);
        }
    }
}