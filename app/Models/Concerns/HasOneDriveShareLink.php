<?php

namespace App\Models\Concerns;

use App\Services\OneDriveService;
use Illuminate\Support\Carbon;

/**
 * Dipakai model yang menyimpan share link folder OneDrive
 * (DeliveryProject, DeliverySupport, Ticket).
 *
 * Tujuannya satu: UI dan job pemeriksa bisa tahu apakah link yang tersimpan
 * benar-benar dapat dibuka orang luar, tanpa harus memanggil Graph tiap render.
 */
trait HasOneDriveShareLink
{
    /** Ambang "akan kedaluwarsa" — link dianggap perlu diperbarui. */
    public static int $shareLinkExpiryWarningDays = 7;

    /**
     * Simpan hasil OneDriveService::createShareLink() ke kolom metadata.
     */
    public function applyOneDriveShareLink(array $meta): void
    {
        $this->update([
            'onedrive_folder_url'      => $meta['url'],
            'onedrive_link_scope'      => $meta['scope'],
            'onedrive_link_expires_at' => $meta['expires_at'],
            'onedrive_link_checked_at' => now(),
        ]);
    }

    /** Link benar-benar publik (bisa dibuka tanpa akun Eclectic). */
    public function getOnedriveLinkIsPublicAttribute(): bool
    {
        return $this->onedrive_link_scope === 'anonymous'
            && OneDriveService::isShareLinkUrl($this->onedrive_folder_url)
            && !$this->onedrive_link_is_expired;
    }

    public function getOnedriveLinkIsExpiredAttribute(): bool
    {
        $expiry = $this->onedrive_link_expires_at;

        return $expiry instanceof Carbon && $expiry->isPast();
    }

    public function getOnedriveLinkExpiresSoonAttribute(): bool
    {
        $expiry = $this->onedrive_link_expires_at;

        return $expiry instanceof Carbon
            && !$expiry->isPast()
            && $expiry->lte(now()->addDays(static::$shareLinkExpiryWarningDays));
    }

    /**
     * Pesan peringatan siap-tampil, atau null bila link sehat.
     * Sengaja bahasa Inggris agar konsisten dengan halaman Delivery.
     */
    public function getOnedriveLinkWarningAttribute(): ?string
    {
        if (!$this->onedrive_folder_url) {
            return null;
        }

        if (!OneDriveService::isShareLinkUrl($this->onedrive_folder_url)) {
            return 'This is a direct OneDrive path, not a sharing link — external recipients will hit "Request access". Refresh the link.';
        }

        if ($this->onedrive_link_is_expired) {
            return 'The sharing link expired on ' . $this->onedrive_link_expires_at->format('d M Y') . '. Refresh the link before sharing it again.';
        }

        // Link lama (dibuat sebelum metadata dicatat) — jangan bikin panik, cukup diam.
        if ($this->onedrive_link_scope === null) {
            return null;
        }

        if ($this->onedrive_link_scope !== 'anonymous') {
            return 'This link only works for people inside Eclectic Consulting (scope: ' . $this->onedrive_link_scope . '). Customers cannot open it.';
        }

        if ($this->onedrive_link_expires_soon) {
            return 'The sharing link expires on ' . $this->onedrive_link_expires_at->format('d M Y') . '.';
        }

        return null;
    }

    /** Label pendek untuk badge di UI. */
    public function getOnedriveLinkScopeLabelAttribute(): ?string
    {
        if (!$this->onedrive_folder_url) {
            return null;
        }

        return match (true) {
            $this->onedrive_link_is_expired         => 'Link expired',
            $this->onedrive_link_scope === null      => 'Not verified',
            $this->onedrive_link_scope === 'anonymous' => 'Anyone with the link',
            $this->onedrive_link_scope === 'organization' => 'Eclectic only',
            default                                  => ucfirst($this->onedrive_link_scope),
        };
    }
}
