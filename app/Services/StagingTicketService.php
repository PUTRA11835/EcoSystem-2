<?php

namespace App\Services;

use App\Models\StagingAttachment;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SlaService;
use App\Services\TicketNumberService;

/**
 * StagingTicketService
 *
 * Bertanggung jawab atas seluruh lifecycle staging ticket:
 *   1. createFromWeb()   → simpan submission customer dari form web Jarvies
 *   2. createFromEmail() → simpan email masuk (dari MS Graph) ke staging
 *   3. approve()         → validasi admin, promote staging → ticket (DB transaction)
 *   4. reject()          → tolak staging, catat alasan
 *
 * TIDAK ada logika ini yang boleh langsung berada di Controller.
 */
class StagingTicketService
{
    public function __construct(
        private readonly TicketNumberService $ticketNumbers,
        private readonly SlaService $sla,
    ) {}

    // ─── 1. Create from web form (Customer Project / Jarvies) ────────────────

    /**
     * Simpan submission tiket baru dari customer ke staging.
     * Dipanggil saat customer submit form di Jarvies.
     *
     * @param  array  $data         ['description', 'body', 'ticket_priority', 'submitted_by_email']
     * @param  int    $customerId   ID customer dari session
     * @return StagingTicket
     */
    public function createFromWeb(array $data, int $customerId): StagingTicket
    {
        // ── Dedup: cegah duplikat saat Jarvies Step 4a + Step 4b keduanya menulis ──
        //
        // Alur Jarvies:
        //   Step 4a — Jarvies langsung tulis ke DB shared (channel=web, tanpa linkStagingToEmail)
        //   Step 4b — Jarvies POST ke EcoSystem API → masuk sini lagi
        //
        // internet_message_id dari M365 Graph bersifat globally unique → pakai sebagai kunci dedup.
        // Jika sudah ada staging unvalidated dengan email_message_id yang sama, kembalikan yang
        // lama agar linkStagingToEmail() (dipanggil jarviesStore) memperkayanya, bukan bikin baru.
        if (!empty($data['internet_message_id'])) {
            $existing = StagingTicket::where('email_message_id', $data['internet_message_id'])
                ->where('status', 'unvalidated')
                ->first();

            if ($existing) {
                Log::info('StagingTicketService@createFromWeb: dedup — returning existing staging', [
                    'existing_id'      => $existing->id,
                    'email_message_id' => $data['internet_message_id'],
                    'customer_id'      => $customerId,
                ]);
                return $existing;
            }
        }

        $staging = StagingTicket::create([
            'customer_id'        => $customerId,
            'end_customer_id'    => isset($data['end_customer_id']) ? (int) $data['end_customer_id'] : null,
            'description'        => $data['description'],
            'body'               => $data['body'] ?? null,
            'ticket_priority'    => $data['ticket_priority'] ?? 'Medium',
            'ticket_type'        => $data['ticket_type'] ?? null,
            'scale'              => $data['scale'] ?? null,
            'status'             => 'unvalidated',
            'channel'            => 'web',
            'submitted_by_email' => $data['submitted_by_email'] ?? null,
            'sender_name'        => $data['sender_name'] ?? null,
            'cc_emails'          => $data['cc_emails'] ?? null,
            'email_message_id'   => $data['internet_message_id'] ?? null,
            'name'               => $data['name'] ?? null,
            'no_hp'              => $data['no_hp'] ?? null,
            'module'             => $data['module'] ?? null,
            'module_id'          => $data['module_id'] ?? null,
            'client'             => $data['client'] ?? null,
        ]);

        // SLA clock mulai sejak staging masuk
        try {
            $this->sla->attachToStaging($staging);
        } catch (\Throwable $e) {
            Log::warning('StagingTicketService@createFromWeb: SLA attachToStaging gagal (non-fatal)', [
                'staging_id' => $staging->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $staging;
    }

    // ─── 2. Create from email (MS Graph inbox processor) ─────────────────────

    /**
     * Simpan email masuk ke staging, bukan langsung ke ticket.
     * Dipanggil oleh EmailController@processInbox saat tidak menemukan tiket terkait.
     *
     * @param  array $emailData  Keys:
     *   customer_id, from_email, from_name, subject, body_html,
     *   conversation_id, internet_message_id, graph_message_id, has_attachments
     * @return StagingTicket
     */
    public function createFromEmail(array $emailData): StagingTicket
    {
        // Cegah duplikat: cek conversation_id dulu, fallback ke internet_message_id
        // CATATAN: hanya dedup untuk staging yang UNVALIDATED — jangan kembalikan staging
        // yang sudah approved/rejected karena emailnya harus diproses sebagai TicketMessage.
        if (!empty($emailData['conversation_id'])) {
            $existing = StagingTicket::where('email_thread_id', $emailData['conversation_id'])
                ->where('status', 'unvalidated')
                ->first();
            if ($existing) {
                return $existing;
            }
        } elseif (!empty($emailData['internet_message_id'])) {
            $existing = StagingTicket::where('email_message_id', $emailData['internet_message_id'])->first();
            if ($existing) {
                return $existing;
            }
        }

        // Cegah duplikat web+email: jika ada staging web dari customer yang sama
        // dengan description cocok (Jarvies kirim email notifikasi saat submit web form)
        $rawSubject   = trim($emailData['subject'] ?? '');
        $cleanSubject = preg_replace('/^\[PENDING\]\s*/i', '', $rawSubject);
        if (!empty($cleanSubject)) {
            // Cari via customer_id (lebih reliable, customer sudah resolve dari email di processInbox)
            $webQuery = StagingTicket::where('channel', 'web')
                ->where('status', 'unvalidated')
                ->whereRaw('LOWER(description) = LOWER(?)', [$cleanSubject])
                ->whereNull('email_thread_id');

            if (!empty($emailData['customer_id'])) {
                $existingWeb = (clone $webQuery)->where('customer_id', $emailData['customer_id'])->first();
            } else {
                // Fallback: cocokkan via submitted_by_email jika tidak ada customer_id
                $existingWeb = !empty($emailData['from_email'])
                    ? (clone $webQuery)->where('submitted_by_email', $emailData['from_email'])->first()
                    : null;
            }

            if ($existingWeb) {
                // Update staging web dengan email metadata agar reply bisa dikirim via email
                $existingWeb->update([
                    'email_thread_id'    => $emailData['conversation_id'] ?? null,
                    'email_message_id'   => $emailData['internet_message_id'] ?? null,
                    'graph_message_id'   => $emailData['graph_message_id'] ?? null,
                    'submitted_by_email' => $existingWeb->submitted_by_email ?? $emailData['from_email'] ?? null,
                    'has_attachments'    => $emailData['has_attachments'] ?? false,
                    'cc_emails'          => $emailData['cc_emails'] ?? null,
                ]);
                Log::info('StagingTicketService@createFromEmail: merged into existing web staging', [
                    'staging_id'  => $existingWeb->id,
                    'customer_id' => $emailData['customer_id'] ?? null,
                    'from_email'  => $emailData['from_email'],
                    'subject'     => $rawSubject,
                ]);
                return $existingWeb;
            }
        }

        // Gunakan sentDateTime email (header Date: dari pengirim) sebagai created_at agar
        // SLA clock dimulai dari waktu customer kirim, bukan waktu scheduler jalan.
        // $emailData['received_at'] adalah Carbon UTC (sentDateTime, fallback receivedDateTime)
        // dari Graph API → konversi ke WIB agar konsisten dengan timezone ecosystem user.
        $appTz      = config('app.timezone', 'Asia/Jakarta');
        $receivedAt = isset($emailData['received_at'])
            ? $emailData['received_at']->copy()->setTimezone($appTz)
            : now();

        $staging = StagingTicket::create([
            'customer_id'        => $emailData['customer_id'] ?? null,
            'description'        => $emailData['subject'] ?? substr(strip_tags($emailData['body_html'] ?? ''), 0, 255),
            'ticket_priority'    => null,
            'status'             => 'unvalidated',
            'channel'            => 'email',
            'email_thread_id'    => $emailData['conversation_id'] ?? null,
            'submitted_by_email' => $emailData['from_email'] ?? null,
            'sender_name'        => $emailData['from_name'] ?? null,
            'email_message_id'   => $emailData['internet_message_id'] ?? null,
            'graph_message_id'   => $emailData['graph_message_id'] ?? null,
            'email_body_html'    => $emailData['body_html'] ?? null,
            'has_attachments'    => $emailData['has_attachments'] ?? false,
            'cc_emails'          => $emailData['cc_emails'] ?? null,
            'created_at'         => $receivedAt,
            'updated_at'         => $receivedAt,
        ]);

        // SLA clock mulai sejak email masuk
        try {
            $this->sla->attachToStaging($staging);
        } catch (\Throwable $e) {
            Log::warning('StagingTicketService@createFromEmail: SLA attachToStaging gagal (non-fatal)', [
                'staging_id' => $staging->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return $staging;
    }

    // ─── 3. Approve → promote staging ke ticket ───────────────────────────────

    /**
     * Approve staging ticket: buat record di tabel `ticket`, update staging.
     * Jika staging dari email dan punya email_body_html → buat TicketMessage pertama.
     * Seluruh operasi dibungkus DB::transaction.
     *
     * @param  StagingTicket  $staging
     * @param  int            $validatedBy  employee_id yang approve
     * @return array{ticket: Ticket, first_message: ?TicketMessage}
     *
     * @throws \LogicException   jika staging sudah pernah diproses
     * @throws \RuntimeException jika DB transaction gagal
     */
    public function approve(StagingTicket $staging, int $validatedBy, ?string $ticketType = null, ?string $ticketPriority = null, ?string $scale = null): array
    {
        // Guard: cegah double validation
        if ($staging->isProcessed()) {
            throw new \LogicException(
                "Staging ticket #{$staging->id} already has status '{$staging->status}' and cannot be reprocessed."
            );
        }

        return DB::transaction(function () use ($staging, $validatedBy, $ticketType, $ticketPriority, $scale) {

            // Generate ticket number (format: YYMM####, locked against race condition)
            $ticketNumber = $this->ticketNumbers->generate();

            // Priority: use what validator set, fallback to staging's stored priority
            $finalPriority = $ticketPriority ?? $staging->ticket_priority ?? 'Medium';

            // Scale: ambil nilai validator → fallback staging (kalau pernah disimpan).
            // Kosong = null (kolom nullable, optional di UI).
            $finalScale = $scale ?? $staging->scale;

            // Normalize cc_emails dari staging (bisa array atau JSON string)
            $ccEmails = $staging->cc_emails;
            if (is_string($ccEmails)) {
                $ccEmails = json_decode($ccEmails, true) ?? null;
            }

            // Buat ticket resmi
            $ticket = Ticket::create([
                'ticket_number'   => $ticketNumber,
                'customer_id'     => $staging->customer_id,
                'end_customer_id' => $staging->end_customer_id,
                'description'     => $staging->description,
                'ticket_priority' => $finalPriority,
                'ticket_type'     => $ticketType,
                'scale'           => $finalScale,
                'status'          => 'open',
                'channel'         => $staging->channel,
                'email_thread_id' => $staging->email_thread_id,
                'cc_emails'       => $ccEmails,              // checklist G
                'start_date'      => now()->toDateString(),
                // Salin field tambahan dari staging
                'name'               => $staging->name,
                'no_hp'              => $staging->no_hp,
                'module'             => $staging->module,
                'module_id'          => $staging->module_id,
                'client'             => $staging->client,
                'submitted_by_email' => $staging->submitted_by_email,
                'submitted_by_name'  => $staging->sender_name,
            ]);

            // Update staging → approved, simpan FK ke ticket
            $staging->update([
                'status'       => 'approved',
                'validated_by' => $validatedBy,
                'validated_at' => now(),
                'ticket_id'    => $ticket->ticket_id,
            ]);

            // Buat TicketMessage pertama dari data yang tersimpan di staging
            $firstMessage = null;

            if ($staging->channel === 'email' && $staging->email_body_html) {
                // Staging dari email → gunakan email_body_html sebagai pesan pertama
                $bodyPlain = trim(strip_tags($staging->email_body_html));

                $firstMessage = TicketMessage::create([
                    'ticket_id'           => $ticket->ticket_id,
                    'sender_type'         => $staging->customer_id ? 'customer' : 'system',
                    'sender_id'           => $staging->customer_id,
                    'sender_email'        => $staging->submitted_by_email,
                    'sender_name'         => $staging->sender_name,
                    'message'             => $bodyPlain,
                    'message_html'        => $staging->email_body_html,
                    'is_internal_note'    => false,
                    'channel'             => 'email',
                    'email_message_id'    => $staging->email_message_id,
                    'email_in_reply_to'   => null,
                    'cc_emails'           => $staging->cc_emails,
                    'is_read_by_customer' => true,
                    'is_read_by_agent'    => false,
                ]);

            } elseif ($staging->channel === 'web' && !empty($staging->body)) {
                // Staging dari web form (Jarvies) → buat pesan pertama customer.
                // Cek anti-duplikat: jika sudah ada message dari email (misal OAuth), skip
                $alreadyFromEmail = TicketMessage::where('ticket_id', $ticket->ticket_id)
                    ->where('channel', 'email')
                    ->exists();

                if (!$alreadyFromEmail) {
                    // Prioritas: nama individual dari staging (dikirim Jarvies),
                    // fallback ke nama perusahaan hanya jika tidak ada
                    $senderName = $staging->sender_name
                        ?? $staging->customer?->basicData?->name_1
                        ?? 'Customer';

                    Log::info('StagingTicketService@approve: first message sender', [
                        'staging_id'          => $staging->id,
                        'staging_sender_name' => $staging->sender_name,
                        'company_name'        => $staging->customer?->basicData?->name_1,
                        'resolved_as'         => $senderName,
                    ]);

                    // Bangun HTML lengkap seperti email Jarvies:
                    // [metadata: phone/module/client] + [description body]
                    $messageHtml = $this->buildJarviesEmailBody($staging);

                    // Jika staging punya email_message_id (internet_message_id dari Jarvies),
                    // tandai channel='email' agar reply berikutnya bisa dilanjutkan via email.
                    $msgChannel = $staging->email_message_id ? 'email' : 'web';

                    $firstMessage = TicketMessage::create([
                        'ticket_id'           => $ticket->ticket_id,
                        'sender_type'         => 'customer',
                        'sender_id'           => $staging->customer_id,
                        'sender_email'        => $staging->submitted_by_email,
                        'sender_name'         => $senderName,
                        'message'             => strip_tags($messageHtml),
                        'message_html'        => $messageHtml,
                        'is_internal_note'    => false,
                        'channel'             => $msgChannel,
                        'email_message_id'    => $staging->email_message_id,
                        'cc_emails'           => $staging->cc_emails,
                        'is_read_by_customer' => true,
                        'is_read_by_agent'    => false,
                    ]);

                    // Gunakan waktu staging dibuat sebagai created_at pesan pertama
                    // agar timestamp di ticket chat konsisten dengan kapan customer submit
                    $firstMessage->timestamps = false;
                    $firstMessage->update(['created_at' => $staging->created_at]);
                }
            }

            // Update ticket timestamps jika ada first message dari customer
            if ($firstMessage) {
                $ticket->update([
                    'last_message_at'        => $firstMessage->created_at,
                    'last_customer_reply_at' => $firstMessage->created_at,
                ]);
            }

            // Checklist J: Pindahkan staging_attachments → ticket_attachment
            $stagingAttachments = StagingAttachment::where('staging_id', $staging->id)->get();
            foreach ($stagingAttachments as $sa) {
                TicketAttachment::create([
                    'ticket_id'        => $ticket->ticket_id,
                    'message_id'       => $firstMessage?->id,
                    'uploaded_by_type' => 'customer',
                    'uploaded_by_id'   => $staging->customer_id,
                    'attachment_type'  => 'file',
                    'link_url'         => '/storage/' . $sa->file_path,
                    'link_title'       => $sa->original_name ?? $sa->file_name,
                    'file_path'        => $sa->file_path,
                    'file_name'        => $sa->file_name,
                    'file_size'        => $sa->file_size,
                    'mime_type'        => $sa->mime_type,
                    'is_inline'        => false,
                ]);
            }

            if ($stagingAttachments->isNotEmpty()) {
                Log::info('StagingTicketService@approve: staging attachments moved to ticket_attachment', [
                    'staging_id' => $staging->id,
                    'ticket_id'  => $ticket->ticket_id,
                    'count'      => $stagingAttachments->count(),
                ]);
            }

            // Inisialisasi SLA record untuk tiket baru (non-fatal)
            try {
                $this->sla->attachToTicket($ticket, $staging);
            } catch (\Throwable $e) {
                Log::warning('StagingTicketService@approve: SLA attach gagal (non-fatal)', [
                    'ticket_id' => $ticket->ticket_id,
                    'error'     => $e->getMessage(),
                ]);
            }

            Log::info('StagingTicketService@approve: staging promoted to ticket', [
                'staging_id'       => $staging->id,
                'ticket_id'        => $ticket->ticket_id,
                'ticket_number'    => $ticketNumber,
                'first_message_id' => $firstMessage?->id,
            ]);

            return [
                'ticket'        => $ticket,
                'first_message' => $firstMessage,
            ];
        });
    }

    // ─── 4. Reject ────────────────────────────────────────────────────────────

    /**
     * Tolak staging ticket, catat alasan penolakan.
     *
     * @param  StagingTicket  $staging
     * @param  int            $validatedBy  employee_id yang reject
     * @param  string         $reason
     * @return StagingTicket
     *
     * @throws \LogicException jika staging sudah pernah diproses
     */
    public function reject(StagingTicket $staging, int $validatedBy, string $reason): StagingTicket
    {
        // Guard: cegah double validation
        if ($staging->isProcessed()) {
            throw new \LogicException(
                "Staging ticket #{$staging->id} already has status '{$staging->status}' and cannot be reprocessed."
            );
        }

        $staging->update([
            'status'            => 'rejected',
            'rejection_reason'  => $reason,
            'validated_by'      => $validatedBy,
            'validated_at'      => now(),
        ]);

        // Hapus SLA record staging — tiket yang ditolak tidak masuk SLA report
        try {
            $this->sla->detachFromStaging($staging);
        } catch (\Throwable $e) {
            Log::warning('StagingTicketService@reject: SLA detachFromStaging gagal (non-fatal)', [
                'staging_id' => $staging->id,
                'error'      => $e->getMessage(),
            ]);
        }

        Log::info('StagingTicketService@reject: staging rejected', [
            'staging_id' => $staging->id,
            'reason'     => $reason,
        ]);

        return $staging;
    }


    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Bangun HTML body pesan pertama customer dari staging web/Jarvies.
     * Format konsisten dengan email yang dikirim Jarvies ke customer:
     *   - Header [Tiket dari ... via Jarvies]
     *   - Tabel metadata (Phone, Module, Client) jika ada
     *   - Blok Description berisi body Quill
     */
    private function buildJarviesEmailBody(StagingTicket $staging): string
    {
        $rows = '';
        if (!empty($staging->no_hp)) {
            $rows .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555;white-space:nowrap">Phone</td>'
                   . '<td>: ' . e($staging->no_hp) . '</td></tr>';
        }
        if (!empty($staging->module_name)) {
            $rows .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555;white-space:nowrap">Module</td>'
                   . '<td>: ' . e($staging->module_name) . '</td></tr>';
        }
        if (!empty($staging->client)) {
            $rows .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555;white-space:nowrap">Client</td>'
                   . '<td>: ' . e($staging->client) . '</td></tr>';
        }

        $metaTable = $rows
            ? '<table style="border-collapse:collapse;margin-bottom:16px">' . $rows . '</table>'
            : '';

        $descSection = !empty($staging->body)
            ? '<div style="margin-bottom:16px"><strong>Description:</strong>'
              . '<div style="margin-top:8px;padding:12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px">'
              . $staging->body   // HTML dari Quill — sudah trusted input dari Jarvies
              . '</div></div>'
            : '';

        $headerNote = !empty($staging->sender_name)
            ? '[Tiket dari ' . e($staging->sender_name) . ' via Jarvies]'
            : '[Tiket baru via Jarvies]';

        return '<p>' . $headerNote . '</p>'
             . $metaTable
             . $descSection;
    }

    private function generateTicketNumber(?int $customerId): string
    {
        $year      = date('y');
        $yearMonth = date('ym');

        $lastNumber = DB::table('ticket')
            ->where('ticket_number', 'like', $year . '%')
            ->whereRaw("ticket_number NOT LIKE '%-%'")
            ->orderByRaw('CAST(SUBSTRING(ticket_number, 5, 4) AS UNSIGNED) DESC')
            ->value('ticket_number');

        $nextNumber = $lastNumber ? ((int) substr($lastNumber, -4)) + 1 : 1;

        return $yearMonth . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

