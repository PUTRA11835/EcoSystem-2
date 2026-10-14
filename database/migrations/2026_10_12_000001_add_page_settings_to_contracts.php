<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Contract (HC-D72) — ukuran kertas, orientasi, dan margin dokumen.
 *
 *  contract_templates.page_size / page_orientation   A3 A4 A5 B5 F4 LETTER LEGAL · portrait|landscape (bawaan A4 potret)
 *  contract_templates.margin_top|right|bottom|left   cm; KOSONG = bawaan (lihat App\Support\Contracts\PageSettings)
 *  employee_contract.page_json                       pengaturan di atas, DIBEKUKAN saat kontrak Active bersama body_html
 *
 * Semua kolom boleh kosong: template & kontrak lama tetap A4 persis seperti sebelumnya. down() hanya melepas kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('contract_templates', 'page_size')) {
                $table->string('page_size', 10)->nullable()->after('use_letterhead');
                $table->string('page_orientation', 10)->nullable()->after('page_size');
                $table->decimal('margin_top', 4, 2)->nullable()->after('page_orientation');
                $table->decimal('margin_right', 4, 2)->nullable()->after('margin_top');
                $table->decimal('margin_bottom', 4, 2)->nullable()->after('margin_right');
                $table->decimal('margin_left', 4, 2)->nullable()->after('margin_bottom');
            }
        });

        if (!Schema::hasColumn('employee_contract', 'page_json')) {
            Schema::table('employee_contract', fn (Blueprint $t) => $t->json('page_json')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_templates', 'page_size')) {
            Schema::table('contract_templates', fn (Blueprint $t) => $t->dropColumn(['page_size', 'page_orientation', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left']));
        }
        if (Schema::hasColumn('employee_contract', 'page_json')) {
            Schema::table('employee_contract', fn (Blueprint $t) => $t->dropColumn('page_json'));
        }
    }
};
