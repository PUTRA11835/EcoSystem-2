<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeContractController extends Controller
{
    /**
     * Get all employee contract records
     */
    public function index($employeeId)
    {
        try {
            Log::info('=== FETCHING EMPLOYEE CONTRACT RECORDS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $contracts = EmployeeContract::where('employee_id', $employeeId)
                ->orderBy('start_date', 'desc')
                ->orderBy('contract_id', 'desc')
                ->get();

            if ($contracts->isEmpty()) {
                Log::info('No contract records found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No contract records found - ready for creation',
                    'data' => []
                ]);
            }

            Log::info('Contract records retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $contracts->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract records retrieved successfully',
                'data' => $contracts,
                'count' => $contracts->count()
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
            Log::error('Error retrieving contract records', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contract records'
            ], 500);
        }
    }

    /**
     * Get single employee contract record
     */
    public function show($employeeId, $contractId)
    {
        try {
            Log::info('=== FETCHING SINGLE CONTRACT RECORD ===', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $contract = EmployeeContract::where('employee_id', $employeeId)
                ->where('contract_id', $contractId)
                ->first();

            if (!$contract) {
                Log::warning('Contract record not found', [
                    'employee_id' => $employeeId,
                    'contract_id' => $contractId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Contract record not found'
                ], 404);
            }

            Log::info('Contract record retrieved successfully', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract record retrieved successfully',
                'data' => $contract
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
            Log::error('Error retrieving contract record', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contract record'
            ], 500);
        }
    }

    /**
     * Create new employee contract record
     */
    public function store(Request $request, $employeeId)
    {
        Log::info('=== CREATING NEW CONTRACT RECORD ===', [
            'employee_id' => $employeeId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // Contract Details
            'contract_number' => 'required|string|max:100|unique:employee_contract,contract_number',
            'contract_name' => 'required|string|max:255',
            'contract_type' => 'required|string|max:50',
            'position' => 'required|string|max:255',
            'contract_date' => 'nullable|date',

            // Employment Period & Compensation
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'salary' => 'nullable|numeric|min:0',

            // Status
            'is_active' => 'boolean',

            // Attachments
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ], [
            'contract_number.required' => 'Contract number is required',
            'contract_number.unique' => 'Contract number already exists, please use a different number',
            'contract_name.required' => 'Contract name is required',
            'contract_type.required' => 'Contract type is required',
            'position.required' => 'Position is required',
            'start_date.required' => 'Start date is required',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'salary.numeric' => 'Salary must be a valid number',
            'salary.min' => 'Salary must be at least 0',
            'drive_link.url' => 'Drive link must be a valid URL',
            'verify_link.url' => 'Verify link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for contract record', [
                'employee_id' => $employeeId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Prepare data
            $contractInput = $request->only([
                'contract_number', 'contract_name', 'contract_type', 'position',
                'contract_date', 'start_date', 'end_date', 'salary',
                'is_active', 'drive_link', 'verify_link'
            ]);
            
            // Convert boolean field
            $contractInput['is_active'] = $request->boolean('is_active');
            
            // If this contract is set as active, deactivate all other contracts
            if ($contractInput['is_active']) {
                EmployeeContract::where('employee_id', $employeeId)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                    
                Log::info('Deactivated other contracts for employee', [
                    'employee_id' => $employeeId
                ]);
            }
            
            $contractInput['employee_id'] = $employeeId;

            // Create new contract record
            $contract = EmployeeContract::create($contractInput);
            
            Log::info('Contract record created successfully', [
                'employee_id' => $employeeId,
                'contract_id' => $contract->contract_id,
                'contract_name' => $contract->contract_name,
                'is_active' => $contract->is_active
            ]);

            DB::commit();

            Log::info('=== CONTRACT RECORD CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'contract_id' => $contract->contract_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract record created successfully',
                'data' => $contract
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
            
            Log::error('Error creating contract record', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating contract record'
            ], 500);
        }
    }

    /**
     * Update employee contract record
     */
    public function update(Request $request, $employeeId, $contractId)
    {
        Log::info('=== UPDATING CONTRACT RECORD ===', [
            'employee_id' => $employeeId,
            'contract_id' => $contractId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // Contract Details
            'contract_number' => 'required|string|max:100|unique:employee_contract,contract_number,' . $contractId . ',contract_id',
            'contract_name' => 'required|string|max:255',
            'contract_type' => 'required|string|max:50',
            'position' => 'required|string|max:255',
            'contract_date' => 'nullable|date',

            // Employment Period & Compensation
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'salary' => 'nullable|numeric|min:0',

            // Status
            'is_active' => 'boolean',

            // Attachments
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ], [
            'contract_number.required' => 'Contract number is required',
            'contract_number.unique' => 'Contract number already exists, please use a different number',
            'contract_name.required' => 'Contract name is required',
            'contract_type.required' => 'Contract type is required',
            'position.required' => 'Position is required',
            'start_date.required' => 'Start date is required',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'salary.numeric' => 'Salary must be a valid number',
            'salary.min' => 'Salary must be at least 0',
            'drive_link.url' => 'Drive link must be a valid URL',
            'verify_link.url' => 'Verify link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for contract record update', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $contract = EmployeeContract::where('employee_id', $employeeId)
                ->where('contract_id', $contractId)
                ->first();

            if (!$contract) {
                Log::warning('Contract record not found for update', [
                    'employee_id' => $employeeId,
                    'contract_id' => $contractId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Contract record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->only([
                'contract_number', 'contract_name', 'contract_type', 'position',
                'contract_date', 'start_date', 'end_date', 'salary',
                'is_active', 'drive_link', 'verify_link'
            ]);
            
            // Convert boolean field
            $updateData['is_active'] = $request->boolean('is_active');
            
            // If this contract is set as active, deactivate all other contracts
            if ($updateData['is_active']) {
                EmployeeContract::where('employee_id', $employeeId)
                    ->where('contract_id', '!=', $contractId)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
                    
                Log::info('Deactivated other contracts for employee', [
                    'employee_id' => $employeeId,
                    'current_contract_id' => $contractId
                ]);
            }

            // Update contract record
            $contract->update($updateData);
            
            Log::info('Contract record updated successfully', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId
            ]);

            DB::commit();

            Log::info('=== CONTRACT RECORD UPDATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract record updated successfully',
                'data' => $contract->fresh()
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
            
            Log::error('Error updating contract record', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating contract record'
            ], 500);
        }
    }

    /**
     * Delete employee contract record
     */
    public function destroy($employeeId, $contractId)
    {
        Log::info('=== DELETING CONTRACT RECORD ===', [
            'employee_id' => $employeeId,
            'contract_id' => $contractId
        ]);

        try {
            DB::beginTransaction();

            $contract = EmployeeContract::where('employee_id', $employeeId)
                ->where('contract_id', $contractId)
                ->first();

            if (!$contract) {
                Log::warning('Contract record not found for deletion', [
                    'employee_id' => $employeeId,
                    'contract_id' => $contractId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Contract record not found'
                ], 404);
            }

            $contractName = $contract->contract_name;
            $contractNumber = $contract->contract_number;
            $contract->delete();

            DB::commit();

            Log::info('=== CONTRACT RECORD DELETED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'contract_name' => $contractName,
                'contract_number' => $contractNumber
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting contract record', [
                'employee_id' => $employeeId,
                'contract_id' => $contractId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting contract record'
            ], 500);
        }
    }

    /**
     * Get contract statistics for employee
     */
    public function statistics($employeeId)
    {
        try {
            Log::info('=== FETCHING CONTRACT STATISTICS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            
            $statistics = [
                'total' => EmployeeContract::where('employee_id', $employeeId)->count(),
                'active' => EmployeeContract::where('employee_id', $employeeId)
                    ->where('is_active', true)->count(),
                'by_type' => EmployeeContract::where('employee_id', $employeeId)
                    ->select('contract_type', DB::raw('count(*) as count'))
                    ->groupBy('contract_type')
                    ->orderBy('count', 'desc')
                    ->get(),
                'permanent' => EmployeeContract::where('employee_id', $employeeId)
                    ->whereNull('end_date')
                    ->count(),
                'expired' => EmployeeContract::where('employee_id', $employeeId)
                    ->where('end_date', '<', now())
                    ->whereNotNull('end_date')
                    ->count(),
                'expiring_soon' => EmployeeContract::where('employee_id', $employeeId)
                    ->whereBetween('end_date', [now(), now()->addDays(30)])
                    ->count(),
                'with_attachments' => EmployeeContract::where('employee_id', $employeeId)
                    ->where(function($query) {
                        $query->whereNotNull('verify_link')
                              ->orWhereNotNull('drive_link');
                    })
                    ->count(),
                'total_salary' => EmployeeContract::where('employee_id', $employeeId)
                    ->sum('salary'),
                'average_salary' => EmployeeContract::where('employee_id', $employeeId)
                    ->avg('salary'),
            ];

            Log::info('Contract statistics retrieved successfully', [
                'employee_id' => $employeeId,
                'statistics' => $statistics
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract statistics retrieved successfully',
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
            Log::error('Error retrieving contract statistics', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contract statistics'
            ], 500);
        }
    }

    /**
     * Get contract records by type
     */
    public function getByType($employeeId, $type)
    {
        try {
            Log::info('=== FETCHING CONTRACT BY TYPE ===', [
                'employee_id' => $employeeId,
                'type' => $type
            ]);

            $employee = Employee::findOrFail($employeeId);
            $contracts = EmployeeContract::where('employee_id', $employeeId)
                ->where('contract_type', $type)
                ->orderBy('start_date', 'desc')
                ->get();

            Log::info('Contract records by type retrieved successfully', [
                'employee_id' => $employeeId,
                'type' => $type,
                'count' => $contracts->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contract records retrieved successfully',
                'data' => $contracts,
                'count' => $contracts->count()
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
            Log::error('Error retrieving contract by type', [
                'employee_id' => $employeeId,
                'type' => $type,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contract records'
            ], 500);
        }
    }

    /**
     * Get expired contracts
     */
    public function getExpired($employeeId)
    {
        try {
            Log::info('=== FETCHING EXPIRED CONTRACTS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $contracts = EmployeeContract::where('employee_id', $employeeId)
                ->where('end_date', '<', now())
                ->whereNotNull('end_date')
                ->orderBy('end_date', 'desc')
                ->get();

            Log::info('Expired contracts retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $contracts->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expired contracts retrieved successfully',
                'data' => $contracts,
                'count' => $contracts->count()
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
            Log::error('Error retrieving expired contracts', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving expired contracts'
            ], 500);
        }
    }

    /**
     * Get active contract
     */
    public function getActive($employeeId)
    {
        try {
            Log::info('=== FETCHING ACTIVE CONTRACT ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $contract = EmployeeContract::where('employee_id', $employeeId)
                ->where('is_active', true)
                ->first();

            if (!$contract) {
                Log::info('No active contract found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No active contract found',
                    'data' => null
                ]);
            }

            Log::info('Active contract retrieved successfully', [
                'employee_id' => $employeeId,
                'contract_id' => $contract->contract_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Active contract retrieved successfully',
                'data' => $contract
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
            Log::error('Error retrieving active contract', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving active contract'
            ], 500);
        }
    }

    /**
     * Get contracts expiring soon (within 30 days)
     */
    public function getExpiringSoon($employeeId)
    {
        try {
            Log::info('=== FETCHING CONTRACTS EXPIRING SOON ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $contracts = EmployeeContract::where('employee_id', $employeeId)
                ->whereBetween('end_date', [now(), now()->addDays(30)])
                ->orderBy('end_date', 'asc')
                ->get();

            Log::info('Contracts expiring soon retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $contracts->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contracts expiring soon retrieved successfully',
                'data' => $contracts,
                'count' => $contracts->count()
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
            Log::error('Error retrieving contracts expiring soon', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contracts expiring soon'
            ], 500);
        }
    }
}