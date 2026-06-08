<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeliveryProjectSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Memulai seeding data Projects...');

        // Ambil customer dan employee IDs
        $customers = DB::table('customer')->pluck('customer_id')->toArray();
        $employees = DB::table('employee')->pluck('employee_id')->toArray();
        
        // Ambil phase system default (template) - tanpa delivery_projects_id
        $systemDefaultPhases = DB::table('delivery_project_phases')
            ->where('is_system_default', true)
            ->whereNull('delivery_projects_id') // Template phases
            ->orderBy('order_sequence')
            ->get();

        if (empty($customers) || empty($employees)) {
            $this->command->error('✗ Customer atau Employee belum ada. Jalankan CustomerSeeder dan EmployeeSeeder terlebih dahulu.');
            return;
        }

        // Sample projects
        $projects = [
            [
                'client_id' => $customers[0] ?? 1, // Pegadaian
                'name' => 'SAP S/4HANA Implementation - Pegadaian',
                'pic' => 'Ari Wibowo',
                'project_type' => 'Implementation',
                'description' => 'Implementasi SAP S/4HANA untuk PT Pegadaian meliputi modul FI, CO, MM, dan SD',
                'category' => 'In Process',
                'status' => 'On Track',
                'phase' => 'Realization',
                'contract_start_date' => Carbon::now()->subMonths(3)->format('Y-m-d'),
                'contract_end_date' => Carbon::now()->addMonths(6)->format('Y-m-d'),
                'go_live_estimated' => Carbon::now()->addMonths(5)->format('Y-m-d'),
                'calculated_progress' => 35.50,
                'ae_name' => 'Budi Santoso',
                'ae_phone' => '081234567890',
                'ae_email' => 'budi.santoso@vendor.com',
                'delivery_owner_id' => $employees[0] ?? 1,
                'delivery_manager_id' => $employees[1] ?? 2,
                'delivery_method' => 'ASAP',
                'warranty_period' => '3 months',
                'total_mandays' => 450,
                'location_name' => 'Kantor Pusat Pegadaian',
                'location_city' => 'Jakarta Pusat',
                'location_region' => 'DKI Jakarta',
                'location_country' => 'Indonesia',
            ],
            [
                'client_id' => $customers[1] ?? 2, // Telkom
                'name' => 'SAP SuccessFactors - Telkom Indonesia',
                'pic' => 'Dara Safitri',
                'project_type' => 'Implementation',
                'description' => 'Implementasi SAP SuccessFactors untuk modul Employee Central, Performance & Goals',
                'category' => 'Open',
                'status' => 'Monitoring',
                'phase' => 'Project Preparation',
                'contract_start_date' => Carbon::now()->format('Y-m-d'),
                'contract_end_date' => Carbon::now()->addMonths(8)->format('Y-m-d'),
                'go_live_estimated' => Carbon::now()->addMonths(7)->format('Y-m-d'),
                'calculated_progress' => 5.00,
                'ae_name' => 'Ahmad Wijaya',
                'ae_phone' => '081234567891',
                'ae_email' => 'ahmad.wijaya@vendor.com',
                'delivery_owner_id' => $employees[2] ?? 3,
                'delivery_manager_id' => $employees[0] ?? 1,
                'delivery_method' => 'SAP Activate',
                'warranty_period' => '6 months',
                'total_mandays' => 320,
                'location_name' => 'Telkom Landmark Tower',
                'location_city' => 'Jakarta Selatan',
                'location_region' => 'DKI Jakarta',
                'location_country' => 'Indonesia',
            ],
            [
                'client_id' => $customers[5] ?? 6, // Waskita
                'name' => 'SAP ERP Enhancement - Waskita Karya',
                'pic' => 'Gilfan Akbar',
                'project_type' => 'Enhancement',
                'description' => 'Enhancement modul PS dan PM untuk project management dan plant maintenance',
                'category' => 'In Process',
                'status' => 'At Risk',
                'phase' => 'Business Blueprint',
                'contract_start_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'contract_end_date' => Carbon::now()->addMonths(4)->format('Y-m-d'),
                'go_live_estimated' => Carbon::now()->addMonths(3)->format('Y-m-d'),
                'calculated_progress' => 25.00,
                'ae_name' => 'Sinta Dewi',
                'ae_phone' => '081234567892',
                'ae_email' => 'sinta.dewi@vendor.com',
                'delivery_owner_id' => $employees[3] ?? 4,
                'delivery_manager_id' => $employees[0] ?? 1,
                'delivery_method' => 'Agile',
                'warranty_period' => '3 months',
                'total_mandays' => 180,
                'location_name' => 'Waskita Tower',
                'location_city' => 'Jakarta Selatan',
                'location_region' => 'DKI Jakarta',
                'location_country' => 'Indonesia',
            ],
            [
                'client_id' => $customers[10] ?? 11, // Pertamina
                'name' => 'SAP Ariba Implementation - Pertamina',
                'pic' => 'Said Abdullah',
                'project_type' => 'Implementation',
                'description' => 'Implementasi SAP Ariba untuk procurement dan supplier management',
                'category' => 'Closed',
                'status' => 'On Track',
                'phase' => 'Go-Live & Support',
                'contract_start_date' => Carbon::now()->subMonths(8)->format('Y-m-d'),
                'contract_end_date' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                'go_live_estimated' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                'calculated_progress' => 100.00,
                'ae_name' => 'Rina Sari',
                'ae_phone' => '081234567893',
                'ae_email' => 'rina.sari@vendor.com',
                'delivery_owner_id' => $employees[4] ?? 5,
                'delivery_manager_id' => $employees[0] ?? 1,
                'delivery_method' => 'SAP Activate',
                'warranty_period' => '6 months',
                'total_mandays' => 280,
                'location_name' => 'Pertamina Energy Tower',
                'location_city' => 'Jakarta Pusat',
                'location_region' => 'DKI Jakarta',
                'location_country' => 'Indonesia',
            ],
        ];

        foreach ($projects as $index => $projectData) {
            DB::beginTransaction();
            try {
                // Insert project
                $projectId = DB::table('delivery_projects')->insertGetId(array_merge($projectData, [
                    'created_by_id' => null,
                    'location_valid_from' => $projectData['contract_start_date'],
                    'location_valid_to' => $projectData['contract_end_date'],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));

                // ✅ CREATE PROJECT-SPECIFIC PHASES (copy from system default)
                $projectPhases = [];
                foreach ($systemDefaultPhases as $phase) {
                    $phaseId = DB::table('delivery_project_phases')->insertGetId([
                        'delivery_projects_id' => $projectId, // Assign ke project ini
                        'name' => $phase->name,
                        'description' => $phase->description,
                        'order_sequence' => $phase->order_sequence,
                        'color' => $phase->color,
                        'weight' => $phase->weight,
                        'is_system_default' => false, // Bukan template lagi
                        'is_optional' => $phase->is_optional,
                        'orientation' => $phase->orientation,
                        'is_active' => true,
                        'parent_phase_id' => null, // Reset parent untuk project-specific
                        'settings' => $phase->settings,
                        'is_visible' => !$phase->is_optional, // ✅ Kolom baru di tabel phases
                        'custom_settings' => null, // ✅ Kolom baru di tabel phases
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                    
                    $projectPhases[] = (object) [
                        'id' => $phaseId,
                        'name' => $phase->name,
                        'order_sequence' => $phase->order_sequence,
                        'weight' => $phase->weight,
                        'is_optional' => $phase->is_optional,
                        'orientation' => $phase->orientation,
                    ];
                }

                // Add team members
                $teamSize = min(5, count($employees));
                for ($i = 0; $i < $teamSize; $i++) {
                    $assignments = ['Project Manager', 'Functional Consultant', 'Technical Consultant', 'Business Analyst', 'Developer'];
                    DB::table('delivery_project_employee')->insert([
                        'delivery_projects_id' => $projectId,
                        'employee_id' => $employees[$i],
                        'assignment' => $assignments[$i] ?? 'Team Member',
                        'start_date' => $projectData['contract_start_date'],
                        'end_date' => $projectData['contract_end_date'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                }

                // Create planning items (groups and activities)
                $this->createProjectPlanning($projectId, collect($projectPhases), $projectData);

                // Create project updates
                $this->createProjectUpdates($projectId);

                // Create documents
                $this->createProjectDocuments($projectId);

                DB::commit();
                $this->command->info("✓ Project '{$projectData['name']}' berhasil dibuat dengan " . count($projectPhases) . " phases");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("✗ Error membuat project '{$projectData['name']}': " . $e->getMessage());
                $this->command->error("Stack trace: " . $e->getTraceAsString());
            }
        }

        $this->command->info('✓ Seeding Projects selesai! Total: ' . count($projects) . ' projects');
    }

    private function createProjectPlanning($projectId, $phases, $projectData): void
    {
        $startDate = Carbon::parse($projectData['contract_start_date']);

        // Hanya buat planning untuk fase yang visible (non-optional)
        $visiblePhases = $phases->filter(fn($p) => !$p->is_optional);

        foreach ($visiblePhases as $phaseIndex => $phase) {
            $phaseStartDate = $startDate->copy()->addWeeks($phaseIndex * 4);
            $phaseEndDate = $phaseStartDate->copy()->addWeeks(4);

            // Create group for this phase
            $groupId = DB::table('delivery_project_planning')->insertGetId([
                'delivery_projects_id' => $projectId,
                'phase_id' => $phase->id, // ✅ Langsung reference ke phase_id
                'parent_id' => null,
                'name' => $phase->name,
                'group_name' => $phase->name,
                'is_group' => true,
                'level' => 0,
                'order_sequence' => $phase->order_sequence,
                'start_date' => $phaseStartDate->format('Y-m-d'),
                'end_date' => $phaseEndDate->format('Y-m-d'),
                'weight' => $phase->weight,
                'status' => $this->determineStatus($phaseIndex, count($visiblePhases), $projectData['calculated_progress']),
                'progress_percentage' => $this->calculatePhaseProgress($phaseIndex, count($visiblePhases), $projectData['calculated_progress']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Create activities for this group
            $activities = $this->getActivitiesForPhase($phase->name);
            foreach ($activities as $actIndex => $activity) {
                $actStartDate = $phaseStartDate->copy()->addDays($actIndex * 3);
                $actEndDate = $actStartDate->copy()->addDays(5);

                $activityProgress = $this->calculateActivityProgress($phaseIndex, $actIndex, count($visiblePhases), $projectData['calculated_progress']);

                $planningId = DB::table('delivery_project_planning')->insertGetId([
                    'delivery_projects_id' => $projectId,
                    'phase_id' => $phase->id, // ✅ Langsung reference ke phase_id
                    'parent_id' => $groupId,
                    'name' => $activity,
                    'group_name' => null,
                    'is_group' => false,
                    'level' => 1,
                    'order_sequence' => $actIndex + 1,
                    'start_date' => $actStartDate->format('Y-m-d'),
                    'end_date' => $actEndDate->format('Y-m-d'),
                    'actual_start_date' => $activityProgress > 0 ? $actStartDate->format('Y-m-d') : null,
                    'actual_end_date' => $activityProgress >= 100 ? $actEndDate->format('Y-m-d') : null,
                    'weight' => round(100 / count($activities), 2),
                    'status' => $this->determineActivityStatus($activityProgress),
                    'progress_percentage' => $activityProgress,
                    'notes' => "Activity notes for {$activity}",
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                // Create stages for this activity
                $this->createActivityStages($planningId, $activity, $activityProgress);
            }
        }
    }

    private function createActivityStages($planningId, $activityName, $activityProgress): void
    {
        $stages = [
            ['name' => 'Analysis', 'weight' => 20],
            ['name' => 'Design', 'weight' => 20],
            ['name' => 'Build', 'weight' => 30],
            ['name' => 'Test', 'weight' => 20],
            ['name' => 'Deploy', 'weight' => 10],
        ];

        $remainingProgress = $activityProgress;

        foreach ($stages as $index => $stage) {
            $stageProgress = min($remainingProgress, 100);
            if ($stageProgress > $stage['weight']) {
                $stageProgress = 100;
                $remainingProgress -= $stage['weight'];
            } else {
                $stageProgress = ($stageProgress / $stage['weight']) * 100;
                $remainingProgress = 0;
            }

            DB::table('activity_stages')->insert([
                'planning_id' => $planningId,
                'activity_id' => null,
                'name' => $stage['name'],
                'description' => "{$stage['name']} stage for {$activityName}",
                'planned_start_date' => Carbon::now()->addDays($index)->format('Y-m-d'),
                'planned_end_date' => Carbon::now()->addDays($index + 2)->format('Y-m-d'),
                'actual_start_date' => $stageProgress > 0 ? Carbon::now()->addDays($index)->format('Y-m-d') : null,
                'actual_end_date' => $stageProgress >= 100 ? Carbon::now()->addDays($index + 2)->format('Y-m-d') : null,
                'progress' => min($stageProgress, 100),
                'status' => $this->determineActivityStatus(min($stageProgress, 100)),
                'weight' => $stage['weight'],
                'order_sequence' => $index + 1,
                'color' => ['#6366F1', '#8B5CF6', '#EC4899', '#F59E0B', '#10B981'][$index],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    private function createProjectUpdates($projectId): void
    {
        $updates = [
            [
                'highlight_issue' => 'Weekly status meeting completed',
                'action' => 'Continue with planned activities',
                'due_date' => Carbon::now()->addWeek()->format('Y-m-d'),
                'status' => 'Open',
                'complexity' => 'Low',
                'deliverable' => 'Weekly Status Report',
                'notes' => 'All tasks on track',
            ],
            [
                'highlight_issue' => 'Resource constraint identified',
                'action' => 'Request additional resources from management',
                'due_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'status' => 'In Progress',
                'complexity' => 'Medium',
                'deliverable' => 'Resource Request Form',
                'notes' => 'Need 2 additional consultants',
            ],
            [
                'highlight_issue' => 'Integration testing delayed',
                'action' => 'Reschedule testing activities',
                'due_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'status' => 'Open',
                'complexity' => 'High',
                'deliverable' => 'Updated Test Plan',
                'notes' => 'Dependency on third-party system',
            ],
        ];

        foreach ($updates as $update) {
            DB::table('delivery_project_updates')->insert(array_merge($update, [
                'delivery_projects_id' => $projectId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }
    }

    private function createProjectDocuments($projectId): void
    {
        $documents = [
            ['document_name' => 'Project Charter', 'document_type' => 'Contract', 'link_document' => 'https://drive.google.com/project-charter'],
            ['document_name' => 'Business Blueprint Document', 'document_type' => 'Others', 'link_document' => 'https://drive.google.com/bbp-document'],
            ['document_name' => 'Test Plan', 'document_type' => 'Others', 'link_document' => 'https://drive.google.com/test-plan'],
        ];

        foreach ($documents as $doc) {
            DB::table('documents')->insert(array_merge($doc, [
                'delivery_projects_id' => $projectId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }
    }

    private function getActivitiesForPhase(string $phaseName): array
    {
        $phaseActivities = [
            'Project Preparation' => ['Project Kickoff', 'Team Onboarding', 'Infrastructure Setup', 'Project Planning'],
            'Business Blueprint' => ['As-Is Process Analysis', 'To-Be Process Design', 'Gap Analysis', 'BBP Documentation', 'Sign-off Meeting'],
            'Realization' => ['System Configuration', 'Custom Development', 'Interface Development', 'Data Migration Prep', 'Unit Testing'],
            'Final Preparation' => ['Integration Testing', 'User Acceptance Testing', 'End User Training', 'Data Migration', 'Cutover Planning'],
            'Go-Live & Support' => ['Go-Live Execution', 'Hypercare Support', 'Issue Resolution', 'Knowledge Transfer', 'Project Closure'],
        ];

        return $phaseActivities[$phaseName] ?? ['Activity 1', 'Activity 2', 'Activity 3'];
    }

    private function determineStatus(int $phaseIndex, int $totalPhases, float $projectProgress): string
    {
        $expectedProgress = (($phaseIndex + 1) / $totalPhases) * 100;

        if ($projectProgress >= $expectedProgress) {
            return $projectProgress >= 100 ? 'completed' : 'in_progress';
        } elseif ($projectProgress > 0 && $phaseIndex === 0) {
            return 'in_progress';
        } elseif ($projectProgress > ($phaseIndex / $totalPhases) * 100) {
            return 'in_progress';
        }

        return 'not_started';
    }

    private function calculatePhaseProgress(int $phaseIndex, int $totalPhases, float $projectProgress): float
    {
        $phaseWeight = 100 / $totalPhases;
        $phaseStart = $phaseIndex * $phaseWeight;
        $phaseEnd = ($phaseIndex + 1) * $phaseWeight;

        if ($projectProgress >= $phaseEnd) {
            return 100;
        } elseif ($projectProgress <= $phaseStart) {
            return 0;
        }

        return (($projectProgress - $phaseStart) / $phaseWeight) * 100;
    }

    private function calculateActivityProgress(int $phaseIndex, int $actIndex, int $totalPhases, float $projectProgress): float
    {
        $phaseProgress = $this->calculatePhaseProgress($phaseIndex, $totalPhases, $projectProgress);
        $activitiesPerPhase = 5; // Assume 5 activities per phase

        $activityWeight = 100 / $activitiesPerPhase;
        $activityStart = $actIndex * $activityWeight;
        $activityEnd = ($actIndex + 1) * $activityWeight;

        if ($phaseProgress >= $activityEnd) {
            return 100;
        } elseif ($phaseProgress <= $activityStart) {
            return 0;
        }

        return (($phaseProgress - $activityStart) / $activityWeight) * 100;
    }

    private function determineActivityStatus(float $progress): string
    {
        if ($progress >= 100) {
            return 'completed';
        } elseif ($progress > 0) {
            return 'in_progress';
        }
        return 'not_started';
    }
}
