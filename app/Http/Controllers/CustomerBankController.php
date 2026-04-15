<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerBankController extends Controller
{
    /**
     * Get all bank accounts for a customer
     */
    public function index($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER BANK ACCOUNTS ===', [
                'customer_id' => $customerId
            ]);

            $banks = DB::table('customer_bank')
                ->where('customer_id', $customerId)
                ->orderByDesc('created_at')
                ->get();

            Log::info('=== API: CUSTOMER BANK ACCOUNTS FETCHED SUCCESSFULLY ===', [
                'count' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $banks
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER BANK ACCOUNTS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank accounts'
            ], 500);
        }
    }

    /**
     * Get single bank account
     */
    public function show($customerId, $bankId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER BANK ACCOUNT ===', [
                'customer_id' => $customerId,
                'bank_id' => $bankId
            ]);

            $bank = DB::table('customer_bank')
                ->where('customer_id', $customerId)
                ->where('bank_id', $bankId)
                ->first();

            if (!$bank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER BANK ACCOUNT FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $bank
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER BANK ACCOUNT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bank account'
            ], 500);
        }
    }

    /**
     * Store new bank account
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER BANK ACCOUNT ===', [
            'customer_id' => $customerId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'bank_key' => 'nullable|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'drive_link' => 'nullable|url|max:255',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'verify_link' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if customer exists
            $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }

            $bankId = DB::table('customer_bank')->insertGetId([
                'customer_id' => $customerId,
                'bank_name' => $request->bank_name,
                'bank_key' => $request->bank_key,
                'account_number' => $request->account_number,
                'account_holder' => $request->account_holder,
                'drive_link' => $request->drive_link,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'verify_link' => $request->verify_link,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('=== API: CUSTOMER BANK ACCOUNT CREATED SUCCESSFULLY ===', [
                'bank_id' => $bankId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank account created successfully',
                'data' => ['bank_id' => $bankId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER BANK ACCOUNT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create bank account'
            ], 500);
        }
    }

    /**
     * Update bank account
     */
    public function update(Request $request, $customerId, $bankId)
    {
        Log::info('=== API: UPDATING CUSTOMER BANK ACCOUNT ===', [
            'customer_id' => $customerId,
            'bank_id' => $bankId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'nullable|string|max:255',
            'bank_key' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'drive_link' => 'nullable|url|max:255',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'verify_link' => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Build update data - only update fields that are provided
            $updateData = [];
            
            if ($request->has('bank_name')) $updateData['bank_name'] = $request->bank_name;
            if ($request->has('bank_key')) $updateData['bank_key'] = $request->bank_key;
            if ($request->has('account_number')) $updateData['account_number'] = $request->account_number;
            if ($request->has('account_holder')) $updateData['account_holder'] = $request->account_holder;
            if ($request->has('drive_link')) $updateData['drive_link'] = $request->drive_link;
            if ($request->has('valid_from')) $updateData['valid_from'] = $request->valid_from;
            if ($request->has('valid_to')) $updateData['valid_to'] = $request->valid_to;
            if ($request->has('verify_link')) $updateData['verify_link'] = $request->verify_link;
            
            $updateData['updated_at'] = now();

            $updated = DB::table('customer_bank')
                ->where('customer_id', $customerId)
                ->where('bank_id', $bankId)
                ->update($updateData);

            if ($updated === 0) {
                // Check if record exists
                $exists = DB::table('customer_bank')
                    ->where('customer_id', $customerId)
                    ->where('bank_id', $bankId)
                    ->exists();
                
                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bank account not found'
                    ], 404);
                }
            }

            Log::info('=== API: CUSTOMER BANK ACCOUNT UPDATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Bank account updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR UPDATING CUSTOMER BANK ACCOUNT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update bank account'
            ], 500);
        }
    }

    /**
     * Delete bank account
     */
    public function destroy($customerId, $bankId)
    {
        Log::info('=== API: DELETING CUSTOMER BANK ACCOUNT ===', [
            'customer_id' => $customerId,
            'bank_id' => $bankId
        ]);

        try {
            $deleted = DB::table('customer_bank')
                ->where('customer_id', $customerId)
                ->where('bank_id', $bankId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bank account not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER BANK ACCOUNT DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER BANK ACCOUNT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bank account'
            ], 500);
        }
    }
}