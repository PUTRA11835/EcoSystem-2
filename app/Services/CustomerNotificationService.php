<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Kirim notifikasi ke customer Jarvies.
 * Dipanggil dari EcoSystem — ditulis ke shared DB sehingga Jarvies bisa membacanya.
 */
class CustomerNotificationService
{
    const TYPE_REPLY          = 'ticket_reply';
    const TYPE_STATUS_CHANGED = 'ticket_status_changed';
    const TYPE_CLOSED         = 'ticket_closed';
    const TYPE_ASSIGNED       = 'ticket_assigned';
    const TYPE_REJECTED       = 'ticket_rejected';

    public static function notify(
        int    $customerId,
        string $type,
        ?int   $ticketId,
        string $fromName,
        string $preview,
        string $link
    ): ?Notification {
        if ($customerId <= 0) {
            return null;
        }

        try {
            return Notification::create([
                'customer_id' => $customerId,
                'type'        => $type,
                'ticket_id'   => $ticketId,
                'from_name'   => $fromName,
                'preview'     => $preview,
                'link'        => $link,
                'is_read'     => false,
            ]);
        } catch (\Exception $e) {
            Log::warning('CustomerNotificationService: failed to create notification', [
                'customer_id' => $customerId,
                'ticket_id'   => $ticketId,
                'type'        => $type,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }
}
