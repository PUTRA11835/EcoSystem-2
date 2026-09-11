<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan metadata share link OneDrive (scope + expiry + kapan terakhir diverifikasi)
 * supaya EcoSystem tahu apakah link folder benar-benar bisa diakses publik.
 * Sebelumnya hanya URL yang disimpan, jadi link yang di-downgrade ke "organization"
 * atau sudah kedaluwarsa tetap terlihat normal di UI.
 */
return new class extends Migration
{
    private array $targets = [
        'delivery_projects' => 'onedrive_folder_url',
        'delivery_support'  => 'onedrive_folder_url',
        'ticket'            => 'onedrive_folder_url',
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $after) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $after)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table, $after) {
                if (!Schema::hasColumn($table, 'onedrive_link_scope')) {
                    $t->string('onedrive_link_scope', 32)->nullable()->after($after);
                }
                if (!Schema::hasColumn($table, 'onedrive_link_expires_at')) {
                    $t->timestamp('onedrive_link_expires_at')->nullable()->after('onedrive_link_scope');
                }
                if (!Schema::hasColumn($table, 'onedrive_link_checked_at')) {
                    $t->timestamp('onedrive_link_checked_at')->nullable()->after('onedrive_link_expires_at');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->targets) as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                ['onedrive_link_scope', 'onedrive_link_expires_at', 'onedrive_link_checked_at'],
                fn($c) => Schema::hasColumn($table, $c)
            ));

            if ($columns) {
                Schema::table($table, fn(Blueprint $t) => $t->dropColumn($columns));
            }
        }
    }
};
