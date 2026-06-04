<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_employee', function (Blueprint $table) {
            // Working-day duration of this member's involvement in the activity.
            // Must not exceed the activity's own duration (validated in controller).
            $table->unsignedInteger('duration')->nullable()->after('allocation_percentage');
        });

        // The activity role now mirrors the project team-member role
        // (e.g. "Project Manager", "Co Project Manager", "Lead", "Member"),
        // so widen the old lowercase enum into a free-form string.
        Schema::table('activity_employee', function (Blueprint $table) {
            $table->string('role', 100)->default('member')->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_employee', function (Blueprint $table) {
            $table->dropColumn('duration');
        });

        Schema::table('activity_employee', function (Blueprint $table) {
            $table->enum('role', ['lead', 'member', 'reviewer', 'support'])
                ->default('member')
                ->change();
        });
    }
};
