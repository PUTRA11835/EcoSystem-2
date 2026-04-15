<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\CustomerCredential;

class CustomerCredentialController extends Controller
{
    /**
     * Get credential for a customer.
     */
    public function show($customerId)
    {
        try {
            $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }

            $credential = CustomerCredential::where('customer_id', $customerId)->first();

            return response()->json([
                'success'    => true,
                'credential' => $credential,
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER CREDENTIAL ===', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch credential',
            ], 500);
        }
    }

    /**
     * Create or update credential for a customer (upsert).
     */
    public function store(Request $request, $customerId)
    {
        try {
            $customerExists = DB::table('customer')->where('customer_id', $customerId)->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }

            $sessionUser = session('user');
            $updatedBy   = $sessionUser['name'] ?? $sessionUser['email'] ?? 'system';

            $credential = CustomerCredential::updateOrCreate(
                ['customer_id' => $customerId],
                [
                    'notes'      => $request->input('notes'),
                    'updated_by' => $updatedBy,
                ]
            );

            return response()->json([
                'success'    => true,
                'message'    => 'Credential saved successfully',
                'credential' => $credential->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR SAVING CUSTOMER CREDENTIAL ===', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save credential',
            ], 500);
        }
    }
}
