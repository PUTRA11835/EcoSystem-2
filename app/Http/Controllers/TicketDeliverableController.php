<?php

namespace App\Http\Controllers;

use App\Exceptions\EmailSendException;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketDeliverable;
use App\Models\TicketMessage;
use App\Services\OneDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketDeliverableController extends Controller
{
    private const DOC_TYPES = ['IR', 'RCA', 'CR Form', 'FSD', 'TD', 'UAT', 'MOM', 'BAST', 'EWA', 'Other'];

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

        // Folder ticket diturunkan langsung dari customer ticket (bukan delivery support).
        [$state, $message] = $this->resolveFolderState($ticket);

        return response()->json([
            'success'        => true,
            'data'           => $deliverables,
            'has_folder'     => $state === 'ready',
            'folder_state'   => $state,           // no_customer | ready
            'folder_message' => $message,
            'folder_url'     => $ticket->onedrive_folder_url,
        ]);
    }

    /**
     * Tentukan kesiapan folder deliverable untuk sebuah ticket.
     * Folder ticket berada di level customer:
     *   {root}/{customer}/TICKETING/{ticket_number}/Deliverable
     * Folder diturunkan langsung dari customer ticket — TIDAK lagi bergantung pada
     * delivery support (deliverable tidak masuk ke folder support). Folder dibuat
     * lazy saat upload pertama — tidak perlu "generate" lebih dulu.
     *
     * @return array{0:string,1:?string}
     *   [state, message]  state: no_customer | ready
     */
    private function resolveFolderState(Ticket $ticket): array
    {
        if ($ticket->customerDeliverableFolderName() === null) {
            return [
                'no_customer',
                'Data customer untuk ticket ini belum lengkap. Lengkapi data customer terlebih dahulu.',
            ];
        }

        return ['ready', null];
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
            // Folder ticket berada di level customer:
            //   {root}/{customer_id} {NAMA}/TICKETING/{ticket_number}/Deliverable
            [$state, $message] = $this->resolveFolderState($ticket);
            if ($state !== 'ready') {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            $uploadedFile = $request->file('file');
            $fileName     = $uploadedFile->getClientOriginalName();
            $mimeType     = $uploadedFile->getMimeType() ?? 'application/octet-stream';
            $fileContent  = file_get_contents($uploadedFile->getRealPath());

            try {
                $oneDrive = new OneDriveService();

                // Folder customer (di-find-or-create, diturunkan langsung dari customer ticket).
                $rootPath           = config('services.microsoft_graph.customer_deliverable_path', 'DELIVERY SUPPORT/CUSTOMER DELIVERABLE');
                $customerFolderId   = $oneDrive->findOrCreateFolderInPath($rootPath, $ticket->customerDeliverableFolderName());

                // Rantai folder (idempotent, case-insensitive): TICKETING -> {ticket_number} -> Deliverable
                $ticketingId    = $oneDrive->findOrCreateSubFolderById($customerFolderId, 'TICKETING');
                $ticketFolderId = $oneDrive->findOrCreateSubFolderById(
                    $ticketingId,
                    $ticket->ticket_number ?: ('Ticket-' . $ticket->ticket_id)
                );
                // File deliverable ditempatkan di subfolder "Deliverable" agar tidak tercampur
                // dengan file lain yang mungkin diupload manual ke folder ticket.
                $deliverableFolderId = $oneDrive->findOrCreateSubFolderById($ticketFolderId, 'Deliverable');

                // Cache folder id + share link (untuk tombol "Open folder").
                // PENTING: link "edit" anonymous dibuat pada folder TICKET (induk),
                // BUKAN subfolder Deliverable. Permission edit anonymous menurun ke
                // seluruh isi folder, sehingga pengguna yang mengakses link bisa
                // upload/create/download langsung di folder ticket MAUPUN di subfolder
                // Deliverable. Jika link dibuat di subfolder Deliverable saja, folder
                // ticket induk hanya view-only (editable hilang saat naik ke folder ticket).
                $update = [
                    'onedrive_folder_id'             => $ticketFolderId,
                    'onedrive_deliverable_folder_id' => $deliverableFolderId,
                ];
                if (empty($ticket->onedrive_folder_url) || $ticket->onedrive_folder_id !== $ticketFolderId) {
                    try {
                        $update['onedrive_folder_url'] = $oneDrive->createAnonymousLink($ticketFolderId, 'edit');
                    } catch (\Throwable $e) {
                        Log::warning('Deliverable folder share link failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
                    }
                }
                $ticket->update($update);

                $result  = $oneDrive->uploadFile($deliverableFolderId, $fileName, $fileContent, $mimeType);
                $fileId  = $result['id'];

                // webUrl dari upload adalah path SharePoint langsung — butuh izin akun (Request access).
                // Buat anonymous share link agar file bisa dibuka customer tanpa login.
                try {
                    $fileUrl = $oneDrive->createAnonymousLink($fileId, 'view');
                } catch (\Throwable $e) {
                    Log::warning('Deliverable file share link failed', ['ticket_id' => $ticketId, 'error' => $e->getMessage()]);
                    $fileUrl = $result['webUrl'] ?? $result['downloadUrl'] ?? null;
                }
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
     * PATCH /api/tickets/{ticketId}/deliverables/{delivId}
     * Update body_text (only allowed while status is not "Sent").
     */
    public function update(Request $request, $ticketId, $delivId)
    {
        $user = session('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'body_text' => ['nullable', 'string', 'max:1000'],
        ]);

        $deliverable = TicketDeliverable::where('id', $delivId)
            ->where('ticket_id', $ticketId)
            ->firstOrFail();

        if ($deliverable->status === 'Sent') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot edit a document that has already been sent to customer.',
            ], 422);
        }

        $deliverable->update(['body_text' => $request->body_text]);

        return response()->json(['success' => true, 'data' => $this->format($deliverable->fresh())]);
    }

    /**
     * PATCH /api/tickets/{ticketId}/deliverables/{delivId}/send
     * Mark a deliverable as "Sent", add to chat, and email the customer.
     *
     * Mengikuti alur reply biasa: helpdesk wajib memilih status tiket lebih dulu
     * (dikirim via `ticket_status`) sebelum dokumen benar-benar dikirim ke customer.
     */
    public function send(Request $request, $ticketId, $delivId)
    {
        $user = session('user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'ticket_status' => 'nullable|in:inprocess,waiting_on_customer,waiting_to_confirmation,waiting_on_3rd_party,hold',
            'to_emails'     => 'nullable',
            'cc_emails'     => 'nullable',
        ]);

        $deliverable = TicketDeliverable::where('id', $delivId)
            ->where('ticket_id', $ticketId)
            ->firstOrFail();

        $ticket = Ticket::where('ticket_id', $ticketId)->firstOrFail();

        $deliverable->update(['status' => 'Sent']);

        // To/Cc diambil dari kolom composer reply (mirror reply biasa). Jika frontend
        // mengirim daftar (walau kosong) → HORMATI apa adanya & persist ke ticket; jika
        // field tidak dikirim (null) → fallback ke resolveCustomerEmail + ticket.cc_emails.
        $requestTo = $this->parseEmailList($request->input('to_emails'));
        $requestCc = $this->parseEmailList($request->input('cc_emails'));

        // CC final: composer jika dikirim, else ticket.cc_emails.
        $ccList = $requestCc !== null ? $requestCc : (
            !empty($ticket->cc_emails)
                ? (is_array($ticket->cc_emails) ? $ticket->cc_emails : (json_decode($ticket->cc_emails, true) ?? []))
                : []
        );

        // TO final: primary + tambahan dari composer jika dikirim, else customer email default.
        if ($requestTo !== null) {
            $primaryTo        = $requestTo[0] ?? null;
            $additionalToList = count($requestTo) > 1 ? array_slice($requestTo, 1) : [];
        } else {
            $primaryTo        = $this->resolveCustomerEmail($ticket);
            $additionalToList = [];
        }

        // Build message body
        // Chat bubble (EcoSystem/Jarvies) tetap tampil "Helpdesk Support" — konsisten
        // dengan reply biasa, karena email dikirim dari shared inbox M365.
        $senderName  = 'Helpdesk Support';
        $senderEmail = $user['email'] ?? null;

        // Footer email "Sent by ..." mengikuti user yang login & menekan Send
        // (mirror alur validate/approval di StagingTicketController): pakai nick_name,
        // fallback ke nama depan, fallback terakhir "Helpdesk".
        $nickName = $user['nick_name'] ?? null;
        if (!$nickName && !empty($user['id'])) {
            $nickName = DB::table('employee_basic_data')
                ->where('employee_id', $user['id'])
                ->value('nick_name');
        }
        $signatureName = $nickName ?? explode(' ', $user['name'] ?? 'Helpdesk')[0];

        $plainMsg = 'Deliverable document sent: ' . $deliverable->doc_type;
        if ($deliverable->body_text) $plainMsg .= ' — ' . $deliverable->body_text;
        if ($deliverable->file_name) $plainMsg .= ' (' . $deliverable->file_name . ')';

        // Layout key-value 3 kolom: label | ":" | value. Kolom ":" dipisah agar titik dua
        // SEJAJAR vertikal antar-baris (tabel otomatis melebarkan kolom label ke label
        // terlebar, sehingga semua ":" mulai di posisi X yang sama). Kolom label
        // `white-space:nowrap` biar "Description" tak terpotong; kolom nilai
        // `overflow-wrap:anywhere` supaya nama file panjang membungkus rapi (bukan meluber).
        // Class `deliv-card` dipakai untuk override border tabel paksaan `.email-html-body td`
        // di chat bubble (lihat CSS di ticket/show.blade.php).
        $labelTd = 'padding:4px 0;color:#6b7280;white-space:nowrap;vertical-align:top;';
        $colonTd = 'padding:4px 10px;color:#6b7280;vertical-align:top;';
        $valueTd = 'padding:4px 0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;';

        $row = fn(string $label, string $value, string $valueExtra = '') =>
            '<tr><td style="' . $labelTd . '">' . $label . '</td>'
            . '<td style="' . $colonTd . '">:</td>'
            . '<td style="' . $valueTd . $valueExtra . '">' . $value . '</td></tr>';

        $htmlMsg = '<p style="margin:0 0 8px"><strong>Deliverable Document</strong></p>'
            . '<table class="deliv-card" style="border-collapse:collapse;font-size:13px;width:100%;max-width:460px;">'
            . $row('Doc Type', htmlspecialchars($deliverable->doc_type), 'font-weight:600;');

        if ($deliverable->body_text) {
            $htmlMsg .= $row('Description', nl2br(htmlspecialchars($deliverable->body_text)));
        }

        if ($deliverable->file_name) {
            $fileCell = $deliverable->onedrive_file_url
                ? '<a href="' . htmlspecialchars($deliverable->onedrive_file_url) . '" target="_blank" style="color:#2563eb;word-break:break-word;overflow-wrap:anywhere;">' . htmlspecialchars($deliverable->file_name) . '</a>'
                : htmlspecialchars($deliverable->file_name);
            $htmlMsg .= $row('File', $fileCell);
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
            'cc_emails'           => !empty($ccList) ? $ccList : null,
        ]);

        // Status tiket yang dipilih helpdesk di modal (mirror alur reply biasa).
        // Sekaligus persist To/Cc composer ke ticket agar reply/pengiriman berikutnya
        // memakai recipient yang sama (mirror TicketMessageController).
        $chosenStatus = $request->input('ticket_status');
        $ticketUpdate = [
            'last_message_at'     => now(),
            'last_agent_reply_at' => now(),
        ];
        if ($chosenStatus)       $ticketUpdate['status']    = $chosenStatus;
        if ($requestTo !== null) $ticketUpdate['to_emails'] = $requestTo;
        if ($requestCc !== null) $ticketUpdate['cc_emails'] = $requestCc;
        $ticket->update($ticketUpdate);

        // Kirim email memakai To/Cc dari composer (mirror reply biasa). Kirim selama ada
        // penerima: To utama TERISI atau minimal ada CC (kasus CC-only). $primaryTo/$ccList/
        // $additionalToList sudah dihitung di atas dari request (fallback customer email).
        $emailError = null; // alasan gagal (untuk response → notifikasi frontend)
        try {
            if ($primaryTo || !empty($ccList)) {
                // Subject HARUS identik dengan thread email ticket ("[JARVIES] #XXXX : desc")
                // agar dokumen deliverable masuk ke thread yang sama, BUKAN membuat email baru.
                // Format ini sama persis dengan email approval (StagingTicketController) dan
                // reply biasa, sehingga subjectTopicMatches() di sendTicketReply mengenali
                // topik yang sama → draft createReply TIDAK di-PATCH subject → Thread-Index
                // Exchange terjaga → Outlook & Gmail tetap menyatukan satu thread.
                $subject = '[JARVIES] #' . ($ticket->ticket_number ?? $ticket->ticket_id)
                    . ' : ' . mb_substr($ticket->description ?? '', 0, 80);

                $inReplyTo = TicketMessage::where('ticket_id', $ticket->ticket_id)
                    ->where('channel', 'email')
                    ->whereNotNull('email_message_id')
                    ->where('id', '!=', $ticketMsg->id)
                    ->orderByDesc('created_at')
                    ->value('email_message_id');

                // Bungkus tabel deliverable dengan footer. "Sent by" mengikuti user yang
                // login & menekan Send (sama seperti email validate), bukan hardcode.
                // Chat bubble tetap memakai $htmlMsg tanpa footer.
                $emailHtml = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#374151;line-height:1.7;max-width:600px;">'
                    . $htmlMsg
                    . '<p style="margin-top:24px;padding-top:12px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;">'
                    . 'Sent by <strong style="color:#6b7280;">' . htmlspecialchars($signatureName) . '</strong> &mdash; PT Eclectic Consulting<br>'
                    . 'Ticket: <strong style="color:#6b7280;">#' . htmlspecialchars($ticket->ticket_number ?? $ticket->ticket_id) . '</strong>'
                    . '</p></div>';

                $emailController = new EmailController();
                $result = $emailController->sendTicketReply(
                    $primaryTo ?? '',            // primary To (boleh kosong bila CC-only)
                    $subject,
                    $emailHtml,
                    $inReplyTo,
                    [],                          // attachments
                    $ccList,
                    true,                        // noRePrefix
                    $ticket->email_thread_id,    // conversationId fallback
                    false,                       // forceNewDraft
                    [],                          // rawAttachments
                    $additionalToList            // additional To recipients (selain primary)
                );

                if (!empty($result['internet_message_id'])) {
                    // Sebagian alamat di-drop (salah tulis) → email tetap terkirim ke
                    // penerima valid, tapi tandai 'partial' + sebut alamat yang gagal.
                    $invalidRecipients = $result['invalid_recipients'] ?? [];
                    $isPartial         = !empty($invalidRecipients);
                    if ($isPartial) {
                        $emailError = EmailController::deliveryFailureReason($invalidRecipients, false);
                    }
                    $ticketMsg->update([
                        'email_message_id'        => $result['internet_message_id'],
                        'email_status'            => $isPartial ? 'partial' : 'sent',
                        'email_error'             => $isPartial ? $emailError : null,
                        'email_recipients'        => $result['recipients'] ?? null,
                        'email_failed_recipients' => $isPartial ? array_values($invalidRecipients) : null,
                    ]);
                }
                if (!empty($result['conversation_id']) && $result['conversation_id'] !== $ticket->email_thread_id) {
                    $ticket->update(['email_thread_id' => $result['conversation_id']]);
                }
            }
        } catch (\Throwable $e) {
            // Email gagal → tandai bubble pesan "Tidak terkirim" + alasan.
            // Pesan sudah tersimpan di atas, jadi cukup di-update statusnya.
            $emailError    = EmailSendException::reasonFrom($e);
            $failedInvalid = EmailSendException::failedRecipientsFrom($e);
            $ticketMsg->update([
                'email_status'            => 'failed',
                'email_error'             => $emailError,
                'email_failed_recipients' => !empty($failedInvalid) ? array_values($failedInvalid) : null,
            ]);
            Log::warning('TicketDeliverableController@send: email failed', [
                'reason'     => $emailError,
                'error'      => $e->getMessage(),
                'ticket_id'  => $ticketId,
                'deliv_id'   => $delivId,
                'raw_detail' => $e instanceof EmailSendException ? $e->rawDetail : null,
            ]);
        }

        return response()->json([
            'success'      => true,
            'data'         => $this->format($deliverable->fresh()),
            // Dokumen tetap tersimpan/terkirim ke chat, tapi email ke customer GAGAL →
            // frontend tampilkan peringatan (bukan sukses) agar tidak membingungkan.
            'email_failed' => $emailError !== null,
            'email_error'  => $emailError,
        ]);
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

        // Dokumen yang sudah dikirim ke customer tidak boleh dihapus.
        if ($deliverable->status === 'Sent') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a document that has already been sent to customer.',
            ], 422);
        }

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

    /**
     * Resolve email customer dari berbagai sumber (urutan prioritas, mirror
     * TicketMessageController::resolveCustomerEmail):
     * 1. ticket.submitted_by_email          — email login customer (initiateEmail / import CSV)
     * 2. staging_tickets.submitted_by_email  — dari staging yang sudah diapprove
     * 3. ticket_message.sender_email pertama — email pesan pertama dari customer
     * 4. customer.email                       — email perusahaan (fallback terakhir)
     */
    private function resolveCustomerEmail(Ticket $ticket): ?string
    {
        if (!empty($ticket->submitted_by_email)) {
            return $ticket->submitted_by_email;
        }

        $submittedEmail = DB::table('staging_tickets')
            ->where('ticket_id', $ticket->ticket_id)
            ->whereNotNull('submitted_by_email')
            ->value('submitted_by_email');
        if ($submittedEmail) {
            return $submittedEmail;
        }

        $firstMsg = TicketMessage::where('ticket_id', $ticket->ticket_id)
            ->where('sender_type', 'customer')
            ->whereNotNull('sender_email')
            ->orderBy('created_at', 'asc')
            ->first();
        if ($firstMsg?->sender_email) {
            return $firstMsg->sender_email;
        }

        if ($ticket->customer_id) {
            return Customer::find($ticket->customer_id)?->email;
        }

        return null;
    }

    /**
     * Normalisasi input daftar email dari request (composer To/Cc).
     * - null (field tak dikirim)   → null  (caller fallback ke resolveCustomerEmail / ticket.cc_emails)
     * - array / JSON string        → array alamat string ter-trim, entri kosong dibuang
     * Menerima item string ("a@b.com") maupun objek ({address, name}).
     */
    private function parseEmailList($raw): ?array
    {
        if ($raw === null) {
            return null;
        }
        $arr = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        if (!is_array($arr)) {
            $arr = [];
        }
        return array_values(array_filter(array_map(
            fn ($e) => is_string($e) ? trim($e) : (is_array($e) ? trim((string) ($e['address'] ?? '')) : ''),
            $arr
        )));
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
