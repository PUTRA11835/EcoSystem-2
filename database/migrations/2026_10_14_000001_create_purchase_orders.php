<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Order — tab ketiga pada menu Purchase Management (Purchase Request |
 * Purchase Orders | Settings).
 *
 * PO lahir dari satu alur pengadaan yang sama dengan PR, jadi hidup di menu yang
 * sama sebagai tab, bukan sub-menu sendiri. Pengaturannya (wajib PR, tarif
 * PPN/PPh, penanda tangan bawaan) menjadi satu bagian pada tab Settings.
 *
 * Nilai pajak per baris DISIMPAN (bukan dihitung ulang saat dibaca) supaya
 * dokumen lama tidak berubah ketika tarif di Settings diganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 150)->default('PT Eclectic Solusi Handal');
            $table->boolean('requires_pr')->default(false);
            $table->decimal('vat_rate', 5, 2)->default(11);
            $table->decimal('pph23_rate', 5, 2)->default(2);
            $table->decimal('pph4_2_rate', 5, 2)->default(10);
            $table->decimal('pp23_rate', 5, 2)->default(0.5);
            $table->decimal('pph21_rate', 5, 2)->default(2.5);
            $table->decimal('pph26_rate', 5, 2)->default(20);
            $table->unsignedBigInteger('accounting_employee_id')->nullable();
            $table->unsignedBigInteger('cashier_employee_id')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('purchase_order_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no', 30)->unique();
            $table->unsignedBigInteger('purchase_request_id')->nullable()->index();
            $table->date('po_date');
            $table->string('supplier_name', 150);
            $table->string('transaction_no', 100)->nullable();
            $table->string('invoice_no', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('accounting_id')->nullable();
            $table->unsignedBigInteger('cashier_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->date('delivered_date')->nullable();
            $table->string('notes', 255)->nullable();
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount_total', 16, 2)->default(0);
            $table->decimal('vat_total', 16, 2)->default(0);
            $table->decimal('withholding_total', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2)->default(0);
            $table->string('status', 20)->default('submitted')->index(); // submitted|approved|rejected
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_notes', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('delete_reason', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('description', 255);
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('unit', 20)->default('PC');
            $table->string('currency', 3)->default('IDR');
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->string('project_label', 150)->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('discount_type', 10)->default('percent'); // percent|amount
            $table->decimal('discount_value', 16, 2)->default(0);
            $table->json('tax_keys')->nullable();                    // ["vat","pph23",...]
            $table->decimal('line_subtotal', 16, 2)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('vat_amount', 16, 2)->default(0);
            $table->decimal('withholding_amount', 16, 2)->default(0);
            $table->decimal('line_total', 16, 2)->default(0);
            $table->timestamps();
        });

        MenuRegistrar::register('general', [
            'general.purchase-order' => 'Purchase Orders',
        ], 130, 'page');

        MenuRegistrar::register('general', [
            'general.purchase-order.create' => 'Purchase Orders — Create',
            'general.purchase-order.manage' => 'Purchase Orders — Edit / Delete',
        ], 131, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove([
            'general.purchase-order', 'general.purchase-order.create',
            'general.purchase-order.manage',
        ]);

        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_order_settings');
    }
};
