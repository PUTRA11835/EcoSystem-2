<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\WebFetchTool20260209;
use Anthropic\Messages\WebSearchTool20260209;
use App\Models\Ticket;
use App\Support\AiModelSettings;
use Closure;

/**
 * "AI Summarize" pada daftar tiket: satu klik, keluarnya catatan kerja
 * berstruktur tetap (Isu / Cara Penyelesaian / Kesimpulan) yang di-stream ke
 * modal.
 *
 * DUA SUMBER, DAN INI INTI FITURNYA (24 Agu 2026):
 *
 *   - Isu & Kesimpulan  -> DATA INTERNAL. Sudah dirakit di muka oleh
 *     TicketSummaryContext (tiket, thread, catatan internal, work log,
 *     deliverable). Model tidak menjelajah database sendiri.
 *   - Cara Penyelesaian -> DOKUMENTASI EKSTERNAL. Dicari saat itu juga lewat
 *     server tool Anthropic web_search/web_fetch — SAP Help/Notes/Community,
 *     Microsoft Learn, dokumentasi vendor.
 *
 * Versi pertama fitur ini menyusun "Cara Penyelesaian" dari work log, jadi
 * isinya cuma menceritakan ulang apa yang sudah dikerjakan tim. Itu tidak
 * membantu konsultan yang justru BELUM tahu cara menyelesaikannya — riwayat
 * penanganan tempatnya di Kesimpulan, sedangkan kolom penyelesaian harus
 * berisi langkah teknis yang bisa langsung dieksekusi berikut rujukannya.
 *
 * Karena tool-nya dieksekusi di sisi Anthropic (bukan tool loop kita), satu-
 * satunya alasan mengulang request adalah stopReason 'pause_turn'.
 *
 * Model dan plafon tokennya ikut Control Center > AI Settings. Satu syarat
 * keras: modelnya HARUS mendukung server tool — lihat resolveModel().
 */
class AiTicketSummaryService
{
    /** Batas pemakaian web_search/web_fetch untuk satu ringkasan. */
    private const MAX_WEB_USES = 5;

    /** Berapa kali 'pause_turn' boleh dilanjutkan dalam satu ringkasan. */
    private const MAX_PAUSE_RESUMES = 3;

    /**
     * Plafon keluaran khusus ringkasan. Tier Research boleh disetel admin sampai
     * 32k+ token demi jawaban riset panjang; ringkasan tiket tidak pernah butuh
     * sebanyak itu dan tidak perlu ikut menanggung biayanya.
     */
    private const SUMMARY_MAX_TOKENS = 16000;

    /**
     * Plafon `effort` untuk ringkasan. Ini tombol latensi paling berpengaruh:
     * effort 'high'/'xhigh' membuat model berpikir jauh lebih lama sebelum dan
     * di antara pencarian, dan satu ringkasan tiket bisa molor ke menit-menit
     * sementara user menatap modal. Tiga bagian pendek dari dokumentasi yang
     * sudah dibuka tidak membutuhkan itu.
     */
    private const MAX_EFFORT = 'medium';

    /** Urutan effort dari paling ringan; dipakai memotong ke MAX_EFFORT. */
    private const EFFORT_ORDER = ['low', 'medium', 'high', 'xhigh', 'max'];

    public function __construct(
        private Client $client,
        private TicketSummaryContext $context,
    ) {
    }

