<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_project_risks', function (Blueprint $table) {
            // Threat | Opportunity — menentukan opsi response strategy yang tersedia
            $table->string('risk_type', 20)->default('Threat')->after('risk_number');
            // Diisi saat status = Closed
            $table->date('actual_end_date')->nullable()->after('target_date');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_risks', function (Blueprint $table) {
            $table->dropColumn(['risk_type', 'actual_end_date']);
        });
    }
};
