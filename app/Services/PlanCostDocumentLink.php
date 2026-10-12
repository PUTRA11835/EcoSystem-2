<?php

namespace App\Services;

use App\Models\DeliveryProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pastikan supporting document expense (Plan Cost — Delivery Project & Support)
 * tersimpan sebagai share link ANONIM ("Anyone with the link").
 *
 * Baris yang di-upload sebelum fix share link menyimpan `webUrl` hasil upload:
 * path SharePoint langsung (…/personal/support_eclectic_co_id/Documents/…) yang
 * hanya bisa dibuka akun pemilik file — akun Eclectic lain pun kena
 * "Request access". Kelas ini membuat ulang link-nya.
 */
class PlanCostDocumentLink
{
    /** Jeda sebelum mencoba lagi baris yang gagal diperbaiki saat render. */
    private const FAILURE_COOLDOWN_MINUTES = 60;

    public function __construct(private ?OneDriveService $oneDrive = null)
    {
    }

    public static function needsRepair(Model $item): bool
    {
        return $item->document_url
            && (!$item->document_file_id || !OneDriveService::isShareLinkUrl($item->document_url));
    }

    /**
     * Perbaiki tanpa pernah menggagalkan request (dipakai saat daftar expense dibuka).
     * Kegagalan dicatat dan tidak dicoba ulang selama cooldown supaya modal tetap cepat.
     */
    public function ensure(Model $item, ?string $ownerFolderId): void
    {
        if (!self::needsRepair($item)) {
            return;
        }

        $cacheKey = 'plan-cost-link-repair-failed:' . $item->getTable() . ':' . $item->getKey();
        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            $this->repair($item, $ownerFolderId);
        } catch (\Throwable $e) {
            Cache::put($cacheKey, true, now()->addMinutes(self::FAILURE_COOLDOWN_MINUTES));
            Log::warning('Plan Cost document link repair failed', [
                'table' => $item->getTable(),
                'id'    => $item->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Buat ulang share link anonim dan simpan ke item. Melempar exception bila gagal.
     *
     * @return array hasil OneDriveService::createShareLink()
     */
    public function repair(Model $item, ?string $ownerFolderId): array
    {
        $oneDrive = $this->oneDrive ??= new OneDriveService();
        $fileId   = $item->document_file_id ?: $this->locateLegacyFile($item, $ownerFolderId);

        $link = $oneDrive->createShareLink($fileId, 'view');

        if ($link['scope'] !== 'anonymous') {
            Log::warning('Plan Cost document share link is not anonymous', [
                'table'   => $item->getTable(),
                'id'      => $item->getKey(),
                'file_id' => $fileId,
                'scope'   => $link['scope'],
            ]);
        }

        // Perbaikan link oleh sistem bukan aktivitas user → jangan geser
        // "Last Update Date" project; updated_at baris juga dibiarkan.
        DeliveryProject::withoutActivityTracking(function () use ($item, $fileId, $link) {
            $item->timestamps = false;
            $item->update([
                'document_file_id' => $fileId,
                'document_url'     => $link['url'],
            ]);
            $item->timestamps = true;
        });

        return $link;
    }

    /** Baris lama belum menyimpan item ID → cari file bernama sama di folder "Plan Cost". */
    private function locateLegacyFile(Model $item, ?string $ownerFolderId): string
    {
        if (!$ownerFolderId) {
            throw new \RuntimeException('owner has no OneDrive folder');
        }

        $planCost = collect($this->oneDrive->listSubFoldersByParentId($ownerFolderId))
            ->first(fn($f) => mb_strtolower($f['name']) === 'plan cost');

        if (!$planCost) {
            throw new \RuntimeException('"Plan Cost" folder not found');
        }

        $file = $this->oneDrive->findFileInFolderByName($planCost['id'], (string) $item->document_name);

        if (!$file) {
            throw new \RuntimeException('file not found in "Plan Cost" folder');
        }

        return $file['id'];
    }
}
