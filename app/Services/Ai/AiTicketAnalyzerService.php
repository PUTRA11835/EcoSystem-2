<?php

namespace App\Services\Ai;

use App\Http\Controllers\ConsultantWorkloadController;
use App\Models\AuditLog;
use App\Models\CustomerCredential;
use App\Models\Employee;
use App\Models\EmployeeQualification;
use App\Models\Module;
use App\Models\ModuleLead;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketMember;
use App\Services\Ai\Drivers\AiDriverFactory;
use App\Support\AiModelSettings;
use App\Support\TicketClassification;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Triage satu staging ticket lewat AI (Claude via Agent Skill custom
 * "sap-ticket-analyzer", atau GPT — lihat TicketAnalysisDriver/AiDriverFactory
 * untuk provider mana yang benar-benar dipanggil): overview, dugaan akar
 * masalah, langkah penyelesaian, dan saran klasifikasi (Type/Priority/Scale/
 * Module). Saran "siapa yang di-assign" TIDAK berasal dari AI — dihitung
 * deterministik dari tiga sinyal: (1) Module Lead + EmployeeQualification,
 * (2) histori pernah menangani tiket dengan isu serupa di modul yang sama
 * (lihat resolveAssignees()/countSimilarIssuesHandled()), (3) workload aktif
 * via ConsultantWorkloadController sebagai penentu akhir — supaya hasilnya
 * bisa dipertanggungjawabkan dan tidak berhalusinasi nama orang.
 *
 * Dipicu OTOMATIS sekali saat admin membuka staging ticket unvalidated untuk
 * divalidasi (bukan tombol manual) — lihat StagingTicketController::analyze(),
 * yang meng-klaim ai_analysis_status='pending' secara atomic sebelum memanggil
 * method ini, supaya cuma ada TEPAT SATU pemanggilan API otomatis per tiket.
 * Admin bisa memicu ulang secara sengaja lewat tombol Re-analyze di panel
 * (klaim ulang lewat parameter force di controller) — pemanggilan itu tetap
 * lewat method analyze() yang sama, cukup menimpa ai_analysis yang lama.
 * Karena tidak ada retry OTOMATIS dari sisi sistem, satu kegagalan transient
 * (rate limit/server sibuk/koneksi putus) tidak boleh langsung menghabiskan
 * jatah — analyze() sendiri retry SEKALI untuk kelas error itu sebelum benar-
 * benar menyerah (lihat callDriverWithRetry()).
 */
class AiTicketAnalyzerService
{
    /** Ambang workload (%) di atasnya kandidat diberi warning "sedang tinggi". */
    private const WORKLOAD_WARNING_THRESHOLD = 80.0;

    /** Jeda sebelum satu-satunya retry internal, untuk error yang genuinely transient. */
    private const RETRY_DELAY_SECONDS = 2;

    /**
     * Urutan seniority dari employee_qualification.qualification_level — dipakai
     * buat ranking "siapa yang paling cocok", BUKAN workload. Nilai tak dikenal
     * (termasuk null) jatuh ke 0 lewat null-coalesce di pemanggilnya.
     */
    private const LEVEL_RANK = [
        'Trainee' => 1,
        'Associate' => 2,
        'Junior' => 3,
        'Middle' => 4,
        'Senior' => 5,
    ];

    private const MAX_ASSIGNEE_CANDIDATES = 5;

    /** Berapa banyak konsultan customer & tiket serupa yang ditampilkan di panel. */
    private const MAX_CUSTOMER_CONSULTANTS = 5;
    private const MAX_SIMILAR_TICKETS = 5;

    /**
     * Kata umum yang dibuang saat menokenisasi deskripsi untuk pencarian tiket
     * serupa (lihat resolveSimilarTickets()) — tanpa ini kata seperti "yang"/
     * "tidak"/"bisa" akan cocok dengan HAMPIR SEMUA tiket dan bikin daftar
     * "serupa" tidak berarti apa-apa. Dicampur ID/EN karena deskripsi tiket di
     * sistem ini bercampur dua bahasa.
     */
    private const SIMILAR_TICKET_STOPWORDS = [
        'yang', 'untuk', 'dengan', 'tidak', 'bisa', 'sudah', 'belum', 'akan',
        'pada', 'dari', 'dalam', 'atau', 'juga', 'saat', 'oleh', 'karena',
        'seperti', 'masih', 'hanya', 'lebih', 'harus', 'setelah', 'sebelum',
        'about', 'after', 'again', 'been', 'before', 'being', 'could', 'does',
        'doing', 'have', 'having', 'into', 'ketika', 'other', 'please',
        'should', 'that', 'their', 'them', 'there', 'these', 'this', 'through',
        'were', 'what', 'when', 'where', 'which', 'while', 'with', 'would',
    ];

