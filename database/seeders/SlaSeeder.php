<?php

namespace Database\Seeders;

use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Services\SlaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SlaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding SLA Policies...');
        $this->seedPolicies();

        $this->command->info('Seeding prerequisite data (ticket scale)...');
        $this->seedPrerequisites();

        $this->command->info('Syncing Ticket SLA records dari data aktual...');
        $this->syncTicketSla();

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
    // PART 2: PREREQUISITES
    // ────────────────────────────────────────────────────────────────────────
    private function seedPrerequisites(): void
    {
        // Isi ticket_type default 'Incident' untuk semua tiket yang kosong
        $updated = DB::table('ticket')
            ->where(function ($q) { $q->whereNull('ticket_type')->orWhere('ticket_type', ''); })
            ->update(['ticket_type' => 'Incident']);

        if ($updated > 0) {
            $this->command->line("  ✓ {$updated} tiket diisi ticket_type='Incident'.");
        }

        // Isi scale default 'Simple' untuk semua tiket yang kosong
        $updatedScale = DB::table('ticket')
            ->where(function ($q) { $q->whereNull('scale')->orWhere('scale', ''); })
            ->update(['scale' => 'Simple']);

        if ($updatedScale > 0) {
            $this->command->line("  ✓ {$updatedScale} tiket diisi scale='Simple'.");
        }

        $this->command->line('  ✓ Prerequisite data siap.');
    }

    // ────────────────────────────────────────────────────────────────────────
    // PART 3: TICKET SLA RECORDS (dynamic — reads from actual DB state)
    // ────────────────────────────────────────────────────────────────────────
    private function syncTicketSla(): void
    {
        $slaService = app(SlaService::class);

        // Load all tickets without SLA record
        $existingIds = DB::table('ticket_sla')
            ->whereNotNull('ticket_id')
            ->pluck('ticket_id')
            ->all();

        $tickets = Ticket::whereNotIn('ticket_id', $existingIds)
            ->whereNotNull('ticket_type')
            ->whereNotNull('ticket_priority')
            ->orderBy('ticket_id')
            ->get();

        if ($tickets->isEmpty()) {
            $this->command->line('  ✓ Semua tiket sudah memiliki SLA record.');
            return;
        }

        // Load staging tickets (keyed by ticket_id)
        $stagingMap = StagingTicket::whereNotNull('ticket_id')
            ->whereIn('ticket_id', $tickets->pluck('ticket_id'))
            ->get()
            ->keyBy('ticket_id');

        $created  = 0;
        $skipped  = 0;

        foreach ($tickets as $ticket) {
            $staging = $stagingMap->get($ticket->ticket_id);
            $slaService->attachToTicket($ticket, $staging);

            if ($ticket->sla()->exists()) {
                $created++;
            } else {
                $this->command->warn("  SKIP ticket #{$ticket->ticket_id}: tidak ada SLA policy yang cocok.");
                $skipped++;
            }
        }

        $this->command->line("  ✓ {$created} ticket_sla records created, {$skipped} dilewati.");

        // Backfill events dari ticket_message
        if ($created > 0) {
            $this->command->line('  Backfill SLA events dari ticket_message...');
            Artisan::call('sla:backfill-events', ['--all' => true]);
            $this->command->line('  ✓ ' . DB::table('ticket_sla_events')->count() . ' total event records.');
        }

        $this->command->line('  ✓ ' . DB::table('ticket_sla')->count() . ' total ticket_sla records.');
        $this->command->line('  ✓ ' . DB::table('ticket_sla_pauses')->count() . ' total pause records.');
    }
}
