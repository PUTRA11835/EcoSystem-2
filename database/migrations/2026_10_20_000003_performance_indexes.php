<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks tambahan untuk jalur yang paling sering dipanggil (audit performa 20 Okt 2026). Murni aditif — tidak mengubah data atau
 * perilaku; dibuat hanya bila belum ada, sehingga aman dijalankan ulang.
 *
 *  - notifications (employee_id, is_read, created_at): hitungan lonceng (dipanggil tiap 15 dtk per tab) + daftar notifikasi terbaru.
 *  - auth_users (remember_token): pemulihan sesi di CheckAuthToken mencari berdasarkan kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications') && !Schema::hasIndex('notifications', 'idx_notif_emp_read_created')) {
            Schema::table('notifications', function (Blueprint $t) {
                $t->index(['employee_id', 'is_read', 'created_at'], 'idx_notif_emp_read_created');
            });
        }
        if (Schema::hasTable('auth_users') && Schema::hasColumn('auth_users', 'remember_token') && !Schema::hasIndex('auth_users', 'idx_auth_users_remember_token')) {
            Schema::table('auth_users', function (Blueprint $t) {
                $t->index('remember_token', 'idx_auth_users_remember_token');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasIndex('notifications', 'idx_notif_emp_read_created')) {
            Schema::table('notifications', fn (Blueprint $t) => $t->dropIndex('idx_notif_emp_read_created'));
        }
        if (Schema::hasTable('auth_users') && Schema::hasIndex('auth_users', 'idx_auth_users_remember_token')) {
            Schema::table('auth_users', fn (Blueprint $t) => $t->dropIndex('idx_auth_users_remember_token'));
        }
    }
};
