<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Lampiran BERBASIS TEKS untuk AI Assistant / AI Research.
 *
 * Ada dua hal yang bermuara ke sini, dan keduanya sebenarnya sama:
 *
 *   1. TEMPELAN BESAR. User menempel 8.000 baris kode ke composer. Kalau itu
 *      masuk ke textarea, tiga hal rusak sekaligus: textarea-nya jadi tak
 *      terpakai, `message` kena batas 4.000 karakter, dan bubble chat-nya
 *      panjang tak berujung. Browser membungkusnya jadi berkas .txt (lihat
 *      view: aiOnPaste / airOnPaste) supaya jalurnya sama dengan lampiran.
 *
 *   2. BERKAS TEKS/KODE yang dilampirkan lewat tombol klip. Sebelum ini
 *      SEMUA yang bukan PDF/gambar ditolak dengan "file type isn't supported
 *      yet" — termasuk .csv, .md, .json, .log, .php. Padahal justru berkas
 *      itulah yang paling gampang dibaca model: isinya sudah teks.
 *
 * Keduanya diubah jadi content block `text` biasa (BUKAN `document` base64),
 * karena teks tidak perlu dikodekan: base64 menaikkan ukurannya ~33% tanpa
 * memberi apa pun, dan blok teks bekerja di semua model tanpa syarat beta.
 *
 * Isinya dibungkus tag <attached_text …> supaya model bisa membedakan mana
 * yang ditulis user dan mana yang cuma ditempel — tanpa itu, potongan kode
 * panjang gampang dikira instruksi.
 */
class AiTextAttachment
{
    /**
     * Plafon per berkas: 600 rb karakter ≈ 150 rb token.
     *
     * Yang mengikat di sini BUKAN jendela konteks — Sonnet 5 dan Opus 5
     * (dua model yang benar-benar dipakai AI Research) punya jendela 1 juta
     * token, jadi angka ini masih sangat longgar. Yang mengikat adalah
     * BIAYA: lampiran menempel di percakapan dan dikirim ULANG pada setiap
     * pertanyaan lanjutan, jadi satu tempelan besar dibayar berkali-kali.
     *
     * 20 Agu 2026: 400 rb → 600 rb. Angka 400 rb memotong file view nyata
     * di repo ini (show.blade.php, 8.645 baris ≈ 485 rb karakter) di sekitar
     * 82% — persis kasus yang paling sering ditempel orang, dan potongan
     * yang hilang justru bagian bawah file.
     *
     * Yang di atas plafon DIPOTONG, bukan ditolak: user yang menempel satu
     * file raksasa lebih terbantu oleh jawaban atas bagian awalnya plus
     * peringatan jujur, daripada oleh penolakan mentah.
     */
    public const MAX_CHARS = 600000;

    /** Ambang "ini tempelan, bukan ketikan" — dipakai juga oleh sisi browser. */
    public const PASTE_THRESHOLD_CHARS = 1500;
    public const PASTE_THRESHOLD_LINES = 25;

    /**
     * Ekstensi yang diperlakukan sebagai teks.
     *
     * Ekstensi diperiksa DULUAN, sebelum MIME, karena tebakan MIME untuk
     * berkas kode sering salah dan salahnya diam-diam: .ts dideteksi sebagai
     * `video/mp2t`, .md sebagai `text/plain`, .csv kadang `application/csv`.
     * Menyandarkan keputusan pada MIME berarti file TypeScript ditolak
     * sebagai "video".
     */
    private const TEXT_EXTENSIONS = [
        'txt', 'md', 'markdown', 'log', 'csv', 'tsv', 'json', 'jsonl', 'xml', 'yml', 'yaml',
        'ini', 'env', 'conf', 'sql', 'html', 'htm', 'css', 'scss', 'less',
        'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'vue', 'svelte',
        'php', 'blade', 'py', 'rb', 'go', 'rs', 'java', 'kt', 'cs', 'c', 'h', 'cpp', 'hpp',
        'sh', 'bash', 'ps1', 'bat', 'lock', 'gitignore', 'dockerfile', 'diff', 'patch',
    ];

    /** Berkas teks tanpa ekstensi yang berarti (Dockerfile, Makefile, …). */
    private const TEXT_BASENAMES = ['dockerfile', 'makefile', 'procfile', 'gemfile', 'rakefile'];

