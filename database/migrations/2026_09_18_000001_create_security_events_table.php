<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();

            $table->string('event_type', 50); // brute_force_account_lockout | brute_force_ip_lockout | xss_probe | sqli_probe | path_traversal_probe | privilege_escalation | mass_export | anomalous_login
            $table->string('severity', 20); // low | medium | high | critical
            $table->string('module', 60); // Auth | WAF | Privilege | Export | Session
            $table->string('status', 20)->default('open'); // open | resolved
            $table->string('title', 255);
            $table->text('description')->nullable();

            // Target/actor: intentionally no FK constraint, same rationale as
            // audit_logs.actor_id — high write volume, must survive employee
            // deletion. Names/identifiers are denormalized.
            $table->unsignedBigInteger('target_employee_id')->nullable();
            $table->string('target_identifier', 150)->nullable(); // attempted email/username when no account exists
            $table->string('target_ip', 45)->nullable();
            $table->unsignedBigInteger('actor_employee_id')->nullable(); // who caused it (e.g. admin who changed a role)
            $table->string('actor_name', 150)->nullable();

            $table->string('ip_address', 45)->nullable(); // request origin
            $table->string('user_agent', 255)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 255)->nullable();

            $table->json('payload')->nullable(); // matched pattern, field name, truncated value, counts, etc.
            $table->unsignedBigInteger('related_audit_log_id')->nullable(); // optional cross-ref, no FK

            $table->unsignedBigInteger('resolved_by_id')->nullable();
            $table->string('resolved_by_name', 150)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('event_type');
            $table->index('severity');
            $table->index('status');
            $table->index('module');
            $table->index('target_ip');
            $table->index('target_employee_id');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
