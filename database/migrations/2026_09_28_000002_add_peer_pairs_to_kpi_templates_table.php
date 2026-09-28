<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            // Peer templates only: explicit "reviewer rates reviewee" pairs chosen
            // by HR, stored as [{"reviewer": <employee_id>, "reviewee": <employee_id>}].
            // When empty, the template behaves as before (audience + subject list,
            // falling back to each employee's direct supervisor).
            $table->json('peer_pairs')->nullable()->after('subject_employees');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn('peer_pairs');
        });
    }
};
