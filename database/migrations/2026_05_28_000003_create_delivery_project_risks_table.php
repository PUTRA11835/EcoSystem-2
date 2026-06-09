<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')
                  ->constrained('delivery_projects')
                  ->onDelete('cascade');
            $table->unsignedSmallInteger('risk_number');   // 1, 2, 3 → displayed as RSK-001
            $table->string('category', 50);                // Scope, Schedule, etc.
            $table->text('description');                   // Deskripsi Risiko
            $table->text('cause')->nullable();             // Penyebab (Trigger)
            $table->text('project_impact')->nullable();    // Dampak Proyek (text)
            $table->tinyInteger('probability');            // 1–5
            $table->tinyInteger('impact');                 // 1–5
            $table->string('response_strategy', 20)->nullable(); // Avoid, Mitigate, etc.
            $table->text('mitigation_plan')->nullable();   // Rencana Mitigasi / Kontiniensi
            $table->string('risk_owner', 100)->nullable();
            $table->string('status', 20)->default('Open');
            $table->date('target_date')->nullable();       // Target Penyelesaian
            $table->text('notes')->nullable();             // Komentar / Catatan
            $table->timestamps();

            $table->index('delivery_projects_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_risks');
    }
};
