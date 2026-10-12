<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WEEKLY CONSOLIDATION — recon tiket weekly per modul (Reporting → Support).
 *
 * Live-join by design: header + line di sini HANYA menyimpan tiket mana saja
 * yang masuk satu batch recon, plus notes-nya. Status/progress/PIC/team lead/
 * member TIDAK disalin ke sini — selalu dibaca langsung dari tabel `ticket`
 * saat batch dibuka/di-export (lihat App\Models\WeeklyConsolidation). Baris
 * lama karenanya ikut menampilkan kondisi tiket TERKINI, bukan snapshot histori
 * — trade-off yang disengaja demi kesederhanaan.
 *
 * `period_start`/`period_end`/`period_label` adalah LABEL periode yang diisi
 * module lead (mis. minggu berjalan), BUKAN filter tanggal terhadap tiket —
 * kriteria tiket selalu "yang masih terbuka SEKARANG", terlepas kapan tiket
 * itu dibuat. Jangan dipakai sebagai filter created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('weekly_consolidations')) {
            Schema::create('weekly_consolidations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('module_id');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('period_label')->nullable();

                // Nullable meski secara bisnis selalu diisi controller — MySQL
                // mewajibkan kolom boleh NULL kalau foreign key-nya ON DELETE SET NULL
                // (error 1830 kalau tetap NOT NULL, sempat kejadian saat migrate pertama).
                $table->unsignedBigInteger('generated_by_id')->nullable();
                $table->timestamp('last_refreshed_at')->nullable();
                $table->unsignedBigInteger('last_refreshed_by_id')->nullable();

                $table->timestamps();

                $table->index(['module_id', 'period_start'], 'idx_weekly_consol_module_period');

                $table->foreign('module_id', 'fk_weekly_consol_module')
                    ->references('id')->on('modules')->onDelete('cascade');
                $table->foreign('generated_by_id', 'fk_weekly_consol_generated_by')
                    ->references('employee_id')->on('employee')->onDelete('set null');
                $table->foreign('last_refreshed_by_id', 'fk_weekly_consol_refreshed_by')
                    ->references('employee_id')->on('employee')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('weekly_consolidation_tickets')) {
            Schema::create('weekly_consolidation_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('weekly_consolidation_id');
                $table->unsignedBigInteger('ticket_id');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('notes_updated_by_id')->nullable();
                $table->timestamp('notes_updated_at')->nullable();
                $table->timestamps();

                // Membuat Refresh aman diklik berkali-kali (insert-if-missing).
                $table->unique(['weekly_consolidation_id', 'ticket_id'], 'uniq_weekly_consol_ticket');

                $table->foreign('weekly_consolidation_id', 'fk_weekly_consol_line_header')
                    ->references('id')->on('weekly_consolidations')->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_weekly_consol_line_ticket')
                    ->references('ticket_id')->on('ticket')->onDelete('cascade');
                $table->foreign('notes_updated_by_id', 'fk_weekly_consol_notes_by')
                    ->references('employee_id')->on('employee')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_consolidation_tickets');
        Schema::dropIfExists('weekly_consolidations');
    }
};
