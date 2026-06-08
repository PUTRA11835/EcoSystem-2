<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')
                  ->constrained('delivery_projects')
                  ->onDelete('cascade');
            $table->unsignedSmallInteger('term_number');          // 1, 2, 3 … (kolom "No")
            $table->string('payment_term');                       // Payment Term (teks)
            $table->decimal('payment_percentage', 8, 2)->default(0); // % dari revenue
            $table->decimal('amount', 20, 2)->default(0);         // auto = revenue × %
            $table->text('requirements')->nullable();             // Payment Requirements / Evidence
            $table->date('estimated_date')->nullable();           // Estimated Date
            $table->date('actual_date')->nullable();              // Actual Date
            $table->string('status', 20)->default('Open');        // Open | Paid | Delay
            $table->timestamps();

            $table->index('delivery_projects_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_payment_terms');
    }
};
