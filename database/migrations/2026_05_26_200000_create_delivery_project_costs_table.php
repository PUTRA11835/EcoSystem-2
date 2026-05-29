<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_costs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_projects_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 20)->nullable();         // e.g. "100", "200", "210"
            $table->string('name', 200);                    // e.g. INDIRECT COST, PROJECT ALLOWANCE
            $table->enum('cost_type', ['indirect', 'direct'])->default('direct');
            $table->decimal('budget', 20, 2)->nullable();
            $table->decimal('release_amount', 20, 2)->nullable();
            $table->decimal('actual_amount', 20, 2)->nullable();
            $table->integer('order_sequence')->default(0);
            $table->timestamps();

            $table->foreign('delivery_projects_id')
                  ->references('id')
                  ->on('delivery_projects')
                  ->onDelete('cascade');

            $table->foreign('parent_id')
                  ->references('id')
                  ->on('delivery_project_costs')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_costs');
    }
};
