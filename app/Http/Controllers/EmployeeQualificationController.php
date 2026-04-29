<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeQualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeQualificationController extends Controller
{
    /**
     * Get all employee qualification records
     */
    public function index($employeeId)
    {
        try {
            Log::info('=== FETCHING EMPLOYEE QUALIFICATION RECORDS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $qualifications = EmployeeQualification::where('employee_id', $employeeId)
                ->orderBy('first_year', 'desc')
                ->orderBy('qualification_id', 'desc')
                ->get();

            if ($qualifications->isEmpty()) {
                Log::info('No qualification records found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No qualification records found - ready for creation',
                    'data' => []
                ]);
            }

            Log::info('Qualification records retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $qualifications->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification records retrieved successfully',
                'data' => $qualifications,
                'count' => $qualifications->count()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Employee not found', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error retrieving qualification records', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving qualification records'
            ], 500);
        }
    }

    /**
     * Get single employee qualification record
     */
    public function show($employeeId, $qualificationId)
    {
        try {
            Log::info('=== FETCHING SINGLE QUALIFICATION RECORD ===', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $qualification = EmployeeQualification::where('employee_id', $employeeId)
                ->where('qualification_id', $qualificationId)
                ->first();

            if (!$qualification) {
                Log::warning('Qualification record not found', [
                    'employee_id' => $employeeId,
                    'qualification_id' => $qualificationId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Qualification record not found'
                ], 404);
            }

            Log::info('Qualification record retrieved successfully', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification record retrieved successfully',
                'data' => $qualification
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Employee not found', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error retrieving qualification record', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving qualification record'
            ], 500);
        }
    }

    /**
     * Create new employee qualification record
     */
    public function store(Request $request, $employeeId)
    {
        Log::info('=== CREATING NEW QUALIFICATION RECORD ===', [
            'employee_id' => $employeeId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // 🎯 Qualification Details
            'qualification_type' => 'required|string|max:50',
            'module' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:100',
            'qualification_level' => 'nullable|string|max:100',
            'first_year' => 'nullable|string|max:50',
            
            // 📋 Flags
            'certified' => 'boolean',
            'dpm' => 'boolean',
            'dsm' => 'boolean',
            
            // ⏳ Validity Period
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // 📎 Attachments
            'verify_link' => 'nullable|url|max:500',
            'drive_link' => 'nullable|url|max:500',
        ], [
            'qualification_type.required' => 'Qualification type is required',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'verify_link.url' => 'Verify link must be a valid URL',
            'drive_link.url' => 'Drive link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for qualification record', [
                'employee_id' => $employeeId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee qualification record data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Prepare data
            $qualificationInput = $request->only([
                'qualification_type', 'module', 'language', 'qualification_level',
                'first_year', 'certified', 'dpm', 'dsm',
                'valid_from', 'valid_to',
                'verify_link', 'drive_link'
            ]);
            
            // Convert boolean fields
            $qualificationInput['certified'] = $request->boolean('certified');
            $qualificationInput['dpm'] = $request->boolean('dpm');
            $qualificationInput['dsm'] = $request->boolean('dsm');
            
            $qualificationInput['employee_id'] = $employeeId;

            // Create new qualification record
            $qualification = EmployeeQualification::create($qualificationInput);
            
            Log::info('Qualification record created successfully', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualification->qualification_id,
                'qualification_type' => $qualification->qualification_type
            ]);

            DB::commit();

            Log::info('=== QUALIFICATION RECORD CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualification->qualification_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification record created successfully',
                'data' => $qualification
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            Log::warning('Employee not found during create', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating qualification record', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating qualification record'
            ], 500);
        }
    }

    /**
     * Update employee qualification record
     */
    public function update(Request $request, $employeeId, $qualificationId)
    {
        Log::info('=== UPDATING QUALIFICATION RECORD ===', [
            'employee_id' => $employeeId,
            'qualification_id' => $qualificationId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // 🎯 Qualification Details
            'qualification_type' => 'required|string|max:50',
            'module' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:100',
            'qualification_level' => 'nullable|string|max:100',
            'first_year' => 'nullable|string|max:50',
            
            // 📋 Flags
            'certified' => 'boolean',
            'dpm' => 'boolean',
            'dsm' => 'boolean',
            
            // ⏳ Validity Period
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // 📎 Attachments
            'verify_link' => 'nullable|url|max:500',
            'drive_link' => 'nullable|url|max:500',
        ], [
            'qualification_type.required' => 'Qualification type is required',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'verify_link.url' => 'Verify link must be a valid URL',
            'drive_link.url' => 'Drive link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for qualification record update', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee qualification record data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $qualification = EmployeeQualification::where('employee_id', $employeeId)
                ->where('qualification_id', $qualificationId)
                ->first();

            if (!$qualification) {
                Log::warning('Qualification record not found for update', [
                    'employee_id' => $employeeId,
                    'qualification_id' => $qualificationId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Qualification record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->only([
                'qualification_type', 'module', 'language', 'qualification_level',
                'first_year', 'certified', 'dpm', 'dsm',
                'valid_from', 'valid_to',
                'verify_link', 'drive_link'
            ]);
            
            // Convert boolean fields
            $updateData['certified'] = $request->boolean('certified');
            $updateData['dpm'] = $request->boolean('dpm');
            $updateData['dsm'] = $request->boolean('dsm');

            // Update qualification record
            $qualification->update($updateData);
            
            Log::info('Qualification record updated successfully', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId
            ]);

            DB::commit();

            Log::info('=== QUALIFICATION RECORD UPDATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification record updated successfully',
                'data' => $qualification->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            Log::warning('Employee not found during update', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating qualification record', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating qualification record'
            ], 500);
        }
    }

    /**
     * Delete employee qualification record
     */
    public function destroy($employeeId, $qualificationId)
    {
        Log::info('=== DELETING QUALIFICATION RECORD ===', [
            'employee_id' => $employeeId,
            'qualification_id' => $qualificationId
        ]);

        try {
            DB::beginTransaction();

            $qualification = EmployeeQualification::where('employee_id', $employeeId)
                ->where('qualification_id', $qualificationId)
                ->first();

            if (!$qualification) {
                Log::warning('Qualification record not found for deletion', [
                    'employee_id' => $employeeId,
                    'qualification_id' => $qualificationId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Qualification record not found'
                ], 404);
            }

            $qualificationType = $qualification->qualification_type;
            $moduleOrLanguage = $qualification->module ?? $qualification->language;
            $qualification->delete();

            DB::commit();

            Log::info('=== QUALIFICATION RECORD DELETED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId,
                'qualification_type' => $qualificationType,
                'module_or_language' => $moduleOrLanguage
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting qualification record', [
                'employee_id' => $employeeId,
                'qualification_id' => $qualificationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting qualification record'
            ], 500);
        }
    }

    /**
     * Get qualification statistics for employee
     */
    public function statistics($employeeId)
    {
        try {
            Log::info('=== FETCHING QUALIFICATION STATISTICS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            
            $statistics = [
                'total' => EmployeeQualification::where('employee_id', $employeeId)->count(),
                'certified' => EmployeeQualification::where('employee_id', $employeeId)
                    ->where('certified', true)->count(),
                'dpm' => EmployeeQualification::where('employee_id', $employeeId)
                    ->where('dpm', true)->count(),
                'dsm' => EmployeeQualification::where('employee_id', $employeeId)
                    ->where('dsm', true)->count(),
                'by_type' => EmployeeQualification::where('employee_id', $employeeId)
                    ->select('qualification_type', DB::raw('count(*) as count'))
                    ->groupBy('qualification_type')
                    ->orderBy('count', 'desc')
                    ->get(),
                'valid' => EmployeeQualification::where('employee_id', $employeeId)
                    ->where(function($query) {
                        $query->whereNull('valid_to')
                              ->orWhere('valid_to', '>=', now());
                    })
                    ->count(),
                'expired' => EmployeeQualification::where('employee_id', $employeeId)
                    ->where('valid_to', '<', now())
                    ->whereNotNull('valid_to')
                    ->count(),
                'expiring_soon' => EmployeeQualification::where('employee_id', $employeeId)
                    ->whereBetween('valid_to', [now(), now()->addDays(30)])
                    ->count(),
                'with_attachments' => EmployeeQualification::where('employee_id', $employeeId)
                    ->where(function($query) {
                        $query->whereNotNull('verify_link')
                              ->orWhereNotNull('drive_link');
                    })
                    ->count(),
            ];

            Log::info('Qualification statistics retrieved successfully', [
                'employee_id' => $employeeId,
                'statistics' => $statistics
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification statistics retrieved successfully',
                'data' => $statistics
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Employee not found', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error retrieving qualification statistics', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving qualification statistics'
            ], 500);
        }
    }

    /**
     * Get qualification records by type
     */
    public function getByType($employeeId, $type)
    {
        try {
            Log::info('=== FETCHING QUALIFICATION BY TYPE ===', [
                'employee_id' => $employeeId,
                'type' => $type
            ]);

            $employee = Employee::findOrFail($employeeId);
            $qualifications = EmployeeQualification::where('employee_id', $employeeId)
                ->where('qualification_type', $type)
                ->orderBy('first_year', 'desc')
                ->get();

            Log::info('Qualification records by type retrieved successfully', [
                'employee_id' => $employeeId,
                'type' => $type,
                'count' => $qualifications->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Qualification records retrieved successfully',
                'data' => $qualifications,
                'count' => $qualifications->count()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Employee not found', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error retrieving qualification by type', [
                'employee_id' => $employeeId,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving qualification records'
            ], 500);
        }
    }

    /**
     * Get expired qualifications
     */
    public function getExpired($employeeId)
    {
        try {
            Log::info('=== FETCHING EXPIRED QUALIFICATIONS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $qualifications = EmployeeQualification::where('employee_id', $employeeId)
                ->where('valid_to', '<', now())
                ->whereNotNull('valid_to')
                ->orderBy('valid_to', 'desc')
                ->get();

            Log::info('Expired qualifications retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $qualifications->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expired qualifications retrieved successfully',
                'data' => $qualifications,
                'count' => $qualifications->count()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Employee not found', [
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error retrieving expired qualifications', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving expired qualifications'
            ], 500);
        }
    }
}