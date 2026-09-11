<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\AiConversation;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Ticket;
use App\Services\Ai\AiResearchService;
use App\Services\Ai\TicketSummaryContext;
use App\Support\AiDocxExport;
use App\Support\AiTextAttachment;
use App\Support\TicketTeamAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI Research — chat backend untuk asisten pencarian EKSTERNAL.
 *
 * Bentuknya sengaja kembar dengan AiAssistantController (SSE, tanpa persistensi,
 * state di cache 'ai_chat'); yang berbeda hanya service di baliknya — di sini
 * AiResearchService, yang memakai server tool web_search/web_fetch dan sama
 * sekali tidak menyentuh data EcoSystem.
 *
 * Selain 'delta'/'done'/'error', stream ini mengirim tiga event tambahan:
 *   - status  : label progres ("Searching the web…") selagi server tool jalan
 *   - sources : daftar {url, title} sumber yang dipakai pada giliran tersebut
 *   - notice  : giliran berakhir karena BATAS, bukan karena jawabannya selesai
 *               (jawaban terpotong / pencarian kepanjangan). Payloadnya
 *               {kind, title, text, can_continue} — UI merendernya sebagai
 *               panel dengan tombol Continue, bukan sebagai teks jawaban.
 */
class AiResearchController extends Controller
{
    /** Gerbang khusus tombol "Ask AI" di halaman tiket — lihat openForTicket(). */
    public const TICKET_BUTTON_PERMISSION_SLUG = 'ui.ticket.btn-ai-research';

    private const SUPPORTED_IMAGE_MIMES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    /**
     * JUMLAH berkas per pesan TIDAK dibatasi — kebutuhan tiap user berbeda,
     * dan membatasinya lebih sering menghalangi pekerjaan daripada menolong.
     *
     * Yang tetap dibatasi hanya dua hal, dan keduanya karena ada plafon keras
     * di luar kendali kode ini:
     *
     *   1. MAX_ATTACHMENT_KB — ukuran satu berkas. Ditahan di bawah
     *      `upload_max_filesize=30M` milik PHP produksi (lihat Dockerfile).
     *      Melebihi plafon PHP bukan menghasilkan pesan yang jelas, melainkan
     *      berkas yang hilang diam-diam.
     *
     *   2. MAX_CONTEXT_ATTACHMENT_BYTES — TOTAL lampiran yang masih menempel
     *      di satu percakapan. Ini yang benar-benar penting: byte lampiran
     *      tersimpan di riwayat (cache 'ai_chat') dan dikirim ULANG ke API
     *      pada SETIAP pertanyaan lanjutan. Tanpa pagar ini, percakapan
     *      panjang yang penuh gambar akhirnya menembus plafon 32 MB per
     *      request milik API — dan begitu itu terjadi percakapannya MATI
     *      PERMANEN: setiap pesan berikutnya kena 413, dan user tidak punya
     *      cara memperbaikinya selain memulai chat baru.
     *
     * Anggaran diukur dalam byte BASE64 (bentuk yang benar-benar dikirim),
     * jadi 22 MB di sini setara ~16 MB berkas asli. Sisanya disediakan untuk
     * teks percakapan, system prompt, dan hasil pencarian web.
     *
     * Ketiganya PUBLIC karena halaman chat menampilkannya kepada user (panel
     * "Limits & behaviour"). Angka yang ditulis ulang di view adalah cara
     * paling gampang membuat UI berbohong — begitu batasnya berubah di sini,
     * yang tampil ikut berubah.
     */
    public const MAX_MESSAGE_CHARS = 4000;
    public const MAX_ATTACHMENT_KB = 20480;                        // 20 MB per berkas

    /**
     * Total unggahan dalam SATU pesan. Bukan batas API, melainkan pagar agar
     * request tidak menabrak `post_max_size` PHP (80M di produksi) — di atas
     * itu PHP membuang seluruh body, bukan cuma berkasnya (lihat
     * rejectOversizedPost()). Harus sama dengan yang dicek di sisi browser.
     */
    public const MAX_MESSAGE_UPLOAD_MB = 25;

