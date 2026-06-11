<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class CustomerController extends Controller
{
    /**
     * Get current user's identifier
     */
    private function getCurrentUserIdentifier()
    {
        return session('user.eci') ?? session('user.email') ?? session('user.name') ?? 'System';
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
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

            // Active employees for the EC Account Executive dropdown (value = ECI)
            $employees = \App\Models\Employee::with('basicData')
                ->where('is_active', true)
                ->get()
                ->map(fn($e) => [
                    'eci'  => $e->eci,
                    'name' => $e->basicData->full_name ?? $e->eci,
                ])
                ->filter(fn($e) => !empty($e['eci']))
                ->sortBy('name')
                ->values();

            // Pass customer, user and employees to view
            return view('master.customer.show', compact('customer', 'user', 'employees'));
            
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

            $perPage = max(1, min((int) $request->get('per_page', 15), 100));
            
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
                    'parent_customer_id' => $customer->parent_customer_id,
                    'parent_name' => $customer->parentCustomer?->basicData?->name_1,
                    'customer_type' => $customer->parent_customer_id ? 'end_customer' : 'top_level',
                    'end_customers_count' => $customer->end_customers_count ?? 0,
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers'
            ], 500);
        }
    }

    /**
     * GET /api/customers/{id}/header
     * Returns only the fields shown in the profile header card for AJAX refresh.
     */
    public function headerData($id)
    {
        try {
            $customer = Customer::with('basicData')->find($id);
            if (!$customer) {
                return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
            }

            $name1 = $customer->basicData->name_1 ?? '';
            $initials = $name1 ? strtoupper(substr($name1, 0, 1)) : 'N';
            if (strlen($name1) > 1 && strpos($name1, ' ') !== false) {
                $parts = explode(' ', $name1);
                $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
            }

            if (!empty($customer->basicData->deletion_flag)) {
                $statusClass = 'bg-red-100 text-red-800';
                $statusLabel = 'Flagged for Deletion';
            } elseif (!empty($customer->basicData->block)) {
                $statusClass = 'bg-yellow-100 text-yellow-800';
                $statusLabel = 'Blocked';
            } elseif ($customer->is_active) {
                $statusClass = 'bg-green-100 text-green-800';
                $statusLabel = 'Active';
            } else {
                $statusClass = 'bg-gray-100 text-gray-800';
                $statusLabel = 'Inactive';
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'name_1'            => $customer->basicData->name_1 ?? '',
                    'name_2'            => $customer->basicData->name_2 ?? '',
                    'customer_code'     => $customer->customer_code ?? '',
                    'email'             => $customer->email ?? '',
                    'phone'             => $customer->basicData->telephone ?? $customer->basicData->cell_phone ?? '',
                    'customer_group'    => $customer->basicData->customer_group ?? '',
                    'customer_category' => $customer->basicData->customer_category ?? '',
                    'industry_sector'   => $customer->basicData->industry_sector ?? '',
                    'initials'          => $initials,
                    'status_label'      => $statusLabel,
                    'status_class'      => $statusClass,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('headerData error', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load header data'], 500);
        }
    }

    /**
     * Customer grouping page (WEB)
     */
    public function grouping()
    {
        return view('master.customer.grouping', ['user' => session('user')]);
    }

    /**
     * Get grouping data: parent customers with their end customers (API)
     */
    public function getGroupingData()
    {
        try {
            $parents = Customer::topLevel()
                ->with(['basicData', 'endCustomers.basicData'])
                ->withCount('endCustomers')
                ->having('end_customers_count', '>', 0)
                ->get()
                ->map(function ($parent) {
                    return [
                        'id'            => $parent->customer_id,
                        'code'          => $parent->customer_code,
                        'email'         => $parent->email,
                        'name'          => $parent->basicData->name_1 ?? $parent->customer_code,
                        'status'        => $parent->is_active ? 'active' : 'inactive',
                        'end_customers' => $parent->endCustomers->map(fn($c) => [
                            'id'     => $c->customer_id,
                            'code'   => $c->customer_code,
                            'email'  => $c->email,
                            'name'   => $c->basicData->name_1 ?? $c->customer_code,
                            'status' => $c->is_active ? 'active' : 'inactive',
                        ])->values(),
                    ];
                });

            return response()->json(['success' => true, 'data' => $parents]);
        } catch (\Exception $e) {
            Log::error('getGroupingData error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load grouping data'], 500);
        }
    }

    /**
     * Get top-level customers (no parent) for dropdown selection (API)
     */
    public function topLevel(Request $request)
    {
        try {
            $customers = Customer::topLevel()
                ->with('basicData')
                ->where('is_active', true)
                ->get()
                ->map(fn($c) => [
                    'id'   => $c->customer_id,
                    'name' => $c->basicData->name_1 ?? $c->customer_code,
                ]);

            return response()->json(['success' => true, 'data' => $customers]);
        } catch (\Exception $e) {
            Log::error('topLevel customers error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch top-level customers'], 500);
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
            'customer_code' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z0-9]{1,4}$/', 'unique:customer,customer_code'],
            'email'         => 'nullable|email|unique:customer,email|max:255',
            'domain'        => 'nullable|string|max:255',
            'name_1'        => 'required|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
        ], [
            'customer_code.required' => 'Customer code is required.',
            'customer_code.max'      => 'Customer code must be at most 4 characters.',
            'customer_code.regex'    => 'Customer code may only contain letters and numbers.',
            'customer_code.unique'   => 'This customer code is already in use.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Prepare customer data (company record only — no login here)
            $customerData = [
                'customer_code'      => strtoupper($request->customer_code),
                'email'              => $request->email ?: null,
                'domain'             => $request->domain ?: null,
                'is_active'          => 1,
                'parent_customer_id' => $request->parent_customer_id ?: null,
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
                    'building_name' => $request->building_name,
                    'full_address' => $request->full_address,
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

            // Login accounts are now managed per-contact-person.
            // Use POST /api/customers/{id}/contacts/{contactId}/create-login
            // to grant Jarvies access to a specific contact person.

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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer'
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
            'customer_code' => ['sometimes', 'required', 'string', 'max:4', 'regex:/^[A-Za-z0-9]{1,4}$/', 'unique:customer,customer_code,' . $id . ',customer_id'],
            'email'         => 'nullable|email|max:255|unique:customer,email,' . $id . ',customer_id',
            'domain'        => 'nullable|string|max:255',
            'name_1'        => 'required|string|max:255',
        ], [
            'customer_code.max'   => 'Customer code must be at most 4 characters.',
            'customer_code.regex' => 'Customer code may only contain letters and numbers.',
            'customer_code.unique'=> 'This customer code is already in use.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
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

            // Update customer (email is optional company contact email)
            $updateData = ['email' => $request->email ?: null];
            if ($request->has('domain')) {
                $updateData['domain'] = Customer::normalizeDomain($request->domain);
            }
            if ($request->filled('customer_code')) {
                $updateData['customer_code'] = strtoupper($request->customer_code);
            }
            $customer->update($updateData);

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
                        'building_name' => $request->building_name,
                        'full_address' => $request->full_address,
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
                        'building_name' => $request->building_name,
                        'full_address' => $request->full_address,
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer'
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer'
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search customers'
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer'
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore customer'
            ], 500);
        }
    }
}
