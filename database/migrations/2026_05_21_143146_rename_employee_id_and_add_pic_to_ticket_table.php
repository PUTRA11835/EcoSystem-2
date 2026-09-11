<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename employee_id → ticket_lead_id
        Schema::table('ticket', function (Blueprint $table) {
            $table->renameColumn('employee_id', 'ticket_lead_id');
        });

        // 2. Add pic column (nullable dulu — akan diisi di step 3)
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('pic')->nullable()->after('ticket_lead_id');
        });

        // 3. Populate pic untuk semua baris existing
        DB::statement("
            UPDATE ticket t
            LEFT JOIN employee e ON t.ticket_lead_id = e.employee_id
            LEFT JOIN employee_basic_data ebd ON e.employee_id = ebd.employee_id
            SET t.pic = CASE
                WHEN t.ticket_lead_id IS NOT NULL
                    THEN TRIM(CONCAT(COALESCE(ebd.first_name,''), ' ', COALESCE(ebd.last_name,'')))
                ELSE 'Helpdesk'
            END
        ");

        // 4. Jadikan NOT NULL setelah semua baris terisi
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('pic')->nullable(false)->default('Helpdesk')->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('pic');
        });
        Schema::table('ticket', function (Blueprint $table) {
            $table->renameColumn('ticket_lead_id', 'employee_id');
        });
    }
};
