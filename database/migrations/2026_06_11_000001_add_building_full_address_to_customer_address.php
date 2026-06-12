<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_address', function (Blueprint $table) {
            // "Nama Gedung/Tempat" — nama gedung/tempat pada alamat (mis. "Graha Mantap")
            $table->string('building_name')->nullable()->after('house_number');
            // "Alamat Lengkap" — alamat lengkap utuh dari dokumen sumber
            $table->text('full_address')->nullable()->after('building_name');
        });
    }

    public function down(): void
    {
        Schema::table('customer_address', function (Blueprint $table) {
            $table->dropColumn(['building_name', 'full_address']);
        });
    }
};
