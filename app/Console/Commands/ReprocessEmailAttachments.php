<?php

namespace App\Console\Commands;

use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ReprocessEmailAttachments extends Command
{
    protected $signature   = 'email:reprocess-attachments
                                {--message-id= : ID spesifik di ticket_message}
                                {--ticket-id=  : Semua pesan email dalam ticket ini}';
    protected $description = 'Simpan ulang metadata attachment dari MS Graph (tanpa download file lokal)';

    public function handle(): int
    {
        $messageId = $this->option('message-id');
        $ticketId  = $this->option('ticket-id');

        if (!$messageId && !$ticketId) {
            $this->error('Gunakan --message-id=X atau --ticket-id=X');
            return 1;
        }

        $query = TicketMessage::where('channel', 'email')->whereNotNull('email_message_id');

        if ($messageId) {
            $query->where('id', $messageId);
        } else {
            $query->where('ticket_id', $ticketId);
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            $this->warn('No email messages found.');
            return 0;
        }

        $sender = env('MS_SENDER_EMAIL');
        $token  = $this->getAccessToken();
        $base   = rtrim(env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'), '/');

        foreach ($messages as $msg) {
            $this->info("Message #{$msg->id} | {$msg->email_message_id}");

            $search = Http::withToken($token)->get("{$base}/users/{$sender}/messages", [
                '$filter' => "internetMessageId eq '" . addslashes($msg->email_message_id) . "'",
                '$select' => 'id,hasAttachments',
                '$top'    => 1,
            ]);

            if (!$search->successful()) {
                $this->warn("  Graph query failed: " . $search->body());
                continue;
            }

            $graphMsg = $search->json('value.0');
            if (!$graphMsg) {
                $this->warn("  Email not found in Graph (may have been deleted from inbox).");
                continue;
            }

            if (empty($graphMsg['hasAttachments'])) {
                $this->line("  No attachments found.");
                continue;
            }

            $graphMsgId = $graphMsg['id'];

            // Tanpa $select — contentId dan contentBytes adalah properti derived type
            // yang tidak bisa dipakai dengan $select pada collection endpoint.
            // contentBytes ada di response tapi tidak disimpan ke disk.
            $attRes = Http::withToken($token)->get(
                "{$base}/users/{$sender}/messages/{$graphMsgId}/attachments"
            );

            if (!$attRes->successful()) {
                $this->warn("  Failed to fetch attachments: " . $attRes->body());
                continue;
            }

            $saved  = 0;
            $cidMap = [];

            foreach ($attRes->json('value') ?? [] as $att) {
                $odataType = $att['@odata.type'] ?? '';
                if ($odataType && !str_contains($odataType, 'fileAttachment')) {
                    continue;
                }
                if (empty($att['name'])) continue;

                $graphAttId   = $att['id'];
                $originalName = $att['name'];
                $mimeType     = $att['contentType'] ?? 'application/octet-stream';
                $isInline     = $att['isInline'] ?? false;
                $fileSize     = $att['size'] ?? 0;
                $contentId    = $att['contentId'] ?? null;

                $existing = TicketAttachment::where('graph_attachment_id', $graphAttId)->first();
                if ($existing) {
                    $this->line("  Skip (sudah ada): {$originalName}");
                    if ($contentId) $cidMap[$contentId] = $existing->id;
                    continue;
                }

                $record = TicketAttachment::create([
                    'ticket_id'           => $msg->ticket_id,
                    'message_id'          => $msg->id,
                    'uploaded_by_type'    => 'system',
                    'uploaded_by_id'      => null,
                    'attachment_type'     => $this->resolveType($mimeType),
                    'file_name'           => $originalName,
                    'file_size'           => $fileSize,
                    'mime_type'           => $mimeType,
                    'is_inline'           => $isInline,
                    'graph_attachment_id' => $graphAttId,
                    'graph_message_id'    => $graphMsgId,
                    'content_id'          => $contentId,
                ]);

                if ($contentId) $cidMap[$contentId] = $record->id;

                $this->info("  Saved metadata: {$originalName} ({$mimeType})");
                $saved++;
            }

            if (!empty($cidMap) && $msg->message_html) {
                $html = $msg->message_html;
                foreach ($cidMap as $cid => $attachmentId) {
                    $proxyUrl = '/attachments/' . $attachmentId;
                    $cleanCid = trim($cid, '<>');
                    $html = str_replace('cid:' . $cleanCid,        $proxyUrl, $html);
                    $html = str_replace('cid:<' . $cleanCid . '>', $proxyUrl, $html);
                    if ($cid !== $cleanCid) {
                        $html = str_replace('cid:' . $cid, $proxyUrl, $html);
                    }
                }
                $msg->update(['message_html' => $html]);
                $this->line("  CID references replaced in message_html.");
            }

            $this->info("  Total saved: {$saved}");
        }

        $this->info('Done.');
        return 0;
    }

    private function getAccessToken(): string
    {
        $res = Http::asForm()->post(
            "https://login.microsoftonline.com/" . env('MS_TENANT_ID') . "/oauth2/v2.0/token",
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => env('MS_CLIENT_ID'),
                'client_secret' => env('MS_CLIENT_SECRET'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );
        return $res->json('access_token');
    }

    private function resolveType(string $mime): string
    {
        if (str_starts_with($mime, 'image/'))                                    return 'image';
        if ($mime === 'application/pdf')                                          return 'pdf';
        if (str_contains($mime, 'word') || str_contains($mime, 'document'))      return 'document';
        if (str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet'))  return 'spreadsheet';
        if (str_contains($mime, 'zip'))                                           return 'archive';
        return 'file';
    }
}
