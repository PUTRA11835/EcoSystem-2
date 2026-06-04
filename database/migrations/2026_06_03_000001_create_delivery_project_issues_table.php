<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')
                  ->constrained('delivery_projects')
                  ->onDelete('cascade');

            $table->unsignedSmallInteger('issue_number');           // 1, 2, 3 → displayed as ISS-001

            // Optional link to a Risk in the Project Risk Register (RSK-xxx).
            // Null when the issue is not tied to any registered risk.
            $table->foreignId('delivery_project_risk_id')
                  ->nullable()
                  ->constrained('delivery_project_risks')
                  ->nullOnDelete();

            $table->text('issue_description');                      // Issue Description
            $table->string('module', 100)->nullable();             // Module (FI, MM, SD, ...)
            $table->date('date_identified');                       // Date Identified
            $table->date('closed_date')->nullable();               // Closed Date (when status = Closed)
            $table->string('status', 20)->default('Open');         // Open | Closed
            $table->text('risk_to_project')->nullable();           // Risk To Project
            $table->string('priority', 10)->default('Medium');     // High | Medium | Low (H/M/L)
            $table->string('originator', 150)->nullable();         // Originator (team member)
            $table->string('owner', 150)->nullable();              // Owner (team member)
            $table->date('estimated_closed')->nullable();          // Estimated Closed
            $table->boolean('escalation_needed')->default(false);  // Escalation Needed (Y/N)
            $table->text('impact_of_issue')->nullable();           // Impact of Issue
            $table->text('tracking_comments')->nullable();         // Tracking Comments
            $table->timestamps();

            $table->index('delivery_projects_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_issues');
    }
};
