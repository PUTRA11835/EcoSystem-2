<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketDeliverable;
use App\Models\TicketMessage;
use App\Services\OneDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TicketDeliverableController extends Controller
{
    private const DOC_TYPES = ['IR', 'RCA', 'CR Form', 'FSD', 'TD', 'UAT', 'MOM', 'BAST', 'Other'];

    /**
     * GET /api/tickets/{id}/deliverables
     */
    public function index($ticketId)
    {
        $user = session('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $deliverables = TicketDeliverable::where('ticket_id', $ticketId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($d) => $this->format($d));

        return response()->json([
            'success'      => true,
            'data'         => $deliverables,
            'has_folder'   => !empty($ticket->onedrive_folder_id),
            'folder_url'   => $ticket->onedrive_folder_url,
        ]);
    }

    /**
     * POST /api/tickets/{id}/deliverables
     * Multipart form: doc_type, body_text (optional), file (optional)
     */
    public function store(Request $request, $ticketId)
    {
        $user = session('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'doc_type'  => ['required', 'string', 'in:' . implode(',', self::DOC_TYPES)],
            'body_text' => ['nullable', 'string', 'max:1000'],
            'file'      => ['nullable', 'file', 'max:20480'], // 20 MB max
        ]);

        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $fileId   = null;
        $fileUrl  = null;
        $fileName = null;

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            if (empty($ticket->onedrive_folder_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket does not have a OneDrive folder yet. Please generate the folder first.',
                ], 422);
            }

            $uploadedFile = $request->file('file');
            $fileName     = $uploadedFile->getClientOriginalName();
            $mimeType     = $uploadedFile->getMimeType() ?? 'application/octet-stream';
            $fileContent  = file_get_contents($uploadedFile->getRealPath());

            try {
                $oneDrive = new OneDriveService();

                // Resolve target folder: gunakan sub-folder "Deliverable" jika tersedia.
                // Untuk tiket lama yang belum punya sub-folder, buat sekarang dan simpan ID-nya.
                $targetFolderId = $ticket->onedrive_deliverable_folder_id;
                if (!$targetFolderId) {
                    $targetFolderId = $oneDrive->createSubFolder($ticket->onedrive_folder_id, 'Deliverable');
                    $ticket->update(['onedrive_deliverable_folder_id' => $targetFolderId]);
                }

                $result  = $oneDrive->uploadFile($targetFolderId, $fileName, $fileContent, $mimeType);
                $fileId  = $result['id'];
                $fileUrl = $result['webUrl'] ?? $result['downloadUrl'] ?? null;
            } catch (\Throwable $e) {
                Log::error('Deliverable upload to OneDrive failed', [
                    'ticket_id' => $ticketId,
                    'error'     => $e->getMessage(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file to OneDrive: ' . $e->getMessage(),
                ], 500);
            }
        }

        $deliverable = TicketDeliverable::create([
            'ticket_id'         => $ticketId,
            'doc_type'          => $request->doc_type,
            'body_text'         => $request->body_text,
            'file_name'         => $fileName,
            'onedrive_file_id'  => $fileId,
            'onedrive_file_url' => $fileUrl,
            'status'            => 'No Send',
            'uploaded_by'       => $user['id'] ?? null,
            'upload_date'       => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $this->format($deliverable),
        ]);
    }

    /**
     * PATCH /api/tickets/{ticketId}/deliverables/{delivId}/send
     * Mark a deliverable as "Sended", add to chat, and email the customer.
     */
    public function send($ticketId, $delivId)
    {
        $user = session('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $deliverable = TicketDeliverable::where('id', $delivId)
            ->where('ticket_id', $ticketId)
            ->firstOrFail();

        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $deliverable->update(['status' => 'Sended']);

        // Build message body
        $senderName  = $user['name'] ?? $user['email'] ?? 'Helpdesk';
        $senderEmail = $user['email'] ?? null;

        $plainMsg = 'Deliverable document sent: ' . $deliverable->doc_type;
        if ($deliverable->body_text) $plainMsg .= ' — ' . $deliverable->body_text;
        if ($deliverable->file_name) $plainMsg .= ' (' . $deliverable->file_name . ')';

        $htmlMsg = '<p style="margin:0 0 8px"><strong>Deliverable Document</strong></p>'
            . '<table style="border-collapse:collapse;font-size:13px">'
            . '<tr><td style="padding:3px 10px 3px 0;color:#6b7280;white-space:nowrap">Doc Type</td>'
            . '<td style="padding:3px 0;font-weight:600">' . htmlspecialchars($deliverable->doc_type) . '</td></tr>';

        if ($deliverable->body_text) {
            $htmlMsg .= '<tr><td style="padding:3px 10px 3px 0;color:#6b7280">Description</td>'
                . '<td style="padding:3px 0">' . nl2br(htmlspecialchars($deliverable->body_text)) . '</td></tr>';
        }

        if ($deliverable->file_name) {
            $fileCell = $deliverable->onedrive_file_url
                ? '<a href="' . htmlspecialchars($deliverable->onedrive_file_url) . '" target="_blank" style="color:#2563eb">' . htmlspecialchars($deliverable->file_name) . '</a>'
                : htmlspecialchars($deliverable->file_name);
            $htmlMsg .= '<tr><td style="padding:3px 10px 3px 0;color:#6b7280">File</td>'
                . '<td style="padding:3px 0">' . $fileCell . '</td></tr>';
        }
        $htmlMsg .= '</table>';

        // Create visible ticket_message (customer can see it)
        $channel    = ($ticket->email_thread_id || $ticket->channel === 'email') ? 'email' : 'web';
        $ticketMsg  = TicketMessage::create([
            'ticket_id'           => $ticket->ticket_id,
            'sender_type'         => 'employee',
            'sender_id'           => $user['id'] ?? null,
            'sender_name'         => $senderName,
            'sender_email'        => $senderEmail,
            'message'             => $plainMsg,
            'message_html'        => $htmlMsg,
            'channel'             => $channel,
            'is_internal_note'    => false,
            'is_read_by_customer' => false,
        ]);

        $ticket->update([
            'last_message_at'     => now(),
            'last_agent_reply_at' => now(),
        ]);

        // Send email to customer whenever they have an email address
        try {
            $customerEmail = $ticket->customer?->email
                ?? Customer::find($ticket->customer_id)?->email;

            if ($customerEmail) {
                $subject = 'Ticket #' . ($ticket->ticket_number ?? $ticket->ticket_id)
                    . ': ' . mb_substr($ticket->description ?? '', 0, 80);

                $inReplyTo = TicketMessage::where('ticket_id', $ticket->ticket_id)
                    ->where('channel', 'email')
                    ->whereNotNull('email_message_id')
                    ->where('id', '!=', $ticketMsg->id)
                    ->orderByDesc('created_at')
                    ->value('email_message_id');

                $ccList = [];
                if (!empty($ticket->cc_emails)) {
                    $ccList = is_array($ticket->cc_emails)
                        ? $ticket->cc_emails
                        : (json_decode($ticket->cc_emails, true) ?? []);
                }

                $emailController = new EmailController();
                $result = $emailController->sendTicketReply(
                    $customerEmail,
                    $subject,
                    $htmlMsg,
                    $inReplyTo,
                    [],
                    $ccList,
                    true,
                    $ticket->email_thread_id
                );

                if (!empty($result['internet_message_id'])) {
                    $ticketMsg->update(['email_message_id' => $result['internet_message_id']]);
                }
                if (!empty($result['conversation_id']) && $result['conversation_id'] !== $ticket->email_thread_id) {
                    $ticket->update(['email_thread_id' => $result['conversation_id']]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TicketDeliverableController@send: email failed', [
                'error'     => $e->getMessage(),
                'ticket_id' => $ticketId,
                'deliv_id'  => $delivId,
            ]);
        }

        return response()->json(['success' => true, 'data' => $this->format($deliverable->fresh())]);
    }

    /**
     * DELETE /api/tickets/{ticketId}/deliverables/{delivId}
     */
    public function destroy($ticketId, $delivId)
    {
        $user = session('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $deliverable = TicketDeliverable::where('id', $delivId)
            ->where('ticket_id', $ticketId)
            ->firstOrFail();

        // Delete from OneDrive if uploaded
        if ($deliverable->onedrive_file_id) {
            try {
                (new OneDriveService())->deleteFolder($deliverable->onedrive_file_id);
            } catch (\Throwable $e) {
                Log::warning('Could not delete deliverable file from OneDrive', [
                    'file_id' => $deliverable->onedrive_file_id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        $deliverable->delete();

        return response()->json(['success' => true]);
    }

    private function format(TicketDeliverable $d): array
    {
        return [
            'id'           => $d->id,
            'doc_type'     => $d->doc_type,
            'body_text'    => $d->body_text,
            'file_name'    => $d->file_name,
            'file_url'     => $d->onedrive_file_url,
            'status'       => $d->status,
            'upload_date'  => $d->upload_date?->format('d/m/Y'),
            'upload_time'  => $d->created_at?->setTimezone('Asia/Jakarta')->format('H:i'),
        ];
    }
}
