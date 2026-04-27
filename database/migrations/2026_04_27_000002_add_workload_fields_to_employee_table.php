<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            // SAP modules handled by this consultant, comma-separated e.g. "FICO,SD,MM"
            $table->string('modules', 255)->nullable()->after('eci');
            $table->decimal('monthly_capacity_md', 5, 2)->default(20)->after('modules');
        });
    }

    public function down(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->dropColumn(['modules', 'monthly_capacity_md']);
        });
    }
};