    /**
     * 20 Agu 2026: 22 MB → 24 MB base64 (~16 MB → ~18 MB berkas asli).
     *
     * JANGAN DINAIKKAN LAGI TANPA MENGUBAH ARSITEKTUR. Ini bukan angka yang
     * dipilih karena selera — ia diturunkan dari plafon KERAS 32 MB per request
     * milik API, dan seluruh percakapan harus muat di dalam SATU request karena
     * lampiran dikirim ulang tiap pertanyaan lanjutan.
     *
     * Pembagian 32 MB itu:
     *   24 MB  lampiran (angka ini)
     *   ~2-3 MB hasil server tool web_search/web_fetch pada giliran berjalan —
     *           bisa menumpuk sampai 4× kalau kena 'pause_turn' berulang
     *   sisanya teks percakapan + system prompt
     *
     * Tersisa ~5 MB margin. Menaikkannya ke 30 MB akan memakan margin itu, dan
     * konsekuensi melewatinya BUKAN error yang bisa dipulihkan: request kena
     * 413, dan percakapan itu MATI PERMANEN — tiap pesan berikutnya gagal, user
     * tidak punya jalan keluar selain memulai chat baru.
     *
     * Untuk plafon yang jauh lebih besar (mis. 100 MB), satu-satunya jalan
     * adalah Files API (beta `files-api-2025-04-14`): berkas diunggah SEKALI
     * lalu dirujuk lewat `file_id`, jadi byte-nya tidak ikut di tiap request
     * dan batas 32 MB tidak lagi mengikat. Itu perubahan arsitektur, bukan
     * perubahan angka — dan berkasnya jadi tersimpan di server Anthropic.
     */
    public const MAX_CONTEXT_ATTACHMENT_BYTES = 24 * 1024 * 1024;  // ~18 MB berkas asli

    /**
     * Halaman chat.
     *
     * Angka batas dikirim dari sini, tidak ditulis ulang di view: sebelum ini
     * tooltip tombol lampiran masih berbunyi "max 2 files, 5 MB each" — sisa
     * versi lama — sementara yang berlaku 20 MB per berkas tanpa batas jumlah.
     * Satu-satunya cara UI berhenti berbohong adalah membacanya dari sumber
     * yang sama dengan yang menegakkannya.
     */
    public function index()
    {
        // Anggaran per PERCAKAPAN adalah yang paling ketat, dan ia mengikat
        // juga pada pesan PERTAMA: satu pesan berisi 25 MB ditolak seketika
        // oleh rejectIfContextFull() karena base64-nya (~33 MB) sudah melewati
        // plafon percakapan sendirian. Jadi 20 MB/berkas dan 25 MB/pesan yang
        // dulu ditampilkan adalah angka yang TIDAK PERNAH bisa dicapai — user
        // dibiarkan memilih berkas sampai 25 MB lalu ditolak server.
        // Yang ditampilkan (dan yang dicek browser) sekarang plafon efektif.
        $chatMb = (int) floor(self::MAX_CONTEXT_ATTACHMENT_BYTES * 0.75 / 1024 / 1024);

        return view('ai.research', [
            'limits' => [
                'chars' => self::MAX_MESSAGE_CHARS,
                'file_mb' => min(intdiv(self::MAX_ATTACHMENT_KB, 1024), $chatMb),
                'message_mb' => min(self::MAX_MESSAGE_UPLOAD_MB, $chatMb),
                'chat_attachment_mb' => $chatMb,
                // Sudah dalam bentuk yang bisa dibaca: "720 minutes" benar
                // secara angka tapi tidak ada manusia yang membacanya begitu.
                'context_age' => self::humanMinutes(AiResearchService::CACHE_TTL_MINUTES),
                'context_messages' => AiResearchService::ARCHIVE_CONTEXT_MESSAGES,
            ],
        ]);
    }

