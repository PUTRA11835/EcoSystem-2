<?php

namespace App\Console\Commands;

use App\Models\DeliveryProject;
use App\Models\DeliverySupport;
use App\Models\Ticket;
use App\Models\TicketDeliverable;
use App\Services\OneDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Periksa kesehatan seluruh share link OneDrive yang tersimpan di EcoSystem.
 *
 * Latar belakang: link folder disimpan sekali saat folder dibuat, lalu tidak
 * pernah diperiksa lagi. Padahal link bisa "mati" tanpa jejak di aplikasi:
 *   - kebijakan tenant menurunkan scope anonymous → organization (internal saja),
 *   - kebijakan "Anyone links expire in N days" menghanguskan link,
 *   - izin diubah/dicabut manual dari UI OneDrive,
 *   - yang tersimpan ternyata webUrl langsung, bukan share link,
 *   - folder-nya sudah dihapus.
 *
 * Semua kondisi itu muncul di sisi penerima sebagai "Request access" / minta login.
 * Command ini mendeteksinya (dan dengan --fix membuat ulang link-nya).
 *
 *   php artisan onedrive:audit-links                 # laporan saja
 *   php artisan onedrive:audit-links --fix           # sekalian perbaiki
 *   php artisan onedrive:audit-links --target=projects --offline
 */
class AuditOneDriveShareLinks extends Command
{
    protected $signature = 'onedrive:audit-links
                            {--fix : Recreate links that are broken or not publicly accessible}
                            {--target=all : all|projects|supports|tickets|deliverables|expenses}
                            {--offline : Judge from stored metadata only (no Microsoft Graph calls)}
                            {--limit=0 : Only inspect the first N rows per target (0 = no limit)}';

    protected $description = 'Audit (and optionally repair) OneDrive share links so customers never hit "Request access".';

    private OneDriveService $oneDrive;
    private bool $fix;
    private bool $offline;

    /** @var array<string,int> */
    private array $totals = ['checked' => 0, 'healthy' => 0, 'problem' => 0, 'fixed' => 0, 'failed' => 0];

    /** @var array<int,array<int,string>> baris untuk tabel ringkasan masalah */
    private array $problems = [];

    public function handle(): int
    {
        $this->oneDrive = new OneDriveService();
        $this->fix      = (bool) $this->option('fix');
        $this->offline  = (bool) $this->option('offline');
        $target         = (string) $this->option('target');
        $limit          = (int) $this->option('limit');

        if (!in_array($target, ['all', 'projects', 'supports', 'tickets', 'deliverables', 'expenses'], true)) {
            $this->error("Unknown --target={$target}. Use: all, projects, supports, tickets, deliverables, expenses.");
            return self::FAILURE;
        }

        $this->line($this->fix ? 'Mode: AUDIT + FIX' : 'Mode: AUDIT ONLY (add --fix to repair)');
        $this->newLine();

        if ($target === 'all' || $target === 'projects') {
            $this->auditFolders(
                'Delivery Project',
                DeliveryProject::whereNotNull('onedrive_folder_id')->orderBy('id')->when($limit > 0, fn($q) => $q->limit($limit))->get(),
                fn(DeliveryProject $p) => '#' . $p->id . ' ' . $p->name
            );
        }

        if ($target === 'all' || $target === 'supports') {
            $this->auditFolders(
                'Delivery Support',
                DeliverySupport::whereNotNull('onedrive_folder_id')->orderBy('id')->when($limit > 0, fn($q) => $q->limit($limit))->get(),
                fn(DeliverySupport $s) => '#' . $s->id . ' ' . $s->name
            );
        }

        if ($target === 'all' || $target === 'tickets') {
            $this->auditFolders(
                'Ticket',
                Ticket::whereNotNull('onedrive_folder_id')->orderBy('ticket_id')->when($limit > 0, fn($q) => $q->limit($limit))->get(),
                fn(Ticket $t) => $t->ticket_number ?: ('Ticket-' . $t->ticket_id)
            );
        }

        if ($target === 'all' || $target === 'deliverables') {
            $this->auditDeliverableFiles($limit);
        }

        if ($target === 'all' || $target === 'expenses') {
            $this->auditExpenseDocuments($limit);
        }

        return $this->report();
    }