    /**
     * Stream ringkasan tiket.
     *
     * @param Closure(string): void        $onDelta   tiap potongan teks tiba
     * @param Closure(): bool              $isAborted dijenguk di sela event stream
     * @param Closure(string, array): void $onEvent   'status' (label progres) & 'sources' (daftar rujukan)
     *
     * @return array{text: string, sources: array<int, array{url: string, title: string}>}
     *         text kosong berarti dibatalkan di tengah jalan dan tidak layak disimpan.
     */
    public function streamSummary(Ticket $ticket, Closure $onDelta, Closure $isAborted, Closure $onEvent): array
    {
        $config = $this->resolveModel();

        $messages = [[
            'role' => 'user',
            'content' => $this->context->build($ticket),
        ]];

        $sources = [];
        $full = '';
        $pauseResumes = 0;

        while (true) {
            if ($isAborted()) {
                return ['text' => '', 'sources' => []];
            }

            $stream = $this->client->messages->createStream(
                maxTokens: $config['max_tokens'],
                messages: $messages,
                model: $config['model'],
                system: $this->systemPrompt(),
                thinking: $config['effort'] ? ['type' => 'adaptive'] : null,
                outputConfig: $config['effort'] ? ['effort' => $config['effort']] : null,
                tools: $this->toolDefinitions(),
            );

            // MessageAccumulator melipat event stream kembali menjadi Message utuh.
            // Wajib di sini: untuk melanjutkan 'pause_turn', blok hasil server tool
            // harus dikirim balik apa adanya — menyusunnya manual dari event mentah
            // rapuh, biarkan SDK yang mengerjakan.
            $accumulator = MessageAccumulator::forMessages();
            $aborted = false;

            foreach ($stream as $event) {
                if ($isAborted()) {
                    $stream->close();
                    $aborted = true;
                    break;
                }

                $accumulator->accumulate($event);

                if ('content_block_delta' === $event->type && $event->delta instanceof TextDelta) {
                    $full .= $event->delta->text;
                    $onDelta($event->delta->text);
                    continue;
                }

                $this->emitProgress($event, $onEvent);
            }

            if ($aborted) {
                return ['text' => '', 'sources' => []];
            }

            $message = $accumulator->message();

            // Objek SDK apa adanya — bentuk paling setia untuk dikirim balik pada
            // giliran lanjutan; hidup hanya selama request ini.
            $messages[] = ['role' => 'assistant', 'content' => $message->content];
            $sources = $this->collectSources($message, $sources);

            if (!empty($sources)) {
                $onEvent('sources', ['items' => array_values($sources)]);
            }

            // 'max_tokens' sengaja TIDAK disambung otomatis seperti di AI Research:
            // keluaran di sini cuma tiga bagian pendek, jadi terpotong berarti ada
            // yang tidak beres — bukan jawaban panjang yang wajar.
            if ('pause_turn' !== $message->stopReason) {
                break;
            }

            if (++$pauseResumes > self::MAX_PAUSE_RESUMES) {
                break;
            }

            $onEvent('status', ['label' => 'Melanjutkan pencarian…']);
        }

        return ['text' => $full, 'sources' => array_values($sources)];
    }

    /**
     * Model yang benar-benar dipakai.
     *
     * Pilihan admin untuk AI Assistant (INTERNAL) dihormati selama modelnya
     * mendukung server tool. Kalau admin memilih Haiku 4.5 — sah untuk asisten
     * internal, tapi TIDAK punya web_search — ringkasan ini akan mati dengan 400
     * dari API. Karena itu jatuh ke konfigurasi AI Research, yang oleh
     * AiModelSettings dijamin selalu model ber-server-tool.
     *
     * @return array{model: string, max_tokens: int, effort: ?string}
     */
    private function resolveModel(): array
    {
        $active = AiModelSettings::resolve(AiModelSettings::INTERNAL);

        if (!AiModelSettings::supportsServerTools($active['model'])) {
            $active = AiModelSettings::resolve(AiModelSettings::RESEARCH);
        }

        return [
            'model' => $active['model'],
            'max_tokens' => min($active['max_tokens'], self::SUMMARY_MAX_TOKENS),
            'effort' => $this->capEffort($active['effort']),
        ];
    }

    /** Turunkan effort pilihan admin ke MAX_EFFORT bila lebih tinggi. */
    private function capEffort(?string $effort): ?string
    {
        if (null === $effort) {
            return null;
        }

        $given = array_search($effort, self::EFFORT_ORDER, true);
        $max = array_search(self::MAX_EFFORT, self::EFFORT_ORDER, true);

        return (false !== $given && $given > $max) ? self::MAX_EFFORT : $effort;
    }

