<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->json('ai_analysis')->nullable()->after('module_id');
            $table->timestamp('ai_analysis_generated_at')->nullable()->after('ai_analysis');
            $table->unsignedBigInteger('ai_analysis_generated_by')->nullable()->after('ai_analysis_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropColumn(['ai_analysis', 'ai_analysis_generated_at', 'ai_analysis_generated_by']);
        });
    }
};
