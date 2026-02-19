<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\IMAP\Facades\Client;

class EmailController extends Controller
{
    /**
     * Buat IMAP client dengan konfigurasi dari env
     */
    private function makeImapClient()
    {
        return Client::make([
            'host'          => env('IMAP_HOST'),
            'port'          => env('IMAP_PORT'),
            'protocol'      => env('IMAP_PROTOCOL'),
            'encryption'    => env('IMAP_ENCRYPTION'),
            'validate_cert' => env('IMAP_VALIDATE_CERT'),
            'username'      => env('IMAP_USERNAME'),
            'password'      => env('IMAP_PASSWORD'),
        ]);
    }

    public function inbox()
    {
        $client = $this->makeImapClient();

        $client->connect();
        $messages = [];

        // $inbox = $client->getFolders();
        // return $inbox;
        $inbox = $client->getFolder("INBOX");

        $messages = $inbox->messages()
            ->all()
            ->setFetchOrderDesc()
            ->limit(1)
            ->get();

        $emails = [];

        foreach ($messages as $message) {
            $toAttribute = $message->getTo();
            if ($toAttribute instanceof \Webklex\PHPIMAP\Attribute) {
                $to = $toAttribute->toArray();
            } elseif (is_array($toAttribute)) {
                $to = $toAttribute;
            } elseif ($toAttribute) {
                $to = [$toAttribute];
            } else {
                $to = [];
            }

            $toAddresses = array_values(array_filter(array_map(function ($recipient) {
                return $recipient->mail ?? null;
            }, $to)));

            $references = $message->getReferences();
            if (!empty($references) && !is_array($references)) {
                $references = [$references];
            }
            if (is_array($references)) {
                $references = array_values(array_filter(array_map(function ($reference) {
                    return (string) $reference;
                }, $references)));
            } else {
                $references = [];
            }

            $messageId = (string) $message->getMessageId();
            $inReplyTo = (string) $message->getInReplyTo();

            $date = $message->getDate();
            if (is_object($date) && method_exists($date, 'toDateTimeString')) {
                $dateString = $date->toDateTimeString();
            } elseif (is_object($date) && method_exists($date, 'format')) {
                $dateString = $date->format('c');
            } else {
                $dateString = (string) $date;
            }

            $emails[] = [
                'subject' => (string) $message->getSubject(),
                'from' => $message->getFrom()[0]->mail ?? null,
                'date' => $dateString,
                'body' => $message->getTextBody(),
                'to' => $toAddresses,
                'message_id' => $messageId,
                'in_reply_to' => $inReplyTo,
                'references' => $references,
            ];
        }

        return response()->json($emails);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        Mail::raw($data['body'], function ($message) use ($data) {
            $message->to($data['to'])->subject($data['subject']);
        });

        return response()->json(['status' => 'sent']);
    }

