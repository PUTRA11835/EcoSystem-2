<?php

namespace App\Console\Commands;

use App\Models\DeliverySupport;
use App\Services\OneDriveService;
use Illuminate\Console\Command;

/**
 * One-off: rename per-support deliverable folders to the current naming scheme
 * (support name only, no ID prefix). Run this after dropping the ID prefix so
 * folders already created with the old "NNN Name" format aren't orphaned.
 *
 * The folder item ID is unchanged by a rename, so delivery_support.onedrive_*
 * references and ticket share links remain valid.
 *
 * Dry-run by default. Pass --execute to apply.
 */
class RenameSupportDeliverableFolders extends Command
{
    protected $signature = 'onedrive:rename-support-folders
                            {--execute : Apply changes (omit for a dry-run)}
                            {--support= : Limit to a single delivery_support id}';

    protected $description = 'Rename per-support deliverable folders to match the current (no ID prefix) naming.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $od      = new OneDriveService();
        $root    = config('services.microsoft_graph.customer_deliverable_path', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE');

        $this->line($execute
            ? '<fg=yellow;options=bold>EXECUTE mode — folders WILL be renamed.</>'
            : '<fg=cyan;options=bold>DRY-RUN mode — no changes. Re-run with --execute to apply.</>');

        $query = DeliverySupport::whereNotNull('onedrive_deliverable_folder_id');
        if ($id = $this->option('support')) {
            $query->where('id', $id);
        }
        $supports = $query->orderBy('id')->get();

        $this->info("Found {$supports->count()} support(s) with a stored folder.");
        $this->newLine();

        $renamed = 0;
        $ok      = 0;
        $skipped = 0;

        foreach ($supports as $support) {
            $tag          = "#{$support->id} {$support->name}";
            $customerName = $support->customerDeliverableFolderName();
            if ($customerName === null) {
                $this->warn("  [{$tag}] skip: client data incomplete.");
                $skipped++;
                continue;
            }

            $desired = $support->supportDeliverableFolderName();
            $folderId = $support->onedrive_deliverable_folder_id;

            // Find the current name of the stored folder among the customer's children.
            try {
                $children = $od->listFolderChildrenByPath("{$root}/{$customerName}");
            } catch (\Throwable $e) {
                $this->warn("  [{$tag}] skip: cannot list '{$customerName}': {$e->getMessage()}");
                $skipped++;
                continue;
            }

            $current = collect($children)->first(fn ($c) => $c['id'] === $folderId);
            if (!$current) {
                $this->warn("  [{$tag}] skip: stored folder {$folderId} not found under '{$customerName}' (stale?).");
                $skipped++;
                continue;
            }

            if ($current['name'] === $desired) {
                $this->line("  [{$tag}] already '{$desired}' — ok.");
                $ok++;
                continue;
            }

            $this->line("  [{$tag}] '{$current['name']}' -> '{$desired}'");

            if (!$execute) {
                $renamed++;
                continue;
            }

            try {
                $od->renameItem($folderId, $desired);
                $renamed++;
            } catch (\Throwable $e) {
                $this->warn("    rename failed: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->newLine();
        $this->info("Done. renamed={$renamed} already_ok={$ok} skipped={$skipped}");

        return self::SUCCESS;
    }
}
