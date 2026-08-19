<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\WebFetchTool20260209;
use Anthropic\Messages\WebSearchTool20260209;
use App\Models\AiConversation;
use App\Models\Employee;
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
 *   - AiResearchService : tool SERVER-SIDE Anthropic (web_search, web_fetch) →
 *     pencarian internet. Tidak ada tool loop di sisi kita: pencarian dijalankan
 *     di infrastruktur Anthropic dan hasilnya sudah ikut dalam response yang sama.
 *
 * Karena itu satu-satunya alasan kita mengulang request adalah stopReason
 * 'pause_turn' (model menjeda giliran panjang dan minta dilanjutkan).
 *
 * Sama seperti AiChatService, isi percakapan TIDAK PERNAH masuk database —
 * hanya cache 'ai_chat' dengan TTL 1 jam (lihat cacheKey()).
 */
class AiResearchService
{
    private const CACHE_STORE = 'ai_chat';
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Berapa PESAN terakhir yang dipulihkan dari arsip saat cache sudah mati
     * (bukan giliran — satu giliran = 2 pesan, jadi 20 ≈ 10 tanya-jawab).
     *
     * Dibatasi karena arsipnya tidak punya batas: percakapan riset berumur
     * dua bulan bisa berisi ratusan giliran, dan memuat semuanya berarti
     * membayar token untuk konteks yang hampir pasti sudah tidak relevan.
     */
    private const ARCHIVE_CONTEXT_MESSAGES = 20;

    /** Batas berapa kali 'pause_turn' boleh dilanjutkan dalam satu giliran. */
    private const MAX_PAUSE_RESUMES = 4;

    /**
     * Batas berapa kali jawaban yang kena plafon 'max_tokens' boleh disambung.
     *
     * Berbeda dari pause_turn: di sini modelnya TIDAK menjeda diri, tapi
     * dipotong paksa di tengah kalimat karena max_tokens habis. Tanpa
     * penanganan, jawaban berhenti begitu saja ("**3. lo_alv->display( ) dipan")
     * dan user tidak diberi tahu apa pun.
     */
    private const MAX_LENGTH_RESUMES = 3;

    /**
     * Penanda pesan "lanjutkan" yang kita suntik sendiri saat jawaban terpotong.
     * Dipakai toHistory() untuk membuangnya dari riwayat: instruksi itu hanya
     * berlaku di dalam satu giliran, dan menyimpannya membuat riwayat berisi
     * perintah palsu yang seolah-olah datang dari user.
     */
    private const CONTINUE_MARKER = '[[continue]] ';

    /** Batas pemakaian tiap server tool per request (biaya + waktu tunggu). */
    private const MAX_WEB_USES = 6;

    public function __construct(private Client $client)
    {
    }

