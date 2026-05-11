<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('end_customer_id')->nullable()->after('customer_id');
            $table->foreign('end_customer_id')->references('customer_id')->on('customer')->nullOnDelete();
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->unsignedBigInteger('end_customer_id')->nullable()->after('customer_id');
            $table->foreign('end_customer_id')->references('customer_id')->on('customer')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropForeign(['end_customer_id']);
            $table->dropColumn('end_customer_id');
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->dropForeign(['end_customer_id']);
            $table->dropColumn('end_customer_id');
        });
    }
};
