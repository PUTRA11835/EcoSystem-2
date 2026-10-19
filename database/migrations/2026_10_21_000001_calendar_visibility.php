<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * events.is_public — an event is PERSONAL (only its creator sees it) or COMPANY-WIDE (every employee; only a role holding
 * the Create / Edit / Delete boxes of `calendar.events` makes or changes one). Events that exist today were visible to
 * everyone and had no `created_by` (the controller read a session key that does not exist), so they become company-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('all_day');
            $table->index(['is_public', 'created_by']);
        });
        DB::table('events')->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'created_by']);
            $table->dropColumn('is_public');
        });
    }
};
