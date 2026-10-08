<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free text beside the Source platform, so a candidate's origin can be recorded in
 * words, e.g. "Referred by Budi (Finance)". The platform stays a fixed list so the
 * Job Source Platforms chart keeps grouping cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->string('source_detail', 150)->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn('source_detail');
        });
    }
};
