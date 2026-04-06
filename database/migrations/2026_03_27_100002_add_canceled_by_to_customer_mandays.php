<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            $table->unsignedBigInteger('canceled_by_id')->nullable()->after('notes');
            $table->foreign('canceled_by_id')
                ->references('employee_id')
                ->on('employee')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            $table->dropForeign(['canceled_by_id']);
            $table->dropColumn('canceled_by_id');
        });
    }
};
