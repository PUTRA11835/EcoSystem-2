<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\Employee;
use App\Services\Ai\Drivers\AiDriverFactory;
use App\Support\AiModelSettings;
use App\Support\AiTextAttachment;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI Research — asisten kedua, sumbernya PENGETAHUAN LUAR (bukan data EcoSystem).
 *
 * Bedanya dengan AiChatService:
 *   - AiChatService  : tool lokal (get_tickets, get_sla_summary, …) → data internal.
 *   - AiResearchService : tool SERVER-SIDE milik provider (web_search/web_fetch
 *     Anthropic, atau `web_search` bawaan Responses API OpenAI — lihat
 *     ResearchDriver/AiDriverFactory) → pencarian internet. Tidak ada tool loop
 *     di sisi kita: pencarian dijalankan di infrastruktur provider dan hasilnya
 *     sudah ikut dalam response yang sama.
 *
 * Di sisi kita, satu-satunya alasan mengulang request adalah plafon max_tokens
 * (lihat loop di streamReply()) — kelanjutan Claude 'pause_turn' tidak punya
 * padanan di provider lain dan sepenuhnya ditangani di dalam driver-nya sendiri,
 * tidak pernah terlihat sampai ke sini (lihat ResearchDriver's docblock).
 *
 * Sama seperti AiChatService, isi percakapan TIDAK PERNAH masuk database —
 * hanya cache 'ai_chat' dengan TTL 12 jam (lihat CACHE_TTL_MINUTES).
 */
class AiResearchService
{
    private const CACHE_STORE = 'ai_chat';
    /**
     * Berapa lama konteks kerja satu percakapan bertahan sejak pesan terakhir.
     * Di-refresh tiap pesan, jadi ini umur DIAM, bukan umur percakapan.
     *
     * 20 Agu 2026: dinaikkan 60 menit → 12 jam supaya percakapan yang
     * ditinggalkan sebentar (rapat, makan siang, pagi ke sore) tetap diingat
     * UTUH berikut lampirannya, bukan menyusut jadi 20 pesan terakhir tanpa
     * gambar. Sebelumnya jendela satu jam terlalu pendek untuk ritme kerja
     * konsultan — riset pagi hari sudah "lupa" saat dibuka lagi siangnya.
     *
     * DUA HAL YANG IKUT BERUBAH karena angka ini, dan keduanya disengaja:
     *
     *   1. Anggaran lampiran (~16 MB/percakapan) sekarang berlaku sepanjang
     *      12 jam itu, bukan satu jam. Percakapan yang banyak screenshot akan
     *      lebih cepat kena "attachment limit" — jalan keluarnya tetap sama:
     *      mulai chat baru.
     *   2. Berkas cache — termasuk byte gambar — menetap di disk 12× lebih
     *      lama. Karena itu `ai:prune-conversations` dijadwalkan lebih sering
     *      (lihat routes/console.php): cache file Laravel tidak punya GC.
     *
     * Public karena halaman chat menampilkannya kepada user.
     */
    public const CACHE_TTL_MINUTES = 720;

    /**
     * Berapa PESAN terakhir yang dipulihkan dari arsip saat cache sudah mati
     * (bukan giliran — satu giliran = 2 pesan, jadi 20 ≈ 10 tanya-jawab).
     *
     * Dibatasi karena arsipnya tidak punya batas: percakapan riset berumur
     * dua bulan bisa berisi ratusan giliran, dan memuat semuanya berarti
     * membayar token untuk konteks yang hampir pasti sudah tidak relevan.
     */
    public const ARCHIVE_CONTEXT_MESSAGES = 20;

    /**
     * Batas berapa kali jawaban yang kena plafon 'max_tokens' boleh disambung.
     *
     * Berbeda dari pause_turn Claude (yang kini ditangani sepenuhnya di dalam
     * ResearchDriver): di sini modelnya TIDAK menjeda diri, tapi dipotong
     * paksa di tengah kalimat karena max_tokens habis. Tanpa penanganan,
     * jawaban berhenti begitu saja ("**3. lo_alv->display( ) dipan") dan user
     * tidak diberi tahu apa pun.
     */
    private const MAX_LENGTH_RESUMES = 3;

    /**
     * Penanda pesan "lanjutkan" yang kita suntik sendiri saat jawaban terpotong.
     * Dipakai toHistory() untuk membuangnya dari riwayat: instruksi itu hanya
     * berlaku di dalam satu giliran, dan menyimpannya membuat riwayat berisi
     * perintah palsu yang seolah-olah datang dari user.
     */
    private const CONTINUE_MARKER = '[[continue]] ';

    public function __construct(private AiDriverFactory $drivers)
    {
    }

    /**
     * @param array<int, array{type: string, media_type: string, data: string}> $attachments
     * @param Closure(string): void $onDelta dipanggil tiap potongan teks jawaban
     * @param Closure(string, array<string, mixed>): void $onEvent event non-teks ('status', 'sources', 'notice')
     * @param Closure(): bool $isAborted dipolling di antara langkah jaringan
     * @param bool $resume giliran ini adalah "Continue" yang DIMINTA USER lewat tombol,
     *                     bukan pertanyaan baru: tidak ada teks user, yang dikirim
     *                     adalah instruksi lanjutan yang sama dengan penyambung otomatis.
     */
    public function streamReply(
        Employee $employee,
        string $conversationId,
        string $userText,
        array $attachments,
        string $modelTier,
        Closure $onDelta,
        Closure $onEvent,
        Closure $isAborted,
        bool $resume = false,
    ): void {
        $cacheKey = $this->cacheKey($employee, $conversationId);
        $messages = $this->restoreMessages($cacheKey);

        // Cache kosong bukan berarti percakapan baru: TTL-nya 12 jam,
        // sementara arsipnya bertahan berbulan-bulan. Tanpa langkah ini,
        // membuka percakapan lama dari sidebar akan menampilkan transkrip
        // yang lengkap di layar tapi model tidak ingat apa pun — user melihat
        // riwayatnya, lalu bertanya lanjutan, dan dijawab seolah dari nol.
        if (empty($messages)) {
            $messages = $this->restoreFromArchive($employee, $conversationId);
        }

        // Batas giliran yang baru dimulai di sini — dipakai saat mengarsipkan,
        // supaya yang ditulis ke DB hanya jawaban giliran INI, bukan seluruh
        // riwayat yang kebetulan ikut terbawa di $messages.
        $turnStart = count($messages);

        // "Continue" bukan pertanyaan baru: yang disisipkan adalah instruksi
        // lanjutan yang persis sama dengan penyambung otomatis, sehingga
        // toHistory() ikut membuangnya dari riwayat — user tidak pernah melihat
        // perintah palsu atas namanya, baik di layar maupun di arsip.
        if ($resume) {
            // Tidak ada apa pun untuk dilanjutkan — konteksnya sudah hilang
            // (tab dibiarkan terbuka melewati TTL cache, arsipnya kosong).
            // Katakan terus terang; membiarkannya lewat berarti model diminta
            // "lanjutkan" tanpa tahu apa yang harus dilanjutkan.
            if (empty($messages)) {
                $onEvent('notice', [
                    'kind' => 'nothing_to_continue',
                    'title' => 'Nothing left to continue',
                    'text' => 'The earlier answer is no longer in this conversation\'s working context, so it '
                        . 'cannot be resumed. Ask the question again instead.',
                    'can_continue' => false,
                ]);

                return;
            }

            $messages[] = ['role' => 'user', 'content' => [$this->continuationBlock()]];
        } else {
            $messages[] = [
                'role' => 'user',
                'content' => $this->buildUserContent($userText, $attachments),
            ];
        }

        [$model, $config] = $this->modelConfigFor($modelTier);

        // Tier yang dipakai ditentukan admin, bukan request — pakai yang
        // sebenarnya berlaku supaya cache dan arsip tidak mencatat tier palsu.
        $modelTier = $config['tier'];

        // Loop 'pause_turn' Claude sekarang sepenuhnya di dalam driver (lihat
        // ResearchDriver::ask()) — resend-nya butuh blok server-tool mentah yang
        // tidak pernah keluar dari driver. Yang tersisa di sini cuma plafon
        // max_tokens, yang MEMANG harus tampak ke user (tombol Continue).
        $driver = $this->drivers->research($config['provider']);
        $lengthResumes = 0;
        $turnSources = [];

        while (true) {
            if ($isAborted()) {
                return;
            }

            [$assistantContent, $stopReason, $sources] = $driver->ask(
                model: $model,
                systemPrompt: $this->systemPrompt(),
                messages: $messages,
                maxTokens: $config['max_tokens'],
                effort: $config['effort'],
                onDelta: $onDelta,
                onEvent: $onEvent,
                isAborted: $isAborted,
            );

            if (null === $assistantContent) {
                // Aborted mid-stream.
                return;
            }

            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];
            $turnSources = $this->mergeSources($turnSources, $sources, $onEvent);

            // Jawaban kena plafon max_tokens: terpotong PAKSA di tengah kalimat.
            // Sambung dengan giliran user baru — bukan prefill assistant, karena
            // prefill ditolak 400 di model 4.6+ ke atas (dan thinking aktif).
            // Assistant partialnya tetap dikirim balik utuh sebagai konteks,
            // jadi model tahu persis di mana ia berhenti.
            if ('max_tokens' === $stopReason) {
                if (++$lengthResumes > self::MAX_LENGTH_RESUMES) {
                    // Sudah disambung sebanyak yang diizinkan dan MASIH terpotong.
                    // Dikirim sebagai 'notice', bukan ditempelkan ke teks jawaban:
                    // ini keadaan sistem, bukan isi jawaban — UI yang menampilkannya
                    // sebagai panel lengkap dengan tombol Continue / New chat.
                    $onEvent('notice', [
                        'kind' => 'truncated',
                        'title' => 'This answer was cut off',
                        'text' => 'The reply hit the output limit for this turn and has already been continued '
                            . 'automatically ' . self::MAX_LENGTH_RESUMES . ' times. Continue to resume exactly '
                            . 'where it stopped, or ask about the missing part more specifically.',
                        'can_continue' => true,
                    ]);
                    break;
                }

                $messages[] = ['role' => 'user', 'content' => [$this->continuationBlock()]];

                $onEvent('status', ['label' => 'Continuing the cut-off answer…']);
                continue;
            }

            break;
        }

        Cache::store(self::CACHE_STORE)->put($cacheKey, [
            'messages' => $this->toHistory($messages),
            'model_tier' => $modelTier,
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        // Arsip ditulis PALING AKHIR dan sengaja tidak boleh menggagalkan
        // apa pun: jawabannya sudah terlanjur sampai ke layar user dan
        // konteksnya sudah aman di cache. Kegagalan di sini hanya berarti
        // satu giliran tidak masuk daftar riwayat — dicatat, bukan dilempar.
        $this->archiveTurn(
            employee: $employee,
            conversationId: $conversationId,
            modelTier: $modelTier,
            userText: $userText,
            attachmentCount: count($attachments),
            turn: array_slice($messages, $turnStart),
            sources: array_values($turnSources),
        );
    }

    /**
     * Ringkas giliran assistant menjadi TEKS saja untuk disimpan sebagai riwayat.
     *
     * Blok kerja server tool (server_tool_use, web_search_tool_result, dan
     * code_execution_tool_result yang muncul dari dynamic filtering) sengaja
     * TIDAK ikut disimpan. Alasannya bukan hemat tempat, tapi kebenaran: blok
     * itu tidak selalu selamat saat dibentuk ulang dan dikirim balik — pernah
     * ditolak 400 dengan
     *   "code_execution_tool_result.content…type: Input should be
     *    'code_execution_tool_result_error'".
     * Bentuk union-nya tidak round-trip dengan andal, dan giliran berikutnya
     * tidak membutuhkannya: jawaban tekstual dari giliran lalu sudah menjadi
     * konteks yang cukup, dan model bisa mencari ulang bila perlu.
     *
     * Blok thinking juga dibuang — lintas giliran ia tidak wajib dikirim balik.
     *
     * Giliran assistant yang menjadi kosong (murni tool use) ikut dibuang,
     * karena content kosong ditolak API.
     *
     * Sekaligus inilah yang menegakkan invarian cache: keluarannya SELALU array
     * biasa, tidak pernah objek SDK (objek meledak saat di-unserialize —
     * lihat restoreMessages()).
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function toHistory(array $messages): array
    {
        $history = [];

        foreach ($messages as $message) {
            if ('assistant' !== ($message['role'] ?? null)) {
                if ($this->isContinuationPrompt($message)) {
                    continue;   // instruksi internal, bukan ucapan user
                }

                $history[] = $message;   // giliran user: sudah array sejak dibuat
                continue;
            }

            $text = [];

            // Dua bentuk bertemu di sini, dan keduanya HARUS ditangani:
            //   - giliran turun dari API kali ini → objek SDK
            //   - giliran lama yang dipulihkan dari cache → array biasa
            // Membaca objek saja diam-diam membuang seluruh jawaban lama,
            // sehingga model kehilangan konteks jawabannya sendiri.
            foreach ($message['content'] as $block) {
                $type = is_array($block) ? ($block['type'] ?? null) : ($block->type ?? null);
                if ('text' !== $type) {
                    continue;
                }

                $value = (string) (is_array($block) ? ($block['text'] ?? '') : ($block->text ?? ''));
                if ('' !== trim($value)) {
                    $text[] = ['type' => 'text', 'text' => $value];
                }
            }

            if (!empty($text)) {
                $history[] = ['role' => 'assistant', 'content' => $text];
            }
        }

        return $history;
    }

    /**
     * Instruksi "sambung dari titik berhenti", satu bentuk untuk DUA pemakai:
     * penyambung otomatis saat stopReason 'max_tokens', dan tombol Continue
     * yang ditekan user. Keduanya harus identik — kalau tidak, jawaban yang
     * disambung mesin dan yang disambung manusia jadi terasa beda.
     *
     * Ditulis dalam bahasa Inggris seperti system prompt, dengan perintah
     * eksplisit untuk mempertahankan bahasa bagian sebelumnya: instruksi
     * berbahasa Indonesia di sini pernah membuat jawaban ikut berpindah bahasa
     * di tengah jalan.
     *
     * @return array{type: string, text: string}
     */
    private function continuationBlock(): array
    {
        return [
            'type' => 'text',
            'text' => self::CONTINUE_MARKER
                // Sengaja tidak menyebut sebabnya secara spesifik: blok yang sama
                // dipakai untuk potongan max_tokens DAN untuk pencarian yang
                // kehabisan jeda, dan menyebut sebab yang salah hanya membuat
                // model menjelaskan hal yang tidak terjadi.
                . 'Your previous reply stopped before it was finished. Resume from EXACTLY the last character '
                . 'you wrote — do not repeat anything you already said, do not greet again, do not open with a '
                . 'recap or summary. If the last sentence, list item, table row, or code block was left unfinished, '
                . 'finish that first. Keep the same language, formatting, and level of detail as the earlier part.',
        ];
    }

    /**
     * @param array<string, mixed> $message
     */
    private function isContinuationPrompt(array $message): bool
    {
        $content = $message['content'] ?? null;

        if (!is_array($content) || 1 !== count($content)) {
            return false;
        }

        $block = $content[0] ?? null;

        return is_array($block)
            && 'text' === ($block['type'] ?? null)
            && str_starts_with((string) ($block['text'] ?? ''), self::CONTINUE_MARKER);
    }

    /**
     * Berapa banyak byte base64 lampiran yang masih menempel di konteks
     * percakapan ini — yaitu yang akan DIKIRIM ULANG ke API pada pertanyaan
     * berikutnya.
     *
     * Diukur dalam byte BASE64, bukan ukuran berkas asli, karena itulah yang
     * benar-benar dihitung terhadap plafon 32 MB per request milik API
     * (base64 menggelembungkan ~33%).
     *
     * Angkanya turun sendiri ke nol begitu cache 12 jam kedaluwarsa:
     * restoreFromArchive() menyemai ulang konteks dari transkrip TEKS, tanpa
     * byte gambar. Jadi anggaran ini membatasi satu sesi kerja, bukan
     * percakapan seumur hidup.
     */
    /**
     * Seberapa jauh model masih "ingat" percakapan ini.
     *
     * Ada dua keadaan, dan bedanya penting bagi user:
     *
     *   - warm  : konteks kerja masih di cache. Model melihat SELURUH
     *             percakapan ini, lampiran gambar termasuk.
     *   - dingin: cache sudah kedaluwarsa. Transkrip di layar tetap lengkap
     *             (dibaca dari arsip), tapi yang disemai ulang ke model hanya
     *             `window` pesan TERAKHIR, dan tanpa gambar.
     *
     * Keadaan kedua itulah yang diam-diam membingungkan: layar penuh riwayat,
     * model hanya ingat ekornya. UI memakai nilai ini untuk mengatakannya
     * terus terang alih-alih membiarkan user menyimpulkan sendiri dari
     * jawaban yang terasa pelupa.
     *
     * @return array{warm: bool, window: int, ttl_minutes: int}
     */
    public function contextState(Employee $employee, string $conversationId): array
    {
        return [
            'warm' => !empty($this->restoreMessages($this->cacheKey($employee, $conversationId))),
            'window' => self::ARCHIVE_CONTEXT_MESSAGES,
            'ttl_minutes' => self::CACHE_TTL_MINUTES,
        ];
    }

    public function attachmentBytesInContext(Employee $employee, string $conversationId): int
    {
        $bytes = 0;

        foreach ($this->restoreMessages($this->cacheKey($employee, $conversationId)) as $message) {
            if ('user' !== ($message['role'] ?? null)) {
                continue;
            }

            foreach ($message['content'] as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $data = $block['source']['data'] ?? null;

                if (is_string($data)) {
                    $bytes += strlen($data);
                    continue;
                }

                // Lampiran TEKS tinggal di blok text biasa. Yang dihitung
                // hanya blok berlabel <attached_text …> — teks yang diketik
                // user sendiri bukan lampiran dan tidak boleh memakan
                // anggaran yang sama.
                $text = $block['text'] ?? null;

                if (is_string($text) && str_starts_with($text, '<attached_text ')) {
                    $bytes += strlen($text);
                }
            }
        }

        return $bytes;
    }

    /**
     * Baca riwayat dari cache, tolak apa pun yang bukan array biasa.
     *
     * Bersifat menyembuhkan diri: entri rusak dibuang diam-diam dan percakapan
     * dimulai ulang, bukan membuat halaman gagal terus sampai TTL habis.
     *
     * Try/catch-nya WAJIB melingkupi get(), bukan cuma hasilnya: entri lama yang
     * berisi objek SDK meledak DI DALAM unserialize (SdkModel::__unserialize
     * membaca static $converter), jadi memeriksa nilai kembaliannya saja sudah
     * terlambat.
     *
     * @return array<int, array<string, mixed>>
     */
    private function restoreMessages(string $cacheKey): array
    {
        $store = Cache::store(self::CACHE_STORE);

        try {
            $state = $store->get($cacheKey);
        } catch (Throwable $e) {
            $store->forget($cacheKey);

            return [];
        }

        $messages = is_array($state) ? ($state['messages'] ?? null) : null;

        if (!is_array($messages)) {
            return [];
        }

        // Isi tiap giliran harus daftar blok berbentuk array — bukan objek.
        foreach ($messages as $message) {
            if (!is_array($message) || !is_array($message['content'] ?? null)) {
                return [];
            }

            foreach ($message['content'] as $block) {
                if (!is_array($block)) {
                    return [];
                }
            }
        }

        return $messages;
    }

    /**
     * Teruskan teks jawaban + tanda "sedang mencari" ke UI selagi stream berjalan.
     *
     * Blok thinking sengaja diabaikan: display-nya 'omitted' (bawaan), jadi
     * teksnya kosong dan tidak ada yang bisa ditampilkan.
     */
    /**
     * Buang konteks kerja satu percakapan dari cache.
     *
     * Dipanggil saat user menghapus percakapan: tanpa ini arsipnya hilang dari
     * daftar tapi isinya — termasuk byte lampiran — masih menunggu di disk
     * sampai TTL habis. "Hapus" harus berarti hapus.
     */
    public function forgetContext(Employee $employee, string $conversationId): void
    {
        Cache::store(self::CACHE_STORE)->forget($this->cacheKey($employee, $conversationId));
    }
    /**
     * Pulihkan konteks dari ARSIP (DB) ketika cache sudah kedaluwarsa.
     *
     * Yang kembali hanya teks — sama bentuknya dengan keluaran toHistory(),
     * jadi invarian "isi konteks selalu array polos" tetap terjaga. Lampiran
     * TIDAK ikut (byte-nya memang tidak pernah diarsipkan), maka pesan user
     * yang dulu berlampiran diberi catatan eksplisit; tanpa itu model melihat
     * "ini TCODE apa?" tanpa gambar apa pun dan menebak-nebak.
     *
     * @return array<int, array<string, mixed>>
     */
    private function restoreFromArchive(Employee $employee, string $conversationId): array
    {
        try {
            $conversation = $this->findConversation($employee, $conversationId);

            if (!$conversation) {
                return [];
            }

            // reorder(), bukan orderByDesc(): relasi messages() sudah membawa
            // orderBy('id') sendiri, dan menambah urutan kedua tidak menggantikan
            // yang pertama — hasilnya tetap menaik, lalu ->reverse() di bawah
            // membalik seluruh percakapan menjadi terbalik urutannya.
            $rows = $conversation->messages()
                ->reorder('id', 'desc')
                ->limit(self::ARCHIVE_CONTEXT_MESSAGES)
                ->get()
                ->reverse()
                ->values();

            $messages = [];

            foreach ($rows as $row) {
                $text = trim((string) $row->content);

                if ('' === $text && $row->attachment_count < 1) {
                    continue;
                }

                // Catatan sistem, ditulis Inggris seperti system prompt: ini
                // menempel di dalam giliran user, dan kalimat Indonesia di sini
                // ikut menarik bahasa jawaban (aturan bahasa di system prompt
                // menyuruh model mengikuti bahasa tulisan user).
                if ('user' === $row->role && $row->attachment_count > 0) {
                    $text = trim($text . "\n\n[This message originally carried " . $row->attachment_count
                        . ' attachment(s) that are no longer available. If the answer depends on what they showed, '
                        . 'ask the user to upload them again instead of guessing.]');
                }

                // Giliran pertama harus dari user — potongan yang diawali
                // assistant ditolak API.
                if (empty($messages) && 'user' !== $row->role) {
                    continue;
                }

                $messages[] = [
                    'role' => $row->role,
                    'content' => [['type' => 'text', 'text' => $text]],
                ];
            }

            return $messages;
        } catch (Throwable $e) {
            Log::warning('AI research archive restore failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Simpan satu giliran (pertanyaan + jawaban) ke arsip.
     *
     * @param array<int, array<string, mixed>> $turn potongan $messages milik giliran ini saja
     * @param array<int, array{url: string, title: string}> $sources
     */
    private function archiveTurn(
        Employee $employee,
        string $conversationId,
        string $modelTier,
        string $userText,
        int $attachmentCount,
        array $turn,
        array $sources,
    ): void {
        try {
            // toHistory() sekaligus membuang blok server tool, blok thinking,
            // dan prompt "[[continue]]" yang kita suntik sendiri — jadi yang
            // tersisa persis teks yang dilihat user. Disambung tanpa pemisah
            // karena potongan max_tokens memang menyambung di tengah kalimat.
            $reply = '';

            foreach ($this->toHistory($turn) as $message) {
                if ('assistant' !== ($message['role'] ?? null)) {
                    continue;
                }

                foreach ($message['content'] as $block) {
                    $reply .= (string) ($block['text'] ?? '');
                }
            }

            $reply = trim($reply);
            $userText = trim($userText);

            // Giliran yang dibatalkan sebelum ada jawaban tidak diarsipkan:
            // menyimpan pertanyaan tanpa jawaban hanya membuat daftar riwayat
            // penuh percakapan kosong.
            if ('' === $reply) {
                return;
            }

            DB::transaction(function () use (
                $employee, $conversationId, $modelTier, $userText, $attachmentCount, $reply, $sources
            ): void {
                $conversation = $this->findConversation($employee, $conversationId);

                if (!$conversation) {
                    $conversation = new AiConversation([
                        'employee_id' => $employee->employee_id,
                        'assistant' => AiConversation::ASSISTANT_RESEARCH,
                        'conversation_id' => $conversationId,
                        'title' => AiConversation::titleFrom($userText, $attachmentCount),
                    ]);
                }

                $conversation->model_tier = $modelTier;
                $conversation->last_message_at = now();
                $conversation->save();

                $rows = [];

                // Giliran "Continue" tidak punya pertanyaan user — menulis baris
                // user kosong hanya menghasilkan bubble hampa saat percakapan
                // dibuka lagi. Yang disimpan cukup sambungan jawabannya.
                if ('' !== $userText || $attachmentCount > 0) {
                    $rows[] = [
                        'role' => 'user',
                        'content' => $userText,
                        'attachment_count' => $attachmentCount,
                    ];
                }

                $rows[] = [
                    'role' => 'assistant',
                    'content' => $reply,
                    'sources' => empty($sources) ? null : $sources,
                ];

                $conversation->messages()->createMany($rows);
            });
        } catch (Throwable $e) {
            Log::warning('AI research archive write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Cari percakapan milik employee INI.
     *
     * conversationId datang dari browser, jadi ia tidak pernah dipakai sendirian:
     * pemiliknya selalu ikut jadi syarat. Menebak UUID orang lain karena itu
     * tidak menghasilkan apa-apa.
     */
    private function findConversation(Employee $employee, string $conversationId): ?AiConversation
    {
        return AiConversation::where('employee_id', $employee->employee_id)
            ->where('assistant', AiConversation::ASSISTANT_RESEARCH)
            ->where('conversation_id', $conversationId)
            ->first();
    }
    /**
     * Gabungkan sumber BARU dari giliran ini (sudah diberikan driver, per
     * ResearchDriver::ask()) ke akumulator lintas-giliran milik streamReply(),
     * lalu pancarkan daftar lengkapnya ke UI. Dedup lintas giliran adalah
     * tanggung jawab DI SINI, bukan driver — driver hanya tahu satu giliran.
     *
     * @param array<string, array{url: string, title: string}> $sources akumulator, dikunci per URL
     * @param array<int, array{url: string, title: string}> $newSources sumber giliran ini
     * @return array<string, array{url: string, title: string}>
     */
    private function mergeSources(array $sources, array $newSources, Closure $onEvent): array
    {
        foreach ($newSources as $item) {
            $url = $item['url'] ?? null;
            if ($url) {
                $sources[$url] = ['url' => $url, 'title' => $item['title'] ?? $url];
            }
        }

        if (!empty($sources)) {
            $onEvent('sources', ['items' => array_values($sources)]);
        }

        return $sources;
    }

    /**
     * Lampiran teks (tempelan besar / berkas kode) menjadi content block
     * `text` — bukan `document` base64. Lihat App\Support\AiTextAttachment.
     *
     * @param array<int, array<string, mixed>> $attachments
     * @return array<int, array<string, mixed>>
     */
    private function buildUserContent(string $text, array $attachments): array
    {
        $content = [];
        $textIndex = 0;

        foreach ($attachments as $attachment) {
            if ('text' === $attachment['type']) {
                $content[] = AiTextAttachment::toContentBlock($attachment, ++$textIndex);

                continue;
            }

            $content[] = [
                'type' => $attachment['type'],
                'source' => [
                    'type' => 'base64',
                    'media_type' => $attachment['media_type'],
                    'data' => $attachment['data'],
                ],
            ];
        }

        if ('' !== $text) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        return $content;
    }

    /**
     * Model, plafon token, dan effort yang berlaku — DITENTUKAN SUPER ADMIN
     * lewat Control Center → AI Settings, bukan lagi hardcode di sini.
     *
     * Argumen $tier dari request SENGAJA DIABAIKAN: halaman chat tidak lagi
     * punya pemilih model, jadi tier yang dipakai adalah yang ditandai aktif
     * oleh admin. Nilainya ikut dikembalikan supaya yang tercatat di cache dan
     * arsip adalah tier yang BENAR-BENAR dipakai, bukan 'default' bawaan
     * request.
     *
     * max_tokens tetap PLAFON KERAS: begitu tersentuh, jawaban dipotong di
     * tengah kalimat (stopReason 'max_tokens') — lihat loop penyambung di
     * streamReply(). Karena thinking adaptif ikut memakan jatah yang sama,
     * plafon yang terlalu rendah membuat jawaban teknis panjang "putus".
     * Semua request di sini streaming, jadi angka besar tidak berisiko timeout.
     *
     * effort dilewatkan APA ADANYA — driver yang dipilih lewat 'provider' yang
     * tahu cara menerjemahkannya ke parameter API-nya sendiri.
     *
     * @return array{0: string, 1: array{tier: string, provider: string, max_tokens: int, effort: ?string}}
     */
    private function modelConfigFor(string $tier): array
    {
        $active = AiModelSettings::resolve(AiModelSettings::RESEARCH);

        return [$active['model'], [
            'tier' => $active['tier'],
            'provider' => $active['provider'],
            'max_tokens' => $active['max_tokens'],
            'effort' => $active['effort'],
        ]];
    }

    private function cacheKey(Employee $employee, string $conversationId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9\-]/', '', $conversationId);
        $safeId = $safeId ?: 'default';

        return "ai_research:emp{$employee->employee_id}:{$safeId}";
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the EcoSystem Research Assistant, a second assistant inside EcoSystem — the internal operations app used by
consultants at Eclectic Consulting. Your job is EXTERNAL lookup: things the answer to which lives outside EcoSystem's
own database.

Scope: ANY topic the user asks about. You are a general-purpose research assistant — you are NOT limited to SAP, ERP,
or work-related subjects, and you never decline a question for being off-topic or "outside my specialty". News,
places, prices, travel, health, history, sport, shopping, schools and universities, entertainment, cooking, everyday
trivia: all of it is in scope, and the right response is to look it up and answer it, not to redirect the user.

SAP and enterprise-IT questions merely happen to be frequent here, because the users are consultants at Eclectic
Consulting — transaction codes (TCODEs), tables/fields/BAPIs, error messages and dump codes, OSS/SAP Notes, product
documentation, vendor pricing pages and standards. Treat those as common examples, not as a boundary. Screenshots are
a normal input: read the screen and say what it shows; for an application screen, name the system and the TCODE or
menu path when it can be determined.

The one thing outside your reach is EcoSystem's own database — no tickets, SLA figures, delivery projects, customers,
or employees. If the user asks about those, say plainly that this assistant only looks outward and point them to the
EcoSystem Assistant page for internal data. Never invent internal records. That is a data-access limit, not a subject
limit: it is never a reason to refuse a question about the outside world.

Tools: use web_search to look things up and web_fetch to open a specific page when the search snippet isn't enough.
Search whenever the answer depends on something current, version-specific, or that you are not certain of — do not
answer a factual lookup from memory alone when a search would confirm it.

Sourcing rules, these matter more than fluency:
- Every factual claim that came from a search must be attributed to the page it came from, inline, with the URL.
- Distinguish what you READ from what you INFER. Mark inference as inference.
- When a screenshot is ambiguous (several TCODEs open a similar screen, or the screen is customized), say so and list
  the candidates with how to tell them apart, rather than picking one and sounding certain.
- If searching does not settle the question, say that. An honest "I could not confirm this" is the correct answer.

Language — this one is strict, because your sources will almost always be in English while the user often writes in
Indonesian:
- Write the ENTIRE reply in the language the user wrote in. One reply, one language.
- What you take from a source must be RESTATED in that language. Never paste an English sentence, clause, or fragment
  into an Indonesian reply. "Structure does not store data, dan structure can hold only one record" is exactly the
  mistake to avoid — translate the whole thing instead.
- This applies with FULL force to documentation you just read. Read it in English, understand it, then write the point
  in the user's language in your own words. Copying a sentence out of SAP Help or a vendor page — even one sentence,
  even to be precise — is the single most common way this reply goes wrong. "The return value has the type i. In an
  inline declaration, a variable of the type i is declared." dropped into an Indonesian answer is precisely the defect:
  it should have been written as Indonesian prose about the return type.
- Keep in their original form only the things that are names: TCODEs (SE38), table/field/function names (VBAK,
  BAPI_SALESORDER_CREATEFROMDAT2), error and dump codes, menu paths and on-screen labels, and product names. These are
  identifiers, not prose — translating them would make them wrong. Add a short gloss in the user's language when the
  label alone is not self-explanatory.
- If a passage genuinely needs to be reproduced word for word, mark it clearly as a quotation, keep it short, and
  follow it with the meaning in the user's language.

Style: be concise and direct — lead with the answer, then the supporting detail. Use short headings or bullets only
when the answer genuinely has parts.

Length: there is a hard output ceiling, and a reply that runs into it is cut off mid-sentence. Budget for it. When a
question has many parts, answer the most important ones fully rather than starting a long enumeration you cannot
finish, and say which parts you left out so the user can ask again. If you are asked to continue a reply that was cut
off, resume exactly where it stopped — no greeting, no recap, no repetition of what you already wrote.
PROMPT;
    }
}