    /** Panjang minimum token supaya lolos ke pencarian tiket serupa. */
    private const SIMILAR_TICKET_MIN_TOKEN_LENGTH = 4;

    /** Berapa token unik maksimum dipakai dalam satu pencarian. */
    private const SIMILAR_TICKET_MAX_TOKENS = 6;

    public function __construct(private AiDriverFactory $drivers)
    {
    }

    /**
     * Kelas exception yang dianggap transient (worth 1x retry internal) —
     * daftar ini SENGAJA sejajar dengan catch chain di
     * StagingTicketController::analyze() (bagian "retryable: true"). Kalau
     * salah satu diubah, cek yang satunya juga.
     */
    private const RETRYABLE_EXCEPTIONS = [
        \Anthropic\Core\Exceptions\RateLimitException::class,
        \Anthropic\Core\Exceptions\InternalServerException::class,
        \Anthropic\Core\Exceptions\APIConnectionException::class,
        \OpenAI\Exceptions\RateLimitException::class,
        \OpenAI\Exceptions\ServerException::class,
        \OpenAI\Exceptions\TransporterException::class,
    ];

    /**
     * @return array{
     *   overview: string, root_cause_hypothesis: string, resolution_steps: string[],
     *   risks: string[], confidence: ?float, suggested_module_id: ?int,
     *   suggested_module_name: ?string, suggested_module_ids: int[],
     *   suggested_module_names: string[], suggested_ticket_type: ?string,
     *   suggested_priority: ?string, suggested_scale: ?string,
     *   suggested_assignees: array, model: string
     * }
     */
    public function analyze(
        StagingTicket $staging,
        int $actorId,
        ?int $actorRoleId,
        ?string $actorName,
        Closure $onEvent,
        Closure $isAborted,
    ): array {
        $modules = Module::active()->orderBy('name')->get(['id', 'name']);
        $tierConfig = AiModelSettings::resolve(AiModelSettings::TICKET_ANALYZER);
        $actor = Employee::find($actorId);

        $driver = $this->drivers->ticketAnalysis($tierConfig['provider']);

        $text = $this->callDriverWithRetry(
            $driver,
            model: $tierConfig['model'],
            systemPrompt: $this->buildSystemPrompt($modules),
            userMessage: $this->buildUserMessage($staging, $this->resolveCredentialContext($staging, $actor)),
            maxTokens: $tierConfig['max_tokens'],
            effort: $tierConfig['effort'],
            onEvent: $onEvent,
            isAborted: $isAborted,
        );

        $parsed = $this->extractJson($text);
        if (null === $parsed) {
            throw new RuntimeException('Gagal membaca hasil analisa AI (format JSON tidak valid).');
        }

        $moduleIds = $this->sanitizeModuleIds($parsed['suggested_module_ids'] ?? [], $modules);
        $confidence = is_numeric($parsed['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $parsed['confidence']))
            : null;

        // Token & ID tiket yang cocok dihitung SEKALI di sini dan dibagi ke
        // resolveAssignees() + resolveSimilarTickets(), supaya "isu serupa"
        // berarti PERSIS hal yang sama di kedua tempat — dan supaya pemindaian
        // LIKE '%token%' non-index terhadap ticket.description (bagian paling
        // mahal di sini) cuma dijalankan SEKALI, bukan tiga kali seperti
        // sebelumnya (resolveSimilarTickets() + dua query di
        // countSimilarIssuesHandled() masing-masing menjalankan LIKE-scan
        // sendiri dengan token yang identik).
        $searchTokens = $this->extractSearchTokens((string) $staging->description);
        $matchingTicketIds = $this->findMatchingTicketIds($moduleIds, $searchTokens);

        $moduleNames = $modules->whereIn('id', $moduleIds)
            ->sortBy(fn ($m) => array_search($m->id, $moduleIds, true))
            ->pluck('name')
            ->values()
            ->all();

        $result = [
            'overview' => trim((string) ($parsed['overview'] ?? '')),
            'root_cause_hypothesis' => trim((string) ($parsed['root_cause_hypothesis'] ?? '')),
            'resolution_steps' => $this->sanitizeStringList($parsed['resolution_steps'] ?? []),
            'risks' => $this->sanitizeStringList($parsed['risks'] ?? []),
            'confidence' => $confidence,
            // Singular = modul PERTAMA yang disarankan — dipertahankan untuk
            // kompatibilitas mundur (siapa pun yang masih baca field tunggal
            // ini, mis. arsip lama). Sumber kebenaran sekarang array di bawah.
            'suggested_module_id' => $moduleIds[0] ?? null,
            'suggested_module_name' => $moduleNames[0] ?? null,
            'suggested_module_ids' => $moduleIds,
            'suggested_module_names' => $moduleNames,
            'suggested_ticket_type' => $this->sanitizeEnum($parsed['suggested_ticket_type'] ?? null, TicketClassification::TYPES),
            'suggested_priority' => $this->sanitizeEnum($parsed['suggested_priority'] ?? null, TicketClassification::PRIORITIES),
            'suggested_scale' => $this->sanitizeEnum($parsed['suggested_scale'] ?? null, TicketClassification::SCALES),
            'suggested_assignees' => $this->resolveAssignees($moduleIds, $matchingTicketIds),
            'customer_consultants' => $this->resolveCustomerConsultants($staging),
            'similar_tickets' => $this->resolveSimilarTickets($matchingTicketIds),
            'model' => $tierConfig['model'],
        ];

        $staging->update([
            'ai_analysis' => $result,
            'ai_analysis_generated_at' => now(),
            'ai_analysis_generated_by' => $actorId,
            'ai_analysis_status' => 'completed',
        ]);

        AuditLog::logAiPrompt(
            module: 'Ticket Analyzer',
            auditableType: 'StagingTicket',
            actorId: $actorId,
            actorRoleId: $actorRoleId,
            actorName: $actorName,
            conversationId: 'staging-' . $staging->id,
            message: Str::limit((string) $staging->description, 150),
            attachmentCount: 0,
            modelTier: $tierConfig['tier'],
        );

        return $result;
    }

    /**
     * Panggil driver, retry SEKALI (setelah jeda singkat) kalau exception-nya
     * termasuk RETRYABLE_EXCEPTIONS. Ini bukan satu-satunya kesempatan pulih
     * lagi (admin bisa pakai tombol Re-analyze setelah status jatuh ke
     * 'failed'), tapi tetap dilakukan supaya gangguan sesaat tidak langsung
     * memaksa admin klik ulang secara manual.
     *
     * SEJAK DRIVER JADI STREAMING: retry di atas HANYA aman selama belum ada
     * satu pun event yang terkirim ke klien — begitu status pertama sudah
     * tampil di layar validator, browser sudah "melihat" giliran ini
     * berjalan, dan mengulang dari nol akan terlihat seperti macet/reset
     * alih-alih pulih. Preseden di ResearchDriver/AnthropicChatDriver malah
     * TIDAK retry sama sekali begitu streaming mulai — di sini kompromi:
     * $hasStarted dijaga lewat wrapper $onEvent, retry cuma jalan kalau
     * gagalnya terjadi SEBELUM event pertama (kasus paling umum untuk rate
     * limit/connection error yang gagal duluan sebelum sempat streaming apa
     * pun).
     */
    private function callDriverWithRetry(
        \App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver $driver,
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens,
        ?string $effort,
        Closure $onEvent,
        Closure $isAborted,
    ): string {
        $hasStarted = false;
        $guardedOnEvent = function (string $event, array $payload) use (&$hasStarted, $onEvent): void {
            $hasStarted = true;
            $onEvent($event, $payload);
        };

        try {
            return $driver->analyze(
                model: $model,
                systemPrompt: $systemPrompt,
                userMessage: $userMessage,
                maxTokens: $maxTokens,
                effort: $effort,
                onEvent: $guardedOnEvent,
                isAborted: $isAborted,
            );
        } catch (Throwable $e) {
            // array_any() is PHP 8.4+; this project targets 8.2 (composer.json).
            $isRetryable = array_filter(
                self::RETRYABLE_EXCEPTIONS,
                static fn (string $cls) => $e instanceof $cls
            ) !== [];

            if (!$isRetryable || $hasStarted) {
                throw $e;
            }

            sleep(self::RETRY_DELAY_SECONDS);

            // Kalau retry ini juga gagal, biarkan exception-nya menjalar apa
            // adanya — tidak ada retry kedua.
            return $driver->analyze(
                model: $model,
                systemPrompt: $systemPrompt,
                userMessage: $userMessage,
                maxTokens: $maxTokens,
                effort: $effort,
                onEvent: $guardedOnEvent,
                isAborted: $isAborted,
            );
        }
    }

    // ─── Prompt building ──────────────────────────────────────────────────────

    private function buildSystemPrompt(Collection $modules): string
    {
        $moduleList = $modules->map(fn ($m) => "{$m->id}: {$m->name}")->implode("\n");
        $enumUnion = static fn (array $values) => '"' . implode('"|"', $values) . '"|null';
        $typeEnum = $enumUnion(TicketClassification::TYPES);
        $priorityEnum = $enumUnion(TicketClassification::PRIORITIES);
        $scaleEnum = $enumUnion(TicketClassification::SCALES);

        return <<<PROMPT
            Anda adalah SAP support triage analyst untuk tim Delivery Support EcoSystem.
            Tugas Anda: menganalisa satu tiket masuk yang BELUM divalidasi, memberi overview
            singkat, dugaan akar masalah, dan langkah penyelesaian awal — pakai pengetahuan
            dari skill yang sudah dimuat di container ini.

            Daftar modul SAP yang terdaftar di sistem (pakai ID persis ini untuk
            suggested_module_ids — array, boleh lebih dari satu ID kalau tiket ini
            memang menyentuh beberapa modul sekaligus; array kosong [] kalau tidak
            ada yang cocok):
            {$moduleList}

            Balas HANYA dengan satu blok JSON (tanpa teks lain di luar JSON, tanpa penjelasan
            tambahan) dengan schema persis berikut:
            {
              "overview": string,
              "root_cause_hypothesis": string,
              "resolution_steps": string[],
              "suggested_module_ids": number[],
              "suggested_ticket_type": {$typeEnum},
              "suggested_priority": {$priorityEnum},
              "suggested_scale": {$scaleEnum},
              "confidence": number,
              "risks": string[]
            }
            PROMPT;
    }

    private function buildUserMessage(StagingTicket $staging, ?string $credentialContext = null): string
    {
        $customerName = $staging->customer?->basicData?->name_1;
        $rawBody = (string) ($staging->body ?: $staging->email_body_html);
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($rawBody)) ?? '');
        $body = Str::limit($body, 6000, '… [terpotong]');

        $lines = [
            'Channel: ' . ($staging->channel ?? '-'),
            'Customer: ' . ($customerName ?? '-'),
            'Pengirim: ' . ($staging->sender_name ?? '-') . ' <' . ($staging->submitted_by_email ?? '-') . '>',
            'Deskripsi/Subject: ' . ($staging->description ?? '-'),
        ];

        if ($staging->module) {
            $lines[] = 'Modul yang disebutkan pengirim (teks bebas, belum tervalidasi): ' . $staging->module;
        }
        if ($staging->client) {
            $lines[] = 'Client (dari form): ' . $staging->client;
        }

        $lines[] = '';
        $lines[] = 'Isi pesan:';
        $lines[] = $body !== '' ? $body : '(tidak ada isi tambahan)';

        // Hanya ada kalau actor yang memicu analisa ini punya izin
        // 'customer.section.credential.view' — lihat resolveCredentialContext().
        // Ditempel sebagai blok terpisah, bukan disisipkan ke body, supaya
        // jelas ini konteks tambahan sistem, bukan bagian dari pesan pengirim.
        if (null !== $credentialContext) {
            $lines[] = '';
            $lines[] = 'Catatan credential/akses customer ini (khusus konteks internal, jangan dikutip ke customer):';
            $lines[] = $credentialContext;
        }

        return implode("\n", $lines);
    }

