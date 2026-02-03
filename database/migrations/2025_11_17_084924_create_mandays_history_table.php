<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mandays_history', function (Blueprint $table) {
            $table->id('history_id');
            $table->unsignedBigInteger('ticket_id');
            $table->decimal('old_mandays', 10, 2);
            $table->decimal('new_mandays', 10, 2);
            $table->unsignedBigInteger('changed_by'); // user_id yang ubah
            $table->enum('changed_by_role', ['admin', 'customer', 'employee']);
            $table->text('reason')->nullable();
            $table->timestamps();
            
            $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mandays_history');
    }
};