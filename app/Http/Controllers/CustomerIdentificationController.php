<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CustomerIdentificationController extends Controller
{
    /**
     * Redact the identification number before it goes into an audit log
     * snapshot. Mirrors CustomerIdentification's own
     * $auditExcept = ['identification_number'] so a raw DB::table() write
     * logs identically to what AuditObserver would have written had this
     * gone through Eloquent.
     */
    private function redactIdentificationRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        if (array_key_exists('identification_number', $row)) {
            $row['identification_number'] = '***redacted***';
        }

        return $row;
    }

    /**
     * Get all identifications for a customer
     */
    public function index($customerId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER IDENTIFICATIONS ===', [
                'customer_id' => $customerId
            ]);

            $identifications = DB::table('customer_identification')
                ->where('customer_id', $customerId)
                ->orderBy('identification_id', 'desc')
                ->get();

            Log::info('=== API: CUSTOMER IDENTIFICATIONS FETCHED SUCCESSFULLY ===', [
                'count' => $identifications->count()
            ]);

            return response()->json([
                'success' => true,
                'data' => $identifications
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER IDENTIFICATIONS ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch identifications'
            ], 500);
        }
    }

    /**
     * Get single identification
     */
    public function show($customerId, $identificationId)
    {
        try {
            Log::info('=== API: FETCHING CUSTOMER IDENTIFICATION ===', [
                'customer_id' => $customerId,
                'identification_id' => $identificationId
            ]);

            $identification = DB::table('customer_identification')
                ->where('customer_id', $customerId)
                ->where('identification_id', $identificationId)
                ->first();

            if (!$identification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identification not found'
                ], 404);
            }

            Log::info('=== API: CUSTOMER IDENTIFICATION FETCHED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'data' => $identification
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR FETCHING CUSTOMER IDENTIFICATION ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch identification'
            ], 500);
        }
    }

    /**
     * Store new identification
     */
    public function store(Request $request, $customerId)
    {
        Log::info('=== API: CREATING CUSTOMER IDENTIFICATION ===', [
            'customer_id' => $customerId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'identification_type' => 'required|string|max:255',
            'identification_number' => 'required|string|max:255',
            'responsible_institution' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'entry_date' => 'nullable|date',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
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

            $identificationData = [
                'customer_id' => $customerId,
                'identification_type' => $request->identification_type,
                'identification_number' => $request->identification_number,
                'responsible_institution' => $request->responsible_institution,
                'country' => $request->country,
                'region' => $request->region,
                'entry_date' => $request->entry_date,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'drive_link' => $request->drive_link,
                'verify_link' => $request->verify_link,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $identificationId = DB::table('customer_identification')->insertGetId($identificationData);

            $label = $request->identification_type ?: "Identification #{$identificationId}";

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerIdentification's own $auditModule so these rows group together
                auditableType: 'CustomerIdentification',
                auditableId: $identificationId,
                event: 'created',
                recordLabel: $label,
                description: "added Customer Identification: {$label} — Customer #{$customerId}",
                old: null,
                new: $this->redactIdentificationRow($identificationData),
            );

            Log::info('=== API: CUSTOMER IDENTIFICATION CREATED SUCCESSFULLY ===', [
                'identification_id' => $identificationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Identification created successfully',
                'data' => ['identification_id' => $identificationId]
            ], 201);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR CREATING CUSTOMER IDENTIFICATION ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create identification'
            ], 500);
        }
    }

    /**
     * Update identification
     */
    public function update(Request $request, $customerId, $identificationId)
    {
        Log::info('=== API: UPDATING CUSTOMER IDENTIFICATION ===', [
            'customer_id' => $customerId,
            'identification_id' => $identificationId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            'identification_type' => 'nullable|string|max:255',
            'identification_number' => 'nullable|string|max:255',
            'responsible_institution' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'entry_date' => 'nullable|date',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date',
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Snapshot before update — needed for the audit log entry below.
            $existingIdentification = DB::table('customer_identification')
                ->where('customer_id', $customerId)
                ->where('identification_id', $identificationId)
                ->first();

            $updateData = [
                'identification_type' => $request->identification_type,
                'identification_number' => $request->identification_number,
                'responsible_institution' => $request->responsible_institution,
                'country' => $request->country,
                'region' => $request->region,
                'entry_date' => $request->entry_date,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'drive_link' => $request->drive_link,
                'verify_link' => $request->verify_link,
                'updated_at' => now(),
            ];

            $updated = DB::table('customer_identification')
                ->where('customer_id', $customerId)
                ->where('identification_id', $identificationId)
                ->update($updateData);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identification not found'
                ], 404);
            }

            $label = $updateData['identification_type'] ?? ($existingIdentification->identification_type ?? "Identification #{$identificationId}");

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerIdentification's own $auditModule so these rows group together
                auditableType: 'CustomerIdentification',
                auditableId: $identificationId,
                event: 'updated',
                recordLabel: $label,
                description: "updated Customer Identification: {$label} — Customer #{$customerId}",
                old: $this->redactIdentificationRow((array) $existingIdentification),
                new: $this->redactIdentificationRow($updateData),
            );

            Log::info('=== API: CUSTOMER IDENTIFICATION UPDATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Identification updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR UPDATING CUSTOMER IDENTIFICATION ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update identification'
            ], 500);
        }
    }

    /**
     * Delete identification
     */
    public function destroy($customerId, $identificationId)
    {
        Log::info('=== API: DELETING CUSTOMER IDENTIFICATION ===', [
            'customer_id' => $customerId,
            'identification_id' => $identificationId
        ]);

        try {
            // Snapshot before delete — needed for the audit log entry below.
            $existingIdentification = DB::table('customer_identification')
                ->where('customer_id', $customerId)
                ->where('identification_id', $identificationId)
                ->first();

            $deleted = DB::table('customer_identification')
                ->where('customer_id', $customerId)
                ->where('identification_id', $identificationId)
                ->delete();

            if ($deleted === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identification not found'
                ], 404);
            }

            $label = $existingIdentification->identification_type ?? "Identification #{$identificationId}";

            AuditLog::recordAction(
                module: 'Customer', // matches CustomerIdentification's own $auditModule so these rows group together
                auditableType: 'CustomerIdentification',
                auditableId: $identificationId,
                event: 'deleted',
                recordLabel: $label,
                description: "deleted Customer Identification: {$label} — Customer #{$customerId}",
                old: $this->redactIdentificationRow((array) $existingIdentification),
                new: null,
            );

            Log::info('=== API: CUSTOMER IDENTIFICATION DELETED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Identification deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('=== API: ERROR DELETING CUSTOMER IDENTIFICATION ===', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete identification'
            ], 500);
        }
    }
}