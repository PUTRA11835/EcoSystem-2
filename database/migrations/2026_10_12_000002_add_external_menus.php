<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access untuk seksi External (konsultan eksternal), di bawah HR & General:
 *
 *   External                              general.external                         (group; hanya induk di Menu Access)
 *   ├─ Consultants                        general.external.consultants             page  (hanya lihat; ubah data di Master Employee)
 *   ├─ Timesheet & Payment                general.external.timesheet               page  (placeholder "Coming soon")
 *   ├─ External Contract                  general.external.contracts               page  C buat · E ubah / ganti status · D hapus Draft
 *   ├─ Assignment Letter                  general.external.assignment-letters      page  C buat surat
 *   ├─ Employment Certificate             general.external.employment-certificates page  C buat surat
 *   └─ Reference Letter                   general.external.reference-letters       page  C buat surat
 *
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator; pembagian ke role lain dilakukan di
 * Management → Roles → Menu Access. Peluncuran ini tidak mengubah perilaku siapa pun yang sudah live.
 *
 * Juga mendaftarkan tiga jenis surat konsultan di Letter Templates → Settings (bahasa & kode surat awal disalin dari jenis
 * pasangannya yang sudah ada) — baris baru saja, tidak ada baris lama yang diubah.
 */
return new class extends Migration
{
    private const PAGES = [
        'general.external.consultants'             => 'Consultants',
        'general.external.timesheet'              => 'Timesheet & Payment',
        'general.external.contracts'              => 'External Contract',
        'general.external.assignment-letters'     => 'Assignment Letter',
        'general.external.employment-certificates' => 'Employment Certificate',
        'general.external.reference-letters'      => 'Reference Letter',
    ];

    /** Slug yang menegakkan kotak Create / Edit / Delete di server. */
    private const CRUD = [
        'general.external.contracts', 'general.external.assignment-letters',
        'general.external.employment-certificates', 'general.external.reference-letters',
    ];

    /** jenis surat konsultan → jenis surat internal yang pengaturan awalnya disalin */
    private const LETTER_TYPES = [
        'ext_assignment_letter'       => 'assignment_letter',
        'ext_employment_certificate'  => 'employment_certificate',
        'ext_reference_letter'        => 'employment_reference',
    ];

    public function up(): void
    {
        if (!MenuRegistrar::register('general', ['general.external' => 'External'], 79, 'group')) {
            return;
        }

        $seq = 1;
        foreach (self::PAGES as $slug => $name) {
            MenuRegistrar::register('general.external', [$slug => $name], $seq++, 'page');
        }

        MenuRegistrar::grantToAdminAndRoles(self::CRUD, [], ['create', 'edit', 'delete']);

        foreach (self::LETTER_TYPES as $type => $counterpart) {
            if (DB::table('letter_type_settings')->where('letter_type', $type)->exists()) {
                continue;
            }
            $base = DB::table('letter_type_settings')->where('letter_type', $counterpart)->first();
            DB::table('letter_type_settings')->insert([
                'letter_type'    => $type,
                'language'       => $base->language ?? 'id',
                'is_active'      => 1,
                'letter_code_id' => $base->letter_code_id ?? null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::PAGES), 'general.external']);
        // Hanya baris pengaturan yang dibuat migrasi ini; surat konsultan yang sudah terbit tidak disentuh.
        DB::table('letter_type_settings')->whereIn('letter_type', array_keys(self::LETTER_TYPES))->delete();
        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
