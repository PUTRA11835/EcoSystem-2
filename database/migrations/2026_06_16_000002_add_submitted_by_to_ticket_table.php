<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('submitted_by_email', 255)->nullable()->after('client');
            $table->string('submitted_by_name',  255)->nullable()->after('submitted_by_email');
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn(['submitted_by_email', 'submitted_by_name']);
        });
    }
};
