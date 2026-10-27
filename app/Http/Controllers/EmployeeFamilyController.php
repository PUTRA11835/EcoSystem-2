<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\Management\EmployeeMasterData as MD;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class EmployeeFamilyController extends Controller
{
    use \App\Http\Controllers\Concerns\FriendlyValidation;

    /**
     * Nama agama baku = sama dengan Basic Data. Nama lama/variasi (Christianity, Catholicism, ...) dinormalkan agar data konsisten.
     */
    private static function normalizeReligion($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === null || $value === '') {
            return null;
        }
        // A value the Management → Employee → Family list offers is kept as it is; only the old spellings around it are normalised.
        if (in_array($value, MD::values('family', 'religion'), true)) {
            return $value;
        }
        $map = [
            'christianity' => 'Christian', 'christian' => 'Christian', 'protestant' => 'Christian', 'kristen' => 'Christian',
            'catholicism' => 'Catholic', 'catholic' => 'Catholic', 'katolik' => 'Catholic',
            'hinduism' => 'Hindu', 'hindu' => 'Hindu',
            'buddhism' => 'Buddhist', 'buddhist' => 'Buddhist', 'budha' => 'Buddhist', 'buddha' => 'Buddhist',
            'confucian' => 'Konghucu', 'confucianism' => 'Konghucu', 'konghucu' => 'Konghucu',
            'islam' => 'Islam',
        ];

        return $map[strtolower((string) $value)] ?? $value;
    }

    /**
     * Validation rules for family member
     */
    private function validationRules(?object $cur = null): array
    {
        return [
            'name' => ['required', 'string', 'max:' . MD::maxLength('family', 'name_max_length')],
            'title' => 'nullable|string|max:50',
            'relation' => array_filter(['required', 'string', 'max:50', MD::inRule('family', 'relation', $cur->relation ?? null)]),
            'gender' => array_filter(['nullable', 'string', 'max:50', MD::inRule('family', 'gender', $cur->gender ?? null)]),
            'religion' => array_filter(['nullable', 'string', 'max:100', MD::inRule('family', 'religion', $cur->religion ?? null)]),
            'country' => 'nullable|string|max:255',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date|before_or_equal:today',
            'occupation' => ['nullable', 'string', 'max:' . MD::maxLength('family', 'occupation_max_length')],
            'is_alive' => 'nullable|boolean',
            'valid_from' => 'nullable|date',
            'valid_to' => 'nullable|date|after_or_equal:valid_from',
            'verify_link' => 'nullable|url|max:500',
        ];
    }

    /**
     * Get all family members for an employee
     */
    public function index(string $employeeId): JsonResponse
    {
        try {
            $families = EmployeeFamily::where('employee_id', $employeeId)
                ->orderBy('relation')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Family members retrieved successfully',
                'data' => $families,
                'count' => $families->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving family members', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving family members'
            ], 500);
        }
    }

    /**
     * Get single family member
     */
    public function show(string $employeeId, int $familyId): JsonResponse
    {
        try {
            $family = EmployeeFamily::where('employee_id', $employeeId)
                ->where('family_id', $familyId)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Family member retrieved successfully',
                'data' => $family
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Family member not found'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error retrieving family member', [
                'family_id' => $familyId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving family member'
            ], 500);
        }
    }

    /**
     * Create new family member
     */
    public function store(Request $request, string $employeeId): JsonResponse
    {
        $request->merge(['religion' => self::normalizeReligion($request->input('religion'))]);
        $validator = Validator::make($request->all(), $this->validationRules(null));

        $this->friendly($validator);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify employee exists
            if (!Employee::where('employee_id', $employeeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            DB::beginTransaction();

            $familyData = $validator->validated();
            $familyData['employee_id'] = $employeeId;
            $familyData['is_alive'] = $request->input('is_alive', true);

            $family = EmployeeFamily::create($familyData);

            DB::commit();

            Log::info('Family member created', [
                'employee_id' => $employeeId,
                'family_id' => $family->family_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Family member created successfully',
                'data' => $family
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error creating family member', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creating family member'
            ], 500);
        }
    }

    /**
     * Update family member
     */
    public function update(Request $request, string $employeeId, int $familyId): JsonResponse
    {
        $request->merge(['religion' => self::normalizeReligion($request->input('religion'))]);
        $validator = Validator::make($request->all(), $this->validationRules(DB::table('employee_family')->where('family_id', $familyId)->first()));

        $this->friendly($validator);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $family = EmployeeFamily::where('employee_id', $employeeId)
                ->where('family_id', $familyId)
                ->firstOrFail();

            $family->update($validator->validated());

            DB::commit();

            Log::info('Family member updated', [
                'employee_id' => $employeeId,
                'family_id' => $familyId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Family member updated successfully',
                'data' => $family->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Family member not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error updating family member', [
                'family_id' => $familyId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating family member'
            ], 500);
        }
    }

    /**
     * Delete family member
     */
    public function destroy(string $employeeId, int $familyId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $family = EmployeeFamily::where('employee_id', $employeeId)
                ->where('family_id', $familyId)
                ->firstOrFail();

            $familyName = $family->name;
            $family->delete();

            DB::commit();

            Log::info('Family member deleted', [
                'employee_id' => $employeeId,
                'family_id' => $familyId,
                'name' => $familyName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Family member deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Family member not found'
            ], 404);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error deleting family member', [
                'family_id' => $familyId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error deleting family member'
            ], 500);
        }
    }

    /**
     * Get family statistics for employee
     */
    public function statistics(string $employeeId): JsonResponse
    {
        try {
            // Verify employee exists
            if (!Employee::where('employee_id', $employeeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $families = EmployeeFamily::where('employee_id', $employeeId);

            $statistics = [
                'total' => $families->count(),
                'alive' => (clone $families)->where('is_alive', true)->count(),
                'deceased' => (clone $families)->where('is_alive', false)->count(),
                'by_relation' => (clone $families)
                    ->select('relation', DB::raw('count(*) as count'))
                    ->groupBy('relation')
                    ->get(),
                'children_count' => (clone $families)->where('relation', 'child')->count(),
                'minor_children' => (clone $families)
                    ->where('relation', 'child')
                    ->where('birth_date', '>=', now()->subYears(18))
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Family statistics retrieved successfully',
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving family statistics', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving family statistics'
            ], 500);
        }
    }

    /**
     * Bulk import family members
     */
    public function bulkImport(Request $request, string $employeeId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'families' => 'required|array|min:1',
            'families.*.name' => 'required|string|max:255',
            'families.*.relation' => array_filter(['required', 'string', 'max:50', MD::inRule('family', 'relation', null)]),
            'families.*.title' => 'nullable|string|max:50',
            'families.*.gender' => array_filter(['nullable', 'string', 'max:50', MD::inRule('family', 'gender', null)]),
            'families.*.birth_date' => 'nullable|date|before_or_equal:today',
        ]);

        $this->friendly($validator);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify employee exists
            if (!Employee::where('employee_id', $employeeId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            DB::beginTransaction();

            $families = collect($request->families)->map(function ($familyData) use ($employeeId) {
                $familyData['employee_id'] = $employeeId;
                if (array_key_exists('religion', $familyData)) {
                    $familyData['religion'] = self::normalizeReligion($familyData['religion']);
                }
                $familyData['is_alive'] = $familyData['is_alive'] ?? true;
                $familyData['created_at'] = now();
                $familyData['updated_at'] = now();
                return $familyData;
            });

            EmployeeFamily::insert($families->toArray());

            DB::commit();

            Log::info('Bulk family members imported', [
                'employee_id' => $employeeId,
                'count' => $families->count()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Family members imported successfully',
                'count' => $families->count()
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error bulk importing family members', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error importing family members'
            ], 500);
        }
    }
}