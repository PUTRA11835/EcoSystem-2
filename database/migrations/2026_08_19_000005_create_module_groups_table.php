<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('module_group_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_group_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();

            $table->unique(['module_group_id', 'module_id']);
            $table->foreign('module_group_id')->references('id')->on('module_groups')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_group_modules');
        Schema::dropIfExists('module_groups');
    }
};
