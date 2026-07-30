<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master Customer menjadi "Business Partner": satu master untuk dua jenis mitra
 * (Customer & Vendor) yang dibedakan kolom `type`.
 *
 * Seluruh baris yang sudah ada adalah customer, jadi default 'Customer' sekaligus
 * jadi backfill data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('customer', 'type')) {
            return;
        }

        Schema::table('customer', function (Blueprint $table) {
            $table->enum('type', ['Customer', 'Vendor'])
                ->default('Customer')
                ->after('customer_code');
            $table->index('type');
        });

        // Eksplisit walau default sudah mengisi — jaga-jaga bila ada baris dengan
        // nilai NULL dari mode SQL yang longgar.
        DB::table('customer')->whereNull('type')->update(['type' => 'Customer']);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('customer', 'type')) {
            return;
        }

        Schema::table('customer', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
