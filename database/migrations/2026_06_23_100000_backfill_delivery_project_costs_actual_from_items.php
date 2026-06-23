<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reconcile existing data with the new rule:
 *   actual_amount = SUM(expense detail items)  (0 when there are none).
 *
 * Actual is now fully derived from the expense detail items and is no longer
 * entered manually. This backfill brings every leaf cost row in line so the
 * "Actual" column reflects its expense total (and shows "Rp 0" when empty).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Leaf rows only (rows that are NOT a parent of any other cost row).
        $leafIds = DB::table('delivery_project_costs as c')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('delivery_project_costs as ch')
                  ->whereColumn('ch.parent_id', 'c.id');
            })
            ->pluck('c.id');

        foreach ($leafIds as $id) {
            $sum = (float) DB::table('delivery_project_cost_items')
                ->where('delivery_project_cost_id', $id)
                ->sum('amount');

            DB::table('delivery_project_costs')
                ->where('id', $id)
                ->update(['actual_amount' => $sum]);
        }
    }

    public function down(): void
    {
        // Data reconciliation only — nothing to roll back.
    }
};
