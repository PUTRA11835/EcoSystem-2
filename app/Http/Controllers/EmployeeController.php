<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;
use App\Models\EmployeeBasicData;

class EmployeeController extends Controller
{
    /**
     * Get current user's ECI
     */
    private function getCurrentUserECI()
    {
        // Cek dari Auth user (jika ada relasi)
        if (Auth::check() && Auth::user()->eci) {
            return Auth::user()->eci;
        }
        
        // Cek dari session
        if (session()->has('user') && isset(session('user')['eci'])) {
            return session('user')['eci'];
        }
        
        // Fallback ke System jika tidak ada
        return 'System';
    }

    /**
     * Display employee list page
     */
    public function index()
    {
        try {
            $user = session('user');
            
            Log::info('=== EMPLOYEE INDEX PAGE ACCESSED ===', [
                'user_id' => $user['id'] ?? null,
                'user_type' => $user['type'] ?? null,
                'user_name' => $user['name'] ?? null,
                'user_eci' => $user['eci'] ?? null
            ]);

            return view('master.employee.index', [
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR LOADING EMPLOYEE INDEX PAGE ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('dashboard')->withErrors([
                'message' => 'Failed to load employee page'
            ]);
        }
    }

    /**
     * Display single employee detail page
     */
    public function show($id)
    {
        try {
            $user = session('user');
            
            Log::info('=== EMPLOYEE DETAIL PAGE ACCESSED ===', [
                'employee_id' => $id,
                'user_id' => $user['id'] ?? null,
                'user_type' => $user['type'] ?? null,
                'user_eci' => $user['eci'] ?? null
            ]);

            // Get employee data dengan struktur tabel baru
            $employee = DB::table('employee as e')
                ->leftJoin('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                ->where('e.employee_id', $id)
                ->select(
                    'e.employee_id as id',
                    'e.eci',
                    'e.is_active',
                    // Basic Data
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
                    // Address
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
                Log::warning('=== EMPLOYEE NOT FOUND ===', [
                    'employee_id' => $id
                ]);

                return redirect()->route('master')->withErrors([
                    'message' => 'Employee not found'
                ]);
            }

            Log::info('=== EMPLOYEE DATA LOADED SUCCESSFULLY ===', [
                'employee_id' => $id,
                'eci' => $employee->eci ?? null
            ]);

            return view('master.employee.show', [
                'employee' => $employee,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR LOADING EMPLOYEE DETAIL PAGE ===', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('master.employee.index')->withErrors([
                'message' => 'Failed to load employee details'
            ]);
        }
    }

    /**
     * Get all employees (API)
     */
    public function getData(Request $request)
    {
        try {
            Log::info('=== API: FETCHING EMPLOYEES ===', [
                'filters' => $request->all(),
                'user_ip' => $request->ip()
            ]);

            $query = DB::table('employee as e')
                ->leftJoin('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                ->select(
                    'e.employee_id as id',
                    'e.eci',
                    'e.is_active',
                    'eb.title',
                    'eb.first_name',
                    'eb.last_name',
                    'eb.nick_name',
                    'eb.gender',
                    'eb.birth_date',
                    'eb.position',
                    'eb.employee_subgroup',
                    'eb.division',
                    'eb.department',
                    'eb.since_date',
                    'eb.block',
                    'eb.deletion_flag'
                );

            // Apply filters berdasarkan status
            if ($request->has('status') && $request->status !== '') {
                switch ($request->status) {
                    case 'active':
                        $query->where('eb.block', false)
                              ->where('eb.deletion_flag', false);
                        break;
                    case 'blocked':
                        $query->where('eb.block', true);
                        break;
                    case 'deleted':
                        $query->where('eb.deletion_flag', true);
                        break;
                }
                Log::info('Filter applied: status', ['status' => $request->status]);
            }

            // Filter by employee (ECI or name)
            if ($request->has('employee') && $request->employee !== '') {
                $search = $request->employee;
                $query->where(function($q) use ($search) {
                    $q->where('e.eci', 'like', "%{$search}%")
                      ->orWhere('eb.first_name', 'like', "%{$search}%")
                      ->orWhere('eb.last_name', 'like', "%{$search}%")
                      ->orWhere('eb.search_term_1', 'like', "%{$search}%")
                      ->orWhere('eb.search_term_2', 'like', "%{$search}%");
                });
                Log::info('Filter applied: employee', ['search' => $search]);
            }

            // Filter by department
            if ($request->has('department') && $request->department !== '') {
                $query->where('eb.department', 'like', "%{$request->department}%");
                Log::info('Filter applied: department', ['department' => $request->department]);
            }

            // Global search
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('e.eci', 'like', "%{$search}%")
                      ->orWhere('eb.first_name', 'like', "%{$search}%")
                      ->orWhere('eb.last_name', 'like', "%{$search}%")
                      ->orWhere('eb.position', 'like', "%{$search}%")
                      ->orWhere('eb.division', 'like', "%{$search}%")
                      ->orWhere('eb.department', 'like', "%{$search}%")
                      ->orWhere('eb.employee_subgroup', 'like', "%{$search}%");
                });
                Log::info('Global search applied', ['search' => $search]);
            }

            $employees = $query->orderBy('e.employee_id', 'desc')->get();

            // Transform status
            $employees = $employees->map(function($emp) {
                // Tentukan status berdasarkan block dan deletion_flag
                if ($emp->deletion_flag) {
                    $emp->status = 'deleted';
                } elseif ($emp->block) {
                    $emp->status = 'blocked';
                } else {
                    $emp->status = 'active';
                }
                return $emp;
            });

            Log::info('=== API: EMPLOYEES FETCHED SUCCESSFULLY ===', [
                'count' => $employees->count(),
                'filters_applied' => $request->all()
            ]);

            return response()->json([
                'success' => true,
                'data' => $employees,
                'count' => $employees->count()
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING EMPLOYEES ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employees: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single employee (API)
     */
    public function getDetail($id)
    {
        try {
            Log::info('=== API: FETCHING EMPLOYEE DETAIL ===', [
                'employee_id' => $id
            ]);

            $employee = DB::table('employee as e')
                ->leftJoin('employee_basic_data as eb', 'e.employee_id', '=', 'eb.employee_id')
                ->leftJoin('employee_address as ea', 'e.employee_id', '=', 'ea.employee_id')
                ->where('e.employee_id', $id)
                ->select(
                    'e.employee_id',
                    'e.eci',
                    'e.is_active',
                    // Basic Data
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
                    // Address
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
                    'ea.email_personal',
                    'ea.email_work'
                )
                ->first();

            if (!$employee) {
                Log::warning('=== API: EMPLOYEE NOT FOUND ===', [
                    'employee_id' => $id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            Log::info('=== API: EMPLOYEE DETAIL FETCHED SUCCESSFULLY ===', [
                'employee_id' => $id,
                'eci' => $employee->eci
            ]);

            return response()->json([
                'success' => true,
                'data' => $employee
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING EMPLOYEE DETAIL ===', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new employee (API)
     */
    public function store(Request $request)
    {
        // Get current user's ECI
        $currentUserECI = $this->getCurrentUserECI();

        Log::info('=== API: CREATING NEW EMPLOYEE ===', [
            'data' => $request->except(['password', 'password_confirmation']),
            'user_ip' => $request->ip(),
            'created_by_eci' => $currentUserECI
        ]);

        $validator = Validator::make($request->all(), [
            'eci' => 'required|unique:employee,eci|unique:auth_users,username|max:50',
            'password' => 'required|string|min:6|confirmed',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role' => 'nullable|integer',
            'gender' => 'nullable|in:Male,Female',
            'religion' => 'nullable|in:Islam,Christian,Catholic,Hindu,Buddhist,Confucian',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widow/Widower',
            'email_work' => 'required|email|unique:auth_users,email|max:255',
            'cell_phone' => 'nullable|string|max:50',
        ], [
            'eci.required' => 'Employee ID is required',
            'eci.unique' => 'Employee ID already exists',
            'first_name.required' => 'First name is required',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 6 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'email_work.required' => 'Email kerja wajib diisi agar dapat menerima link aktivasi akun',
            'email_work.email' => 'Format email kerja tidak valid',
            'email_work.unique' => 'Email kerja sudah digunakan oleh akun lain',
        ]);

        if ($validator->fails()) {
            Log::warning('=== API: VALIDATION FAILED ===', [
                'errors' => $validator->errors()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create employee (password disimpan di auth_users, bukan di tabel employee)
            $employeeId = DB::table('employee')->insertGetId([
                'eci'       => $request->eci,
                'role_id'   => $request->role ?? 2,
                'is_active' => 1,
            ]);

            Log::info('Employee record created', [
                'employee_id' => $employeeId,
                'eci' => $request->eci,
                'role_id' => $request->role ?? 2
            ]);

            // Create basic data
            DB::table('employee_basic_data')->insert([
                'employee_id' => $employeeId,
                'title' => $request->title,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'search_term_1' => strtoupper($request->first_name),
                'search_term_2' => $request->last_name ? strtoupper($request->last_name) : null,
                'nick_name' => $request->nick_name,
                'gender' => $request->gender,
                'religion' => $request->religion,
                'marital_status' => $request->marital_status,
                'birth_date' => $request->birth_date ?: null,
                'birth_place' => $request->birth_place,
                'since_date' => $request->since_date,
                'personnel_area' => $request->personnel_area,
                'personnel_subarea' => $request->personnel_subarea,
                'employee_group' => $request->employee_group,
                'employee_subgroup' => $request->employee_subgroup,
                'position' => $request->position,
                'division' => $request->division,
                'department' => $request->department,
                'direct_supervision' => $request->direct_supervision,
                'manager' => $request->manager,
                'authorization_group' => $request->authorization_group,
                'created_by' => $currentUserECI,  // ✅ Gunakan ECI
                'created_on' => now(),
                'block' => false,
                'deletion_flag' => false,
            ]);

            Log::info('Employee basic data created', [
                'created_by' => $currentUserECI
            ]);

            // Create address if any address field is provided
            if ($request->filled(['street']) || $request->filled(['city']) || $request->filled(['country'])) {
                DB::table('employee_address')->insert([
                    'employee_id' => $employeeId,
                    'address_type' => 'primary',
                    'country' => $request->country,
                    'region' => $request->region,
                    'city' => $request->city,
                    'district' => $request->district,
                    'rural_urban_village' => $request->rural_urban_village,
                    'street' => $request->street,
                    'house_number' => $request->house_number,
                    'postal_code' => $request->postal_code,
                    'language' => $request->language,
                    'cell_phone' => $request->cell_phone,
                    'telephone' => $request->telephone,
                    'email_personal' => $request->email_personal,
                    'email_work' => $request->email_work,
                    'is_primary' => true,
                ]);

                Log::info('Employee address created');
            }

            // Buat akun auth_users untuk login
            // is_already_cp = false → employee baru juga wajib verifikasi email & ganti password sebelum login
            DB::table('auth_users')->insert([
                'employee_id'   => $employeeId,
                'customer_id'   => null,
                'username'      => $request->eci,
                'email'         => $request->email_work ?: null,
                'phone'         => $request->cell_phone ?: null,
                'password'      => Hash::make($request->password),
                'is_active'     => true,
                'is_already_cp' => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::commit();

            Log::info('=== API: EMPLOYEE CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'eci' => $request->eci,
                'created_by' => $currentUserECI
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee created successfully',
                'data' => [
                    'employee_id' => $employeeId
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR CREATING EMPLOYEE ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->except(['password', 'password_confirmation'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update employee (API)
     */
    public function update(Request $request, $id)
    {
        // Get current user's ECI
        $currentUserECI = $this->getCurrentUserECI();

        Log::info('=== API: UPDATING EMPLOYEE ===', [
            'employee_id' => $id,
            'data' => $request->except(['password']),
            'user_ip' => $request->ip(),
            'updated_by_eci' => $currentUserECI
        ]);

        $validator = Validator::make($request->all(), [
            'eci' => 'required|max:50|unique:employee,eci,' . $id . ',employee_id',
            'first_name' => 'required|string|max:255',
            'gender' => 'nullable|in:Male,Female',
            'religion' => 'nullable|in:Islam,Christian,Catholic,Hindu,Buddhist,Confucian',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widow/Widower',
        ]);

        if ($validator->fails()) {
            Log::warning('=== API: VALIDATION FAILED ===', [
                'employee_id' => $id,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Check if employee exists
            $employeeExists = DB::table('employee')->where('employee_id', $id)->exists();
            
            if (!$employeeExists) {
                Log::warning('=== API: EMPLOYEE NOT FOUND FOR UPDATE ===', [
                    'employee_id' => $id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Update employee
            DB::table('employee')
                ->where('employee_id', $id)
                ->update([
                    'eci' => $request->eci,
                ]);

            Log::info('Employee record updated');

            // Update basic data
            DB::table('employee_basic_data')
                ->updateOrInsert(
                    ['employee_id' => $id],
                    [
                        'title' => $request->title,
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'search_term_1' => strtoupper($request->first_name),
                        'search_term_2' => $request->last_name ? strtoupper($request->last_name) : null,
                        'nick_name' => $request->nick_name,
                        'gender' => $request->gender,
                        'religion' => $request->religion,
                        'marital_status' => $request->marital_status,
                        'birth_date' => $request->birth_date ?: null,
                        'birth_place' => $request->birth_place,
                        'since_date' => $request->since_date,
                        'personnel_area' => $request->personnel_area,
                        'personnel_subarea' => $request->personnel_subarea,
                        'employee_group' => $request->employee_group,
                        'employee_subgroup' => $request->employee_subgroup,
                        'position' => $request->position,
                        'division' => $request->division,
                        'department' => $request->department,
                        'direct_supervision' => $request->direct_supervision,
                        'manager' => $request->manager,
                        'authorization_group' => $request->authorization_group,
                        'last_changed_by' => $currentUserECI,  // ✅ Gunakan ECI
                        'last_changed_on' => now(),
                    ]
                );

            Log::info('Employee basic data updated', [
                'last_changed_by' => $currentUserECI
            ]);

            // Update address
            if ($request->filled(['street', 'city', 'country'])) {
                DB::table('employee_address')
                    ->updateOrInsert(
                        ['employee_id' => $id, 'is_primary' => true],
                        [
                            'address_type' => 'primary',
                            'country' => $request->country,
                            'region' => $request->region,
                            'city' => $request->city,
                            'district' => $request->district,
                            'rural_urban_village' => $request->rural_urban_village,
                            'street' => $request->street,
                            'house_number' => $request->house_number,
                            'postal_code' => $request->postal_code,
                            'language' => $request->language,
                            'cell_phone' => $request->cell_phone,
                            'telephone' => $request->telephone,
                            'email_personal' => $request->email_personal,
                            'email_work' => $request->email_work,
                        ]
                    );

                Log::info('Employee address updated');
            }

            DB::commit();

            Log::info('=== API: EMPLOYEE UPDATED SUCCESSFULLY ===', [
                'employee_id' => $id,
                'eci' => $request->eci,
                'updated_by' => $currentUserECI
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR UPDATING EMPLOYEE ===', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employee (Permanent delete)
     */
    public function destroy($id)
    {
        Log::info('=== API: DELETING EMPLOYEE (PERMANENT) ===', [
            'employee_id' => $id,
            'user_ip' => request()->ip()
        ]);

        DB::beginTransaction();

        try {
            // Check if employee exists
            $employee = DB::table('employee')->where('employee_id', $id)->first();
            
            if (!$employee) {
                Log::warning('=== API: EMPLOYEE NOT FOUND FOR DELETION ===', [
                    'employee_id' => $id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found. The employee may have already been deleted.'
                ], 404);
            }

            $eci = $employee->eci;
            Log::info('Employee found, proceeding with permanent deletion', ['eci' => $eci]);

            // Delete related records first (foreign key constraints)
            
            // 1. Delete employee addresses
            $addressesDeleted = DB::table('employee_address')->where('employee_id', $id)->delete();
            Log::info('Employee addresses deleted', ['count' => $addressesDeleted]);

            // 2. Delete employee basic data
            $basicDataDeleted = DB::table('employee_basic_data')->where('employee_id', $id)->delete();
            Log::info('Employee basic data deleted', ['count' => $basicDataDeleted]);

            // 3. Delete employee identifications (if exists)
            $identificationsDeleted = DB::table('employee_identification')->where('employee_id', $id)->delete();
            Log::info('Employee identifications deleted', ['count' => $identificationsDeleted]);

            // 4. Delete employee families (if exists)
            $familiesDeleted = DB::table('employee_family')->where('employee_id', $id)->delete();
            Log::info('Employee families deleted', ['count' => $familiesDeleted]);

            // 5. Finally, delete employee record
            $employeeDeleted = DB::table('employee')->where('employee_id', $id)->delete();
            Log::info('Employee record deleted', ['count' => $employeeDeleted]);

            DB::commit();

            Log::info('=== API: EMPLOYEE PERMANENTLY DELETED SUCCESSFULLY ===', [
                'employee_id' => $id,
                'eci' => $eci
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee and all related data have been permanently deleted'
            ]);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            // Handle specific database errors
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            Log::error('=== API: DATABASE ERROR DELETING EMPLOYEE ===', [
                'employee_id' => $id,
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString()
            ]);

            // Check for foreign key constraint errors
            if (str_contains($errorMessage, 'foreign key constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete employee: This employee has related records that must be deleted first.'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Database error while deleting employee: ' . $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR DELETING EMPLOYEE ===', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting employee: ' . $e->getMessage()
            ], 500);
        }
    }
}