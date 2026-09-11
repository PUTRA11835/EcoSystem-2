<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }

    public function down(): void
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('employee_id');
            $table->foreign('role_id')->references('id')->on('employee_role')->onDelete('cascade');
        });
    }
};
