<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('reason', 255)->nullable();
            $table->string('source', 20)->default('manual'); // auto | manual
            $table->unsignedBigInteger('blocked_by_id')->nullable();
            $table->string('blocked_by_name', 150)->nullable();
            $table->timestamp('expires_at')->nullable(); // null = permanent
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
