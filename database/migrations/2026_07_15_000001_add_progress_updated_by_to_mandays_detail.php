<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->unsignedBigInteger('progress_updated_by')->nullable()->after('progress_updated_at');

            $table->foreign('progress_updated_by')
                  ->references('employee_id')
                  ->on('employee')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->dropForeign(['progress_updated_by']);
            $table->dropColumn('progress_updated_by');
        });
    }
};
