<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EmailController;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketMessageController extends Controller
{
    /**
     * Get messages for a ticket
     */
    public function index($ticketId)
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($ticketId);

            // Check access based on role
            $roleId = $sessionUser['role']['id'];

            // Build query for messages (eager load attachments)
            $query = TicketMessage::with(['attachments'])
                ->where('ticket_id', $ticketId)
                ->orderBy('created_at', 'asc');

            $messages = $query->get()->map(function ($message) {
                return [
                    'id'                  => $message->id,
                    'ticket_id'           => $message->ticket_id,
                    'sender_type'         => $message->sender_type,
                    'sender_id'           => $message->sender_id,
                    'sender_name'         => $message->sender_name,
                    'sender_email'        => $message->sender_email,
                    'message_body'        => $message->message,
                    'message_html'        => $message->message_html,
                    'message_type'        => $message->is_internal_note ? 'internal_note' : 'reply',
                    'channel'             => $message->channel ?? 'web',
                    'is_read_by_customer' => $message->is_read_by_customer,
                    'is_read_by_agent'    => $message->is_read_by_agent,
                    'cc_emails'           => $message->cc_emails ? json_decode($message->cc_emails, true) : [],
                    'created_at'          => $message->created_at,
                    'attachments'         => $message->attachments->map(fn ($a) => [
                        'id'              => $a->id,
                        'file_name'       => $a->file_name,
                        'file_size'       => $a->file_size,
                        'mime_type'       => $a->mime_type,
                        'attachment_type' => $a->attachment_type,
                        'is_inline'       => (bool) $a->is_inline,
                        'url'             => $a->public_url,
                    ]),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching ticket messages:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new message (reply or internal note)
     */
    public function store(Request $request, $ticketId)
    {
        $validator = Validator::make($request->all(), [
            'message_body'  => 'nullable|string',
            'message_type'  => 'required|in:reply,internal_note',
            'attachments'   => 'nullable|array',
            'attachments.*' => 'file|max:10240', // maks 10 MB per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Perlu minimal pesan atau file
        $hasFiles = $request->hasFile('attachments') && count($request->file('attachments')) > 0;
        if (empty(trim(strip_tags($request->input('message_body', '')))) && !$hasFiles) {
            return response()->json([
                'success' => false,
                'message' => 'Ketik pesan atau lampirkan file.'
            ], 422);
        }

        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($ticketId);
            $roleId = $sessionUser['role']['id'];

            // Determine sender type and info
            $senderType = 'employee';
            $senderId   = $sessionUser['id'];

            // Employee selalu tampil sebagai "Helpdesk Support" agar konsisten
            // (semua email dikirim dari 1 akun M365); nama asli diambil sebagai nick_name
            $senderName = 'Helpdesk Support';

            // Ambil nick_name dari session, fallback ke first_name dari DB
            $nickName = $sessionUser['nick_name'] ?? null;
            if (!$nickName) {
                $nickName = DB::table('employee_basic_data')
                    ->where('employee_id', $senderId)
                    ->value('nick_name');
            }
            // Fallback terakhir: kata pertama dari full name
            if (!$nickName) {
                $nickName = explode(' ', $sessionUser['name'] ?? 'Helpdesk')[0];
            }

            // Tambahkan tanda tangan "-NickName" di akhir pesan untuk employee
            $messageBody = $request->message_body ?? '';
            if ($nickName && $request->message_type !== 'internal_note') {
                $nick = htmlspecialchars($nickName, ENT_QUOTES, 'UTF-8');
                $messageBody .= '<p style="margin-top:4px;color:#6b7280;font-style:italic;">-' . $nick . '</p>';
            }

            // Create the message
            $message = TicketMessage::create([
                'ticket_id' => $ticketId,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'message' => $messageBody,
                'is_internal_note' => $request->message_type === 'internal_note',
                'channel' => 'web',
                'is_read_by_customer' => $senderType === 'customer',
                'is_read_by_agent' => $senderType === 'employee',
            ]);

            // Update timestamps only — status diubah manual oleh helpdesk via dropdown
            if ($request->message_type === 'reply') {
                if ($senderType === 'employee') {
                    $ticket->update(['last_agent_reply_at' => now(), 'last_message_at' => now()]);
                } else {
                    $ticket->update(['last_customer_reply_at' => now(), 'last_message_at' => now()]);
                }
            }

            // ── Kirim email + proses attachment ───────────────────────────────
            $uploadedFiles = $request->hasFile('attachments') ? $request->file('attachments') : [];

            if (
                $request->message_type === 'reply'
                && $senderType === 'employee'
                && $ticket->channel === 'email'
                && !empty($ticket->email_thread_id)
            ) {
                // Email ticket: kirim via Graph, dapatkan graph IDs, simpan metadata
                $this->sendEmailReply($ticket, $message, $uploadedFiles);
            } elseif (!empty($uploadedFiles)) {
                // Non-email ticket atau internal note: simpan file lokal
                $this->saveLocalAttachments($uploadedFiles, $message, $ticketId, $senderId);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'ticket_id' => $message->ticket_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name,
                    'message_body' => $message->message,
                    'message_type' => $message->is_internal_note ? 'internal_note' : 'reply',
                    'created_at' => $message->created_at,
                ],
                'message' => 'Message sent successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending ticket message:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kirim email balasan ke customer untuk tiket yang berasal dari email.
     * Jika ada $files, lampirkan ke email via Graph dan simpan metadata ke ticket_attachment.
     *
     * @param \Illuminate\Http\UploadedFile[] $files
     */
    private function sendEmailReply(Ticket $ticket, TicketMessage $message, array $files = []): void
    {
        try {
            // Dapatkan email customer
            $customerEmail = null;
            if ($ticket->customer_id) {
                $customer = Customer::find($ticket->customer_id);
                $customerEmail = $customer?->email;
            }

            // Fallback: cari dari pesan pertama tiket
            if (!$customerEmail) {
                $firstMessage = TicketMessage::where('ticket_id', $ticket->ticket_id)
                    ->where('channel', 'email')
                    ->orderBy('created_at', 'asc')
                    ->first();
                $customerEmail = $firstMessage?->sender_email;
            }

            if (!$customerEmail) {
                Log::warning('TicketMessageController@sendEmailReply: no customer email found', [
                    'ticket_id' => $ticket->ticket_id,
                ]);
                return;
            }

            $subject = 'Ticket #' . $ticket->ticket_number . ': ' . ($ticket->description ? substr($ticket->description, 0, 80) : 'Update');

            $lastEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
                ->where('channel', 'email')
                ->whereNotNull('email_message_id')
                ->orderBy('created_at', 'desc')
                ->first();

            $inReplyTo = $lastEmailMsg?->email_message_id;

            // Ambil CC dari pesan pertama tiket (email awal dari customer)
            $firstEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
                ->where('channel', 'email')
                ->whereNotNull('cc_emails')
                ->orderBy('created_at', 'asc')
                ->first();
            $ccList = $firstEmailMsg?->cc_emails
                ? json_decode($firstEmailMsg->cc_emails, true)
                : [];

            $emailController = new EmailController();
            $result = $emailController->sendTicketReply(
                $customerEmail,
                $subject,
                $this->buildEmailHtml($message->message, $ticket, $message->sender_name ?? 'Helpdesk Support'),
                $inReplyTo,
                $files,
                $ccList,
                true  // noRePrefix — subject tetap "Ticket #XXXX: desc" tanpa "Re: "
            );

            // Simpan metadata attachment dari Graph ke DB (tanpa file lokal)
            $graphMessageId = $result['graph_message_id'] ?? null;
            foreach ($result['attachments'] ?? [] as $att) {
                if (empty($att['graph_att_id'])) continue;

                TicketAttachment::create([
                    'ticket_id'           => $ticket->ticket_id,
                    'message_id'          => $message->id,
                    'uploaded_by_type'    => 'employee',
                    'uploaded_by_id'      => $message->sender_id,
                    'attachment_type'     => $this->resolveAttachmentType($att['mime']),
                    'file_name'           => $att['name'],
                    'file_size'           => $att['size'],
                    'mime_type'           => $att['mime'],
                    'is_inline'           => false,
                    'graph_attachment_id' => $att['graph_att_id'],
                    'graph_message_id'    => $graphMessageId,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('TicketMessageController@sendEmailReply: failed', [
                'ticket_id' => $ticket->ticket_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bungkus body pesan dari Quill dalam HTML email template yang proper.
     * Template dengan branding perusahaan dan konteks tiket membuat email
     * lebih terlihat legitimate oleh spam filter.
     */
    private function buildEmailHtml(string $body, Ticket $ticket, string $agentName): string
    {
        $ticketNum   = htmlspecialchars($ticket->ticket_number ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(mb_substr($ticket->description ?? '', 0, 90), ENT_QUOTES, 'UTF-8');
        $agent       = htmlspecialchars($agentName, ENT_QUOTES, 'UTF-8');

        // Kembalikan hanya fragment HTML (tanpa <!DOCTYPE>, <html>, <body>).
        // Graph API sudah menyediakan outer HTML structure sendiri.
        // Full HTML document di dalam body email menyebabkan rendering gagal di Gmail.
        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="font-family:Arial,Helvetica,sans-serif;max-width:600px;border-collapse:collapse;">

            <!-- Header -->
            <tr>
                <td style="background-color:#8b1a1a;padding:16px 24px;border-radius:6px 6px 0 0;">
                    <p style="color:#ffffff;font-size:16px;font-weight:bold;margin:0;line-height:1.3;">PT Eclectic Consulting</p>
                    <p style="color:rgba(255,255,255,0.7);font-size:11px;margin:3px 0 0 0;">Helpdesk Support &nbsp;&middot;&nbsp; Ticket #{$ticketNum}</p>
                </td>
            </tr>

            <!-- Body -->
            <tr>
                <td style="background-color:#ffffff;padding:24px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;font-size:14px;color:#374151;line-height:1.7;">
                    {$body}
                </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td style="background-color:#f9fafb;padding:14px 24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;">
                    <p style="color:#9ca3af;font-size:11px;margin:0;line-height:1.6;">
                        Sent by <strong style="color:#6b7280;">{$agent}</strong> &mdash; PT Eclectic Consulting Yogyakarta<br>
                        Ticket: <strong style="color:#6b7280;">#{$ticketNum}</strong> &mdash; {$description}
                    </p>
                </td>
            </tr>

        </table>
        HTML;
    }

    /**
     * Simpan file ke local storage untuk non-email ticket atau internal note.
     *
     * @param \Illuminate\Http\UploadedFile[] $files
     */
    private function saveLocalAttachments(array $files, TicketMessage $message, int $ticketId, int $uploadedById): void
    {
        foreach ($files as $file) {
            try {
                $path = $file->store("ticket-attachments/{$ticketId}", 'public');
                TicketAttachment::create([
                    'ticket_id'        => $ticketId,
                    'message_id'       => $message->id,
                    'uploaded_by_type' => 'employee',
                    'uploaded_by_id'   => $uploadedById,
                    'attachment_type'  => $this->resolveAttachmentType($file->getMimeType()),
                    'file_name'        => $file->getClientOriginalName(),
                    'file_size'        => $file->getSize(),
                    'mime_type'        => $file->getMimeType(),
                    'is_inline'        => false,
                    'file_path'        => $path,
                ]);
            } catch (\Exception $e) {
                Log::warning('TicketMessageController@saveLocalAttachments: gagal simpan file', [
                    'file'  => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Tentukan attachment_type dari MIME type.
     */
    private function resolveAttachmentType(string $mime): string
    {
        if (str_starts_with($mime, 'image/'))                                   return 'image';
        if ($mime === 'application/pdf')                                         return 'pdf';
        if (str_contains($mime, 'word') || str_contains($mime, 'document'))     return 'document';
        if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet')) return 'spreadsheet';
        if (str_contains($mime, 'zip') || str_contains($mime, 'compressed'))    return 'archive';
        return 'file';
    }

    /**
     * Mark all messages as read
     */
    public function markAllRead($ticketId)
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $ticket = Ticket::findOrFail($ticketId);
            $roleId = $sessionUser['role']['id'];

            // Employee/Admin marks messages as read by agent
            TicketMessage::where('ticket_id', $ticketId)
                ->where('sender_type', 'customer')
                ->where('is_read_by_agent', false)
                ->update([
                    'is_read_by_agent' => true,
                    'read_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read'
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking messages as read:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
