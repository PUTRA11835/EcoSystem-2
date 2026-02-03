<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CustomerBasicDataController extends Controller
{
    /**
     * Get current user's identifier for audit tracking
     */
    private function getCurrentUserIdentifier()
    {
        // Try to get from Auth
        if (Auth::check()) {
            $user = Auth::user();
            if (isset($user->eci) && !empty($user->eci)) {
                return $user->eci;
            } elseif (isset($user->email) && !empty($user->email)) {
                return $user->email;
            } elseif (isset($user->name) && !empty($user->name)) {
                return $user->name;
            }
        }
        
        // Try to get from session
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
        
        return 'System';
    }

    /**
     * Get customer basic data
     */
    public function show($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER BASIC DATA ===', [
                'customer_id' => $customerId
            ]);

            $basicData = DB::table('customer_basic_data')
                ->where('customer_id', $customerId)
                ->first();

            if (!$basicData) {
                Log::info('No basic data found, returning empty response');
                return response()->json([
                    'success' => true,
                    'message' => 'No basic data found',
                    'data' => null
                ]);
            }

            Log::info('=== API: CUSTOMER BASIC DATA FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $basicData
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER BASIC DATA ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch basic data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store or update customer basic data
     * CRITICAL: Proper audit tracking implementation
     */
    public function store(Request $request, $customerId)
    {
        $currentUserIdentifier = $this->getCurrentUserIdentifier();

        Log::info('=== API: STORING CUSTOMER BASIC DATA ===', [
            'customer_id' => $customerId,
            'data' => $request->all(),
            'user' => $currentUserIdentifier
        ]);

        $validator = Validator::make($request->all(), [
            'name_1' => 'required|string|max:255',
            'name_2' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:50',
            'search_term_1' => 'nullable|string|max:255',
            'search_term_2' => 'nullable|string|max:255',
            'external_number' => 'nullable|string|max:50',
            'customer_group' => 'nullable|string|max:100',
            'customer_category' => 'nullable|string|max:100',
            'credit_limit_type' => 'nullable|string|max:100',
            'industry_sector' => 'nullable|string|max:100',
            'ec_account_executive' => 'nullable|string|max:100',
            'sap_account_executive' => 'nullable|string|max:100',
            'authorization_group' => 'nullable|string|max:100',
            'block' => 'nullable|boolean',
            'deletion_flag' => 'nullable|boolean',
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
            // Check if customer exists
            $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
            if (!$customerExists) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            // Check if basic data already exists
            $existingData = DB::table('customer_basic_data')
                ->where('customer_id', $customerId)
                ->first();

            $data = [
                'customer_id' => $customerId,
                'title' => $request->title,
                'name_1' => $request->name_1,
                'name_2' => $request->name_2,
                'search_term_1' => $request->search_term_1 ?? strtoupper($request->name_1),
                'search_term_2' => $request->search_term_2,
                'external_number' => $request->external_number,
                'customer_group' => $request->customer_group,
                'customer_category' => $request->customer_category,
                'credit_limit_type' => $request->credit_limit_type,
                'industry_sector' => $request->industry_sector,
                'ec_account_executive' => $request->ec_account_executive,
                'sap_account_executive' => $request->sap_account_executive,
                'authorization_group' => $request->authorization_group,
                'block' => $request->block ? 1 : 0,
                'deletion_flag' => $request->deletion_flag ? 1 : 0,
            ];

            if ($existingData) {
                // ========== UPDATE EXISTING RECORD ==========
                // IMPORTANT: JANGAN update created_by dan created_on!
                // Hanya update last_changed_by dan last_changed_on
                
                $data['last_changed_by'] = $currentUserIdentifier;
                $data['last_changed_on'] = now();
                $data['updated_at'] = now();
                
                // JANGAN include created_by dan created_on dalam update
                unset($data['created_by'], $data['created_on']);
                
                DB::table('customer_basic_data')
                    ->where('customer_id', $customerId)
                    ->update($data);

                $message = 'Customer basic data updated successfully';
                $action = 'update';
                
                Log::info('Updated basic data', [
                    'customer_id' => $customerId,
                    'updated_by' => $currentUserIdentifier,
                    'updated_on' => now()
                ]);
            } else {
                // ========== INSERT NEW RECORD ==========
                // IMPORTANT: SET created_by dan created_on
                // JANGAN set last_changed_by dan last_changed_on untuk record baru
                
                $data['created_by'] = $currentUserIdentifier;
                $data['created_on'] = now();
                $data['created_at'] = now();
                $data['updated_at'] = now();
                
                // Pastikan last_changed fields NULL untuk record baru
                $data['last_changed_by'] = null;
                $data['last_changed_on'] = null;
                
                DB::table('customer_basic_data')->insert($data);

                $message = 'Customer basic data created successfully';
                $action = 'create';
                
                Log::info('Created basic data', [
                    'customer_id' => $customerId,
                    'created_by' => $currentUserIdentifier,
                    'created_on' => now()
                ]);
            }

            // Log to customer_history
            DB::table('customer_history')->insert([
                'customer_id' => $customerId,
                'action' => $action === 'create' ? 'Created Basic Data' : 'Updated Basic Data',
                'description' => $action === 'create' 
                    ? "Basic data created by {$currentUserIdentifier}" 
                    : "Basic data updated by {$currentUserIdentifier}",
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            Log::info('=== API: CUSTOMER BASIC DATA STORED SUCCESSFULLY ===', [
                'customer_id' => $customerId,
                'action' => $action,
                'user' => $currentUserIdentifier
            ]);

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR STORING CUSTOMER BASIC DATA ===', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to store basic data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete customer basic data
     */
    public function destroy($customerId)
    {
        $currentUserIdentifier = $this->getCurrentUserIdentifier();

        Log::info('=== API: DELETING CUSTOMER BASIC DATA ===', [
            'customer_id' => $customerId
        ]);

        DB::beginTransaction();

        try {
            $deleted = DB::table('customer_basic_data')
                ->where('customer_id', $customerId)
                ->delete();

            if ($deleted === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Basic data not found'
                ], 404);
            }

            // Log to customer_history
            DB::table('customer_history')->insert([
                'customer_id' => $customerId,
                'action' => 'Deleted Basic Data',
                'description' => "Basic data deleted by {$currentUserIdentifier}",
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::commit();

            Log::info('=== API: CUSTOMER BASIC DATA DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Customer basic data deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('=== API: ERROR DELETING CUSTOMER BASIC DATA ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete basic data: ' . $e->getMessage()
            ], 500);
        }
    }
}