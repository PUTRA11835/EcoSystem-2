<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeBasicData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EmployeeBasicDataController extends Controller
{
    /**
     * Get current user's ECI
     */
    private function getCurrentUserECI()
    {
        return session('user.eci') ?? 'System';
    }

    /**
     * Get employee basic data
     */
    public function show($employeeId)
    {
        try {
            $employee = Employee::findOrFail($employeeId);
            $basicData = EmployeeBasicData::where('employee_id', $employeeId)->first();

            if (!$basicData) {
                // Return empty structure if no basic data exists yet
                return response()->json([
                    'success' => true,
                    'message' => 'No basic data found - ready for creation',
                    'data' => [
                        'employee_id' => $employeeId,
                        'title' => null,
                        'nick_name' => null,
                        'gender' => null,
                        'religion' => null,
                        'first_name' => null,
                        'last_name' => null,
                        'search_term_1' => null,
                        'search_term_2' => null,
                        'marital_status' => null,
                        'birth_date' => null,
                        'birth_place' => null,
                        'since_date' => null,
                        'created_by' => null,
                        'created_on' => null,
                        'last_changed_by' => null,
                        'last_changed_on' => null,
                        'personnel_area' => null,
                        'personnel_subarea' => null,
                        'employee_group' => null,
                        'employee_subgroup' => null,
                        'position' => null,
                        'division' => null,
                        'department' => null,
                        'direct_supervision' => null,
                        'manager' => null,
                        'authorization_group' => null,
                        'block' => false,
                        'deletion_flag' => false,
                        'created_at' => null,
                        'updated_at' => null
                    ]
                ]);
            }

            // Format dates untuk HTML input
            $data = $basicData->toArray();
            
            // PENTING: Format date fields ke YYYY-MM-DD
            if (!empty($data['birth_date'])) {
                // Pastikan format date benar
                try {
                    $data['birth_date'] = date('Y-m-d', strtotime($data['birth_date']));
                } catch (\Exception $e) {
                    \Log::error('Error formatting birth_date');
                    $data['birth_date'] = null;
                }
            }
            
            if (!empty($data['since_date'])) {
                try {
                    $data['since_date'] = date('Y-m-d', strtotime($data['since_date']));
                } catch (\Exception $e) {
                    \Log::error('Error formatting since_date');
                    $data['since_date'] = null;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Basic data retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in EmployeeBasicDataController@show');
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving basic data'
            ], 500);
        }
    }

    /**
     * Create or update employee basic data
     */
    public function store(Request $request, $employeeId)
    {
        // Normalisasi empty string ke null untuk field enum agar nullable|in: tidak gagal
        $input = $request->all();
        foreach (['gender', 'marital_status', 'religion', 'title'] as $field) {
            if (array_key_exists($field, $input) && $input[$field] === '') {
                $input[$field] = null;
            }
        }

        $validator = Validator::make($input, [
            // Identitas Pribadi
            'title' => 'nullable|string|max:10',
            'nick_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:Male,Female',
            'religion' => 'nullable|in:Islam,Christian,Catholic,Hindu,Buddhist,Confucian',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'search_term_1' => 'nullable|string|max:255',
            'search_term_2' => 'nullable|string|max:255',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widow/Widower',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:255',
            'since_date' => 'nullable|date',

            // Informasi Pencatatan
            'created_by' => 'nullable|string|max:255',
            'created_on' => 'nullable|date',
            'last_changed_by' => 'nullable|string|max:255',
            'last_changed_on' => 'nullable|date',

            // Informasi Kepegawaian
            'personnel_area' => 'nullable|string|max:100',
            'personnel_subarea' => 'nullable|string|max:100',
            'employee_group' => 'nullable|string|max:100',
            'employee_subgroup' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:255',
            'division' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'direct_supervision' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'authorization_group' => 'nullable|string|max:100',

            // Status Administrasi
            'block' => 'nullable|boolean',
            'deletion_flag' => 'nullable|boolean',
        ], [
            'first_name.required' => 'First Name is required.',
            'first_name.max'      => 'First Name may not exceed 255 characters.',
            'gender.in'           => 'Gender must be Male or Female.',
            'marital_status.in'   => 'Marital Status must be one of: Single, Married, Divorced, Widow/Widower.',
            'religion.in'         => 'Religion value is not valid.',
            'birth_date.date'     => 'Birth Date must be a valid date.',
            'since_date.date'     => 'Since Date must be a valid date.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the following errors:',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            
            // Get current user's ECI
            $currentUserECI = $this->getCurrentUserECI();

            // Prepare data
            $basicDataInput = $request->all();
            $basicDataInput['employee_id'] = $employeeId;
            
            // Auto-generate search_term_1 and search_term_2 if not provided
            if (empty($basicDataInput['search_term_1']) && !empty($basicDataInput['first_name'])) {
                $basicDataInput['search_term_1'] = strtoupper($basicDataInput['first_name']);
            }
            
            if (empty($basicDataInput['search_term_2']) && !empty($basicDataInput['last_name'])) {
                $basicDataInput['search_term_2'] = strtoupper($basicDataInput['last_name']);
            }

            // Set default values for boolean fields
            $basicDataInput['block'] = $request->input('block', false);
            $basicDataInput['deletion_flag'] = $request->input('deletion_flag', false);

            // Check if basic data already exists
            $basicData = EmployeeBasicData::where('employee_id', $employeeId)->first();
            $isNew     = $basicData === null;

            if ($basicData) {
                // Update existing data
                $basicDataInput['last_changed_by'] = $currentUserECI;
                $basicDataInput['last_changed_on'] = now();

                $basicData->update($basicDataInput);
                $message = 'Basic data updated successfully';
            } else {
                // Create new data
                $basicDataInput['created_by'] = $currentUserECI;
                $basicDataInput['created_on'] = now();

                $basicData = EmployeeBasicData::create($basicDataInput);
                $message = 'Basic data created successfully';
            }

            DB::commit();

            // Format dates untuk response
            $responseData = $basicData->toArray();
            if (!empty($responseData['birth_date'])) {
                $responseData['birth_date'] = date('Y-m-d', strtotime($responseData['birth_date']));
            }
            if (!empty($responseData['since_date'])) {
                $responseData['since_date'] = date('Y-m-d', strtotime($responseData['since_date']));
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $responseData
            ], $isNew ? 201 : 200);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in EmployeeBasicDataController@store');
            return response()->json([
                'success' => false,
                'message' => 'Error saving basic data'
            ], 500);
        }
    }

    /**
     * Update specific fields of employee basic data
     */
    public function update(Request $request, $employeeId)
    {
        try {
            $basicData = EmployeeBasicData::where('employee_id', $employeeId)->first();

            if (!$basicData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basic data not found. Please create it first.'
                ], 404);
            }

            // Normalisasi empty string ke null untuk field enum
            $partialInput = $request->all();
            foreach (['gender', 'marital_status', 'religion', 'title'] as $field) {
                if (array_key_exists($field, $partialInput) && $partialInput[$field] === '') {
                    $partialInput[$field] = null;
                }
            }

            // Validate only provided fields
            $validator = Validator::make($partialInput, [
                'title' => 'nullable|string|max:10',
                'nick_name' => 'nullable|string|max:100',
                'gender' => 'nullable|in:Male,Female',
                'religion' => 'nullable|in:Islam,Christian,Catholic,Hindu,Buddhist,Confucian',
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'search_term_1' => 'nullable|string|max:255',
                'search_term_2' => 'nullable|string|max:255',
                'marital_status' => 'nullable|in:Single,Married,Divorced,Widow/Widower',
                'birth_date' => 'nullable|date',
                'birth_place' => 'nullable|string|max:255',
                'since_date' => 'nullable|date',
                'personnel_area' => 'nullable|string|max:100',
                'personnel_subarea' => 'nullable|string|max:100',
                'employee_group' => 'nullable|string|max:100',
                'employee_subgroup' => 'nullable|string|max:100',
                'position' => 'nullable|string|max:255',
                'division' => 'nullable|string|max:255',
                'department' => 'nullable|string|max:255',
                'direct_supervision' => 'nullable|string|max:255',
                'manager' => 'nullable|string|max:255',
                'authorization_group' => 'nullable|string|max:100',
                'block' => 'nullable|boolean',
                'deletion_flag' => 'nullable|boolean',
            ], [
                'gender.in'         => 'Gender must be Male or Female.',
                'marital_status.in' => 'Marital Status must be one of: Single, Married, Divorced, Widow/Widower.',
                'religion.in'       => 'Religion value is not valid.',
                'birth_date.date'   => 'Birth Date must be a valid date.',
                'since_date.date'   => 'Since Date must be a valid date.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please correct the following errors:',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Get current user's ECI
            $currentUserECI = $this->getCurrentUserECI();

            // Update only provided fields
            $updateData = $request->only([
                'title', 'nick_name', 'gender', 'religion',
                'first_name', 'last_name', 'search_term_1', 'search_term_2',
                'marital_status', 'birth_date', 'birth_place', 'since_date',
                'personnel_area', 'personnel_subarea', 'employee_group', 'employee_subgroup',
                'position', 'division', 'department', 'direct_supervision',
                'manager', 'authorization_group', 'block', 'deletion_flag'
            ]);

            // Auto-update search terms if names are updated
            if (isset($updateData['first_name'])) {
                $updateData['search_term_1'] = strtoupper($updateData['first_name']);
            }
            if (isset($updateData['last_name'])) {
                $updateData['search_term_2'] = strtoupper($updateData['last_name']);
            }

            // Set last changed info dengan ECI
            $updateData['last_changed_by'] = $currentUserECI;
            $updateData['last_changed_on'] = now();

            $basicData->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Basic data updated successfully',
                'data' => $basicData->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in EmployeeBasicDataController@update');
            return response()->json([
                'success' => false,
                'message' => 'Error updating basic data'
            ], 500);
        }
    }

    /**
     * Delete employee basic data
     */
    public function destroy($employeeId)
    {
        try {
            $basicData = EmployeeBasicData::where('employee_id', $employeeId)->first();

            if (!$basicData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basic data not found'
                ], 404);
            }

            DB::beginTransaction();

            $basicData->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Basic data deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error deleting basic data'
            ], 500);
        }
    }

    /**
     * Soft delete (set deletion_flag)
     */
    public function softDelete($employeeId)
    {
        try {
            $basicData = EmployeeBasicData::where('employee_id', $employeeId)->first();

            if (!$basicData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basic data not found'
                ], 404);
            }

            DB::beginTransaction();

            // Get current user's ECI
            $currentUserECI = $this->getCurrentUserECI();

            $basicData->update([
                'deletion_flag' => true,
                'last_changed_by' => $currentUserECI,
                'last_changed_on' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Basic data marked for deletion'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error marking data for deletion'
            ], 500);
        }
    }

    /**
     * Block/Unblock employee
     */
    public function toggleBlock($employeeId)
    {
        try {
            $basicData = EmployeeBasicData::where('employee_id', $employeeId)->first();

            if (!$basicData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basic data not found'
                ], 404);
            }

            DB::beginTransaction();

            // Get current user's ECI
            $currentUserECI = $this->getCurrentUserECI();

            $newBlockStatus = !$basicData->block;
            
            $basicData->update([
                'block' => $newBlockStatus,
                'last_changed_by' => $currentUserECI,
                'last_changed_on' => now()
            ]);

            DB::commit();

            $message = $newBlockStatus ? 'Employee blocked successfully' : 'Employee unblocked successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'employee_id' => $employeeId,
                    'block_status' => $newBlockStatus
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error toggling block status'
            ], 500);
        }
    }
}