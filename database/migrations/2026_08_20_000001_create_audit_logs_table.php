<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id');
            $table->string('module', 60);
            $table->string('event', 20); // created | updated | deleted
            $table->string('record_label', 255)->nullable();

            // Actor: intentionally no FK constraint. This table has far higher
            // write volume than period_audit_logs (touches almost every
            // employee), so it must never block employee deletion or lose
            // history. actor_name is denormalized for the same reason.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedSmallInteger('actor_role_id')->nullable();
            $table->string('actor_name', 150)->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('actor_id');
            $table->index('module');
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
