<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('menu_id');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();

            $table->primary(['role_id', 'menu_id']);

            $table->foreign('role_id')
                ->references('id')
                ->on('employee_role')
                ->onDelete('cascade');

            $table->foreign('menu_id')
                ->references('id')
                ->on('menu')
                ->onDelete('cascade');

            $table->index('role_id', 'idx_role_menu_role');
            $table->index('menu_id', 'idx_role_menu_menu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu');
    }
};
