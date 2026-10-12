<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project timesheet enhancement:
 *  - GPS device location captured when the timesheet is created (latitude/longitude/
 *    accuracy/captured_at) + the reverse-geocoded address shown on the approval side.
 *    `location` stays the user's free-text location.
 *  - `is_without_activity` — project timesheet logged on a weekend / public holiday,
 *    where no activity runs; the detail is written manually in `description`.
 *  - `activity_type` becomes nullable: project timesheets no longer ask for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->decimal('gps_latitude', 10, 7)->nullable()->after('location');
            $table->decimal('gps_longitude', 10, 7)->nullable()->after('gps_latitude');
            $table->decimal('gps_accuracy', 10, 2)->nullable()->after('gps_longitude'); // meters
            $table->timestamp('gps_captured_at')->nullable()->after('gps_accuracy');
            $table->text('gps_address')->nullable()->after('gps_captured_at');
            $table->boolean('is_without_activity')->default(false)->after('activity_id');
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->enum('activity_type', ['development', 'meeting', 'documentation', 'testing', 'support', 'training', 'other'])
                ->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn([
                'gps_latitude', 'gps_longitude', 'gps_accuracy', 'gps_captured_at', 'gps_address',
                'is_without_activity',
            ]);
        });

        Schema::table('timesheets', function (Blueprint $table) {
            $table->enum('activity_type', ['development', 'meeting', 'documentation', 'testing', 'support', 'training', 'other'])
                ->default('development')->change();
        });
    }
};
