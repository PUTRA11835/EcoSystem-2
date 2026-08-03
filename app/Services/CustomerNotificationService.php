<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Ticket;
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

        // Choke point: tiket internal EcoSystem (visible_to_customer = 0) dan tiket
        // yang di-hide tidak boleh memicu notifikasi ke customer — kalau lolos,
        // customer dapat notifikasi bell untuk tiket yang tidak bisa ia buka
        // (link-nya akan 403 di Jarvies).
        if ($ticketId) {
            $ticket = Ticket::select('visible_to_customer', 'is_hidden')->find($ticketId);
            if ($ticket && (!$ticket->visible_to_customer || $ticket->is_hidden)) {
                return null;
            }
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
