<?php

namespace App\Services;

use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        return StagingTicket::create([
            'customer_id'        => $customerId,
            'description'        => $data['description'],
            'body'               => $data['body'] ?? null,
            'ticket_priority'    => $data['ticket_priority'] ?? 'Medium',
            'status'             => 'unvalidated',
            'channel'            => 'web',
            'submitted_by_email' => $data['submitted_by_email'] ?? null,
            'sender_name'        => $data['sender_name'] ?? null,
            'cc_emails'          => $data['cc_emails'] ?? null,
            // Field tambahan (opsional dari Jarvies)
            'name'               => $data['name'] ?? null,
            'no_hp'              => $data['no_hp'] ?? null,
            'module'             => $data['module'] ?? null,
            'client'             => $data['client'] ?? null,
        ]);
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
        if (!empty($emailData['conversation_id'])) {
            $existing = StagingTicket::where('email_thread_id', $emailData['conversation_id'])->first();
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

        return StagingTicket::create([
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
        ]);
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
    public function approve(StagingTicket $staging, int $validatedBy, ?string $ticketType = null, ?string $ticketPriority = null): array
    {
        // Guard: cegah double validation
        if ($staging->isProcessed()) {
            throw new \LogicException(
                "Staging ticket #{$staging->id} sudah berstatus '{$staging->status}'. Tidak bisa diproses ulang."
            );
        }

        return DB::transaction(function () use ($staging, $validatedBy, $ticketType, $ticketPriority) {

            // Generate ticket number (format: YYMM-XXXX-0000)
            $ticketNumber = $this->generateTicketNumber($staging->customer_id);

            // Priority: use what validator set, fallback to staging's stored priority
            $finalPriority = $ticketPriority ?? $staging->ticket_priority ?? 'Medium';

            // Buat ticket resmi
            $ticket = Ticket::create([
                'ticket_number'   => $ticketNumber,
                'customer_id'     => $staging->customer_id,
                'description'     => $staging->description,
                'ticket_priority' => $finalPriority,
                'ticket_type'     => $ticketType,
                'status'          => 'open',
                'jarvies_status'  => 'in process',
                'channel'         => $staging->channel,
                'email_thread_id' => $staging->email_thread_id,
                'start_date'      => now()->toDateString(),
                // Salin field tambahan dari staging
                'name'            => $staging->name,
                'no_hp'           => $staging->no_hp,
                'module'          => $staging->module,
                'client'          => $staging->client,
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
                // Staging dari web form → gunakan body sebagai pesan pertama customer
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

                    $firstMessage = TicketMessage::create([
                        'ticket_id'           => $ticket->ticket_id,
                        'sender_type'         => 'customer',
                        'sender_id'           => $staging->customer_id,
                        'sender_email'        => $staging->submitted_by_email,
                        'sender_name'         => $senderName,
                        'message'             => $staging->body,
                        'is_internal_note'    => false,
                        'channel'             => 'web',
                        'cc_emails'           => $staging->cc_emails,
                        'is_read_by_customer' => true,
                        'is_read_by_agent'    => false,
                    ]);
                }
            }

            // Update ticket timestamps jika ada first message dari customer
            if ($firstMessage) {
                $ticket->update([
                    'last_message_at'        => $firstMessage->created_at,
                    'last_customer_reply_at' => $firstMessage->created_at,
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
                "Staging ticket #{$staging->id} sudah berstatus '{$staging->status}'. Tidak bisa diproses ulang."
            );
        }

        $staging->update([
            'status'            => 'rejected',
            'rejection_reason'  => $reason,
            'validated_by'      => $validatedBy,
            'validated_at'      => now(),
        ]);

        Log::info('StagingTicketService@reject: staging rejected', [
            'staging_id' => $staging->id,
            'reason'     => $reason,
        ]);

        return $staging;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Generate ticket number dengan format YYMM-XXXX-0000.
     * Identik dengan TicketController@generateTicketNumber, dipindah ke service
     * agar bisa dipakai saat promote tanpa ketergantungan pada controller.
     */
    private function generateTicketNumber(?int $customerId): string
    {
        $customer     = DB::table('customer')->where('customer_id', $customerId)->first();
        $customerCode = strtoupper(substr(str_pad($customer->customer_code ?? 'UNKN', 4, 'X'), 0, 4));
        $yearMonth    = date('ym');
        $prefix       = $yearMonth . '-' . $customerCode . '-';

        $lastTicket = DB::table('ticket')
            ->where('ticket_number', 'like', $prefix . '%')
            ->orderBy('ticket_number', 'desc')
            ->first();

        $nextNumber = $lastTicket
            ? ((int) substr($lastTicket->ticket_number, -4)) + 1
            : 1;

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
