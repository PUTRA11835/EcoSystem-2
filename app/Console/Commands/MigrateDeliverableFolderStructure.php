<?php

namespace App\Console\Commands;

use App\Models\DeliverySupport;
use App\Services\OneDriveService;
use Illuminate\Console\Command;

/**
 * Migrate Customer Deliverable folders from the OLD nested layout to the NEW layout.
 *
 *   OLD:  {root}/{customer}/{user-subfolder}/                     (stored on delivery_support)
 *         {root}/{customer}/{user-subfolder}/TICKETING/{ticket}/DELIVERABLE/...
 *
 *   NEW:  {root}/{customer}/{NNN support}/{user-subfolder}/       (support folder is now stored)
 *         {root}/{customer}/TICKETING/{ticket}/Deliverable/...    (sibling of support folders)
 *
 * Item IDs are stable across moves, so cached ticket folder/file IDs stay valid.
 * Idempotent: re-running skips supports already pointing at their support folder.
 *
 * Dry-run by default. Pass --execute to apply changes to OneDrive.
 */
class MigrateDeliverableFolderStructure extends Command
{
    protected $signature = 'onedrive:migrate-deliverable-structure
                            {--execute : Apply changes (omit for a dry-run)}
                            {--support= : Limit to a single delivery_support id}';

    protected $description = 'Migrate Customer Deliverable folders to the per-support + customer-level TICKETING layout.';

    private OneDriveService $od;
    private bool $execute = false;

