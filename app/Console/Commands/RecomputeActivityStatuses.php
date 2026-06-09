<?php

namespace App\Console\Commands;

use App\Models\DeliveryProjectActivity;
use App\Models\DeliveryProjectPlanning;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecomputeActivityStatuses extends Command
{
    protected $signature = 'activities:recompute-status';

    protected $description = 'Recompute delivery activity statuses (for time-dependent transitions like delayed).';

    public function handle(): int
    {
        $this->info('Recomputing activity statuses...');

        $updated = 0;
        $checked = 0;

        DeliveryProjectActivity::chunkById(500, function ($activities) use (&$updated, &$checked) {
            foreach ($activities as $activity) {
                $checked++;
                $newStatus = $activity->computeStatus();

                if ($activity->status !== $newStatus) {
                    $activity->status = $newStatus;
                    $activity->saveQuietly();

                    $planning = DeliveryProjectPlanning::where('activity_id', $activity->id)->first();
                    if ($planning && $planning->status !== $newStatus) {
                        $planning->status = $newStatus;
                        $planning->saveQuietly();
                    }

                    $updated++;
                }
            }
        });

        $this->info("Checked: {$checked}, Updated: {$updated}");
        Log::info('Activity status recompute completed', ['checked' => $checked, 'updated' => $updated]);

        return self::SUCCESS;
    }
}
