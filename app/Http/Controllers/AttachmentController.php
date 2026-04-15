<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AttachmentController extends Controller
{
    /**
     * Proxy: ambil file attachment dari Microsoft Graph dan stream ke browser.
     *
     * Route: GET /attachments/{id}  (CheckAuthToken middleware)
     *
     * File TIDAK disimpan di server — setiap request diambil langsung dari Graph.
     * Ini menghemat storage server sambil tetap menjaga keamanan akses (user harus login).
     */
    public function show(int $id)
    {
        // Wajib login
        $sessionUser = session('user');
        if (!$sessionUser) {
            abort(401, 'Silakan login terlebih dahulu.');
        }

        $attachment = TicketAttachment::findOrFail($id);

        // Record lama: file disimpan lokal → redirect ke storage path
        if (!$attachment->graph_message_id && $attachment->file_path) {
            return redirect('/storage/' . $attachment->file_path);
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
                                    Log::warning('AttachmentController@show: gagal fetch Sent Items att list', [
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
                                Log::warning('AttachmentController@show: sentMsgId tidak ditemukan di SentItems', [
                                    'attachment_id'    => $id,
                                    'email_message_id' => $ticketMsg->email_message_id,
                                ]);
                            }
                        } catch (\Exception $retryE) {
                            Log::warning('AttachmentController@show: retry Graph gagal', [
                                'attachment_id' => $id,
                                'error'         => $retryE->getMessage(),
                            ]);
                        }
                    }
                }

                if (!$response->successful()) {
                    Log::warning('AttachmentController@show: Graph gagal', [
                        'attachment_id' => $id,
                        'status'        => $response->status(),
                        'body'          => substr($response->body(), 0, 500),
                    ]);
                    abort(404, 'File tidak dapat diambil dari Microsoft Graph. Mungkin email sudah dihapus dari inbox.');
                }
            }

            $data    = $response->json();
            $content = base64_decode($data['contentBytes'] ?? '');

            if (empty($content)) {
                abort(404, 'Konten file kosong.');
            }

            $mime     = $data['contentType'] ?? $attachment->mime_type ?? 'application/octet-stream';
            $filename = $data['name'] ?? $attachment->file_name ?? 'attachment';

            // Inline: tampilkan di browser (gambar, PDF). Attachment: paksa download.
            $disposition = $attachment->is_inline ? 'inline' : 'attachment';

            return response($content, 200)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', $disposition . '; filename="' . rawurlencode($filename) . '"')
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'private, max-age=3600');

        } catch (\Exception $e) {
            Log::error('AttachmentController@show: exception', [
                'attachment_id' => $id,
                'error'         => $e->getMessage(),
            ]);
            abort(500, 'Terjadi kesalahan saat mengambil file.');
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
            throw new \RuntimeException('Gagal mendapatkan access token Graph' . $response->body());
        }

        return $response->json('access_token');
    }
}
