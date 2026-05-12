<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_customer_id')->nullable()->after('customer_id');
            $table->foreign('parent_customer_id')
                  ->references('customer_id')
                  ->on('customer')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropForeign(['parent_customer_id']);
            $table->dropColumn('parent_customer_id');
        });
    }
};
