<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_activity', function (Blueprint $table) {
            $table->id('activity_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('login_at')->nullable();
            $table->timestamp('logout_at')->nullable();
            $table->enum('status', ['success', 'failed', 'logout'])->default('success');
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('user_id');
            $table->index('role_id');
            $table->index('login_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_activity');
    }
};

// ===== routes/api.php =====

