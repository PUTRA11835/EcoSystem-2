<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Master data "Project Type" (delivery_projects.project_type) dan "Support
 * Type" (delivery_support.type) — dulu hardcoded di banyak tempat:
 *   - resources/views/delivery/project/projects/create.blade.php
 *   - resources/views/delivery/project/projects/partials/modal-general-info.blade.php
 *   - App\Http\Controllers\DeliveryProjectController (Rule::in ...)
 *   - resources/views/delivery/support/list/create.blade.php & edit.blade.php
 *   - resources/views/delivery/support/index.blade.php (filter dropdown)
 *   - App\Http\Controllers\Delivery\DeliverySupportController (Rule::in ...)
 *
 * Dipindah ke master data (menu Management > Master Delivery Settings) supaya
 * admin bisa menambah/menonaktifkan tipe tanpa deploy kode — mirror pola
 * Deliverable Document Type (2026_09_07_000001).
 *
 * Kedua kolom sumber (project_type & type) tetap string biasa (bukan FK), jadi
 * menghapus sebuah tipe di sini tidak mengubah data yang sudah tersimpan,
 * hanya menghilangkannya dari pilihan dropdown ke depan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_seq')->default(0);
            $table->timestamps();
        });

        Schema::create('delivery_support_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_seq')->default(0);
            $table->timestamps();
        });

        $now = now();

        $projectTypes = ['Implementation', 'Roll Out', 'Migration', 'Upgrade', 'WRICEF', 'Body Hire'];
        foreach ($projectTypes as $i => $name) {
            DB::table('delivery_project_types')->insert([
                'name'       => $name,
                'is_active'  => true,
                'order_seq'  => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $supportTypes = ['AMS', 'MO', 'ATS', 'CR', 'RISE', 'CLOUD', 'POSTPAID', 'Project', 'Internal'];
        foreach ($supportTypes as $i => $name) {
            DB::table('delivery_support_types')->insert([
                'name'       => $name,
                'is_active'  => true,
                'order_seq'  => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_support_types');
        Schema::dropIfExists('delivery_project_types');
    }
};
