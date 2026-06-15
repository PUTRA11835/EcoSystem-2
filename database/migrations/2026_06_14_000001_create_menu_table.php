<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->enum('type', ['group', 'page', 'function'])->default('page');
            $table->string('route_name', 150)->nullable();
            $table->string('icon', 100)->nullable();
            $table->integer('order_seq')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menu')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu');
    }
};
