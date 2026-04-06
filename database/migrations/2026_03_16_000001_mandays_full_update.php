<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ====================================================================
        // 1. TICKET — tambah mandays_proposal_status & internal_mandays_status
        // ====================================================================
        Schema::table('ticket', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket', 'mandays_proposal_status')) {
                $table->enum('mandays_proposal_status', [
                    'none', 'pic_draft', 'pending_helpdesk', 'sent_to_chat', 'approved', 'canceled',
                ])->default('none')->after('man_days');
            }
            if (!Schema::hasColumn('ticket', 'internal_mandays_status')) {
                $table->enum('internal_mandays_status', [
                    'none', 'draft', 'pending_head', 'approved', 'rejected',
                ])->default('none')->after('mandays_proposal_status');
            }
        });

        // ====================================================================
        // 2. CUSTOMER_MANDAYS — update enum status + tambah kolom baru
        // ====================================================================
        // Ubah status ENUM ke nilai baru (MySQL: ALTER COLUMN)
        DB::statement("ALTER TABLE customer_mandays MODIFY COLUMN status ENUM(
            'draft', 'pending_helpdesk', 'sent_to_chat', 'approved', 'canceled'
        ) NOT NULL DEFAULT 'draft'");

        // Drop unique constraint (ticket_id, version) agar PIC bisa buat versi baru setelah approved
        // Gunakan try/catch karena nama constraint bisa bervariasi
        try {
            Schema::table('customer_mandays', function (Blueprint $table) {
                $table->dropUnique('idx_cm_ticket_version');
            });
        } catch (\Exception $e) {
            // constraint mungkin sudah dihapus atau namanya berbeda — abaikan
        }

        Schema::table('customer_mandays', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_mandays', 'sent_to_chat_at')) {
                $table->timestamp('sent_to_chat_at')->nullable()->after('submitted_to_customer_at');
            }
            if (!Schema::hasColumn('customer_mandays', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('customer_notes');
            }
            if (!Schema::hasColumn('customer_mandays', 'notes')) {
                $table->text('notes')->nullable()->after('rejection_reason');
            }
        });

        // ====================================================================
        // 3. CUSTOMER_MANDAYS_DETAIL — tambah kolom activity
        // ====================================================================
        Schema::table('customer_mandays_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_mandays_detail', 'activity')) {
                $table->string('activity', 150)->nullable()->after('customer_mandays_id');
            }
        });

        // ====================================================================
        // 4. CONSULTANT_MANDAYS — update enum + tambah helpdesk_notes + drop unique
        // ====================================================================
        // Ubah status ENUM ke nilai baru (tambah needs_revision)
        DB::statement("ALTER TABLE consultant_mandays MODIFY COLUMN status ENUM(
            'draft', 'pending_approval', 'approved', 'rejected', 'needs_revision'
        ) NOT NULL DEFAULT 'draft'");

        // Drop unique constraint pada ticket_id
        try {
            Schema::table('consultant_mandays', function (Blueprint $table) {
                $table->dropUnique('idx_cim_ticket');
            });
        } catch (\Exception $e) {
            // abaikan jika tidak ada
        }

        Schema::table('consultant_mandays', function (Blueprint $table) {
            if (!Schema::hasColumn('consultant_mandays', 'helpdesk_notes')) {
                $table->text('helpdesk_notes')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        // Kembalikan ticket columns
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn(['mandays_proposal_status', 'internal_mandays_status']);
        });

        // Kembalikan customer_mandays
        DB::statement("ALTER TABLE customer_mandays MODIFY COLUMN status ENUM(
            'draft', 'pending_customer', 'approved', 'rejected'
        ) NOT NULL DEFAULT 'draft'");

        Schema::table('customer_mandays', function (Blueprint $table) {
            $table->dropColumn(['sent_to_chat_at', 'rejection_reason', 'notes']);
        });

        Schema::table('customer_mandays_detail', function (Blueprint $table) {
            $table->dropColumn('activity');
        });

        // Kembalikan consultant_mandays
        DB::statement("ALTER TABLE consultant_mandays MODIFY COLUMN status ENUM(
            'draft', 'pending_approval', 'approved', 'rejected'
        ) NOT NULL DEFAULT 'draft'");

        Schema::table('consultant_mandays', function (Blueprint $table) {
            $table->dropColumn('helpdesk_notes');
        });
    }
};
