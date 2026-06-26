<?php

namespace App\Console\Commands;

use App\Models\TicketDeliverable;
use App\Services\OneDriveService;
use Illuminate\Console\Command;

/**
 * One-off: backfill anonymous share links for ticket deliverable files.
 *
 * Older records stored the raw SharePoint path (webUrl) in onedrive_file_url,
 * which requires drive permission to open ("Request access"). This regenerates
 * a proper anonymous (anyone-with-the-link) share link from the stored
 * onedrive_file_id so customers can open the file without logging in.
 *
 * Dry-run by default. Pass --execute to apply.
 */
class BackfillDeliverableFileLinks extends Command
{
    protected $signature = 'onedrive:backfill-deliverable-links
                            {--execute : Apply changes (omit for a dry-run)}
                            {--all : Re-link every record, even ones that already look like a share link}';

    protected $description = 'Regenerate anonymous share links for ticket deliverable files (fixes "Request access").';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $all     = (bool) $this->option('all');
        $od      = new OneDriveService();

        $rows = TicketDeliverable::whereNotNull('onedrive_file_id')->get();

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($rows as $d) {
            // A real anonymous share link contains a "/:x:/" segment; raw path URLs do not.
            $looksShared = $d->onedrive_file_url && preg_match('~/:[a-z]:/~i', $d->onedrive_file_url);
            if ($looksShared && !$all) {
                $skipped++;
                continue;
            }

            try {
                $link = $od->createAnonymousLink($d->onedrive_file_id, 'view');
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  [#{$d->id}] {$d->file_name}: {$e->getMessage()}");
                continue;
            }

            $this->line("  [#{$d->id}] {$d->file_name}");
            $this->line("      old: " . ($d->onedrive_file_url ?: '(none)'));
            $this->line("      new: {$link}");

            if ($execute) {
                $d->update(['onedrive_file_url' => $link]);
            }
            $updated++;
        }

        $this->newLine();
        $this->info(($execute ? 'Applied' : 'DRY-RUN') . ": {$updated} relinked, {$skipped} already shared, {$failed} failed.");
        if (!$execute && $updated > 0) {
            $this->comment('Re-run with --execute to persist changes.');
        }

        return self::SUCCESS;
    }
}
