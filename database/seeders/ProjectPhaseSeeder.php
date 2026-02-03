<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProjectPhaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai seeding data Project Phases...');

        // Default project phases untuk SAP Implementation
        $phases = [
            [
                'name' => 'Project Preparation',
                'description' => 'Fase persiapan project termasuk kickoff meeting, project planning, dan resource allocation',
                'order_sequence' => 1,
                'color' => '#6366F1', // Indigo
                'weight' => 10.00,
                'is_system_default' => true,
                'is_optional' => false,
                'orientation' => 'vertical',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['Project Kickoff', 'Team Setup', 'Infrastructure Setup'],
                    'estimated_duration_weeks' => 2,
                ]),
            ],
            [
                'name' => 'Business Blueprint',
                'description' => 'Fase analisis bisnis dan dokumentasi requirement (BBP/As-Is dan To-Be)',
                'order_sequence' => 2,
                'color' => '#8B5CF6', // Purple
                'weight' => 20.00,
                'is_system_default' => true,
                'is_optional' => false,
                'orientation' => 'vertical',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['As-Is Analysis', 'To-Be Design', 'Gap Analysis', 'BBP Document'],
                    'estimated_duration_weeks' => 4,
                ]),
            ],
            [
                'name' => 'Realization',
                'description' => 'Fase development dan konfigurasi sistem sesuai blueprint',
                'order_sequence' => 3,
                'color' => '#EC4899', // Pink
                'weight' => 30.00,
                'is_system_default' => true,
                'is_optional' => false,
                'orientation' => 'vertical',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['Configuration', 'Development', 'Unit Testing', 'Data Migration Prep'],
                    'estimated_duration_weeks' => 8,
                ]),
            ],
            [
                'name' => 'Final Preparation',
                'description' => 'Fase persiapan final termasuk testing, training, dan data migration',
                'order_sequence' => 4,
                'color' => '#F59E0B', // Amber
                'weight' => 25.00,
                'is_system_default' => true,
                'is_optional' => false,
                'orientation' => 'vertical',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['Integration Testing', 'UAT', 'End User Training', 'Data Migration', 'Cutover Planning'],
                    'estimated_duration_weeks' => 4,
                ]),
            ],
            [
                'name' => 'Go-Live & Support',
                'description' => 'Fase go-live dan dukungan pasca implementasi',
                'order_sequence' => 5,
                'color' => '#10B981', // Emerald
                'weight' => 15.00,
                'is_system_default' => true,
                'is_optional' => false,
                'orientation' => 'vertical',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['Go-Live Execution', 'Hypercare Support', 'Issue Resolution', 'Project Closure'],
                    'estimated_duration_weeks' => 4,
                ]),
            ],
            // Optional phases
            [
                'name' => 'Change Management',
                'description' => 'Fase opsional untuk manajemen perubahan organisasi',
                'order_sequence' => 6,
                'color' => '#06B6D4', // Cyan
                'weight' => 0.00,
                'is_system_default' => true,
                'is_optional' => true,
                'orientation' => 'horizontal',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['Stakeholder Analysis', 'Communication Plan', 'Training Plan'],
                    'estimated_duration_weeks' => 0,
                    'spans_all_phases' => true,
                ]),
            ],
            [
                'name' => 'Quality Assurance',
                'description' => 'Fase opsional untuk quality assurance dan testing',
                'order_sequence' => 7,
                'color' => '#EF4444', // Red
                'weight' => 0.00,
                'is_system_default' => true,
                'is_optional' => true,
                'orientation' => 'horizontal',
                'is_active' => true,
                'settings' => json_encode([
                    'default_activities' => ['Quality Planning', 'Test Strategy', 'Defect Management'],
                    'estimated_duration_weeks' => 0,
                    'spans_all_phases' => true,
                ]),
            ],
        ];

        foreach ($phases as $phase) {
            DB::table('project_phases')->insert(array_merge($phase, [
                'parent_phase_id' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
            $this->command->info("✓ Phase '{$phase['name']}' berhasil dibuat");
        }

        $this->command->info('✓ Seeding Project Phases selesai! Total: ' . count($phases) . ' phases');
    }
}
