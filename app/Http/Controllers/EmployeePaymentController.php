<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeePaymentController extends Controller
{
    /**
     * Get all employee payment records
     */
    public function index($employeeId)
    {
        try {
            Log::info('=== FETCHING EMPLOYEE PAYMENT RECORDS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payments = EmployeePayment::where('employee_id', $employeeId)
                ->orderBy('paid_at', 'desc')
                ->orderBy('payment_id', 'desc')
                ->get();

            if ($payments->isEmpty()) {
                Log::info('No payment records found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No payment records found - ready for creation',
                    'data' => []
                ]);
            }

            Log::info('Payment records retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment records retrieved successfully',
                'data' => $payments,
                'count' => $payments->count()
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
            Log::error('Error retrieving payment records', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single employee payment record
     */
    public function show($employeeId, $paymentId)
    {
        try {
            Log::info('=== FETCHING SINGLE PAYMENT RECORD ===', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payment = EmployeePayment::where('employee_id', $employeeId)
                ->where('payment_id', $paymentId)
                ->first();

            if (!$payment) {
                Log::warning('Payment record not found', [
                    'employee_id' => $employeeId,
                    'payment_id' => $paymentId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found'
                ], 404);
            }

            Log::info('Payment record retrieved successfully', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment record retrieved successfully',
                'data' => $payment
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
            Log::error('Error retrieving payment record', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new employee payment record
     */
    public function store(Request $request, $employeeId)
    {
        Log::info('=== CREATING NEW PAYMENT RECORD ===', [
            'employee_id' => $employeeId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // Payment Details
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'nullable|date',
            'payment_method' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100|unique:employee_payment,reference_number',
            'payment_status' => 'nullable|string|in:Pending,Completed,Failed,Processing,Cancelled',
            
            // Validity
            'valid_to' => 'nullable|date',
            
            // Attachments
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ], [
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a valid number',
            'amount.min' => 'Payment amount must be at least 0',
            'reference_number.unique' => 'This reference number already exists',
            'payment_status.in' => 'Invalid payment status',
            'drive_link.url' => 'Drive link must be a valid URL',
            'verify_link.url' => 'Verify link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for payment record', [
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
            $paymentInput = $request->only([
                'amount', 'paid_at', 'payment_method', 'reference_number',
                'payment_status', 'valid_to', 'drive_link', 'verify_link'
            ]);
            
            // Set default status if not provided
            if (!isset($paymentInput['payment_status'])) {
                $paymentInput['payment_status'] = EmployeePayment::STATUS_PENDING;
            }
            
            $paymentInput['employee_id'] = $employeeId;

            // Create new payment record
            $payment = EmployeePayment::create($paymentInput);
            
            Log::info('Payment record created successfully', [
                'employee_id' => $employeeId,
                'payment_id' => $payment->payment_id,
                'amount' => $payment->amount,
                'status' => $payment->payment_status
            ]);

            DB::commit();

            Log::info('=== PAYMENT RECORD CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'payment_id' => $payment->payment_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment record created successfully',
                'data' => $payment
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
            
            Log::error('Error creating payment record', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating payment record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update employee payment record
     */
    public function update(Request $request, $employeeId, $paymentId)
    {
        Log::info('=== UPDATING PAYMENT RECORD ===', [
            'employee_id' => $employeeId,
            'payment_id' => $paymentId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // Payment Details
            'amount' => 'required|numeric|min:0',
            'paid_at' => 'nullable|date',
            'payment_method' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:100|unique:employee_payment,reference_number,' . $paymentId . ',payment_id',
            'payment_status' => 'nullable|string|in:Pending,Completed,Failed,Processing,Cancelled',
            
            // Validity
            'valid_to' => 'nullable|date',
            
            // Attachments
            'drive_link' => 'nullable|url|max:500',
            'verify_link' => 'nullable|url|max:500',
        ], [
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a valid number',
            'amount.min' => 'Payment amount must be at least 0',
            'reference_number.unique' => 'This reference number already exists',
            'payment_status.in' => 'Invalid payment status',
            'drive_link.url' => 'Drive link must be a valid URL',
            'verify_link.url' => 'Verify link must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for payment record update', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId,
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
            $payment = EmployeePayment::where('employee_id', $employeeId)
                ->where('payment_id', $paymentId)
                ->first();

            if (!$payment) {
                Log::warning('Payment record not found for update', [
                    'employee_id' => $employeeId,
                    'payment_id' => $paymentId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->only([
                'amount', 'paid_at', 'payment_method', 'reference_number',
                'payment_status', 'valid_to', 'drive_link', 'verify_link'
            ]);

            // Update payment record
            $payment->update($updateData);
            
            Log::info('Payment record updated successfully', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId
            ]);

            DB::commit();

            Log::info('=== PAYMENT RECORD UPDATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment record updated successfully',
                'data' => $payment->fresh()
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
            
            Log::error('Error updating payment record', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating payment record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete employee payment record
     */
    public function destroy($employeeId, $paymentId)
    {
        Log::info('=== DELETING PAYMENT RECORD ===', [
            'employee_id' => $employeeId,
            'payment_id' => $paymentId
        ]);

        try {
            DB::beginTransaction();

            $payment = EmployeePayment::where('employee_id', $employeeId)
                ->where('payment_id', $paymentId)
                ->first();

            if (!$payment) {
                Log::warning('Payment record not found for deletion', [
                    'employee_id' => $employeeId,
                    'payment_id' => $paymentId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Payment record not found'
                ], 404);
            }

            $amount = $payment->amount;
            $referenceNumber = $payment->reference_number;
            $payment->delete();

            DB::commit();

            Log::info('=== PAYMENT RECORD DELETED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId,
                'amount' => $amount,
                'reference_number' => $referenceNumber
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment record deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting payment record', [
                'employee_id' => $employeeId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment record: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics for employee
     */
    public function statistics($employeeId)
    {
        try {
            Log::info('=== FETCHING PAYMENT STATISTICS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            
            $statistics = [
                'total' => EmployeePayment::where('employee_id', $employeeId)->count(),
                'total_amount' => EmployeePayment::where('employee_id', $employeeId)->sum('amount'),
                'average_amount' => EmployeePayment::where('employee_id', $employeeId)->avg('amount'),
                'completed' => EmployeePayment::where('employee_id', $employeeId)
                    ->completed()->count(),
                'pending' => EmployeePayment::where('employee_id', $employeeId)
                    ->pending()->count(),
                'failed' => EmployeePayment::where('employee_id', $employeeId)
                    ->failed()->count(),
                'processing' => EmployeePayment::where('employee_id', $employeeId)
                    ->processing()->count(),
                'by_method' => EmployeePayment::where('employee_id', $employeeId)
                    ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                    ->groupBy('payment_method')
                    ->orderBy('count', 'desc')
                    ->get(),
                'by_status' => EmployeePayment::where('employee_id', $employeeId)
                    ->select('payment_status', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
                    ->groupBy('payment_status')
                    ->orderBy('count', 'desc')
                    ->get(),
                'this_month' => EmployeePayment::where('employee_id', $employeeId)
                    ->thisMonth()->sum('amount'),
                'this_year' => EmployeePayment::where('employee_id', $employeeId)
                    ->thisYear()->sum('amount'),
                'valid' => EmployeePayment::where('employee_id', $employeeId)
                    ->valid()->count(),
                'expired' => EmployeePayment::where('employee_id', $employeeId)
                    ->expired()->count(),
                'with_attachments' => EmployeePayment::where('employee_id', $employeeId)
                    ->where(function($query) {
                        $query->whereNotNull('verify_link')
                              ->orWhereNotNull('drive_link');
                    })
                    ->count(),
            ];

            Log::info('Payment statistics retrieved successfully', [
                'employee_id' => $employeeId,
                'statistics' => $statistics
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment statistics retrieved successfully',
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
            Log::error('Error retrieving payment statistics', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment records by status
     */
    public function getByStatus($employeeId, $status)
    {
        try {
            Log::info('=== FETCHING PAYMENT BY STATUS ===', [
                'employee_id' => $employeeId,
                'status' => $status
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payments = EmployeePayment::where('employee_id', $employeeId)
                ->where('payment_status', $status)
                ->orderBy('paid_at', 'desc')
                ->get();

            Log::info('Payment records by status retrieved successfully', [
                'employee_id' => $employeeId,
                'status' => $status,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment records retrieved successfully',
                'data' => $payments,
                'count' => $payments->count()
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
            Log::error('Error retrieving payment by status', [
                'employee_id' => $employeeId,
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment records by method
     */
    public function getByMethod($employeeId, $method)
    {
        try {
            Log::info('=== FETCHING PAYMENT BY METHOD ===', [
                'employee_id' => $employeeId,
                'method' => $method
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payments = EmployeePayment::where('employee_id', $employeeId)
                ->byMethod($method)
                ->orderBy('paid_at', 'desc')
                ->get();

            Log::info('Payment records by method retrieved successfully', [
                'employee_id' => $employeeId,
                'method' => $method,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment records retrieved successfully',
                'data' => $payments,
                'count' => $payments->count()
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
            Log::error('Error retrieving payment by method', [
                'employee_id' => $employeeId,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get completed payments
     */
    public function getCompleted($employeeId)
    {
        try {
            Log::info('=== FETCHING COMPLETED PAYMENTS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payments = EmployeePayment::where('employee_id', $employeeId)
                ->completed()
                ->orderBy('paid_at', 'desc')
                ->get();

            Log::info('Completed payments retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Completed payments retrieved successfully',
                'data' => $payments,
                'count' => $payments->count()
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
            Log::error('Error retrieving completed payments', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving completed payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending payments
     */
    public function getPending($employeeId)
    {
        try {
            Log::info('=== FETCHING PENDING PAYMENTS ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payments = EmployeePayment::where('employee_id', $employeeId)
                ->pending()
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Pending payments retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pending payments retrieved successfully',
                'data' => $payments,
                'count' => $payments->count()
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
            Log::error('Error retrieving pending payments', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving pending payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments in date range
     */
    public function getByDateRange(Request $request, $employeeId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            Log::info('=== FETCHING PAYMENTS BY DATE RANGE ===', [
                'employee_id' => $employeeId,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ]);

            $employee = Employee::findOrFail($employeeId);
            $payments = EmployeePayment::where('employee_id', $employeeId)
                ->inDateRange($request->start_date, $request->end_date)
                ->orderBy('paid_at', 'desc')
                ->get();

            Log::info('Payments by date range retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $payments->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments,
                'count' => $payments->count(),
                'total_amount' => $payments->sum('amount')
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
            Log::error('Error retrieving payments by date range', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payments: ' . $e->getMessage()
            ], 500);
        }
    }
}