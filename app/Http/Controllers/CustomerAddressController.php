<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerAddressController extends Controller
{
    /**
     * Get all addresses for a customer
     */
    public function index($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER ADDRESSES ===', [
                'customer_id' => $customerId
            ]);

            $addresses = DB::table('customer_address')
                ->where('customer_id', $customerId)
                ->orderBy('address_id', 'desc')
                ->get();

            Log::info('=== API: CUSTOMER ADDRESSES FETCHED SUCCESSFULLY ===', [
                'count' => $addresses->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $addresses
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER ADDRESSES ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch addresses'
            ], 500);
        }
    }

    /**
     * Get single address
     */
    public function show($customerId, $addressId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER ADDRESS ===', [
                'customer_id' => $customerId,
                'address_id' => $addressId
            ]);

            $address = DB::table('customer_address')
                ->where('customer_id', $customerId)
                ->where('address_id', $addressId)
                ->first();

            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER ADDRESS FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $address
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER ADDRESS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch address'
            ], 500);
        }
    }

    /**
     * Store new address
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER ADDRESS ===', [
            'customer_id' => $customerId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'address_type' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'rural_urban_village' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:50',
            'cell_phone_country' => 'nullable|string|max:10',
            'telephone_country' => 'nullable|string|max:10',
            'fax_country' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'preferred_communication' => 'nullable|string|max:50',
            'cell_phone' => 'nullable|string|max:50',
            'telephone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'telephone_extension' => 'nullable|string|max:10',
            'fax_extension' => 'nullable|string|max:10',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
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

            $addressId = DB::table('customer_address')->insertGetId([
                'customer_id' => $customerId,
                'address_type' => $request->address_type,
                'country' => $request->country,
                'region' => $request->region,
                'city' => $request->city,
                'district' => $request->district,
                'rural_urban_village' => $request->rural_urban_village,
                'street' => $request->street,
                'house_number' => $request->house_number,
                'postal_code' => $request->postal_code,
                'language' => $request->language,
                'cell_phone_country' => $request->cell_phone_country,
                'telephone_country' => $request->telephone_country,
                'fax_country' => $request->fax_country,
                'email' => $request->email,
                'website' => $request->website,
                'preferred_communication' => $request->preferred_communication,
                'cell_phone' => $request->cell_phone,
                'telephone' => $request->telephone,
                'fax' => $request->fax,
                'telephone_extension' => $request->telephone_extension,
                'fax_extension' => $request->fax_extension,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('=== API: CUSTOMER ADDRESS CREATED SUCCESSFULLY ===', [
                'address_id' => $addressId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => ['address_id' => $addressId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER ADDRESS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create address'
            ], 500);
        }
    }

    /**
     * Update address
     */
    public function update(Request $request, $customerId, $addressId)
    {
        Log::info('=== API: UPDATING CUSTOMER ADDRESS ===', [
            'customer_id' => $customerId,
            'address_id' => $addressId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'address_type' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'rural_urban_village' => 'nullable|string|max:100',
            'street' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            'language' => 'nullable|string|max:50',
            'cell_phone_country' => 'nullable|string|max:10',
            'telephone_country' => 'nullable|string|max:10',
            'fax_country' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'preferred_communication' => 'nullable|string|max:50',
            'cell_phone' => 'nullable|string|max:50',
            'telephone' => 'nullable|string|max:50',
            'fax' => 'nullable|string|max:50',
            'telephone_extension' => 'nullable|string|max:10',
            'fax_extension' => 'nullable|string|max:10',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updated = DB::table('customer_address')
                ->where('customer_id', $customerId)
                ->where('address_id', $addressId)
                ->update([
                    'address_type' => $request->address_type,
                    'country' => $request->country,
                    'region' => $request->region,
                    'city' => $request->city,
                    'district' => $request->district,
                    'rural_urban_village' => $request->rural_urban_village,
                    'street' => $request->street,
                    'house_number' => $request->house_number,
                    'postal_code' => $request->postal_code,
                    'language' => $request->language,
                    'cell_phone_country' => $request->cell_phone_country,
                    'telephone_country' => $request->telephone_country,
                    'fax_country' => $request->fax_country,
                    'email' => $request->email,
                    'website' => $request->website,
                    'preferred_communication' => $request->preferred_communication,
                    'cell_phone' => $request->cell_phone,
                    'telephone' => $request->telephone,
                    'fax' => $request->fax,
                    'telephone_extension' => $request->telephone_extension,
                    'fax_extension' => $request->fax_extension,
                    'valid_from' => $request->valid_from,
                    'valid_to' => $request->valid_to,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER ADDRESS UPDATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR UPDATING CUSTOMER ADDRESS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update address'
            ], 500);
        }
    }

    /**
     * Delete address
     */
    public function destroy($customerId, $addressId)
    {
        Log::info('=== API: DELETING CUSTOMER ADDRESS ===', [
            'customer_id' => $customerId,
            'address_id' => $addressId
        ]);

        try {
            $deleted = DB::table('customer_address')
                ->where('customer_id', $customerId)
                ->where('address_id', $addressId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER ADDRESS DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER ADDRESS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address'
            ], 500);
        }
    }
}