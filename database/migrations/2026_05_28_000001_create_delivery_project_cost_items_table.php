<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_cost_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_project_cost_id')
                  ->constrained('delivery_project_costs')
                  ->onDelete('cascade');
            $table->string('description', 200);
            $table->decimal('amount', 20, 2);
            $table->string('document_name', 255)->nullable();
            $table->string('document_path', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_cost_items');
    }
};