    /**
     * Audit share link folder untuk model yang memakai HasOneDriveShareLink.
     *
     * @param  \Illuminate\Support\Collection<int,\Illuminate\Database\Eloquent\Model>  $rows
     */
    private function auditFolders(string $label, $rows, callable $describe): void
    {
        $this->components->info("{$label} ({$rows->count()} folder)");

        foreach ($rows as $row) {
            $this->totals['checked']++;
            $name   = $describe($row);
            $issues = [];

            if (!$row->onedrive_folder_url) {
                $issues[] = 'no share link stored';
            } elseif (!OneDriveService::isShareLinkUrl($row->onedrive_folder_url)) {
                $issues[] = 'stored URL is a direct path, not a share link';
            }

            if (!$this->offline) {
                $issues = array_merge($issues, $this->verifyAgainstGraph($row, $name));
            } else {
                if ($row->onedrive_link_is_expired) {
                    $issues[] = 'link expired ' . $row->onedrive_link_expires_at->format('d M Y');
                } elseif ($row->onedrive_link_scope !== null && $row->onedrive_link_scope !== 'anonymous') {
                    $issues[] = 'scope is ' . $row->onedrive_link_scope . ' (internal only)';
                } elseif ($row->onedrive_link_scope === null) {
                    $issues[] = 'never verified (legacy link)';
                }
            }

            // Folder hilang → tidak bisa diperbaiki dengan membuat link baru.
            if (in_array('folder no longer exists in OneDrive', $issues, true)) {
                $this->recordProblem($label, $name, $issues, 'manual');
                continue;
            }

            if (!$issues) {
                $this->totals['healthy']++;
                continue;
            }

            $outcome = 'reported';
            if ($this->fix) {
                $outcome = $this->repairFolderLink($row, $label, $name);
            }

            $this->recordProblem($label, $name, $issues, $outcome);
        }
    }

    /**
     * Tanya Graph: izin apa yang sebenarnya masih menempel di folder ini?
     * Sekalian menyegarkan kolom metadata supaya UI menampilkan status terkini.
     *
     * @return array<int,string> daftar masalah
     */
    private function verifyAgainstGraph($row, string $name): array
    {
        try {
            if (!$this->oneDrive->itemExists($row->onedrive_folder_id)) {
                return ['folder no longer exists in OneDrive'];
            }

            $permissions = $this->oneDrive->listItemPermissions($row->onedrive_folder_id);
        } catch (\Throwable $e) {
            Log::warning('onedrive:audit-links — Graph check failed', [
                'item'  => $name,
                'error' => $e->getMessage(),
            ]);
            return ['could not verify via Graph: ' . $e->getMessage()];
        }

        $stored = collect($permissions)->firstWhere('url', $row->onedrive_folder_url);

        if (!$stored) {
            return ['the stored link no longer exists on the folder (revoked or replaced)'];
        }

        // Segarkan metadata dari kondisi sebenarnya di OneDrive.
        $row->update([
            'onedrive_link_scope'      => $stored['scope'],
            'onedrive_link_expires_at' => $stored['expires_at'],
            'onedrive_link_checked_at' => now(),
        ]);

        $issues = [];
        if ($stored['expires_at'] && $stored['expires_at']->isPast()) {
            $issues[] = 'link expired ' . $stored['expires_at']->format('d M Y');
        } elseif ($stored['expires_at'] && $stored['expires_at']->lte(now()->addDays($row::$shareLinkExpiryWarningDays))) {
            $issues[] = 'link expires ' . $stored['expires_at']->format('d M Y');
        }

        if ($stored['scope'] !== 'anonymous') {
            $issues[] = 'scope is ' . ($stored['scope'] ?: 'unknown') . ' (internal only)';
        }

        return $issues;
    }

    private function repairFolderLink($row, string $label, string $name): string
    {
        try {
            $link = $this->oneDrive->createShareLink($row->onedrive_folder_id, 'edit');
            $row->applyOneDriveShareLink($link);
        } catch (\Throwable $e) {
            $this->totals['failed']++;
            Log::error('onedrive:audit-links — repair failed', [
                'type'  => $label,
                'item'  => $name,
                'error' => $e->getMessage(),
            ]);
            return 'FIX FAILED: ' . $e->getMessage();
        }

        $this->totals['fixed']++;

        // Link baru pun bisa tetap non-anonymous kalau kebijakan tenant memblokirnya —
        // itu bukan bug aplikasi dan harus ditindaklanjuti admin M365.
        return $link['scope'] === 'anonymous'
            ? 'fixed (anyone with the link)'
            : 'fixed but still ' . $link['scope'] . ' — check M365 sharing policy';
    }

