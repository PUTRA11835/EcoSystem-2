<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * InlineImageService
 *
 * Menegakkan satu invariant untuk menjaga usia database:
 *
 *   >> Byte gambar TIDAK PERNAH tersimpan di kolom DB (message_html /
 *      email_body_html longText). DB hanya menyimpan URL pendek /storage/... <<
 *
 * Editor balasan (Quill) dan sebagian email masuk menyisipkan gambar sebagai
 * data URI `<img src="data:image/...;base64,...">`. Bila HTML itu disimpan apa
 * adanya ke longText, satu gambar ~500 KB akan membengkakkan DB dan memperlambat
 * backup/replikasi. Service ini mengekstrak setiap data URI, menuliskan byte-nya
 * ke public disk (jalur yang sama dengan inline image dari Graph), lalu mengganti
 * `src` dengan `/storage/...` sehingga DB tetap ramping.
 *
 * Email KELUAR tidak terpengaruh: konversi /storage -> CID untuk email dilakukan
 * di EmailController (extractLocalStorageImages) saat membangun payload Graph.
 */
class InlineImageService
{
    /**
     * Ekstrak semua data URI base64 dari $html, simpan ke public disk, dan ganti
     * src dengan URL /storage/...
     *
     * @param  string        $html       HTML sumber (boleh mengandung data URI atau tidak).
     * @param  string        $folderKey  Segmen folder di bawah ticket-inline-images/
     *                                    (mis. ticket_id, atau "staging-{id}").
     * @param  callable|null $onStored    Dipanggil per gambar tersimpan dengan
     *                                    array metadata {file_path, file_name,
     *                                    file_size, mime_type}. Dipakai caller untuk
     *                                    membuat baris metadata (TicketAttachment).
     * @return string  HTML dengan data URI diganti /storage URL. Bila tidak ada
     *                 data URI, string asli dikembalikan tanpa perubahan.
     */
    public function persistBase64ToStorage(string $html, string $folderKey, ?callable $onStored = null): string
    {
        // Fast path: mayoritas pesan tidak punya data URI → hindari regex mahal.
        if (stripos($html, 'data:image') === false) {
            return $html;
        }

        // Base64 gambar bisa sangat panjang; naikkan backtrack limit sementara
        // agar preg_replace_callback tidak gagal (return null) untuk gambar besar.
        $prevLimit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '20000000');

        $result = preg_replace_callback(
            '/<img([^>]*?)\s+src="data:(image\/[a-zA-Z0-9.+\-]+);base64,([A-Za-z0-9+\/=\s]+)"([^>]*?)>/i',
            function (array $m) use ($folderKey, $onStored): string {
                $before = $m[1];
                $mime   = $m[2];
                $base64 = preg_replace('/\s+/', '', $m[3]); // buang whitespace di base64
                $after  = $m[4];

                $binary = base64_decode($base64, true);
                if ($binary === false || $binary === '') {
                    // Bukan base64 valid → jangan rusak konten, biarkan apa adanya.
                    return $m[0];
                }

                $ext      = $this->extForMime($mime);
                $safeName = Str::uuid() . '.' . $ext;
                $filePath = "ticket-inline-images/{$folderKey}/{$safeName}";

                try {
                    Storage::disk('public')->put($filePath, $binary);
                } catch (\Throwable $e) {
                    Log::warning('InlineImageService: gagal simpan inline base64 ke storage', [
                        'folder_key' => $folderKey,
                        'error'      => $e->getMessage(),
                    ]);
                    // Gagal simpan → biarkan data URI (lebih baik gambar tetap tampil
                    // daripada hilang); tidak ideal untuk DB tapi kasusnya langka.
                    return $m[0];
                }

                if ($onStored) {
                    $onStored([
                        'file_path' => $filePath,
                        'file_name' => 'image.' . $ext,
                        'file_size' => strlen($binary),
                        'mime_type' => $mime,
                    ]);
                }

                return '<img' . $before . ' src="/storage/' . $filePath . '"' . $after . '>';
            },
            $html
        );

        ini_set('pcre.backtrack_limit', $prevLimit);

        // preg_replace_callback mengembalikan null bila terjadi error internal
        // (mis. backtrack limit tetap terlampaui) → fallback ke HTML asli agar
        // konten tidak hilang.
        return $result ?? $html;
    }

    /**
     * Ekstensi file dari MIME image.
     */
    private function extForMime(string $mime): string
    {
        $sub = strtolower(explode('/', $mime)[1] ?? 'png');

        return match ($sub) {
            'jpeg', 'jpg'    => 'jpg',
            'png'            => 'png',
            'gif'            => 'gif',
            'webp'           => 'webp',
            'bmp'            => 'bmp',
            'svg+xml', 'svg' => 'svg',
            default          => 'png',
        };
    }
}