    public function reply(Request $request)
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'in_reply_to' => ['nullable', 'string'],
            'references' => ['nullable'],
        ]);

        $subject = $data['subject'];
        if (stripos($subject, 're:') !== 0) {
            $subject = 'Re: ' . $subject;
        }

        Mail::raw($data['body'], function ($message) use ($data, $subject) {
            $message->to($data['to'])->subject($subject);

            $headers = $message->getHeaders();

            if (!empty($data['in_reply_to'])) {
                $headers->addTextHeader('In-Reply-To', $data['in_reply_to']);
            }

            if (!empty($data['references'])) {
                $references = $data['references'];
                if (is_array($references)) {
                    $references = implode(' ', $references);
                }
                $headers->addTextHeader('References', $references);
            }
        });

        return response()->json(['status' => 'replied']);
    }

    /**
     * Proses inbox: buat tiket baru dari email baru, atau tambah pesan ke tiket yang ada (jika reply).
     * Endpoint ini bisa dipanggil secara periodik (misal via cron/scheduler).
     */
    public function processInbox(Request $request)
    {
        try {
            $client = $this->makeImapClient();
            $client->connect();

            $inbox = $client->getFolder('INBOX');

            // Ambil email yang belum dibaca (UNSEEN)
            $messages = $inbox->messages()
                ->unseen()
                ->setFetchOrderAsc()
                ->get();

            $processed = 0;
            $skipped   = 0;
            $errors    = [];

            foreach ($messages as $message) {
                try {
                    $subject    = (string) $message->getSubject();
                    $fromEmail  = $message->getFrom()[0]->mail ?? null;
                    $fromName   = $message->getFrom()[0]->personal ?? $fromEmail;
                    $body       = $message->getTextBody() ?? $message->getHtmlBody() ?? '';
                    $messageId  = (string) $message->getMessageId();
                    $inReplyTo  = (string) $message->getInReplyTo();

                    $referencesRaw = $message->getReferences();
                    if (!empty($referencesRaw) && !is_array($referencesRaw)) {
                        $referencesRaw = [$referencesRaw];
                    }
                    $references = is_array($referencesRaw)
                        ? implode(' ', array_map(fn($r) => (string) $r, $referencesRaw))
                        : '';

                    // Jika ini adalah reply (ada in_reply_to), cari tiket yang sudah ada
                    if (!empty($inReplyTo)) {
                        $ticket = Ticket::where('email_thread_id', $inReplyTo)
                            ->orWhereHas('messages', function ($q) use ($inReplyTo, $references) {
                                $q->where('email_message_id', $inReplyTo);
                                if (!empty($references)) {
                                    $refIds = explode(' ', trim($references));
                                    $q->orWhereIn('email_message_id', $refIds);
                                }
                            })
                            ->first();

                        if ($ticket) {
                            // Tentukan customer dari email pengirim
                            $customer = Customer::where('email', $fromEmail)->first();
                            $senderId = $customer?->customer_id;

                            TicketMessage::create([
                                'ticket_id'           => $ticket->ticket_id,
                                'sender_type'         => $customer ? 'customer' : 'system',
                                'sender_id'           => $senderId,
                                'sender_email'        => $fromEmail,
                                'sender_name'         => $fromName,
                                'message'             => $body,
                                'is_internal_note'    => false,
                                'channel'             => 'email',
                                'email_message_id'    => $messageId,
                                'email_in_reply_to'   => $inReplyTo,
                                'is_read_by_customer' => true,
                                'is_read_by_agent'    => false,
                            ]);

                            // Update status tiket jika customer membalas
                            if ($customer && $ticket->status === 'reply') {
                                $ticket->update(['status' => 'in_progress']);
                            }

                            $ticket->update(['last_customer_reply_at' => now(), 'last_message_at' => now()]);

                            // Tandai email sebagai sudah dibaca di IMAP
                            $message->setFlag('Seen');
                            $processed++;
                            continue;
                        }
                    }

                    // Email baru → buat tiket baru
                    $customer = Customer::where('email', $fromEmail)->first();

                    // Generate ticket number unik
                    $ticketNumber = 'TKT-' . strtoupper(uniqid());

                    $ticket = Ticket::create([
                        'ticket_number'    => $ticketNumber,
                        'customer_id'      => $customer?->customer_id,
                        'description'      => $subject . ($body ? "\n\n" . $body : ''),
                        'status'           => 'open',
                        'channel'          => 'email',
                        'email_thread_id'  => $messageId,
                        'start_date'       => now()->toDateString(),
                    ]);

                    // Simpan pesan awal
                    TicketMessage::create([
                        'ticket_id'           => $ticket->ticket_id,
                        'sender_type'         => $customer ? 'customer' : 'system',
                        'sender_id'           => $customer?->customer_id,
                        'sender_email'        => $fromEmail,
                        'sender_name'         => $fromName,
                        'message'             => $body,
                        'is_internal_note'    => false,
                        'channel'             => 'email',
                        'email_message_id'    => $messageId,
                        'email_in_reply_to'   => null,
                        'is_read_by_customer' => true,
                        'is_read_by_agent'    => false,
                    ]);

                    // Tandai email sebagai sudah dibaca di IMAP
                    $message->setFlag('Seen');
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
            Log::error('EmailController@processInbox: IMAP connection failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal terhubung ke inbox: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Kirim email balasan untuk sebuah tiket (digunakan oleh TicketMessageController)
     */
    public function sendTicketReply(string $toEmail, string $subject, string $body, ?string $inReplyTo = null, ?string $references = null): void
    {
        $replySubject = stripos($subject, 're:') !== 0 ? 'Re: ' . $subject : $subject;

        Mail::raw($body, function ($message) use ($toEmail, $replySubject, $inReplyTo, $references) {
            $message->to($toEmail)->subject($replySubject);

            $headers = $message->getHeaders();

            if (!empty($inReplyTo)) {
                $headers->addTextHeader('In-Reply-To', $inReplyTo);
            }

            if (!empty($references)) {
                $headers->addTextHeader('References', $references);
            }
        });
    }
}
