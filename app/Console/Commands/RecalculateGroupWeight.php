<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeliveryProjectPlanning;

class RecalculateGroupWeight extends Command
{
    protected $signature = 'planning:recalculate-weight {delivery_projects_id?}';
    protected $description = 'Recalculate all group weights from children';

    public function handle()
    {
        $projectId = $this->argument('delivery_projects_id');
        
        $query = DeliveryProjectPlanning::where('is_group', true)
            ->with('children');
        
        if ($projectId) {
            $query->where('delivery_projects_id', $projectId);
            $this->info("Recalculating weights for project ID: {$projectId}");
        } else {
            $this->info("Recalculating weights for ALL projects");
        }
        
        $groups = $query->get();
        
        $this->info("Found {$groups->count()} groups to update");
        
        $bar = $this->output->createProgressBar($groups->count());
        $bar->start();
        
        foreach ($groups as $group) {
            // Update weight dari calculated_weight
            $group->weight = $group->calculated_weight;
            $group->saveQuietly();
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("✅ Done! All group weights recalculated.");
        
        return Command::SUCCESS;
    }
}