    /**
     * Tombol "Ask AI" di halaman detail tiket (lihat ticket/show.blade.php)
     * — bukan endpoint chat baru, cuma menyiapkan (atau menemukan lagi) SATU
     * AiConversation milik employee ini tentang tiket ini, lalu mengarahkan
     * ke halaman AI Research yang sudah ada.
     *
     * Dua gerbang BERLAPIS, sama persis dengan yang dicek di Blade (lihat
     * migration add_ai_research_ticket_button_menu.php untuk alasannya):
     *   1. Permission slug ui.ticket.btn-ai-research — role mana yang BOLEH
     *      memakai fitur ini sama sekali, admin-only default, diatur admin.
     *   2. isLeadOrMember() ATAU EC Administrator — KE TIKET MANA.
     *
     * Wajib DIULANG di sini (bukan cukup disembunyikan di Blade) — kalau
     * tidak, siapa pun yang tahu URL-nya bisa lewati tombolnya sama sekali.
     *
     * conversation_id DETERMINISTIK ("ticket-{id}") supaya klik berulang oleh
     * orang yang sama selalu kembali ke percakapan yang SAMA (constraint
     * unique-nya (employee_id, assistant, conversation_id), jadi string yang
     * sama aman dipakai lintas employee — masing-masing dapat baris sendiri,
     * TIDAK ada satu thread yang dibagi rame-rame, sesuai keputusan produk).
     *
     * Judul di-set MANUAL saat membuat baris (nomor + deskripsi tiket) karena
     * AiConversation::titleFrom() (dipakai AiResearchService::archiveTurn())
     * hanya mengambil dari pesan pertama USER — dan archiveTurn() TIDAK
     * PERNAH menimpa title kalau barisnya sudah ada, jadi title manual ini
     * aman dari giliran chat asli berikutnya.
     *
     * Konteks tiket di-seed sebagai SATU AiMessage (role user) langsung ke
     * arsip, bukan lewat AiResearchService — cache 'ai_chat' percakapan baru
     * ini kosong, dan restoreFromArchive() milik AiResearchService (jalur
     * yang sudah ada untuk cache dingin) akan membaca baliknya otomatis di
     * giliran chat pertama, tanpa kode tambahan di jalur streaming.
     */
    public function openForTicket(int $ticketId)
    {
        $employee = $this->currentEmployee();
        $ticket = Ticket::findOrFail($ticketId);

        if (!$employee->hasPermission(self::TICKET_BUTTON_PERMISSION_SLUG)) {
            abort(403);
        }

        $isAdmin = $employee->hasRole(RoleId::EC_ADMINISTRATOR->value);
        if (!TicketTeamAccess::canAccessAiResearch($employee->employee_id, $ticket, $isAdmin)) {
            abort(403);
        }

        $conversationId = "ticket-{$ticket->ticket_id}";

        $conversation = AiConversation::firstOrCreate(
            [
                'employee_id' => $employee->employee_id,
                'assistant' => AiConversation::ASSISTANT_RESEARCH,
                'conversation_id' => $conversationId,
            ],
            [
                'title' => Str::limit(
                    trim($ticket->ticket_number . ' - ' . (string) $ticket->description),
                    180,
                    ''
                ),
            ],
        );

        if ($conversation->wasRecentlyCreated) {
            $conversation->messages()->create([
                'role' => 'user',
                'content' => $this->ticketContextSeed($ticket),
            ]);
        }

        // autorun=1 HANYA pada penciptaan baru: klik berulang ke tiket yang
        // sama (percakapan lama, sudah ada jawaban) tidak boleh memicu
        // panggilan berbayar kedua — lihat pengecekan sisi client di
        // research.blade.php (DOMContentLoaded) dan sisi server di
        // AiResearchService::streamReply() (giliran terakhir harus masih
        // role user, bukan sekadar percaya query string ini).
        return redirect()->route('ai-research', [
            'conversation' => $conversationId,
            ...($conversation->wasRecentlyCreated ? ['autorun' => 1] : []),
        ]);
    }

    /**
     * Konteks tiket yang di-seed sebagai giliran "user" pertama (lihat
     * openForTicket()). Datanya SAMA PERSIS dengan yang dipakai AI Summarize
     * (TicketSummaryContext::build() — tidak ada context builder baru), tapi
     * dibungkus catatan penekanan di akhir: AI Summarize sudah menjawab
     * "apa status tiket ini & apa yang sudah dikerjakan" (termasuk hal
     * administratif — mandays, approval, email, follow-up); fitur INI
     * seharusnya menjawab pertanyaan yang beda — "apa solusi TEKNIS-nya".
     *
     * SENGAJA tidak menyaring/memotong bagian administratif dari data
     * mentahnya (mis. lewat regex/keyword) — mengenali "ini administratif,
     * ini teknis" jauh lebih andal diserahkan ke penalaran model sendiri
     * saat membaca, daripada heuristik kaku di sini yang berisiko malah
     * membuang detail teknis yang kebetulan disampaikan dengan nada santai.
     */
    private function ticketContextSeed(Ticket $ticket): string
    {
        $context = app(TicketSummaryContext::class)->build($ticket);

        return <<<TEXT
            📋 Konteks tiket (otomatis — lihat halaman tiket untuk detail lengkap):

            {$context}

            ---
            Catatan: informasi mandays/approval, komunikasi email, dan follow-up
            administratif di atas HANYA latar belakang — TIDAK perlu dibahas ulang
            atau diringkas (sudah tercakup di fitur AI Summarize tiket ini). Kalau
            saya bertanya, fokuskan jawaban pada memahami akar masalah TEKNIS tiket
            ini dan memberikan solusi konkret & mendalam untuk menyelesaikannya —
            cari dokumentasi resmi (web search) kalau perlu, bukan cuma menceritakan
            ulang apa yang sudah terjadi di tiket ini.
            TEXT;
    }

