<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Offboarding (HR & General → Offboarding): proses keluarnya karyawan/konsultan — daftar tugas, Final Settlement, nonaktifkan akses.
 *
 * ADITIF dan MATI BAWAAN: `offboarding_settings.module_enabled = 0` dan `calculator_enabled = 0`. Tidak ada tabel lama yang diubah;
 * Offboarding TIDAK memakai hapus-keras karyawan dan tidak menyentuh `deletion_flag`.
 * Nominal Final Settlement SENSITIF (slug `general.offboarding.settlement`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offboarding_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('module_enabled')->default(false);                    // saklar modul
            $table->boolean('calculator_enabled')->default(false);                // saklar kalkulator Final Settlement
            $table->boolean('auto_deactivate')->default(false);                   // nonaktifkan akses otomatis pada tanggal efektif
            $table->boolean('include_external')->default(true);                   // konsultan External ikut Offboarding
            $table->boolean('require_tasks_before_deactivate')->default(true);    // semua tugas wajib selesai sebelum akses dinonaktifkan
            $table->unsignedSmallInteger('reminder_days')->default(3);            // pengingat tugas jatuh tempo (hari sebelumnya)
            $table->decimal('leave_divisor', 4, 1)->default(21);                  // pembagi upah harian uang penggantian cuti
            $table->timestamps();
        });

        Schema::create('offboarding_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('task_key', 40)->unique();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->smallInteger('due_offset_days')->default(0);                  // jatuh tempo = tanggal efektif + offset (negatif = sebelum)
            $table->string('applies_to', 10)->default('both');                    // internal | external | both
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('offboardings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('contract_id')->nullable();
            $table->string('employee_type', 10)->default('Internal');             // snapshot: Internal | External
            $table->string('offboarding_type', 30);                               // resignation | end_of_pkwt | retirement | termination | mutual_agreement | death | end_of_engagement
            $table->string('reason_code', 30)->nullable();                        // kunci alasan di register offboarding.reason_multipliers
            $table->date('effective_date');
            $table->date('last_working_day')->nullable();
            $table->string('status', 12)->default('in_progress');                 // in_progress | completed | cancelled
            $table->text('notes')->nullable();
            $table->timestamp('access_deactivated_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'effective_date'], 'idx_offb_status_date');
            $table->index('employee_id', 'idx_offb_employee');
            $table->foreign('employee_id', 'fk_offb_employee')->references('employee_id')->on('employee')->cascadeOnDelete();
        });

        Schema::create('offboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_id');
            $table->string('task_key', 40);
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->string('status', 8)->default('pending');                      // pending | done
            $table->string('note', 500)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['offboarding_id', 'task_key'], 'uq_offb_task');
            $table->foreign('offboarding_id', 'fk_offb_task_offb')->references('id')->on('offboardings')->cascadeOnDelete();
        });

        Schema::create('offboarding_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('offboarding_id')->unique();
            $table->string('contract_type', 20)->nullable();
            $table->decimal('wage_base_override', 17, 2)->nullable();             // SENSITIF — kosong = dari kontrak
            $table->decimal('leave_days', 6, 2)->default(0);
            $table->decimal('retirement_benefit', 17, 2)->default(0);
            $table->decimal('separation_pay', 17, 2)->default(0);
            $table->decimal('other_compensation', 17, 2)->default(0);
            $table->text('legal_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('result')->nullable();                                   // hasil hitung (SENSITIF)
            $table->json('legal_basis_ids')->nullable();                          // versi register yang dipakai saat dihitung
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('offboarding_id', 'fk_offb_settle_offb')->references('id')->on('offboardings')->cascadeOnDelete();
        });

        DB::table('offboarding_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        // Enam tugas bawaan (sesuai ESH), dapat diubah di Settings.
        $now = now();
        foreach ([
            ['validate_basics', 'Validate offboarding basics', 'Confirm the resignation, termination or retirement letter and its legal grounds are documented.', -14, 'both'],
            ['final_entitlements', 'Calculate final entitlements', 'Compute severance, service award, PKWT compensation, unused leave and other final payments where they apply.', -7, 'internal'],
            ['return_assets', 'Return of assets', 'Make sure laptop, access card, documents and other company inventory are returned.', -3, 'both'],
            ['deactivate_access', 'Deactivate system access', 'Remove email, VPN, ESS, attendance and application access on the effective date.', 0, 'both'],
            ['exit_interview', 'Exit interview', 'Record the reasons for leaving, lessons learned and any industrial-relations issue.', -2, 'both'],
            ['final_admin', 'Reference letter & final administration', 'Issue the work reference letter and complete the final administration, including BPJS where needed.', 2, 'both'],
        ] as $i => [$key, $name, $desc, $offset, $applies]) {
            DB::table('offboarding_task_templates')->insert([
                'task_key' => $key, 'name' => $name, 'description' => $desc, 'due_offset_days' => $offset, 'applies_to' => $applies,
                'is_active' => 1, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Menu Access (hanya EC Administrator di awal; Admin membagikan lewat Menu Access).
        if (MenuRegistrar::register('general', ['general.offboarding' => 'Offboarding'], 81, 'group')) {
            MenuRegistrar::register('general.offboarding', ['general.offboarding.list' => 'Offboardings', 'general.offboarding.settings' => 'Offboarding Settings'], 1, 'page');
            MenuRegistrar::register('general.offboarding', ['general.offboarding.settlement' => 'View Settlement Amounts'], 10, 'function');
            MenuRegistrar::grantToAdminAndRoles(['general.offboarding.list', 'general.offboarding.settings'], [], ['create', 'edit', 'delete']);
        }
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($id) {
            Cache::forget("perm_slugs_{$id}");
            Cache::forget("perm_matrix_{$id}");
        });
    }

    public function down(): void
    {
        MenuRegistrar::remove(['general.offboarding.settlement', 'general.offboarding.settings', 'general.offboarding.list', 'general.offboarding']);
        foreach (['offboarding_settlements', 'offboarding_tasks', 'offboardings', 'offboarding_task_templates', 'offboarding_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
