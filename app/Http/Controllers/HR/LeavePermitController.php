<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeavePermitApplication;
use App\Models\LeavePermitLog;
use App\Models\LeavePermitType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeavePermitController extends Controller
{
    /**
     * Helper to normalize employee gender string into 'P' or 'L'
     */
    private function normalizeGender(?string $gender): string
    {
        if (!$gender) return 'all';
        $g = strtolower(trim($gender));
        if (str_starts_with($g, 'p') || str_starts_with($g, 'f') || str_contains($g, 'perempuan') || str_contains($g, 'female')) {
            return 'P';
        }
        if (str_starts_with($g, 'l') || str_starts_with($g, 'm') || str_contains($g, 'laki') || str_contains($g, 'male')) {
            return 'L';
        }
        return 'all';
    }

    /**
     * Display standalone ESS page for Employee Self-Service: "My Leave & Permit"
     */
    public function myLeavePermitIndex(Request $request)
    {
        $user       = session('user');
        $employeeId = $user['id'] ?? null;
        $employeeModel = $employeeId ? Employee::with('basicData')->find($employeeId) : null;

        $empGender = $employeeModel && $employeeModel->basicData ? $this->normalizeGender($employeeModel->basicData->gender) : 'all';

        $activeTypes = LeavePermitType::where('is_active', true)
            ->where(function ($q) use ($empGender) {
                $q->where('gender_target', 'all')
                  ->orWhere('gender_target', $empGender);
            })
            ->get();

        $currentYear = (int) date('Y');

        return view('hr-general.leave-permit.my-leave-permit', [
            'user'          => $user,
            'employeeId'    => $employeeId,
            'employeeModel' => $employeeModel,
            'activeTypes'   => $activeTypes,
            'isHR'          => false,
            'currentYear'   => $currentYear,
        ]);
    }

    /**
     * Display HR Leave & Permit Management Page
     */
    public function index(Request $request)
    {
        $user       = session('user');
        $employeeId = $user['id'] ?? null;
        $shared     = \Illuminate\Support\Facades\View::getShared();
        $permSlugs  = $shared['permSlugs'] ?? [];

        $employeeModel = $employeeId ? Employee::find($employeeId) : null;
        $isHR = false;

        if ($employeeModel) {
            $isHR = $employeeModel->canAccessMenu('hr-general.leave-permit.manage')
                || $employeeModel->hasAnyRole([1, 4, 5, 7])
                || in_array('hr-general.leave-permit.manage', $permSlugs);
        }

        $allTypes    = LeavePermitType::orderBy('id', 'asc')->get();
        $activeTypes = LeavePermitType::where('is_active', true)->get();
        $currentYear = (int) date('Y');

        return view('hr-general.leave-permit.index', [
            'user'        => $user,
            'employeeId'  => $employeeId,
            'isHR'        => $isHR,
            'activeTypes' => $activeTypes,
            'allTypes'    => $allTypes,
            'currentYear' => $currentYear,
        ]);
    }

    /**
     * Helper to compute quota breakdown for an employee considering gender, monthly resets, and event-based types
     */
    public function calculateUserQuotaSummary(int $employeeId, int $year, ?int $month = null): array
    {
        $employeeModel = Employee::with('basicData')->find($employeeId);
        $empGender = $employeeModel && $employeeModel->basicData ? $this->normalizeGender($employeeModel->basicData->gender) : 'all';
        $currentMonth = $month ?: (int) date('n');

        $types = LeavePermitType::where('is_active', true)
            ->where(function ($q) use ($empGender) {
                $q->where('gender_target', 'all')
                  ->orWhere('gender_target', $empGender);
            })
            ->get();

        $summary = [];

        foreach ($types as $type) {
            $isMonthlyReset = ($type->code === 'CHD');
            $isEventBased   = in_array($type->code, ['CML', 'CGK', 'CSK', 'CTU']);

            if ($isMonthlyReset) {
                // Menstrual Leave (CHD) resets monthly - 1 day per month
                $allocated = $type->default_quota > 0 ? (float) $type->default_quota : 1.0;

                $usedDays = LeavePermitApplication::where('employee_id', $employeeId)
                    ->where('leave_permit_type_id', $type->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->whereMonth('start_date', $currentMonth)
                    ->sum('total_days');

                $pendingDays = LeavePermitApplication::where('employee_id', $employeeId)
                    ->where('leave_permit_type_id', $type->id)
                    ->where('status', 'pending')
                    ->whereYear('start_date', $year)
                    ->whereMonth('start_date', $currentMonth)
                    ->sum('total_days');

                $remaining = max(0.0, $allocated - $usedDays - $pendingDays);
            } else if ($isEventBased) {
                // Event-based leaves (childbirth, miscarriage, sick, unpaid) are uncapped per incident with doctor note
                $allocated = 0.0;

                $usedDays = LeavePermitApplication::where('employee_id', $employeeId)
                    ->where('leave_permit_type_id', $type->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->sum('total_days');

                $pendingDays = LeavePermitApplication::where('employee_id', $employeeId)
                    ->where('leave_permit_type_id', $type->id)
                    ->where('status', 'pending')
                    ->whereYear('start_date', $year)
                    ->sum('total_days');

                $remaining = 999.0; // Uncapped event-based
            } else {
                // Standard annual quota types (CTH, CMN, CMA, CKA, CKM, CIM, CAS)
                $allocated = (float) $type->default_quota;

                $usedDays = LeavePermitApplication::where('employee_id', $employeeId)
                    ->where('leave_permit_type_id', $type->id)
                    ->where('status', 'approved')
                    ->whereYear('start_date', $year)
                    ->sum('total_days');

                $pendingDays = LeavePermitApplication::where('employee_id', $employeeId)
                    ->where('leave_permit_type_id', $type->id)
                    ->where('status', 'pending')
                    ->whereYear('start_date', $year)
                    ->sum('total_days');

                $remaining = max(0.0, $allocated - $usedDays - $pendingDays);
            }

            $summary[] = [
                'type_id'             => $type->id,
                'type_code'           => $type->code,
                'type_name'           => $type->name,
                'category'            => $type->category,
                'default_quota'       => $type->default_quota,
                'min_service_period'  => $type->min_service_period,
                'is_paid'             => $type->is_paid,
                'gender_target'       => $type->gender_target,
                'requires_attachment' => $type->requires_attachment,
                'is_monthly_reset'    => $isMonthlyReset,
                'is_event_based'      => $isEventBased,
                'description'         => $type->description,
                'allocated_quota'     => (float) $allocated,
                'used_quota'          => (float) $usedDays,
                'pending_quota'       => (float) $pendingDays,
                'remaining_quota'     => (float) $remaining,
            ];
        }

        return $summary;
    }

    /**
     * API: Get a lightweight list of all active employees (name + ECI only, no quota calc)
     * Used by the HR apply modal dropdown for fast loading.
     */
    public function getEmployeesList(Request $request)
    {
        $search = $request->input('search', '');

        $query = Employee::with('basicData')->where('is_active', true);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('eci', 'like', "%{$search}%")
                  ->orWhereHas('basicData', function ($bq) use ($search) {
                      $bq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name',  'like', "%{$search}%");
                  });
            });
        }

        $employees = $query->orderBy('eci')->get()->map(function ($emp) {
            $fullName    = $emp->basicData ? $emp->basicData->full_name : null;
            $displayName = $fullName ? "{$fullName}" : $emp->eci;

            return [
                'employee_id'  => $emp->employee_id,
                'eci'          => $emp->eci,
                'display_name' => $displayName,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $employees,
        ]);
    }

    /**
     * API: Get quota breakdown for logged-in user
     */
    public function getUserQuotas(Request $request)
    {
        $user       = session('user');
        $employeeId = $request->input('employee_id', $user['id'] ?? null);
        $year       = (int) $request->input('year', date('Y'));
        $month      = $request->filled('month') ? (int) $request->month : (int) date('n');

        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Employee session not found.'], 401);
        }

        $summary = $this->calculateUserQuotaSummary($employeeId, $year, $month);

        return response()->json([
            'success' => true,
            'year'    => $year,
            'month'   => $month,
            'data'    => $summary,
        ]);
    }

    /**
     * API: Get Quota Summary for ALL active employees (Requirement 8)
     */
    public function getAllEmployeesQuotas(Request $request)
    {
        $year   = (int) $request->input('year', date('Y'));
        $search = $request->input('search', '');

        $query = Employee::with('basicData')->where('is_active', true);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('eci', 'like', "%{$search}%")
                  ->orWhereHas('basicData', function ($bq) use ($search) {
                      $bq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        $employees = $query->get();
        $result    = [];

        foreach ($employees as $emp) {
            $fullName = $emp->basicData ? $emp->basicData->full_name : null;
            $displayName = $fullName ? "{$fullName} ({$emp->eci})" : $emp->eci;

            $quotasSummary = $this->calculateUserQuotaSummary($emp->employee_id, $year);

            $totalAllocated = 0.0;
            $totalUsed      = 0.0;
            $totalPending   = 0.0;
            $totalRemaining = 0.0;

            foreach ($quotasSummary as $q) {
                if (!$q['is_event_based']) {
                    $totalAllocated += $q['allocated_quota'];
                    $totalUsed      += $q['used_quota'];
                    $totalPending   += $q['pending_quota'];
                    $totalRemaining += $q['remaining_quota'];
                }
            }

            $result[] = [
                'employee_id'     => $emp->employee_id,
                'eci'             => $emp->eci,
                'display_name'    => $displayName,
                'total_allocated' => $totalAllocated,
                'total_used'      => $totalUsed,
                'total_pending'   => $totalPending,
                'total_remaining' => $totalRemaining,
            ];
        }

        return response()->json([
            'success' => true,
            'year'    => $year,
            'data'    => $result,
        ]);
    }

    /**
     * API: Get specific employee quota detailed breakdown (Requirement 8)
     */
    public function getEmployeeQuotaDetail($employee_id, Request $request)
    {
        $year  = (int) $request->input('year', date('Y'));
        $month = $request->filled('month') ? (int) $request->month : (int) date('n');

        $emp = Employee::with('basicData')->findOrFail($employee_id);
        $fullName = $emp->basicData ? $emp->basicData->full_name : null;
        $displayName = $fullName ? "{$fullName} ({$emp->eci})" : $emp->eci;

        $summary = $this->calculateUserQuotaSummary($emp->employee_id, $year, $month);

        return response()->json([
            'success'      => true,
            'employee'     => [
                'employee_id'  => $emp->employee_id,
                'display_name' => $displayName,
                'gender'       => $emp->basicData ? $emp->basicData->gender : '-',
            ],
            'year'         => $year,
            'data'         => $summary,
        ]);
    }

    /**
     * API: Get Master Leave & Permit Types
     */
    public function getMasterTypes()
    {
        $types = LeavePermitType::orderBy('id', 'asc')->get();

        return response()->json([
            'success' => true,
            'data'    => $types,
        ]);
    }

    /**
     * API: Store new Master Leave/Permit Type (HR CRUD)
     */
    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'code'                => 'required|string|max:50|unique:leave_permit_types,code',
            'name'                => 'required|string|max:150',
            'category'            => 'required|in:leave,permit',
            'default_quota'       => 'required|numeric|min:0',
            'min_service_period'  => 'nullable|string|max:50',
            'is_paid'             => 'required|boolean',
            'gender_target'       => 'required|in:all,P,L',
            'requires_attachment' => 'boolean',
            'description'         => 'nullable|string|max:1000',
        ]);

        $type = LeavePermitType::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Master leave/permit type created successfully.',
            'data'    => $type,
        ]);
    }

    /**
     * API: Update Master Leave/Permit Type with Deactivation Protection (Requirement 2)
     */
    public function updateType(Request $request, $id)
    {
        $type = LeavePermitType::findOrFail($id);

        $validated = $request->validate([
            'code'                => 'required|string|max:50|unique:leave_permit_types,code,' . $type->id,
            'name'                => 'required|string|max:150',
            'category'            => 'required|in:leave,permit',
            'default_quota'       => 'required|numeric|min:0',
            'min_service_period'  => 'nullable|string|max:50',
            'is_paid'             => 'required|boolean',
            'gender_target'       => 'required|in:all,P,L',
            'requires_attachment' => 'boolean',
            'description'         => 'nullable|string|max:1000',
            'is_active'           => 'boolean',
        ]);

        // Requirement 2: Check if attempting to deactivate when pending applications exist
        if (isset($validated['is_active']) && !$validated['is_active'] && $type->is_active) {
            $pendingCount = LeavePermitApplication::where('leave_permit_type_id', $type->id)
                ->where('status', 'pending')
                ->count();

            if ($pendingCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot deactivate '{$type->name}' because there are {$pendingCount} pending application(s) awaiting approval or rejection. Please review and approve/reject pending requests before deactivating.",
                ], 422);
            }
        }

        $type->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Master leave/permit type updated successfully.',
            'data'    => $type,
        ]);
    }

    /**
     * API: Toggle Active / Nonactive for Master Type with Deactivation Protection (Requirement 2)
     */
    public function toggleTypeActive(Request $request, $id)
    {
        $type = LeavePermitType::findOrFail($id);
        $newStatus = !$type->is_active;

        if (!$newStatus) {
            $pendingCount = LeavePermitApplication::where('leave_permit_type_id', $type->id)
                ->where('status', 'pending')
                ->count();

            if ($pendingCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot set '{$type->name}' to Nonactive because there are {$pendingCount} pending application(s) awaiting approval or rejection.",
                ], 422);
            }
        }

        $type->is_active = $newStatus;
        $type->save();

        return response()->json([
            'success' => true,
            'message' => "Leave/permit type status updated to " . ($newStatus ? 'Active' : 'Nonactive') . ".",
            'data'    => $type,
        ]);
    }

    /**
     * API: Get Leave & Permit Applications
     */
    public function getApplications(Request $request)
    {
        $user       = session('user');
        $employeeId = $user['id'] ?? null;

        $employeeModel = $employeeId ? Employee::find($employeeId) : null;
        $isHR = false;
        if ($employeeModel) {
            $isHR = $employeeModel->canAccessMenu('hr_general.leave-permit.manage')
                || $employeeModel->hasAnyRole([1, 4, 5, 7]);
        }

        $query = LeavePermitApplication::with(['employee.basicData', 'leavePermitType', 'reviewer.basicData', 'logs.performer.basicData']);

        if (!$isHR || $request->has('my_only')) {
            $query->where('employee_id', $employeeId);
        } else if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->whereYear('start_date', $request->year);
        }

        if ($request->filled('month')) {
            $query->whereMonth('start_date', $request->month);
        }

        if ($request->filled('type_id')) {
            $query->where('leave_permit_type_id', $request->type_id);
        }

        $applications = $query->orderBy('created_at', 'desc')->get()->map(function ($app) {
            if ($app->employee) {
                $fullName = $app->employee->basicData ? $app->employee->basicData->full_name : null;
                $app->employee_display_name = $fullName ? "{$fullName} ({$app->employee->eci})" : $app->employee->eci;
            } else {
                $app->employee_display_name = "Emp #{$app->employee_id}";
            }

            // Preservation of type name in history log even if master type details changed or deactivated (Requirement 2)
            $app->type_name = $app->leavePermitType ? $app->leavePermitType->name : 'Unknown Type';

            $year = (int) Carbon::parse($app->start_date)->format('Y');
            $month = (int) Carbon::parse($app->start_date)->format('n');
            $quotas = $this->calculateUserQuotaSummary($app->employee_id, $year, $month);

            $typeQuota = collect($quotas)->firstWhere('type_id', $app->leave_permit_type_id);

            if ($typeQuota) {
                $app->remaining_quota = $typeQuota['remaining_quota'];
                $app->is_event_based  = $typeQuota['is_event_based'];
                $app->is_within_quota = $typeQuota['is_event_based'] || ($typeQuota['remaining_quota'] >= $app->total_days);
            } else {
                $app->remaining_quota = 0.0;
                $app->is_event_based  = false;
                $app->is_within_quota = false;
            }

            return $app;
        });

        return response()->json([
            'success' => true,
            'data'    => $applications,
        ]);
    }

    /**
     * API: Store Application (Submit Leave / Permit)
     */
    public function storeApplication(Request $request)
    {
        $user = session('user');
        $currentEmpId = $user['id'] ?? null;

        $employeeModel = $currentEmpId ? Employee::find($currentEmpId) : null;
        $isHR = false;
        if ($employeeModel) {
            $isHR = $employeeModel->canAccessMenu('hr_general.leave-permit.manage')
                || $employeeModel->hasAnyRole([1, 4, 5, 7]);
        }

        $validated = $request->validate([
            'employee_id'          => 'nullable|exists:employee,employee_id',
            'leave_permit_type_id' => 'required|exists:leave_permit_types,id',
            'start_date'           => 'required|date',
            'end_date'             => 'required|date|after_or_equal:start_date',
            'reason'               => 'required|string|max:1000',
            'attachment'           => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:1024',
        ]);

        $applicantEmpId = ($isHR && !empty($validated['employee_id'])) ? (int) $validated['employee_id'] : $currentEmpId;

        if (!$applicantEmpId) {
            return response()->json(['success' => false, 'message' => 'Applicant employee ID is required.'], 422);
        }

        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);
        $today     = Carbon::today();
        $year      = (int) $startDate->format('Y');
        $month     = (int) $startDate->format('n');

        if (!$isHR && $startDate->lt($today)) {
            return response()->json([
                'success' => false,
                'message' => 'Regular employees cannot submit leave or permit requests for past dates.',
            ], 422);
        }

        if ($request->input('day_type') === 'half' || $request->input('is_half_day') || $request->input('total_days') == 0.5) {
            $totalDays = 0.5;
        } else {
            $totalDays = $startDate->diffInDaysFiltered(function (Carbon $date) {
                return !$date->isWeekend();
            }, $endDate->copy()->addDay());

            if ($totalDays <= 0) $totalDays = 1.0;
        }

        $type = LeavePermitType::findOrFail($validated['leave_permit_type_id']);

        // Requirement 2: Inactive types cannot be chosen for new applications
        if (!$type->is_active) {
            return response()->json([
                'success' => false,
                'message' => "The leave/permit type '{$type->name}' is currently Nonactive and cannot be selected.",
            ], 422);
        }

        // Gender Check
        $applicantModel = Employee::with('basicData')->find($applicantEmpId);
        $empGender = $applicantModel && $applicantModel->basicData ? $this->normalizeGender($applicantModel->basicData->gender) : 'all';

        if ($type->gender_target !== 'all' && $type->gender_target !== $empGender) {
            return response()->json([
                'success' => false,
                'message' => "The leave/permit type '{$type->name}' is not applicable for your gender.",
            ], 422);
        }

        if ($type->requires_attachment && !$request->hasFile('attachment')) {
            return response()->json([
                'success' => false,
                'message' => "Attachment file (doctor's note / supporting document) is required for {$type->name}.",
            ], 422);
        }

        // Quota Limit Check
        $quotas = $this->calculateUserQuotaSummary($applicantEmpId, $year, $month);
        $typeQuota = collect($quotas)->firstWhere('type_id', $type->id);

        if ($typeQuota && !$typeQuota['is_event_based']) {
            if ($totalDays > $typeQuota['remaining_quota']) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot apply: Maximum quota for '{$type->name}' has already been reached. Requested: {$totalDays} day(s), Remaining: {$typeQuota['remaining_quota']} day(s).",
                ], 422);
            }
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('leave-permits', 'public');
        }

        $appNo = 'LP-' . date('Ym') . '-' . Str::padLeft(mt_rand(1, 9999), 4, '0');

        $application = LeavePermitApplication::create([
            'application_no'       => $appNo,
            'employee_id'          => $applicantEmpId,
            'leave_permit_type_id' => $type->id,
            'start_date'           => $startDate->format('Y-m-d'),
            'end_date'             => $endDate->format('Y-m-d'),
            'total_days'           => $totalDays,
            'reason'               => $validated['reason'],
            'attachment_path'      => $attachmentPath,
            'status'               => 'pending',
        ]);

        LeavePermitLog::create([
            'application_id' => $application->id,
            'action'         => 'submitted',
            'performed_by'   => $currentEmpId,
            'notes'          => $isHR && $applicantEmpId !== $currentEmpId ? 'Submitted by HR on behalf of employee.' : 'Application submitted.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave/Permit application submitted successfully.',
            'data'    => $application->load(['leavePermitType', 'employee.basicData']),
        ]);
    }

    /**
     * API: Update application details (HR or Employee)
     */
    public function updateApplication(Request $request, $id)
    {
        $user = session('user');
        $currentEmpId = $user['id'] ?? null;

        $employeeModel = $currentEmpId ? Employee::find($currentEmpId) : null;
        $isHR = false;
        if ($employeeModel) {
            $isHR = $employeeModel->canAccessMenu('hr_general.leave-permit.manage')
                || $employeeModel->hasAnyRole([1, 4, 5, 7]);
        }

        $application = LeavePermitApplication::findOrFail($id);

        if (!$isHR && !in_array($application->status, ['pending', 'revision'])) {
            return response()->json(['success' => false, 'message' => 'Only applications in Pending or Revision status can be updated by employees.'], 422);
        }

        $validated = $request->validate([
            'leave_permit_type_id' => 'required|exists:leave_permit_types,id',
            'start_date'           => 'required|date',
            'end_date'             => 'required|date|after_or_equal:start_date',
            'reason'               => 'required|string|max:1000',
            'status'               => 'nullable|in:pending,approved,rejected,revision',
            'attachment'           => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:1024',
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate   = Carbon::parse($validated['end_date']);

        if ($request->input('day_type') === 'half' || $request->input('is_half_day') || $request->input('total_days') == 0.5) {
            $totalDays = 0.5;
        } else {
            $totalDays = $startDate->diffInDaysFiltered(function (Carbon $date) {
                return !$date->isWeekend();
            }, $endDate->copy()->addDay());

            if ($totalDays <= 0) $totalDays = 1.0;
        }

        $type = LeavePermitType::findOrFail($validated['leave_permit_type_id']);

        if ($request->hasFile('attachment')) {
            $application->attachment_path = $request->file('attachment')->store('leave_permits', 'public');
        }

        $application->leave_permit_type_id = $type->id;
        $application->start_date           = $startDate->format('Y-m-d');
        $application->end_date             = $endDate->format('Y-m-d');
        $application->total_days           = $totalDays;
        $application->reason               = $validated['reason'];

        if ($isHR && !empty($validated['status'])) {
            $application->status = $validated['status'];
        }

        $application->save();

        LeavePermitLog::create([
            'application_id' => $application->id,
            'action'         => 'updated',
            'performed_by'   => $currentEmpId,
            'notes'          => 'Application details updated.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data'    => $application->load(['leavePermitType', 'employee.basicData']),
        ]);
    }

    /**
     * API: Approve Application (HR Only)
     */
    public function approveApplication(Request $request, $id)
    {
        $user       = session('user');
        $currentEmp = $user['id'] ?? null;

        $application = LeavePermitApplication::findOrFail($id);
        $application->status = 'approved';
        $application->reviewed_by = $currentEmp;
        $application->reviewed_at = now();
        $application->save();

        LeavePermitLog::create([
            'application_id' => $application->id,
            'action'         => 'approved',
            'performed_by'   => $currentEmp,
            'notes'          => $request->input('notes', 'Application approved by HR.'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave/permit application approved successfully.',
            'data'    => $application,
        ]);
    }

    /**
     * API: Reject Application (HR Only)
     */
    public function rejectApplication(Request $request, $id)
    {
        $user       = session('user');
        $currentEmp = $user['id'] ?? null;

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $application = LeavePermitApplication::findOrFail($id);
        $application->status = 'rejected';
        $application->reviewed_by = $currentEmp;
        $application->reviewed_at = now();
        $application->save();

        LeavePermitLog::create([
            'application_id' => $application->id,
            'action'         => 'rejected',
            'performed_by'   => $currentEmp,
            'notes'          => $validated['rejection_reason'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave/permit application rejected.',
            'data'    => $application,
        ]);
    }

    /**
     * API: Request Revision (HR Only)
     */
    public function requestRevision(Request $request, $id)
    {
        $user       = session('user');
        $currentEmp = $user['id'] ?? null;

        $validated = $request->validate([
            'revision_notes' => 'required|string|max:1000',
        ]);

        $application = LeavePermitApplication::findOrFail($id);
        $application->status = 'revision';
        $application->reviewed_by = $currentEmp;
        $application->reviewed_at = now();
        $application->save();

        LeavePermitLog::create([
            'application_id' => $application->id,
            'action'         => 'revision_requested',
            'performed_by'   => $currentEmp,
            'notes'          => $validated['revision_notes'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Revision request sent to employee.',
            'data'    => $application,
        ]);
    }

    /**
     * API: HR Monthly & Yearly Reports
     */
    public function getReport(Request $request)
    {
        $year  = (int) $request->input('year', date('Y'));
        $month = $request->filled('month') ? (int) $request->month : null;

        $query = LeavePermitApplication::with(['employee.basicData', 'leavePermitType'])
            ->whereYear('start_date', $year);

        if ($month) {
            $query->whereMonth('start_date', $month);
        }

        $applications = $query->get();

        $stats = [
            'total_applications'         => $applications->count(),
            'approved_count'             => $applications->where('status', 'approved')->count(),
            'pending_count'              => $applications->where('status', 'pending')->count(),
            'rejected_count'             => $applications->where('status', 'rejected')->count(),
            'revision_count'             => $applications->where('status', 'revision')->count(),
            'approved_days'              => (float) $applications->where('status', 'approved')->sum('total_days'),
            'total_requesting_employees' => $applications->pluck('employee_id')->unique()->count(),
            'approved_employees'         => $applications->where('status', 'approved')->pluck('employee_id')->unique()->count(),
        ];

        $byType = $applications->groupBy('leave_permit_type_id')->map(function ($items) {
            $first = $items->first();
            return [
                'type_id'        => $first->leave_permit_type_id,
                'type_code'      => $first->leavePermitType->code ?? 'UNK',
                'type_name'      => $first->leavePermitType->name ?? 'Unknown',
                'category'       => $first->leavePermitType->category ?? 'leave',
                'total_count'    => $items->count(),
                'approved_count' => $items->where('status', 'approved')->count(),
                'approved_days'  => (float) $items->where('status', 'approved')->sum('total_days'),
                'pending_count'  => $items->where('status', 'pending')->count(),
            ];
        })->values();

        $byEmployee = $applications->groupBy('employee_id')->map(function ($items) {
            $first = $items->first();
            $empName = $first->employee && $first->employee->basicData 
                ? $first->employee->basicData->full_name 
                : ($first->employee ? $first->employee->eci : 'Emp #' . $first->employee_id);
            $eci = $first->employee ? $first->employee->eci : '-';
            return [
                'employee_id'    => $first->employee_id,
                'eci'            => $eci,
                'employee_name'  => $empName,
                'total_apps'     => $items->count(),
                'approved_days'  => (float) $items->where('status', 'approved')->sum('total_days'),
                'pending_days'   => (float) $items->where('status', 'pending')->sum('total_days'),
                'rejected_apps'  => $items->where('status', 'rejected')->count(),
            ];
        })->values();

        $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthApps = $applications->filter(function ($app) use ($m) {
                return (int) Carbon::parse($app->start_date)->format('n') === $m;
            });
            $byMonth[] = [
                'month_num'        => $m,
                'month_name'       => $monthNames[$m],
                'total_apps'       => $monthApps->count(),
                'approved_days'    => (float) $monthApps->where('status', 'approved')->sum('total_days'),
                'unique_employees' => $monthApps->pluck('employee_id')->unique()->count(),
            ];
        }

        return response()->json([
            'success'      => true,
            'year'         => $year,
            'month'        => $month,
            'stats'        => $stats,
            'by_type'      => $byType,
            'by_employee'  => $byEmployee,
            'by_month'     => $byMonth,
            'applications' => $applications,
        ]);
    }
}
