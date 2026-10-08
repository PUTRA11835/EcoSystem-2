<?php

use App\Support\Contracts\DefaultTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Contract (HC-D70) — penandatangan bawaan per template, kotak meterai, dan salinan bermeterai yang diunggah.
 *
 *  contract_templates.signatory_employee_id   penandatangan bawaan template (dari Letter Templates → Settings → Signers);
 *                                             dipakai bila kontrak tidak memilih sendiri.
 *  employee_contract.signed_file_path/_name   salinan kontrak yang sudah ditandatangani + dibubuhi meterai (metode 1: unduh →
 *                                             tempel meterai → unggah), disimpan di disk PRIVAT, bukan public.
 *  employee_contract.signed_file_at/_by       kapan dan siapa yang mengunggah
 *  employee_contract.stamp_method             physical (unggah salinan) | e_meterai (disiapkan untuk integrasi penyedia e-Meterai)
 *
 * Template bawaan yang belum pernah disunting ikut menerima kotak {{meterai}} di sel tanda tangan pekerja.
 * Semua kolom boleh kosong; kontrak lama tak terpengaruh. down() hanya melepas kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contract_templates', 'signatory_employee_id')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->unsignedBigInteger('signatory_employee_id')->nullable()->after('use_letterhead');
            });
        }

        Schema::table('employee_contract', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contract', 'signed_file_path')) {
                $table->string('signed_file_path', 255)->nullable();
                $table->string('signed_file_name', 255)->nullable();
                $table->timestamp('signed_file_at')->nullable();
                $table->unsignedBigInteger('signed_file_by')->nullable();
                $table->string('stamp_method', 20)->nullable();
            }
        });

        foreach (DefaultTemplates::all() as $template) {
            DB::table('contract_templates')
                ->where('is_system_default', 1)
                ->where('contract_type', $template['contract_type'])
                ->whereNull('updated_by')
                ->update(['body_html' => $template['body_html'], 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_templates', 'signatory_employee_id')) {
            Schema::table('contract_templates', fn (Blueprint $t) => $t->dropColumn('signatory_employee_id'));
        }
        $cols = array_values(array_filter(
            ['signed_file_path', 'signed_file_name', 'signed_file_at', 'signed_file_by', 'stamp_method'],
            fn ($c) => Schema::hasColumn('employee_contract', $c)
        ));
        if ($cols) {
            Schema::table('employee_contract', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
