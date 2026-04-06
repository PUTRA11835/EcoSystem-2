<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPlanning;
use App\Models\ActivityStage;
use App\Models\DeliveryProjectPhase;
use Illuminate\Support\Facades\DB;

class MigrateHorizontalPhasesToStages extends Command
{
    protected $signature = 'planning:migrate-horizontal-to-stages {delivery_projects_id?}';
    protected $description = 'Migrate horizontal phases to activity stages';

    public function handle()
    {
        $projectId = $this->argument('delivery_projects_id');
        
        if ($projectId) {
            $projects = DeliveryProject::where('id', $projectId)->get();
        } else {
            $projects = DeliveryProject::all();
        }
        
        $this->info("Migrating horizontal phases for {$projects->count()} project(s)...");
        
        foreach ($projects as $project) {
            $this->migrateProject($project);
        }
        
        $this->info("✅ Migration complete!");
    }
    
    private function migrateProject(DeliveryProject $project)
    {
        $this->line("Processing project: {$project->description}");
        
        // Get horizontal phases (One-to-Many relationship)
        $horizontalPhases = $project->phases()
            ->where('orientation', 'horizontal')
            ->get();
        
        if ($horizontalPhases->isEmpty()) {
            $this->warn("  No horizontal phases found");
            return;
        }
        
        // Get all activities
        $activities = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('is_group', false)
            ->get();
        
        foreach ($activities as $activity) {
            $this->info("  Activity: {$activity->name}");
            
            // Skip if already has stages
            if ($activity->stages()->count() > 0) {
                $this->warn("    Already has stages, skipping");
                continue;
            }
            
            // Create stages based on horizontal phases
            $weightPerStage = 100 / $horizontalPhases->count();
            $activityDuration = $activity->start_date->diffInDays($activity->end_date);
            $daysPerStage = max(1, floor($activityDuration / $horizontalPhases->count()));
            
            $sequence = 0;
            foreach ($horizontalPhases as $phase) {
                $stageStart = $activity->start_date->copy()->addDays($sequence * $daysPerStage);
                $stageEnd = $stageStart->copy()->addDays($daysPerStage - 1);
                
                // Don't exceed activity end date
                if ($stageEnd->gt($activity->end_date)) {
                    $stageEnd = $activity->end_date;
                }
                
                ActivityStage::create([
                    'planning_id' => $activity->id,
                    'name' => $phase->name,
                    'planned_start_date' => $stageStart,
                    'planned_end_date' => $stageEnd,
                    'weight' => $weightPerStage,
                    'progress' => 0,
                    'status' => 'not_started',
                    'color' => $phase->color,
                    'order_sequence' => $sequence,
                ]);
                
                $sequence++;
                $this->line("    ✓ Created stage: {$phase->name}");
            }
        }
    }
}