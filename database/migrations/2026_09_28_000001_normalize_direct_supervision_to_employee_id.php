<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Master Data used to accept free text (e.g. an ECI) in "Direct Supervision",
 * but KPI / approval code reads it as an employee_id. Convert legacy ECI values
 * to the supervisor's employee_id. Unresolvable values are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $eciToId = DB::table('employee')->pluck('employee_id', 'eci');
        $validIds = DB::table('employee')->pluck('employee_id')->flip();

        DB::table('employee_basic_data')
            ->whereNotNull('direct_supervision')
            ->where('direct_supervision', '!=', '')
            ->select('basic_data_id', 'direct_supervision')
            ->orderBy('basic_data_id')
            ->each(function ($row) use ($eciToId, $validIds) {
                $value = trim($row->direct_supervision);

                if (ctype_digit($value) && isset($validIds[(int) $value])) {
                    return; // already an employee_id
                }

                $id = $eciToId[$value] ?? null;
                if ($id) {
                    DB::table('employee_basic_data')
                        ->where('basic_data_id', $row->basic_data_id)
                        ->update(['direct_supervision' => (string) $id]);
                }
            });
    }

    public function down(): void
    {
        // Data normalisation only — nothing to reverse.
    }
};