    public function chat(Request $request): Response
    {
        if ($rejection = $this->rejectOversizedPost($request)) {
            return $rejection;
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:' . self::MAX_MESSAGE_CHARS,
            'conversation_id' => 'required|string|max:100',
            'model' => 'nullable|string|in:default,deep',
            // Tombol "Continue" pada jawaban yang terpotong: giliran tanpa
            // pertanyaan baru, instruksinya disusun server (lihat service).
            'resume' => 'nullable|boolean',
            // Jawaban otomatis SEKALI untuk percakapan tiket yang baru dibuat
            // (lihat openForTicket() & AiResearchService::streamReply()) —
            // beda dari resume: tidak ada apa pun untuk disambung, giliran
            // user yang dijawab sudah ada (konteks tiket yang di-seed).
            'initial' => 'nullable|boolean',
            'files' => 'nullable|array',
            'files.*' => 'file|max:' . self::MAX_ATTACHMENT_KB,
        ]);

        $employee = $this->currentEmployee();


        $message = trim((string) ($validated['message'] ?? ''));
        $modelTier = $validated['model'] ?? 'default';
        $conversationId = $validated['conversation_id'];
        $resume = (bool) ($validated['resume'] ?? false);
        $initial = (bool) ($validated['initial'] ?? false);

        [$attachments, $rejectedNote] = $this->prepareAttachments($request->file('files', []));

        // Continue dan giliran-otomatis-pertama sama-sama datang tanpa teks
        // dan tanpa berkas — itu memang bentuknya, jadi keduanya tidak boleh
        // kena pagar "pesan kosong".
        if ($resume || $initial) {
            $message = '';
            $attachments = [];
            $rejectedNote = null;
        } elseif ('' === $message && empty($attachments) && null === $rejectedNote) {
            abort(422, 'Message or a supported attachment is required.');
        }

        if ($rejection = $this->rejectIfContextFull($employee, $conversationId, $attachments)) {
            return $rejection;
        }

        $sessionUser = session('user');
        AuditLog::logAiPrompt(
            module: 'AI Research',
            auditableType: 'AiResearchPrompt',
            actorId: $employee->employee_id,
            actorRoleId: $sessionUser['role']['id'] ?? null,
            actorName: $sessionUser['name'] ?? null,
            conversationId: $conversationId,
            message: $message,
            attachmentCount: count($attachments),
            modelTier: $modelTier,
            resume: $resume,
            initial: $initial,
        );

        // Lepas lock session sebelum stream panjang, supaya tab/request lain
        // milik user yang sama tidak ikut terblokir.
        $request->session()->save();

