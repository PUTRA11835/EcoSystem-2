<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            $table->timestamp('customer_response_at')->nullable()->after('sent_to_chat_at');
        });
    }

    public function down(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            $table->dropColumn('customer_response_at');
        });
    }
};
