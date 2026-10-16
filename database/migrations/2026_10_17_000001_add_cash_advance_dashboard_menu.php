<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tab "Dashboard" of Finance → Cash Advance Management.
 *
 * The hub has four tabs and each one has its own View box in Menu Access:
 *
 *   Dashboard            general.cash-advance-dashboard   (this migration)
 *   Cash Advance (CA)    general.cash-advance
 *   Cash Advance Report  general.cash-advance-report
 *   Settings             management.cash-advance-settings (+ the two approval-workflow slugs)
 *
 * Like every new slug it is born active for EC Administrator only (MenuRegistrar's standing rule); the owner of
 * the system decides which roles get the tab. It is a top-level page, the same as its sibling tabs.
 */
return new class extends Migration
{
    private const SLUG = 'general.cash-advance-dashboard';

    public function up(): void
    {
        MenuRegistrar::register('general', [self::SLUG => 'Cash Advance Dashboard'], 90, 'page');

        DB::table('menu')->where('slug', self::SLUG)->update([
            'parent_id'  => null,
            'order_seq'  => 6,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
