<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel referensi Grade consultant — single source of truth untuk:
 *   1. Opsi dropdown Grade di form employee (master/profile).
 *   2. Harga Man-Day (MD) per grade (kolom `md_price`, diisi saat fitur harga aktif).
 *
 * Catatan desain: `employee_basic_data.grade` TETAP menyimpan nama grade sebagai
 * string (penghubung lewat `grades.name`), bukan FK — agar minim disrupsi & import
 * tetap berbasis nama. FK `grade_id` bisa ditambah belakangan bila diperlukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // Harga Man-Day per grade — nullable, diisi nanti saat fitur harga MD aktif.
            $table->decimal('md_price', 15, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed 7 grade awal (urutan = jenjang karier). Sama persis dengan opsi
        // dropdown lama agar data employee existing tetap cocok.
        $names = [
            'Management Trainee',
            'Junior Consultant',
            'Associate Consultant',
            'Middle Consultant',
            'Senior Consultant',
            'Principal Consultant',
            'Expert Consultant',
        ];

        $now  = now();
        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = [
                'name'       => $name,
                'md_price'   => null,
                'sort_order' => $i + 1,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('grades')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
