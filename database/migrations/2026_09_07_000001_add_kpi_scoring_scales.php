<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom scoring scale per KPI template.
 *
 *   kpi_scoring_scales   — the "SKALA PENILAIAN" rows an admin edits per template
 *                          (value, category, definition, achievement band, note)
 *   kpi_templates.score_divisor
 *                        — the divisor in "Weighted Score = Score ÷ divisor × Bobot"
 *                          (defaults to the highest scale value, e.g. 5)
 *   kpi_indicators.answer_type
 *                        — 'rating' (scored on the scale) or 'paragraph' (free text,
 *                          not scored, weight 0)
 *   kpi_indicators.rating_max
 *                        — optional per-indicator cap (e.g. the 1-3 integrity items
 *                          inside an otherwise 1-5 template); null = template scale max
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kpi_scoring_scales')) {
            Schema::create('kpi_scoring_scales', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id');
                $table->unsignedTinyInteger('scale_value')->comment('e.g. 5,4,3,2,1');
                $table->string('category', 100)->nullable()->comment('e.g. Outstanding');
                $table->string('definition', 255)->nullable()->comment('e.g. Jauh melampaui ekspektasi');
                $table->string('achievement_label', 100)->nullable()->comment('free text band, e.g. ">= 120%"');
                $table->decimal('achievement_min', 6, 2)->nullable()->comment('optional numeric lower bound (%)');
                $table->decimal('achievement_max', 6, 2)->nullable()->comment('optional numeric upper bound (%)');
                $table->string('description', 255)->nullable()->comment('e.g. Kinerja sangat istimewa');
                $table->unsignedTinyInteger('order_seq')->default(1);
                $table->timestamps();

                $table->foreign('template_id')->references('id')->on('kpi_templates')->onDelete('cascade');
                $table->index('template_id');
            });
        }

        if (!Schema::hasColumn('kpi_templates', 'score_divisor')) {
            Schema::table('kpi_templates', function (Blueprint $table) {
                $table->unsignedTinyInteger('score_divisor')->default(5)->after('target_positions');
            });
        }

        if (!Schema::hasColumn('kpi_indicators', 'answer_type')) {
            Schema::table('kpi_indicators', function (Blueprint $table) {
                $table->enum('answer_type', ['rating', 'paragraph'])->default('rating')->after('name');
            });
        }
        if (!Schema::hasColumn('kpi_indicators', 'rating_max')) {
            Schema::table('kpi_indicators', function (Blueprint $table) {
                $table->unsignedTinyInteger('rating_max')->nullable()->after('answer_type')
                    ->comment('per-indicator scale cap; null = template score_divisor');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_scoring_scales');

        if (Schema::hasColumn('kpi_templates', 'score_divisor')) {
            Schema::table('kpi_templates', fn (Blueprint $t) => $t->dropColumn('score_divisor'));
        }
        foreach (['answer_type', 'rating_max'] as $col) {
            if (Schema::hasColumn('kpi_indicators', $col)) {
                Schema::table('kpi_indicators', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
