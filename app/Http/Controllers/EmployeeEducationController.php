<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeEducation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeEducationController extends Controller
{
    /**
     * Get all employee education records
     */
    public function index($employeeId)
    {
        try {
            Log::info('=== FETCHING EMPLOYEE EDUCATION RECORDS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $educations = EmployeeEducation::where('employee_id', $employeeId)
                ->orderBy('graduation_year', 'desc')
                ->orderBy('education_id', 'desc')
                ->get();

            if ($educations->isEmpty()) {
                Log::info('No education records found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No education records found - ready for creation',
                    'data' => []
                ]);
            }

            Log::info('Education records retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $educations->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education records retrieved successfully',
                'data' => $educations,
                'count' => $educations->count()
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
            Log::error('Error retrieving education records', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving education records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single employee education record
     */
    public function show($employeeId, $educationId)
    {
        try {
            Log::info('=== FETCHING SINGLE EDUCATION RECORD ===', [
                'employee_id' => $employeeId,
                'education_id' => $educationId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $education = EmployeeEducation::where('employee_id', $employeeId)
                ->where('education_id', $educationId)
                ->first();

            if (!$education) {
                Log::warning('Education record not found', [
                    'employee_id' => $employeeId,
                    'education_id' => $educationId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Education record not found'
                ], 404);
            }

            Log::info('Education record retrieved successfully', [
                'employee_id' => $employeeId,
                'education_id' => $educationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education record retrieved successfully',
                'data' => $education
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
            Log::error('Error retrieving education record', [
                'employee_id' => $employeeId,
                'education_id' => $educationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving education record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new employee education record
     */
    public function store(Request $request, $employeeId)
    {
        Log::info('=== CREATING NEW EDUCATION RECORD ===', [
            'employee_id' => $employeeId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // 🎓 Education Information
            'education_type' => 'required|string|max:50',
            'institute_place' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'degree' => 'nullable|string|max:255',
            'duration_of_course' => 'nullable|string|max:100',
            'final_grade' => 'nullable|string|max:50',
            'branch_of_study' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            
            // 📅 Academic Period
            'start_year' => 'nullable|integer|min:1900|max:2100',
            'graduation_year' => 'nullable|integer|min:1900|max:2100|gte:start_year',
            
            // ⏳ Validity Period
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // 📎 Attachments
            'attachment_name' => 'nullable|string|max:255',
            'attachment_verify_link' => 'nullable|url|max:500',
            'attachment_drive_link' => 'nullable|url|max:500',
        ], [
            'education_type.required' => 'Education level is required',
            'institute_place.required' => 'Institution name is required',
            'graduation_year.gte' => 'Graduation year must be greater than or equal to start year',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'attachment_verify_link.url' => 'Verify link must be a valid URL',
            'attachment_drive_link.url' => 'Drive link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for education record', [
                'employee_id' => $employeeId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Prepare data
            $educationInput = $request->only([
                'education_type', 'institute_place', 'country', 'degree',
                'duration_of_course', 'final_grade', 'branch_of_study', 'unit',
                'start_year', 'graduation_year',
                'valid_from', 'valid_to',
                'attachment_name', 'attachment_verify_link', 'attachment_drive_link'
            ]);
            
            $educationInput['employee_id'] = $employeeId;

            // Create new education record
            $education = EmployeeEducation::create($educationInput);
            
            Log::info('Education record created successfully', [
                'employee_id' => $employeeId,
                'education_id' => $education->education_id,
                'education_type' => $education->education_type,
                'institute' => $education->institute_place
            ]);

            DB::commit();

            Log::info('=== EDUCATION RECORD CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'education_id' => $education->education_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education record created successfully',
                'data' => $education
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
            
            Log::error('Error creating education record', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating education record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update employee education record
     */
    public function update(Request $request, $employeeId, $educationId)
    {
        Log::info('=== UPDATING EDUCATION RECORD ===', [
            'employee_id' => $employeeId,
            'education_id' => $educationId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // 🎓 Education Information
            'education_type' => 'required|string|max:50',
            'institute_place' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'degree' => 'nullable|string|max:255',
            'duration_of_course' => 'nullable|string|max:100',
            'final_grade' => 'nullable|string|max:50',
            'branch_of_study' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            
            // 📅 Academic Period
            'start_year' => 'nullable|integer|min:1900|max:2100',
            'graduation_year' => 'nullable|integer|min:1900|max:2100|gte:start_year',
            
            // ⏳ Validity Period
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // 📎 Attachments
            'attachment_name' => 'nullable|string|max:255',
            'attachment_verify_link' => 'nullable|url|max:500',
            'attachment_drive_link' => 'nullable|url|max:500',
        ], [
            'education_type.required' => 'Education level is required',
            'institute_place.required' => 'Institution name is required',
            'graduation_year.gte' => 'Graduation year must be greater than or equal to start year',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'attachment_verify_link.url' => 'Verify link must be a valid URL',
            'attachment_drive_link.url' => 'Drive link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for education record update', [
                'employee_id' => $employeeId,
                'education_id' => $educationId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $education = EmployeeEducation::where('employee_id', $employeeId)
                ->where('education_id', $educationId)
                ->first();

            if (!$education) {
                Log::warning('Education record not found for update', [
                    'employee_id' => $employeeId,
                    'education_id' => $educationId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Education record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->only([
                'education_type', 'institute_place', 'country', 'degree',
                'duration_of_course', 'final_grade', 'branch_of_study', 'unit',
                'start_year', 'graduation_year',
                'valid_from', 'valid_to',
                'attachment_name', 'attachment_verify_link', 'attachment_drive_link'
            ]);

            // Update education record
            $education->update($updateData);
            
            Log::info('Education record updated successfully', [
                'employee_id' => $employeeId,
                'education_id' => $educationId
            ]);

            DB::commit();

            Log::info('=== EDUCATION RECORD UPDATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'education_id' => $educationId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education record updated successfully',
                'data' => $education->fresh()
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
            
            Log::error('Error updating education record', [
                'employee_id' => $employeeId,
                'education_id' => $educationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating education record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employee education record
     */
    public function destroy($employeeId, $educationId)
    {
        Log::info('=== DELETING EDUCATION RECORD ===', [
            'employee_id' => $employeeId,
            'education_id' => $educationId
        ]);

        try {
            DB::beginTransaction();

            $education = EmployeeEducation::where('employee_id', $employeeId)
                ->where('education_id', $educationId)
                ->first();

            if (!$education) {
                Log::warning('Education record not found for deletion', [
                    'employee_id' => $employeeId,
                    'education_id' => $educationId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Education record not found'
                ], 404);
            }

            $educationType = $education->education_type;
            $institutePlace = $education->institute_place;
            $education->delete();

            DB::commit();

            Log::info('=== EDUCATION RECORD DELETED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'education_id' => $educationId,
                'education_type' => $educationType,
                'institute' => $institutePlace
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting education record', [
                'employee_id' => $employeeId,
                'education_id' => $educationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting education record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get education statistics for employee
     */
    public function statistics($employeeId)
    {
        try {
            Log::info('=== FETCHING EDUCATION STATISTICS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            
            $statistics = [
                'total' => EmployeeEducation::where('employee_id', $employeeId)->count(),
                'valid' => EmployeeEducation::where('employee_id', $employeeId)->valid()->count(),
                'by_type' => EmployeeEducation::where('employee_id', $employeeId)
                    ->select('education_type', DB::raw('count(*) as count'))
                    ->groupBy('education_type')
                    ->orderBy('education_type', 'desc')
                    ->get(),
                'highest_education' => EmployeeEducation::where('employee_id', $employeeId)
                    ->orderByRaw("FIELD(education_type, 'S3', 'S2', 'S1', 'D4', 'D3', 'D2', 'D1', 'SMK', 'SMA', 'SMP', 'SD')")
                    ->first(),
                'latest_graduation' => EmployeeEducation::where('employee_id', $employeeId)
                    ->orderBy('graduation_year', 'desc')
                    ->first(),
                'with_attachments' => EmployeeEducation::where('employee_id', $employeeId)
                    ->where(function($query) {
                        $query->whereNotNull('attachment_verify_link')
                              ->orWhereNotNull('attachment_drive_link');
                    })
                    ->count(),
            ];

            Log::info('Education statistics retrieved successfully', [
                'employee_id' => $employeeId,
                'statistics' => $statistics
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education statistics retrieved successfully',
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
            Log::error('Error retrieving education statistics', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving education statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get education records by type
     */
    public function getByType($employeeId, $type)
    {
        try {
            Log::info('=== FETCHING EDUCATION BY TYPE ===', [
                'employee_id' => $employeeId,
                'type' => $type
            ]);

            $employee = Employee::findOrFail($employeeId);
            $educations = EmployeeEducation::where('employee_id', $employeeId)
                ->byType($type)
                ->orderBy('graduation_year', 'desc')
                ->get();

            Log::info('Education records by type retrieved successfully', [
                'employee_id' => $employeeId,
                'type' => $type,
                'count' => $educations->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Education records retrieved successfully',
                'data' => $educations,
                'count' => $educations->count()
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
            Log::error('Error retrieving education by type', [
                'employee_id' => $employeeId,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving education records: ' . $e->getMessage()
            ], 500);
        }
    }
}