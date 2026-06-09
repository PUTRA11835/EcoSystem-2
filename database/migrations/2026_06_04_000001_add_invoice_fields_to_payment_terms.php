<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_project_payment_terms', function (Blueprint $table) {
            // "Actual Date" kini bermakna tanggal invoice di-submit
            $table->renameColumn('actual_date', 'submit_invoice_date');
        });

        Schema::table('delivery_project_payment_terms', function (Blueprint $table) {
            // Nomor invoice — wajib diisi saat submit_invoice_date terisi (divalidasi di controller)
            $table->string('invoice_number')->nullable()->after('submit_invoice_date');
            // Tanggal pembayaran diterima (Paid Date)
            $table->date('paid_date')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_payment_terms', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'paid_date']);
        });

        Schema::table('delivery_project_payment_terms', function (Blueprint $table) {
            $table->renameColumn('submit_invoice_date', 'actual_date');
        });
    }
};