    /**
     * Apakah berkas ini bisa dibaca sebagai teks?
     *
     * Cek terakhir sengaja melihat ISI berkas, bukan namanya: file bernama
     * .txt yang ternyata biner akan lolos nama tapi gagal di sini, dan itu
     * yang kita mau — mengirim byte biner sebagai teks membuang token tanpa
     * hasil.
     */
    public static function isTextual(UploadedFile $file): bool
    {
        $name = strtolower($file->getClientOriginalName());
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, self::TEXT_EXTENSIONS, true)) {
            return true;
        }

        if (in_array(pathinfo($name, PATHINFO_FILENAME), self::TEXT_BASENAMES, true)) {
            return true;
        }

        $mime = (string) $file->getMimeType();

        if (str_starts_with($mime, 'text/') || in_array($mime, ['application/json', 'application/xml', 'application/csv'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Berkas terunggah → lampiran teks siap kirim.
     *
     * @return array{type: string, name: string, text: string, chars: int, lines: int, truncated: bool}
     */
    public static function fromFile(UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());

        return self::fromText($file->getClientOriginalName(), $raw);
    }

    /**
     * @return array{type: string, name: string, text: string, chars: int, lines: int, truncated: bool}
     */
    public static function fromText(string $name, string $raw): array
    {
        $text = self::toUtf8($raw);

        // chars/lines menggambarkan berkas UTUH, bukan potongannya — kalau
        // keduanya dihitung setelah pemotongan, header lampiran berbohong
        // tentang seberapa besar yang sebenarnya ada. Yang dipotong
        // diumumkan lewat penanda [... truncated ...] di akhir isi.
        $chars = mb_strlen($text);
        $lines = substr_count($text, "\n") + 1;
        $truncated = $chars > self::MAX_CHARS;

        if ($truncated) {
            $text = mb_substr($text, 0, self::MAX_CHARS);
        }

        return [
            'type' => 'text',
            'name' => self::safeName($name),
            'text' => $text,
            'chars' => $chars,
            'lines' => $lines,
            'truncated' => $truncated,
        ];
    }

    /**
     * Lampiran teks → content block untuk API.
     *
     * @param array{name: string, text: string, chars: int, lines: int, truncated: bool} $attachment
     * @return array<string, mixed>
     */
    public static function toContentBlock(array $attachment, int $index): array
    {
        $name = $attachment['name'];
        $lines = $attachment['lines'];
        $chars = $attachment['chars'];

        $header = "<attached_text index=\"{$index}\" name=\"{$name}\" lines=\"{$lines}\" chars=\"{$chars}\">";

        $footer = '</attached_text>';

        if (!empty($attachment['truncated'])) {
            $footer = "\n[... truncated: only the first " . number_format(self::MAX_CHARS)
                . ' of ' . number_format($chars) . " characters are shown ...]\n" . $footer;
        }

        return [
            'type' => 'text',
            'text' => $header . "\n" . $attachment['text'] . "\n" . $footer,
        ];
    }

    /**
     * Ukuran lampiran teks dalam byte — dipakai anggaran konteks AI Research,
     * yang menghitung SEMUA lampiran dengan satuan yang sama.
     */
    public static function bytes(array $attachment): int
    {
        return strlen((string) ($attachment['text'] ?? ''));
    }

    /**
     * CSV dari Excel datang sebagai Windows-1252, dan byte non-UTF-8 membuat
     * json_encode() pada payload API mengembalikan false — request-nya gagal
     * tanpa pesan yang menjelaskan apa pun. Sama seperti helper toUtf8() di
     * import CSV planning.
     */
    private static function toUtf8(string $raw): string
    {
        // BOM UTF-8 ikut terbaca sebagai karakter kalau tidak dibuang.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252, ISO-8859-1');
        }

        // NBSP dan CR dari file Windows tidak membawa makna di sini, tapi
        // membuat diff/penghitungan baris jadi ribut.
        return str_replace(["\xC2\xA0", "\r\n", "\r"], [' ', "\n", "\n"], $raw);
    }

    /** Nama masuk ke prompt, jadi tanda kutip dan '<' harus dijinakkan. */
    private static function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F<>"]/', '', $name) ?? $name;
        $name = trim($name) ?: 'pasted-text.txt';

        return mb_substr($name, 0, 120);
    }
}
