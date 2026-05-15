<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlaSeeder extends Seeder
{
    // ─── Mapping jarvies_status → pause_reason ──────────────────────────────
    private const PAUSE_REASONS = [
        'proposed solution'  => 'waiting_customer',
        'author action'      => 'waiting_customer',
        'sent in to SAP'     => 'sent_to_sap',
        'sent it to support' => 'sent_to_support',
    ];

    public function run(): void
    {
        $this->command->info('Seeding SLA Policies...');
        $this->seedPolicies();

        $this->command->info('Seeding prerequisite data (delivery support, ticket scale)...');
        $this->seedPrerequisites();

        $this->command->info('Seeding Ticket SLA records...');
        $this->seedTicketSla();

        $this->command->info('SLA Seeder selesai.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // PART 1: SLA POLICIES
    // ────────────────────────────────────────────────────────────────────────
    private function seedPolicies(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('ticket_sla_events')->truncate();
        DB::table('ticket_sla_pauses')->truncate();
        DB::table('ticket_sla')->truncate();
        DB::table('sla_policies')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = now();

        // ── GLOBAL DEFAULT (customer_id = NULL) ───────────────────────────
        $global = [
            // Very High — 24 jam
            ['Very High', 'Simple',  1,  2, true],
            ['Very High', 'Medium',  1,  4, true],
            ['Very High', 'Complex', 1,  8, true],
            // High
            ['High',      'Simple',  2,  8,  false],
            ['High',      'Medium',  2,  16, false],
            ['High',      'Complex', 2,  24, false],
            // Medium
            ['Medium',    'Simple',  4,  24, false],
            ['Medium',    'Medium',  4,  48, false],
            ['Medium',    'Complex', 4,  72, false],
            // Low
            ['Low',       'Simple',  8,  48, false],
            ['Low',       'Medium',  8,  72, false],
            ['Low',       'Complex', 8,  96, false],
        ];
        foreach ($global as [$priority, $scale, $response, $resolution, $is24]) {
            DB::table('sla_policies')->insert([
                'customer_id'      => null,
                'priority'         => $priority,
                'scale'            => $scale,
                'response_hours'   => $response,
                'resolution_hours' => $resolution,
                'is_24_hours'      => $is24,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── PEGADAIAN (customer_id = 1) ───────────────────────────────────
        // Sesuai gambar pertama yang dikirim user
        $pegadaian = [
            ['Very High', 'Simple',  1, 2,  true],
            ['Very High', 'Medium',  1, 4,  true],
            ['Very High', 'Complex', 1, 8,  true],
            ['High',      'Simple',  1, 6,  false],
            ['High',      'Medium',  1, 8,  false],
            ['High',      'Complex', 1, 12, false],
            ['Medium',    'Simple',  1, 18, false],
            ['Medium',    'Medium',  1, 24, false],
            ['Medium',    'Complex', 1, 48, false],
            ['Low',       'Simple',  1, 48, false],
            ['Low',       'Medium',  1, 72, false],
            ['Low',       'Complex', 1, 96, false],
        ];
        foreach ($pegadaian as [$priority, $scale, $response, $resolution, $is24]) {
            DB::table('sla_policies')->insert([
                'customer_id'      => 1,
                'priority'         => $priority,
                'scale'            => $scale,
                'response_hours'   => $response,
                'resolution_hours' => $resolution,
                'is_24_hours'      => $is24,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── AIRNAV (customer_id = 4) ──────────────────────────────────────
        // Sesuai gambar pertama (hanya 4 baris)
        $airnav = [
            ['Very High', 'Medium', 1, 4,  true],
            ['High',      'Medium', 4, 24, false],
            ['Medium',    'Medium', 4, 48, false],
            ['Low',       'Medium', 4, 72, false],
        ];
        foreach ($airnav as [$priority, $scale, $response, $resolution, $is24]) {
            DB::table('sla_policies')->insert([
                'customer_id'      => 4,
                'priority'         => $priority,
                'scale'            => $scale,
                'response_hours'   => $response,
                'resolution_hours' => $resolution,
                'is_24_hours'      => $is24,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── INDO RAYA (customer_id = 37) ──────────────────────────────────
        $indoraya = [
            ['Very High', 'Simple',  1, 4,  true],
            ['Very High', 'Medium',  1, 6,  true],
            ['Very High', 'Complex', 1, 12, true],
            ['High',      'Simple',  2, 8,  false],
            ['High',      'Medium',  2, 12, false],
            ['High',      'Complex', 2, 24, false],
            ['Medium',    'Simple',  4, 24, false],
            ['Medium',    'Medium',  4, 48, false],
            ['Medium',    'Complex', 4, 72, false],
            ['Low',       'Simple',  8, 48, false],
            ['Low',       'Medium',  8, 72, false],
            ['Low',       'Complex', 8, 96, false],
        ];
        foreach ($indoraya as [$priority, $scale, $response, $resolution, $is24]) {
            DB::table('sla_policies')->insert([
                'customer_id'      => 37,
                'priority'         => $priority,
                'scale'            => $scale,
                'response_hours'   => $response,
                'resolution_hours' => $resolution,
                'is_24_hours'      => $is24,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        // ── APTAWORKS (customer_id = 39) ──────────────────────────────────
        $aptaworks = [
            ['Very High', 'Simple',  1, 2,  true],
            ['Very High', 'Medium',  1, 4,  true],
            ['Very High', 'Complex', 1, 8,  true],
            ['High',      'Simple',  2, 6,  false],
            ['High',      'Medium',  2, 10, false],
            ['High',      'Complex', 2, 20, false],
            ['Medium',    'Simple',  4, 20, false],
            ['Medium',    'Medium',  4, 40, false],
            ['Medium',    'Complex', 4, 60, false],
            ['Low',       'Simple',  8, 40, false],
            ['Low',       'Medium',  8, 60, false],
            ['Low',       'Complex', 8, 80, false],
        ];
        foreach ($aptaworks as [$priority, $scale, $response, $resolution, $is24]) {
            DB::table('sla_policies')->insert([
                'customer_id'      => 39,
                'priority'         => $priority,
                'scale'            => $scale,
                'response_hours'   => $response,
                'resolution_hours' => $resolution,
                'is_24_hours'      => $is24,
                'is_active'        => true,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }

        $this->command->line('  ✓ ' . DB::table('sla_policies')->count() . ' SLA policies created.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // PART 2: PREREQUISITES — delivery support & ticket scale
    // ────────────────────────────────────────────────────────────────────────
    private function seedPrerequisites(): void
    {
        $now = now();

        // ── Isi ticket_type yang kosong (default Incident) ───────────────
        DB::table('ticket')
            ->whereIn('ticket_id', [1,2,3,4,5,6,7,8,9,11,12,13,14,15,16,17,18])
            ->where(function ($q) { $q->whereNull('ticket_type')->orWhere('ticket_type', ''); })
            ->update(['ticket_type' => 'Incident']);

        // ── Isi scale tiket yang kosong ──────────────────────────────────
        $scaleMap = [
            1 => 'Simple',  2 => 'Simple',  3 => 'Complex', 4 => 'Simple',
            5 => 'Medium',  6 => 'Complex', 7 => 'Simple',  8 => 'Complex',
            9 => 'Medium', 10 => 'Medium', 11 => 'Complex', 12 => 'Simple',
           13 => 'Complex', 14 => 'Medium', 15 => 'Medium', 16 => 'Simple',
           17 => 'Medium', 18 => 'Medium',
        ];
        foreach ($scaleMap as $ticketId => $scale) {
            DB::table('ticket')
                ->where('ticket_id', $ticketId)
                ->where(function ($q) { $q->whereNull('scale')->orWhere('scale', ''); })
                ->update(['scale' => $scale]);
        }
        $this->command->line('  ✓ Ticket scale diisi.');

        $this->command->line('  ✓ Prerequisite data siap.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // PART 3: TICKET SLA RECORDS
    // ────────────────────────────────────────────────────────────────────────
    private function seedTicketSla(): void
    {
        /*
         * Data tiket aktual dari DB:
         * ID | ticket_number | customer_id | priority   | scale  | status        | created_at
         *  1 | 26040001      | null        | High       | null   | open          | 15/04 13:25
         *  2 | 26040002      | null        | Very High  | null   | open          | 15/04 13:48
         *  3 | 26040003      | 37 (IndoRy) | High       | null   | open          | 15/04 14:15
         *  4 | 26040004      | 39 (Apta)   | Medium     | null   | in_progress   | 15/04 14:52
         *  5 | 26040005      | 39 (Apta)   | Medium     | null   | open          | 15/04 17:41
         *  6 | 26040006      | 37 (IndoRy) | Medium     | null   | open          | 20/04 10:49
         *  7 | 26040007      | 37 (IndoRy) | Low        | null   | open          | 20/04 12:44
         *  8 | 26040008      | 37 (IndoRy) | Very High  | null   | open          | 20/04 14:59
         *  9 | 26040009      | 39 (Apta)   | Medium     | null   | open          | 22/04 10:58
         * 10 | 26040010      | 39 (Apta)   | Medium     | null   | open          | 22/04 12:37
         * 11 | 26040011      | 37 (IndoRy) | Medium     | null   | open          | 22/04 13:51
         * 12 | 26040012      | 39 (Apta)   | Medium     | null   | open          | 22/04 14:16
         * 13 | 26040013      | 37 (IndoRy) | Very High  | null   | open          | 27/04 14:55
         * 14 | 26040014      | 39 (Apta)   | Very High  | Medium | in_progress   | 30/04 12:13
         * 15 | 26040015      | 37 (IndoRy) | Very High  | Medium | open          | 30/04 12:45
         * 16 | 26040016      | 37 (IndoRy) | Very High  | null   | open          | 30/04 13:48
         * 17 | 26040017      | 39 (Apta)   | High       | Medium | in_progress   | 30/04 14:15
         * 18 | 26040018      | 39 (Apta)   | Medium     | Medium | wait_to_close | 30/04 23:13
         *
         * Staging → Ticket mapping (staging.created_at = sla_start_at):
         *  staging:2  → ticket:1  | 15/04 13:23
         *  staging:3  → ticket:2  | 15/04 13:47
         *  staging:5  → ticket:3  | 15/04 07:10
         *  staging:6  → ticket:4  | 15/04 07:38
         *  staging:8  → ticket:5  | 15/04 17:39
         *  staging:12 → ticket:6  | 20/04 10:48
         *  staging:16 → ticket:7  | 20/04 12:43
         *  staging:20 → ticket:8  | 20/04 14:59
         *  staging:23 → ticket:9  | 22/04 10:43
         *  staging:24 → ticket:10 | 22/04 12:35
         *  staging:25 → ticket:11 | 22/04 13:49
         *  staging:26 → ticket:12 | 22/04 14:04
         *  staging:38 → ticket:13 | 27/04 14:45
         *  staging:43 → ticket:14 | 30/04 12:12
         *  staging:44 → ticket:15 | 30/04 12:44
         *  staging:45 → ticket:16 | 30/04 13:48
         *  staging:48 → ticket:17 | 30/04 14:13
         *  staging:54 → ticket:18 | 30/04 23:12
         */

        // Null scale → pakai 'Simple' untuk policy lookup
        $tickets = [
            // [ticket_id, staging_id, customer_id, priority, scale, sla_start_at, ticket_created_at, ticket_status, scenario]
            [1,  2,  null, 'High',      'Simple',  '2026-04-15 13:23:00', '2026-04-15 13:25:00', 'open',          'breached_long'],
            [2,  3,  null, 'Very High', 'Simple',  '2026-04-15 13:47:00', '2026-04-15 13:48:00', 'open',          'breached_with_pauses'],
            [3,  5,  37,   'High',      'Simple',  '2026-04-15 07:10:00', '2026-04-15 14:15:00', 'open',          'breached_slow_validation'],
            [4,  6,  39,   'Medium',    'Simple',  '2026-04-15 07:38:00', '2026-04-15 14:52:00', 'in_progress',   'breached_with_waiting'],
            [5,  8,  39,   'Medium',    'Simple',  '2026-04-15 17:39:00', '2026-04-15 17:41:00', 'open',          'breached_no_action'],
            [6,  12, 37,   'Medium',    'Simple',  '2026-04-20 10:48:00', '2026-04-20 10:49:00', 'open',          'breached_with_pauses'],
            [7,  16, 37,   'Low',       'Simple',  '2026-04-20 12:43:00', '2026-04-20 12:44:00', 'open',          'met_closed'],
            [8,  20, 37,   'Very High', 'Simple',  '2026-04-20 14:59:00', '2026-04-20 14:59:00', 'open',          'breached_long'],
            [9,  23, 39,   'Medium',    'Simple',  '2026-04-22 10:43:00', '2026-04-22 10:58:00', 'open',          'paused_waiting'],
            [10, 24, 39,   'Medium',    'Simple',  '2026-04-22 12:35:00', '2026-04-22 12:37:00', 'open',          'met_closed'],
            [11, 25, 37,   'Medium',    'Simple',  '2026-04-22 13:49:00', '2026-04-22 13:51:00', 'open',          'breached_with_pauses'],
            [12, 26, 39,   'Medium',    'Simple',  '2026-04-22 14:04:00', '2026-04-22 14:16:00', 'open',          'pending_active'],
            [13, 38, 37,   'Very High', 'Simple',  '2026-04-27 14:45:00', '2026-04-27 14:55:00', 'open',          'breached_with_pauses'],
            [14, 43, 39,   'Very High', 'Medium',  '2026-04-30 12:12:00', '2026-04-30 12:13:00', 'in_progress',   'paused_waiting'],
            [15, 44, 37,   'Very High', 'Medium',  '2026-04-30 12:44:00', '2026-04-30 12:45:00', 'open',          'pending_active'],
            [16, 45, 37,   'Very High', 'Simple',  '2026-04-30 13:48:00', '2026-04-30 13:48:00', 'open',          'pending_active'],
            [17, 48, 39,   'High',      'Medium',  '2026-04-30 14:13:00', '2026-04-30 14:15:00', 'in_progress',   'pending_active'],
            [18, 54, 39,   'Medium',    'Medium',  '2026-04-30 23:12:00', '2026-04-30 23:13:00', 'wait_to_close', 'met_closed'],
        ];

        $slaFullTypes = ['Incident', 'Service Request'];

        foreach ($tickets as $t) {
            [$ticketId, $stagingId, $customerId, $priority, $scale, $slaStart, $ticketCreated, $status, $scenario] = $t;

            // Semua ticket_type eligible — skip hanya jika ticket tidak ada
            $ticket = DB::table('ticket')->where('ticket_id', $ticketId)->first();
            if (!$ticket) {
                $this->command->warn("  Skip ticket #{$ticketId}: tiket tidak ditemukan di DB.");
                continue;
            }

            // Tentukan mode SLA
            $slaMode = in_array($ticket->ticket_type, $slaFullTypes, true)
                ? 'full'
                : 'response_only';

            // Trova policy
            $policy = DB::table('sla_policies')
                ->where('priority', $priority)
                ->where('scale', $scale)
                ->where(function($q) use ($customerId) {
                    if ($customerId) {
                        $q->where('customer_id', $customerId)->orWhereNull('customer_id');
                    } else {
                        $q->whereNull('customer_id');
                    }
                })
                ->orderByRaw('customer_id IS NULL ASC')
                ->first();

            if (!$policy) continue;

            $start = Carbon::parse($slaStart);
            $validated = Carbon::parse($ticketCreated);

            // Hitung response time (sla_start → ticket_created)
            $validationHours = round($start->diffInMinutes($validated) / 60, 2);
            $responseTarget  = (float) $policy->response_hours;
            $responseStatus  = $validationHours <= $responseTarget ? 'met' : 'breached';

            // Buat data SLA berdasarkan skenario
            $slaData = $this->buildScenario(
                $scenario, $start, $validated, $policy, $validationHours, $responseStatus
            );

            // response_only: kosongkan semua field resolution
            if ($slaMode === 'response_only') {
                $slaData['resolution_due_at']  = null;
                $slaData['resolved_at']        = null;
                $slaData['total_waiting']      = 0;
                $slaData['net_resolution']     = null;
                $slaData['resolution_status']  = 'pending';
                $slaData['pauses']             = [];
            }

            // Insert ticket_sla
            $slaId = DB::table('ticket_sla')->insertGetId([
                'staging_ticket_id'          => $stagingId,
                'ticket_id'                  => $ticketId,
                'sla_policy_id'              => $policy->id,
                'sla_mode'                   => $slaMode,
                'sla_start_at'               => $start,
                'response_due_at'            => $start->copy()->addHours($responseTarget),
                'resolution_due_at'          => $slaData['resolution_due_at'],
                'first_responded_at'         => $validated,
                'validation_duration_hours'  => $validationHours,
                'response_status'            => $responseStatus,
                'resolved_at'                => $slaData['resolved_at'],
                'total_waiting_hours'        => $slaData['total_waiting'],
                'net_resolution_hours'       => $slaData['net_resolution'],
                'resolution_status'          => $slaData['resolution_status'],
                'created_at'                 => $start,
                'updated_at'                 => $slaData['updated_at'],
            ]);

            // Insert pauses
            foreach ($slaData['pauses'] as $pause) {
                DB::table('ticket_sla_pauses')->insert([
                    'ticket_id'            => $ticketId,
                    'pause_reason'         => $pause['reason'],
                    'triggered_by_status'  => $pause['triggered_status'],
                    'started_at'           => $pause['started_at'],
                    'ended_at'             => $pause['ended_at'],
                    'duration_hours'       => $pause['duration_hours'],
                    'created_at'           => $pause['started_at'],
                ]);
            }

            // Insert events
            foreach ($slaData['events'] as $event) {
                DB::table('ticket_sla_events')->insert([
                    'ticket_id'          => $ticketId,
                    'staging_ticket_id'  => $stagingId,
                    'event_type'         => $event['type'],
                    'event_at'           => $event['at'],
                    'waiting_hours'      => $event['waiting']    ?? null,
                    'response_hours'     => $event['response']   ?? null,
                    'resolution_hours'   => $event['resolution'] ?? null,
                    'notes'              => $event['notes']      ?? null,
                    'triggered_by_type'  => $event['by_type']    ?? 'system',
                    'created_at'         => $event['at'],
                ]);
            }
        }

        $this->command->line('  ✓ ' . DB::table('ticket_sla')->count() . ' ticket_sla records created.');
        $this->command->line('  ✓ ' . DB::table('ticket_sla_pauses')->count() . ' pause records created.');
        $this->command->line('  ✓ ' . DB::table('ticket_sla_events')->count() . ' event log records created.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // Scenario Builder
    // ────────────────────────────────────────────────────────────────────────
    private function buildScenario(
        string $scenario,
        Carbon $start,
        Carbon $validated,
        object $policy,
        float  $validationHours,
        string $responseStatus
    ): array {
        $resTarget = (float) $policy->resolution_hours;

        return match($scenario) {
            'met_closed'              => $this->scenarioMetClosed($start, $validated, $policy, $validationHours, $responseStatus),
            'breached_long'           => $this->scenarioBreachedLong($start, $validated, $policy, $validationHours, $responseStatus),
            'breached_with_pauses'    => $this->scenarioBreachedWithPauses($start, $validated, $policy, $validationHours, $responseStatus),
            'breached_slow_validation'=> $this->scenarioBreachedSlowValidation($start, $validated, $policy, $validationHours, $responseStatus),
            'breached_with_waiting'   => $this->scenarioBreachedWithWaiting($start, $validated, $policy, $validationHours, $responseStatus),
            'breached_no_action'      => $this->scenarioBreachedNoAction($start, $validated, $policy, $validationHours, $responseStatus),
            'paused_waiting'          => $this->scenarioPausedWaiting($start, $validated, $policy, $validationHours, $responseStatus),
            'pending_active'          => $this->scenarioPendingActive($start, $validated, $policy, $validationHours, $responseStatus),
            default                   => $this->scenarioPendingActive($start, $validated, $policy, $validationHours, $responseStatus),
        };
    }

    // ── Scenario: SLA Met, Tiket Closed ─────────────────────────────────────
    private function scenarioMetClosed(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget  = (float) $policy->resolution_hours;
        $netWork    = round($resTarget * 0.65, 2); // Selesai di 65% dari target
        $waitHours  = 1.5;
        $closedAt   = $validated->copy()->addHours($netWork + $waitHours + 0.5);
        $resDue     = $start->copy()->addHours($resTarget + $waitHours);

        $pause1Start = $validated->copy()->addHours(2);
        $pause1End   = $pause1Start->copy()->addHours($waitHours);

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => $closedAt,
            'total_waiting'     => $waitHours,
            'net_resolution'    => $netWork,
            'resolution_status' => 'met',
            'updated_at'        => $closedAt,
            'pauses' => [
                [
                    'reason'           => 'waiting_customer',
                    'triggered_status' => 'proposed solution',
                    'started_at'       => $pause1Start,
                    'ended_at'         => $pause1End,
                    'duration_hours'   => $waitHours,
                ],
            ],
            'events' => [
                ['type' => 'email_received',   'at' => $start,                       'response' => 0,   'notes' => 'Request masuk', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated,                   'response' => $vh, 'notes' => 'Tiket dibuat oleh helpdesk', 'by_type' => 'employee'],
                ['type' => 'resolution_sent',  'at' => $pause1Start,                 'resolution' => round($netWork * 0.5, 2), 'notes' => 'Resolusi #1 dikirim ke customer', 'by_type' => 'employee'],
                ['type' => 'customer_replied', 'at' => $pause1End,                   'waiting' => $waitHours, 'notes' => 'Customer membalas', 'by_type' => 'customer'],
                ['type' => 'ticket_closed',    'at' => $closedAt,                    'resolution' => $netWork, 'notes' => "SLA Met: selesai dalam {$netWork} jam (target {$policy->resolution_hours} jam)", 'by_type' => 'employee'],
            ],
        ];
    }

    // ── Scenario: Breached, Tiket Masih Open Lama ───────────────────────────
    private function scenarioBreachedLong(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget = (float) $policy->resolution_hours;
        // Tiket sudah jauh melewati deadline, masih open
        $now        = Carbon::parse('2026-05-07 09:00:00');
        $elapsed    = round($start->diffInHours($now), 2);
        $netWork    = round($elapsed * 0.8, 2); // 80% waktu aktif, 20% waiting historis
        $resDue     = $start->copy()->addHours($resTarget);
        $breach     = $resDue->copy()->addHours(1);

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => null,
            'total_waiting'     => 0,
            'net_resolution'    => null,
            'resolution_status' => 'breached',
            'updated_at'        => $breach,
            'pauses' => [],
            'events' => [
                ['type' => 'email_received',   'at' => $start,     'response' => 0,   'notes' => 'Request masuk', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh, 'notes' => 'Tiket dibuat oleh helpdesk', 'by_type' => 'employee'],
                ['type' => 'sla_breached',     'at' => $breach,    'notes' => "SLA Breach: melewati target {$policy->resolution_hours} jam", 'by_type' => 'system'],
            ],
        ];
    }

    // ── Scenario: Breached, Ada Beberapa Pauses ─────────────────────────────
    private function scenarioBreachedWithPauses(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget = (float) $policy->resolution_hours;

        // Round 1: agen kirim solusi → customer balas → lanjut
        $res1At      = $validated->copy()->addHours(2);
        $wait1Hours  = 0.83;
        $resume1At   = $res1At->copy()->addHours($wait1Hours + 2); // +2 jam = weekend/malam
        $res1Net     = 2.0;

        // Round 2: agen kirim lagi → customer balas
        $res2At      = $resume1At->copy()->addHours(1.5);
        $wait2Hours  = 2.0;
        $resume2At   = $res2At->copy()->addHours($wait2Hours + 3);
        $res2Net     = round($res1Net + 1.5, 2);

        // Round 3: agen kirim lagi → belum ditutup
        $res3At      = $resume2At->copy()->addHours(3.0);
        $res3Net     = round($res2Net + 3.0, 2);
        $totalWait   = round($wait1Hours + $wait2Hours, 2);
        $resDue      = $start->copy()->addHours($resTarget + $totalWait);
        $breachAt    = $resDue->copy()->addMinutes(30);

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => null,
            'total_waiting'     => $totalWait,
            'net_resolution'    => null,
            'resolution_status' => 'breached',
            'updated_at'        => $breachAt,
            'pauses' => [
                ['reason' => 'waiting_customer', 'triggered_status' => 'proposed solution',
                 'started_at' => $res1At, 'ended_at' => $resume1At, 'duration_hours' => $wait1Hours],
                ['reason' => 'waiting_customer', 'triggered_status' => 'author action',
                 'started_at' => $res2At, 'ended_at' => $resume2At, 'duration_hours' => $wait2Hours],
            ],
            'events' => [
                ['type' => 'email_received',   'at' => $start,     'response' => 0,     'notes' => 'Request masuk', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh,   'notes' => 'Tiket dibuat', 'by_type' => 'employee'],
                ['type' => 'resolution_sent',  'at' => $res1At,    'resolution' => $res1Net, 'notes' => 'Resolusi #1 dikirim', 'by_type' => 'employee'],
                ['type' => 'customer_replied', 'at' => $resume1At, 'waiting' => $wait1Hours, 'notes' => 'Customer membalas', 'by_type' => 'customer'],
                ['type' => 'resolution_sent',  'at' => $res2At,    'resolution' => $res2Net, 'notes' => 'Resolusi #2 dikirim', 'by_type' => 'employee'],
                ['type' => 'customer_replied', 'at' => $resume2At, 'waiting' => $wait2Hours, 'notes' => 'Customer membalas lagi', 'by_type' => 'customer'],
                ['type' => 'resolution_sent',  'at' => $res3At,    'resolution' => $res3Net, 'notes' => 'Resolusi #3 dikirim', 'by_type' => 'employee'],
                ['type' => 'sla_breached',     'at' => $breachAt,  'notes' => "SLA Breach: {$res3Net} jam net (target {$policy->resolution_hours} jam)", 'by_type' => 'system'],
            ],
        ];
    }

    // ── Scenario: Breached karena Validasi Lambat ───────────────────────────
    private function scenarioBreachedSlowValidation(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget = (float) $policy->resolution_hours;
        $resDue    = $start->copy()->addHours($resTarget);
        $breachAt  = $resDue->copy()->addHours(2);
        $netWork   = 1.5; // Kerja singkat setelah validasi lama

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => null,
            'total_waiting'     => 0,
            'net_resolution'    => null,
            'resolution_status' => 'breached',
            'updated_at'        => $breachAt,
            'pauses' => [],
            'events' => [
                ['type' => 'email_received',   'at' => $start,     'response' => 0,   'notes' => 'Request masuk pagi', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh, 'notes' => "Validasi lambat ({$vh} jam)", 'by_type' => 'employee'],
                ['type' => 'sla_warning',      'at' => $resDue->copy()->subHour(), 'notes' => 'Sisa 1 jam menuju deadline', 'by_type' => 'system'],
                ['type' => 'sla_breached',     'at' => $breachAt,  'notes' => "Breach: validasi memakan {$vh} jam dari target {$policy->response_hours} jam response", 'by_type' => 'system'],
            ],
        ];
    }

    // ── Scenario: Breached, Ada Waiting yang Panjang ────────────────────────
    private function scenarioBreachedWithWaiting(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget  = (float) $policy->resolution_hours;
        $res1At     = $validated->copy()->addHours(3);
        $wait1Hours = 4.0; // Customer lama balas
        $resume1At  = $res1At->copy()->addHours($wait1Hours + 5);
        $res2At     = $resume1At->copy()->addHours(2.5);
        $wait2Hours = 1.0;
        $resume2At  = $res2At->copy()->addHours($wait2Hours);
        $closeAt    = $resume2At->copy()->addHours(0.5);
        $netWork    = round(3 + 2.5 + 0.5 + $vh, 2);
        $totalWait  = round($wait1Hours + $wait2Hours, 2);
        $resDue     = $start->copy()->addHours($resTarget + $totalWait);

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => $closeAt,
            'total_waiting'     => $totalWait,
            'net_resolution'    => $netWork,
            'resolution_status' => 'breached',
            'updated_at'        => $closeAt,
            'pauses' => [
                ['reason' => 'waiting_customer', 'triggered_status' => 'proposed solution',
                 'started_at' => $res1At, 'ended_at' => $resume1At, 'duration_hours' => $wait1Hours],
                ['reason' => 'waiting_customer', 'triggered_status' => 'author action',
                 'started_at' => $res2At, 'ended_at' => $resume2At, 'duration_hours' => $wait2Hours],
            ],
            'events' => [
                ['type' => 'email_received',   'at' => $start,     'response' => 0,   'notes' => 'Request masuk', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh, 'notes' => 'Tiket dibuat', 'by_type' => 'employee'],
                ['type' => 'resolution_sent',  'at' => $res1At,    'resolution' => round(3 + $vh, 2), 'notes' => 'Resolusi #1', 'by_type' => 'employee'],
                ['type' => 'customer_replied', 'at' => $resume1At, 'waiting' => $wait1Hours, 'notes' => 'Customer lama balas', 'by_type' => 'customer'],
                ['type' => 'resolution_sent',  'at' => $res2At,    'resolution' => round(3 + 2.5 + $vh, 2), 'notes' => 'Resolusi #2', 'by_type' => 'employee'],
                ['type' => 'customer_replied', 'at' => $resume2At, 'waiting' => $wait2Hours, 'notes' => 'Customer balas', 'by_type' => 'customer'],
                ['type' => 'ticket_closed',    'at' => $closeAt,   'resolution' => $netWork, 'notes' => "Breach: {$netWork} jam (target {$policy->resolution_hours} jam)", 'by_type' => 'employee'],
            ],
        ];
    }

    // ── Scenario: Breached, Tidak Ada Aksi dari Agen ────────────────────────
    private function scenarioBreachedNoAction(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget = (float) $policy->resolution_hours;
        $resDue    = $start->copy()->addHours($resTarget);
        $warnAt    = $resDue->copy()->subHours(max(1, $resTarget * 0.2));
        $breachAt  = $resDue->copy()->addMinutes(15);

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => null,
            'total_waiting'     => 0,
            'net_resolution'    => null,
            'resolution_status' => 'breached',
            'updated_at'        => $breachAt,
            'pauses' => [],
            'events' => [
                ['type' => 'email_received',   'at' => $start,     'response' => 0,   'notes' => 'Request masuk', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh, 'notes' => 'Tiket dibuat', 'by_type' => 'employee'],
                ['type' => 'sla_warning',      'at' => $warnAt,    'notes' => 'Mendekati deadline SLA — tidak ada aksi agen', 'by_type' => 'system'],
                ['type' => 'sla_breached',     'at' => $breachAt,  'notes' => 'Breach: tidak ada aksi dari agen', 'by_type' => 'system'],
            ],
        ];
    }

    // ── Scenario: SLA Paused, Menunggu Customer ──────────────────────────────
    private function scenarioPausedWaiting(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget   = (float) $policy->resolution_hours;
        $res1At      = $validated->copy()->addHours(1.5);
        $wait1Hours  = 0.75;
        $resume1At   = $res1At->copy()->addHours($wait1Hours + 1);
        $res2At      = $resume1At->copy()->addHours(2.0);
        $netSoFar    = round($vh + 1.5 + 2.0, 2);
        $totalWait   = $wait1Hours;
        $resDue      = $start->copy()->addHours($resTarget + $totalWait);

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => null,
            'total_waiting'     => $totalWait,
            'net_resolution'    => null,
            'resolution_status' => 'paused',
            'updated_at'        => $res2At,
            'pauses' => [
                ['reason' => 'waiting_customer', 'triggered_status' => 'proposed solution',
                 'started_at' => $res1At, 'ended_at' => $resume1At, 'duration_hours' => $wait1Hours],
                // Pause aktif (ended_at = NULL)
                ['reason' => 'waiting_customer', 'triggered_status' => 'proposed solution',
                 'started_at' => $res2At, 'ended_at' => null, 'duration_hours' => null],
            ],
            'events' => [
                ['type' => 'email_received',   'at' => $start,     'response' => 0,     'notes' => 'Request masuk', 'by_type' => 'system'],
                ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh,   'notes' => 'Tiket dibuat', 'by_type' => 'employee'],
                ['type' => 'resolution_sent',  'at' => $res1At,    'resolution' => round($vh + 1.5, 2), 'notes' => 'Resolusi #1 dikirim', 'by_type' => 'employee'],
                ['type' => 'customer_replied', 'at' => $resume1At, 'waiting' => $wait1Hours, 'notes' => 'Customer membalas', 'by_type' => 'customer'],
                ['type' => 'resolution_sent',  'at' => $res2At,    'resolution' => $netSoFar, 'notes' => 'Resolusi #2 dikirim — menunggu konfirmasi customer', 'by_type' => 'employee'],
            ],
        ];
    }

    // ── Scenario: SLA Aktif, Baru Dimulai ───────────────────────────────────
    private function scenarioPendingActive(Carbon $start, Carbon $validated, object $policy, float $vh, string $rs): array
    {
        $resTarget = (float) $policy->resolution_hours;
        $resDue    = $start->copy()->addHours($resTarget);
        $warnAt    = $resDue->copy()->subHours(max(0.5, $resTarget * 0.2));

        $events = [
            ['type' => 'email_received',   'at' => $start,     'response' => 0,   'notes' => 'Request masuk', 'by_type' => 'system'],
            ['type' => 'ticket_validated', 'at' => $validated, 'response' => $vh, 'notes' => 'Tiket dibuat', 'by_type' => 'employee'],
        ];

        $resStatus = 'pending';

        // Jika sudah lewat 80% target → tambah warning
        $elapsed = $start->diffInHours(Carbon::parse('2026-05-07 09:00:00'));
        if ($elapsed >= $resTarget * 0.8 && $elapsed < $resTarget) {
            $events[] = ['type' => 'sla_warning', 'at' => $warnAt, 'notes' => 'Mendekati deadline SLA', 'by_type' => 'system'];
        } elseif ($elapsed >= $resTarget) {
            $events[] = ['type' => 'sla_breached', 'at' => $resDue->copy()->addMinutes(10), 'notes' => 'Deadline terlewati', 'by_type' => 'system'];
            $resStatus = 'breached';
        }

        return [
            'resolution_due_at' => $resDue,
            'resolved_at'       => null,
            'total_waiting'     => 0,
            'net_resolution'    => null,
            'resolution_status' => $resStatus,
            'updated_at'        => $validated,
            'pauses'            => [],
            'events'            => $events,
        ];
    }
}
