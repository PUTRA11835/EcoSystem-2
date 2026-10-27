<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For databases that already ran 2026_10_27_000001: bring the seeded lists in line with the UAT revision (titles with Prof., religion as
 * "Konghucu" with the same names in Basic Data and Family, English document labels). Only touches what is still the old default — a label
 * or a list an administrator already changed is left alone. Fresh databases get the new defaults from the first migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_field_options')) {
            return;
        }

        $add = function (string $section, string $field, string $value, string $label) {
            $q = DB::table('employee_field_options')->where('section', $section)->where('field', $field);
            if ($q->clone()->where('value', $value)->exists() || !$q->clone()->exists()) {
                return;     // already there, or the list was never seeded (the code defaults apply)
            }
            DB::table('employee_field_options')->insert([
                'section' => $section, 'field' => $field, 'value' => $value, 'label' => $label,
                'sort_order' => 1 + (int) $q->clone()->max('sort_order'), 'is_active' => true, 'internal_only' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $relabel = function (string $section, string $field, string $value, string $from, string $to) {
            DB::table('employee_field_options')->where('section', $section)->where('field', $field)
                ->where('value', $value)->where('label', $from)->update(['label' => $to, 'updated_at' => now()]);
        };

        $add('basic-data', 'title', 'Prof.', 'Prof.');
        $add('family', 'title', 'Prof.', 'Prof.');

        foreach (['basic-data', 'family'] as $section) {
            $add($section, 'religion', 'Konghucu', 'Konghucu');
        }
        // The family list used Christianity / Catholicism / … : the same names as Basic Data now (the API normalises the old spellings).
        foreach (['Christianity' => ['Christian', 'Christian'], 'Catholicism' => ['Catholic', 'Catholic'], 'Hinduism' => ['Hindu', 'Hindu'], 'Buddhism' => ['Buddhist', 'Buddhist']] as $old => [$new, $label]) {
            $list = fn () => DB::table('employee_field_options')->where('section', 'family')->where('field', 'religion');
            if (!$list()->where('value', $new)->exists()) {
                $list()->where('value', $old)->update(['value' => $new, 'label' => $label, 'updated_at' => now()]);
            }
        }

        $relabel('identification', 'identification_type', 'KITAS', 'KITAS', 'KITAS (Temporary Stay Permit)');
        $relabel('identification', 'identification_type', 'KITAP', 'KITAP', 'KITAP (Permanent Stay Permit)');

        foreach ([
            'Paspor' => ['Paspor / Passport', 'Passport'], 'Ijazah' => ['Ijazah / Diploma', 'Diploma Certificate'], 'Transkrip Nilai' => ['Transkrip Nilai', 'Academic Transcript'],
            'Sertifikat' => ['Sertifikat', 'Certificate'], 'Kontrak Kerja' => ['Kontrak Kerja', 'Employment Contract'], 'SK Pengangkatan' => ['SK Pengangkatan', 'Appointment Letter'],
            'Surat Referensi' => ['Surat Referensi', 'Reference Letter'], 'Akta Lahir' => ['Akta Lahir', 'Birth Certificate'], 'Kartu Keluarga' => ['Kartu Keluarga', 'Family Card (KK)'],
            'Foto' => ['Foto', 'Photo'], 'Lainnya' => ['Lainnya', 'Other'],
        ] as $value => [$from, $to]) {
            $relabel('attachment', 'document_type', $value, $from, $to);
        }

        if (class_exists(\App\Services\Management\EmployeeMasterData::class)) {
            \App\Services\Management\EmployeeMasterData::forget();
        }
    }

    public function down(): void
    {
        // Data alignment only — nothing to undo.
    }
};
