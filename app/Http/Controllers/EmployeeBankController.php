<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeBankController extends Controller
{
    /**
     * Get all employee bank records
     */
    public function index($employeeId)
    {
        try {
            Log::info('=== FETCHING EMPLOYEE BANK RECORDS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $banks = EmployeeBank::where('employee_id', $employeeId)
                ->orderBy('bank_id', 'desc')
                ->get();

            if ($banks->isEmpty()) {
                Log::info('No bank records found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No bank records found - ready for creation',
                    'data' => []
                ]);
            }

            Log::info('Bank records retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank records retrieved successfully',
                'data' => $banks,
                'count' => $banks->count()
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
            Log::error('Error retrieving bank records', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single employee bank record
     */
    public function show($employeeId, $bankId)
    {
        try {
            Log::info('=== FETCHING SINGLE BANK RECORD ===', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $bank = EmployeeBank::where('employee_id', $employeeId)
                ->where('bank_id', $bankId)
                ->first();

            if (!$bank) {
                Log::warning('Bank record not found', [
                    'employee_id' => $employeeId,
                    'bank_id' => $bankId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bank record not found'
                ], 404);
            }

            Log::info('Bank record retrieved successfully', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank record retrieved successfully',
                'data' => $bank
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
            Log::error('Error retrieving bank record', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new employee bank record
     */
    public function store(Request $request, $employeeId)
    {
        Log::info('=== CREATING NEW BANK RECORD ===', [
            'employee_id' => $employeeId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // Bank Details
            'bank_name' => 'required|string|max:255',
            'bank_key' => 'nullable|string|max:50',
            'account_number' => 'required|string|max:50|unique:employee_bank,account_number',
            'account_holder' => 'nullable|string|max:255',
            
            // Validity Period
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // Attachments
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ], [
            'bank_name.required' => 'Bank name is required',
            'account_number.required' => 'Account number is required',
            'account_number.unique' => 'This account number already exists',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'drive_link.url' => 'Drive link must be a valid URL',
            'verify_link.url' => 'Verify link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for bank record', [
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
            $bankInput = $request->only([
                'bank_name', 'bank_key', 'account_number', 'account_holder',
                'valid_from', 'valid_to', 'drive_link', 'verify_link'
            ]);
            
            $bankInput['employee_id'] = $employeeId;

            // Create new bank record
            $bank = EmployeeBank::create($bankInput);
            
            Log::info('Bank record created successfully', [
                'employee_id' => $employeeId,
                'bank_id' => $bank->bank_id,
                'bank_name' => $bank->bank_name
            ]);

            DB::commit();

            Log::info('=== BANK RECORD CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'bank_id' => $bank->bank_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank record created successfully',
                'data' => $bank
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
            
            Log::error('Error creating bank record', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating bank record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update employee bank record
     */
    public function update(Request $request, $employeeId, $bankId)
    {
        Log::info('=== UPDATING BANK RECORD ===', [
            'employee_id' => $employeeId,
            'bank_id' => $bankId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // Bank Details
            'bank_name' => 'required|string|max:255',
            'bank_key' => 'nullable|string|max:50',
            'account_number' => 'required|string|max:50|unique:employee_bank,account_number,' . $bankId . ',bank_id',
            'account_holder' => 'nullable|string|max:255',
            
            // Validity Period
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // Attachments
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ], [
            'bank_name.required' => 'Bank name is required',
            'account_number.required' => 'Account number is required',
            'account_number.unique' => 'This account number already exists',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'drive_link.url' => 'Drive link must be a valid URL',
            'verify_link.url' => 'Verify link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for bank record update', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId,
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
            $bank = EmployeeBank::where('employee_id', $employeeId)
                ->where('bank_id', $bankId)
                ->first();

            if (!$bank) {
                Log::warning('Bank record not found for update', [
                    'employee_id' => $employeeId,
                    'bank_id' => $bankId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bank record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->only([
                'bank_name', 'bank_key', 'account_number', 'account_holder',
                'valid_from', 'valid_to', 'drive_link', 'verify_link'
            ]);

            // Update bank record
            $bank->update($updateData);
            
            Log::info('Bank record updated successfully', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId
            ]);

            DB::commit();

            Log::info('=== BANK RECORD UPDATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank record updated successfully',
                'data' => $bank->fresh()
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
            
            Log::error('Error updating bank record', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating bank record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employee bank record
     */
    public function destroy($employeeId, $bankId)
    {
        Log::info('=== DELETING BANK RECORD ===', [
            'employee_id' => $employeeId,
            'bank_id' => $bankId
        ]);

        try {
            DB::beginTransaction();

            $bank = EmployeeBank::where('employee_id', $employeeId)
                ->where('bank_id', $bankId)
                ->first();

            if (!$bank) {
                Log::warning('Bank record not found for deletion', [
                    'employee_id' => $employeeId,
                    'bank_id' => $bankId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Bank record not found'
                ], 404);
            }

            $bankName = $bank->bank_name;
            $accountNumber = $bank->account_number;
            $bank->delete();

            DB::commit();

            Log::info('=== BANK RECORD DELETED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId,
                'bank_name' => $bankName,
                'account_number' => $accountNumber
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting bank record', [
                'employee_id' => $employeeId,
                'bank_id' => $bankId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting bank record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank statistics for employee
     */
    public function statistics($employeeId)
    {
        try {
            Log::info('=== FETCHING BANK STATISTICS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            
            $statistics = [
                'total' => EmployeeBank::where('employee_id', $employeeId)->count(),
                'by_bank' => EmployeeBank::where('employee_id', $employeeId)
                    ->select('bank_name', DB::raw('count(*) as count'))
                    ->groupBy('bank_name')
                    ->orderBy('count', 'desc')
                    ->get(),
                'valid' => EmployeeBank::where('employee_id', $employeeId)
                    ->valid()
                    ->count(),
                'expired' => EmployeeBank::where('employee_id', $employeeId)
                    ->expired()
                    ->count(),
                'expiring_soon' => EmployeeBank::where('employee_id', $employeeId)
                    ->expiringSoon()
                    ->count(),
                'with_attachments' => EmployeeBank::where('employee_id', $employeeId)
                    ->where(function($query) {
                        $query->whereNotNull('verify_link')
                              ->orWhereNotNull('drive_link');
                    })
                    ->count(),
            ];

            Log::info('Bank statistics retrieved successfully', [
                'employee_id' => $employeeId,
                'statistics' => $statistics
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank statistics retrieved successfully',
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
            Log::error('Error retrieving bank statistics', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank records by bank name
     */
    public function getByBank($employeeId, $bankName)
    {
        try {
            Log::info('=== FETCHING BANK BY NAME ===', [
                'employee_id' => $employeeId,
                'bank_name' => $bankName
            ]);

            $employee = Employee::findOrFail($employeeId);
            $banks = EmployeeBank::where('employee_id', $employeeId)
                ->where('bank_name', 'LIKE', '%' . $bankName . '%')
                ->orderBy('bank_id', 'desc')
                ->get();

            Log::info('Bank records by name retrieved successfully', [
                'employee_id' => $employeeId,
                'bank_name' => $bankName,
                'count' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank records retrieved successfully',
                'data' => $banks,
                'count' => $banks->count()
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
            Log::error('Error retrieving bank by name', [
                'employee_id' => $employeeId,
                'bank_name' => $bankName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get expired bank accounts
     */
    public function getExpired($employeeId)
    {
        try {
            Log::info('=== FETCHING EXPIRED BANK ACCOUNTS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $banks = EmployeeBank::where('employee_id', $employeeId)
                ->expired()
                ->orderBy('valid_to', 'desc')
                ->get();

            Log::info('Expired bank accounts retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expired bank accounts retrieved successfully',
                'data' => $banks,
                'count' => $banks->count()
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
            Log::error('Error retrieving expired bank accounts', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving expired bank accounts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get valid bank accounts
     */
    public function getValid($employeeId)
    {
        try {
            Log::info('=== FETCHING VALID BANK ACCOUNTS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $banks = EmployeeBank::where('employee_id', $employeeId)
                ->valid()
                ->orderBy('bank_id', 'desc')
                ->get();

            Log::info('Valid bank accounts retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Valid bank accounts retrieved successfully',
                'data' => $banks,
                'count' => $banks->count()
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
            Log::error('Error retrieving valid bank accounts', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving valid bank accounts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bank accounts expiring soon (within 30 days)
     */
    public function getExpiringSoon($employeeId)
    {
        try {
            Log::info('=== FETCHING BANK ACCOUNTS EXPIRING SOON ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $banks = EmployeeBank::where('employee_id', $employeeId)
                ->expiringSoon()
                ->orderBy('valid_to', 'asc')
                ->get();

            Log::info('Bank accounts expiring soon retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $banks->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bank accounts expiring soon retrieved successfully',
                'data' => $banks,
                'count' => $banks->count()
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
            Log::error('Error retrieving bank accounts expiring soon', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving bank accounts expiring soon: ' . $e->getMessage()
            ], 500);
        }
    }
}