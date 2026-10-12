<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contact_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();

            // A contact can belong to at most one group at a time — keeps the
            // shared-visibility model easy to reason about (no overlapping groups).
            $table->unique('contact_id', 'contact_group_members_contact_unique');

            $table->foreign('group_id')
                  ->references('group_id')
                  ->on('customer_contact_groups')
                  ->onDelete('cascade');

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('customer_contact')
                  ->onDelete('cascade');

            $table->index('group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contact_group_members');
    }
};