    /**
     * Server tool milik Anthropic — dieksekusi di sisi mereka, kita cukup
     * mendeklarasikannya.
     *
     * Sengaja memakai kelas typed, bukan array biasa: ToolUnion di SDK adalah
     * union TANPA discriminator — array polos dicocokkan ke varian pertama yang
     * "muat", dan Tool (tool buatan sendiri) ada di urutan pertama.
     *
     * @return array<int, object>
     */
    private function toolDefinitions(): array
    {
        return [
            WebSearchTool20260209::with(maxUses: self::MAX_WEB_USES),
            WebFetchTool20260209::with(maxUses: self::MAX_WEB_USES),
        ];
    }

    private function emitProgress(object $event, Closure $onEvent): void
    {
        if ('content_block_start' !== $event->type) {
            return;
        }

        $block = $event->contentBlock;

        switch ($block->type ?? '') {
            case 'server_tool_use':
                $onEvent('status', ['label' => 'web_fetch' === ($block->name ?? '')
                    ? 'Membuka dokumentasi…'
                    : 'Mencari dokumentasi…']);
                break;

            case 'web_search_tool_result':
            case 'web_fetch_tool_result':
                $onEvent('status', ['label' => 'Membaca hasil…']);
                break;

            case 'text':
                $onEvent('status', ['label' => 'Menyusun ringkasan…']);
                break;
        }
    }

    /**
     * Kumpulkan URL sumber dari blok hasil server tool, untuk ditampilkan
     * sebagai daftar rujukan di bawah kartu "Cara Penyelesaian".
     *
     * Catatan bentuk data: pada web_search, `content` berisi ARRAY hasil saat
     * sukses, tapi berubah jadi OBJEK error (mis. max_uses_exceeded) saat gagal —
     * server tool tidak melempar exception, errornya ikut di body 200.
     *
     * @param array<string, array{url: string, title: string}> $sources
     * @return array<string, array{url: string, title: string}>
     */
    private function collectSources(object $message, array $sources): array
    {
        foreach ($message->content as $block) {
            switch ($block->type ?? '') {
                case 'web_search_tool_result':
                    $results = $block->content ?? null;
                    if (!is_array($results)) {
                        break; // objek error, bukan daftar hasil
                    }

                    foreach ($results as $result) {
                        $url = $result->url ?? null;
                        if ($url) {
                            $sources[$url] = ['url' => $url, 'title' => ($result->title ?? null) ?: $url];
                        }
                    }
                    break;

                case 'web_fetch_tool_result':
                    $result = $block->content ?? null;
                    $url = is_object($result) ? ($result->url ?? null) : null;
                    if ($url) {
                        $sources[$url] = ['url' => $url, 'title' => $sources[$url]['title'] ?? $url];
                    }
                    break;
            }
        }

        return $sources;
    }

