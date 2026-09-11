<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE ticket_sla_pauses
            MODIFY pause_reason ENUM('waiting_customer','sent_to_sap','sent_to_support','on_hold','meeting')
            NOT NULL
        ");

        DB::statement("
            ALTER TABLE ticket_sla_events
            MODIFY event_type ENUM(
                'email_received',
                'ticket_validated',
                'agent_replied',
                'customer_replied',
                'resolution_sent',
                'escalated_to_sap',
                'escalated_to_support',
                'sla_warning',
                'sla_breached',
                'ticket_closed',
                'meeting_started',
                'meeting_ended'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE ticket_sla_pauses
            MODIFY pause_reason ENUM('waiting_customer','sent_to_sap','sent_to_support','on_hold')
            NOT NULL
        ");

        DB::statement("
            ALTER TABLE ticket_sla_events
            MODIFY event_type ENUM(
                'email_received',
                'ticket_validated',
                'agent_replied',
                'customer_replied',
                'resolution_sent',
                'escalated_to_sap',
                'escalated_to_support',
                'sla_warning',
                'sla_breached',
                'ticket_closed'
            ) NOT NULL
        ");
    }
};