        return response()->stream(function () use ($employee, $conversationId, $message, $attachments, $modelTier, $rejectedNote, $resume, $initial) {
            $send = function (string $event, array $payload): void {
                echo 'event: ' . $event . "\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            if (null !== $rejectedNote) {
                $send('delta', ['text' => $rejectedNote]);
            }

            try {
                /** @var AiResearchService $research */
                $research = app(AiResearchService::class);

                $research->streamReply(
                    employee: $employee,
                    conversationId: $conversationId,
                    userText: $message,
                    attachments: $attachments,
                    modelTier: $modelTier,
                    onDelta: function (string $text) use ($send): void {
                        $send('delta', ['text' => $text]);
                    },
                    onEvent: function (string $event, array $payload) use ($send): void {
                        $send($event, $payload);
                    },
                    isAborted: fn () => 1 === connection_aborted(),
                    resume: $resume,
                    initial: $initial,
                );

                if (0 === connection_aborted()) {
                    $send('done', []);
                }
            } catch (\Throwable $e) {
                Log::error('AI research chat failed', ['error' => $e->getMessage()]);
                if (0 === connection_aborted()) {
                    $send('error', ['message' => 'Something went wrong while searching. Please try again.']);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Daftar percakapan milik user yang sedang login.
     *
     * Sengaja ringkas (judul + waktu): isi percakapan baru diambil saat dibuka,
     * supaya sidebar tetap enteng meski riwayatnya panjang.
     */
    public function conversations(): JsonResponse
    {
        $employee = $this->currentEmployee();

        $rows = AiConversation::where('employee_id', $employee->employee_id)
            ->where('assistant', AiConversation::ASSISTANT_RESEARCH)
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get(['conversation_id', 'title', 'model_tier', 'last_message_at']);

        return response()->json([
            'items' => $rows->map(fn (AiConversation $row) => [
                'id' => $row->conversation_id,
                'title' => $row->title,
                'model_tier' => $row->model_tier,
                'updated_at' => optional($row->last_message_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    /** Transkrip satu percakapan. */
    public function conversation(string $conversation): JsonResponse
    {
        $row = $this->findOwnedConversation($conversation);

        return response()->json([
            'id' => $row->conversation_id,
            'title' => $row->title,
            'model_tier' => $row->model_tier,
            // Sampai di mana ingatan model membentang atas transkrip ini —
            // lihat AiResearchService::contextState(). Dikirim bersama pesan
            // supaya UI bisa menandai batasnya di tempat yang tepat.
            'context' => app(AiResearchService::class)->contextState($this->currentEmployee(), $conversation),
            'messages' => $row->messages->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
                'sources' => $message->sources ?? [],
                'attachments' => $message->attachment_count,
                'at' => optional($message->created_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Hapus satu percakapan — arsip DB sekaligus konteks di cache.
     *
     * POST, bukan DELETE: verb DELETE diblokir edge/WAF di production
     * (lihat catatan yang sama pada modul lain).
     */
    public function destroyConversation(string $conversation): JsonResponse
    {
        $row = $this->findOwnedConversation($conversation);

        $row->delete();   // ai_messages ikut terhapus lewat cascade

        app(AiResearchService::class)->forgetContext($this->currentEmployee(), $conversation);

        return response()->json(['ok' => true]);
    }

    /**
     * Percakapan milik user yang login — atau 404.
     *
     * conversationId adalah UUID yang dikirim browser, jadi ia TIDAK PERNAH
     * dipakai tanpa syarat pemilik: tanpa ini, menempelkan UUID orang lain
     * cukup untuk membaca risetnya (dan lampirannya). employee_id diambil dari
     * session server, bukan dari request.
     */
    private function findOwnedConversation(string $conversation): AiConversation
    {
        $employee = $this->currentEmployee();

        return AiConversation::with('messages')
            ->where('employee_id', $employee->employee_id)
            ->where('assistant', AiConversation::ASSISTANT_RESEARCH)
            ->where('conversation_id', $conversation)
            ->firstOrFail();
    }

    /**
     * Tangkap request yang dipotong PHP karena melewati `post_max_size`.
     *
     * Kalau itu terjadi, PHP membuang SELURUH body — bukan hanya berkasnya.
     * Tanpa pemeriksaan ini, gejala yang sampai ke user adalah error validasi
     * "Message or a supported attachment is required", yang menyesatkan: yang
     * salah bukan pesannya, tapi total unggahannya. Content-Length tetap
     * terisi walau body-nya sudah dibuang — itulah sidik jarinya.
     */
    private function rejectOversizedPost(Request $request): ?JsonResponse
    {
        $length = (int) $request->server('CONTENT_LENGTH', 0);

        if ($length <= 0 || !empty($_POST) || !empty($_FILES)) {
            return null;
        }

        $limit = ini_get('post_max_size');

        return response()->json([
            'message' => "Upload too large for the server to accept (limit {$limit} per message). "
                . 'Send fewer files in one message, or split them across messages.',
        ], 413);
    }

    /**
     * Tolak lampiran baru saat konteks percakapan sudah penuh.
     *
     * Menolak, bukan membuang lampiran lama diam-diam: pemilik sistem memilih
     * agar gambar yang sudah diunggah TETAP bisa dilihat model selama
     * percakapan hidup. Konsekuensinya harus dikatakan terus terang di pesan
     * penolakan — user perlu tahu bahwa memulai chat baru adalah jalan
     * keluarnya, bukan menganggap unggahannya rusak.
     *
     * Pertanyaan TANPA lampiran tidak pernah ditolak; percakapan yang penuh
     * tetap bisa dilanjutkan dengan teks.
     *
     * @param array<int, array{type: string, media_type: string, data: string}> $attachments
     */
    private function rejectIfContextFull(Employee $employee, string $conversationId, array $attachments): ?JsonResponse
    {
        if (empty($attachments)) {
            return null;
        }

        // Lampiran teks tidak punya 'data' base64 — yang dihitung isi teksnya.
        $incoming = array_sum(array_map(
            static fn (array $a) => strlen((string) ($a['data'] ?? $a['text'] ?? '')),
            $attachments
        ));

        if ($incoming > self::MAX_CONTEXT_ATTACHMENT_BYTES) {
            return response()->json([
                'message' => 'These files are too large to fit in a single conversation ('
                    . $this->humanBytes(self::MAX_CONTEXT_ATTACHMENT_BYTES) . ' of attachments in total). '
                    . 'Try sending them across separate chats.',
            ], 422);
        }

        $used = app(AiResearchService::class)->attachmentBytesInContext($employee, $conversationId);

        if ($used + $incoming <= self::MAX_CONTEXT_ATTACHMENT_BYTES) {
            return null;
        }

        return response()->json([
            'message' => 'This chat has reached its attachment limit. Attachments stay in the conversation and '
                . 'are re-sent with every follow-up question, so one chat can only hold about '
                . $this->humanBytes(self::MAX_CONTEXT_ATTACHMENT_BYTES) . ' of them. '
                . 'Start a new chat to attach more — this one stays readable, and you can still ask '
                . 'follow-up questions here without attachments.',
        ], 422);
    }

    /** Menit → "45 minutes" / "12 hours", supaya UI tidak menulis "720 minutes". */
    private static function humanMinutes(int $minutes): string
    {
        if ($minutes < 60 || 0 !== $minutes % 60) {
            return $minutes . ' minutes';
        }

        $hours = intdiv($minutes, 60);

        return $hours . (1 === $hours ? ' hour' : ' hours');
    }

    /** Byte base64 → ukuran berkas asli yang bisa dibaca manusia. */
    private function humanBytes(int $base64Bytes): string
    {
        return floor($base64Bytes * 0.75 / 1024 / 1024) . ' MB';
    }

    /**
     * Ubah teks jawaban assistant (yang sedang ditampilkan di satu bubble)
     * jadi berkas .docx untuk diunduh. Assistant sendiri tidak punya alat
     * untuk membuat/melampirkan file — konversinya terjadi di sini, atas
     * teks yang SUDAH ADA di browser user, bukan permintaan baru ke model.
     */
    public function exportDocx(Request $request): Response
    {
        $this->currentEmployee();

        $validated = $request->validate([
            'text' => 'required|string|max:2000000',
        ]);

        $bytes = AiDocxExport::build($validated['text']);
        $filename = 'ai-research-' . now()->format('Y-m-d-His') . '.docx';

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function currentEmployee(): Employee
    {
        $sessionUser = session('user');

        if (!$sessionUser || 'employee' !== ($sessionUser['type'] ?? null)) {
            abort(401);
        }

        $employee = Employee::find($sessionUser['id']);

        if (!$employee) {
            abort(401);
        }

        return $employee;
    }
    /**
     * Pisahkan file terunggah menjadi lampiran siap-kirim (gambar/PDF) dan
     * catatan untuk user tentang file yang terpaksa dilewati.
     *
     * @param array<int, UploadedFile|null> $files
     * @return array{0: array<int, array{type: string, media_type: string, data: string}>, 1: ?string}
     */
    private function prepareAttachments(array $files): array
    {
        $attachments = [];
        $rejected = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $mime = (string) $file->getMimeType();

            // Teks/kode diperiksa DULUAN: tebakan MIME untuk berkas kode
            // sering salah (.ts → video/mp2t), dan tempelan besar dari
            // composer sampai ke sini sebagai berkas .txt.
            if (AiTextAttachment::isTextual($file)) {
                $attachments[] = AiTextAttachment::fromFile($file);
            } elseif ('application/pdf' === $mime) {
                $attachments[] = [
                    'type' => 'document',
                    'media_type' => 'application/pdf',
                    'data' => base64_encode((string) file_get_contents($file->getRealPath())),
                ];
            } elseif (in_array($mime, self::SUPPORTED_IMAGE_MIMES, true)) {
                $attachments[] = [
                    'type' => 'image',
                    'media_type' => $mime,
                    'data' => base64_encode((string) file_get_contents($file->getRealPath())),
                ];
            } else {
                $rejected[] = $file->getClientOriginalName();
            }
        }

        $note = null;
        if (!empty($rejected)) {
            $names = implode(', ', $rejected);
            $note = "_Note: {$names} — this file type isn't supported yet. Only PDF, image "
                . "(PNG, JPEG, GIF, WEBP), Word (.docx), and text/code attachments can be read right now._\n\n";
        }

        return [$attachments, $note];
    }
}
