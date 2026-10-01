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

        return $this->streamAttachment($id, $sessionUser);
    }

    /**
     * Varian TANPA login, dilindungi tanda tangan URL, untuk group chat Teams.
     *
     * Route: GET /teams/attachments/{id}  (middleware `signed`)
     *
     * Kenapa ada jalur kedua: lampiran internal note ikut dikirim ke group chat
     * tiket sebagai tautan, dan yang membukanya adalah klien Teams milik orang
     * yang tidak punya sesi EcoSystem. Memakai {@see show()} apa adanya berujung
     * layar login — persis masalah yang dulu bikin tautan SharePoint mentah
     * ditolak (lihat `streamSharePointFile()`).
     *
     * **Tanda tangan URL adalah kapabilitas, bukan izin.** Siapa pun yang
     * memegang tautannya bisa membuka berkasnya tanpa akun. Itu konsekuensi yang
     * diterima secara sadar (keputusan 23 Sep 2026) supaya gambar tetap tampil di
     * riwayat chat; masa berlakunya diatur `TEAMS_ATTACHMENT_LINK_DAYS` dan
     * default-nya tanpa batas. Karena itu aksesnya DICATAT — satu-satunya jejak
     * yang tersisa kalau tautannya bocor.
     */
    public function showForTeams(int $id)
    {
        Log::info('AttachmentController: akses lewat tautan Teams bertanda tangan', [
            'attachment_id' => $id,
            'ip'            => request()->ip(),
        ]);

        return $this->streamAttachment($id, ['name' => 'Teams (tautan bertanda tangan)']);
    }

    /**
     * Isi sesungguhnya {@see show()} — dipisah supaya jalur bertanda tangan
     * memakai logika streaming yang SAMA, bukan salinannya. Perbedaan kedua
     * jalur hanya pada cara menentukan siapa yang boleh mengakses.
     *
     * @param  array<string,mixed>  $sessionUser  pengakses, untuk baris log
     */
    private function streamAttachment(int $id, array $sessionUser)
    {
        $attachment = TicketAttachment::findOrFail($id);

        // File lokal (internal note / ticket non-email / record lama) → stream dari disk
        // dengan Content-Disposition berisi file_name asli. Tanpa ini browser memakai
        // nama hash acak dari path di disk.
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
            // Sama seperti cabang Graph: inline (gambar) boleh dirender di tab,
            // sisanya dipaksa download. Atribut download="" di <a> tetap memaksa
            // unduh untuk yang inline, dengan nama dari filename header ini.
            $disposition = $attachment->is_inline ? 'inline' : 'attachment';

            Log::info('AttachmentController: local file accessed', [
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
                'Content-Disposition' => $disposition . '; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($filename),
                'Content-Length'      => Storage::disk('public')->size($filePath),
                'Cache-Control'       => 'private, max-age=3600',
            ]);
        }

        // Berkas yang dibagikan di group chat Teams. Lampirannya berupa TAUTAN
        // SharePoint (`contentType: "reference"`), dan membuka tautan itu
        // langsung menuntut login Microsoft lebih dulu — sering berujung layar
        // "Request access" bagi yang tidak punya izin di SharePoint-nya.
        //
        // Diambilkan lewat Graph memakai kredensial aplikasi, persis pola
        // lampiran email di bawah: byte-nya TIDAK disimpan di server, tiap
        // request diambil ulang.
        if ($attachment->isCloudProxyable()) {
            return $this->streamSharePointFile($attachment, $sessionUser);
        }

        try {
            $sender  = config('services.microsoft_graph.sender_email');
            $token   = $this->getGraphToken();
            $baseUrl = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

            // Baris tanpa Graph ID (mis. balasan customer dari portal JARVIES saat
            // lookup Sent Items gagal) tetap bisa dipulihkan lewat email_message_id
            // dari ticket_message. Hasilnya disimpan ke DB, jadi request berikutnya
            // langsung memakai jalur normal.
            if (!$attachment->graph_message_id || !$attachment->graph_attachment_id) {
                $this->recoverGraphIds($attachment, $token, $sender, $baseUrl);
            }

            if (!$attachment->graph_message_id || !$attachment->graph_attachment_id) {
                abort(404, 'File ini tidak dapat diakses via proxy.');
            }

            // Fetch attachment beserta contentBytes dari Graph
            $response = Http::withToken($token)->get(
                "{$baseUrl}/users/{$sender}/messages/{$attachment->graph_message_id}/attachments/{$attachment->graph_attachment_id}"
            );

            if (!$response->successful()) {
                // Jika 404: kemungkinan graph_message_id adalah draft ID lama yang sudah
                // tidak valid setelah email dikirim (email pindah ke Sent Items dengan ID baru).
                // Cari message baru via internetMessageId dari ticket_message.
                if ($response->status() === 404 && $this->recoverGraphIds($attachment, $token, $sender, $baseUrl)) {
                    $response = Http::withToken($token)->get(
                        "{$baseUrl}/users/{$sender}/messages/{$attachment->graph_message_id}/attachments/{$attachment->graph_attachment_id}"
                    );
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

            // itemAttachment (email yang dilampirkan, mis. .eml) tidak menyediakan
            // contentBytes. Ambil konten MIME mentah (RFC822) via endpoint /$value.
            if (empty($content)) {
                $odataType = $data['@odata.type'] ?? '';
                $isItem    = str_contains($odataType, 'itemAttachment')
                          || $attachment->mime_type === 'message/rfc822';
                if ($isItem) {
                    $valueResp = Http::withToken($token)->get(
                        "{$baseUrl}/users/{$sender}/messages/{$attachment->graph_message_id}/attachments/{$attachment->graph_attachment_id}/\$value"
                    );
                    if ($valueResp->successful()) {
                        $content = $valueResp->body();
                    } else {
                        Log::warning('AttachmentController@show: gagal fetch /$value untuk itemAttachment', [
                            'attachment_id' => $id,
                            'status'        => $valueResp->status(),
                        ]);
                    }
                }
            }

            if (empty($content)) {
                abort(404, 'The file content is empty. The attachment may be corrupted or unavailable.');
            }

            $mime     = $data['contentType'] ?? $attachment->mime_type ?? 'application/octet-stream';
            $filename = $data['name'] ?? $attachment->file_name ?? 'attachment';

            // Email yang dilampirkan: pastikan mime message/rfc822 + ekstensi .eml
            // agar terunduh dan terbuka sebagai file email yang benar.
            if ($attachment->mime_type === 'message/rfc822' || str_contains($mime, 'rfc822')) {
                $mime = 'message/rfc822';
                if ($attachment->file_name) {
                    $filename = $attachment->file_name;
                }
                if (!preg_match('/\.eml$/i', $filename)) {
                    $filename .= '.eml';
                }
            }

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
     * Cari ulang ID pesan + ID attachment yang valid di mailbox Graph, lalu simpan ke DB.
     *
     * Dipakai saat ID kosong (lookup Sent Items saat pengiriman gagal) atau sudah
     * tidak valid (draft ID yang berubah setelah email terkirim). Kuncinya
     * internetMessageId yang tersimpan di ticket_message.email_message_id; attachment
     * dicocokkan berdasarkan nama file.
     *
     * @return bool true jika ID berhasil ditemukan dan disimpan
     */
    private function recoverGraphIds(TicketAttachment $attachment, string $token, string $sender, string $baseUrl): bool
    {
        if (!$attachment->message_id) {
            return false;
        }

        $internetMsgId = DB::table('ticket_message')
            ->where('id', $attachment->message_id)
            ->value('email_message_id');

        if (!$internetMsgId) {
            return false;
        }

        try {
            $msgId = null;

            // 1) Filter langsung di seluruh mailbox (mencakup email masuk yang dipindah folder).
            $filtered = Http::withToken($token)->get("{$baseUrl}/users/{$sender}/messages", [
                '$filter' => "internetMessageId eq '" . str_replace("'", "''", $internetMsgId) . "'",
                '$select' => 'id',
                '$top'    => 1,
            ]);
            $msgId = $filtered->successful() ? ($filtered->json('value.0.id') ?? null) : null;

            // 2) Fallback: $filter pada internetMessageId tidak selalu andal, jadi pindai
            //    pesan terbaru di Sent Items dan cocokkan di PHP.
            if (!$msgId) {
                $scan = Http::withToken($token)->get("{$baseUrl}/users/{$sender}/mailFolders/SentItems/messages", [
                    '$orderby' => 'sentDateTime desc',
                    '$select'  => 'id,internetMessageId',
                    '$top'     => 50,
                ]);
                foreach ($scan->json('value') ?? [] as $msg) {
                    if (($msg['internetMessageId'] ?? '') === $internetMsgId) {
                        $msgId = $msg['id'];
                        break;
                    }
                }
            }

            if (!$msgId) {
                Log::warning('AttachmentController: recoverGraphIds: pesan tidak ditemukan di mailbox', [
                    'attachment_id'    => $attachment->id,
                    'email_message_id' => $internetMsgId,
                ]);
                return false;
            }

            // Attachment ID juga berubah setelah draft dikirim: cocokkan by nama file.
            $attList = Http::withToken($token)->get(
                "{$baseUrl}/users/{$sender}/messages/{$msgId}/attachments",
                ['$select' => 'id,name']
            );
            $attId = null;
            foreach ($attList->json('value') ?? [] as $sa) {
                if (strtolower($sa['name'] ?? '') === strtolower($attachment->file_name ?? '')) {
                    $attId = $sa['id'];
                    break;
                }
            }

            if (!$attId) {
                Log::warning('AttachmentController: recoverGraphIds: attachment tidak cocok by nama', [
                    'attachment_id' => $attachment->id,
                    'file_name'     => $attachment->file_name,
                ]);
                return false;
            }

            $attachment->update([
                'graph_message_id'    => $msgId,
                'graph_attachment_id' => $attId,
            ]);

            Log::info('AttachmentController: Graph ID dipulihkan', ['attachment_id' => $attachment->id]);

            return true;
        } catch (\Exception $e) {
            Log::warning('AttachmentController: recoverGraphIds gagal', [
                'attachment_id' => $attachment->id,
                'error'         => $e->getMessage(),
            ]);
            return false;
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

    /**
     * Stream berkas SharePoint/OneDrive yang dibagikan di group chat Teams.
     *
     * Graph tidak menerima URL SharePoint apa adanya; ia harus diubah dulu jadi
     * "sharing token" — base64url dari URL-nya, berawalan `u!`. Dari situ
     * /shares/{token}/driveItem memberi metadata (nama, MIME, ukuran) dan
     * /content memberi byte-nya.
     *
     * Aksesnya dijaga di dua tempat: pemanggil WAJIB sudah login (dicek di
     * show()), dan host URL-nya dibatasi di TicketAttachment::isCloudProxyable().
     */
    private function streamSharePointFile(TicketAttachment $attachment, array $sessionUser)
    {
        $graph = app(\App\Services\Teams\TeamsGraphClient::class);
        $token = "u!" . rtrim(strtr(base64_encode($attachment->link_url), "+/", "-_"), "=");

        try {
            $meta = $graph->get("shares/{$token}/driveItem");
        } catch (\Throwable $e) {
            Log::warning("AttachmentController: gagal membaca metadata berkas SharePoint", [
                "attachment_id" => $attachment->id,
                "error"         => $e->getMessage(),
            ]);
            abort(404, "Berkas tidak dapat diakses. Mungkin sudah dipindah atau dihapus di SharePoint.");
        }

        $filename = $meta["name"] ?? $attachment->file_name ?? "file";
        $mime     = $meta["file"]["mimeType"] ?? "application/octet-stream";
        $size     = (int) ($meta["size"] ?? 0);

        // Berkas besar TIDAK diproksi: Graph mengembalikan seluruh isi sekaligus,
        // jadi memuatnya ke memori PHP hanya untuk diteruskan akan menjatuhkan
        // proses pada berkas ratusan MB. Untuk yang sebesar itu, lebih baik
        // pengguna dilempar ke SharePoint-nya — perlu login, tapi jalan.
        $maxProxyBytes = max(1, (int) config("services.teams_sync.max_proxy_file_mb", 25)) * 1024 * 1024;

        if ($size > $maxProxyBytes) {
            Log::info("AttachmentController: berkas terlalu besar untuk diproksi, dialihkan ke SharePoint", [
                "attachment_id" => $attachment->id,
                "size"          => $size,
            ]);

            return redirect()->away($attachment->link_url);
        }

        try {
            $response = $graph->getRaw("shares/{$token}/driveItem/content");
        } catch (\Throwable $e) {
            Log::warning("AttachmentController: gagal mengunduh berkas SharePoint", [
                "attachment_id" => $attachment->id,
                "error"         => $e->getMessage(),
            ]);
            abort(404, "Berkas tidak dapat diunduh.");
        }

        Log::info("AttachmentController: berkas Teams/SharePoint diakses", [
            "attachment_id" => $attachment->id,
            "file_name"     => $filename,
            "ticket_id"     => $attachment->ticket_id,
            "accessed_by"   => $sessionUser["eci"] ?? $sessionUser["name"] ?? $sessionUser["id"] ?? "unknown",
        ]);

        $asciiName = str_replace(["\"", "\\", "
", "
"], "", preg_replace("/[^ -~]/", "_", $filename));

        return response($response->body(), 200, [
            "Content-Type"        => $mime,
            "Content-Disposition" => "attachment; filename=\"{$asciiName}\"; filename*=UTF-8''" . rawurlencode($filename),
            "Cache-Control"       => "private, max-age=600",
        ]);
    }
}