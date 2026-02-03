<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerContactController extends Controller
{
    /**
     * Get all contacts for a customer
     */
    public function index($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER CONTACTS ===', [
                'customer_id' => $customerId
            ]);

            $contacts = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->orderBy('contact_id', 'desc')
                ->get();

            Log::info('=== API: CUSTOMER CONTACTS FETCHED SUCCESSFULLY ===', [
                'count' => $contacts->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $contacts
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER CONTACTS ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contacts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single contact
     */
    public function show($customerId, $contactId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER CONTACT ===', [
                'customer_id' => $customerId,
                'contact_id' => $contactId
            ]);

            $contact = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->first();

            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER CONTACT FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $contact
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch contact: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store new contact
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER CONTACT ===', [
            'customer_id' => $customerId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:100',
            'cell_phone_country' => 'nullable|string|max:10',
            'cell_phone' => 'nullable|string|max:50',
            'telephone_country' => 'nullable|string|max:10',
            'telephone' => 'nullable|string|max:50',
            'telephone_extension' => 'nullable|string|max:20',
            'fax_country' => 'nullable|string|max:10',
            'fax' => 'nullable|string|max:50',
            'fax_extension' => 'nullable|string|max:20',
            'email_personal' => 'nullable|email|max:255',
            'email_work' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'preferred_communication' => 'nullable|string|max:100',
            'entry_date' => 'nullable|date',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
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

            $contactData = [
                'customer_id' => $customerId,
                'title' => $request->title,
                'full_name' => $request->full_name,
                'nick_name' => $request->nick_name,
                'position' => $request->position,
                'department' => $request->department,
                'language' => $request->language,
                'cell_phone_country' => $request->cell_phone_country,
                'cell_phone' => $request->cell_phone,
                'telephone_country' => $request->telephone_country,
                'telephone' => $request->telephone,
                'telephone_extension' => $request->telephone_extension,
                'fax_country' => $request->fax_country,
                'fax' => $request->fax,
                'fax_extension' => $request->fax_extension,
                'email_personal' => $request->email_personal,
                'email_work' => $request->email_work,
                'website' => $request->website,
                'preferred_communication' => $request->preferred_communication,
                'entry_date' => $request->entry_date,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            Log::info('=== API: PREPARED DATA FOR INSERT ===', ['data' => $contactData]);

            $contactId = DB::table('customer_contact')->insertGetId($contactData);

            Log::info('=== API: CUSTOMER CONTACT CREATED SUCCESSFULLY ===', [
                'contact_id' => $contactId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contact created successfully',
                'data' => ['contact_id' => $contactId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create contact: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update contact
     */
    public function update(Request $request, $customerId, $contactId)
    {
        Log::info('=== API: UPDATING CUSTOMER CONTACT ===', [
            'customer_id' => $customerId,
            'contact_id' => $contactId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:100',
            'cell_phone_country' => 'nullable|string|max:10',
            'cell_phone' => 'nullable|string|max:50',
            'telephone_country' => 'nullable|string|max:10',
            'telephone' => 'nullable|string|max:50',
            'telephone_extension' => 'nullable|string|max:20',
            'fax_country' => 'nullable|string|max:10',
            'fax' => 'nullable|string|max:50',
            'fax_extension' => 'nullable|string|max:20',
            'email_personal' => 'nullable|email|max:255',
            'email_work' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'preferred_communication' => 'nullable|string|max:100',
            'entry_date' => 'nullable|date',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [
                'title' => $request->title,
                'full_name' => $request->full_name,
                'nick_name' => $request->nick_name,
                'position' => $request->position,
                'department' => $request->department,
                'language' => $request->language,
                'cell_phone_country' => $request->cell_phone_country,
                'cell_phone' => $request->cell_phone,
                'telephone_country' => $request->telephone_country,
                'telephone' => $request->telephone,
                'telephone_extension' => $request->telephone_extension,
                'fax_country' => $request->fax_country,
                'fax' => $request->fax,
                'fax_extension' => $request->fax_extension,
                'email_personal' => $request->email_personal,
                'email_work' => $request->email_work,
                'website' => $request->website,
                'preferred_communication' => $request->preferred_communication,
                'entry_date' => $request->entry_date,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'updated_at' => now(),
            ];

            $updated = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->update($updateData);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER CONTACT UPDATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR UPDATING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete contact
     */
    public function destroy($customerId, $contactId)
    {
        Log::info('=== API: DELETING CUSTOMER CONTACT ===', [
            'customer_id' => $customerId,
            'contact_id' => $contactId
        ]);

        try {
            $deleted = DB::table('customer_contact')
                ->where('customer_id', $customerId)
                ->where('contact_id', $contactId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER CONTACT DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER CONTACT ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete contact: ' . $e->getMessage()
            ], 500);
        }
    }
}