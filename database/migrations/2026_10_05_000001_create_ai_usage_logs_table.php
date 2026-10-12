<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Control Center → AI Usage.
 *
 * Satu baris per panggilan API ke Claude/OpenAI, ditulis oleh
 * App\Services\Ai\AiUsageRecorder dari angka usage yang dikembalikan provider.
 * Sebelum tabel ini tidak ada catatan pemakaian token sama sekali, jadi
 * halaman AI Usage hanya bisa menghitung dari titik migrasi ini ke depan.
 */
return new class extends Migration
{
    private const SLUG = 'control-center.ai-usage';

    public function up(): void
    {
        if (!Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 20);
                $table->string('model', 80);
                // Jenis driver pemanggil: chat | research | ticket_analysis | report.
                $table->string('source', 30);
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('cache_read_tokens')->default(0);
                $table->unsignedInteger('cache_write_tokens')->default(0);
                // Estimasi dari katalog harga AiModelSettings saat dicatat, bukan tagihan resmi.
                $table->decimal('cost_usd', 12, 6)->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index('created_at');
                $table->index(['provider', 'created_at']);
                $table->index(['model', 'created_at']);
            });
        }

        $parentId = DB::table('menu')->where('slug', 'control-center')->value('id');

        if (!$parentId) {
            return;
        }

        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => $parentId,
                'name'       => 'AI Usage',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'admin.ai-usage',
                'icon'       => 'fa-chart-line',
                'order_seq'  => 11,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MenuRegistrar::grantToAdminOnly([self::SLUG]);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
        Schema::dropIfExists('ai_usage_logs');
    }
};