    public function handle(): int
    {
        $this->execute = (bool) $this->option('execute');
        $this->od      = new OneDriveService();
        $root          = config('services.microsoft_graph.customer_deliverable_path', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE');

        $this->line($this->execute
            ? '<fg=yellow;options=bold>EXECUTE mode — changes WILL be applied to OneDrive.</>'
            : '<fg=cyan;options=bold>DRY-RUN mode — no changes made. Re-run with --execute to apply.</>');

        $query = DeliverySupport::whereNotNull('onedrive_deliverable_folder_id');
        if ($id = $this->option('support')) {
            $query->where('id', $id);
        }
        $supports = $query->orderBy('id')->get();

        $this->info("Found {$supports->count()} support(s) with a stored deliverable folder.");
        $this->newLine();

        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($supports as $support) {
            try {
                $this->migrateSupport($support, $root) ? $migrated++ : $skipped++;
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  [#{$support->id}] FATAL: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done. migrated={$migrated} skipped={$skipped} errors={$errors}");

        return self::SUCCESS;
    }

    private function migrateSupport(DeliverySupport $support, string $root): bool
    {
        $tag          = "#{$support->id} {$support->name}";
        $customerName = $support->customerDeliverableFolderName();

        if ($customerName === null) {
            $this->warn("  [{$tag}] skip: client / basic data incomplete.");
            return false;
        }

        $supportFolderName = $support->supportDeliverableFolderName();
        $oldSub            = $support->onedrive_deliverable_folder_id;

        $this->line("  [{$tag}] {$customerName} -> '{$supportFolderName}'");

        // Ensure customer + support folders exist (returns null in dry-run when absent).
        $customerFolderId = $this->ensureFolderInPath($root, $customerName);
        $supportFolderId  = $this->ensureSubFolder($customerFolderId, $supportFolderName, "{$root}/{$customerName}");

        // Already migrated → nothing to move.
        if ($oldSub && $supportFolderId && $oldSub === $supportFolderId) {
            $this->line('    already points to the support folder — nothing to do.');
            return false;
        }

        if ($oldSub) {
            $children = $this->safeListById($oldSub);

            // Pull any nested TICKETING up to the customer level.
            $oldTicketing = collect($children)->first(fn ($c) => mb_strtolower($c['name']) === 'ticketing');
            if ($oldTicketing) {
                $custTicketingId = $this->ensureSubFolder($customerFolderId, 'TICKETING', "{$root}/{$customerName}");
                foreach ($this->safeListById($oldTicketing['id']) as $ticketFolder) {
                    $this->moveTicketFolder($ticketFolder, $custTicketingId);
                }
                $this->deleteFolder($oldTicketing['id'], 'old nested TICKETING (now empty)');
            }

            // Move the old user sub-folder under the new support folder.
            $this->move($oldSub, $supportFolderId, 'old sub-folder -> support folder');
        }

        $this->repoint($support, $supportFolderId);

        return true;
    }

    // ── OneDrive helpers (dry-run aware, fault-tolerant) ────────────────────────

    private function ensureFolderInPath(string $parentPath, string $name): ?string
    {
        if ($this->execute) {
            return $this->od->findOrCreateFolderInPath($parentPath, $name);
        }

        $existing = collect($this->od->listFolderChildrenByPath($parentPath))
            ->first(fn ($c) => mb_strtolower($c['name']) === mb_strtolower($name));

        if ($existing) {
            return $existing['id'];
        }

        $this->line("    [dry] would create folder '{$name}' in '{$parentPath}'");
        return null;
    }

    private function ensureSubFolder(?string $parentId, string $name, string $displayParent): ?string
    {
        if ($this->execute) {
            return $parentId ? $this->od->findOrCreateSubFolderById($parentId, $name) : null;
        }

        if ($parentId) {
            $existing = collect($this->od->listSubFoldersByParentId($parentId))
                ->first(fn ($c) => mb_strtolower($c['name']) === mb_strtolower($name));
            if ($existing) {
                return $existing['id'];
            }
        }

        $this->line("    [dry] would create sub-folder '{$name}' in '{$displayParent}'");
        return null;
    }

    private function move(string $itemId, ?string $newParentId, string $why): void
    {
        if (!$this->execute) {
            $this->line("    [dry] would move {$itemId} -> " . ($newParentId ?: '(new support folder)') . " ({$why})");
            return;
        }
        if (!$newParentId) {
            $this->warn("    skip move: target parent missing ({$why})");
            return;
        }
        try {
            $this->od->moveItem($itemId, $newParentId);
            $this->line("    moved {$itemId} -> {$newParentId} ({$why})");
        } catch (\Throwable $e) {
            $this->warn("    move failed ({$why}): {$e->getMessage()}");
        }
    }

    private function moveTicketFolder(array $ticketFolder, ?string $custTicketingId): void
    {
        $name = $ticketFolder['name'];

        if (!$this->execute) {
            $this->line("    [dry] would move ticket folder '{$name}' -> customer TICKETING");
            return;
        }
        if (!$custTicketingId) {
            $this->warn("    skip ticket '{$name}': customer TICKETING unavailable");
            return;
        }

        // Avoid clobbering an existing same-named ticket folder at the destination.
        $conflict = collect($this->od->listSubFoldersByParentId($custTicketingId))
            ->first(fn ($c) => mb_strtolower($c['name']) === mb_strtolower($name));
        if ($conflict) {
            $this->warn("    conflict: ticket '{$name}' already at customer TICKETING — left in place for manual review.");
            return;
        }

        try {
            $this->od->moveItem($ticketFolder['id'], $custTicketingId);
            $this->line("    moved ticket folder '{$name}' -> customer TICKETING");
        } catch (\Throwable $e) {
            $this->warn("    ticket move failed '{$name}': {$e->getMessage()}");
        }
    }

    private function deleteFolder(string $id, string $why): void
    {
        if (!$this->execute) {
            $this->line("    [dry] would delete {$id} ({$why})");
            return;
        }
        try {
            $this->od->deleteFolder($id);
            $this->line("    deleted {$id} ({$why})");
        } catch (\Throwable $e) {
            $this->warn("    delete failed ({$why}): {$e->getMessage()}");
        }
    }

    private function repoint(DeliverySupport $support, ?string $supportFolderId): void
    {
        if (!$this->execute) {
            $this->line('    [dry] would repoint onedrive_deliverable_folder_id to the support folder + refresh share link');
            return;
        }
        if (!$supportFolderId) {
            $this->warn('    skip repoint: support folder id unknown');
            return;
        }

        $url = null;
        try {
            $url = $this->od->createAnonymousLink($supportFolderId);
        } catch (\Throwable $e) {
            $this->warn("    share link failed: {$e->getMessage()}");
        }

        $support->update([
            'onedrive_deliverable_folder_id'  => $supportFolderId,
            'onedrive_deliverable_folder_url' => $url,
        ]);
        $this->line("    DB repointed to support folder {$supportFolderId}");
    }

    /**
     * List child folders by parent id; returns [] on any error (stale/missing/404).
     */
    private function safeListById(string $id): array
    {
        try {
            return $this->od->listSubFoldersByParentId($id);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
