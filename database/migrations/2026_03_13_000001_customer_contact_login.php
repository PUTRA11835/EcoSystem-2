<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Make customer.email nullable — no longer required for login,
        //    now optional (company general email). Keep unique so it still
        //    prevents duplicate entries when filled.
        DB::statement('ALTER TABLE customer MODIFY email VARCHAR(255) NULL');

        // 2. Add contact_id to auth_users — links a login account to a
        //    specific contact person. Cascade-delete: if the contact is
        //    removed, the login account is also removed automatically.
        Schema::table('auth_users', function (Blueprint $table) {
            $table->unsignedBigInteger('contact_id')->nullable()->after('customer_id');
            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('customer_contact')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        DB::statement('ALTER TABLE customer MODIFY email VARCHAR(255) NOT NULL');
    }
};
