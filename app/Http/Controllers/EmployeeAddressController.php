<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeAddressController extends Controller
{
    /**
     * Get all employee addresses
     */
    public function index($employeeId)
    {
        try {
            Log::info('=== FETCHING EMPLOYEE ADDRESSES ===', [
                'employee_id' => $employeeId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $addresses = EmployeeAddress::where('employee_id', $employeeId)
                ->orderBy('is_primary', 'desc')
                ->orderBy('address_id', 'asc')
                ->get();

            if ($addresses->isEmpty()) {
                Log::info('No address data found for employee', [
                    'employee_id' => $employeeId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'No address data found - ready for creation',
                    'data' => []
                ]);
            }

            Log::info('Addresses retrieved successfully', [
                'employee_id' => $employeeId,
                'count' => $addresses->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Addresses retrieved successfully',
                'data' => $addresses,
                'count' => $addresses->count()
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
            Log::error('Error retrieving addresses', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving addresses'
            ], 500);
        }
    }

    /**
     * Get single employee address
     */
    public function show($employeeId, $addressId)
    {
        try {
            Log::info('=== FETCHING SINGLE ADDRESS ===', [
                'employee_id' => $employeeId,
                'address_id' => $addressId
            ]);

            $employee = Employee::findOrFail($employeeId);
            $address = EmployeeAddress::where('employee_id', $employeeId)
                ->where('address_id', $addressId)
                ->first();

            if (!$address) {
                Log::warning('Address not found', [
                    'employee_id' => $employeeId,
                    'address_id' => $addressId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            Log::info('Address retrieved successfully', [
                'employee_id' => $employeeId,
                'address_id' => $addressId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address retrieved successfully',
                'data' => $address
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
            Log::error('Error retrieving address', [
                'employee_id' => $employeeId,
                'address_id' => $addressId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving address'
            ], 500);
        }
    }

    /**
     * Create new employee address
     */
    public function store(Request $request, $employeeId)
    {
        Log::info('=== CREATING NEW ADDRESS ===', [
            'employee_id' => $employeeId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // 🏠 ADDRESS INFORMATION
            'address_type' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'rural_urban_village' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            
            // 📞 COMMUNICATION
            'language' => 'nullable|string|max:50',
            'cell_phone_country' => 'nullable|string|max:10',
            'telephone_country' => 'nullable|string|max:10',
            'fax_country' => 'nullable|string|max:10',
            'preferred_communication' => 'nullable|string|max:50',
            'cell_phone' => 'nullable|string|max:20',
            'telephone' => 'nullable|string|max:20',
            'telephone_extension' => 'nullable|string|max:10',
            'fax' => 'nullable|string|max:20',
            'fax_extension' => 'nullable|string|max:10',
            'email_personal' => 'nullable|email|max:255',
            'email_work' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            
            // ⏳ VALIDITY PERIOD
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // ⚙️ STATUS
            'is_primary' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ], [
            'address_type.required' => 'Address type is required',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'email_personal.email' => 'Personal email must be a valid email address',
            'email_work.email' => 'Work email must be a valid email address',
            'website.url' => 'Website must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for address', [
                'employee_id' => $employeeId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee address data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);

            // Prepare data
            $addressInput = $request->only([
                'address_type', 'country', 'region', 'city', 'district',
                'rural_urban_village', 'street', 'house_number', 'postal_code',
                'language', 'cell_phone_country', 'telephone_country', 'fax_country',
                'preferred_communication', 'cell_phone', 'telephone', 'telephone_extension',
                'fax', 'fax_extension', 'email_personal', 'email_work', 'website',
                'valid_from', 'valid_to', 'is_primary', 'is_verified'
            ]);
            
            $addressInput['employee_id'] = $employeeId;

            // Set default values for boolean fields
            $addressInput['is_primary'] = $request->input('is_primary', false);
            $addressInput['is_verified'] = $request->input('is_verified', false);

            // If this is set as primary, unset other primary addresses
            if ($addressInput['is_primary']) {
                EmployeeAddress::where('employee_id', $employeeId)
                    ->update(['is_primary' => false]);
                
                Log::info('Unset other primary addresses', [
                    'employee_id' => $employeeId
                ]);
            }

            // Create new address
            $address = EmployeeAddress::create($addressInput);

            Log::info('Address created successfully', [
                'employee_id' => $employeeId,
                'address_id' => $address->address_id,
                'address_type' => $address->address_type
            ]);

            // Jaga konsistensi: email_work alamat primary = email login (reset password).
            $emailSyncWarning = $this->syncEmailWorkToAuth($employeeId, $address);

            DB::commit();

            Log::info('=== ADDRESS CREATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'address_id' => $address->address_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => $address,
                'warning' => $emailSyncWarning,
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
            
            Log::error('Error creating address', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating address'
            ], 500);
        }
    }

    /**
     * Update employee address
     */
    public function update(Request $request, $employeeId, $addressId)
    {
        Log::info('=== UPDATING ADDRESS ===', [
            'employee_id' => $employeeId,
            'address_id' => $addressId,
            'data' => $request->all()
        ]);

        $validator = Validator::make($request->all(), [
            // 🏠 ADDRESS INFORMATION
            'address_type' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'rural_urban_village' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'house_number' => 'nullable|string|max:50',
            'postal_code' => 'nullable|string|max:20',
            
            // 📞 COMMUNICATION
            'language' => 'nullable|string|max:50',
            'cell_phone_country' => 'nullable|string|max:10',
            'telephone_country' => 'nullable|string|max:10',
            'fax_country' => 'nullable|string|max:10',
            'preferred_communication' => 'nullable|string|max:50',
            'cell_phone' => 'nullable|string|max:20',
            'telephone' => 'nullable|string|max:20',
            'telephone_extension' => 'nullable|string|max:10',
            'fax' => 'nullable|string|max:20',
            'fax_extension' => 'nullable|string|max:10',
            'email_personal' => 'nullable|email|max:255',
            'email_work' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            
            // ⏳ VALIDITY PERIOD
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            
            // ⚙️ STATUS
            'is_primary' => 'nullable|boolean',
            'is_verified' => 'nullable|boolean',
        ], [
            'address_type.required' => 'Address type is required',
            'valid_to.after_or_equal' => 'Valid to date must be after or equal to valid from date',
            'email_personal.email' => 'Personal email must be a valid email address',
            'email_work.email' => 'Work email must be a valid email address',
            'website.url' => 'Website must be a valid URL',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for address update', [
                'employee_id' => $employeeId,
                'address_id' => $addressId,
                'errors' => $validator->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Employee address data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $address = EmployeeAddress::where('employee_id', $employeeId)
                ->where('address_id', $addressId)
                ->first();

            if (!$address) {
                Log::warning('Address not found for update', [
                    'employee_id' => $employeeId,
                    'address_id' => $addressId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            // Prepare update data
            $updateData = $request->only([
                'address_type', 'country', 'region', 'city', 'district',
                'rural_urban_village', 'street', 'house_number', 'postal_code',
                'language', 'cell_phone_country', 'telephone_country', 'fax_country',
                'preferred_communication', 'cell_phone', 'telephone', 'telephone_extension',
                'fax', 'fax_extension', 'email_personal', 'email_work', 'website',
                'valid_from', 'valid_to', 'is_primary', 'is_verified'
            ]);

            // If this is set as primary, unset other primary addresses
            if (isset($updateData['is_primary']) && $updateData['is_primary']) {
                EmployeeAddress::where('employee_id', $employeeId)
                    ->where('address_id', '!=', $addressId)
                    ->update(['is_primary' => false]);
                
                Log::info('Unset other primary addresses', [
                    'employee_id' => $employeeId,
                    'except_address_id' => $addressId
                ]);
            }

            // Update address
            $address->update($updateData);

            Log::info('Address updated successfully', [
                'employee_id' => $employeeId,
                'address_id' => $addressId
            ]);

            // Jaga konsistensi: email_work alamat primary = email login (reset password).
            $emailSyncWarning = $this->syncEmailWorkToAuth($employeeId, $address);

            DB::commit();

            Log::info('=== ADDRESS UPDATED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'address_id' => $addressId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => $address->fresh(),
                'warning' => $emailSyncWarning,
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
            
            Log::error('Error updating address', [
                'employee_id' => $employeeId,
                'address_id' => $addressId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating address'
            ], 500);
        }
    }

    /**
     * Delete employee address
     */
    public function destroy($employeeId, $addressId)
    {
        Log::info('=== DELETING ADDRESS ===', [
            'employee_id' => $employeeId,
            'address_id' => $addressId
        ]);

        try {
            DB::beginTransaction();

            $address = EmployeeAddress::where('employee_id', $employeeId)
                ->where('address_id', $addressId)
                ->first();

            if (!$address) {
                Log::warning('Address not found for deletion', [
                    'employee_id' => $employeeId,
                    'address_id' => $addressId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            $wasPrimary = $address->is_primary;
            $address->delete();

            // If deleted address was primary, set the first remaining address as primary
            if ($wasPrimary) {
                $firstAddress = EmployeeAddress::where('employee_id', $employeeId)
                    ->orderBy('address_id', 'asc')
                    ->first();
                
                if ($firstAddress) {
                    $firstAddress->update(['is_primary' => true]);
                    
                    Log::info('Set new primary address', [
                        'employee_id' => $employeeId,
                        'new_primary_address_id' => $firstAddress->address_id
                    ]);
                }
            }

            DB::commit();

            Log::info('=== ADDRESS DELETED SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'address_id' => $addressId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting address', [
                'employee_id' => $employeeId,
                'address_id' => $addressId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting address'
            ], 500);
        }
    }

    /**
     * Set address as primary
     */
    public function setPrimary($employeeId, $addressId)
    {
        Log::info('=== SETTING PRIMARY ADDRESS ===', [
            'employee_id' => $employeeId,
            'address_id' => $addressId
        ]);

        try {
            DB::beginTransaction();

            $address = EmployeeAddress::where('employee_id', $employeeId)
                ->where('address_id', $addressId)
                ->first();

            if (!$address) {
                Log::warning('Address not found', [
                    'employee_id' => $employeeId,
                    'address_id' => $addressId
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Address not found'
                ], 404);
            }

            // Unset all primary addresses for this employee
            EmployeeAddress::where('employee_id', $employeeId)
                ->update(['is_primary' => false]);

            // Set this address as primary
            $address->update(['is_primary' => true]);

            // Primary berpindah → email_work alamat baru ini jadi email login (reset password).
            $emailSyncWarning = $this->syncEmailWorkToAuth($employeeId, $address);

            DB::commit();

            Log::info('=== PRIMARY ADDRESS SET SUCCESSFULLY ===', [
                'employee_id' => $employeeId,
                'address_id' => $addressId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Address set as primary successfully',
                'data' => $address->fresh(),
                'warning' => $emailSyncWarning,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error setting primary address', [
                'employee_id' => $employeeId,
                'address_id' => $addressId,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error setting primary address'
            ], 500);
        }
    }

    /**
     * Sinkronkan email_work alamat PRIMARY → auth_users.email.
     *
     * auth_users.email adalah sumber otoritatif untuk kirim set/reset password,
     * sementara UI mengeditnya lewat field "Email (Work)" (employee_address.email_work).
     * Agar keduanya konsisten, perubahan email_work pada alamat PRIMARY diteruskan
     * ke auth_users.email. Hanya untuk alamat primary & email non-kosong; dilewati
     * (dengan peringatan) bila email sudah dipakai akun lain (auth_users.email unik).
     *
     * @return string|null Pesan peringatan bila sinkronisasi dilewati, selain itu null.
     */
    private function syncEmailWorkToAuth($employeeId, EmployeeAddress $address): ?string
    {
        if (!$address->is_primary) return null;

        $emailWork = trim((string) $address->email_work);
        if ($emailWork === '') return null;

        $authUser = DB::table('auth_users')->where('employee_id', $employeeId)->first();
        if (!$authUser) return null;
        if (strcasecmp((string) $authUser->email, $emailWork) === 0) return null; // sudah sama

        $taken = DB::table('auth_users')->where('email', $emailWork)
            ->where('id', '!=', $authUser->id)->exists();
        if ($taken) {
            Log::warning('Email work sync skipped — email already used by another account', [
                'employee_id' => $employeeId,
                'email' => $emailWork,
            ]);
            return "Email \"{$emailWork}\" is already used by another account — the login email (used for password reset) was not changed.";
        }

        DB::table('auth_users')->where('id', $authUser->id)
            ->update(['email' => $emailWork, 'updated_at' => now()]);

        Log::info('Synced email_work to auth_users.email', [
            'employee_id' => $employeeId,
            'email' => $emailWork,
        ]);

        return null;
    }
}