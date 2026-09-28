<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-time backfill. `customer_basic_data.created_on` and
 * `employee_basic_data.created_on` were added later (see
 * 2026_03_17_000001_add_audit_columns_to_customer_basic_data and the
 * equivalent employee migration) and were only ever populated by
 * application code going forward — records created before that code
 * shipped never got a value, so their "Created On" shows blank in the UI
 * (e.g. Business Partner/Employee detail pages) even though the record
 * genuinely has a creation date, just under the standard `created_at`
 * column instead.
 *
 * This copies `created_at` into `created_on` wherever `created_on` is
 * still null — `created_at` is Eloquent-managed and has always been set,
 * so it's a reliable stand-in for the missing value. `created_by` is
 * deliberately left alone: there is no stored record of who created
 * these older rows, so leaving it blank is more honest than guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_basic_data')
            ->whereNull('created_on')
            ->whereNotNull('created_at')
            ->update(['created_on' => DB::raw('created_at')]);

        DB::table('employee_basic_data')
            ->whereNull('created_on')
            ->whereNotNull('created_at')
            ->update(['created_on' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Data backfill only — not reversible (we can no longer tell which
        // rows were backfilled here vs. genuinely set by application code).
    }
};
