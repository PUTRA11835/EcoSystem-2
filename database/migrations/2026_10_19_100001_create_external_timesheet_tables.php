<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * External → Timesheet & Payment: lembar jam konsultan per bulan kontrak, invoice, dan pengaturan penyetuju.
 *
 * MODUL TERPISAH dari tabel `timesheets` (Timesheet internal yang live berbasis tiket/aktivitas proyek, periode 21–20, kuota support):
 * tidak ada tabel lama yang disentuh. Alur: draft → submitted → (returned | approved) → invoice uploaded → verified | rejected → paid.
 *
 * DATA SENSITIF: snapshot tarif (`rate`, `gross_amount`) dan jumlah invoice; dijaga slug `general.external.timesheet.amount`
 * dan dibatasi ke pemilik lembar. Hanya menambah tabel; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_timesheets', function (Blueprint $table) {
            $table->id();
            $table->string('sheet_no', 40)->unique();                 // EXT-TS/2026/10/0001
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('contract_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 12)->default('draft');           // draft | submitted | returned | approved | void

            // Dibekukan dari kontrak saat Submit: penagihan tidak berubah bila kontrak diubah kemudian.
            $table->string('engagement_scheme', 20)->nullable();
            $table->decimal('rate', 15, 2)->nullable();               // SENSITIF
            $table->string('currency', 3)->default('IDR');
            $table->string('billing_unit', 30)->nullable();
            $table->decimal('hours_per_manday', 4, 1)->default(8);
            $table->string('proration', 20)->default('working_days');
            $table->decimal('milestone_pct', 5, 2)->default(100);     // fixed price: persen tagihan lembar ini
            $table->decimal('total_hours', 8, 2)->default(0);
            $table->decimal('billable_qty', 10, 2)->default(0);
            $table->decimal('gross_amount', 17, 2)->nullable();       // SENSITIF

            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
            $table->text('return_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();     // pembuat (bisa HR/RPMO atas nama konsultan)
            $table->timestamps();

            $table->unique(['employee_id', 'contract_id', 'period_year', 'period_month'], 'uq_ext_ts_period');
            $table->index(['status', 'period_year', 'period_month'], 'idx_ext_ts_status');
            $table->foreign('employee_id', 'fk_ext_ts_employee')->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('contract_id', 'fk_ext_ts_contract')->references('contract_id')->on('employee_contract')->cascadeOnDelete();
        });

        Schema::create('external_timesheet_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->date('work_date');
            $table->unsignedBigInteger('project_id')->nullable();     // delivery_projects (hanya dibaca), boleh kosong
            $table->string('project_label', 255)->nullable();         // nama proyek saat dipilih / teks bebas
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('hours', 5, 2);
            $table->string('location', 10)->nullable();               // ONSITE | REMOTE | HYBRID
            $table->string('activity', 500)->nullable();
            $table->timestamps();

            $table->index(['timesheet_id', 'work_date'], 'idx_ext_ts_entry_date');
            $table->foreign('timesheet_id', 'fk_ext_ts_entry_sheet')->references('id')->on('external_timesheets')->cascadeOnDelete();
        });

        // Penyetuju tiap tahap, dibekukan per lembar saat Submit (mengubah Settings tidak merusak lembar berjalan).
        Schema::create('external_timesheet_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id');
            $table->string('stage', 24);                              // timesheet_approval | invoice_verification
            $table->json('approver_employee_ids');                    // hasil resolusi saat Submit
            $table->string('resolved_from', 20)->default('role');     // role | employee | override
            $table->timestamps();

            $table->unique(['timesheet_id', 'stage'], 'uq_ext_ts_approval_stage');
            $table->foreign('timesheet_id', 'fk_ext_ts_approval_sheet')->references('id')->on('external_timesheets')->cascadeOnDelete();
        });

        Schema::create('external_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('timesheet_id')->unique();
            $table->string('invoice_no', 100);
            $table->date('invoice_date');
            $table->decimal('amount', 17, 2);                         // SENSITIF
            $table->string('file_path', 255)->nullable();             // disk privat external-invoices/{timesheet}/…
            $table->string('file_name', 200)->nullable();
            $table->string('status', 10)->default('uploaded');        // uploaded | verified | rejected | paid
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('reject_reason')->nullable();
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->string('payment_ref', 150)->nullable();
            $table->timestamps();

            $table->index('status', 'idx_ext_inv_status');
            $table->foreign('timesheet_id', 'fk_ext_inv_sheet')->references('id')->on('external_timesheets')->cascadeOnDelete();
        });

        Schema::create('external_workflow_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('allow_self_approval')->default(false);
            $table->unsignedSmallInteger('backfill_days')->default(60);   // batas mundur pengisian (hari); 0 = tanpa batas
            $table->timestamps();
        });

        Schema::create('external_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 24);
            $table->unsignedSmallInteger('order_seq')->default(1);
            $table->string('name', 120);
            $table->string('approver_type', 12)->default('role');     // role | employee
            $table->json('approver_role_ids')->nullable();            // salah satu pemegang role mana pun boleh bertindak
            $table->json('approver_employee_ids')->nullable();
            $table->timestamps();

            $table->index(['stage', 'order_seq'], 'idx_ext_wf_step');
        });

        Schema::create('external_approver_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');                // konsultan
            $table->string('stage', 24);
            $table->json('approver_employee_ids');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'stage'], 'uq_ext_wf_override');
            $table->foreign('employee_id', 'fk_ext_wf_override_employee')->references('employee_id')->on('employee')->cascadeOnDelete();
        });

        Schema::create('external_timesheet_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
        });

        // Bawaan: satu baris pengaturan + satu langkah per tahap (RPMO Head approve; Finance verifikasi) — role dicari menurut nama.
        DB::table('external_workflow_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
        $role = fn (array $names) => DB::table('employee_role')->whereIn('name', $names)->pluck('id')->map(fn ($id) => (int) $id)->all();
        DB::table('external_workflow_steps')->insert([
            ['stage' => 'timesheet_approval', 'order_seq' => 1, 'name' => 'Head of RPMO', 'approver_type' => 'role',
                'approver_role_ids' => json_encode($role(['Delivery RPMO Head'])), 'approver_employee_ids' => null, 'created_at' => now(), 'updated_at' => now()],
            ['stage' => 'invoice_verification', 'order_seq' => 1, 'name' => 'Finance', 'approver_type' => 'role',
                'approver_role_ids' => json_encode($role(['HO Finance Administrator', 'HO Finance Head', 'HO Finance User'])), 'approver_employee_ids' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        foreach (['external_timesheet_sequences', 'external_approver_overrides', 'external_workflow_steps', 'external_workflow_settings',
            'external_invoices', 'external_timesheet_approvals', 'external_timesheet_entries', 'external_timesheets'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
