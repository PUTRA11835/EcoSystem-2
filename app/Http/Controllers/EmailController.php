<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Customer;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Services\StagingTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        $tenantId = config('services.microsoft_graph.tenant_id');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.microsoft_graph.client_id'),
                'client_secret' => config('services.microsoft_graph.client_secret'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to obtain Microsoft Graph access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Public wrapper agar controller lain bisa mendapatkan Graph access token.
     */
    public function getAccessTokenPublic(): string
    {
        return $this->getAccessToken();
    }

    /**
     * Public wrapper untuk graphGet — digunakan controller lain yang butuh raw Graph call.
     */
    public function graphGetPublic(string $path, array $query = []): array
    {
        return $this->graphGet($path, $query);
    }

    /**
     * Kembalikan daftar attachment NON-INLINE dari sebuah message Graph API.
     * Digunakan untuk menampilkan attachment di modal validasi staging ticket.
     *
     * @return array  [{id, name, size, contentType, isInline}, ...]
     */
    public function listNonInlineAttachments(string $graphMsgId): array
    {
        $sender = config('services.microsoft_graph.sender_email');
        $result = $this->graphGet(
            "/users/{$sender}/messages/{$graphMsgId}/attachments",
            ['$select' => 'id,name,size,contentType,isInline']
        );

        return array_values(array_filter(
            $result['value'] ?? [],
            fn ($att) => empty($att['isInline'])
        ));
    }

    private function graphGet(string $path, array $query = [], array $headers = []): array
    {
        $token   = $this->getAccessToken();
        $baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

        $http = Http::withToken($token);
        if (!empty($headers)) {
            $http = $http->withHeaders($headers);
        }
        $response = $http->get("{$baseUrl}{$path}", $query);

        if (!$response->successful()) {
            throw new \RuntimeException("Graph GET {$path} failed: " . $response->body());
        }

        return $response->json();
    }

    private function graphPost(string $path, array $body): \Illuminate\Http\Client\Response
    {
        $token   = $this->getAccessToken();
        $baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

        $response = Http::withToken($token)->post("{$baseUrl}{$path}", $body);

        if (!$response->successful()) {
            throw new \RuntimeException("Graph POST {$path} failed: " . $response->body());
        }

        return $response;
    }

    private function graphPatch(string $path, array $body): void
    {
        $token   = $this->getAccessToken();
        $baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

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

        // ── Potong di BATAS quote (penting untuk Outlook/Exchange) ────────────
        // Outlook (OWA & "new Outlook") menaruh balasan baru DI ATAS marker
        // <div id="appendonsend"></div> lalu <hr> + header "From:/Sent:/To:"
        // (<div id="divRplyFwdMsg">) dan body quoted di bawahnya — sering TANPA
        // <blockquote>. Pendekatan hapus-selector saja tidak cukup → quote bocor
        // ke gelembung chat ("kotak biru"). Maka kita potong marker batas + SEMUA
        // node setelahnya. Gmail tetap aman karena marker ini tidak ada di sana
        // (ditangani oleh selector di bawah).
        $bodyNode = $dom->getElementsByTagName('body')->item(0);
        if ($bodyNode) {
            $this->cutAtQuoteBoundary($xpath, $bodyNode);
        }

        // Elemen yang mengandung quoted/previous messages — hapus semua
        $removeSelectors = [
            '//blockquote',                                   // RFC standard, semua klien
            '//*[contains(@class,"gmail_quote")]',            // Gmail
            '//*[contains(@class,"yahoo_quoted")]',           // Yahoo Mail
            '//*[contains(@class,"moz-cite-prefix")]',        // Thunderbird
            '//*[@id="divRplyFwdMsg" or @id="appendonsend"]', // Outlook Web (header/marker batas)
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

    /**
     * Cari marker batas quote (gaya Outlook/Exchange) lalu hapus marker + SEMUA node
     * setelahnya (dalam urutan dokumen), naik dari posisi marker sampai level <body>.
     * Konten balasan BARU (yang selalu berada di atas marker) tetap utuh.
     *
     * Marker yang dideteksi:
     *  - <div id="appendonsend"> — titik sisip quote di Outlook/OWA
     *  - <div id="divRplyFwdMsg"> — blok header "From:/Sent:/To:/Subject:"
     * Sekaligus membuang <hr> pemisah yang biasanya tepat sebelum marker.
     */
    private function cutAtQuoteBoundary(\DOMXPath $xpath, \DOMNode $bodyNode): void
    {
        $boundary = null;
        foreach (['//*[@id="appendonsend"]', '//*[@id="divRplyFwdMsg"]'] as $q) {
            $nodes = $xpath->query($q);
            if ($nodes && $nodes->length > 0) {
                $boundary = $nodes->item(0);
                break;
            }
        }
        if (!$boundary) return;

        // Buang <hr> pemisah (dan whitespace) tepat sebelum marker di level yang sama.
        $prev = $boundary->previousSibling;
        while ($prev) {
            $before = $prev->previousSibling;
            $isHr        = ($prev->nodeType === XML_ELEMENT_NODE && strtolower($prev->nodeName) === 'hr');
            $isWhitespace= ($prev->nodeType === XML_TEXT_NODE && trim($prev->textContent) === '');
            if ($isHr || $isWhitespace) {
                $prev->parentNode->removeChild($prev);
                if ($isHr) break;       // cukup satu hr pemisah
            } else {
                break;
            }
            $prev = $before;
        }

        // Hapus marker + semua node setelahnya. Di tiap level ancestor (sampai body),
        // hapus sibling yang mengikuti — TANPA menghapus ancestor itu sendiri (ancestor
        // masih memuat balasan baru yang ada sebelum marker).
        $current = $boundary;
        $removeSelf = true;
        while ($current && $current !== $bodyNode) {
            while ($current->nextSibling) {
                $current->parentNode->removeChild($current->nextSibling);
            }
            $parent = $current->parentNode;
            if ($removeSelf) {
                $parent->removeChild($current);
                $removeSelf = false;
            }
            $current = $parent;
        }
    }

    // =========================================================================
    // PUBLIC ENDPOINTS
    // =========================================================================

    /**
     * Ambil daftar pesan terbaru dari inbox (untuk debug / preview).
     */
    public function inbox()
    {
        $email = config('services.microsoft_graph.sender_email');

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

        $sender = config('services.microsoft_graph.sender_email');

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

        $sender  = config('services.microsoft_graph.sender_email');
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
        // Lepas session lock sebelum hit external API agar tab/request lain tidak ikut terblokir
        session()->save();

        // Cegah proses ganda: jika scheduler + request manual datang bersamaan, skip
        $lockKey = 'process_inbox_lock';
        if (cache()->get($lockKey)) {
            return response()->json(['processed' => 0, 'skipped' => true, 'reason' => 'already_running']);
        }
        cache()->put($lockKey, true, now()->addSeconds(55));

        try {
            $sender = config('services.microsoft_graph.sender_email');

            // internetMessageHeaders dibutuhkan untuk mengekstrak In-Reply-To + References
            // agar reply customer dari Gmail/ext. client bisa di-thread ke tiket yang ada
            // (Exchange conversationId tidak reliable lintas email system).
            // sentDateTime = header Date: dari email (waktu customer kirim) — dipakai sebagai
            // acuan SLA agar timezone customer apapun tetap dikonversi ke WIB.
            $select      = 'id,subject,from,ccRecipients,sentDateTime,receivedDateTime,body,internetMessageId,conversationId,hasAttachments,internetMessageHeaders';
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
                    // sentDateTime = header Date: dari email (waktu customer kirim, UTC).
                    // Dipakai sebagai acuan SLA — timezone customer apapun akan dikonversi ke WIB.
                    // receivedDateTime dipakai sebagai fallback jika sentDateTime tidak tersedia.
                    $sentAt = isset($msg['sentDateTime'])
                        ? \Carbon\Carbon::parse($msg['sentDateTime'])->utc()
                        : null;
                    $receivedAt = isset($msg['receivedDateTime'])
                        ? \Carbon\Carbon::parse($msg['receivedDateTime'])->utc()
                        : null;
                    // emailAt = waktu acuan email (sentDateTime jika ada, fallback ke receivedDateTime)
                    $emailAt = $sentAt ?? $receivedAt;

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

                    // Ekstrak In-Reply-To dan References dari SMTP headers
                    // Dibutuhkan untuk threading lintas email system (Gmail → M365)
                    // karena Exchange conversationId tidak reliable untuk email eksternal.
                    $inReplyToId    = null; // ID pesan yang dibalas (<...@...>)
                    $referencesIds  = [];   // semua ID dalam rantai thread
                    foreach ($msg['internetMessageHeaders'] ?? [] as $header) {
                        $headerName = strtolower($header['name'] ?? '');
                        $headerVal  = $header['value'] ?? '';
                        if ($headerName === 'in-reply-to') {
                            // Format: <id@domain> atau plain id@domain
                            preg_match('/<([^>]+)>/', $headerVal, $m2);
                            $inReplyToId = isset($m2[1]) ? '<' . $m2[1] . '>' : trim($headerVal);
                        }
                        if ($headerName === 'references') {
                            // Format: <id1> <id2> <id3> ...
                            preg_match_all('/<([^>]+)>/', $headerVal, $refMatches);
                            $referencesIds = array_map(fn($id) => '<' . $id . '>', $refMatches[1] ?? []);
                        }
                    }

                    // ── Cari tiket terkait — 5 strategi, prioritas dari paling tepat ─────────
                    $ticket = null;

                    // 1) conversationId Exchange — paling cepat, andal untuk Outlook-ke-Outlook
                    if ($conversationId) {
                        $ticket = Ticket::where('email_thread_id', $conversationId)->first();
                    }

                    // 2) In-Reply-To SMTP header — andal lintas email system (Gmail, Yahoo, dll.)
                    //    Cek apakah ada ticket_message dengan email_message_id = In-Reply-To
                    if (!$ticket && $inReplyToId) {
                        $ticket = Ticket::whereHas('messages', function ($q) use ($inReplyToId) {
                            $q->where('email_message_id', $inReplyToId);
                        })->first();
                    }

                    // 3) References SMTP header — seluruh rantai thread, pakai ID terbaru dulu
                    //    Berguna jika pelanggan membalas email yang jauh di rantai
                    if (!$ticket && !empty($referencesIds)) {
                        // Coba dari ID terbaru (akhir array) agar lebih spesifik
                        foreach (array_reverse($referencesIds) as $refId) {
                            $ticket = Ticket::whereHas('messages', function ($q) use ($refId) {
                                $q->where('email_message_id', $refId);
                            })->first();
                            if ($ticket) break;
                        }
                    }

                    // 4) internetMessageId email ini sendiri — dedup check saja
                    //    (jika sudah ada, akan di-skip di bagian bawah)
                    if (!$ticket && $internetMsgId) {
                        $ticket = Ticket::whereHas('messages', function ($q) use ($internetMsgId) {
                            $q->where('email_message_id', $internetMsgId);
                        })->first();
                    }

                    // 5) Fallback: cek staging yang sudah approved dengan conversationId yang sama
                    //    Menangani kasus ticket.email_thread_id belum di-update saat approve
                    if (!$ticket && $conversationId) {
                        $approvedStaging = \App\Models\StagingTicket::where('email_thread_id', $conversationId)
                            ->where('status', 'approved')
                            ->whereNotNull('ticket_id')
                            ->first();
                        if ($approvedStaging) {
                            $ticket = Ticket::find($approvedStaging->ticket_id);
                        }
                    }

                    Log::info('EmailController@processInbox: thread lookup result', [
                        'from'           => $fromEmail,
                        'subject'        => $subject,
                        'conversation_id'=> $conversationId,
                        'in_reply_to'    => $inReplyToId,
                        'references'     => array_slice($referencesIds, -3), // 3 ID terbaru saja
                        'ticket_found'   => $ticket?->ticket_id,
                    ]);

                    // Cari customer dengan urutan prioritas:
                    //   1. customer.email exact match (company email langsung)
                    //   2. auth_users.email exact match (login account terdaftar)
                    //   3. customer.domain match (semua email dengan domain yang sama)
                    //
                    // Match #3 menangani kasus customer company dengan banyak karyawan: alih-alih
                    // mendaftarkan setiap email karyawan sebagai contact person, admin cukup mengisi
                    // field `domain` di master customer (mis. "@apta.co.id") agar SEMUA email dari
                    // domain tsb (charli@apta.co.id, akbar@apta.co.id, dll) otomatis dikenali
                    // sebagai milik customer ybs dan masuk ke staging ticket validation.
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
                    // 3b. customer_contact.email_work / email_personal — contact person terdaftar.
                    //     Menangani contact person yang TIDAK punya akun login (auth_users) DAN
                    //     memakai email di luar domain customer (mis. konsultan ber-Gmail).
                    //     Dicek sebelum domain karena exact email lebih spesifik daripada domain.
                    if (!$customer && $fromEmail) {
                        $contactCustomerId = \DB::table('customer_contact')
                            ->where(function ($q) use ($fromEmail) {
                                $q->whereRaw('LOWER(email_work) = LOWER(?)', [$fromEmail])
                                  ->orWhereRaw('LOWER(email_personal) = LOWER(?)', [$fromEmail]);
                            })
                            ->whereNotNull('customer_id')
                            ->value('customer_id');
                        if ($contactCustomerId) {
                            $customer = Customer::find($contactCustomerId);
                            if ($customer) {
                                Log::info('EmailController@processInbox: customer matched by contact person', [
                                    'from'        => $fromEmail,
                                    'customer_id' => $customer->customer_id,
                                ]);
                            }
                        }
                    }
                    if (!$customer && $fromEmail) {
                        $emailDomain = Customer::extractEmailDomain($fromEmail); // mis. "@apta.co.id"
                        if ($emailDomain) {
                            // Prefer top-level customer (parent_customer_id null) jika ada
                            // subsidiary yang share domain yang sama (parent + child).
                            $customer = Customer::whereRaw('LOWER(domain) = ?', [$emailDomain])
                                ->where('is_active', true)
                                ->orderByRaw('parent_customer_id IS NULL DESC')
                                ->orderBy('customer_id', 'asc')
                                ->first();
                            if ($customer) {
                                Log::info('EmailController@processInbox: customer matched by domain', [
                                    'from'        => $fromEmail,
                                    'domain'      => $emailDomain,
                                    'customer_id' => $customer->customer_id,
                                ]);
                            }
                        }
                    }

                    if ($ticket) {
                        // Dedup: skip jika message dengan ID ini sudah ada
                        if ($internetMsgId && TicketMessage::where('email_message_id', $internetMsgId)->exists()) {
                            $this->graphPatch("/users/{$sender}/messages/{$graphMsgId}", ['isRead' => true]);
                            $skipped++;
                            continue;
                        }

                        // Atomic insert: message + attachments + cid replacement + ticket update
                        // dibungkus transaction supaya polling client (Jarvies/EcoSystem) tidak
                        // melihat row baru sampai message_html sudah selesai di-replace dari
                        // cid:xxx ke proxy URL. Tanpa ini, polling yang fire selama Graph API
                        // attachment download (~1-3 detik) akan render gambar dengan src="cid:xxx"
                        // yang broken di browser, dan karena polling bersifat incremental append-only,
                        // tampilan rusak itu menetap sampai user manual refresh halaman.
                        $savedMessage = null;
                        \DB::transaction(function () use (
                            $ticket, $customer, $fromEmail, $fromName,
                            $bodyPlain, $bodyHtml, $internetMsgId, $ccEmails,
                            $receivedAt, $hasAttachments, $sender, $graphMsgId, $conversationId,
                            &$savedMessage
                        ) {
                            // Tambah pesan ke tiket yang sudah ada
                            // cc_emails: kirim PHP array (bukan JSON string) karena TicketMessage
                            // model punya 'array' cast — mengirim JSON string akan double-encode
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
                                'cc_emails'           => !empty($ccEmails) ? $ccEmails : null,
                                'is_read_by_customer' => true,
                                'is_read_by_agent'    => false,
                            ]);

                            // Gunakan receivedDateTime (waktu Exchange menerima email) untuk created_at pesan.
                            // BUKAN sentDateTime — customer di timezone lain bisa punya sentDateTime
                            // lebih awal dari pesan-pesan yang sudah ada, sehingga pesannya muncul
                            // di atas (urutan salah) di room chat.
                            // receivedDateTime selalu kronologis sesuai urutan Exchange menerima email.
                            // PENTING: Carbon UTC → konversi ke WIB sebelum disimpan via raw DB::table()
                            // karena Eloquent membaca string DB tanpa konversi UTC→WIB.
                            if ($receivedAt) {
                                $appTz     = config('app.timezone', 'Asia/Jakarta');
                                $localTime = $receivedAt->copy()->setTimezone($appTz)->toDateTimeString();
                                \DB::table('ticket_message')->where('id', $message->id)->update([
                                    'created_at' => $localTime,
                                    'updated_at' => $localTime,
                                ]);
                                $message->created_at = $receivedAt->copy()->setTimezone($appTz);
                                $message->updated_at = $receivedAt->copy()->setTimezone($appTz);
                            }

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

                            // Merge CC dari reply customer ke ticket.cc_emails — agar helpdesk
                            // reply form otomatis show CC terbaru. Normalize ke format {address,name},
                            // dedup by address (case-insensitive), exclude helpdesk sendiri.
                            $ticketCcUpdate = [
                                'last_customer_reply_at' => now(),
                                'last_message_at'        => now(),
                            ];

                            // Sync email_thread_id ke convId email customer reply. Jika convId
                            // berubah (misal Exchange split thread karena subject di-patch),
                            // ticket selalu reference thread aktif terakhir. Jarvies/EcoSystem
                            // reply berikutnya akan createReply pada pesan di thread yang sama.
                            if ($conversationId && $conversationId !== $ticket->email_thread_id) {
                                $ticketCcUpdate['email_thread_id'] = $conversationId;
                            }
                            if (!empty($ccEmails)) {
                                $existing = collect($ticket->cc_emails ?? [])
                                    ->map(fn ($c) => is_array($c)
                                        ? ['address' => strtolower($c['address'] ?? ''), 'name' => $c['name'] ?? null]
                                        : ['address' => strtolower((string) $c), 'name' => null])
                                    ->filter(fn ($c) => !empty($c['address']));
                                $incoming = collect($ccEmails)
                                    ->map(fn ($c) => [
                                        'address' => strtolower($c['address'] ?? ''),
                                        'name'    => $c['name'] ?? null,
                                    ])
                                    ->filter(fn ($c) => !empty($c['address']));
                                $senderLower = strtolower((string) $sender);
                                $merged = $existing->concat($incoming)
                                    ->reject(fn ($c) => $c['address'] === $senderLower)
                                    ->unique('address')
                                    ->values()
                                    ->all();
                                $ticketCcUpdate['cc_emails'] = $merged;
                            }
                            // Customer balas → kembalikan ticket ke inprocess jika sedang pause
                            $stopStatuses = ['waiting_on_customer', 'waiting_to_confirmation', 'waiting_on_3rd_party', 'hold'];
                            if ($customer && in_array($ticket->status, $stopStatuses)) {
                                $ticketCcUpdate['status'] = 'inprocess';
                            }
                            $ticket->update($ticketCcUpdate);
                            $savedMessage = $message;
                        });

                        // Trigger SLA event setelah transaction commit (non-fatal)
                        if ($savedMessage && $customer) {
                            try {
                                $ticket->refresh();
                                app(\App\Services\SlaService::class)->recordMessageEvent(
                                    $ticket,
                                    $savedMessage,
                                    'customer',
                                    null
                                );
                            } catch (\Throwable $e) {
                                Log::warning('EmailController@processInbox: SLA record gagal (non-fatal)', [
                                    'ticket_id'  => $ticket->ticket_id,
                                    'message_id' => $savedMessage->id,
                                    'error'      => $e->getMessage(),
                                ]);
                            }
                        }

                    } else {
                        // Dedup staging: skip jika sudah pernah masuk staging dengan internet_message_id ini
                        if ($internetMsgId && \App\Models\StagingTicket::where('email_message_id', $internetMsgId)->exists()) {
                            $this->graphPatch("/users/{$sender}/messages/{$graphMsgId}", ['isRead' => true]);
                            $skipped++;
                            continue;
                        }

                        // Pengirim tidak terdaftar sebagai contact person client → abaikan
                        if (!$customer) {
                            $this->graphPatch("/users/{$sender}/messages/{$graphMsgId}", ['isRead' => true]);
                            Log::info('EmailController@processInbox: pengirim tidak terdaftar sebagai customer, email dilewati', [
                                'from'    => $fromEmail,
                                'subject' => $subject,
                            ]);
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
                            'cc_emails'           => !empty($ccEmails) ? $ccEmails : null, // PHP array, bukan JSON string
                            'received_at'         => $emailAt, // sentDateTime (Date: header) → WIB untuk SLA start
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
                        'error_at' => $e->getFile() . ':' . $e->getLine(),
                    ]);
                    $errors[] = $e->getMessage();
                    $skipped++;
                }
            }

            cache()->forget($lockKey);

            return response()->json([
                'status'    => 'done',
                'processed' => $processed,
                'skipped'   => $skipped,
                'errors'    => $errors,
            ]);

        } catch (\Exception $e) {
            cache()->forget($lockKey);

            Log::error('EmailController@processInbox: Graph API gagal', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to access inbox',
            ], 500);
        }
    }

    // =========================================================================
    // SENT ITEMS PROCESSOR — link staging tickets ke email [Menunggu Validasi]
    // =========================================================================

    /**
     * Scan Sent Items Raditya untuk email "[Menunggu Validasi]" / "[PENDING]" yang
     * belum di-link ke staging ticket, lalu hubungkan berdasarkan subject + recipient.
     *
     * Endpoint: POST /api/email/process-sent
     * Dipanggil dari tombol "Fetch Email" di halaman staging validation.
     *
     * Matching:
     *   - Bersihkan prefix dari subject → cocokkan dengan staging.description
     *   - Cocokkan toRecipients[0] dengan staging.submitted_by_email
     *   - Staging harus belum punya graph_message_id (unlinked)
     *   - Dibuat dalam 7 hari terakhir
     *
     * Setelah link berhasil: staging.channel = 'email', graph_message_id tersimpan,
     * email_body_html tersimpan → modal validasi bisa render body + attachment.
     */
    public function processSentItems(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $sender     = config('services.microsoft_graph.sender_email');
            $preferHtml = ['Prefer' => 'outlook.body-content-type="html"'];
            $select     = 'id,subject,toRecipients,sentDateTime,body,internetMessageId,conversationId,hasAttachments';
            $since      = \Carbon\Carbon::now('UTC')->subDays(7)->format('Y-m-d\TH:i:s\Z');

            // Fetch 50 sent items terbaru dalam 7 hari terakhir,
            // lalu filter subject di PHP (menghindari masalah OData escaping untuk '[' dan ']')
            $data = $this->graphGet("/users/{$sender}/mailFolders/SentItems/messages", [
                '$filter'  => "sentDateTime ge {$since}",
                '$orderby' => 'sentDateTime desc',
                '$top'     => 50,
                '$select'  => $select,
            ], $preferHtml);

            // Filter: hanya email dengan prefix [Menunggu Validasi] atau [PENDING]
            $emails = array_filter(
                $data['value'] ?? [],
                fn ($e) => preg_match('/^\[(Menunggu Validasi|PENDING)\]/iu', $e['subject'] ?? '')
            );

            $totalFetched = count($data['value'] ?? []);
            $totalPending = count($emails);

            Log::info('EmailController@processSentItems: scan started', [
                'total_fetched_from_graph' => $totalFetched,
                'after_subject_filter'     => $totalPending,
                'since_utc'               => $since,
            ]);

            $linked  = 0;
            $skipped = 0;
            $errors  = [];

            foreach ($emails as $email) {
                try {
                    $graphMsgId      = $email['id'];
                    $rawSubject      = $email['subject'] ?? '';
                    $internetMsgId   = $email['internetMessageId'] ?? null;
                    $conversationId  = $email['conversationId'] ?? null;
                    $hasAttachments  = $email['hasAttachments'] ?? false;
                    $bodyHtml        = $email['body']['content'] ?? null;

                    // Ambil recipient pertama sebagai "to email"
                    $toEmail = $email['toRecipients'][0]['emailAddress']['address'] ?? null;

                    // Bersihkan prefix dari subject
                    $cleanSubject = trim(preg_replace('/^\[(Menunggu Validasi|PENDING)\]\s*/iu', '', $rawSubject));

                    // Anggap "already linked" hanya jika graph_message_id sudah terisi.
                    $alreadyLinked = \App\Models\StagingTicket::where('graph_message_id', $graphMsgId)->exists();

                    if ($alreadyLinked) {
                        Log::info('EmailController@processSentItems: skip (already linked)', [
                            'subject' => $rawSubject,
                        ]);
                        $skipped++;
                        continue;
                    }

                    // Cari staging yang cocok:
                    // 1. description == cleanSubject (case-insensitive)
                    // 2. submitted_by_email cocok ATAU null
                    // 3. dibuat dalam 7 hari terakhir
                    $staging = \App\Models\StagingTicket::whereRaw('LOWER(description) = ?', [mb_strtolower($cleanSubject)])
                        ->where(function ($q) use ($toEmail) {
                            if ($toEmail) {
                                $q->where('submitted_by_email', $toEmail)
                                  ->orWhereNull('submitted_by_email');
                            }
                        })
                        ->where('created_at', '>=', now()->subDays(7))
                        ->latest()
                        ->first();

                    if (!$staging) {
                        // Log detail kandidat staging yang ada untuk debug matching
                        $candidates = \App\Models\StagingTicket::where('created_at', '>=', now()->subDays(7))
                            ->whereNull('graph_message_id')
                            ->select('id', 'description', 'submitted_by_email', 'created_at')
                            ->get()
                            ->map(fn ($s) => [
                                'id'          => $s->id,
                                'description' => $s->description,
                                'email'       => $s->submitted_by_email,
                                'created_at'  => $s->created_at,
                            ])->toArray();

                        Log::info('EmailController@processSentItems: no matching staging for sent email', [
                            'subject'          => $rawSubject,
                            'clean_subject'    => $cleanSubject,
                            'to'               => $toEmail,
                            'graph_message_id' => $graphMsgId,
                            'unlinked_staging_candidates' => $candidates,
                        ]);
                        $skipped++;
                        continue;
                    }

                    $update = [
                        'graph_message_id' => $graphMsgId,
                        'email_thread_id'  => $conversationId,
                        'email_message_id' => $internetMsgId,
                        'channel'          => 'email',
                        'has_attachments'  => $hasAttachments || $staging->has_attachments,
                    ];
                    if ($bodyHtml) {
                        $update['email_body_html'] = $bodyHtml;
                    }
                    $staging->update($update);

                    Log::info('EmailController@processSentItems: linked staging to sent email', [
                        'staging_id'       => $staging->id,
                        'graph_message_id' => $graphMsgId,
                        'subject'          => $rawSubject,
                    ]);
                    $linked++;

                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                    Log::warning('EmailController@processSentItems: error processing email', [
                        'subject' => $email['subject'] ?? '?',
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'linked'  => $linked,
                'skipped' => $skipped,
                'total'   => count($emails),
                'errors'  => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('EmailController@processSentItems: failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sent items',
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
        $sender = config('services.microsoft_graph.sender_email');
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
        $sender = config('services.microsoft_graph.sender_email');
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
     * Ambil attachment dari Graph API dan simpan ke DB.
     *
     * - Inline image (is_inline=true, ada contentId, mime image/*):
     *   Download binary dari Graph → simpan ke storage/public → cidMap berisi storage URL.
     *   URL storage bisa diakses langsung oleh browser / Jarvies tanpa perlu session EcoSystem.
     *
     * - Non-inline / file biasa:
     *   Simpan metadata saja → cidMap berisi proxy URL /attachments/{id} (butuh session).
     *
     * @param  string         $senderEmail   email akun MS Graph (MS_SENDER_EMAIL)
     * @param  string         $graphMsgId    Graph internal message ID
     * @param  TicketMessage  $message       record pesan yang baru dibuat
     * @param  int            $ticketId
     * @return array          Mapping [contentId => replacementUrl] untuk CID replacement di HTML
     */
    private function storeEmailAttachments(string $senderEmail, string $graphMsgId, TicketMessage $message, int $ticketId): array
    {
        $cidMap = [];

        try {
            $result = $this->graphGet("/users/{$senderEmail}/messages/{$graphMsgId}/attachments");

            foreach ($result['value'] ?? [] as $att) {
                // Lewati non-fileAttachment (referenceAttachment, itemAttachment, dll)
                $odataType = $att['@odata.type'] ?? '';
                if ($odataType && !str_contains($odataType, 'fileAttachment')) {
                    continue;
                }

                if (empty($att['name'])) {
                    continue;
                }

                $graphAttId   = $att['id'];
                $originalName = $att['name'];
                $mimeType     = $att['contentType'] ?? 'application/octet-stream';
                $isInline     = $att['isInline'] ?? false;
                $fileSize     = $att['size'] ?? 0;
                $contentId    = $att['contentId'] ?? null;

                // Lewati jika sudah pernah disimpan berdasarkan graph_attachment_id
                $existing = TicketAttachment::where('graph_attachment_id', $graphAttId)->first();
                if ($existing) {
                    if ($contentId) {
                        // Gunakan storage URL jika ada file_path, fallback ke proxy
                        $cidMap[$contentId] = $existing->file_path
                            ? '/storage/' . $existing->file_path
                            : '/attachments/' . $existing->id;
                    }
                    continue;
                }

                // Dedup tambahan untuk file non-inline yang sudah di-copy dari staging_attachments.
                // staging_attachments → ticket_attachment tidak punya graph_attachment_id,
                // sehingga dedup di atas tidak menangkapnya → file yang sama akan double.
                // Solusi: cocokkan by link_title (original filename) + ticket_id.
                if (!$isInline) {
                    $existingByName = TicketAttachment::where('ticket_id', $ticketId)
                        ->where('link_title', $originalName)
                        ->whereNull('graph_attachment_id')
                        ->first();
                    if ($existingByName) {
                        // Enrich record yang sudah ada dengan Graph IDs agar proxy access bisa digunakan
                        $existingByName->update([
                            'graph_attachment_id' => $graphAttId,
                            'graph_message_id'    => $graphMsgId,
                        ]);
                        continue;
                    }
                }

                $filePath   = null;
                $storageUrl = null;

                // ── Inline image: download binary dan simpan ke storage ──────────
                if ($isInline && $contentId && str_starts_with($mimeType, 'image/')) {
                    $contentBytes = $att['contentBytes'] ?? null;

                    // Graph kadang menghapus contentBytes dari list response untuk file besar
                    if (!$contentBytes) {
                        try {
                            $single       = $this->graphGet("/users/{$senderEmail}/messages/{$graphMsgId}/attachments/{$graphAttId}");
                            $contentBytes = $single['contentBytes'] ?? null;
                            if (!$mimeType || $mimeType === 'application/octet-stream') {
                                $mimeType = $single['contentType'] ?? $mimeType;
                            }
                        } catch (\Exception $e) {
                            Log::warning('EmailController@storeEmailAttachments: gagal fetch individual inline attachment', [
                                'att_id' => $graphAttId,
                                'error'  => $e->getMessage(),
                            ]);
                        }
                    }

                    if ($contentBytes) {
                        try {
                            $ext        = pathinfo($originalName, PATHINFO_EXTENSION) ?: 'png';
                            $safeName   = Str::uuid() . '.' . $ext;
                            $filePath   = "ticket-inline-images/{$ticketId}/{$safeName}";
                            Storage::disk('public')->put($filePath, base64_decode($contentBytes));
                            $storageUrl = '/storage/' . $filePath;

                            Log::info('EmailController@storeEmailAttachments: inline image disimpan ke storage', [
                                'ticket_id' => $ticketId,
                                'file_path' => $filePath,
                                'mime'      => $mimeType,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('EmailController@storeEmailAttachments: gagal simpan inline image ke storage', [
                                'error' => $e->getMessage(),
                            ]);
                            $filePath   = null;
                            $storageUrl = null;
                        }
                    }
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
                    'file_path'           => $filePath,
                ]);

                if ($contentId) {
                    // Inline image yang berhasil disimpan → storage URL (akses publik)
                    // Selainnya → proxy URL (butuh session EcoSystem)
                    $cidMap[$contentId] = $storageUrl ?? '/attachments/' . $record->id;
                }
            }
        } catch (\Exception $e) {
            Log::warning('EmailController@storeEmailAttachments: gagal ambil metadata attachment', [
                'graph_msg_id' => $graphMsgId,
                'ticket_id'    => $ticketId,
                'error'        => $e->getMessage(),
            ]);
        }

        return $cidMap;
    }

    /**
     * Ganti referensi cid:xxx di HTML email dengan URL replacement.
     * Dipanggil setelah storeEmailAttachments() berhasil menyimpan record.
     *
     * @param  string $html       HTML body pesan
     * @param  array  $cidUrlMap  [contentId => url] — storage URL (inline) atau proxy URL
     * @return string HTML yang sudah diganti referensi cid-nya
     */
    private function replaceCidReferences(string $html, array $cidUrlMap): string
    {
        foreach ($cidUrlMap as $cid => $url) {
            // Graph bisa mengembalikan contentId dengan atau tanpa angle brackets: <xxx> atau xxx
            $cleanCid = trim($cid, '<>');
            // Replace semua variasi yang mungkin muncul di HTML
            $html = str_replace('cid:' . $cleanCid,        $url, $html);
            $html = str_replace('cid:<' . $cleanCid . '>', $url, $html);
            if ($cid !== $cleanCid) {
                $html = str_replace('cid:' . $cid, $url, $html);
            }
        }
        return $html;
    }

    /**
     * Tentukan attachment_type berdasarkan MIME type.
     */
    public function resolveAttachmentTypePublic(string $mimeType): string
    {
        return $this->resolveAttachmentType($mimeType);
    }

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
        if (!$sessionUser || !in_array($sessionUser['role']['id'], array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_USER->value], RoleId::HELPDESK_GROUP), true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $message = TicketMessage::findOrFail($messageId);

        if ($message->channel !== 'email' || !$message->email_message_id) {
            return response()->json(['success' => false, 'message' => 'This message is not from an email or has no email_message_id'], 422);
        }

        $sender = config('services.microsoft_graph.sender_email');

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
            return response()->json(['success' => false, 'message' => 'Failed to reprocess email attachments. Check the server logs for details.'], 500);
        }
    }

    /**
     * Bandingkan "topic" dua subject email — abaikan prefix balas/teruskan (Re:, Fw:, Fwd:,
     * Bls:, dll), spasi berlebih, dan perbedaan huruf besar/kecil.
     *
     * Dipakai untuk memutuskan apakah subject draft reply perlu di-PATCH. Meng-PATCH subject
     * draft createReply memicu Exchange me-reset Thread-Index/conversationId sehingga thread
     * pecah di klien Outlook/Exchange. Jika topic-nya sudah sama (mis. Exchange sudah set
     * "Re: Ticket #XXXX: ..." dan kita ingin "Ticket #XXXX: ..."), subject TIDAK perlu diubah.
     */
    private function subjectTopicMatches(?string $a, ?string $b): bool
    {
        if ($a === null || $b === null) return false;
        $na = $this->normalizeSubjectTopic($a);
        $nb = $this->normalizeSubjectTopic($b);
        return $na !== '' && $na === $nb;
    }

    /**
     * Normalisasi subject ke "topic": buang prefix balas/teruskan berulang di awal,
     * rapikan whitespace, dan lower-case.
     */
    private function normalizeSubjectTopic(string $subject): string
    {
        $s = preg_replace('/^(?:\s*(?:re|fw|fwd|bls|aw|sv|antw)\s*:\s*)+/i', '', trim($subject));
        $s = preg_replace('/\s+/u', ' ', trim((string) $s));
        return mb_strtolower((string) $s, 'UTF-8');
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
        array $ccList = [],       // [{name, address}, ...] atau [address, ...]
        bool $noRePrefix = false,
        ?string $threadId = null, // M365 conversationId — fallback jika inReplyTo tidak ditemukan
        bool $forceNewDraft = false, // true = skip createReply, buat draft baru dengan In-Reply-To eksplisit
                                     // Gunakan saat subject sengaja diubah agar Gmail tetap thread dengan benar
        array $rawAttachments = [], // [{name, mime, bytes}] untuk forward attachment dari email asal
        array $additionalToEmails = [] // alamat tambahan untuk toRecipients selain $toEmail
    ): array {
        $sender         = config('services.microsoft_graph.sender_email');
        $replySubject   = (!$noRePrefix && stripos($subject, 're:') !== 0) ? 'Re: ' . $subject : $subject;
        $draftId        = null;
        $conversationId = null;

        // ── Bangun daftar toRecipients (primary + additional, dedup, exclude self) ──
        // Format: [['emailAddress' => ['address' => 'foo@bar']], ...]
        // Primary $toEmail SELALU di posisi pertama agar Reply/Reply-All di mail client
        // memprioritaskan customer. Additional address ditambah jika valid & belum ada.
        $senderLower    = strtolower((string) $sender);
        $toRecipients   = [['emailAddress' => ['address' => $toEmail]]];
        $seenToLower    = [strtolower(trim($toEmail))];
        foreach ($additionalToEmails as $addr) {
            $cleaned = is_string($addr) ? trim($addr) : '';
            if ($cleaned === '') continue;
            $cleanedLower = strtolower($cleaned);
            if (in_array($cleanedLower, $seenToLower, true)) continue;
            if ($cleanedLower === $senderLower) continue;  // jangan kirim ke helpdesk sendiri
            if (!filter_var($cleaned, FILTER_VALIDATE_EMAIL)) continue;
            $toRecipients[] = ['emailAddress' => ['address' => $cleaned]];
            $seenToLower[]  = $cleanedLower;
        }

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
        // Email clients block data URI images; replace with cid: references.
        // Naikkan PCRE backtrack limit sementara agar regex bisa menangani base64 gambar besar
        // (default 1M karakter bisa tidak cukup untuk foto berukuran > 750 KB).
        $prevPcreLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', 10000000);
        $extracted    = $this->extractBase64Images($body);
        ini_set('pcre.backtrack_limit', $prevPcreLimit);
        $cleanBody    = $extracted['html'];
        $inlineImages = $extracted['inlineImages'];

        // ── Convert <img src="/storage/..."> ke CID inline attachment ─────────
        // Email clients tidak bisa load URL relatif, dan APP_URL public sering tidak
        // reachable dari Gmail/Outlook (server private, symlink /storage hilang, proxy
        // butuh session). CID inline membuat email self-contained — gambar selalu tampil
        // tanpa bergantung pada accessibility server.
        // Sumber URL ini biasanya dari processAttachmentsForMessage() yang menyimpan
        // inline image dari Graph ke /storage/ticket-inline-images/{ticketId}/.
        $storageExtracted = $this->extractLocalStorageImages($cleanBody);
        $cleanBody        = $storageExtracted['html'];
        $inlineImages     = array_merge($inlineImages, $storageExtracted['inlineImages']);

        // ── Coba buat reply draft dari pesan asli (untuk threading yang benar) ──
        //
        // createReply digunakan untuk mendapat In-Reply-To + References + conversationId otomatis.
        // SETELAH createReply, toRecipients SELALU di-patch ke $toEmail (customer) karena:
        //   - Inbox message: createReply sudah benar (reply ke customer yg kirim) → patch confirm
        //   - SentItems msg: createReply salah arah (ke raditya sendiri) → patch fix ke customer
        //
        // Dengan selalu patch toRecipients, kita tidak perlu membedakan Inbox vs SentItems.
        if ($inReplyTo && !$forceNewDraft) {
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
                    // Subject: secara default TIDAK dioverride agar conversationId Exchange tetap
                    // terjaga (threading di Outlook). Graph auto-set "Re: {original_subject}".
                    //
                    // PENGECUALIAN: jika $noRePrefix=true, caller menginginkan subject spesifik
                    // (contoh: approval email dengan "Ticket #XXXX: desc"). Dalam kasus ini kita
                    // patch subject. SMTP headers In-Reply-To & References sudah tertanam saat
                    // createReply → tetap ada setelah patch subject → Gmail/client SMTP tetap thread.
                    // Exchange conversationId akan berubah (Outlook mungkin tampilkan sebagai
                    // thread terpisah) tapi ini trade-off yang diterima untuk subject yang benar.

                    // PATCH: body, toRecipients, ccRecipients SELALU. Subject KONDISIONAL.
                    // internetMessageHeaders TIDAK di-patch — field ini read-only pada createReply draft.
                    // Exchange sudah otomatis set In-Reply-To + References yang benar dari originalId.
                    //
                    // FIX THREADING OUTLOOK/EXCHANGE:
                    // Meng-PATCH subject draft createReply memicu Exchange me-RESET Thread-Index +
                    // conversationId. Akibatnya klien Outlook/Exchange (mis. customer dengan domain
                    // sendiri seperti @apta.id, termasuk grouping & parent-child) menampilkan SETIAP
                    // balasan sebagai percakapan TERPISAH. Gmail tetap menyatukan thread lewat header
                    // References (yang ikut tertanam saat createReply & bertahan setelah patch),
                    // sehingga bug ini "tersembunyi" ketika diuji pakai Gmail.
                    //
                    // Solusi: subject HANYA di-patch jika thread belum membawa identitas tiket yang
                    // diinginkan ("Ticket #XXXX: ..."). Saat Exchange sudah meng-set subject reply
                    // menjadi "Re: Ticket #XXXX: ..." (topic-nya sama dengan yang diminta), JANGAN
                    // patch subject → Thread-Index terjaga → semua klien (Outlook, Gmail, dll) tetap
                    // satu thread. Injeksi identitas tiket cukup terjadi sekali (email pertama thread).
                    $patchData = [
                        'body'         => ['contentType' => 'HTML', 'content' => $cleanBody],
                        'toRecipients' => $toRecipients,
                        'ccRecipients' => $ccRecipients,
                    ];
                    if (!$this->subjectTopicMatches($draft->json('subject'), $replySubject)) {
                        $patchData['subject'] = $replySubject;
                    }
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
        if (!$draftId && $threadId && !$forceNewDraft) {
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
                    // Subject KONDISIONAL — lihat penjelasan di blok createReply di atas.
                    // Hindari patch subject agar Thread-Index Exchange tidak ter-reset (Outlook
                    // tetap satu thread).
                    $patchData = [
                        'body'         => ['contentType' => 'HTML', 'content' => $cleanBody],
                        'toRecipients' => $toRecipients,
                        'ccRecipients' => $ccRecipients,
                    ];
                    if (!$this->subjectTopicMatches($draft->json('subject'), $replySubject)) {
                        $patchData['subject'] = $replySubject;
                    }
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
        // Email baru tanpa threading — Graph API melarang header 'In-Reply-To'/'References'
        // di internetMessageHeaders (hanya 'x-*' yang diizinkan).
        // toRecipients = $toEmail (selalu customer, tidak pernah raditya sendiri).
        if (!$draftId) {
            // CATATAN: Graph API hanya izinkan header dengan prefix 'x-' atau 'X-' di internetMessageHeaders.
            // Header standard seperti 'In-Reply-To' dan 'References' TIDAK bisa di-set manual di sini.
            // Threading via SMTP header hanya bisa dilakukan melalui createReply (path di atas).
            // Fallback ini mengirim email baru tanpa threading — lebih baik daripada 500 error.
            $headers = [
                ['name' => 'X-Mailer',        'value' => 'EcoSystem-Helpdesk'],
                ['name' => 'X-Entity-Ref-ID', 'value' => uniqid('ecosys-', true)],
            ];
            if ($inReplyTo) {
                // Simpan reference sebagai custom header (tidak untuk threading, hanya untuk traceability)
                $headers[] = ['name' => 'X-Original-Message-ID', 'value' => $inReplyTo];
            }
            $newMsgPayload = [
                'subject'                => $replySubject,
                'body'                   => ['contentType' => 'HTML', 'content' => $cleanBody],
                'toRecipients'           => $toRecipients,
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

        // ── Lampirkan raw attachments (forwarded dari email asal) ─────────────
        foreach ($rawAttachments as $rawAtt) {
            try {
                $this->graphPost("/users/{$sender}/messages/{$draftId}/attachments", [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name'        => $rawAtt['name'],
                    'contentType' => $rawAtt['mime'] ?? 'application/octet-stream',
                    'contentBytes'=> base64_encode($rawAtt['bytes']),
                ]);
            } catch (\Exception $e) {
                Log::warning('EmailController@sendTicketReply: gagal lampirkan raw attachment', [
                    'name'  => $rawAtt['name'] ?? '?',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── Ambil internetMessageId dari draft sebelum dikirim ────────────────
        // internetMessageId diperlukan oleh Jarvies sebagai In-Reply-To header
        // agar balasan customer masuk ke thread yang sama di Gmail/Outlook.
        //
        // CATATAN PENTING — conversationId post-patch:
        // Jika subject di-patch (kasus $noRePrefix=true, contoh "Ticket #XXXX: ..."),
        // Exchange akan assign conversationId BARU saat draft dikirim. convId yang
        // didapat dari createReply (sebelum patch) jadi STALE. Kita harus selalu
        // ambil convId terbaru, bukan hanya saat null — kalau tidak, ticket.email_thread_id
        // akan tetap menunjuk ke thread lama ("[Pending Validation]") dan reply
        // berikutnya dari Jarvies akan threading ke thread yang salah.
        $internetMessageId = null;
        try {
            $draftInfo = $this->graphGet("/users/{$sender}/messages/{$draftId}", [
                '$select' => 'internetMessageId,conversationId',
            ]);
            $internetMessageId = $draftInfo['internetMessageId'] ?? null;
            if (!empty($draftInfo['conversationId'])) {
                $conversationId = $draftInfo['conversationId'];
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
                        '$select'  => 'id,internetMessageId,conversationId',
                        '$top'     => 20,
                    ]);
                    foreach ($searchResult['value'] ?? [] as $msg) {
                        if (($msg['internetMessageId'] ?? '') === $internetMessageId) {
                            $sentMessageId = $msg['id'];
                            // convId final dari Sent Items — ini yang authoritative.
                            // Setelah subject patch, Exchange bisa assign convId baru saat
                            // draft dikirim. Update ke convId final agar caller (yang
                            // menyimpan ke ticket.email_thread_id) ref ke thread aktif.
                            if (!empty($msg['conversationId'])) {
                                $conversationId = $msg['conversationId'];
                            }
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
    /**
     * Convert <img src="/storage/..."> references (relative OR absolute with app.url host)
     * to inline CID attachments. Email clients can't load relative URLs, dan public APP_URL
     * sering tidak reachable dari Gmail/Outlook (server private, symlink /storage hilang,
     * proxy require session). Embedding sebagai CID membuat email self-contained.
     *
     * Composes dengan extractBase64Images() — keduanya menghasilkan format
     * {cid, mime, content, name} yang sama untuk loop CID-attach di sendTicketReply().
     *
     * @return array{html: string, inlineImages: array}
     */
    private function extractLocalStorageImages(string $html): array
    {
        $inlineImages = [];

        // Match liberal: any optional scheme://host[:port] before /storage/<path>.
        // File existence check below acts as the safeguard against false positives.
        // [^/"\s] = chars that aren't path-separator, quote, or whitespace (covers any host:port).
        // ([^"]+) captures full path-including-query — strip query/fragment in callback.
        $pattern    = '~<img\b([^>]*?)\s+src="(?:https?://[^/"\s]+)?/storage/([^"]+)"([^>]*?)>~i';
        $matchCount = 0;

        $modifiedHtml = preg_replace_callback(
            $pattern,
            function ($matches) use (&$inlineImages, &$matchCount) {
                $matchCount++;
                $before   = $matches[1];
                // Strip query string / fragment before resolving file path on disk
                $rawPath  = preg_replace('~[?#].*$~', '', $matches[2]);
                $filePath = urldecode($rawPath);
                $after    = $matches[3];

                try {
                    if (!Storage::disk('public')->exists($filePath)) {
                        Log::info('EmailController@extractLocalStorageImages: file tidak ada di public disk, URL dibiarkan', [
                            'file_path' => $filePath,
                        ]);
                        return $matches[0];
                    }
                    $content = Storage::disk('public')->get($filePath);
                    $mime    = Storage::disk('public')->mimeType($filePath) ?: 'image/png';
                    if (!str_starts_with($mime, 'image/')) {
                        return $matches[0];
                    }
                } catch (\Throwable $e) {
                    Log::warning('EmailController@extractLocalStorageImages: gagal baca file', [
                        'file_path' => $filePath,
                        'error'     => $e->getMessage(),
                    ]);
                    return $matches[0];
                }

                $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION) ?: 'png');
                $cid  = 'img-' . Str::uuid() . '@ecosys';
                $name = basename($filePath) ?: ('image.' . $ext);

                $inlineImages[] = [
                    'cid'     => $cid,
                    'mime'    => $mime,
                    'content' => base64_encode($content),
                    'name'    => $name,
                ];

                Log::info('EmailController@extractLocalStorageImages: gambar dikonversi ke CID', [
                    'file_path' => $filePath,
                    'cid'       => $cid,
                    'size'      => strlen($content),
                ]);

                return '<img' . $before . ' src="cid:' . $cid . '"' . $after . '>';
            },
            $html
        );

        Log::info('EmailController@extractLocalStorageImages: selesai scan body', [
            'regex_matches'        => $matchCount,
            'converted_to_cid'     => count($inlineImages),
            'body_has_storage_url' => str_contains($html, '/storage/'),
            'body_has_img_tag'     => str_contains(strtolower($html), '<img'),
        ]);

        return [
            'html'         => $modifiedHtml ?? $html,
            'inlineImages' => $inlineImages,
        ];
    }

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
