<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add category column to ticket table.
 *
 * Berbeda dengan kolom `type` yang sudah dipindahkan ke delivery_support
 * (untuk klasifikasi AMS/MO/ATS/Project/Internal), kolom `category` ini
 * digunakan untuk mengklasifikasikan tiket dari sisi helpdesk/mobile:
 * Bug, Feature Request, Improvement, Question.
 *
 * @date 2026-03-30
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket', 'category')) {
                $table->string('category')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            if (Schema::hasColumn('ticket', 'category')) {
                $table->dropColumn('category');
            }
        });
    }
};
