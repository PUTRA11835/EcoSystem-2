<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Term Of Payment punya dua mode penagihan:
 *   - percentage : termin = % × revenue Sales Data (perilaku lama, default).
 *   - line_item  : termin bernominal tetap yang wajib dikaitkan ke Contract
 *                  Line Item (mis. kontrak berisi service + license, dan license
 *                  tidak ikut TOP). Nominal diisi sendiri, tidak diturunkan dari
 *                  revenue — hanya diperingatkan bila totalnya melebihi revenue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->string('top_mode', 20)->default('percentage')->after('gross_profit_percentage');
        });

        Schema::create('delivery_project_contract_line_items', function (Blueprint $table) {
            $table->id();
            // Nama FK eksplisit: nama default (65 karakter) melewati batas MySQL 64.
            $table->foreignId('delivery_projects_id')
                  ->constrained('delivery_projects', 'id', 'dp_contract_line_items_project_fk')
                  ->onDelete('cascade');
            $table->string('name');
            $table->string('type', 20)->default('one_time');      // one_time | recurring
            $table->string('frequency', 20)->nullable();          // monthly | quarterly | semiannual | yearly (recurring saja)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 20, 2)->default(0);         // nominal per periode (one-time: nominal total)
            $table->unsignedSmallInteger('order_sequence')->default(0);
            $table->timestamps();

            $table->index('delivery_projects_id');
        });

        Schema::table('delivery_project_payment_terms', function (Blueprint $table) {
            // percentage → amount = revenue × payment_percentage / 100 (diturunkan)
            // fixed      → amount diisi user; payment_percentage disimpan 0
            $table->string('basis', 20)->default('percentage')->after('term_number');
            $table->foreignId('contract_line_item_id')->nullable()->after('basis')
                  ->constrained('delivery_project_contract_line_items')
                  ->nullOnDelete();
            $table->string('period', 50)->nullable()->after('payment_term');   // label periode, mis. "Feb 2027"
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_payment_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_line_item_id');
            $table->dropColumn(['basis', 'period']);
        });

        Schema::dropIfExists('delivery_project_contract_line_items');

        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropColumn('top_mode');
        });
    }
};
