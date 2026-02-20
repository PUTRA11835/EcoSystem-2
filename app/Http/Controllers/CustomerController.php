<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /**
     * Get current user's identifier
     */
    private function getCurrentUserIdentifier()
    {
        // Try to get from session first (most reliable)
        if (session()->has('user')) {
            $user = session('user');
            if (isset($user['eci']) && !empty($user['eci'])) {
                return $user['eci'];
            } elseif (isset($user['email']) && !empty($user['email'])) {
                return $user['email'];
            } elseif (isset($user['name']) && !empty($user['name'])) {
                return $user['name'];
            }
        }
        
        // Fallback to Auth
        if (Auth::check()) {
            $authUser = Auth::user();
            if (isset($authUser->eci) && !empty($authUser->eci)) {
                return $authUser->eci;
            } elseif (isset($authUser->email) && !empty($authUser->email)) {
                return $authUser->email;
            }
        }
        
        return 'System';
    }

    // ==================== WEB METHODS (untuk render views) ====================

    /**
     * Display customer list page (WEB)
     */
    public function index()
    {
        try {
            $user = session('user');
            
            Log::info('=== WEB: CUSTOMER INDEX PAGE ACCESSED ===', [
                'user_id' => $user['id'] ?? null,
                'user_type' => $user['type'] ?? null,
                'user_name' => $user['name'] ?? null
            ]);

            return view('master.customer.index', [
                'user' => $user
            ]);

        } catch (\Exception $e) {
            Log::error('=== WEB: ERROR LOADING CUSTOMER INDEX PAGE ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('dashboard')->withErrors([
                'message' => 'Failed to load customer page'
            ]);
        }
    }

    /**
     * Display single customer detail page (WEB)
     */
    public function show($id)
    {
        try {
            // Get user from session
            $user = session('user');
            
            Log::info('=== WEB: CUSTOMER SHOW PAGE ACCESSED ===', [
                'customer_id' => $id,
                'user_data' => $user,
                'user_name' => $user['name'] ?? 'UNKNOWN',
                'url' => request()->url()
            ]);

            // Get customer with relationships using Model
            $customer = Customer::with([
                'basicData',
                'contact',
                'primaryAddress',
                'primaryBank'
            ])->find($id);

            if (!$customer) {
                Log::warning('Customer not found', ['id' => $id]);
                return redirect()->route('customer')->with('error', 'Customer not found');
            }

            Log::info('Customer data prepared, returning view', [
                'user_passed_to_view' => $user,
                'user_name_passed' => $user['name'] ?? 'NO NAME'
            ]);
            
            // Pass both customer and user to view
            return view('master.customer.show', compact('customer', 'user'));
            
        } catch (\Exception $e) {
            Log::error('=== WEB: ERROR SHOWING CUSTOMER DETAIL ===', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return redirect()->route('customer')->with('error', 'Failed to load customer details');
        }
    }

    // ==================== API METHODS ====================

    /**
     * Get paginated list of customers with filtering and search (API)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getData(Request $request)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMERS LIST ===', [
                'filters' => $request->all()
            ]);

            $perPage = $request->get('per_page', 15);
            
            $filters = [
                'search' => $request->get('search'),
                'customer' => $request->get('customer'), // Untuk compatibility dengan code lama
                'is_active' => $request->get('is_active'),
                'status' => $request->get('status'), // Untuk compatibility dengan code lama
                'customer_group' => $request->get('customer_group'),
                'customer_category' => $request->get('customer_category'),
                'active_only' => $request->get('active_only', false),
                'sort_field' => $request->get('sort_field', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
            ];

            // Use Model method for pagination
            $customers = Customer::getPaginated($perPage, $filters);

            // Transform data for backward compatibility with frontend
            $customersData = $customers->map(function($customer) {
                // Determine status
                if ($customer->basicData && $customer->basicData->deletion_flag) {
                    $status = 'deleted';
                } elseif ($customer->basicData && $customer->basicData->block) {
                    $status = 'blocked';
                } else {
                    $status = 'active';
                }

                return [
                    'id' => $customer->customer_id,
                    'email' => $customer->email,
                    'is_active' => $customer->is_active,
                    'name_1' => $customer->basicData->name_1 ?? null,
                    'customer_group' => $customer->basicData->customer_group ?? null,
                    'customer_category' => $customer->basicData->customer_category ?? null,
                    'industry_sector' => $customer->basicData->industry_sector ?? null,
                    'block' => $customer->basicData->block ?? false,
                    'deletion_flag' => $customer->basicData->deletion_flag ?? false,
                    'city' => $customer->primaryAddress->city ?? null,
                    'region' => $customer->primaryAddress->region ?? null,
                    'status' => $status,
                ];
            });

            Log::info('=== API: CUSTOMERS FETCHED SUCCESSFULLY ===', [
                'count' => $customers->count(),
                'total' => $customers->total()
            ]);

            return response()->json([
                'success' => true,
                'data' => $customersData,
                'count' => $customers->count(),
                'pagination' => [
                    'total' => $customers->total(),
                    'per_page' => $customers->perPage(),
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMERS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new customer (API) - Creates customer + basic data + address + contact
     */
    public function store(Request $request)
    {
        $currentUserIdentifier = $this->getCurrentUserIdentifier();

        Log::info('=== API: CREATING NEW CUSTOMER ===', [
            'data' => $request->except(['password', 'password_confirmation']),
            'created_by' => $currentUserIdentifier
        ]);

        $validator = Validator::make($request->all(), [
            'email'         => 'required|email|unique:customer,email|unique:auth_users,email|max:255',
            'password'      => 'required|string|min:6|confirmed',
            'name_1'        => 'required|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Prepare customer data (authentication)
            $customerData = [
                'email' => $request->email,
                'password' => $request->password, // Will be hashed by model mutator
                'is_active' => 1,
            ];

            // Prepare basic data
            $basicData = [
                'title' => $request->title,
                'name_1' => $request->name_1,
                'name_2' => $request->name_2,
                'search_term_1' => strtoupper($request->name_1),
                'search_term_2' => $request->search_term_2,
                'external_number' => $request->external_number,
                'customer_group' => $request->customer_group,
                'customer_category' => $request->customer_category,
                'credit_limit_type' => $request->credit_limit_type,
                'industry_sector' => $request->industry_sector,
                'ec_account_executive' => $request->ec_account_executive,
                'sap_account_executive' => $request->sap_account_executive,
                'authorization_group' => $request->authorization_group,
                'created_by' => $currentUserIdentifier,
                'created_on' => now(),
                'block' => false,
                'deletion_flag' => false,
            ];

            // Create customer with basic data using Model method
            $customer = Customer::createWithBasicData($customerData, $basicData);

            // Create address if provided
            if ($request->filled(['street', 'city', 'country'])) {
                $customer->addresses()->create([
                    'country' => $request->country,
                    'region' => $request->region,
                    'city' => $request->city,
                    'district' => $request->district,
                    'rural_urban_village' => $request->rural_urban_village,
                    'street' => $request->street,
                    'postal_code' => $request->postal_code,
                    'language' => $request->language,
                ]);
            }

            // Create contact if provided
            if ($request->filled(['contact_name', 'contact_phone'])) {
                $customer->contact()->create([
                    'full_name' => $request->contact_name,
                    'cell_phone' => $request->contact_phone,
                ]);
            }

            // Buat akun auth_users untuk login
            // is_already_cp = false → customer harus verifikasi email & ganti password sebelum bisa login
            DB::table('auth_users')->insert([
                'employee_id'   => null,
                'customer_id'   => $customer->customer_id,
                'username'      => $customer->customer_code,
                'email'         => $request->email,
                'phone'         => $request->contact_phone ?: null,
                'password'      => Hash::make($request->password),
                'is_active'     => true,
                'is_already_cp' => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::commit();

            Log::info('=== API: CUSTOMER CREATED SUCCESSFULLY ===', [
                'customer_id' => $customer->customer_id,
                'customer_code' => $customer->customer_code
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => [
                    'customer_id' => $customer->customer_id,
                    'customer_code' => $customer->customer_code
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR CREATING CUSTOMER ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update customer (API) - Updates all related data
     */
    public function update(Request $request, $id)
    {
        $currentUserIdentifier = $this->getCurrentUserIdentifier();

        Log::info('=== API: UPDATING CUSTOMER ===', [
            'customer_id' => $id,
            'data' => $request->except(['password']),
            'updated_by' => $currentUserIdentifier
        ]);

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255|unique:customer,email,' . $id . ',customer_id',
            'name_1' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Update customer
            $customer->update([
                'email' => $request->email,
            ]);

            // Update or create basic data
            $customer->basicData()->updateOrCreate(
                ['customer_id' => $id],
                [
                    'title' => $request->title,
                    'name_1' => $request->name_1,
                    'name_2' => $request->name_2,
                    'search_term_1' => strtoupper($request->name_1),
                    'search_term_2' => $request->search_term_2,
                    'external_number' => $request->external_number,
                    'customer_group' => $request->customer_group,
                    'customer_category' => $request->customer_category,
                    'credit_limit_type' => $request->credit_limit_type,
                    'industry_sector' => $request->industry_sector,
                    'ec_account_executive' => $request->ec_account_executive,
                    'sap_account_executive' => $request->sap_account_executive,
                    'authorization_group' => $request->authorization_group,
                    'last_changed_by' => $currentUserIdentifier,
                    'last_changed_on' => now(),
                ]
            );

            // Update address if provided
            if ($request->filled(['street', 'city', 'country'])) {
                // Get first address or create new
                $address = $customer->addresses()->first();
                if ($address) {
                    $address->update([
                        'country' => $request->country,
                        'region' => $request->region,
                        'city' => $request->city,
                        'district' => $request->district,
                        'rural_urban_village' => $request->rural_urban_village,
                        'street' => $request->street,
                        'postal_code' => $request->postal_code,
                        'language' => $request->language,
                    ]);
                } else {
                    $customer->addresses()->create([
                        'country' => $request->country,
                        'region' => $request->region,
                        'city' => $request->city,
                        'district' => $request->district,
                        'rural_urban_village' => $request->rural_urban_village,
                        'street' => $request->street,
                        'postal_code' => $request->postal_code,
                        'language' => $request->language,
                    ]);
                }
            }

            // Update contact if provided
            if ($request->filled(['contact_name', 'contact_phone'])) {
                $customer->contact()->updateOrCreate(
                    ['customer_id' => $id],
                    [
                        'full_name' => $request->contact_name,
                        'cell_phone' => $request->contact_phone,
                    ]
                );
            }

            // Log activity
            $customer->logActivity('update', "Customer updated by {$currentUserIdentifier}", 'customer');

            DB::commit();

            Log::info('=== API: CUSTOMER UPDATED SUCCESSFULLY ===', [
                'customer_id' => $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR UPDATING CUSTOMER ===', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer permanently (API)
     */
    public function destroy($id)
    {
        $currentUserIdentifier = $this->getCurrentUserIdentifier();

        Log::info('=== API: DELETING CUSTOMER (PERMANENT) ===', [
            'customer_id' => $id,
            'deleted_by' => $currentUserIdentifier
        ]);

        try {
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Log before deleting
            $customer->logActivity('delete', "Customer permanently deleted by {$currentUserIdentifier}", 'customer');

            // Hard delete using Model method
            $customer->hardDeleteCustomer();

            Log::info('=== API: CUSTOMER PERMANENTLY DELETED SUCCESSFULLY ===', [
                'customer_id' => $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer and all related data have been permanently deleted'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER ===', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== ADDITIONAL API METHODS ====================

    /**
     * Get customer statistics
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics()
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER STATISTICS ===');

            $stats = Customer::getStatistics();

            Log::info('=== API: CUSTOMER STATISTICS FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER STATISTICS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers by keyword
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $search = $request->get('q');

            if (empty($search)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query is required'
                ], 422);
            }

            Log::info('=== API: SEARCHING CUSTOMERS ===', [
                'search' => $search
            ]);

            $customers = Customer::with('basicData')
                ->search($search)
                ->limit(20)
                ->get();

            Log::info('=== API: CUSTOMERS SEARCH COMPLETED ===', [
                'count' => $customers->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $customers
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR SEARCHING CUSTOMERS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search customers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete customer (mark as deleted)
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function softDelete($id)
    {
        Log::info('=== API: SOFT DELETING CUSTOMER ===', [
            'customer_id' => $id
        ]);

        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Soft delete using Model method
            $customer->softDeleteCustomer();

            Log::info('=== API: CUSTOMER SOFT DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Customer marked as deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR SOFT DELETING CUSTOMER ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore deleted customer
     * 
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore($id)
    {
        Log::info('=== API: RESTORING CUSTOMER ===', [
            'customer_id' => $id
        ]);

        try {
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Restore using Model method
            $customer->restoreCustomer();

            Log::info('=== API: CUSTOMER RESTORED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Customer restored successfully',
                'data' => $customer->fresh(['basicData'])
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR RESTORING CUSTOMER ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore customer: ' . $e->getMessage()
            ], 500);
        }
    }
}
