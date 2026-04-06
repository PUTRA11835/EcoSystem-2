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
                    'email_message_id'    => $message->email_message_id,
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

            // ── Kirim email dulu, baru simpan ke DB ───────────────────────────
            // Untuk reply employee: kirim ke M365 dulu → dapat Sent Items ID yang benar →
            // baru buat TicketMessage + TicketAttachment. Ini memastikan:
            //   1. graph_message_id = Sent Items ID (valid untuk proxy attachment)
            //   2. email_message_id selalu terisi (untuk reply-chain selanjutnya)
            //   3. channel = 'email' sejak awal (tidak perlu update setelah kirim)
            $uploadedFiles = $request->hasFile('attachments') ? $request->file('attachments') : [];
            $message       = null;

            if ($request->message_type === 'reply') {
                $customerEmail = $this->resolveCustomerEmail($ticket);
                if ($customerEmail) {
                    // Email-first: kirim → dapat hasil → simpan ke DB
                    $message = $this->sendEmailThenSave($ticket, [
                        'sender_type'  => $senderType,
                        'sender_id'    => $senderId,
                        'sender_name'  => $senderName,
                        'message'      => trim(strip_tags($messageBody)),
                        'message_html' => $messageBody,
                    ], $uploadedFiles, $ticketId, $senderId);
                }

                if (!$message) {
                    // Fallback: tidak ada email customer atau email gagal → simpan tanpa email
                    $message = TicketMessage::create([
                        'ticket_id'           => $ticketId,
                        'sender_type'         => $senderType,
                        'sender_id'           => $senderId,
                        'sender_name'         => $senderName,
                        'message'             => trim(strip_tags($messageBody)),
                        'message_html'        => $messageBody,
                        'is_internal_note'    => false,
                        'channel'             => 'web',
                        'is_read_by_customer' => false,
                        'is_read_by_agent'    => true,
                    ]);
                    if (!empty($uploadedFiles)) {
                        $this->saveLocalAttachments($uploadedFiles, $message, $ticketId, $senderId);
                    }
                }

                $ticket->update(['last_agent_reply_at' => now(), 'last_message_at' => now()]);

            } else {
                // Internal note — tidak pernah dikirim ke email
                $message = TicketMessage::create([
                    'ticket_id'           => $ticketId,
                    'sender_type'         => $senderType,
                    'sender_id'           => $senderId,
                    'sender_name'         => $senderName,
                    'message'             => trim(strip_tags($messageBody)),
                    'message_html'        => $messageBody,
                    'is_internal_note'    => true,
                    'channel'             => 'web',
                    'is_read_by_customer' => false,
                    'is_read_by_agent'    => true,
                ]);
                if (!empty($uploadedFiles)) {
                    $this->saveLocalAttachments($uploadedFiles, $message, $ticketId, $senderId);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'          => $message->id,
                    'ticket_id'   => $message->ticket_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name,
                    'message_body'=> $message->message,
                    'channel'     => $message->channel,
                    'message_type'=> $message->is_internal_note ? 'internal_note' : 'reply',
                    'created_at'  => $message->created_at,
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
     * POST /api/tickets/{id}/customer-reply
     *
     * Endpoint khusus untuk Jarvies: simpan balasan customer ke DB
     * dan kirim relay email ke thread yang sama (agar thread M365 tetap tersambung).
     *
     * Body:
     *   message_body  string  required  HTML dari Quill / rich text editor
     *   sender_name   string  required  Nama individual customer (bukan nama perusahaan)
     *   sender_email  string  required  Email customer yang mengirim
     *   customer_id   int     optional  ID customer (jika diketahui)
     */
    public function customerReply(Request $request, $ticketId)
    {
        Log::info('TicketMessageController@customerReply: request masuk', [
            'ticket_id'    => $ticketId,
            'full_url'     => $request->fullUrl(),
            'method'       => $request->method(),
            'body_keys'    => array_keys($request->all()),
            'content_type' => $request->header('Content-Type'),
        ]);

        $validator = Validator::make($request->all(), [
            'message_body'     => 'required|string',
            'sender_name'      => 'required|string|max:255',
            'sender_email'     => 'required|email',
            'customer_id'      => 'nullable|integer',
            // skip_relay: true → Jarvies sudah kirim email sendiri via OAuth customer
            //             false (default) → EcoSystem kirim relay dari helpdesk M365
            'skip_relay'       => 'nullable|boolean',
            // channel: 'email' jika dikirim via OAuth email customer, 'web' jika dari form biasa
            'channel'          => 'nullable|in:web,email',
            // email_message_id: RFC 2822 Message-ID dari email yang dikirim Jarvies via Gmail/Outlook OAuth
            // Diperlukan agar processInbox bisa dedup dan tidak menyimpan duplikat
            'email_message_id' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $ticket      = Ticket::findOrFail($ticketId);
            $messageBody = $request->message_body;
            $senderName  = $request->sender_name;
            $senderEmail = $request->sender_email;
            $customerId  = $request->customer_id ?? $ticket->customer_id;
            $skipRelay      = $request->boolean('skip_relay', false);
            $channel        = $request->input('channel', 'web');
            $emailMessageId = $request->input('email_message_id');

            // Simpan pesan ke DB
            $message = TicketMessage::create([
                'ticket_id'           => $ticket->ticket_id,
                'sender_type'         => 'customer',
                'sender_id'           => $customerId,
                'sender_name'         => $senderName,
                'sender_email'        => $senderEmail,
                'message'             => trim(strip_tags($messageBody)),
                'message_html'        => null,
                'is_internal_note'    => false,
                'channel'             => $channel,
                'email_message_id'    => $emailMessageId, // RFC 2822 Message-ID dari Gmail/Outlook OAuth
                'is_read_by_customer' => true,
                'is_read_by_agent'    => false,
            ]);

            $ticket->update([
                'last_customer_reply_at' => now(),
                'last_message_at'        => now(),
            ]);

            // Relay email ke thread M365:
            // - skip_relay = true  → Jarvies sudah kirim email via OAuth customer sendiri,
            //                        thread sudah tersambung, tidak perlu relay dari helpdesk
            // - skip_relay = false → kirim relay dari helpdesk M365 agar thread tetap hidup
            //
            // Relay dijalankan jika:
            //   1. skip_relay = false
            //   2. Ada email customer (sender_email dari request atau customer.email dari DB)
            //   3. email_thread_id sudah ada → relay masuk thread yang sama (normal)
            //      email_thread_id NULL → relay buat thread baru, conversationId disimpan ke ticket
            if (!$skipRelay) {
                $hasEmail = !empty($message->sender_email)
                    || Customer::find($ticket->customer_id)?->email;
                if ($hasEmail) {
                    $this->sendCustomerReplyRelay($ticket, $message, $senderName, $messageBody);
                }
            }

            Log::info('TicketMessageController@customerReply: customer message saved', [
                'ticket_id'    => $ticket->ticket_id,
                'message_id'   => $message->id,
                'sender_email' => $senderEmail,
                'channel'      => $channel,
                'skip_relay'   => $skipRelay,
                'has_thread'   => !empty($ticket->email_thread_id),
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'          => $message->id,
                    'ticket_id'   => $message->ticket_id,
                    'sender_type' => $message->sender_type,
                    'sender_name' => $message->sender_name,
                    'message_body'=> $message->message,
                    'channel'     => $message->channel,
                    'created_at'  => $message->created_at,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('TicketMessageController@customerReply: failed', [
                'ticket_id' => $ticketId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save message: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Kirim relay email dari M365 ke customer saat customer reply via Jarvies web.
     * Tujuan: menjaga agar thread email M365 tetap tersambung, dan menyimpan
     * email_message_id relay agar reply berikutnya dari helpdesk bisa pakai inReplyTo.
     */
    private function sendCustomerReplyRelay(Ticket $ticket, TicketMessage $message, string $senderName, string $messageBody): void
    {
        try {
            // Dapatkan email customer untuk tujuan relay
            $customerEmail = $message->sender_email;
            if (!$customerEmail && $ticket->customer_id) {
                $customerEmail = Customer::find($ticket->customer_id)?->email;
            }

            if (!$customerEmail) {
                Log::warning('TicketMessageController@sendCustomerReplyRelay: no customer email', [
                    'ticket_id' => $ticket->ticket_id,
                ]);
                return;
            }

            // inReplyTo = internetMessageId pesan email TERAKHIR di thread
            $lastEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
                ->where('channel', 'email')
                ->whereNotNull('email_message_id')
                ->orderBy('created_at', 'desc')
                ->first();

            $inReplyTo = $lastEmailMsg?->email_message_id;

            $subject = 'Ticket #' . $ticket->ticket_number . ': ' . mb_substr($ticket->description ?? '', 0, 80);

            // Bungkus pesan customer dalam template email yang proper
            $relayBody = $this->buildCustomerRelayHtml($messageBody, $ticket, $senderName);

            // Ambil CC dari pesan email pertama tiket (agar CC tetap dikirimi balasan)
            $firstEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
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
                $relayBody,
                $inReplyTo,
                [],     // no file attachments
                $ccList,
                true    // noRePrefix
            );

            // Simpan internetMessageId relay ke ticket_message agar inReplyTo berikutnya bisa threaded
            $relayInternetMsgId = $result['internet_message_id'] ?? null;
            if ($relayInternetMsgId) {
                $message->update(['email_message_id' => $relayInternetMsgId]);
            }

            // Jika ticket belum punya email_thread_id (approval email tidak terkirim sebelumnya),
            // simpan conversationId dari relay pertama ini sebagai thread anchor
            $relayConvId = $result['conversation_id'] ?? null;
            if ($relayConvId && empty($ticket->email_thread_id)) {
                $ticket->update(['email_thread_id' => $relayConvId]);
            }

            Log::info('TicketMessageController@sendCustomerReplyRelay: relay sent', [
                'ticket_id'           => $ticket->ticket_id,
                'message_id'          => $message->id,
                'to'                  => $customerEmail,
                'in_reply_to'         => $inReplyTo,
                'graph_message_id'    => $result['graph_message_id'] ?? null,
                'internet_message_id' => $relayInternetMsgId,
            ]);

        } catch (\Exception $e) {
            Log::warning('TicketMessageController@sendCustomerReplyRelay: failed (non-fatal)', [
                'ticket_id' => $ticket->ticket_id,
                'error'     => $e->getMessage(),
            ]);
            // Non-fatal — pesan sudah tersimpan di DB meski relay email gagal
        }
    }

    /**
     * Template HTML untuk relay email customer reply.
     */
    private function buildCustomerRelayHtml(string $body, Ticket $ticket, string $senderName): string
    {
        $ticketNum   = htmlspecialchars($ticket->ticket_number ?? '', ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars(mb_substr($ticket->description ?? '', 0, 90), ENT_QUOTES, 'UTF-8');
        $name        = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <table width="100%" cellpadding="0" cellspacing="0" border="0"
               style="font-family:Arial,Helvetica,sans-serif;max-width:600px;border-collapse:collapse;">
            <tr>
                <td style="background-color:#8b1a1a;padding:16px 24px;border-radius:6px 6px 0 0;">
                    <p style="color:#ffffff;font-size:16px;font-weight:bold;margin:0;line-height:1.3;">PT Eclectic Consulting</p>
                    <p style="color:rgba(255,255,255,0.7);font-size:11px;margin:3px 0 0 0;">Helpdesk Support &nbsp;&middot;&nbsp; Ticket #{$ticketNum}</p>
                </td>
            </tr>
            <tr>
                <td style="background-color:#ffffff;padding:24px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;font-size:14px;color:#374151;line-height:1.7;">
                    {$body}
                </td>
            </tr>
            <tr>
                <td style="background-color:#f9fafb;padding:14px 24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;">
                    <p style="color:#9ca3af;font-size:11px;margin:0;line-height:1.6;">
                        Sent by <strong style="color:#6b7280;">{$name}</strong> via Jarvies Customer Portal<br>
                        Ticket: <strong style="color:#6b7280;">#{$ticketNum}</strong> &mdash; {$description}
                    </p>
                </td>
            </tr>
        </table>
        HTML;
    }

    /**
     * Kirim email reply ke customer dulu via Graph, lalu simpan TicketMessage + TicketAttachment ke DB.
     *
     * Pendekatan "email-first":
     *   1. Kirim email via Graph → dapat graph_message_id = Sent Items ID yang valid
     *   2. Buat TicketMessage dengan channel='email' + email_message_id sudah terisi
     *   3. Buat TicketAttachment dengan graph_message_id = Sent Items ID (bukan draft ID)
     *
     * Ini menghilangkan masalah draft ID yang menjadi invalid setelah /send,
     * sehingga proxy attachment (/attachments/{id}) tidak pernah 404 lagi.
     *
     * @param  array{sender_type:string, sender_id:int, sender_name:string, message:string, message_html:string} $msgData
     * @param  \Illuminate\Http\UploadedFile[]  $files
     * @return TicketMessage|null  null jika email gagal terkirim (caller akan fallback ke web)
     */
    private function sendEmailThenSave(
        Ticket $ticket,
        array  $msgData,
        array  $files,
        int    $ticketId,
        int    $senderId
    ): ?TicketMessage {
        try {
            $customerEmail = $this->resolveCustomerEmail($ticket);
            if (!$customerEmail) return null;

            $subject = 'Ticket #' . $ticket->ticket_number . ': ' . mb_substr($ticket->description ?? '', 0, 80);

            // inReplyTo = internetMessageId pesan email terakhir (untuk thread yang benar)
            $lastEmailMsg = TicketMessage::where('ticket_id', $ticketId)
                ->where('channel', 'email')
                ->whereNotNull('email_message_id')
                ->orderBy('created_at', 'desc')
                ->first();
            $inReplyTo = $lastEmailMsg?->email_message_id;

            // CC dari pesan pertama yang menyertakan cc_emails
            $firstMsgWithCc = TicketMessage::where('ticket_id', $ticketId)
                ->whereNotNull('cc_emails')
                ->orderBy('created_at', 'asc')
                ->first();
            $ccList = $firstMsgWithCc?->cc_emails
                ? json_decode($firstMsgWithCc->cc_emails, true)
                : [];

            // ── Kirim email ───────────────────────────────────────────────────
            $result = app(EmailController::class)->sendTicketReply(
                $customerEmail,
                $subject,
                $this->buildEmailHtml($msgData['message_html'], $ticket, $msgData['sender_name']),
                $inReplyTo,
                $files,
                $ccList,
                true  // noRePrefix — subject langsung "Ticket #XXXX: desc" tanpa "Re: "
            );

            // ── Simpan TicketMessage SETELAH email berhasil ───────────────────
            $message = TicketMessage::create([
                'ticket_id'           => $ticketId,
                'sender_type'         => $msgData['sender_type'],
                'sender_id'           => $msgData['sender_id'],
                'sender_name'         => $msgData['sender_name'],
                'message'             => $msgData['message'],
                'message_html'        => $msgData['message_html'],
                'is_internal_note'    => false,
                'channel'             => 'email',
                'email_message_id'    => $result['internet_message_id'] ?? null,
                'is_read_by_customer' => false,
                'is_read_by_agent'    => true,
            ]);

            // Update email_thread_id pada ticket jika belum ada
            if (!empty($result['conversation_id']) && empty($ticket->email_thread_id)) {
                $ticket->update(['email_thread_id' => $result['conversation_id']]);
            }

            // ── Simpan attachment dengan Sent Items ID (bukan draft ID) ───────
            $graphMessageId = $result['graph_message_id'] ?? null;
            foreach ($result['attachments'] ?? [] as $att) {
                if (empty($att['graph_att_id'])) continue;

                TicketAttachment::create([
                    'ticket_id'           => $ticketId,
                    'message_id'          => $message->id,
                    'uploaded_by_type'    => 'employee',
                    'uploaded_by_id'      => $senderId,
                    'attachment_type'     => $this->resolveAttachmentType($att['mime']),
                    'file_name'           => $att['name'],
                    'file_size'           => $att['size'],
                    'mime_type'           => $att['mime'],
                    'is_inline'           => false,
                    'graph_attachment_id' => $att['graph_att_id'],
                    'graph_message_id'    => $graphMessageId,
                ]);
            }

            Log::info('TicketMessageController@sendEmailThenSave: sukses', [
                'ticket_id'           => $ticket->ticket_id,
                'message_id'          => $message->id,
                'to'                  => $customerEmail,
                'in_reply_to'         => $inReplyTo,
                'internet_message_id' => $result['internet_message_id'] ?? null,
                'graph_message_id'    => $graphMessageId,
            ]);

            return $message;

        } catch (\Exception $e) {
            Log::error('TicketMessageController@sendEmailThenSave: failed', [
                'ticket_id' => $ticket->ticket_id,
                'error'     => $e->getMessage(),
            ]);
            return null;
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
     * Resolve email customer dari berbagai sumber (urutan prioritas):
     * 1. staging_tickets.submitted_by_email  — email login customer yang dikirim Jarvies
     * 2. ticket_message.sender_email pertama — email yang disimpan saat first message dibuat
     * 3. customer.email                       — email perusahaan (fallback terakhir)
     *
     * @return string|null
     */
    private function resolveCustomerEmail(Ticket $ticket): ?string
    {
        // 1. submitted_by_email dari staging ticket yang sudah diapprove
        $submittedEmail = DB::table('staging_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->whereNotNull('submitted_by_email')
            ->value('submitted_by_email');
        if ($submittedEmail) {
            return $submittedEmail;
        }

        // 2. sender_email dari pesan pertama customer
        $firstMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
            ->where('sender_type', 'customer')
            ->whereNotNull('sender_email')
            ->orderBy('created_at', 'asc')
            ->first();
        if ($firstMsg?->sender_email) {
            return $firstMsg->sender_email;
        }

        // 3. Fallback ke customer.email (email perusahaan)
        if ($ticket->customer_id) {
            return Customer::find($ticket->customer_id)?->email;
        }

        return null;
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