    /**
     * Deliverable file link (dikirim ke customer lewat email) tidak punya kolom
     * metadata; cukup pastikan bentuknya share link dan masih hidup.
     */
    private function auditDeliverableFiles(int $limit): void
    {
        $rows = TicketDeliverable::whereNotNull('onedrive_file_id')
            ->orderBy('id')
            ->when($limit > 0, fn($q) => $q->limit($limit))
            ->get();

        $this->components->info("Ticket Deliverable file ({$rows->count()} file)");

        foreach ($rows as $deliverable) {
            $this->totals['checked']++;
            $name   = '#' . $deliverable->id . ' ' . ($deliverable->file_name ?: '(no name)');
            $issues = [];

            if (!OneDriveService::isShareLinkUrl($deliverable->onedrive_file_url)) {
                $issues[] = 'stored URL is a direct path, not a share link';
            } elseif (!$this->offline) {
                try {
                    $permissions = $this->oneDrive->listItemPermissions($deliverable->onedrive_file_id);
                    $stored      = collect($permissions)->firstWhere('url', $deliverable->onedrive_file_url);

                    if (!$stored) {
                        $issues[] = 'the stored link no longer exists on the file (revoked or replaced)';
                    } else {
                        if ($stored['scope'] !== 'anonymous') {
                            $issues[] = 'scope is ' . ($stored['scope'] ?: 'unknown') . ' (internal only)';
                        }
                        if ($stored['expires_at'] && $stored['expires_at']->isPast()) {
                            $issues[] = 'link expired ' . $stored['expires_at']->format('d M Y');
                        }
                    }
                } catch (\Throwable $e) {
                    $issues[] = 'could not verify via Graph: ' . $e->getMessage();
                }
            }

            if (!$issues) {
                $this->totals['healthy']++;
                continue;
            }

            $outcome = 'reported';
            if ($this->fix) {
                try {
                    $link = $this->oneDrive->createShareLink($deliverable->onedrive_file_id, 'view');
                    $deliverable->update(['onedrive_file_url' => $link['url']]);
                    $this->totals['fixed']++;
                    $outcome = $link['scope'] === 'anonymous'
                        ? 'fixed (anyone with the link)'
                        : 'fixed but still ' . $link['scope'] . ' — check M365 sharing policy';
                } catch (\Throwable $e) {
                    $this->totals['failed']++;
                    $outcome = 'FIX FAILED: ' . $e->getMessage();
                }
            }

            $this->recordProblem('Deliverable', $name, $issues, $outcome);
        }
    }

    /**
     * Supporting document expense (Plan Cost) di Delivery Project & Support.
     *
     * Baris lama menyimpan `webUrl` hasil upload — path SharePoint langsung yang
     * selalu meminta login / "Request access". Baris lama juga belum menyimpan
     * item ID, jadi ID-nya dipulihkan dulu dengan mencari file bernama sama di
     * subfolder "Plan Cost" milik project/support tersebut.
     */
    private function auditExpenseDocuments(int $limit): void
    {
        $sources = [
            [
                'label'      => 'Project Expense',
                'rows'       => \App\Models\DeliveryProjectCostItem::whereNotNull('document_url')
                                    ->with('costItem.project')
                                    ->orderBy('id')
                                    ->when($limit > 0, fn($q) => $q->limit($limit))
                                    ->get(),
                'folder'     => fn($item) => $item->costItem?->project?->onedrive_folder_id,
            ],
            [
                'label'      => 'Support Expense',
                'rows'       => \App\Models\DeliverySupportCostItem::whereNotNull('document_url')
                                    ->with('costItem.support')
                                    ->orderBy('id')
                                    ->when($limit > 0, fn($q) => $q->limit($limit))
                                    ->get(),
                'folder'     => fn($item) => $item->costItem?->support?->onedrive_folder_id,
            ],
        ];

        foreach ($sources as $source) {
            $rows = $source['rows'];
            $this->components->info("{$source['label']} document ({$rows->count()} file)");

            foreach ($rows as $item) {
                $this->totals['checked']++;
                $name   = '#' . $item->id . ' ' . ($item->document_name ?: '(no name)');
                $issues = [];

                if (!OneDriveService::isShareLinkUrl($item->document_url)) {
                    $issues[] = 'stored URL is a direct path, not a share link';
                }
                if (!$item->document_file_id) {
                    $issues[] = 'no OneDrive item ID stored (cannot delete/replace the file)';
                }

                if (!$issues && !$this->offline) {
                    $issues = array_merge($issues, $this->verifyFileLink($item->document_file_id, $item->document_url));
                }

                if (!$issues) {
                    $this->totals['healthy']++;
                    continue;
                }

                $outcome = 'reported';
                if ($this->fix) {
                    $outcome = $this->repairExpenseDocument($item, ($source['folder'])($item), $name);
                }

                $this->recordProblem($source['label'], $name, $issues, $outcome);
            }
        }
    }

