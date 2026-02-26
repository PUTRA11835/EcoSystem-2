<?php

namespace App\Http\Controllers;

use App\Models\TicketAttachment;
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
            $sender  = env('MS_SENDER_EMAIL');
            $token   = $this->getGraphToken();
            $baseUrl = rtrim(env('GRAPH_BASE_URL', 'https://graph.microsoft.com/v1.0'), '/');

            // Fetch attachment beserta contentBytes dari Graph
            $response = Http::withToken($token)->get(
                "{$baseUrl}/users/{$sender}/messages/{$attachment->graph_message_id}/attachments/{$attachment->graph_attachment_id}"
            );

            if (!$response->successful()) {
                Log::warning('AttachmentController@show: Graph gagal', [
                    'attachment_id' => $id,
                    'status'        => $response->status(),
                    'body'          => substr($response->body(), 0, 500),
                ]);
                abort(404, 'File tidak dapat diambil dari Microsoft Graph. Mungkin email sudah dihapus dari inbox.');
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
            'https://login.microsoftonline.com/' . env('MS_TENANT_ID') . '/oauth2/v2.0/token',
            [
                'grant_type'    => 'client_credentials',
                'client_id'     => env('MS_CLIENT_ID'),
                'client_secret' => env('MS_CLIENT_SECRET'),
                'scope'         => 'https://graph.microsoft.com/.default',
            ]
        );

        if (!$response->successful()) {
            throw new \RuntimeException('Gagal mendapatkan access token Graph: ' . $response->body());
        }

        return $response->json('access_token');
    }
}
