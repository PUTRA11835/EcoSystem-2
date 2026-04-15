<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeIdentification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeIdentificationController extends Controller
{
    /**
     * Get all identifications for an employee
     */
    public function index($employeeId)
    {
        try {
            Log::info('Fetching identifications', ['employee_id' => $employeeId]);

            // Check if employee exists
            $employee = Employee::where('employee_id', $employeeId)->first();
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $identifications = EmployeeIdentification::where('employee_id', $employeeId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Identifications retrieved successfully',
                'data' => $identifications
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching identifications', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving identifications'
            ], 500);
        }
    }

    /**
     * Get single identification detail
     */
    public function show($employeeId, $identificationId)
    {
        try {
            $identification = EmployeeIdentification::where('identification_id', $identificationId)
                ->where('employee_id', $employeeId)
                ->first();

            if (!$identification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identification not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Identification detail retrieved successfully',
                'data' => $identification
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching identification detail', [
                'identification_id' => $identificationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving identification detail'
            ], 500);
        }
    }

    /**
     * Create new identification
     */
    public function store(Request $request, $employeeId)
    {
        $validator = Validator::make($request->all(), [
            'identification_type' => 'required|string|max:50',
            'identification_number' => 'required|string|max:100',
            'responsible_institution' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'entry_date' => 'nullable|date',
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Check if employee exists
            $employee = Employee::where('employee_id', $employeeId)->first();
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Check for duplicate
            $exists = EmployeeIdentification::where('employee_id', $employeeId)
                ->where('identification_type', $request->identification_type)
                ->where('identification_number', $request->identification_number)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This identification already exists'
                ], 422);
            }

            // Create identification
            $identification = EmployeeIdentification::create([
                'employee_id' => $employeeId,
                'identification_type' => $request->identification_type,
                'identification_number' => $request->identification_number,
                'responsible_institution' => $request->responsible_institution,
                'country' => $request->country,
                'region' => $request->region,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'entry_date' => $request->entry_date,
                'drive_link' => $request->drive_link,
                'verify_link' => $request->verify_link,
                'created_by' => session('user.eci') ?? session('user.name') ?? 'System'
            ]);

            DB::commit();

            Log::info('Identification created', [
                'identification_id' => $identification->identification_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Identification created successfully',
                'data' => $identification
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating identification', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating identification'
            ], 500);
        }
    }

    /**
     * Update identification
     */
    public function update(Request $request, $employeeId, $identificationId)
    {
        $validator = Validator::make($request->all(), [
            'identification_type' => 'required|string|max:50',
            'identification_number' => 'required|string|max:100',
            'responsible_institution' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'entry_date' => 'nullable|date',
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $identification = EmployeeIdentification::where('identification_id', $identificationId)
                ->where('employee_id', $employeeId)
                ->first();

            if (!$identification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identification not found'
                ], 404);
            }

            // Check for duplicate (excluding current record)
            $exists = EmployeeIdentification::where('employee_id', $employeeId)
                ->where('identification_type', $request->identification_type)
                ->where('identification_number', $request->identification_number)
                ->where('identification_id', '!=', $identificationId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'This identification already exists'
                ], 422);
            }

            // Update identification
            $identification->update([
                'identification_type' => $request->identification_type,
                'identification_number' => $request->identification_number,
                'responsible_institution' => $request->responsible_institution,
                'country' => $request->country,
                'region' => $request->region,
                'valid_from' => $request->valid_from,
                'valid_to' => $request->valid_to,
                'entry_date' => $request->entry_date,
                'drive_link' => $request->drive_link,
                'verify_link' => $request->verify_link
            ]);

            DB::commit();

            Log::info('Identification updated', [
                'identification_id' => $identificationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Identification updated successfully',
                'data' => $identification
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating identification', [
                'identification_id' => $identificationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating identification'
            ], 500);
        }
    }

    /**
     * Delete identification
     */
    public function destroy($employeeId, $identificationId)
    {
        DB::beginTransaction();

        try {
            $identification = EmployeeIdentification::where('identification_id', $identificationId)
                ->where('employee_id', $employeeId)
                ->first();

            if (!$identification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identification not found'
                ], 404);
            }

            $identification->delete();

            DB::commit();

            Log::info('Identification deleted', [
                'identification_id' => $identificationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Identification deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting identification', [
                'identification_id' => $identificationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting identification'
            ], 500);
        }
    }
}