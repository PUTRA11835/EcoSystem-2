<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Proxy: ambil file attachment dari Microsoft Graph dan stream ke browser.
     *
     * Route: GET /attachments/{id}  (CheckAuthToken middleware)
     *       atau GET /api/lite/attachments/{id}  (lite.auth middleware — Bearer token)
     *
     * File TIDAK disimpan di server — setiap request diambil langsung dari Graph.
     * Ini menghemat storage server sambil tetap menjaga keamanan akses (user harus login).
     */
    public function show(int $id)
    {
        // Wajib login — via session web biasa ATAU Bearer token Lite API
        $sessionUser = session('user') ?? request()->attributes->get('lite_user');
        if (!$sessionUser) {
            abort(401, 'Authentication required. Please log in to access this resource.');
        }

        $attachment = TicketAttachment::findOrFail($id);

        // Record lama: file disimpan lokal → stream dengan Content-Disposition agar nama file benar
        if (!$attachment->graph_message_id && $attachment->file_path) {
            $filePath = $attachment->file_path;
            abort_if(
                str_contains($filePath, '..') || str_starts_with($filePath, '/') || str_starts_with($filePath, '\\'),
                404,
                'File tidak ditemukan.'
            );
            abort_if(!Storage::disk('public')->exists($filePath), 404, 'File tidak ditemukan.');

            $filename  = $attachment->file_name ?? basename($filePath);
            $mime      = $attachment->mime_type ?? Storage::disk('public')->mimeType($filePath) ?? 'application/octet-stream';
            $asciiName = str_replace(['"', '\\', "\r", "\n"], '', preg_replace('/[^\x20-\x7E]/', '_', $filename));

            Log::info('AttachmentController: legacy file accessed', [
                'attachment_id' => $id,
                'file_name'     => $filename,
                'ticket_id'     => $attachment->ticket_id ?? null,
                'accessed_by'   => $sessionUser['eci'] ?? $sessionUser['name'] ?? $sessionUser['id'] ?? 'unknown',
            ]);

            return response()->stream(function () use ($filePath) {
                $stream = Storage::disk('public')->readStream($filePath);
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
                'Content-Length'      => Storage::disk('public')->size($filePath),
                'Cache-Control'       => 'private, max-age=3600',
            ]);
        }

        // Validasi: harus punya graph_message_id + graph_attachment_id
        if (!$attachment->graph_message_id || !$attachment->graph_attachment_id) {
            abort(404, 'File ini tidak dapat diakses via proxy.');
        }

        try {
            $sender  = config('services.microsoft_graph.sender_email');
            $token   = $this->getGraphToken();
            $baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

            // Fetch attachment beserta contentBytes dari Graph
            $response = Http::withToken($token)->get(
                "{$baseUrl}/users/{$sender}/messages/{$attachment->graph_message_id}/attachments/{$attachment->graph_attachment_id}"
            );

            if (!$response->successful()) {
                // Jika 404: kemungkinan graph_message_id adalah draft ID lama yang sudah
                // tidak valid setelah email dikirim (email pindah ke Sent Items dengan ID baru).
                // Coba cari message baru di Sent Items via internetMessageId dari ticket_message.
                if ($response->status() === 404 && $attachment->message_id) {
                    $ticketMsg = DB::table('ticket_message')
                        ->where('id', $attachment->message_id)
                        ->whereNotNull('email_message_id')
                        ->first();

                    if ($ticketMsg?->email_message_id) {
                        try {
                            // OData $filter pada internetMessageId tidak reliable di SentItems.
                            // Ambil pesan terbaru dan cocokkan internetMessageId di PHP.
                            $sentMsgId = null;
                            $baseUrl   = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');
                            for ($retryAttempt = 1; $retryAttempt <= 3; $retryAttempt++) {
                                if ($retryAttempt > 1) sleep(1);
                                $searchResult = Http::withToken($token)->get(
                                    "{$baseUrl}/users/{$sender}/mailFolders/SentItems/messages",
                                    [
                                        '$orderby' => 'sentDateTime desc',
                                        '$select'  => 'id,internetMessageId',
                                        '$top'     => 20,
                                    ]
                                );
                                foreach ($searchResult->json('value') ?? [] as $msg) {
                                    if (($msg['internetMessageId'] ?? '') === $ticketMsg->email_message_id) {
                                        $sentMsgId = $msg['id'];
                                        break 2;
                                    }
                                }
                            }

                            if ($sentMsgId) {
                                // Attachment ID juga berubah setelah draft dikirim.
                                // Fetch daftar attachment dari Sent Items, cocokkan berdasarkan nama file.
                                $sentAttId = $attachment->graph_attachment_id; // fallback
                                try {
                                    $attList = Http::withToken($token)->get(
                                        "{$baseUrl}/users/{$sender}/messages/{$sentMsgId}/attachments",
                                        ['$select' => 'id,name']
                                    );
                                    foreach ($attList->json('value') ?? [] as $sa) {
                                        if (strtolower($sa['name'] ?? '') === strtolower($attachment->file_name ?? '')) {
                                            $sentAttId = $sa['id'];
                                            break;
                                        }
                                    }
                                } catch (\Exception $attE) {
                                    Log::warning('AttachmentController@show: failed to fetch Sent Items attachment list', [
                                        'attachment_id' => $id,
                                        'error'         => $attE->getMessage(),
                                    ]);
                                }

                                // Simpan kedua ID yang benar ke DB untuk request berikutnya
                                $attachment->update([
                                    'graph_message_id'    => $sentMsgId,
                                    'graph_attachment_id' => $sentAttId,
                                ]);

                                // Retry fetch dengan kedua ID yang sudah diperbarui
                                $response = Http::withToken($token)->get(
                                    "{$baseUrl}/users/{$sender}/messages/{$sentMsgId}/attachments/{$sentAttId}"
                                );
                            } else {
                                Log::warning('AttachmentController@show: sentMsgId not found in SentItems', [
                                    'attachment_id'    => $id,
                                    'email_message_id' => $ticketMsg->email_message_id,
                                ]);
                            }
                        } catch (\Exception $retryE) {
                            Log::warning('AttachmentController@show: Graph retry failed', [
                                'attachment_id' => $id,
                                'error'         => $retryE->getMessage(),
                            ]);
                        }
                    }
                }

                if (!$response->successful()) {
                    Log::warning('AttachmentController@show: Graph request failed', [
                        'attachment_id' => $id,
                        'status'        => $response->status(),
                        'body'          => substr($response->body(), 0, 500),
                    ]);
                    abort(404, 'The file could not be retrieved from Microsoft Graph. The source email may have been deleted from the inbox.');
                }
            }

            $data    = $response->json();
            $content = base64_decode($data['contentBytes'] ?? '');

            if (empty($content)) {
                abort(404, 'The file content is empty. The attachment may be corrupted or unavailable.');
            }

            $mime     = $data['contentType'] ?? $attachment->mime_type ?? 'application/octet-stream';
            $filename = $data['name'] ?? $attachment->file_name ?? 'attachment';

            Log::info('AttachmentController: file downloaded via Graph', [
                'attachment_id' => $id,
                'file_name'     => $filename,
                'ticket_id'     => $attachment->ticket_id ?? null,
                'mime_type'     => $mime,
                'accessed_by'   => $sessionUser['eci'] ?? $sessionUser['name'] ?? $sessionUser['id'] ?? 'unknown',
            ]);

            // Inline: tampilkan di browser (gambar, PDF). Attachment: paksa download.
            $disposition = $attachment->is_inline ? 'inline' : 'attachment';

            $asciiName = str_replace(['"', '\\', "\r", "\n"], '', preg_replace('/[^\x20-\x7E]/', '_', $filename));

            return response($content, 200)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', $disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename))
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'private, max-age=3600');

        } catch (\Exception $e) {
            Log::error('AttachmentController@show: exception', [
                'attachment_id' => $id,
                'error'         => $e->getMessage(),
            ]);
            abort(500, 'An unexpected error occurred while retrieving the file.');
        }
    }

    /**
     * Ambil access token Microsoft Graph via client credentials flow.
     */
    private function getGraphToken(): string
    {
        $response = Http::asForm()->post(
            'https://login.microsoftonline.com/' . config('services.microsoft_graph.tenant_id') . '/oauth2/v2.0/token',
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
}
