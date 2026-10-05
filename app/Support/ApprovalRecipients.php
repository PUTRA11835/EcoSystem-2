<?php

namespace App\Support;

use App\Models\Employee;

/**
 * Resolusi penerima notifikasi "perlu tindakan Anda" dari SATU baris langkah
 * persetujuan — dipakai bersama oleh 5 modul (Overtime, Reimbursement,
 * Purchase Request, Cash Advance, Cash Advance Report) karena baris langkah
 * (snapshot) kelimanya berbagi bentuk kolom yang SAMA persis:
 * `approver_type` ('role' | 'employee' | 'direct_manager'), `approver_role_id`,
 * `approver_employee_ids` (JSON array) — lihat method `allows()` pada
 * masing-masing model *RequestApproval/ *ReportApproval, logikanya di sini
 * sengaja DISAMAKAN dengan `allows()`, bukan ditulis ulang dari nol.
 */
class ApprovalRecipients
{
    /**
     * @param  object{approver_type: ?string, approver_role_id: ?int, approver_employee_ids: ?array}  $step
     * @return array<int>  id karyawan, tanpa duplikat.
     */
    public static function forStep(object $step): array
    {
        $ids = match ($step->approver_type) {
            'role' => $step->approver_role_id === null ? [] : Employee::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('employee_role.id', $step->approver_role_id))
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all(),

            'employee' => array_map('intval', $step->approver_employee_ids ?? []),

            // direct_manager: hierarki atasan belum ada di basis data (sama
            // seperti `allows()` di tiap model Approval) — belum ada penerima.
            default => [],
        };

        return array_values(array_unique($ids));
    }
}
