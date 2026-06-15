<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Group struktural (membership) — meng-upgrade field teks lama
 * `customer_basic_data.customer_group` menjadi grouping nyata berbasis tabel.
 *
 * - Tabel baru `customer_groups` (nama unik + kode opsional).
 * - Kolom FK `customer.customer_group_id` (nullable, set null saat grup dihapus).
 * - Backfill: tiap nilai distinct `customer_group` text yang sudah ada dijadikan
 *   satu grup, lalu customer-nya di-assign ke grup tsb. Kolom teks lama TETAP
 *   dipertahankan (di-mirror nama grup) agar filter/header/Jarvies yang
 *   membacanya tidak rusak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customer', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_group_id')->nullable()->after('parent_customer_id');
            $table->foreign('customer_group_id')
                  ->references('id')
                  ->on('customer_groups')
                  ->nullOnDelete();
        });

        // ── Backfill dari nilai teks customer_group yang sudah ada ──
        $existingGroups = DB::table('customer_basic_data')
            ->whereNotNull('customer_group')
            ->where('customer_group', '!=', '')
            ->select('customer_group')
            ->distinct()
            ->pluck('customer_group');

        foreach ($existingGroups as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $groupId = DB::table('customer_groups')->insertGetId([
                'name'       => $name,
                'code'       => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10)) ?: null,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign semua customer yang basic data-nya punya group text ini
            $customerIds = DB::table('customer_basic_data')
                ->where('customer_group', $name)
                ->pluck('customer_id');

            DB::table('customer')
                ->whereIn('customer_id', $customerIds)
                ->update(['customer_group_id' => $groupId]);
        }
    }

    public function down(): void
    {
        Schema::table('customer', function (Blueprint $table) {
            $table->dropForeign(['customer_group_id']);
            $table->dropColumn('customer_group_id');
        });

        Schema::dropIfExists('customer_groups');
    }
};
