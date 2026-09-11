<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor (opsional) pada Delivery Support — menunjuk ke master Business Partner
 * (`customer`) bertipe Vendor. FK + onDelete('set null') mengikuti pola
 * `client_id` di tabel yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('delivery_support', 'vendor_id')) {
            return;
        }

        Schema::table('delivery_support', function (Blueprint $table) {
            $table->unsignedBigInteger('vendor_id')->nullable()->after('client_id');
            $table->index('vendor_id');
            $table->foreign('vendor_id')->references('customer_id')->on('customer')->onDelete('set null');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('delivery_support', 'vendor_id')) {
            return;
        }

        Schema::table('delivery_support', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }
};
