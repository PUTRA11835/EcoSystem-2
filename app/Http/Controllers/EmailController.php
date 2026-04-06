<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Services\StagingTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    // =========================================================================
    // GRAPH API HELPERS
    // =========================================================================

    /**
     * Ambil OAuth2 access token via client credentials flow.
     */
    private function getAccessToken(): string
    {
        $tenantId = env('MS_TENANT_ID');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => env('MS_CLIENT_ID'),
                'client_secret' => env('MS_CLIENT_SECRET'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Gagal mendapatkan access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    private function graphGet(string $path, array $query = [], array $headers = []): array
    {
        $token   = $this->getAccessToken();
        $baseUrl = rtrim(env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'), '/');

        $http = Http::withToken($token);
        if (!empty($headers)) {
            $http = $http->withHeaders($headers);
        }
        $response = $http->get("{$baseUrl}{$path}", $query);

        if (!$response->successful()) {
            throw new \RuntimeException("Graph GET {$path} gagal: " . $response->body());
        }

        return $response->json();
    }

    private function graphPost(string $path, array $body): \Illuminate\Http\Client\Response
    {
        $token   = $this->getAccessToken();
        $baseUrl = rtrim(env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'), '/');

        $response = Http::withToken($token)->post("{$baseUrl}{$path}", $body);

        if (!$response->successful()) {
            throw new \RuntimeException("Graph POST {$path} gagal: " . $response->body());
        }

        return $response;
    }

    private function graphPatch(string $path, array $body): void
    {
        $token   = $this->getAccessToken();
        $baseUrl = rtrim(env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'), '/');

        $response = Http::withToken($token)->patch("{$baseUrl}{$path}", $body);

        if (!$response->successful()) {
            throw new \RuntimeException("Graph PATCH {$path} gagal: " . $response->body());
        }
    }

    /**
     * Ekstrak hanya bagian reply baru dari body email HTML.
     * Membuang quoted text (<blockquote>, gmail_quote, Outlook divider, dll).
     *
     * Mengembalikan HTML (bukan plain text) agar:
     * - Formatting (bold, tabel, dll) tetap terjaga
     * - Referensi inline image (src="cid:xxx") tetap ada untuk di-replace kemudian
     */
    private function extractReplyBody(string $html): string
    {
        if (empty($html)) return '';

        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML(
            '<html><head><meta charset="utf-8"/></head><body>' .
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') .
            '</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        $xpath = new \DOMXPath($dom);

        // Elemen yang mengandung quoted/previous messages — hapus semua
        $removeSelectors = [
            '//blockquote',                                   // RFC standard, semua klien
            '//*[contains(@class,"gmail_quote")]',            // Gmail
            '//*[contains(@class,"yahoo_quoted")]',           // Yahoo Mail
            '//*[contains(@class,"moz-cite-prefix")]',        // Thunderbird
            '//*[@id="divRplyFwdMsg"]',                       // Outlook Web
            '//*[contains(@class,"OutlookMessageHeader")]',   // Outlook Desktop
            '//*[contains(@class,"x_gmail_quote")]',          // Gmail via Outlook
        ];

        foreach ($removeSelectors as $selector) {
            foreach (iterator_to_array($xpath->query($selector)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Serialisasi ulang isi <body> sebagai HTML (bukan textContent)
        // agar img, formatting, dll tetap terjaga
        $bodyNode = $dom->getElementsByTagName('body')->item(0);

        if (!$bodyNode) {
            return strip_tags($html);
        }

        $innerHTML = '';
        foreach ($bodyNode->childNodes as $child) {
            $innerHTML .= $dom->saveHTML($child);
        }

        // Bersihkan karakter khusus yang mungkin di-encode oleh DOMDocument
        $innerHTML = html_entity_decode($innerHTML, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($innerHTML);
    }

    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Ambil daftar pesan terbaru dari inbox (untuk debug / preview).
     */
    public function inbox()
    {
        $email = env('MS_SENDER_EMAIL');

        $data = $this->graphGet("/users/{$email}/mailFolders/inbox/messages", [
            '$top'     => 20,
            '$orderby' => 'receivedDateTime desc',
            '$select'  => 'id,subject,from,toRecipients,receivedDateTime,bodyPreview,internetMessageId,conversationId,isRead',
        ]);

        return response()->json($data['value'] ?? []);
    }

    /**
     * Kirim email baru via Graph API.
     */
    public function send(Request $request)
    {
        $data = $request->validate([
            'to'      => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
        ]);

        $sender = env('MS_SENDER_EMAIL');

        $this->graphPost("/users/{$sender}/sendMail", [
            'message' => [
                'subject' => $data['subject'],
                'body'    => ['contentType' => 'Text', 'content' => $data['body']],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $data['to']]],
                ],
            ],
            'saveToSentItems' => true,
        ]);

        return response()->json(['status' => 'sent']);
    }

    /**
     * Balas email via Graph API.
     * Jika ada in_reply_to (internetMessageId), gunakan endpoint /reply Graph.
     * Jika tidak ditemukan, fallback ke sendMail biasa.
     */
    public function reply(Request $request)
    {
        $data = $request->validate([
            'to'          => ['required', 'email'],
            'subject'     => ['required', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'in_reply_to' => ['nullable', 'string'],
        ]);

        $sender  = env('MS_SENDER_EMAIL');
        $subject = stripos($data['subject'], 're:') !== 0 ? 'Re: ' . $data['subject'] : $data['subject'];

        if (!empty($data['in_reply_to'])) {
            try {
                $result = $this->graphGet("/users/{$sender}/messages", [
                    '$filter' => "internetMessageId eq '" . str_replace("'", "''", $data['in_reply_to']) . "'",
                    '$select' => 'id',
                    '$top'    => 1,
                ]);

                if (!empty($result['value'][0]['id'])) {
                    $this->graphPost(
                        "/users/{$sender}/messages/{$result['value'][0]['id']}/reply",
                        ['comment' => $data['body']]
                    );
                    return response()->json(['status' => 'replied']);
                }
            } catch (\Exception $e) {
                Log::warning('EmailController@reply: reply via Graph gagal, fallback ke sendMail', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: kirim sebagai email baru
        $this->graphPost("/users/{$sender}/sendMail", [
            'message' => [
                'subject' => $subject,
                'body'    => ['contentType' => 'Text', 'content' => $data['body']],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $data['to']]],
                ],
            ],
            'saveToSentItems' => true,
        ]);

        return response()->json(['status' => 'replied']);
    }

    /**
     * Proses inbox: buat tiket baru dari email baru, atau tambah pesan ke tiket yang ada.
     * Endpoint ini dipanggil oleh scheduler (email:process-inbox).
     */
    public function processInbox(Request $request)
    {
        try {
            $sender = env('MS_SENDER_EMAIL');

            $select      = 'id,subject,from,ccRecipients,receivedDateTime,body,internetMessageId,conversationId,hasAttachments';
            // Minta body dalam format HTML agar inline image (cid:) tetap terjaga
            $preferHtml  = ['Prefer' => 'outlook.body-content-type="html"'];

            // Query 1: semua unread (perilaku utama)
            $unreadData = $this->graphGet("/users/{$sender}/mailFolders/inbox/messages", [
                '$filter'  => 'isRead eq false',
                '$orderby' => 'receivedDateTime asc',
                '$top'     => 50,
                '$select'  => $select,
            ], $preferHtml);
            $unreadMessages = $unreadData['value'] ?? [];

            // Query 2: email masuk dalam 60 menit terakhir (jaring pengaman untuk email
            // yang sudah dibaca di Outlook sebelum auto-poll sempat memprosesnya).
            // PENTING: Graph API pakai UTC — gunakan Carbon::now('UTC') agar tidak salah timezone.
            $since        = \Carbon\Carbon::now('UTC')->subMinutes(60)->format('Y-m-d\TH:i:s\Z');
            $recentData   = $this->graphGet("/users/{$sender}/mailFolders/inbox/messages", [
                '$filter'  => "receivedDateTime ge {$since}",
                '$orderby' => 'receivedDateTime asc',
                '$top'     => 20,
                '$select'  => $select,
            ], $preferHtml);
            $recentMessages = $recentData['value'] ?? [];

            Log::info('EmailController@processInbox: fetch summary', [
                'unread_count' => count($unreadMessages),
                'recent_count' => count($recentMessages),
                'since_utc'    => $since,
            ]);

            // Gabungkan dan dedup berdasarkan internetMessageId
            $seen     = [];
            $messages = [];
            foreach (array_merge($unreadMessages, $recentMessages) as $msg) {
                $msgId = $msg['internetMessageId'] ?? $msg['id'];
                if (!isset($seen[$msgId])) {
                    $seen[$msgId] = true;
                    $messages[]  = $msg;
                }
            }

            $processed = 0;
            $skipped   = 0;
            $errors    = [];

            foreach ($messages as $msg) {
                try {
                    $graphMsgId     = $msg['id'];
                    $subject        = $msg['subject'] ?? '(no subject)';
                    $fromEmail      = $msg['from']['emailAddress']['address'] ?? null;
                    $fromName       = $msg['from']['emailAddress']['name'] ?? $fromEmail;
                    // extractReplyBody sekarang mengembalikan HTML (bukan plain text)
                    $bodyHtml       = $this->extractReplyBody($msg['body']['content'] ?? '');
                    $bodyPlain      = trim(strip_tags($bodyHtml)); // untuk kolom `message`
                    $internetMsgId  = $msg['internetMessageId'] ?? null;
                    $conversationId = $msg['conversationId'] ?? null;
                    $hasAttachments = $msg['hasAttachments'] ?? false;

                    // Ekstrak CC recipients: [{name, address}, ...]
                    $ccEmails = collect($msg['ccRecipients'] ?? [])
                        ->map(fn($r) => [
                            'name'    => $r['emailAddress']['name'] ?? null,
                            'address' => $r['emailAddress']['address'] ?? null,
                        ])
                        ->filter(fn($r) => !empty($r['address']))
                        ->values()
                        ->all();
                    $ccJson = !empty($ccEmails) ? json_encode($ccEmails) : null;

                    // Cari tiket terkait: pertama cek conversationId (thread), lalu internetMessageId
                    $ticket = null;
                    if ($conversationId) {
                        $ticket = Ticket::where('email_thread_id', $conversationId)->first();
                    }
                    if (!$ticket && $internetMsgId) {
                        $ticket = Ticket::whereHas('messages', function ($q) use ($internetMsgId) {
                            $q->where('email_message_id', $internetMsgId);
                        })->first();
                    }

                    // Cari customer: cek customer.email dulu, fallback ke auth_users.email
                    $customer = Customer::where('email', $fromEmail)->first();
                    if (!$customer && $fromEmail) {
                        $authCustomerId = \DB::table('auth_users')
                            ->whereRaw('LOWER(email) = LOWER(?)', [$fromEmail])
                            ->whereNotNull('customer_id')
                            ->value('customer_id');
                        if ($authCustomerId) {
                            $customer = Customer::find($authCustomerId);
                        }
                    }

                    if ($ticket) {
                        // Dedup: skip jika message dengan ID ini sudah ada
                        if ($internetMsgId && TicketMessage::where('email_message_id', $internetMsgId)->exists()) {
                            $this->graphPatch("/users/{$sender}/messages/{$graphMsgId}", ['isRead' => true]);
                            $skipped++;
                            continue;
                        }

                        // Tambah pesan ke tiket yang sudah ada
                        $message = TicketMessage::create([
                            'ticket_id'           => $ticket->ticket_id,
                            'sender_type'         => $customer ? 'customer' : 'system',
                            'sender_id'           => $customer?->customer_id,
                            'sender_email'        => $fromEmail,
                            'sender_name'         => $fromName,
                            'message'             => $bodyPlain,
                            'message_html'        => $bodyHtml,
                            'is_internal_note'    => false,
                            'channel'             => 'email',
                            'email_message_id'    => $internetMsgId,
                            'email_in_reply_to'   => null,
                            'cc_emails'           => $ccJson,
                            'is_read_by_customer' => true,
                            'is_read_by_agent'    => false,
                        ]);

                        // Ambil metadata attachment & ganti referensi cid: di message_html.
                        // Cek dua kondisi: hasAttachments flag ATAU ada cid: references di body
                        // (beberapa email client melaporkan hasAttachments=false meski ada inline image).
                        if ($hasAttachments || str_contains($bodyHtml, 'cid:')) {
                            $cidMap = $this->storeEmailAttachments($sender, $graphMsgId, $message, $ticket->ticket_id);
                            if (!empty($cidMap) && $message->message_html) {
                                $message->update([
                                    'message_html' => $this->replaceCidReferences($message->message_html, $cidMap),
                                ]);
                            }
                        }

                        // Status diubah manual oleh helpdesk — hanya update timestamps
                        $ticket->update([
                            'last_customer_reply_at' => now(),
                            'last_message_at'        => now(),
                        ]);

                    } else {
                        // Dedup staging: skip jika sudah pernah masuk staging dengan internet_message_id ini
                        if ($internetMsgId && \App\Models\StagingTicket::where('email_message_id', $internetMsgId)->exists()) {
                            $this->graphPatch("/users/{$sender}/messages/{$graphMsgId}", ['isRead' => true]);
                            $skipped++;
                            continue;
                        }

                        // Email baru tanpa tiket terkait → simpan ke staging untuk divalidasi Helpdesk
                        app(StagingTicketService::class)->createFromEmail([
                            'customer_id'         => $customer?->customer_id,
                            'from_email'          => $fromEmail,
                            'from_name'           => $fromName,
                            'subject'             => $subject,
                            'body_html'           => $bodyHtml,
                            'conversation_id'     => $conversationId,
                            'internet_message_id' => $internetMsgId,
                            'graph_message_id'    => $graphMsgId,
                            'has_attachments'     => $hasAttachments,
                            'cc_emails'           => $ccJson,
                        ]);

                        Log::info('EmailController@processInbox: email baru masuk ke staging', [
                            'from'    => $fromEmail,
                            'subject' => $subject,
                        ]);
                    }

                    // Tandai sebagai sudah dibaca di Graph
                    $this->graphPatch("/users/{$sender}/messages/{$graphMsgId}", ['isRead' => true]);
                    $processed++;

                } catch (\Exception $e) {
                    Log::error('EmailController@processInbox: error processing message', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $errors[] = $e->getMessage();
                    $skipped++;
                }
            }

            return response()->json([
                'status'    => 'done',
                'processed' => $processed,
                'skipped'   => $skipped,
                'errors'    => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('EmailController@processInbox: Graph API gagal', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to access inbox: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // EMAIL ATTACHMENT HANDLER
    // =========================================================================

    /**
     * Public wrapper: proses attachment dari email dan ganti referensi cid: di message_html.
     * Dipanggil dari StagingTicketController@approve setelah TicketMessage pertama dibuat.
     *
     * @param  string        $graphMsgId  Graph internal message ID (disimpan di staging_tickets.graph_message_id)
     * @param  TicketMessage $message     TicketMessage yang baru dibuat saat approve
     * @param  int           $ticketId
     */
    public function processAttachmentsForMessage(string $graphMsgId, TicketMessage $message, int $ticketId): void
    {
        $sender = env('MS_SENDER_EMAIL');
        $cidMap = $this->storeEmailAttachments($sender, $graphMsgId, $message, $ticketId);

        if (!empty($cidMap) && $message->message_html) {
            $message->update([
                'message_html' => $this->replaceCidReferences($message->message_html, $cidMap),
            ]);
        }
    }

    /**
     * Resolve inline cid: image references in an HTML body by embedding them
     * as base64 data URIs fetched directly from Graph API.
     * Used for staging ticket preview — no DB records are created.
     *
     * @param  string $graphMsgId  Graph message ID of the original email
     * @param  string $html        Raw HTML body (may contain cid: references)
     * @return string HTML with cid: replaced by data URIs (or original if fetch fails)
     */
    public function resolveInlineImagesAsDataUris(string $graphMsgId, string $html): string
    {
        $sender = env('MS_SENDER_EMAIL');
        try {
            $result = $this->graphGet("/users/{$sender}/messages/{$graphMsgId}/attachments");
            foreach ($result['value'] ?? [] as $att) {
                if (!str_contains($att['@odata.type'] ?? '', 'fileAttachment')) continue;
                $contentId    = $att['contentId'] ?? null;
                $mimeType     = $att['contentType'] ?? 'application/octet-stream';
                $attName      = $att['name'] ?? null;
                $attId        = $att['id'] ?? null;
                $contentBytes = $att['contentBytes'] ?? null;

                // Graph API omits contentBytes for larger attachments in list responses.
                // If contentBytes is missing, individually fetch the attachment to get it.
                if (!$contentBytes && $attId) {
                    try {
                        $single       = $this->graphGet("/users/{$sender}/messages/{$graphMsgId}/attachments/{$attId}");
                        $contentBytes = $single['contentBytes'] ?? null;
                        if (!$mimeType || $mimeType === 'application/octet-stream') {
                            $mimeType = $single['contentType'] ?? $mimeType;
                        }
                    } catch (\Exception $e) {
                        Log::warning('EmailController@resolveInlineImagesAsDataUris: individual fetch failed', [
                            'att_id' => $attId,
                            'error'  => $e->getMessage(),
                        ]);
                    }
                }

                if (!$contentBytes) continue;

                $dataUri = "data:{$mimeType};base64,{$contentBytes}";

                // Ganti referensi cid: (HTML email dengan inline attachment)
                if ($contentId) {
                    $cleanCid = trim($contentId, '<>');
                    $html = str_replace('cid:' . $cleanCid, $dataUri, $html);
                    $html = str_replace('cid:<' . $cleanCid . '>', $dataUri, $html);
                    if ($contentId !== $cleanCid) {
                        $html = str_replace('cid:' . $contentId, $dataUri, $html);
                    }
                }

                // Ganti placeholder [filename.ext] yang muncul saat body disimpan sebagai plain-text
                if ($attName && preg_match('/\.(png|jpe?g|gif|bmp|webp)$/i', $attName)) {
                    $placeholder = '[' . $attName . ']';
                    if (str_contains($html, $placeholder)) {
                        $imgTag = '<img src="' . $dataUri . '" alt="' . htmlspecialchars($attName, ENT_QUOTES) . '" style="max-width:100%">';
                        $html   = str_replace($placeholder, $imgTag, $html);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('EmailController@resolveInlineImagesAsDataUris: failed', [
                'graph_msg_id' => $graphMsgId,
                'error'        => $e->getMessage(),
            ]);
        }
        return $html;
    }

    /**
     * Ambil metadata attachment dari Graph API dan simpan ke DB.
     * File TIDAK diunduh ke server — disimpan via proxy route /attachments/{id}.
     *
     * @param  string         $senderEmail   email akun MS Graph (MS_SENDER_EMAIL)
     * @param  string         $graphMsgId    Graph internal message ID
     * @param  TicketMessage  $message       record pesan yang baru dibuat
     * @param  int            $ticketId
     * @return array          Mapping [contentId => attachmentDbId] untuk inline image CID replacement
     */
    private function storeEmailAttachments(string $senderEmail, string $graphMsgId, TicketMessage $message, int $ticketId): array
    {
        $cidMap = [];

        try {
            // Fetch semua field attachment tanpa $select.
            // Catatan: $select TIDAK bisa dipakai untuk contentId maupun contentBytes karena
            // keduanya adalah properti derived type (fileAttachment), bukan base type (attachment).
            // Graph API mengembalikan semua field termasuk contentBytes — namun kita hanya
            // menggunakan metadata dan TIDAK menyimpan contentBytes ke disk.
            $result = $this->graphGet("/users/{$senderEmail}/messages/{$graphMsgId}/attachments");

            foreach ($result['value'] ?? [] as $att) {
                // Lewati non-fileAttachment (referenceAttachment, itemAttachment, dll)
                $odataType = $att['@odata.type'] ?? '';
                if ($odataType && !str_contains($odataType, 'fileAttachment')) {
                    continue;
                }

                // Lewati jika tidak ada nama (attachment tidak valid)
                if (empty($att['name'])) {
                    continue;
                }

                $graphAttId  = $att['id'];
                $originalName = $att['name'];
                $mimeType    = $att['contentType'] ?? 'application/octet-stream';
                $isInline    = $att['isInline'] ?? false;
                $fileSize    = $att['size'] ?? 0;
                $contentId   = $att['contentId'] ?? null; // CID untuk inline images

                // Lewati jika sudah pernah disimpan
                $existing = TicketAttachment::where('graph_attachment_id', $graphAttId)->first();
                if ($existing) {
                    if ($contentId) $cidMap[$contentId] = $existing->id;
                    continue;
                }

                $record = TicketAttachment::create([
                    'ticket_id'           => $ticketId,
                    'message_id'          => $message->id,
                    'uploaded_by_type'    => 'system',
                    'uploaded_by_id'      => null,
                    'attachment_type'     => $this->resolveAttachmentType($mimeType),
                    'file_name'           => $originalName,
                    'file_size'           => $fileSize,
                    'mime_type'           => $mimeType,
                    'is_inline'           => $isInline,
                    'graph_attachment_id' => $graphAttId,
                    'graph_message_id'    => $graphMsgId,
                    'content_id'          => $contentId,
                ]);

                if ($contentId) {
                    $cidMap[$contentId] = $record->id;
                }
            }
        } catch (\Exception $e) {
            Log::warning('EmailController@storeEmailAttachments: gagal ambil metadata attachment', [
                'graph_msg_id' => $graphMsgId,
                'ticket_id'    => $ticketId,
                'error'        => $e->getMessage(),
            ]);
            // Jangan throw — gagal attachment tidak boleh membatalkan penyimpanan pesan
        }

        return $cidMap;
    }

    /**
     * Ganti referensi cid:xxx di HTML email dengan URL proxy attachment.
     * Dipanggil setelah storeEmailAttachments() berhasil menyimpan record.
     *
     * @param  string $html    HTML body pesan
     * @param  array  $cidMap  [contentId => attachmentDbId]
     * @return string HTML yang sudah diganti referensi cid-nya
     */
    private function replaceCidReferences(string $html, array $cidMap): string
    {
        foreach ($cidMap as $cid => $attachmentId) {
            $proxyUrl = '/attachments/' . $attachmentId;
            // Graph bisa mengembalikan contentId dengan atau tanpa angle brackets: <xxx> atau xxx
            $cleanCid = trim($cid, '<>');
            // Replace semua variasi yang mungkin muncul di HTML
            $html = str_replace('cid:' . $cleanCid,        $proxyUrl, $html);
            $html = str_replace('cid:<' . $cleanCid . '>', $proxyUrl, $html);
            // Kalau cid asli berbeda dari cleanCid (ada angle brackets), replace juga
            if ($cid !== $cleanCid) {
                $html = str_replace('cid:' . $cid, $proxyUrl, $html);
            }
        }
        return $html;
    }

    /**
     * Tentukan attachment_type berdasarkan MIME type.
     */
    private function resolveAttachmentType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if ($mimeType === 'application/pdf') return 'pdf';
        if (str_contains($mimeType, 'word') || str_contains($mimeType, 'document')) return 'document';
        if (str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet')) return 'spreadsheet';
        if (str_contains($mimeType, 'zip') || str_contains($mimeType, 'compressed')) return 'archive';
        return 'file';
    }

    /**
     * POST /api/email/reprocess-attachments/{messageId}
     * Reprocess attachment untuk ticket_message yang sudah ada tapi belum punya attachment.
     * Berguna untuk pesan yang masuk sebelum fitur attachment diaktifkan.
     */
    public function reprocessAttachments(Request $request, int $messageId)
    {
        $sessionUser = session('user');
        if (!$sessionUser || !in_array($sessionUser['role']['id'], [1, 2, 6, 7])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $message = TicketMessage::findOrFail($messageId);

        if ($message->channel !== 'email' || !$message->email_message_id) {
            return response()->json(['success' => false, 'message' => 'This message is not from an email or has no email_message_id'], 422);
        }

        $sender = env('MS_SENDER_EMAIL');

        try {
            // Cari Graph message ID berdasarkan internetMessageId
            $result = $this->graphGet("/users/{$sender}/messages", [
                '$filter' => "internetMessageId eq '" . str_replace("'", "''", $message->email_message_id) . "'",
                '$select' => 'id,hasAttachments',
                '$top'    => 1,
            ]);

            $graphMsg = $result['value'][0] ?? null;
            if (!$graphMsg) {
                return response()->json(['success' => false, 'message' => 'Email not found in Graph inbox'], 404);
            }

            // Lewati hanya jika Graph melaporkan tidak ada attachment DAN body tidak punya cid: references
            $hasCidInBody = str_contains($message->message_html ?? '', 'cid:');
            if (empty($graphMsg['hasAttachments']) && !$hasCidInBody) {
                return response()->json(['success' => true, 'message' => 'Email has no attachments', 'attachments' => 0]);
            }

            $before = $message->attachments()->count();
            $cidMap = $this->storeEmailAttachments($sender, $graphMsg['id'], $message, $message->ticket_id);
            $after  = $message->attachments()->count();

            // Ganti referensi cid: di HTML jika ada inline image baru
            if (!empty($cidMap) && $message->message_html) {
                $message->update([
                    'message_html' => $this->replaceCidReferences($message->message_html, $cidMap),
                ]);
            }

            return response()->json([
                'success'     => true,
                'message'     => 'Attachments reprocessed successfully',
                'attachments' => $after - $before,
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Kirim email balasan untuk sebuah tiket (digunakan oleh TicketMessageController).
     *
     * Alur:
     * 1. Jika ada inReplyTo → createReply draft → patch body → tambah attachment → send
     * 2. Fallback: buat message draft baru → tambah attachment → send
     *
     * Seluruh alur berbasis draft sehingga kita selalu mendapatkan draftId
     * (= sent message ID) dan attachment ID dari Graph — tanpa menyimpan file lokal.
     *
     * @param  string                                          $toEmail
     * @param  string                                          $subject
     * @param  string                                          $body        HTML dari Quill
     * @param  string|null                                     $inReplyTo   internetMessageId pesan terakhir
     * @param  \Illuminate\Http\UploadedFile[]                 $files       File lampiran dari request
     * @return array{graph_message_id:string, attachments:list<array{name:string,mime:string,size:int,graph_att_id:string}>}
     */
    public function sendTicketReply(
        string $toEmail,
        string $subject,
        string $body,
        ?string $inReplyTo = null,
        array $files = [],
        array $ccList = [],  // [{name, address}, ...] atau [address, ...]
        bool $noRePrefix = false,
        ?string $threadId = null  // M365 conversationId — fallback jika inReplyTo tidak ditemukan
    ): array {
        $sender         = env('MS_SENDER_EMAIL');
        $replySubject   = (!$noRePrefix && stripos($subject, 're:') !== 0) ? 'Re: ' . $subject : $subject;
        $draftId        = null;
        $conversationId = null;

        // Normalisasi ccList → format Graph API: [{emailAddress: {address, name}}]
        $ccRecipients = [];
        foreach ($ccList as $cc) {
            if (is_string($cc)) {
                $ccRecipients[] = ['emailAddress' => ['address' => $cc]];
            } elseif (is_array($cc) && !empty($cc['address'])) {
                $ccRecipients[] = ['emailAddress' => array_filter([
                    'address' => $cc['address'],
                    'name'    => $cc['name'] ?? null,
                ])];
            }
        }

        // ── Ekstrak inline images (base64) dari body HTML ──────────────────────
        // Email clients block data URI images; replace with cid: references
        $extracted    = $this->extractBase64Images($body);
        $cleanBody    = $extracted['html'];
        $inlineImages = $extracted['inlineImages'];

        // ── Coba buat reply draft dari pesan asli (untuk threading yang benar) ──
        //
        // createReply digunakan untuk mendapat In-Reply-To + References + conversationId otomatis.
        // SETELAH createReply, toRecipients SELALU di-patch ke $toEmail (customer) karena:
        //   - Inbox message: createReply sudah benar (reply ke customer yg kirim) → patch confirm
        //   - SentItems msg: createReply salah arah (ke raditya sendiri) → patch fix ke customer
        //
        // Dengan selalu patch toRecipients, kita tidak perlu membedakan Inbox vs SentItems.
        if ($inReplyTo) {
            $filterVal  = str_replace("'", "''", $inReplyTo);
            $originalId = null;

            // Cari di Inbox dulu, lalu SentItems, terakhir all messages (fallback global).
            // Fallback global diperlukan jika email sudah dipindah ke subfolder atau custom folder.
            foreach ([
                "/users/{$sender}/mailFolders/Inbox/messages",
                "/users/{$sender}/mailFolders/SentItems/messages",
                "/users/{$sender}/messages",   // ← global fallback: semua folder
            ] as $searchPath) {
                try {
                    $result = $this->graphGet($searchPath, [
                        '$filter' => "internetMessageId eq '{$filterVal}'",
                        '$select' => 'id,conversationId',
                        '$top'    => 1,
                    ]);
                    if (!empty($result['value'][0]['id'])) {
                        $originalId = $result['value'][0]['id'];
                        if (!$conversationId && !empty($result['value'][0]['conversationId'])) {
                            $conversationId = $result['value'][0]['conversationId'];
                        }
                        Log::info('EmailController@sendTicketReply: inReplyTo found', [
                            'path'        => $searchPath,
                            'in_reply_to' => $inReplyTo,
                            'original_id' => $originalId,
                        ]);
                        break;
                    }
                } catch (\Exception $e) {
                    Log::warning('EmailController@sendTicketReply: search inReplyTo gagal', [
                        'path'        => $searchPath,
                        'in_reply_to' => $inReplyTo,
                        'error'       => $e->getMessage(),
                    ]);
                }
            }

            if ($originalId) {
                try {
                    $draft          = $this->graphPost("/users/{$sender}/messages/{$originalId}/createReply", []);
                    $draftId        = $draft->json('id');
                    $conversationId = $draft->json('conversationId') ?? $conversationId;

                    // SELALU patch toRecipients dan ccRecipients agar tidak pernah salah kirim.
                    // Ini fix untuk kasus SentItems: createReply default-nya reply ke raditya sendiri.
                    // ccRecipients selalu di-set eksplisit (bisa [] untuk hapus pre-populated CC yang salah).
                    //
                    // JANGAN override subject di sini — Exchange menghitung ulang conversationId
                    // dari normalized subject. Jika subject diubah → conversationId baru →
                    // email keluar dari thread di Outlook DAN SMTP In-Reply-To header hilang.
                    // Graph sudah otomatis set "Re: {original_subject}" via createReply → biarkan.
                    $patchData = [
                        'body'         => ['contentType' => 'HTML', 'content' => $cleanBody],
                        'toRecipients' => [['emailAddress' => ['address' => $toEmail]]],
                        'ccRecipients' => $ccRecipients,
                    ];
                    $this->graphPatch("/users/{$sender}/messages/{$draftId}", $patchData);
                } catch (\Exception $e) {
                    Log::warning('EmailController@sendTicketReply: createReply gagal, fallback ke draft baru', [
                        'original_id' => $originalId,
                        'error'       => $e->getMessage(),
                    ]);
                    $draftId = null;
                }
            } else {
                Log::warning('EmailController@sendTicketReply: inReplyTo tidak ditemukan di mana-mana, pakai fallback draft', [
                    'in_reply_to' => $inReplyTo,
                ]);
            }
        }

        // ── Fallback by threadId (conversationId) ─────────────────────────────
        // Dipakai jika inReplyTo tidak ditemukan di manapun tapi kita punya email_thread_id.
        // Cari pesan manapun dalam thread tsb (terbaru) → createReply agar tetap threaded.
        if (!$draftId && $threadId) {
            try {
                $threadVal   = str_replace("'", "''", $threadId);
                $threadResult = $this->graphGet("/users/{$sender}/messages", [
                    '$filter'  => "conversationId eq '{$threadVal}'",
                    '$select'  => 'id,conversationId',
                    '$orderby' => 'receivedDateTime desc',
                    '$top'     => 1,
                ]);
                if (!empty($threadResult['value'][0]['id'])) {
                    $threadMsgId = $threadResult['value'][0]['id'];
                    $draft       = $this->graphPost("/users/{$sender}/messages/{$threadMsgId}/createReply", []);
                    $draftId     = $draft->json('id');
                    if (!$conversationId) {
                        $conversationId = $draft->json('conversationId') ?? $threadId;
                    }
                    $patchData = [
                        'body'         => ['contentType' => 'HTML', 'content' => $cleanBody],
                        'toRecipients' => [['emailAddress' => ['address' => $toEmail]]],
                        'ccRecipients' => $ccRecipients,
                    ];
                    $this->graphPatch("/users/{$sender}/messages/{$draftId}", $patchData);
                    Log::info('EmailController@sendTicketReply: threaded via conversationId fallback', [
                        'thread_id'  => $threadId,
                        'thread_msg' => $threadMsgId,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('EmailController@sendTicketReply: threadId fallback gagal', [
                    'thread_id' => $threadId,
                    'error'     => $e->getMessage(),
                ]);
                $draftId = null;
            }
        }

        // ── Fallback: buat draft baru ─────────────────────────────────────────
        // Dipakai jika: inReplyTo null, message tidak ditemukan, atau createReply gagal.
        // In-Reply-To + References di-set manual agar Gmail/Outlook tetap bisa thread.
        // toRecipients = $toEmail (selalu customer, tidak pernah raditya sendiri).
        if (!$draftId) {
            $headers = [
                ['name' => 'X-Mailer',        'value' => 'EcoSystem-Helpdesk'],
                ['name' => 'X-Entity-Ref-ID', 'value' => uniqid('ecosys-', true)],
            ];
            if ($inReplyTo) {
                $headers[] = ['name' => 'In-Reply-To', 'value' => $inReplyTo];
                $headers[] = ['name' => 'References',  'value' => $inReplyTo];
            }
            $newMsgPayload = [
                'subject'                => $replySubject,
                'body'                   => ['contentType' => 'HTML', 'content' => $cleanBody],
                'toRecipients'           => [['emailAddress' => ['address' => $toEmail]]],
                'internetMessageHeaders' => $headers,
            ];
            if (!empty($ccRecipients)) {
                $newMsgPayload['ccRecipients'] = $ccRecipients;
            }
            $draft   = $this->graphPost("/users/{$sender}/messages", $newMsgPayload);
            $draftId = $draft->json('id');
            if (!$conversationId) {
                $conversationId = $draft->json('conversationId');
            }
        }

        // ── Lampirkan inline images sebagai CID attachments ────────────────────
        foreach ($inlineImages as $img) {
            try {
                $this->graphPost("/users/{$sender}/messages/{$draftId}/attachments", [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name'        => $img['name'],
                    'contentType' => $img['mime'],
                    'contentBytes'=> $img['content'],
                    'contentId'   => $img['cid'],
                    'isInline'    => true,
                ]);
            } catch (\Exception $e) {
                Log::warning('EmailController@sendTicketReply: gagal lampirkan inline image', [
                    'cid'   => $img['cid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── Tambahkan attachment ke draft satu per satu ────────────────────────
        // File TIDAK disimpan ke disk lokal — isi dibaca ke memori, di-base64, langsung ke Graph
        $attachmentRecords = [];
        foreach ($files as $file) {
            try {
                $contentBytes = base64_encode(file_get_contents($file->getRealPath()));
                $attRes = $this->graphPost("/users/{$sender}/messages/{$draftId}/attachments", [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name'        => $file->getClientOriginalName(),
                    'contentType' => $file->getMimeType() ?? 'application/octet-stream',
                    'contentBytes'=> $contentBytes,
                ]);

                $attachmentRecords[] = [
                    'name'         => $file->getClientOriginalName(),
                    'mime'         => $file->getMimeType() ?? 'application/octet-stream',
                    'size'         => $file->getSize(),
                    'graph_att_id' => $attRes->json('id'),
                ];
            } catch (\Exception $e) {
                Log::warning('EmailController@sendTicketReply: gagal tambah attachment', [
                    'file'  => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
                // Lanjutkan — satu file gagal tidak membatalkan keseluruhan pengiriman
            }
        }

        // ── Ambil internetMessageId dari draft sebelum dikirim ────────────────
        // internetMessageId diperlukan oleh Jarvies sebagai In-Reply-To header
        // agar balasan customer masuk ke thread yang sama di Gmail/Outlook.
        $internetMessageId = null;
        try {
            $draftInfo = $this->graphGet("/users/{$sender}/messages/{$draftId}", [
                '$select' => 'internetMessageId,conversationId',
            ]);
            $internetMessageId = $draftInfo['internetMessageId'] ?? null;
            if (!$conversationId) {
                $conversationId = $draftInfo['conversationId'] ?? null;
            }
            if (!$internetMessageId) {
                Log::warning('EmailController@sendTicketReply: internetMessageId null dari draft', [
                    'draft_id' => $draftId,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('EmailController@sendTicketReply: gagal ambil internetMessageId dari draft', [
                'draft_id' => $draftId,
                'error'    => $e->getMessage(),
            ]);
        }

        // ── Kirim draft ───────────────────────────────────────────────────────
        $this->graphPost("/users/{$sender}/messages/{$draftId}/send", []);

        // ── Cari ID pesan di Sent Items setelah terkirim ──────────────────────
        // Setelah /send, draft berpindah dari Drafts → Sent Items dengan ID baru.
        // Draft ID lama sudah tidak valid → attachment proxy akan 404 jika pakai draft ID.
        // Cari di folder SentItems spesifik (lebih cepat terindex daripada global search).
        // Retry 2x dengan jeda 1 detik karena Graph kadang belum index segera setelah /send.
        $sentMessageId = $draftId; // fallback ke draft ID jika pencarian gagal
        if ($internetMessageId) {
            // OData $filter pada internetMessageId tidak reliable di SentItems.
            // Solusi: ambil beberapa pesan terbaru dari SentItems, cocokkan internetMessageId di PHP.
            for ($attempt = 1; $attempt <= 3; $attempt++) {
                if ($attempt > 1) sleep(1);
                try {
                    $searchResult = $this->graphGet("/users/{$sender}/mailFolders/SentItems/messages", [
                        '$orderby' => 'sentDateTime desc',
                        '$select'  => 'id,internetMessageId',
                        '$top'     => 20,
                    ]);
                    foreach ($searchResult['value'] ?? [] as $msg) {
                        if (($msg['internetMessageId'] ?? '') === $internetMessageId) {
                            $sentMessageId = $msg['id'];
                            break 2;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('EmailController@sendTicketReply: gagal ambil sentMessageId dari Sent Items', [
                        'attempt'             => $attempt,
                        'internet_message_id' => $internetMessageId,
                        'error'               => $e->getMessage(),
                    ]);
                    break;
                }
            }
            if ($sentMessageId === $draftId) {
                Log::warning('EmailController@sendTicketReply: sentMessageId tidak ditemukan, pakai draft ID', [
                    'draft_id'            => $draftId,
                    'internet_message_id' => $internetMessageId,
                ]);
            }
        }

        // ── Perbarui graph_att_id ke Sent Items attachment ID ─────────────────
        // Setelah draft dikirim, Graph mengganti attachment ID. Draft att ID → 404.
        // Fetch daftar attachment dari Sent Items message dan cocokkan berdasarkan nama file.
        if ($sentMessageId !== $draftId && !empty($attachmentRecords)) {
            try {
                $sentAtts = $this->graphGet("/users/{$sender}/messages/{$sentMessageId}/attachments", [
                    '$select' => 'id,name',
                ]);
                $sentAttMap = [];
                foreach ($sentAtts['value'] ?? [] as $sa) {
                    $sentAttMap[strtolower($sa['name'] ?? '')] = $sa['id'];
                }
                foreach ($attachmentRecords as &$rec) {
                    $key = strtolower($rec['name'] ?? '');
                    if (isset($sentAttMap[$key])) {
                        $rec['graph_att_id'] = $sentAttMap[$key];
                    }
                }
                unset($rec);
            } catch (\Exception $e) {
                Log::warning('EmailController@sendTicketReply: gagal fetch Sent Items attachment IDs', [
                    'sent_message_id' => $sentMessageId,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return [
            'graph_message_id'    => $sentMessageId,   // ID di Sent Items (bukan draft ID)
            'conversation_id'     => $conversationId,
            'internet_message_id' => $internetMessageId,
            'attachments'         => $attachmentRecords,
        ];
    }

    /**
     * Ekstrak semua inline base64 images dari HTML.
     *
     * Setiap <img src="data:image/...;base64,..."> diganti dengan
     * <img src="cid:{uuid}@ecosys"> dan data aslinya dikembalikan sebagai
     * array agar bisa dilampirkan sebagai fileAttachment isInline di Graph API.
     *
     * @param  string $html
     * @return array{html: string, inlineImages: list<array{cid:string, mime:string, content:string, name:string}>}
     */
    private function extractBase64Images(string $html): array
    {
        $inlineImages = [];

        $modifiedHtml = preg_replace_callback(
            '/<img([^>]*?)\s+src="data:(image\/[a-zA-Z+\-.]+);base64,([A-Za-z0-9+\/=\s]+)"([^>]*?)>/i',
            function ($matches) use (&$inlineImages) {
                $before  = $matches[1];
                $mime    = $matches[2];
                $content = preg_replace('/\s+/', '', $matches[3]); // strip whitespace from base64
                $after   = $matches[4];

                $ext = strtolower(explode('/', $mime)[1] ?? 'png');
                $ext = match ($ext) {
                    'jpeg', 'jpg' => 'jpg',
                    'png'         => 'png',
                    'gif'         => 'gif',
                    'webp'        => 'webp',
                    'svg+xml'     => 'svg',
                    default       => 'png',
                };

                $cid = 'img-' . \Illuminate\Support\Str::uuid() . '@ecosys';

                $inlineImages[] = [
                    'cid'     => $cid,
                    'mime'    => $mime,
                    'content' => $content,
                    'name'    => 'image.' . $ext,
                ];

                return '<img' . $before . ' src="cid:' . $cid . '"' . $after . '>';
            },
            $html
        );

        return [
            'html'         => $modifiedHtml ?? $html,
            'inlineImages' => $inlineImages,
        ];
    }
}
