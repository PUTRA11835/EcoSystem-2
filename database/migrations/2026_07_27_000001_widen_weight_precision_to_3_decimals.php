<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weight (%) di Project Configuration kini boleh 3 angka di belakang koma.
 * Kolom weight sebelumnya decimal(5,2) → truncate ke 2 desimal. Dilebarkan ke
 * decimal(8,3) di seluruh tabel yang menyimpan bobot (phase / activity / planning
 * group / activity stage) agar roll-up bobot tetap konsisten.
 */
return new class extends Migration
{
    private array $tables = [
        'delivery_project_phases',
        'delivery_project_activities',
        'delivery_project_planning',
        'activity_stages',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'weight')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('weight', 8, 3)->default(0)->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'weight')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('weight', 5, 2)->default(0)->change();
                });
            }
        }
    }
};
