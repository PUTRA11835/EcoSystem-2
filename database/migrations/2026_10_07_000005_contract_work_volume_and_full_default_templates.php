<?php

use App\Support\Contracts\DefaultTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Contract (HC-D67):
 *
 *  1. `employee_contract.work_volume` — "Jumlah waktu pelaksanaan" kontrak External (mis. "20,00 mandays"); tersedia sebagai
 *     placeholder {{volume_kerja}}. Kolom baru boleh kosong; kontrak lama tak terpengaruh.
 *  2. Teks tiga template bawaan diganti isi LENGKAP dari PDF template ESH (PKWT 14 pasal, PKWTT 16 pasal, PKS 9 pasal).
 *     Hanya baris bawaan yang BELUM PERNAH disunting orang (`updated_by` kosong) yang ditimpa — template yang sudah disunting
 *     HR dibiarkan. Kontrak Active tidak terpengaruh (teksnya sudah dibekukan di body_html).
 *
 * down(): hanya melepas kolom; teks lama (kerangka) tidak dipulihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employee_contract', 'work_volume')) {
            Schema::table('employee_contract', function (Blueprint $table) {
                $table->string('work_volume', 100)->nullable()->after('work_location');
            });
        }

        $now = now();
        foreach (DefaultTemplates::all() as $template) {
            DB::table('contract_templates')
                ->where('is_system_default', 1)
                ->where('contract_type', $template['contract_type'])
                ->whereNull('updated_by')
                ->update([
                    'name'        => $template['name'],
                    'description' => $template['description'],
                    'body_html'   => $template['body_html'],
                    'updated_at'  => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employee_contract', 'work_volume')) {
            Schema::table('employee_contract', function (Blueprint $table) {
                $table->dropColumn('work_volume');
            });
        }
    }
};
