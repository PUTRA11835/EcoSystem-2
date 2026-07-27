<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exceptions\EmailSendException;
use App\Http\Controllers\EmailController;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Services\MessageHtmlSanitizerService;
use App\Services\SlaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TicketMessageController extends Controller
{
    /**
     * Alasan kegagalan email dari percobaan sendEmailThenSave() terakhir.
     * Diisi di blok catch, dibaca oleh caller (store) untuk menandai bubble
     * pesan "Tidak terkirim" beserta alasannya. null = tidak ada kegagalan.
     */
    private ?string $lastEmailError = null;

    /**
     * Alamat tujuan yang gagal pada percobaan sendEmailThenSave() terakhir (total failure /
     * semua invalid). Dibaca oleh store() untuk mengisi email_failed_recipients.
     */
    private array $lastEmailFailedRecipients = [];

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

            // Build query for messages (eager load attachments + replied-to message)
            $query = TicketMessage::with(['attachments', 'replyTo'])
                ->where('ticket_id', $ticketId)
                ->orderBy('created_at', 'asc');

            $messages = $query->get()->map(function ($message) use ($ticket) {
                // To recipients untuk pesan KELUAR (employee via email) — ditampilkan
                // seperti From/CC pada pesan masuk. Diturunkan dari email_recipients
                // (semua To+CC) dikurangi alamat CC; fallback ke ticket.to_emails untuk
                // pesan lama yang belum menyimpan email_recipients.
                $toEmails = [];
                if ($message->sender_type === 'employee' && ($message->channel ?? '') === 'email') {
                    $ccAddrs = collect($message->cc_emails ?? [])
                        ->map(fn ($c) => strtolower(is_array($c) ? ($c['address'] ?? '') : (string) $c))
                        ->filter()->all();
                    if (!empty($message->email_recipients)) {
                        $toEmails = collect($message->email_recipients)
                            ->map(fn ($a) => strtolower(trim((string) $a)))
                            ->reject(fn ($a) => $a === '' || in_array($a, $ccAddrs, true))
                            ->unique()->values()->all();
                    } elseif (!empty($ticket->to_emails)) {
                        $toEmails = collect($ticket->to_emails)
                            ->map(fn ($a) => is_array($a) ? ($a['address'] ?? '') : (string) $a)
                            ->filter()->values()->all();
                    }
                }

                $replyToPreview = null;
                if ($message->reply_to_id && $message->replyTo) {
                    $parent = $message->replyTo;
                    $replyToPreview = [
                        'id'          => $parent->id,
                        'sender_name' => $parent->sender_name,
                        'text'        => mb_substr(strip_tags($parent->message_html ?: $parent->message ?? ''), 0, 120),
                    ];
                }
                return [
                    'id'                  => $message->id,
                    'ticket_id'           => $message->ticket_id,
                    'sender_type'         => $message->sender_type,
                    'sender_id'           => $message->sender_id,
                    'sender_name'         => $message->sender_name,
                    'sender_email'        => $message->sender_email,
                    'message_body'        => $message->message,
                    'message_html'        => $message->message_html,
                    'sla_message'         => $message->sla_message,
                    'message_type'        => $message->message_type
                                                ?: ($message->is_internal_note ? 'internal_note' : 'reply'),
                    'reply_to_id'         => $message->reply_to_id,
                    'reply_to_preview'    => $replyToPreview,
                    'channel'             => $message->channel ?? 'web',
                    'to_emails'           => $toEmails,
                    'email_message_id'    => $message->email_message_id,
                    'email_status'        => $message->email_status,
                    'email_error'         => $message->email_error,
                    'is_read_by_customer' => $message->is_read_by_customer,
                    'is_read_by_agent'    => $message->is_read_by_agent,
                    'read_at'             => $message->read_at?->toIso8601String(),
                    'cc_emails'           => (function($cc) {
                                                if (is_array($cc)) return $cc;
                                                if (is_string($cc) && $cc !== '') return json_decode($cc, true) ?? [];
                                                return [];
                                            })($message->cc_emails),
                    'created_at'          => $message->created_at,
                    'is_deleted'          => (bool) $message->is_deleted,
                    'edited_at'           => $message->edited_at?->toIso8601String(),
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
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
            'message_body'            => 'nullable|string',
            'message_type'            => 'required|in:reply,internal_note',
            'to_emails'               => 'nullable',
            'cc_emails'               => 'nullable',
            'attachments'             => 'nullable|array',
            'attachments.*'           => 'file|max:10240', // maks 10 MB per file
            'mentioned_employee_ids'  => 'nullable|array',
            'mentioned_employee_ids.*'=> 'integer',
            'mentioned_role_ids'      => 'nullable|array',
            'mentioned_role_ids.*'    => 'integer',
            'reply_to_id'             => 'nullable|integer|exists:ticket_message,id',
            'ticket_status'           => 'nullable|in:inprocess,waiting_on_customer,waiting_to_confirmation,waiting_on_3rd_party,hold',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket message data is invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Perlu minimal pesan atau file
        $hasFiles = $request->hasFile('attachments') && count($request->file('attachments')) > 0;
        if (empty(trim(strip_tags($request->input('message_body', '')))) && !$hasFiles) {
            return response()->json([
                'success' => false,
                'message' => 'A message body or at least one attachment is required.'
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

            $ticket = Ticket::with('members')->findOrFail($ticketId);
            $roleId = $sessionUser['role']['id'];

            // Determine sender type and info
            $senderType = 'employee';
            $senderId   = $sessionUser['id'];

            // For internal notes, use the employee's real name so mentions are attributed correctly.
            // For public replies, always show "Helpdesk Support" for consistency (email sent from M365 shared inbox).
            $isInternalNote = $request->message_type === 'internal_note';

            $senderName = $isInternalNote ? ($sessionUser['name'] ?? 'Helpdesk Support') : 'Helpdesk Support';

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
            // Sanitasi HTML dulu sebelum disimpan/dirender/dikirim ke email customer —
            // ini satu-satunya titik masuk message_html untuk reply & internal note.
            $messageBody = MessageHtmlSanitizerService::sanitize($request->message_body ?? '');
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
            // Parse CC dari request — jika dikirim, override ticket.cc_emails
            $requestCcRaw = $request->input('cc_emails');
            $requestCc    = null;
            if ($requestCcRaw !== null) {
                if (is_array($requestCcRaw)) {
                    $requestCc = $requestCcRaw;
                } else {
                    $decoded   = json_decode($requestCcRaw, true);
                    $requestCc = is_array($decoded) ? $decoded : [];
                }
            }

            // Parse TO dari request — list email primary recipient.
            // Pertama dipakai sebagai $toEmail utama, sisanya jadi additional toRecipients.
            // Dipersist ke ticket.to_emails (untuk reply) agar recipient tambahan
            // bertahan antar reply/reload — mirror perilaku cc_emails.
            $requestToRaw = $request->input('to_emails');
            $requestTo    = null;
            if ($requestToRaw !== null) {
                if (is_array($requestToRaw)) {
                    $requestTo = $requestToRaw;
                } else {
                    $decoded   = json_decode($requestToRaw, true);
                    $requestTo = is_array($decoded) ? $decoded : [];
                }
                $requestTo = array_values(array_filter(array_map(
                    fn($e) => is_string($e) ? trim($e) : (is_array($e) ? trim((string)($e['address'] ?? '')) : ''),
                    $requestTo
                )));
            }

            $uploadedFiles = $request->hasFile('attachments') ? $request->file('attachments') : [];
            $message       = null;

            if ($request->message_type === 'reply') {
                // Simpan CC baru ke ticket agar reply berikutnya juga pakai CC yang sama
                if ($requestCc !== null) {
                    $ticket->update(['cc_emails' => $requestCc]);
                    $ticket->refresh();
                }

                // Simpan daftar TO baru ke ticket (primary customer + recipient tambahan)
                // agar reply berikutnya / reload tetap menampilkan recipient yang sama.
                if ($requestTo !== null) {
                    $ticket->update(['to_emails' => $requestTo]);
                    $ticket->refresh();
                }

                // Resolve primary TO.
                // - Jika frontend mengirim daftar to_emails (walau kosong), HORMATI apa
                //   adanya — JANGAN fallback ke company email. To kosong = tak ada primary
                //   recipient; email tetap terkirim bila ada CC (kasus EWA).
                // - resolveCustomerEmail hanya untuk caller legacy yang tidak mengirim to_emails.
                $requestToProvided = $requestTo !== null;
                $primaryTo = $requestToProvided
                    ? ($requestTo[0] ?? null)
                    : $this->resolveCustomerEmail($ticket);
                $additionalToList = $requestToProvided && count($requestTo) > 1
                    ? array_slice($requestTo, 1)
                    : [];

                // Email-first: sendEmailThenSave return null jika tak ada penerima (To & CC
                // kosong) atau email gagal → fallback simpan internal di bawah.
                $message = $this->sendEmailThenSave($ticket, [
                    'sender_type'  => $senderType,
                    'sender_id'    => $senderId,
                    'sender_name'  => $senderName,
                    'message'      => trim(strip_tags($messageBody)),
                    'message_html' => $messageBody,
                ], $uploadedFiles, $ticketId, $senderId, $requestCc, $primaryTo, $additionalToList, $requestToProvided);

                if (!$message) {
                    // Fallback: tidak ada email customer, atau email GAGAL dikirim.
                    // $this->lastEmailError terisi hanya bila email dicoba tapi ditolak
                    // (mis. alamat tujuan tidak valid) — bukan saat memang tak ada penerima.
                    // Simpan pesan + tandai status 'failed' + alasan agar tampil di bubble.
                    $failedReason = $this->lastEmailError;
                    $message = TicketMessage::create([
                        'ticket_id'               => $ticketId,
                        'sender_type'             => $senderType,
                        'sender_id'               => $senderId,
                        'sender_name'             => $senderName,
                        'message'                 => trim(strip_tags($messageBody)),
                        'message_html'            => $messageBody,
                        'is_internal_note'        => false,
                        'channel'                 => 'web',
                        'email_status'            => $failedReason ? 'failed' : null,
                        'email_error'             => $failedReason,
                        'email_failed_recipients' => !empty($this->lastEmailFailedRecipients) ? $this->lastEmailFailedRecipients : null,
                        'cc_emails'               => !empty($requestCc) ? $requestCc : null,
                        'is_read_by_customer'     => false,
                        'is_read_by_agent'        => true,
                    ]);
                    if (!empty($uploadedFiles)) {
                        $this->saveLocalAttachments($uploadedFiles, $message, $ticketId, $senderId);
                    }
                }

                $chosenStatus    = $request->input('ticket_status');
                $ticketUpdateFields = ['last_agent_reply_at' => now(), 'last_message_at' => now()];
                if ($chosenStatus) {
                    $ticketUpdateFields['status'] = $chosenStatus;
                }
                $ticket->update($ticketUpdateFields);
                $this->markTicketReadForSender($ticketId, $senderId);

                // Notifikasi ke PIC + member aktif lain
                if ($message) {
                    $replyPreview = mb_substr(strip_tags($messageBody), 0, 100);
                    $this->notifyTicketParticipants(
                        $ticket, $message, $senderId, $senderName,
                        'ticket_reply',
                        $senderName . ' replied: ' . ($replyPreview ?: '(reply)')
                    );

                    // Notifikasi bell Jarvies ke customer
                    if ($ticket->customer_id) {
                        \App\Services\CustomerNotificationService::notify(
                            customerId: (int) $ticket->customer_id,
                            type:       'ticket_reply',
                            ticketId:   (int) $ticket->ticket_id,
                            fromName:   $senderName,
                            preview:    \Illuminate\Support\Str::limit(strip_tags($messageBody), 100),
                            link:       '/tickets/' . $ticket->ticket_id,
                        );
                    }
                }

            } else {
                // Internal note — tidak pernah dikirim ke email
                $mentionedEmployeeIds = $request->input('mentioned_employee_ids', []);
                $mentionedRoleIds     = $request->input('mentioned_role_ids', []);
                $replyToId            = $request->input('reply_to_id');

                $message = TicketMessage::create([
                    'ticket_id'              => $ticketId,
                    'sender_type'            => $senderType,
                    'sender_id'              => $senderId,
                    'sender_name'            => $senderName,
                    'message'                => trim(strip_tags($messageBody)),
                    'message_html'           => $messageBody,
                    'is_internal_note'       => true,
                    'reply_to_id'            => $replyToId ?: null,
                    'channel'                => 'web',
                    'is_read_by_customer'    => false,
                    'is_read_by_agent'       => true,
                    'mentioned_employee_ids' => !empty($mentionedEmployeeIds) ? $mentionedEmployeeIds : null,
                    'mentioned_role_ids'     => !empty($mentionedRoleIds) ? $mentionedRoleIds : null,
                ]);

                if (!empty($uploadedFiles)) {
                    $this->saveLocalAttachments($uploadedFiles, $message, $ticketId, $senderId);
                }

                $ticket->update([
                    'last_message_at'              => now(),
                    'last_internal_note_at'        => now(),
                    'last_internal_note_sender_id' => $senderId,
                ]);
                $this->markTicketReadForSender($ticketId, $senderId);

                // Fire mention notifications (non-fatal)
                $mentionedNotifiedIds = [];
                if (!empty($mentionedEmployeeIds) || !empty($mentionedRoleIds)) {
                    $mentionedNotifiedIds = $this->createMentionNotifications(
                        $message,
                        $ticket,
                        $senderId,
                        $senderName,
                        $mentionedEmployeeIds,
                        $mentionedRoleIds
                    );
                }

                // Notifikasi ke PIC + member aktif lain (skip yang sudah dapat notifikasi mention)
                $notePreview = mb_substr(strip_tags($messageBody), 0, 100);
                $this->notifyTicketParticipants(
                    $ticket, $message, $senderId, $senderName,
                    'ticket_internal_note',
                    $senderName . ': ' . ($notePreview ?: '(internal note)'),
                    $mentionedNotifiedIds
                );
            }

            // Trigger SLA event (non-fatal) — status sudah diupdate di atas bersama last_agent_reply_at
            if ($message && !$isInternalNote) {
                try {
                    $ticket->refresh();
                    app(SlaService::class)->recordMessageEvent(
                        $ticket,
                        $message,
                        'employee',
                        $chosenStatus ?? $ticket->status
                    );
                } catch (\Throwable $e) {
                    Log::warning('TicketMessageController@store: SLA record gagal (non-fatal)', [
                        'ticket_id'  => $ticketId,
                        'message_id' => $message->id,
                        'error'      => $e->getMessage(),
                    ]);
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
                    'email_status'=> $message->email_status,
                    'email_error' => $message->email_error,
                    'created_at'  => $message->created_at,
                ],
                // Flag peringatan (bukan sukses) saat email GAGAL total ATAU hanya
                // sebagian terkirim (partial) — keduanya perlu toast peringatan + alasan.
                'email_failed'  => in_array($message->email_status, ['failed', 'partial'], true),
                'email_status'  => $message->email_status,
                'email_error'   => $message->email_error,
                'message' => 'Message sent successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error sending ticket message:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
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
            // channel'email' jika dikirim via OAuth email customer, 'web' jika dari form biasa
            'channel'          => 'nullable|in:web,email',
            // email_message_id: RFC 2822 Message-ID dari email yang dikirim Jarvies via Gmail/Outlook OAuth
            // Diperlukan agar processInbox bisa dedup dan tidak menyimpan duplikat
            'email_message_id' => 'nullable|string|max:500',
            // cc_emails: dari Jarvies (array atau JSON string). Dedup + exclude helpdesk self
            // lalu merge ke ticket.cc_emails agar helpdesk reply form auto-populate.
            'cc_emails'        => 'nullable',
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

            // Normalize cc_emails request — support array objects, array strings, or JSON string.
            $rawCc = $request->input('cc_emails');
            if (is_string($rawCc) && $rawCc !== '') {
                $decoded = json_decode($rawCc, true);
                $rawCc = is_array($decoded) ? $decoded : [];
            }
            $ccList = [];
            if (is_array($rawCc)) {
                $senderSelf = strtolower((string) config('services.microsoft_graph.sender_email', ''));
                $customerAddr = strtolower((string) $senderEmail);
                foreach ($rawCc as $c) {
                    $addr = is_array($c) ? ($c['address'] ?? '') : (string) $c;
                    $addr = strtolower(trim($addr));
                    if (!$addr || !filter_var($addr, FILTER_VALIDATE_EMAIL)) continue;
                    if ($addr === $senderSelf || $addr === $customerAddr) continue;
                    $ccList[$addr] = is_array($c) ? ['address' => $addr, 'name' => $c['name'] ?? null] : ['address' => $addr, 'name' => null];
                }
                $ccList = array_values($ccList);
            }

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
                'cc_emails'           => !empty($ccList) ? $ccList : null,
                'is_read_by_customer' => true,
                'is_read_by_agent'    => false,
            ]);

            // Merge CC reply customer ke ticket.cc_emails (dedup by address)
            $ticketUpdate = [
                'last_customer_reply_at' => now(),
                'last_message_at'        => now(),
            ];
            if (!empty($ccList)) {
                $existing = collect($ticket->cc_emails ?? [])
                    ->map(fn ($c) => is_array($c)
                        ? ['address' => strtolower($c['address'] ?? ''), 'name' => $c['name'] ?? null]
                        : ['address' => strtolower((string) $c), 'name' => null])
                    ->filter(fn ($c) => !empty($c['address']));
                $merged = $existing->concat($ccList)->unique('address')->values()->all();
                $ticketUpdate['cc_emails'] = $merged;
            }

            // Customer balas → otomatis kembalikan ticket ke inprocess jika sedang paused
            $stopStatuses = ['waiting_on_customer', 'waiting_to_confirmation', 'waiting_on_3rd_party', 'hold'];
            if (in_array($ticket->status, $stopStatuses)) {
                $ticketUpdate['status'] = 'inprocess';
            }

            $ticket->update($ticketUpdate);

            // Notifikasi ke PIC + member aktif — bunyi chat + entri biru di bell
            $replyPreview = mb_substr(strip_tags($messageBody), 0, 80);
            $this->notifyTicketParticipants(
                $ticket, $message, 0, $senderName,
                'ticket_reply',
                $senderName . ' (customer): ' . ($replyPreview ?: '(message)')
            );

            // Trigger SLA event untuk customer reply (non-fatal)
            try {
                app(SlaService::class)->recordMessageEvent(
                    $ticket,
                    $message,
                    'customer',
                    null
                );
            } catch (\Throwable $e) {
                Log::warning('TicketMessageController@customerReply: SLA record gagal (non-fatal)', [
                    'ticket_id'  => $ticket->ticket_id,
                    'message_id' => $message->id,
                    'error'      => $e->getMessage(),
                ]);
            }

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
                'message' => 'Failed to save message',
            ], 500);
        }
    }

    /**
     * Keep the sender's own read state fresh after they post a reply/note, so the
     * ticket doesn't immediately show as bold/unread for the person who just wrote it
     * (only other participants should see it as unread again).
     */
    private function markTicketReadForSender(int $ticketId, ?int $employeeId): void
    {
        if (!$employeeId) {
            return;
        }
        $now = now();
        DB::table('ticket_reads')->upsert(
            [['ticket_id' => $ticketId, 'employee_id' => $employeeId, 'read_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
            ['ticket_id', 'employee_id'],
            ['read_at', 'updated_at']
        );
    }

    /**
     * Create mention notifications for employees/roles tagged in an internal note.
     * Role mentions are fan-out expanded: each member of the role gets one notification.
     * The sender never receives their own notification.
     */

    /**
     * Kirim notifikasi ke PIC + semua member aktif ticket (kecuali pengirim pesan).
     * Dipanggil setelah internal note atau email reply tersimpan.
     */
    private function notifyTicketParticipants(
        Ticket        $ticket,
        TicketMessage $message,
        int           $senderId,
        string        $senderName,
        string        $type,    // 'ticket_internal_note' | 'ticket_reply'
        string        $preview,
        array         $excludeEmployeeIds = [] // already notified elsewhere for this message (e.g. mentions)
    ): void {
        // Kumpulkan PIC + member aktif, hapus duplikat dan pengirim sendiri
        $recipients = collect();

        if ($ticket->ticket_lead_id && $ticket->ticket_lead_id !== $senderId) {
            $recipients->push((int) $ticket->ticket_lead_id);
        }

        $ticket->members // hanya aktif (via wherePivot)
            ->pluck('employee_id')
            ->each(fn ($id) => $recipients->push((int) $id));

        $link = "/ticket/{$ticket->ticket_id}?msg={$message->id}";

        $recipients->unique()
            ->reject(fn ($id) => $id === $senderId)
            ->reject(fn ($id) => in_array($id, $excludeEmployeeIds, true))
            ->each(function ($empId) use ($senderId, $senderName, $type, $ticket, $message, $link, $preview) {
                Notification::create([
                    'employee_id'      => $empId,
                    'type'             => $type,
                    'ticket_id'        => $ticket->ticket_id,
                    'message_id'       => $message->id,
                    'from_employee_id' => $senderId,
                    'from_name'        => $senderName,
                    'preview'          => $preview,
                    'link'             => $link,
                    'is_read'          => false,
                ]);
            });
    }

    /**
     * @return int[] Employee IDs actually notified (for de-duping downstream notifications).
     */
    public function createMentionNotifications(
        TicketMessage $message,
        Ticket $ticket,
        int $senderId,
        string $senderName,
        array $mentionedEmployeeIds,
        array $mentionedRoleIds
    ): array {
        try {
            $ticketId  = $message->ticket_id;
            $ticketNum = $ticket->ticket_number ?? $ticketId;
            $rawText   = mb_substr(strip_tags($message->message ?? ''), 0, 100);
            $preview   = "[Ticket #{$ticketNum}] {$rawText}";
            $link      = "/ticket/{$ticketId}?msg={$message->id}";

            // Collect all recipient employee IDs (unique, exclude sender)
            $recipientIds = collect($mentionedEmployeeIds)->map(fn ($id) => (int) $id)->toArray();

            // Fan-out role mentions → individual employees via assignment pivot
            if (!empty($mentionedRoleIds)) {
                $byRole = Employee::withAnyRole($mentionedRoleIds)
                    ->where('is_active', true)
                    ->pluck('employee_id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();

                $recipientIds = array_merge($recipientIds, $byRole);
            }

            $recipientIds = array_unique($recipientIds);
            $notifiedIds  = [];

            foreach ($recipientIds as $recipientId) {
                if ($recipientId === $senderId) {
                    continue; // never notify yourself
                }

                Notification::create([
                    'employee_id'      => $recipientId,
                    'type'             => 'mention',
                    'ticket_id'        => $ticketId,
                    'message_id'       => $message->id,
                    'from_employee_id' => $senderId,
                    'from_name'        => $senderName,
                    'preview'          => $preview,
                    'link'             => $link,
                    'is_read'          => false,
                ]);
                $notifiedIds[] = $recipientId;
            }

            return $notifiedIds;
        } catch (\Exception $e) {
            Log::warning('createMentionNotifications: failed (non-fatal)', [
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);

            return [];
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

            $subject = '[JARVIES] #' . $ticket->ticket_number . ' : ' . mb_substr($ticket->description ?? '', 0, 80);

            // Bungkus pesan customer dalam template email yang proper
            $relayBody = $this->buildCustomerRelayHtml($messageBody, $ticket, $senderName);

            // Ambil CC: utamakan ticket.cc_emails (sumber terpercaya), fallback ke pesan pertama
            $ccList = [];
            if (!empty($ticket->cc_emails)) {
                $ccList = is_array($ticket->cc_emails) ? $ticket->cc_emails : (json_decode($ticket->cc_emails, true) ?? []);
            }
            if (empty($ccList)) {
                $firstEmailMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
                    ->whereNotNull('cc_emails')
                    ->orderBy('created_at', 'asc')
                    ->first();
                $ccList = $firstEmailMsg?->cc_emails
                    ? (is_array($firstEmailMsg->cc_emails) ? $firstEmailMsg->cc_emails : (json_decode($firstEmailMsg->cc_emails, true) ?? []))
                    : [];
            }

            $emailController = new EmailController();
            $result = $emailController->sendTicketReply(
                $customerEmail,
                $subject,
                $relayBody,
                $inReplyTo,
                [],                         // no file attachments
                $ccList,
                true,                       // noRePrefix
                $ticket->email_thread_id    // conversationId fallback jika inReplyTo tidak ditemukan
            );

            // Simpan internetMessageId relay ke ticket_message agar inReplyTo berikutnya bisa threaded
            $relayInternetMsgId = $result['internet_message_id'] ?? null;
            if ($relayInternetMsgId) {
                $message->update(['email_message_id' => $relayInternetMsgId]);
            }

            // Selalu sync email_thread_id ke convId hasil relay. Exchange bisa ubah
            // convId (misal saat subject di-patch tanpa prefix "Re:"), dan reply
            // berikutnya dari Jarvies/EcoSystem harus ref ke convId terbaru agar
            // Gmail menjaga thread tetap satu (tidak terpecah ke thread baru).
            $relayConvId = $result['conversation_id'] ?? null;
            if ($relayConvId && $relayConvId !== $ticket->email_thread_id) {
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
     * Format minimal: isi pesan + tanda tangan kecil (tanpa box/layout branding).
     */
    private function buildCustomerRelayHtml(string $body, Ticket $ticket, string $senderName): string
    {
        $ticketNum = htmlspecialchars($ticket->ticket_number ?? '', ENT_QUOTES, 'UTF-8');
        $name      = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.7;max-width:600px;">
            {$body}
            <p style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;">
                Sent by <strong style="color:#6b7280;">{$name}</strong> via Jarvies Customer Portal<br>
                Ticket: <strong style="color:#6b7280;">#{$ticketNum}</strong>
            </p>
        </div>
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
        int    $senderId,
        ?array $ccOverride = null,
        ?string $toOverride = null,
        array  $additionalToEmails = [],
        bool   $noCompanyFallback = false
    ): ?TicketMessage {
        // Reset penanda error di awal — nilai lama tidak boleh bocor ke percobaan ini.
        $this->lastEmailError = null;
        $this->lastEmailFailedRecipients = [];
        try {
            // CC: pakai override dari request jika ada, fallback ke ticket.cc_emails, lalu pesan pertama.
            // Dihitung lebih dulu karena ikut menentukan apakah email perlu dikirim (kasus CC-only).
            if ($ccOverride !== null) {
                $ccList = $ccOverride;
            } else {
                $ccList = [];
                if (!empty($ticket->cc_emails)) {
                    $ccList = is_array($ticket->cc_emails) ? $ticket->cc_emails : (json_decode($ticket->cc_emails, true) ?? []);
                }
                if (empty($ccList)) {
                    $firstMsgWithCc = TicketMessage::where('ticket_id', $ticketId)
                        ->whereNotNull('cc_emails')
                        ->orderBy('created_at', 'asc')
                        ->first();
                    $ccList = $firstMsgWithCc?->cc_emails
                        ? (is_array($firstMsgWithCc->cc_emails) ? $firstMsgWithCc->cc_emails : (json_decode($firstMsgWithCc->cc_emails, true) ?? []))
                        : [];
                }
            }

            // Primary TO.
            // - $noCompanyFallback (dari composer UI): hormati $toOverride apa adanya
            //   (boleh kosong). JANGAN tarik company email. To kosong → kirim CC-only.
            // - Selain itu (caller legacy): fallback ke resolveCustomerEmail spt semula.
            if ($noCompanyFallback) {
                $customerEmail = trim((string) ($toOverride ?? ''));
                // Tidak ada penerima sama sekali (To & CC kosong) → tidak ada yang dikirim.
                if ($customerEmail === '' && empty($ccList)) return null;
            } else {
                $customerEmail = $toOverride ?: $this->resolveCustomerEmail($ticket);
                if (!$customerEmail) return null;
            }

            $subject = '[JARVIES] #' . $ticket->ticket_number . ' : ' . mb_substr($ticket->description ?? '', 0, 80);

            // inReplyTo = internetMessageId pesan email terakhir (untuk thread yang benar)
            $lastEmailMsg = TicketMessage::where('ticket_id', $ticketId)
                ->where('channel', 'email')
                ->whereNotNull('email_message_id')
                ->orderBy('created_at', 'desc')
                ->first();
            $inReplyTo = $lastEmailMsg?->email_message_id;

            // ── Kirim email ───────────────────────────────────────────────────
            $result = app(EmailController::class)->sendTicketReply(
                $customerEmail,
                $subject,
                $this->buildEmailHtml($msgData['message_html'], $ticket, $msgData['sender_name']),
                $inReplyTo,
                $files,
                $ccList,
                true,                       // noRePrefix — subject langsung "Ticket #XXXX: desc" tanpa "Re: "
                $ticket->email_thread_id,   // conversationId fallback jika inReplyTo tidak ditemukan
                false,                      // forceNewDraft — biarkan default
                [],                         // rawAttachments — tidak ada forwarded attachment di reply biasa
                $additionalToEmails         // additional toRecipients selain primary
            );

            // Sebagian alamat di-drop karena salah tulis → email TETAP terkirim ke penerima
            // valid, tapi pesan ditandai 'partial' (amber) + sebut alamat yang gagal.
            $invalidRecipients = $result['invalid_recipients'] ?? [];
            $isPartial         = !empty($invalidRecipients);

            // ── Simpan TicketMessage SETELAH email berhasil ───────────────────
            $message = TicketMessage::create([
                'ticket_id'               => $ticketId,
                'sender_type'             => $msgData['sender_type'],
                'sender_id'               => $msgData['sender_id'],
                'sender_name'             => $msgData['sender_name'],
                'message'                 => $msgData['message'],
                'message_html'            => $msgData['message_html'],
                'is_internal_note'        => false,
                'channel'                 => 'email',
                'email_message_id'        => $result['internet_message_id'] ?? null,
                'email_status'            => $isPartial ? 'partial' : null,
                'email_error'             => $isPartial ? EmailController::deliveryFailureReason($invalidRecipients, false) : null,
                'email_recipients'        => $result['recipients'] ?? null,
                'email_failed_recipients' => $isPartial ? array_values($invalidRecipients) : null,
                'cc_emails'               => !empty($ccList) ? $ccList : null,
                'is_read_by_customer'     => false,
                'is_read_by_agent'        => true,
            ]);

            // Selalu sync email_thread_id ke convId reply helpdesk ini (bukan hanya
            // saat kosong). Exchange bisa ubah convId saat subject di-patch, dan
            // reply berikutnya dari Jarvies/EcoSystem harus ref ke convId terbaru
            // supaya Gmail client menjaga thread tetap satu.
            if (!empty($result['conversation_id'])
                && $result['conversation_id'] !== $ticket->email_thread_id) {
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
            // Simpan alasan gagal agar caller bisa menandai bubble "Tidak terkirim".
            // EmailSendException sudah berisi alasan ramah pengguna; exception lain
            // (mis. bug tak terduga) dapat pesan generik.
            $this->lastEmailError = EmailSendException::reasonFrom($e);
            $this->lastEmailFailedRecipients = EmailSendException::failedRecipientsFrom($e);
            Log::error('TicketMessageController@sendEmailThenSave: failed', [
                'ticket_id'  => $ticket->ticket_id,
                'reason'     => $this->lastEmailError,
                'error'      => $e->getMessage(),
                'raw_detail' => $e instanceof EmailSendException ? $e->rawDetail : null,
            ]);
            return null;
        }
    }

    /**
     * Bungkus body pesan dari Quill untuk dikirim via email.
     * Format minimal: isi pesan + tanda tangan kecil.
     * Tidak ada box/layout branding agar tampilan email sama dengan tampilan di website.
     */
    private function buildEmailHtml(string $body, Ticket $ticket, string $agentName): string
    {
        $ticketNum = htmlspecialchars($ticket->ticket_number ?? '', ENT_QUOTES, 'UTF-8');
        $agent     = htmlspecialchars($agentName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.7;max-width:600px;">
            {$body}
            <p style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;">
                Sent by <strong style="color:#6b7280;">{$agent}</strong> &mdash; PT Eclectic Consulting<br>
                Ticket: <strong style="color:#6b7280;">#{$ticketNum}</strong>
            </p>
        </div>
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
        // 1. submitted_by_email langsung di ticket (diisi saat initiateEmail / import CSV)
        if (!empty($ticket->submitted_by_email)) {
            return $ticket->submitted_by_email;
        }

        // 2. submitted_by_email dari staging ticket yang sudah diapprove
        $submittedEmail = DB::table('staging_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->whereNotNull('submitted_by_email')
            ->value('submitted_by_email');
        if ($submittedEmail) {
            return $submittedEmail;
        }

        // 3. sender_email dari pesan pertama customer
        $firstMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
            ->where('sender_type', 'customer')
            ->whereNotNull('sender_email')
            ->orderBy('created_at', 'asc')
            ->first();
        if ($firstMsg?->sender_email) {
            return $firstMsg->sender_email;
        }

        // 4. Fallback ke customer.email (email perusahaan)
        if ($ticket->customer_id) {
            return Customer::find($ticket->customer_id)?->email;
        }

        return null;
    }

    /**
     * Kirim reply dari Lite API (mobile) — alur email-first sama dengan store() versi
     * web: kirim ke customer (To/CC dari ticket, atau override dari $ccOverride/$toList
     * bila dikirim) via M365 Graph, fallback tersimpan sebagai pesan 'web' bila email
     * gagal/tidak ada penerima, lalu update status tiket, notifikasi PIC/member, dan
     * SLA event. Lite API belum punya endpoint upload attachment untuk chat, jadi
     * reply di sini selalu tanpa lampiran.
     *
     * @param  array{id:int, name:string, nick_name?:string|null} $sender
     * @param  array|null $ccOverride  Daftar CC baru (array string/objek {address,name}). null = pakai CC tiket saat ini.
     * @param  array|null $toList      Daftar TO baru, item pertama jadi primary recipient, sisanya additional.
     *                                 null = pakai resolveCustomerEmail() (perilaku lama/legacy).
     *                                 [] (array kosong, eksplisit dikirim) = tidak ada primary TO (kirim CC-only jika ada CC).
     * @return array{message: TicketMessage, email_failed: bool}
     */
    public function sendLiteReply(Ticket $ticket, array $sender, string $messageHtml, ?string $chosenStatus = null, ?array $ccOverride = null, ?array $toList = null): array
    {
        $ticketId = $ticket->ticket_id;
        $senderId = (int) $sender['id'];

        // Reply publik selalu tampil sebagai "Helpdesk Support" (email dikirim dari M365 shared inbox)
        $senderName = 'Helpdesk Support';

        $nickName = $sender['nick_name'] ?? null;
        if (!$nickName) {
            $nickName = DB::table('employee_basic_data')->where('employee_id', $senderId)->value('nick_name');
        }
        if (!$nickName) {
            $nickName = explode(' ', $sender['name'] ?? 'Helpdesk')[0];
        }

        $messageBody = $messageHtml;
        if ($nickName) {
            $nick = htmlspecialchars($nickName, ENT_QUOTES, 'UTF-8');
            $messageBody .= '<p style="margin-top:4px;color:#6b7280;font-style:italic;">-' . $nick . '</p>';
        }

        $ticket->loadMissing('members');

        // Simpan CC/TO baru ke ticket (persist) agar reply berikutnya — dari web
        // maupun mobile — ikut memakai daftar yang sama, sama seperti store() web.
        if ($ccOverride !== null) {
            $ticket->update(['cc_emails' => $ccOverride]);
            $ticket->refresh();
        }
        if ($toList !== null) {
            $ticket->update(['to_emails' => $toList]);
            $ticket->refresh();
        }

        $toProvided       = $toList !== null;
        $primaryTo         = $toProvided ? ($toList[0] ?? null) : null;
        $additionalToList  = $toProvided && count($toList) > 1 ? array_slice($toList, 1) : [];

        $message = $this->sendEmailThenSave($ticket, [
            'sender_type'  => 'employee',
            'sender_id'    => $senderId,
            'sender_name'  => $senderName,
            'message'      => trim(strip_tags($messageBody)),
            'message_html' => $messageBody,
        ], [], $ticketId, $senderId, $ccOverride, $primaryTo, $additionalToList, $toProvided);

        if (!$message) {
            // Fallback: tidak ada email customer, atau email GAGAL dikirim.
            $failedReason = $this->lastEmailError;
            $message = TicketMessage::create([
                'ticket_id'               => $ticketId,
                'sender_type'             => 'employee',
                'sender_id'               => $senderId,
                'sender_name'             => $senderName,
                'message'                 => trim(strip_tags($messageBody)),
                'message_html'            => $messageBody,
                'is_internal_note'        => false,
                'channel'                 => 'web',
                'email_status'            => $failedReason ? 'failed' : null,
                'email_error'             => $failedReason,
                'email_failed_recipients' => !empty($this->lastEmailFailedRecipients) ? $this->lastEmailFailedRecipients : null,
                'cc_emails'               => !empty($ccOverride) ? $ccOverride : null,
                'is_read_by_customer'     => false,
                'is_read_by_agent'        => true,
            ]);
        }

        $ticketUpdateFields = ['last_agent_reply_at' => now(), 'last_message_at' => now()];
        if ($chosenStatus) {
            $ticketUpdateFields['status'] = $chosenStatus;
        }
        $ticket->update($ticketUpdateFields);
        $this->markTicketReadForSender($ticketId, $senderId);

        $replyPreview = mb_substr(strip_tags($messageBody), 0, 100);
        $this->notifyTicketParticipants(
            $ticket, $message, $senderId, $senderName,
            'ticket_reply',
            $senderName . ' replied: ' . ($replyPreview ?: '(reply)')
        );

        if ($ticket->customer_id) {
            \App\Services\CustomerNotificationService::notify(
                customerId: (int) $ticket->customer_id,
                type:       'ticket_reply',
                ticketId:   (int) $ticket->ticket_id,
                fromName:   $senderName,
                preview:    \Illuminate\Support\Str::limit(strip_tags($messageBody), 100),
                link:       '/tickets/' . $ticket->ticket_id,
            );
        }

        try {
            $ticket->refresh();
            app(SlaService::class)->recordMessageEvent($ticket, $message, 'employee', $chosenStatus ?? $ticket->status);
        } catch (\Throwable $e) {
            Log::warning('TicketMessageController@sendLiteReply: SLA record gagal (non-fatal)', [
                'ticket_id'  => $ticketId,
                'message_id' => $message->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return [
            'message'      => $message->fresh(['attachments', 'replyTo']),
            'email_failed' => in_array($message->email_status, ['failed', 'partial'], true),
        ];
    }

    /**
     * Kirim email sistem (mis. meeting invitation) ke customer menggunakan infrastruktur
     * yang SAMA dengan sendEmailThenSave — threading, resolve email, logging semuanya
     * seragam dengan reply biasa. Setelah TicketMessage tersimpan, message_type diupdate.
     *
     * @return TicketMessage|null  null jika tidak ada customer email atau email gagal dikirim
     */
    public function sendSystemReplyEmail(
        Ticket $ticket,
        int    $senderId,
        string $senderName,
        string $htmlBody,
        string $plainBody,
        string $messageType,
        ?array $ccOverride = null,
        ?array $toList = null
    ): ?TicketMessage {
        $toProvided       = $toList !== null;
        $primaryTo        = $toProvided ? ($toList[0] ?? null) : null;
        $additionalToList = $toProvided && count($toList) > 1 ? array_slice($toList, 1) : [];

        $message = $this->sendEmailThenSave(
            $ticket,
            [
                'sender_type'  => 'employee',
                'sender_id'    => $senderId,
                'sender_name'  => $senderName,
                'message'      => $plainBody,
                'message_html' => $htmlBody,
            ],
            [],
            $ticket->ticket_id,
            $senderId,
            $ccOverride,
            $primaryTo,
            $additionalToList,
            $toProvided
        );

        if ($message) {
            $message->update(['message_type' => $messageType]);
        }

        return $message;
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
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit an internal note (sender only, within 10 minutes of posting).
     */
    public function updateInternalNote(Request $request, $ticketId, $messageId)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $message = TicketMessage::where('ticket_id', $ticketId)
            ->where('id', $messageId)
            ->where('is_internal_note', true)
            ->firstOrFail();

        if ((int) $message->sender_id !== (int) ($sessionUser['id'] ?? 0)) {
            return response()->json(['success' => false, 'message' => 'You can only edit your own notes.'], 403);
        }

        if ($message->created_at->addMinutes(10)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Notes can only be edited within 10 minutes of posting.'], 403);
        }

        if ($message->is_deleted) {
            return response()->json(['success' => false, 'message' => 'Cannot edit a deleted note.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'message_html'            => 'nullable|string',
            'remove_attachment_ids'   => 'nullable|array',
            'remove_attachment_ids.*' => 'integer',
            'attachments'             => 'nullable|array',
            'attachments.*'           => 'file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::transaction(function () use ($request, $message, $ticketId, $sessionUser) {
                $messageHtml  = MessageHtmlSanitizerService::sanitize($request->input('message_html', ''));
                $messagePlain = trim(strip_tags($messageHtml));

                $message->update([
                    'message'      => $messagePlain,
                    'message_html' => $messageHtml,
                    'edited_at'    => now(),
                ]);

                // Remove attachments.
                // GUARD: jangan pernah hapus inline image yang byte-nya masih
                // direferensikan oleh <img src="/storage/..."> di message_html baru.
                // Menghapus file-nya akan meng-orphan URL di body → gambar 404
                // (mirror invariant email: inline image dikelola lewat body, bukan
                // daftar attachment). File di-serve dari public disk via InlineImageService.
                $removeIds = $request->input('remove_attachment_ids', []);
                if (!empty($removeIds)) {
                    $toRemove = TicketAttachment::where('message_id', $message->id)
                        ->whereIn('id', $removeIds)
                        ->get();
                    foreach ($toRemove as $att) {
                        if ($att->is_inline && $att->file_path
                            && str_contains((string) $messageHtml, $att->file_path)) {
                            continue; // masih dipakai di body → jangan hapus (cegah 404)
                        }
                        if ($att->file_path) {
                            Storage::disk('public')->delete($att->file_path);
                        }
                        $att->delete();
                    }
                }

                // Add new attachments
                $uploadedFiles = $request->file('attachments') ?? [];
                if (!empty($uploadedFiles)) {
                    $this->saveLocalAttachments($uploadedFiles, $message, (int) $ticketId, (int) $sessionUser['id']);
                }
            });

            $message->refresh();
            $message->load('attachments');

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'           => $message->id,
                    'message_body' => $message->message,
                    'message_html' => $message->message_html,
                    'edited_at'    => $message->edited_at?->toIso8601String(),
                    'attachments'  => $message->attachments->map(fn($a) => [
                        'id'              => $a->id,
                        'file_name'       => $a->file_name,
                        'file_size'       => $a->file_size,
                        'mime_type'       => $a->mime_type,
                        'attachment_type' => $a->attachment_type,
                        'is_inline'       => (bool) $a->is_inline,
                        'url'             => $a->public_url,
                    ]),
                ],
                'message' => 'Note updated.',
            ]);
        } catch (\Exception $e) {
            Log::error('updateInternalNote error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update note.'], 500);
        }
    }

    /**
     * Soft-delete an internal note (sender only, within 10 minutes of posting).
     */
    public function destroyInternalNote($ticketId, $messageId)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $message = TicketMessage::where('ticket_id', $ticketId)
            ->where('id', $messageId)
            ->where('is_internal_note', true)
            ->firstOrFail();

        $sessionRoleIds = !empty($sessionUser['role_ids'])
            ? array_map('intval', $sessionUser['role_ids'])
            : DB::table('employee_role_assignment')
                ->where('employee_id', (int) ($sessionUser['id'] ?? 0))
                ->pluck('role_id')->map(fn($id) => (int) $id)->toArray();

        $isAdmin   = in_array(RoleId::EC_ADMINISTRATOR->value, $sessionRoleIds, true);
        $isSender  = $message->sender_id !== null
                     && (int) $message->sender_id === (int) ($sessionUser['id'] ?? 0);

        if (!$isAdmin && !$isSender) {
            return response()->json(['success' => false, 'message' => 'You can only delete your own notes.'], 403);
        }

        if (!$isAdmin && $message->created_at->addMinutes(10)->isPast()) {
            return response()->json(['success' => false, 'message' => 'Notes can only be deleted within 10 minutes of posting.'], 403);
        }

        if ($message->is_deleted) {
            return response()->json(['success' => false, 'message' => 'Note already deleted.'], 422);
        }

        $message->update(['is_deleted' => true]);

        return response()->json(['success' => true, 'message' => 'Note deleted.']);
    }

    /**
     * Initiate the first email thread for a ticket that has no email_thread_id yet.
     * Used for tickets imported via CSV that start with no email channel.
     *
     * POST /api/tickets/{ticketId}/initiate-email
     */
    public function initiateEmail(Request $request, $ticketId)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'to'   => 'required|email',
            'cc'   => 'nullable|string',
            'body' => 'nullable|string',
        ]);

        try {
            $ticket = Ticket::findOrFail($ticketId);

            if ($ticket->email_thread_id || $ticket->channel === 'email') {
                return response()->json([
                    'success' => false,
                    'message' => 'This ticket already has an active email thread.',
                ], 422);
            }

            $toEmail = strtolower(trim($request->input('to')));

            // Normalize CC list → [{address, name}]
            $ccList = [];
            $rawCc  = trim($request->input('cc', ''));
            if ($rawCc !== '') {
                foreach (array_filter(array_map('trim', explode(',', $rawCc))) as $addr) {
                    if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                        $ccList[] = ['address' => $addr, 'name' => null];
                    }
                }
            }

            $agentName = 'Helpdesk Support';
            // Anchor subject thread — format seragam "[JARVIES] #XXXX : desc" agar konsisten
            // dengan email approval & reply, sehingga semua email tetap satu thread.
            $subject   = '[JARVIES] #' . $ticket->ticket_number . ' : ' . mb_substr($ticket->description ?? '', 0, 80);

            $safeNum   = htmlspecialchars($ticket->ticket_number ?? '', ENT_QUOTES, 'UTF-8');
            $safeDesc  = htmlspecialchars(mb_substr($ticket->description ?? '', 0, 90), ENT_QUOTES, 'UTF-8');
            $emailBody = <<<HTML
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="font-family:Arial,Helvetica,sans-serif;max-width:600px;border-collapse:collapse;">
                <tr>
                    <td style="background-color:#8b1a1a;padding:16px 24px;border-radius:6px 6px 0 0;">
                        <p style="color:#ffffff;font-size:16px;font-weight:bold;margin:0;line-height:1.3;">PT Eclectic Consulting</p>
                        <p style="color:rgba(255,255,255,0.7);font-size:11px;margin:3px 0 0 0;">Helpdesk Support &nbsp;&middot;&nbsp; Ticket #{$safeNum}</p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#ffffff;padding:24px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;font-size:14px;color:#374151;line-height:1.7;">
                        <p>Dear Customer,</p>
                        <p>Sehubungan dengan pembaruan sistem layanan kami, proses penanganan tiket <strong>#{$safeNum}</strong> kini dilanjutkan melalui email ini.</p>
                        <p>Untuk memperlancar komunikasi, Anda dapat merespons melalui salah satu cara berikut:</p>
                        <ol style="margin:8px 0 8px 20px;padding:0;">
                            <li style="margin-bottom:6px;">Balas email ini secara langsung.</li>
                            <li style="margin-bottom:6px;">Akses portal layanan kami di <a href="https://help.eclectic.co.id" style="color:#8b1a1a;">https://help.eclectic.co.id</a>.</li>
                        </ol>
                        <p>Apabila terdapat pihak lain yang perlu dilibatkan dalam komunikasi ini, silakan tambahkan alamat email mereka pada kolom CC saat membalas email ini.</p>
                        <p style="margin-top:16px;">Hormat kami,<br><strong>Helpdesk Support</strong></p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f9fafb;padding:14px 24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;">
                        <p style="color:#9ca3af;font-size:11px;margin:0;line-height:1.6;">
                            Sent by <strong style="color:#6b7280;">Helpdesk Support</strong> &mdash; PT Eclectic Consulting<br>
                            Ticket: <strong style="color:#6b7280;">#{$safeNum}</strong> &mdash; {$safeDesc}
                        </p>
                    </td>
                </tr>
            </table>
            HTML;

            $plainBody = "Dear Customer, sehubungan dengan pembaruan sistem layanan kami, proses penanganan tiket #{$safeNum} kini dilanjutkan melalui email ini. Anda dapat membalas email ini secara langsung atau mengakses portal kami di https://help.eclectic.co.id. Hormat kami, Helpdesk Support.";

            $emailCtrl = new EmailController();
            $result    = $emailCtrl->sendTicketReply(
                toEmail:    $toEmail,
                subject:    $subject,
                body:       $emailBody,
                inReplyTo:  null,
                files:      [],
                ccList:     array_column($ccList, 'address'),
                noRePrefix: true,
            );

            $conversationId = $result['conversation_id']    ?? null;
            $internetMsgId  = $result['internet_message_id'] ?? null;

            DB::transaction(function () use ($ticket, $toEmail, $ccList, $conversationId, $plainBody, $emailBody, $internetMsgId, $sessionUser) {
                $ticket->update([
                    'channel'             => 'email',
                    'email_thread_id'     => $conversationId,
                    'submitted_by_email'  => $toEmail,
                    'cc_emails'           => !empty($ccList) ? $ccList : null,
                    'last_message_at'     => now(),
                    'last_agent_reply_at' => now(),
                ]);

                TicketMessage::create([
                    'ticket_id'           => $ticket->ticket_id,
                    'sender_type'         => 'employee',
                    'sender_id'           => $sessionUser['employee_id'] ?? null,
                    'sender_name'         => 'Helpdesk Support',
                    'message'             => $plainBody,
                    'message_html'        => $emailBody,
                    'is_internal_note'    => false,
                    'channel'             => 'email',
                    'email_message_id'    => $internetMsgId,
                    'cc_emails'           => !empty($ccList) ? $ccList : null,
                    'is_read_by_customer' => false,
                    'is_read_by_agent'    => true,
                ]);
            });
            $this->markTicketReadForSender($ticket->ticket_id, $sessionUser['id']);

            Log::info('TicketMessageController@initiateEmail: email thread dimulai', [
                'ticket_id'      => $ticket->ticket_id,
                'ticket_number'  => $ticket->ticket_number,
                'to'             => $toEmail,
                'conversation_id'=> $conversationId,
                'by'             => $sessionUser['eci'] ?? $agentName,
            ]);

            return response()->json([
                'success'         => true,
                'message'         => 'Email sent successfully. The chat thread is now active.',
                'conversation_id' => $conversationId,
            ]);

        } catch (\Exception $e) {
            Log::error('TicketMessageController@initiateEmail: gagal', [
                'ticket_id' => $ticketId,
                'error'     => $e->getMessage(),
                'error_at'  => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update sla_message for a specific ticket message
     */
    public function updateSlaMessage($ticketId, $messageId, Request $request)
    {
        try {
            $sessionUser = session('user');

            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $message = TicketMessage::where('ticket_id', $ticketId)->findOrFail($messageId);

            $slaMessage = $request->input('sla_message');
            $hasContent = $slaMessage !== null && trim($slaMessage) !== '';

            $message->update([
                'sla_message'    => $slaMessage,
                'sla_message_by' => $hasContent ? ($sessionUser['id'] ?? null) : null,
                'sla_message_at' => $hasContent ? now() : null,
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Error updating sla_message:', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send email: ' . $e->getMessage(),
//                 'message' => 'Failed to update SLA message',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
