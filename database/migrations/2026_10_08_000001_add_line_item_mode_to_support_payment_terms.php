<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhancement Term Of Payment Delivery Project (2026_10_01_000001) diterapkan
 * juga ke Delivery Support. Dua mode penagihan:
 *   - percentage : termin = % × revenue Sales Data (perilaku lama, default).
 *   - line_item  : termin wajib dikaitkan ke Contract Line Item; amount diambil
 *                  dari nilai line item (% × nilai line item, atau nominal tetap).
 *                  Melebihi revenue hanya diperingatkan.
 * Status "Invoiced" tidak butuh kolom baru (kolom status berupa string).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->string('top_mode', 20)->default('percentage')->after('gross_profit_percentage');
        });

        Schema::create('delivery_support_contract_line_items', function (Blueprint $table) {
            $table->id();
            // Nama FK eksplisit: nama default (65 karakter) melewati batas MySQL 64.
            $table->foreignId('delivery_support_id')
                  ->constrained('delivery_support', 'id', 'ds_contract_line_items_support_fk')
                  ->onDelete('cascade');
            $table->string('name');
            $table->string('type', 20)->default('one_time');      // one_time | recurring | milestone
            $table->string('frequency', 20)->nullable();          // monthly | quarterly | semiannual | yearly (recurring saja)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 20, 2)->default(0);         // nominal per periode (one-time / milestone: nominal total)
            $table->unsignedSmallInteger('order_sequence')->default(0);
            $table->timestamps();

            $table->index('delivery_support_id');
        });

        Schema::table('delivery_support_payment_terms', function (Blueprint $table) {
            // percentage → amount = revenue × payment_percentage / 100 (diturunkan)
            // line_item  → amount = nilai line item × payment_percentage / 100
            // fixed      → amount diisi user; payment_percentage disimpan 0
            $table->string('basis', 20)->default('percentage')->after('term_number');
            $table->foreignId('contract_line_item_id')->nullable()->after('basis')
                  ->constrained('delivery_support_contract_line_items', 'id', 'ds_payment_terms_line_item_fk')
                  ->nullOnDelete();
            $table->string('period', 50)->nullable()->after('payment_term');   // label periode, mis. "Feb 2027"
        });
    }

    public function down(): void
    {
        Schema::table('delivery_support_payment_terms', function (Blueprint $table) {
            $table->dropForeign('ds_payment_terms_line_item_fk');
            $table->dropColumn(['contract_line_item_id', 'basis', 'period']);
        });

        Schema::dropIfExists('delivery_support_contract_line_items');

        Schema::table('delivery_support', function (Blueprint $table) {
            $table->dropColumn('top_mode');
        });
    }
};