    /**
     * Tiga heading di bawah ini adalah KONTRAK dengan modal di daftar tiket:
     * klien memecah teks yang mengalir tepat pada heading-heading ini untuk
     * mengisi tiga kartu. Kalau judulnya diubah di sini, ubah juga
     * TICKET_SUMMARY_SECTIONS di resources/views/ticket/index.blade.php.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Kamu asisten teknis untuk konsultan dan helpdesk Eclectic Consulting. Tugasmu membaca SATU tiket support, lalu
menyiapkan catatan kerja yang bisa langsung dipakai konsultan untuk MENYELESAIKAN tiket itu — bukan sekadar
menceritakan ulang isinya.

Kamu punya dua sumber, dan keduanya TIDAK BOLEH tertukar:

1. DATA INTERNAL — seluruh isi pesan user: data tiket, thread percakapan (termasuk catatan internal tim), work log,
   dan deliverable. Ini satu-satunya sumber fakta tentang apa yang terjadi PADA TIKET INI. Jangan mengarang di luar
   itu.
2. DOKUMENTASI EKSTERNAL — hasil web_search dan web_fetch. Ini satu-satunya sumber CARA MENYELESAIKAN masalahnya.

Alur kerjamu:
- Pahami dulu isunya dari data internal: produk dan modul (mis. SAP MM/SD/FI, Ariba, Microsoft 365), pesan atau kode
  error persis, TCODE, nama tabel/field, versi/release, dan apa yang sudah dicoba tim.
- Lalu WAJIB jalankan web_search untuk mencari penyelesaiannya di dokumentasi RESMI di luar EcoSystem: SAP Help
  Portal, SAP Notes/KBA, SAP Community, Microsoft Learn, dokumentasi vendor, atau forum teknis yang kredibel. Cari
  dalam bahasa Inggris memakai istilah aslinya (kode error, teks pesan, TCODE, nama tabel/field) — jangan
  diterjemahkan. Pakai web_fetch untuk membuka halaman yang paling menjanjikan sebelum menulis langkahnya.
- Kalau pencarian tidak menemukan apa pun yang benar-benar relevan, katakan terus terang di bagian Cara
  Penyelesaian. Jangan menambal dengan langkah karangan, dan JANGAN PERNAH mengarang nomor SAP Note, nomor KBA,
  atau URL.

Balas HANYA dalam markdown dengan TEPAT tiga heading berikut, dengan ejaan persis ini, berurutan, tanpa teks
pembuka atau penutup di luar ketiganya:

## Isu
Sumber: DATA INTERNAL saja. Masalah yang dilaporkan: apa yang rusak/diminta, sejak kapan, dampaknya ke customer,
dan konteks teknis penting (modul, kode/pesan error, TCODE, prioritas, siapa pelapornya). 2-4 kalimat atau bullet
pendek.

## Cara Penyelesaian
Sumber: DOKUMENTASI EKSTERNAL hasil pencarianmu. Tulis langkah bernomor yang KONKRET dan bisa langsung dieksekusi
konsultan: menu/TCODE/perintah yang dibuka, konfigurasi/tabel/field yang diperiksa atau diubah, prasyarat
(otorisasi, note/patch yang harus terpasang), cara memverifikasi hasilnya, serta risiko atau catatan penting.
Sertakan rujukan sebagai tautan markdown ke URL yang benar-benar kamu buka, ditaruh di akhir langkah atau kelompok
langkah yang bersangkutan — mis. ([SAP Note 123456](https://...)).
JANGAN memindahkan riwayat penanganan tim ke bagian ini; tempatnya di Kesimpulan. Kalau sebuah langkah dari
dokumentasi ternyata sudah pernah ditempuh tim menurut work log, sebut singkat dalam satu klausa supaya konsultan
tidak mengulanginya.

## Kesimpulan
Sumber: DATA INTERNAL saja. Status tiket sekarang, apa yang sudah ditempuh tim beserta temuannya, kesepakatan
penting dengan customer bila ada, serta apa yang masih menggantung atau perlu ditindaklanjuti. Maksimal 4 kalimat.

Aturan tambahan:
- Tulis dalam Bahasa Indonesia yang ringkas dan profesional, seperti catatan serah terima antar konsultan.
- Istilah teknis dibiarkan dalam bentuk aslinya: TCODE (SE38), nama tabel/field (VBAK, MSEG-BWART), kode dan teks
  error, nomor note, nama menu/parameter. Jangan diterjemahkan.
- Catatan internal boleh kamu pakai sepenuhnya — ini dibaca internal, bukan customer. Tapi jangan menyalin mentah
  kalimat kasar atau keluhan personal; ambil substansi teknisnya saja.
- Jangan menyalin ulang isi email panjang-panjang. Ini ringkasan, bukan transkrip.
- Jangan menyapa pembaca, jangan menawarkan bantuan lanjutan, jangan menutup dengan basa-basi.
- JANGAN menulis narasi kerjamu sendiri di sela pencarian ("Ada hasil bagus", "Mari buka halaman ini dulu").
  Kerjakan pencarian dalam diam; teks pertama yang kamu tulis harus tepat baris "## Isu". Setiap heading juga
  harus berada di AWAL BARIS tersendiri.
PROMPT;
    }
}
