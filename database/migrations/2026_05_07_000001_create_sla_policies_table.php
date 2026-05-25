<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();

            // NULL = policy default/global (fallback jika customer tidak punya policy spesifik)
            $table->unsignedBigInteger('customer_id')->nullable();

            $table->enum('priority', ['Low', 'Medium', 'High', 'Very High']);
            $table->enum('scale', ['Simple', 'Medium', 'Complex']);

            // Target waktu dalam jam (desimal, contoh: 1.5 = 1 jam 30 menit)
            $table->decimal('response_hours', 6, 2);
            $table->decimal('resolution_hours', 6, 2);

            // true = hitung 24 jam penuh (Very High biasanya), false = business hours saja
            $table->boolean('is_24_hours')->default(false);

            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Satu customer tidak boleh punya 2 policy dengan kombinasi priority+scale yang sama
            $table->unique(['customer_id', 'priority', 'scale'], 'sla_policies_unique');

            // Index untuk lookup cepat saat tiket dibuat
            $table->index(['customer_id', 'priority', 'scale', 'is_active'], 'sla_policies_lookup_idx');

            $table->foreign('customer_id')
                ->references('customer_id')
                ->on('customer')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('employee_id')
                ->on('employee')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