    /** @return array<int,string> */
    private function verifyFileLink(string $fileId, ?string $storedUrl): array
    {
        try {
            $stored = collect($this->oneDrive->listItemPermissions($fileId))->firstWhere('url', $storedUrl);
        } catch (\Throwable $e) {
            return ['could not verify via Graph: ' . $e->getMessage()];
        }

        if (!$stored) {
            return ['the stored link no longer exists on the file (revoked or replaced)'];
        }

        $issues = [];
        if ($stored['scope'] !== 'anonymous') {
            $issues[] = 'scope is ' . ($stored['scope'] ?: 'unknown') . ' (internal only)';
        }
        if ($stored['expires_at'] && $stored['expires_at']->isPast()) {
            $issues[] = 'link expired ' . $stored['expires_at']->format('d M Y');
        }

        return $issues;
    }

    private function repairExpenseDocument($item, ?string $parentFolderId, string $name): string
    {
        try {
            $fileId = $item->document_file_id;

            // Baris lama: item ID belum pernah disimpan → cari di folder "Plan Cost".
            if (!$fileId) {
                if (!$parentFolderId) {
                    $this->totals['failed']++;
                    return 'FIX FAILED: owner has no OneDrive folder';
                }

                $planCost = collect($this->oneDrive->listSubFoldersByParentId($parentFolderId))
                    ->first(fn($f) => mb_strtolower($f['name']) === 'plan cost');

                if (!$planCost) {
                    $this->totals['failed']++;
                    return 'FIX FAILED: "Plan Cost" folder not found';
                }

                $file = $this->oneDrive->findFileInFolderByName($planCost['id'], (string) $item->document_name);

                if (!$file) {
                    $this->totals['failed']++;
                    return 'FIX FAILED: file not found in "Plan Cost" folder';
                }

                $fileId = $file['id'];
            }

            $link = $this->oneDrive->createShareLink($fileId, 'view');

            $item->update([
                'document_file_id' => $fileId,
                'document_url'     => $link['url'],
            ]);
        } catch (\Throwable $e) {
            $this->totals['failed']++;
            Log::error('onedrive:audit-links — expense document repair failed', [
                'item'  => $name,
                'error' => $e->getMessage(),
            ]);
            return 'FIX FAILED: ' . $e->getMessage();
        }

        $this->totals['fixed']++;

        return $link['scope'] === 'anonymous'
            ? 'fixed (anyone with the link)'
            : 'fixed but still ' . $link['scope'] . ' — check M365 sharing policy';
    }

    /** @param array<int,string> $issues */
    private function recordProblem(string $type, string $name, array $issues, string $outcome): void
    {
        $this->totals['problem']++;
        $this->problems[] = [$type, mb_strimwidth($name, 0, 45, '…'), implode('; ', $issues), $outcome];
    }

    private function report(): int
    {
        $this->newLine();

        if ($this->problems) {
            $this->table(['Type', 'Item', 'Issue', 'Outcome'], $this->problems);
        } else {
            $this->components->info('All share links are healthy.');
        }

        $this->newLine();
        $this->line(sprintf(
            'Checked %d · healthy %d · problem %d · fixed %d · failed %d',
            $this->totals['checked'],
            $this->totals['healthy'],
            $this->totals['problem'],
            $this->totals['fixed'],
            $this->totals['failed']
        ));

        if ($this->problems && !$this->fix) {
            $this->comment('Run with --fix to recreate the broken links.');
        }

        // Sisa masalah setelah audit dicatat ke log supaya scheduler/monitoring bisa memantaunya.
        if ($this->totals['problem'] > 0) {
            Log::warning('onedrive:audit-links found unhealthy share links', $this->totals);
        }

        return self::SUCCESS;
    }
}
