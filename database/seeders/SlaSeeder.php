<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SlaSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating SLA policies...');

        // ── Global Default Policies (customer_id = NULL) ──────────────────────
        $globals = [
            // Very High
            ['priority' => 'Very High', 'scale' => 'Simple',  'response_hours' => 2,  'resolution_hours' => 8,   'is_24_hours' => true],
            ['priority' => 'Very High', 'scale' => 'Medium',  'response_hours' => 2,  'resolution_hours' => 12,  'is_24_hours' => true],
            ['priority' => 'Very High', 'scale' => 'Complex', 'response_hours' => 2,  'resolution_hours' => 24,  'is_24_hours' => true],
            // High
            ['priority' => 'High',      'scale' => 'Simple',  'response_hours' => 4,  'resolution_hours' => 16,  'is_24_hours' => false],
            ['priority' => 'High',      'scale' => 'Medium',  'response_hours' => 4,  'resolution_hours' => 24,  'is_24_hours' => false],
            ['priority' => 'High',      'scale' => 'Complex', 'response_hours' => 4,  'resolution_hours' => 40,  'is_24_hours' => false],
            // Medium
            ['priority' => 'Medium',    'scale' => 'Simple',  'response_hours' => 8,  'resolution_hours' => 40,  'is_24_hours' => false],
            ['priority' => 'Medium',    'scale' => 'Medium',  'response_hours' => 8,  'resolution_hours' => 80,  'is_24_hours' => false],
            ['priority' => 'Medium',    'scale' => 'Complex', 'response_hours' => 8,  'resolution_hours' => 120, 'is_24_hours' => false],
            // Low
            ['priority' => 'Low',       'scale' => 'Simple',  'response_hours' => 16, 'resolution_hours' => 80,  'is_24_hours' => false],
            ['priority' => 'Low',       'scale' => 'Medium',  'response_hours' => 16, 'resolution_hours' => 120, 'is_24_hours' => false],
            ['priority' => 'Low',       'scale' => 'Complex', 'response_hours' => 16, 'resolution_hours' => 160, 'is_24_hours' => false],
        ];

        foreach ($globals as $g) {
            SlaPolicy::firstOrCreate(
                ['customer_id' => null, 'priority' => $g['priority'], 'scale' => $g['scale']],
                array_merge($g, ['customer_id' => null, 'is_active' => true])
            );
        }

        $this->command->info('Global default policies created.');

        // ── Fix tickets missing ticket_type or scale ──────────────────────────
        $fixed = DB::table('ticket')->whereNull('ticket_type')->update(['ticket_type' => 'Incident']);
        if ($fixed) {
            $this->command->info("  $fixed ticket(s) with no ticket_type set to 'Incident'.");
        }

        $fixedScale = DB::table('ticket')->whereNull('scale')->update(['scale' => 'Simple']);
        if ($fixedScale) {
            $this->command->info("  $fixedScale ticket(s) with no scale set to 'Simple'.");
        }

        // ── Sync SLA records ──────────────────────────────────────────────────
        $this->command->info('Running sla:sync...');
        Artisan::call('sla:sync', [], $this->command->getOutput());

        $this->command->info('Running sla:backfill-events --all...');
        Artisan::call('sla:backfill-events', ['--all' => true], $this->command->getOutput());

        $this->command->info('SlaSeeder complete.');
    }
}