    // ─── Response parsing & sanitasi ──────────────────────────────────────────

    private function extractJson(string $text): ?array
    {
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode(trim($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if (false !== $start && false !== $end && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function sanitizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null,
            $value
        )));
    }

    private function sanitizeEnum(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * @return int[] ID unik, sudah divalidasi ada di $modules, urutan dipertahankan
     * (elemen pertama = modul yang jadi "modul utama" — lihat analyze()).
     */
    private function sanitizeModuleIds(mixed $value, Collection $modules): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $v) {
            if (!is_numeric($v)) {
                continue;
            }

            $id = (int) $v;
            if ($modules->contains('id', $id) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    // ─── Saran assignee (deterministik, bukan dari AI) ────────────────────────

    /**
     * Kandidat paling cocok untuk mengerjakan tiket ini, diranking dari TIGA
     * sinyal — persis tiga yang diminta: (1) kecocokan modul (module lead
     * lebih dulu, lalu level kualifikasi), (2) histori pernah menangani isu
     * SERUPA (dihitung dari $searchTokens yang SAMA dengan yang membentuk
     * "Similar Past Tickets" — lihat resolveSimilarTickets()), (3) workload
     * sebagai penentu akhir. Hasilnya array TERURUT 1..N — array_values()
     * di ujung berarti urutan itu SENDIRI adalah rank-nya, tidak perlu kolom
     * rank terpisah.
     *
     * Workload SENGAJA tetap penentu TERAKHIR, bukan filter yang membuang
     * kandidat overload dari daftar — kandidat yang paling cocok dari sisi
     * modul & pengalaman isu serupa tetap harus terlihat, dengan warning,
     * bukan hilang diam-diam. Itu keputusan yang sudah diambil sebelum
     * sinyal isu-serupa ditambahkan, dan tetap dipertahankan di sini.
     *
     * @param int[] $moduleIds modul yang disarankan AI — kandidat adalah UNION lintas semuanya
     * @param int[] $matchingTicketIds hasil findMatchingTicketIds() — sama dengan yang dipakai resolveSimilarTickets()
     */
    private function resolveAssignees(array $moduleIds, array $matchingTicketIds): array
    {
        if (empty($moduleIds)) {
            return [];
        }

        // ModuleLead::module_leads adalah tabel pivot murni — pluck langsung dari
        // sana (bukan lewat leadEmployees()) supaya tidak ambigu dengan kolom
        // employee_id yang juga ada di tabel employee saat di-join. Union lintas
        // SEMUA modul yang disarankan, bukan cuma satu.
        $leadIds = ModuleLead::whereIn('module_id', $moduleIds)->pluck('employee_id')->unique()->all();

        // Tidak difilter ke qualification_type tertentu — di data real, kompetensi
        // modul dicatat di berbagai type ('Certification', dst), bukan cuma 'Skill'
        // literal (lihat juga ConsultantWorkloadController::modulesMapForEmployees(),
        // yang punya masalah sama dan sudah tidak filter by type). Yang penting cuma
        // employee itu punya catatan kualifikasi di SALAH SATU modul ini & belum
        // kedaluwarsa. qualification_level dipakai sebagai sinyal "seberapa cocok" —
        // employee bisa punya >1 baris (beda modul, atau beda tahun modul yang sama),
        // ambil level TERTINGGI di antara semuanya.
        $levelByEmployee = [];
        EmployeeQualification::query()
            ->whereIn('module_id', $moduleIds)
            ->valid()
            ->get(['employee_id', 'qualification_level'])
            ->each(function ($q) use (&$levelByEmployee) {
                $rank = self::LEVEL_RANK[$q->qualification_level] ?? 0;
                $levelByEmployee[$q->employee_id] = max($levelByEmployee[$q->employee_id] ?? 0, $rank);
            });

        $candidateIds = collect($leadIds)->merge(array_keys($levelByEmployee))->unique()->values()->all();
        if (empty($candidateIds)) {
            return [];
        }

        // eligibleForTicketTeam() (bukan cuma is_active) — supaya AI tidak menyarankan
        // employee yang sudah di-block/ditandai untuk dihapus, sinkron dengan
        // gerbang yang sama dipakai TicketController::assignTicketLead()/addMember()
        // saat saran ini benar-benar diklik oleh validator.
        $employees = Employee::whereIn('employee_id', $candidateIds)
            ->eligibleForTicketTeam()
            ->with('basicData')
            ->get()
            ->keyBy('employee_id');

        if ($employees->isEmpty()) {
            return [];
        }

        $workloadMap = ConsultantWorkloadController::workloadByRemainForEmployees(
            $employees->keys()->all(),
            ConsultantWorkloadController::ACTIVE_STATUSES
        );

        $similarCounts = $this->countSimilarIssuesHandled($employees->keys()->all(), $matchingTicketIds);

        return $employees
            ->map(function (Employee $emp) use ($leadIds, $levelByEmployee, $workloadMap, $similarCounts) {
                $pct = (float) ($workloadMap[$emp->employee_id]['pct'] ?? 0.0);
                $isLead = in_array($emp->employee_id, $leadIds, true);
                $name = $emp->basicData
                    ? trim(($emp->basicData->first_name ?? '') . ' ' . ($emp->basicData->last_name ?? ''))
                    : '';
                $highWorkload = $pct >= self::WORKLOAD_WARNING_THRESHOLD;

                return [
                    'employee_id' => $emp->employee_id,
                    'eci' => $emp->eci,
                    'name' => $name !== '' ? $name : $emp->eci,
                    'is_module_lead' => $isLead,
                    'qualification_level' => array_search($levelByEmployee[$emp->employee_id] ?? 0, self::LEVEL_RANK, true) ?: null,
                    '_level_rank' => $levelByEmployee[$emp->employee_id] ?? 0,
                    'similar_issues_handled' => $similarCounts[$emp->employee_id] ?? 0,
                    'workload_pct' => $pct,
                    'warning' => $highWorkload,
                    'warning_message' => $highWorkload ? "Workload sedang tinggi ({$pct}%)" : null,
                ];
            })
            // Tiga tingkat, sesuai urutan yang diminta: (1) kecocokan modul —
            // module lead dulu, lalu level kualifikasi; (2) pernah menangani
            // isu serupa — lebih banyak menang; (3) workload — lebih ringan
            // menang, HANYA sebagai penentu akhir kalau dua sinyal di atas
            // seri, bukan filter yang membuang kandidat overload.
            ->sort(function (array $a, array $b) {
                if ($a['is_module_lead'] !== $b['is_module_lead']) {
                    return $a['is_module_lead'] ? -1 : 1;
                }
                if ($a['_level_rank'] !== $b['_level_rank']) {
                    return $a['_level_rank'] > $b['_level_rank'] ? -1 : 1;
                }
                if ($a['similar_issues_handled'] !== $b['similar_issues_handled']) {
                    return $a['similar_issues_handled'] > $b['similar_issues_handled'] ? -1 : 1;
                }

                return $a['workload_pct'] <=> $b['workload_pct'];
            })
            ->map(function (array $c) {
                unset($c['_level_rank']);

                return $c;
            })
            ->values()
            ->take(self::MAX_ASSIGNEE_CANDIDATES)
            ->all();
    }

    /**
     * Berapa kali tiap kandidat (lead ATAU member aktif) pernah mengerjakan
     * tiket di SALAH SATU modul ini yang deskripsinya cocok dengan token
     * pencarian — sinyal "pernah menangani isu serupa atau mirip" yang
     * diminta secara eksplisit untuk ranking assignee, terpisah dari daftar
     * "Similar Past Tickets" yang cuma menampilkan 5 tiket teratas lintas
     * SEMUA konsultan.
     *
     * $matchingTicketIds sudah menggabungkan filter modul+token (lihat
     * findMatchingTicketIds()), jadi di sini tinggal whereIn(ticket_id) yang
     * murah — tidak menjalankan ulang pemindaian LIKE non-index.
     *
     * @param int[] $candidateIds
     * @param int[] $matchingTicketIds hasil findMatchingTicketIds()
     * @return array<int, int> employee_id => jumlah tiket
     */
    private function countSimilarIssuesHandled(array $candidateIds, array $matchingTicketIds): array
    {
        if (empty($candidateIds) || empty($matchingTicketIds)) {
            return [];
        }

        $counts = [];

        Ticket::query()
            ->whereIn('ticket_id', $matchingTicketIds)
            ->whereIn('ticket_lead_id', $candidateIds)
            ->selectRaw('ticket_lead_id as employee_id, COUNT(*) as cnt')
            ->groupBy('ticket_lead_id')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[$row->employee_id] = ($counts[$row->employee_id] ?? 0) + (int) $row->cnt;
            });

        TicketMember::query()
            ->where('is_active', true)
            ->whereIn('employee_id', $candidateIds)
            ->whereIn('ticket_id', $matchingTicketIds)
            ->selectRaw('employee_id, COUNT(*) as cnt')
            ->groupBy('employee_id')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[$row->employee_id] = ($counts[$row->employee_id] ?? 0) + (int) $row->cnt;
            });

        return $counts;
    }

    // ─── Konteks tambahan: credential, konsultan customer, tiket serupa ───────

    /**
     * Catatan credential/akses customer ini, untuk ditempel ke prompt AI —
     * TIDAK PERNAH ditulis ke $result/ai_analysis, cuma input sekali pakai.
     * Gerbang izin & query-nya ada di CustomerCredential::contextNotesFor()
     * (dipakai bersama dengan AiTicketQaService) — bukan izin "boleh
     * validasi staging ticket", yang tidak otomatis berarti boleh melihat
     * credential customer. Validator tanpa izin ini diam-diam tidak dapat
     * konteksnya, bukan error.
     */
    private function resolveCredentialContext(StagingTicket $staging, ?Employee $actor): ?string
    {
        return CustomerCredential::contextNotesFor(
            [$staging->customer_id, $staging->end_customer_id],
            $actor,
        );
    }

    /**
     * Konsultan yang pernah mengerjakan tiket customer ini — lead ATAU member
     * aktif (lihat Ticket::members(), yang sudah memfilter is_active) — supaya
     * validator tahu siapa yang sudah familiar dengan customer ini, bukan
     * cuma siapa yang cocok dari sisi kualifikasi modul (itu tugas
     * resolveAssignees()). Deterministik, murni dari histori tiket — tidak
     * lewat AI, jadi tidak bisa berhalusinasi nama orang.
     *
     * ticket_team SENGAJA tidak dipakai di sini — tabel itu tidak terpakai di
     * mana pun di aplikasi (lihat riset), ticket_member adalah sumber nyata.
     */
    private function resolveCustomerConsultants(StagingTicket $staging): array
    {
        $customerIds = array_values(array_unique(array_filter([
            $staging->customer_id,
            $staging->end_customer_id,
        ])));

        if (empty($customerIds)) {
            return [];
        }

        // Dibatasi ke N tiket TERBARU (bukan seluruh histori) — customer lama
        // dengan ribuan tiket cuma perlu sinyal "siapa yang baru-baru ini
        // menangani customer ini" untuk saran assignee, bukan seluruh riwayat.
        // orderByDesc di atas sudah memastikan yang dibuang adalah yang paling
        // lama, bukan acak.
        $tickets = Ticket::query()
            ->where(function ($q) use ($customerIds) {
                $q->whereIn('customer_id', $customerIds)->orWhereIn('end_customer_id', $customerIds);
            })
            ->with(['ticketLead.basicData', 'members.basicData'])
            ->orderByDesc('last_message_at')
            ->limit(200)
            ->get(['ticket_id', 'ticket_number', 'ticket_lead_id', 'last_message_at']);

        if ($tickets->isEmpty()) {
            return [];
        }

        // Kumpulkan per employee: hitung berapa tiket, tandai pernah jadi lead
        // atau tidak, simpan nomor tiket & waktu paling baru yang ia kerjakan.
        $byEmployee = [];

        $register = function (?Employee $employee, Ticket $ticket, bool $isLead) use (&$byEmployee): void {
            if (!$employee) {
                return;
            }

            $id = $employee->employee_id;
            $entry = $byEmployee[$id] ?? [
                'employee' => $employee,
                'count' => 0,
                'is_lead' => false,
                'last_ticket_number' => null,
                'last_activity_at' => null,
            ];

            ++$entry['count'];
            $entry['is_lead'] = $entry['is_lead'] || $isLead;

            if (null === $entry['last_activity_at'] || $ticket->last_message_at?->gt($entry['last_activity_at'])) {
                $entry['last_activity_at'] = $ticket->last_message_at;
                $entry['last_ticket_number'] = $ticket->ticket_number;
            }

            $byEmployee[$id] = $entry;
        };

        foreach ($tickets as $ticket) {
            $register($ticket->ticketLead, $ticket, true);

            foreach ($ticket->members as $member) {
                $register($member, $ticket, false);
            }
        }

        return collect($byEmployee)
            ->map(static function (array $entry) {
                $emp = $entry['employee'];
                $name = $emp->basicData
                    ? trim(($emp->basicData->first_name ?? '') . ' ' . ($emp->basicData->last_name ?? ''))
                    : '';

                return [
                    'employee_id' => $emp->employee_id,
                    'eci' => $emp->eci,
                    'name' => $name !== '' ? $name : $emp->eci,
                    'is_lead' => $entry['is_lead'],
                    'tickets_count' => $entry['count'],
                    'last_ticket_number' => $entry['last_ticket_number'],
                    'last_activity_at' => optional($entry['last_activity_at'])->toIso8601String(),
                ];
            })
            ->sortByDesc(static fn (array $c) => $c['last_activity_at'] ?? '')
            ->values()
            ->take(self::MAX_CUSTOMER_CONSULTANTS)
            ->all();
    }

    /**
     * Tiket lama yang deskripsinya bertumpang-tindak kata kunci dengan tiket
     * ini (dan salah satu modul yang sama, kalau AI berhasil menebaknya) —
     * supaya validator bisa lihat siapa yang pernah menangani isu serupa. Ini
     * heuristik LIKE sederhana, BUKAN pencarian semantik: tidak ada
     * infrastruktur fulltext/embedding di sistem ini, jadi ini yang paling
     * jujur bisa dilakukan tanpa menambah dependensi baru — daftarnya bisa
     * meleset kalau deskripsinya pendek/generik, dan itu keterbatasan yang
     * disadari, bukan bug.
     *
     * $matchingTicketIds dihitung SEKALI di analyze() lewat
     * findMatchingTicketIds() dan dibagi ke sini DAN ke resolveAssignees()
     * (lewat countSimilarIssuesHandled()) — supaya "isu serupa" berarti
     * kriteria yang sama persis di kedua daftar, TANPA menjalankan ulang
     * pemindaian LIKE-nya.
     *
     * @param int[] $matchingTicketIds hasil findMatchingTicketIds()
     */
    private function resolveSimilarTickets(array $matchingTicketIds): array
    {
        if (empty($matchingTicketIds)) {
            return [];
        }

        $tickets = Ticket::query()
            ->whereIn('ticket_id', $matchingTicketIds)
            ->whereNotNull('ticket_lead_id')
            ->with('ticketLead.basicData')
            ->orderByDesc('created_at')
            ->limit(self::MAX_SIMILAR_TICKETS)
            ->get(['ticket_id', 'ticket_number', 'description', 'ticket_lead_id', 'created_at']);

        return $tickets->map(static function (Ticket $ticket) {
            $lead = $ticket->ticketLead;
            $name = $lead?->basicData
                ? trim(($lead->basicData->first_name ?? '') . ' ' . ($lead->basicData->last_name ?? ''))
                : '';

            return [
                'ticket_number' => $ticket->ticket_number,
                'excerpt' => Str::limit(trim((string) $ticket->description), 140),
                'consultant_name' => $name !== '' ? $name : $lead?->eci,
                'consultant_eci' => $lead?->eci,
                'created_at' => optional($ticket->created_at)->toIso8601String(),
            ];
        })->all();
    }

    /**
     * ID tiket yang deskripsinya cocok dengan salah satu $tokens (dan salah
     * satu $moduleIds kalau ada) — pemindaian LIKE '%token%' non-index yang
     * jadi bagian PALING MAHAL dari resolveAssignees()+resolveSimilarTickets()
     * gabungan. Dihitung SEKALI di analyze() dan hasilnya (ID, bukan model)
     * dibagi ke countSimilarIssuesHandled() dan resolveSimilarTickets() lewat
     * whereIn(ticket_id) yang murah, alih-alih tiap tempat menjalankan ulang
     * LIKE-scan yang sama dengan token yang sama persis.
     *
     * @param int[] $moduleIds
     * @param string[] $tokens
     * @return int[] ticket_id
     */
    private function findMatchingTicketIds(array $moduleIds, array $tokens): array
    {
        if (empty($tokens)) {
            return [];
        }

        // Dibatasi + diurutkan ke yang paling baru — endpoint ini jalan OTOMATIS
        // tiap kali admin membuka staging ticket, jadi full-table LIKE scan tanpa
        // batas jadi makin mahal seiring tabel ticket membesar. Tiket TERBARU
        // yang mirip lebih relevan untuk "similar issues"/saran assignee
        // daripada kecocokan lama yang mungkin sudah tidak representatif.
        return Ticket::query()
            ->when(!empty($moduleIds), fn ($q) => $q->whereHas('modules', fn ($q2) => $q2->whereIn('module_id', $moduleIds)))
            ->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhere('description', 'like', '%' . $token . '%');
                }
            })
            ->orderByDesc('created_at')
            ->limit(300)
            ->pluck('ticket_id')
            ->all();
    }

    /**
     * @return string[] token unik, huruf kecil, sudah dibuang stopword & yang terlalu pendek
     */
    private function extractSearchTokens(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];

        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < self::SIMILAR_TICKET_MIN_TOKEN_LENGTH
                || in_array($word, self::SIMILAR_TICKET_STOPWORDS, true)
                || in_array($word, $tokens, true)
            ) {
                continue;
            }

            $tokens[] = $word;

            if (count($tokens) >= self::SIMILAR_TICKET_MAX_TOKENS) {
                break;
            }
        }

        return $tokens;
    }
}