    /**
     * @param array<int, array{type: string, media_type: string, data: string}> $attachments
     * @param Closure(string): void $onDelta dipanggil tiap potongan teks jawaban
     * @param Closure(string, array<string, mixed>): void $onEvent event non-teks ('status', 'sources')
     * @param Closure(): bool $isAborted dipolling di antara langkah jaringan
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
    ): void {
        $cacheKey = $this->cacheKey($employee, $conversationId);
        $messages = $this->restoreMessages($cacheKey);

        // Cache kosong bukan berarti percakapan baru: TTL-nya cuma 1 jam,
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

        $messages[] = [
            'role' => 'user',
            'content' => $this->buildUserContent($userText, $attachments),
        ];

        [$model, $config] = $this->modelConfigFor($modelTier);

        $pauseResumes = 0;
        $lengthResumes = 0;
        $turnSources = [];

        while (true) {
            if ($isAborted()) {
                return;
            }

            $stream = $this->client->messages->createStream(
                maxTokens: $config['max_tokens'],
                messages: $messages,
                model: $model,
                system: $this->systemPrompt(),
                thinking: ['type' => 'adaptive'],
                outputConfig: ['effort' => $config['effort']],
                tools: $this->toolDefinitions(),
            );

            // MessageAccumulator melipat event stream kembali menjadi Message utuh.
            // Ini penting di sini: blok hasil server tool (web_search_tool_result)
            // harus dikirim balik apa adanya pada giliran berikutnya, dan menyusunnya
            // manual dari event mentah rapuh — biarkan SDK yang mengerjakan.
            $accumulator = MessageAccumulator::forMessages();
            $aborted = false;

            foreach ($stream as $event) {
                if ($isAborted()) {
                    $stream->close();
                    $aborted = true;
                    break;
                }

                $accumulator->accumulate($event);
                $this->emitProgress($event, $onDelta, $onEvent);
            }

            if ($aborted) {
                return;
            }

            $message = $accumulator->message();

            // Objek SDK apa adanya — HANYA hidup di memori selama request ini.
            // Untuk melanjutkan 'pause_turn', giliran assistant harus dikirim
            // balik utuh, dan objek aslinya adalah bentuk paling setia: tidak
            // ada round-trip JSON yang bisa merusaknya. Yang masuk cache adalah
            // hasil toHistory(), bukan ini — lihat catatan di sana.
            $messages[] = ['role' => 'assistant', 'content' => $message->content];
            $turnSources = $this->emitSources($message, $onEvent, $turnSources);

            $stopReason = $message->stopReason;

            // Jawaban kena plafon max_tokens: terpotong PAKSA di tengah kalimat.
            // Sambung dengan giliran user baru — bukan prefill assistant, karena
            // prefill ditolak 400 di model 4.6+ ke atas (dan thinking aktif).
            // Assistant partialnya tetap dikirim balik utuh sebagai konteks,
            // jadi model tahu persis di mana ia berhenti.
            if ('max_tokens' === $stopReason) {
                if (++$lengthResumes > self::MAX_LENGTH_RESUMES) {
                    $onDelta("\n\n_…jawaban dipotong karena sudah terlalu panjang. Tanyakan bagian yang belum terjawab secara lebih spesifik._");
                    break;
                }

                $messages[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'text',
                        'text' => self::CONTINUE_MARKER
                            . 'Jawabanmu terpotong karena batas panjang. Lanjutkan PERSIS dari '
                            . 'karakter terakhir yang tadi kamu tulis — jangan mengulang bagian yang sudah ada, '
                            . 'jangan menyapa ulang, jangan membuat ringkasan pembuka. Kalau kalimat atau blok kode '
                            . 'terakhir terputus di tengah, teruskan kalimat/blok itu sampai selesai. '
                            . 'Bahasa dan gaya penulisannya harus sama dengan bagian sebelumnya.',
                    ]],
                ];

                $onEvent('status', ['label' => 'Melanjutkan jawaban yang terpotong…']);
                continue;
            }

            if ('pause_turn' !== $stopReason) {
                break;
            }

            if (++$pauseResumes > self::MAX_PAUSE_RESUMES) {
                $onDelta("\n\n_Pencarian ini terlalu panjang untuk diselesaikan sekaligus — coba persempit pertanyaannya._");
                break;
            }

            $onEvent('status', ['label' => 'Melanjutkan pencarian…']);
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

                if ('user' === $row->role && $row->attachment_count > 0) {
                    $text = trim($text . "\n\n[Pesan ini dulu memuat " . $row->attachment_count
                        . ' lampiran yang sudah tidak tersedia lagi. Kalau jawabannya bergantung pada isi lampiran itu, '
                        . 'minta user mengunggahnya ulang alih-alih menebak.]');
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

                $conversation->messages()->createMany([
                    [
                        'role' => 'user',
                        'content' => $userText,
                        'attachment_count' => $attachmentCount,
                    ],
                    [
                        'role' => 'assistant',
                        'content' => $reply,
                        'sources' => empty($sources) ? null : $sources,
                    ],
                ]);
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
    private function emitProgress(object $event, Closure $onDelta, Closure $onEvent): void
    {
        switch ($event->type) {
            case 'content_block_start':
                $block = $event->contentBlock;

                switch ($block->type ?? '') {
                    case 'server_tool_use':
                        $onEvent('status', ['label' => 'web_fetch' === ($block->name ?? '')
                            ? 'Membuka halaman sumber…'
                            : 'Mencari di internet…']);
                        break;

                    case 'web_search_tool_result':
                    case 'web_fetch_tool_result':
                        $onEvent('status', ['label' => 'Membaca hasil pencarian…']);
                        break;
                }
                break;

            case 'content_block_delta':
                $delta = $event->delta;
                if ($delta instanceof TextDelta) {
                    $onDelta($delta->text);
                }
                break;
        }
    }

    /**
     * Kumpulkan URL sumber dari blok hasil server tool supaya UI bisa
     * menampilkannya sebagai daftar rujukan di bawah jawaban.
     *
     * Catatan bentuk data: pada web_search, `content` berisi ARRAY hasil saat
     * sukses, tapi berubah jadi OBJEK error (mis. max_uses_exceeded) saat gagal —
     * server tool tidak melempar exception, errornya ikut di body 200.
     */
    private function emitSources(object $message, Closure $onEvent, array $sources = []): array
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

