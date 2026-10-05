<?php

namespace App\Services\HrProfile;

use App\Models\Employee;
use App\Models\EmployeeEngagement;
use App\Models\EmployeeHistory;
use Illuminate\Support\Facades\DB;

/**
 * Blok Engagement konsultan External: baca dan simpan (HC-D47).
 *
 * Izin seksi dicek di rute; izin TARIF dan aturan "hanya External/bukan diri sendiri" di controller.
 * Service ini menjaga isi: kunci yang diterima, validasi (EngagementRules), dan riwayat — yang hanya
 * mencatat NAMA field yang berubah, tidak pernah nilainya (terutama tarif).
 */
class EngagementService
{
    /**
     * @return array<string,mixed>
     */
    public function forEmployee(int $employeeId, bool $includeRate): array
    {
        $e = EmployeeEngagement::where('employee_id', $employeeId)->first();

        $fields = [
            'engagement_scheme' => $e?->engagement_scheme,
            'vendor_partner'    => $e?->vendor_partner,
            'client_company'    => $e?->client_company,
            'assignment_role'   => $e?->assignment_role,
            'managed_by'        => $e?->managed_by,
            'start_date'        => $e?->start_date?->format('Y-m-d'),
            'end_date'          => $e?->end_date?->format('Y-m-d'),
        ];
        if ($includeRate) {
            $fields['rate']     = $e?->rate;          // string "2700000.00" atau null
            $fields['currency'] = $e?->currency ?: 'IDR';
        }

        return ['fields' => $fields, 'scheme_options' => EngagementRules::SCHEMES, 'currencies' => EngagementRules::CURRENCIES];
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{ok: bool, errors: array<string,string>, rejected: string[]}
     */
    public function save(int $employeeId, array $input, int $actorId, bool $canEditRate): array
    {
        $allowed = $canEditRate
            ? array_merge(EngagementRules::BASIC_FIELDS, EngagementRules::RATE_FIELDS)
            : EngagementRules::BASIC_FIELDS;

        $rejected = array_values(array_map('strval', array_diff(array_keys($input), $allowed)));
        if ($rejected) {
            return ['ok' => false, 'errors' => ['_' => 'You are not allowed to change: ' . implode(', ', $rejected)], 'rejected' => $rejected];
        }

        if (!Employee::where('employee_id', $employeeId)->exists()) {
            return ['ok' => false, 'errors' => ['_' => 'Employee not found.'], 'rejected' => []];
        }

        $current = EmployeeEngagement::where('employee_id', $employeeId)->first();
        [$clean, $errors] = EngagementRules::clean(
            $input,
            $current?->start_date?->format('Y-m-d'),
            $current?->end_date?->format('Y-m-d')
        );
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'rejected' => []];
        }
        if (!$clean) {
            return ['ok' => true, 'errors' => [], 'rejected' => []];
        }

        DB::transaction(function () use ($employeeId, $clean, $current, $actorId) {
            $row = $current ?: new EmployeeEngagement(['employee_id' => $employeeId]);

            $row->fill(array_intersect_key($clean, array_flip(EngagementRules::BASIC_FIELDS)));
            $rateTouched = array_intersect_key($clean, array_flip(EngagementRules::RATE_FIELDS));
            if ($rateTouched) {
                $row->forceFill($rateTouched);
            }
            $row->forceFill(['updated_by' => $actorId]);

            $changed = array_values(array_diff(array_keys($row->getDirty()), ['updated_by', 'employee_id']));
            $row->save();

            $basicChanged = array_values(array_diff($changed, EngagementRules::RATE_FIELDS));
            $rateChanged  = array_values(array_intersect($changed, EngagementRules::RATE_FIELDS));

            if ($basicChanged) {
                $this->history($employeeId, $actorId, 'Engagement updated', implode(', ', $basicChanged));
            }
            if ($rateChanged) {
                $this->history($employeeId, $actorId, 'Engagement rate changed', 'rate/currency');
            }
        });

        return ['ok' => true, 'errors' => [], 'rejected' => []];
    }

    private function history(int $employeeId, int $actorId, string $action, string $fieldNames): void
    {
        // Nama field saja — nilai (terutama tarif) tidak pernah dicatat.
        EmployeeHistory::create([
            'employee_id'  => $employeeId,
            'action'       => $action,
            'description'  => 'Engagement: ' . $fieldNames,
            'performed_by' => $actorId,
            'performed_at' => now(),
        ]);
    }
}
