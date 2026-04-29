<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Employee;
use App\Models\EmployeeBasicData;

class EmployeeController extends Controller
{
    /**
     * Get current user's ECI
     */
    private function getCurrentUserECI()
    {
        return session('user.eci') ?? 'System';
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

            // Fetch roles for all employees via pivot table (isolated so missing table won't break employee list)
            $roleAssignments = collect();
            try {
                $employeeIds = $employees->pluck('id')->all();
                if (!empty($employeeIds)) {
                    $roleAssignments = DB::table('employee_role_assignment as era')
                        ->join('employee_role as er', 'era.role_id', '=', 'er.id')
                        ->whereIn('era.employee_id', $employeeIds)
                        ->select('era.employee_id', 'er.id as role_id', 'er.name as role_name')
                        ->get()
                        ->groupBy('employee_id');
                }
            } catch (\Exception $roleEx) {
                Log::warning('getData: gagal fetch role assignments, lanjut tanpa roles', [
                    'error' => $roleEx->getMessage(),
                ]);
            }

            // Transform status & attach roles
            $employees = $employees->map(function($emp) use ($roleAssignments) {
                if ($emp->deletion_flag) {
                    $emp->status = 'deleted';
                } elseif ($emp->block) {
                    $emp->status = 'blocked';
                } else {
                    $emp->status = 'active';
                }

                $emp->roles = isset($roleAssignments[$emp->id])
                    ? $roleAssignments[$emp->id]->map(fn($r) => ['id' => $r->role_id, 'name' => $r->role_name])->values()
                    : collect();

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
                'message' => 'Failed to fetch employees'
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
                'message' => 'Failed to fetch employee'
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
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create employee (password disimpan di auth_users, bukan di tabel employee)
            $defaultRoleId = $request->role ?? 2;
            $employeeId = DB::table('employee')->insertGetId([
                'eci'       => $request->eci,
                'role_id'   => $defaultRoleId,
                'is_active' => 1,
            ]);

            // Insert default role into pivot table
            DB::table('employee_role_assignment')->insertOrIgnore([
                'employee_id' => $employeeId,
                'role_id'     => $defaultRoleId,
                'created_at'  => now(),
                'updated_at'  => now(),
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
                'message' => 'Failed to create employee'
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
                'message' => $validator->errors()->first(),
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
                'message' => 'Failed to update employee'
            ], 500);
        }
    }

    /**
     * Get all employee roles (API)
     */
    /**
     * GET /api/employees/mentionable
     * Returns list of employees (id, name, role_name) for @mention autocomplete.
     * Excludes the current session user from the list.
     */
    public function getMentionable(Request $request)
    {
        try {
            $sessionUser = session('user');
            $currentId   = $sessionUser['id'] ?? 0;

            $q = $request->input('q', '');

            $employees = DB::table('employee as e')
                ->join('employee_role as r', 'e.role_id', '=', 'r.id')
                ->leftJoin('employee_basic_data as bd', 'e.employee_id', '=', 'bd.employee_id')
                ->where('e.employee_id', '!=', $currentId)
                ->where('e.is_active', true)
                ->where(function ($q2) {
                    $q2->whereNull('bd.block')->orWhere('bd.block', false);
                })
                ->where(function ($q2) {
                    $q2->whereNull('bd.deletion_flag')->orWhere('bd.deletion_flag', false);
                })
                ->when($q, function ($query) use ($q) {
                    $query->where(function ($inner) use ($q) {
                        $inner->where(DB::raw("CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))"), 'like', "%{$q}%")
                              ->orWhere('bd.nick_name', 'like', "%{$q}%");
                    });
                })
                ->select(
                    'e.employee_id as id',
                    DB::raw("CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,'')) as full_name"),
                    DB::raw("COALESCE(NULLIF(bd.nick_name,''), CONCAT(COALESCE(bd.first_name,''), ' ', COALESCE(bd.last_name,''))) as display_name"),
                    'r.name as role_name'
                )
                ->orderBy('bd.first_name')
                ->limit(20)
                ->get();

            // Also include roles for @role-level mentions
            $roles = DB::table('employee_role')
                ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%"))
                ->select('id', 'name', DB::raw("'role' as type"))
                ->orderBy('name')
                ->get();

            return response()->json([
                'success'   => true,
                'employees' => $employees,
                'roles'     => $roles,
            ]);
        } catch (\Exception $e) {
            Log::error('getMentionable error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve mentionable employees and roles.'], 500);
        }
    }

public function getRoles()
    {
        try {
            $roles = DB::table('employee_role')->select('id', 'name', 'description')->orderBy('id')->get();

            return response()->json([
                'success' => true,
                'data'    => $roles,
            ]);
        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING ROLES ===', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles',
            ], 500);
        }
    }

    /**
     * Change employee password (API)
     */
    public function changePassword(Request $request, $id)
    {
        $currentUserECI = $this->getCurrentUserECI();

        Log::info('=== API: CHANGE EMPLOYEE PASSWORD ===', [
            'employee_id'    => $id,
            'changed_by_eci' => $currentUserECI,
        ]);

        $validator = Validator::make($request->all(), [
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ], [
            'password.required'              => 'Password baru wajib diisi',
            'password.min'                   => 'Password minimal 6 karakter',
            'password.confirmed'             => 'Konfirmasi password tidak cocok',
            'password_confirmation.required' => 'Konfirmasi password wajib diisi',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $authUser = DB::table('auth_users')->where('employee_id', $id)->first();

            if (!$authUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Auth account for this employee not found',
                ], 404);
            }

            DB::table('auth_users')
                ->where('employee_id', $id)
                ->update([
                    'password'   => Hash::make($request->password),
                    'updated_at' => now(),
                ]);

            Log::info('=== API: EMPLOYEE PASSWORD CHANGED SUCCESSFULLY ===', [
                'employee_id'    => $id,
                'changed_by_eci' => $currentUserECI,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CHANGING PASSWORD ===', [
                'employee_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
            ], 500);
        }
    }

    /**
     * Sync employee roles — replaces all current roles with the given list (API)
     */
    public function changeRole(Request $request, $id)
    {
        $currentUserECI = $this->getCurrentUserECI();

        Log::info('=== API: SYNC EMPLOYEE ROLES ===', [
            'employee_id'    => $id,
            'role_ids'       => $request->role_ids,
            'changed_by_eci' => $currentUserECI,
        ]);

        $validator = Validator::make($request->all(), [
            'role_ids'   => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:employee_role,id',
        ], [
            'role_ids.required' => 'At least one role must be selected.',
            'role_ids.min'      => 'At least one role must be selected.',
            'role_ids.*.exists' => 'One or more selected roles are invalid.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $employee = DB::table('employee')->where('employee_id', $id)->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found',
                ], 404);
            }

            $roleIds = array_unique(array_map('intval', $request->role_ids));

            // Sync pivot table (delete old, insert new)
            DB::table('employee_role_assignment')->where('employee_id', $id)->delete();

            $pivotRows = array_map(fn($roleId) => [
                'employee_id' => (int) $id,
                'role_id'     => $roleId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $roleIds);

            DB::table('employee_role_assignment')->insert($pivotRows);

            // Keep legacy role_id in sync with the first role
            DB::table('employee')
                ->where('employee_id', $id)
                ->update(['role_id' => $roleIds[0]]);

            $roles = DB::table('employee_role')->whereIn('id', $roleIds)->select('id', 'name')->get();

            Log::info('=== API: EMPLOYEE ROLES SYNCED SUCCESSFULLY ===', [
                'employee_id'    => $id,
                'role_ids'       => $roleIds,
                'changed_by_eci' => $currentUserECI,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Roles updated successfully',
                'data'    => ['roles' => $roles],
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR SYNCING ROLES ===', [
                'employee_id' => $id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update roles',
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
                'message' => 'Database error while deleting employee'
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
                'message' => 'An unexpected error occurred while deleting employee'
            ], 500);
        }
    }
}