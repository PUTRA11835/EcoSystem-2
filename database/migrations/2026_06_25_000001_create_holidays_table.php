<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');                              // tanggal libur (YYYY-MM-DD)
            $table->string('name');                            // nama libur
            $table->string('type', 20)->default('national');   // national | cuti_bersama | custom
            $table->boolean('is_active')->default(true);       // bisa dinonaktifkan tanpa hapus
            $table->timestamps();

            $table->index('date');
            // Satu tanggal boleh punya >1 entri (mis. cuti bersama + hari raya),
            // tapi nama yang sama persis di tanggal yang sama dicegah duplikat.
            $table->unique(['date', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
