<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mandays_negotiation', function (Blueprint $table) {
            $table->id('negotiation_id');
            $table->unsignedBigInteger('ticket_id');
            $table->decimal('proposed_mandays', 10, 2);
            $table->enum('proposed_by', ['employee', 'admin', 'customer']);
            $table->unsignedBigInteger('proposed_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'countered'])->default('pending');
            $table->boolean('is_customer_confirmed')->default(false);
            $table->timestamp('customer_confirmed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            
            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->onDelete('cascade');
        });
        
        Schema::table('ticket', function (Blueprint $table) {
            $table->decimal('current_negotiated_mandays', 10, 2)->nullable()->after('man_days');
            $table->enum('negotiation_status', ['none', 'pending_customer', 'pending_admin', 'accepted'])->default('none')->after('current_negotiated_mandays');
        });
    }

    public function down()
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn(['current_negotiated_mandays', 'negotiation_status']);
        });
        
        // ✅ PERBAIKI nama tabel (sesuai dengan up())
        Schema::dropIfExists('mandays_negotiation');
    }
};