        if (!empty($sources)) {
            $onEvent('sources', ['items' => array_values($sources)]);
        }

        return $sources;
    }

    /**
     * Server tool milik Anthropic — dieksekusi di sisi mereka, kita cukup
     * mendeklarasikannya. Varian _20260209 (dynamic filtering) butuh model
     * Sonnet 5 / Opus 5 ke atas; itulah kenapa tier "cepat" (Haiku) tidak
     * ditawarkan di halaman ini.
     *
     * Sengaja memakai kelas typed, bukan array biasa: ToolUnion di SDK adalah
     * union TANPA discriminator — array polos dicocokkan ke varian pertama yang
     * "muat", dan Tool (tool buatan sendiri) ada di urutan pertama.
     *
     * citations pada web_fetch sengaja TIDAK diaktifkan. Kutipan wajib berupa
     * potongan HARFIAH dari halaman sumber — dan sumbernya hampir selalu bahasa
     * Inggris, sehingga fiturnya justru mendorong kalimat Inggris mentah masuk
     * ke jawaban berbahasa Indonesia ("The return value has the type i…").
     * Daftar rujukan tetap ada: kita menyusunnya sendiri di emitSources() dari
     * URL blok hasil, tanpa bergantung pada citations.
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

    /**
     * @param array<int, array{type: string, media_type: string, data: string}> $attachments
     * @return array<int, array<string, mixed>>
     */
    private function buildUserContent(string $text, array $attachments): array
    {
        $content = [];

        foreach ($attachments as $attachment) {
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
     * max_tokens di sini adalah PLAFON KERAS: begitu tersentuh, jawaban dipotong
     * di tengah kalimat (stopReason 'max_tokens'). Karena thinking adaptif ikut
     * memakan jatah yang sama, plafon lama (8000/16000) gampang habis pada
     * jawaban teknis panjang — itulah penyebab jawaban "putus" di layar.
     * Semua request di sini streaming, jadi angka besar tidak berisiko timeout.
     *
     * @return array{0: string, 1: array{max_tokens: int, effort: string}}
     */
    private function modelConfigFor(string $tier): array
    {
        return match ($tier) {
            'deep' => ['claude-opus-5', [
                'max_tokens' => 64000,
                'effort' => 'high',
            ]],
            default => ['claude-sonnet-5', [
                'max_tokens' => 32000,
                'effort' => 'medium',
            ]],
        };
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

Scope: identify and explain things the user shows or names — SAP transaction codes (TCODEs), SAP tables/fields/BAPIs,
error messages and dump codes, OSS/SAP Notes topics, product documentation, vendor pricing pages, standards, and
general technical questions consultants hit during a project. Screenshots are a normal input: read the screen, name
what system and screen it is, and identify the TCODE or menu path when it can be determined.

You do NOT have access to EcoSystem data — no tickets, SLA figures, delivery projects, customers, or employees. If the
user asks about those, say plainly that this assistant only looks outward and point them to the EcoSystem Assistant
page for internal data. Never invent internal records.

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
