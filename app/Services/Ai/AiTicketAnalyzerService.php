<?php

namespace App\Services\Ai;

use App\Http\Controllers\ConsultantWorkloadController;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeQualification;
use App\Models\Module;
use App\Models\StagingTicket;
use App\Services\Ai\Drivers\AiDriverFactory;
use App\Support\AiModelSettings;
use App\Support\TicketClassification;
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
 * deterministik dari Module Lead + EmployeeQualification, lalu di-cross-check
 * dengan workload aktif via ConsultantWorkloadController supaya hasilnya bisa
 * dipertanggungjawabkan dan tidak berhalusinasi nama orang.
 *
 * Dipicu OTOMATIS sekali saat admin membuka staging ticket unvalidated untuk
 * divalidasi (bukan tombol manual) — lihat StagingTicketController::analyze(),
 * yang meng-klaim ai_analysis_status='pending' secara atomic sebelum memanggil
 * method ini, supaya cuma ada TEPAT SATU pemanggilan API nyata per tiket
 * selama-lamanya (tidak ada re-analyze). Karena tidak ada jalan untuk mencoba
 * lagi, satu kegagalan transient (rate limit/server sibuk/koneksi putus) tidak
 * boleh langsung menghabiskan jatah — analyze() sendiri retry SEKALI untuk
 * kelas error itu sebelum benar-benar menyerah (lihat callDriverWithRetry()).
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
     *   suggested_module_name: ?string, suggested_ticket_type: ?string,
     *   suggested_priority: ?string, suggested_scale: ?string,
     *   suggested_assignees: array, model: string
     * }
     */
    public function analyze(
        StagingTicket $staging,
        int $actorId,
        ?int $actorRoleId,
        ?string $actorName,
    ): array {
        $modules = Module::active()->orderBy('name')->get(['id', 'name']);
        $tierConfig = AiModelSettings::resolve(AiModelSettings::TICKET_ANALYZER);

        $driver = $this->drivers->ticketAnalysis($tierConfig['provider']);

        $text = $this->callDriverWithRetry(
            $driver,
            model: $tierConfig['model'],
            systemPrompt: $this->buildSystemPrompt($modules),
            userMessage: $this->buildUserMessage($staging),
            maxTokens: $tierConfig['max_tokens'],
            effort: $tierConfig['effort'],
        );

        $parsed = $this->extractJson($text);
        if (null === $parsed) {
            throw new RuntimeException('Gagal membaca hasil analisa AI (format JSON tidak valid).');
        }

        $moduleId = $this->sanitizeModuleId($parsed['suggested_module_id'] ?? null, $modules);
        $confidence = is_numeric($parsed['confidence'] ?? null)
            ? max(0.0, min(1.0, (float) $parsed['confidence']))
            : null;

        $result = [
            'overview' => trim((string) ($parsed['overview'] ?? '')),
            'root_cause_hypothesis' => trim((string) ($parsed['root_cause_hypothesis'] ?? '')),
            'resolution_steps' => $this->sanitizeStringList($parsed['resolution_steps'] ?? []),
            'risks' => $this->sanitizeStringList($parsed['risks'] ?? []),
            'confidence' => $confidence,
            'suggested_module_id' => $moduleId,
            'suggested_module_name' => $moduleId ? $modules->firstWhere('id', $moduleId)?->name : null,
            'suggested_ticket_type' => $this->sanitizeEnum($parsed['suggested_ticket_type'] ?? null, TicketClassification::TYPES),
            'suggested_priority' => $this->sanitizeEnum($parsed['suggested_priority'] ?? null, TicketClassification::PRIORITIES),
            'suggested_scale' => $this->sanitizeEnum($parsed['suggested_scale'] ?? null, TicketClassification::SCALES),
            'suggested_assignees' => $this->resolveAssignees($moduleId),
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
     * termasuk RETRYABLE_EXCEPTIONS. Karena tidak ada tombol re-analyze lagi,
     * ini satu-satunya kesempatan tiket ini punya untuk pulih dari gangguan
     * sesaat sebelum status jatuh ke 'failed' secara permanen.
     */
    private function callDriverWithRetry(
        \App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver $driver,
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens,
        ?string $effort,
    ): string {
        try {
            return $driver->analyze(
                model: $model,
                systemPrompt: $systemPrompt,
                userMessage: $userMessage,
                maxTokens: $maxTokens,
                effort: $effort,
            );
        } catch (Throwable $e) {
            // array_any() is PHP 8.4+; this project targets 8.2 (composer.json).
            $isRetryable = array_filter(
                self::RETRYABLE_EXCEPTIONS,
                static fn (string $cls) => $e instanceof $cls
            ) !== [];

            if (!$isRetryable) {
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
            suggested_module_id; null kalau tidak ada yang cocok):
            {$moduleList}

            Balas HANYA dengan satu blok JSON (tanpa teks lain di luar JSON, tanpa penjelasan
            tambahan) dengan schema persis berikut:
            {
              "overview": string,
              "root_cause_hypothesis": string,
              "resolution_steps": string[],
              "suggested_module_id": number|null,
              "suggested_ticket_type": {$typeEnum},
              "suggested_priority": {$priorityEnum},
              "suggested_scale": {$scaleEnum},
              "confidence": number,
              "risks": string[]
            }
            PROMPT;
    }

    private function buildUserMessage(StagingTicket $staging): string
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

    private function sanitizeModuleId(mixed $value, Collection $modules): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $modules->contains('id', $id) ? $id : null;
    }

    // ─── Saran assignee (deterministik, bukan dari AI) ────────────────────────

    private function resolveAssignees(?int $moduleId): array
    {
        if (!$moduleId) {
            return [];
        }

        $module = Module::find($moduleId);
        if (!$module) {
            return [];
        }

        // ModuleLead::module_leads adalah tabel pivot murni — pluck langsung dari
        // sana (bukan lewat leadEmployees()) supaya tidak ambigu dengan kolom
        // employee_id yang juga ada di tabel employee saat di-join.
        $leadIds = $module->leads()->pluck('employee_id')->all();

        // Tidak difilter ke qualification_type tertentu — di data real, kompetensi
        // modul dicatat di berbagai type ('Certification', dst), bukan cuma 'Skill'
        // literal (lihat juga ConsultantWorkloadController::modulesMapForEmployees(),
        // yang punya masalah sama dan sudah tidak filter by type). Yang penting cuma
        // employee itu punya catatan kualifikasi utk modul ini & belum kedaluwarsa.
        // qualification_level dipakai sebagai sinyal "seberapa cocok" — employee bisa
        // punya >1 baris utk modul yang sama (mis. beda tahun), ambil level tertinggi.
        $levelByEmployee = [];
        EmployeeQualification::query()
            ->where('module_id', $moduleId)
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

        $employees = Employee::whereIn('employee_id', $candidateIds)
            ->where('is_active', true)
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

        return $employees
            ->map(function (Employee $emp) use ($leadIds, $levelByEmployee, $workloadMap) {
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
                    'workload_pct' => $pct,
                    'warning' => $highWorkload,
                    'warning_message' => $highWorkload ? "Workload sedang tinggi ({$pct}%)" : null,
                ];
            })
            // Urutan murni dari kecocokan (module lead, lalu level kualifikasi) — BUKAN
            // dari workload. Workload cuma metadata/warning, tidak boleh menyingkirkan
            // kandidat yang sebetulnya paling cocok dari daftar yang ditampilkan.
            ->sort(function (array $a, array $b) {
                if ($a['is_module_lead'] !== $b['is_module_lead']) {
                    return $a['is_module_lead'] ? -1 : 1;
                }
                if ($a['_level_rank'] !== $b['_level_rank']) {
                    return $a['_level_rank'] > $b['_level_rank'] ? -1 : 1;
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
}
