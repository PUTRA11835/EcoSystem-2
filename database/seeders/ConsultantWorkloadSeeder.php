<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConsultantWorkloadSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding consultant workload data...');

        // ── Lookup helpers ───────────────────────────────────────────────
        $helpdeskId = DB::table('employee')->where('eci', 'ECI_HELPDESK')->value('employee_id');
        $customerId = DB::table('customer')->first()?->customer_id;

        if (!$helpdeskId || !$customerId) {
            $this->command->warn('Helpdesk employee atau customer tidak ditemukan. Jalankan EmployeeSeeder dan CustomerSeeder terlebih dahulu.');
            return;
        }

        // Ambil 6 consultant aktif (role_id = 2)
        $consultants = DB::table('employee')
            ->where('role_id', 2)
            ->where('is_active', true)
            ->orderBy('employee_id')
            ->limit(6)
            ->pluck('employee_id')
            ->toArray();

        if (count($consultants) < 2) {
            $this->command->warn('Minimal butuh 2 consultant (role_id=2). Jalankan EmployeeSeeder terlebih dahulu.');
            return;
        }

        // ── Ticket definitions ───────────────────────────────────────────
        $tickets = [
            [
                'number'    => 'TKT-SEED-001',
                'subject'   => '[SEED] Error AP Invoice Posting – Duplicate Document',
                'status'    => 'in_progress',
                'priority'  => 'High',
                'type'      => 'AMS',
                'module'    => 'FI',
                'progress'  => 30,
                'start'     => now()->subDays(10),
                'end'       => now()->addDays(5),
                'pic_idx'   => 0,
                'members'   => [1, 2],
                'mandays'   => [
                    0 => ['md' => 3, 'add' => 1],
                    1 => ['md' => 2, 'add' => 0],
                    2 => ['md' => 2, 'add' => 0.5],
                ],
            ],
            [
                'number'    => 'TKT-SEED-002',
                'subject'   => '[SEED] BOM Explosion Issue – MRP Planning Run',
                'status'    => 'in_progress',
                'priority'  => 'Medium',
                'type'      => 'AMS',
                'module'    => 'PP',
                'progress'  => 60,
                'start'     => now()->subDays(20),
                'end'       => now()->addDays(3),
                'pic_idx'   => 1,
                'members'   => [2, 3],
                'mandays'   => [
                    1 => ['md' => 4, 'add' => 0],
                    2 => ['md' => 1, 'add' => 0],
                    3 => ['md' => 3, 'add' => 1],
                ],
            ],
            [
                'number'    => 'TKT-SEED-003',
                'subject'   => '[SEED] Customer Master Data Mismatch – Credit Limit',
                'status'    => 'in_progress',
                'priority'  => 'High',
                'type'      => 'MO',
                'module'    => 'SD',
                'progress'  => 20,
                'start'     => now()->subDays(5),
                'end'       => now()->addDays(10),
                'pic_idx'   => 2,
                'members'   => [0, 3, 4],
                'mandays'   => [
                    2 => ['md' => 2, 'add' => 0],
                    0 => ['md' => 3, 'add' => 0],
                    3 => ['md' => 1, 'add' => 0],
                    4 => ['md' => 2, 'add' => 1],
                ],
            ],
            [
                'number'    => 'TKT-SEED-004',
                'subject'   => '[SEED] Goods Receipt Quantity Discrepancy – WM Integration',
                'status'    => 'in_progress',
                'priority'  => 'Medium',
                'type'      => 'ATS',
                'module'    => 'WM',
                'progress'  => 75,
                'start'     => now()->subDays(30),
                'end'       => now()->subDays(2),
                'pic_idx'   => 3,
                'members'   => [4, 5],
                'mandays'   => [
                    3 => ['md' => 2, 'add' => 0],
                    4 => ['md' => 3, 'add' => 0.5],
                    5 => ['md' => 1, 'add' => 0],
                ],
            ],
            [
                'number'    => 'TKT-SEED-005',
                'subject'   => '[SEED] Payroll Calculation Error – Overtime Rule Change',
                'status'    => 'in_progress',
                'priority'  => 'Very High',
                'type'      => 'AMS',
                'module'    => 'HCM',
                'progress'  => 10,
                'start'     => now()->subDays(3),
                'end'       => now()->addDays(14),
                'pic_idx'   => 4,
                'members'   => [0, 1],
                'mandays'   => [
                    4 => ['md' => 5, 'add' => 0],
                    0 => ['md' => 2, 'add' => 0],
                    1 => ['md' => 2, 'add' => 1],
                ],
            ],
            [
                'number'    => 'TKT-SEED-006',
                'subject'   => '[SEED] Asset Depreciation Run – Incorrect Period Assignment',
                'status'    => 'open',
                'priority'  => 'Low',
                'type'      => 'AMS',
                'module'    => 'FI-AA',
                'progress'  => 0,
                'start'     => now()->addDays(1),
                'end'       => now()->addDays(10),
                'pic_idx'   => 5,
                'members'   => [2],
                'mandays'   => [
                    5 => ['md' => 3, 'add' => 0],
                    2 => ['md' => 2, 'add' => 0],
                ],
            ],
        ];

        // ── Insert ───────────────────────────────────────────────────────
        foreach ($tickets as $def) {
            // Skip jika ticket number sudah ada
            if (DB::table('ticket')->where('ticket_number', $def['number'])->exists()) {
                $this->command->line("  Skip {$def['number']} (sudah ada)");
                continue;
            }

            $picEmpId = $consultants[$def['pic_idx']] ?? $consultants[0];

            // 1. Insert ticket
            $ticketId = DB::table('ticket')->insertGetId([
                'ticket_number'      => $def['number'],
                'customer_id'        => $customerId,
                'employee_id'        => $picEmpId,
                'subject'            => $def['subject'],
                'status'             => $def['status'],
                'ticket_priority'    => $def['priority'],
                'ticket_type'        => $def['type'],
                'module'             => $def['module'],
                'man_days'           => array_sum(array_column($def['mandays'], 'md')),
                'progress_percentage'=> $def['progress'],
                'last_progress_at'   => $def['progress'] > 0 ? now()->subDays(rand(1, 3)) : null,
                'start_date'         => $def['start'],
                'end_date'           => $def['end'],
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // 2. Insert ticket_member
            foreach ($def['members'] as $memberIdx) {
                $memberEmpId = $consultants[$memberIdx] ?? null;
                if (!$memberEmpId || $memberEmpId === $picEmpId) continue;

                DB::table('ticket_member')->updateOrInsert(
                    ['ticket_id' => $ticketId, 'employee_id' => $memberEmpId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }

            // 3. Insert consultant_mandays
            $totalMd = array_sum(array_map(
                fn($m) => $m['md'] + $m['add'],
                $def['mandays']
            ));

            $cmId = DB::table('consultant_mandays')->insertGetId([
                'ticket_id'            => $ticketId,
                'proposed_by_agent_id' => $helpdeskId,
                'proposed_at'          => now()->subDays(rand(1, 5)),
                'status'               => 'approved',
                'approved_by_head_id'  => null,
                'approved_at'          => now()->subDays(rand(1, 3)),
                'total_mandays'        => $totalMd,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            // 4. Insert consultant_mandays_detail per consultant
            foreach ($def['mandays'] as $empIdx => $alloc) {
                $empId = $consultants[$empIdx] ?? null;
                if (!$empId) continue;

                DB::table('consultant_mandays_detail')->insert([
                    'consultant_mandays_id' => $cmId,
                    'employee_id'           => $empId,
                    'module'                => $def['module'],
                    'mandays'               => $alloc['md'],
                    'additional_mandays'    => $alloc['add'],
                    'approved_additional'   => $alloc['add'],
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }

            $this->command->line("  ✓ {$def['number']} – {$def['subject']}");
        }

        $this->command->info('Done. ' . count($tickets) . ' tiket di-seed.');
    }
}
