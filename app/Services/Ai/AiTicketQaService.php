<?php

namespace App\Services\Ai;

use App\Models\AuditLog;
use App\Models\CustomerCredential;
use App\Models\Employee;
use App\Models\StagingTicket;
use App\Services\Ai\Drivers\AiDriverFactory;
use App\Support\AiModelSettings;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Tanya-jawab interaktif di panel AI Analyzer selagi admin memvalidasi satu
 * staging ticket — bukan cuma menerima saran AiTicketAnalyzerService apa
 * adanya, validator bisa bertanya balik ("apa langkah paling awal?", "kenapa
 * root cause-nya begitu?") dan dijawab dengan konteks tiket yang sama persis
 * dengan yang dilihat di layar.
 *
 * SENGAJA lebih ringan dari kembarnya di AI Assistant (AiChatService):
 *   - tools: [] — tidak ada tool loop. Semua konteks yang relevan (deskripsi
 *     tiket, hasil analyze() sebelumnya, daftar konsultan customer, tiket
 *     serupa, catatan credential kalau actor punya izin) sudah dibangun
 *     SEKALI ke system prompt tiap giliran lewat systemPrompt() — model tidak
 *     perlu memanggil apa pun untuk menjawab pertanyaan tentang tiket yang
 *     sama, jadi tidak ada tool baru yang perlu ditulis untuk fitur ini.
 *   - Tidak ada arsip DB. Validasi satu tiket biasanya selesai dalam satu
 *     duduk; riwayatnya cukup hidup di cache selama sesi modal terbuka.
 *   - $sessionId BUKAN id yang dipertahankan lintas buka-tutup modal (beda
 *     dari AiChatService, yang justru sengaja mempertahankan conversation id
 *     lewat sessionStorage supaya chat tidak hilang saat pindah halaman) —
 *     di sini frontend membuat UUID baru SETIAP KALI modal validasi dibuka
 *     untuk sebuah tiket (lihat staging/index.blade.php). Sesi validasi yang
 *     sudah ditinggalkan tidak boleh diam-diam "diingat" lagi saat tiket yang
 *     sama dibuka ulang nanti — kalau id-nya dipertahankan, layar akan mulai
 *     kosong padahal server masih ingat percakapan lama, persis kelas bug
 *     yang baru dibenahi di AI Assistant.
 */
class AiTicketQaService
{
    private const CACHE_STORE = 'ai_chat';
    private const CACHE_TTL_MINUTES = 120;

    public function __construct(private AiDriverFactory $drivers)
    {
    }

    /**
     * @param Closure(string): void $onDelta dipanggil tiap potongan teks jawaban
     * @param Closure(): bool $isAborted dipolling di antara langkah jaringan
     */
    public function streamReply(
        Employee $employee,
        StagingTicket $staging,
        string $sessionId,
        string $userText,
        Closure $onDelta,
        Closure $isAborted,
        ?int $actorRoleId = null,
        ?string $actorName = null,
    ): void {
        $cacheKey = $this->cacheKey($staging, $employee, $sessionId);
        $messages = $this->readCache($cacheKey);

        $messages[] = [
            'role' => 'user',
            'content' => [['type' => 'text', 'text' => $userText]],
        ];

        $config = AiModelSettings::resolve(AiModelSettings::TICKET_QA);
        $driver = $this->drivers->chat($config['provider']);

        [$assistantContent] = $driver->turn(
            model: $config['model'],
            systemPrompt: $this->systemPrompt($staging, $employee),
            messages: $messages,
            tools: [],
            maxTokens: $config['max_tokens'],
            effort: $config['effort'],
            onDelta: $onDelta,
            isAborted: $isAborted,
        );

        if (null === $assistantContent) {
            // Aborted mid-stream — jangan simpan giliran yang tidak lengkap.
            return;
        }

        $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

        Cache::store(self::CACHE_STORE)->put(
            $cacheKey,
            ['messages' => $messages],
            now()->addMinutes(self::CACHE_TTL_MINUTES),
        );

        AuditLog::logAiPrompt(
            module: 'Ticket Analyzer Q&A',
            auditableType: 'StagingTicket',
            actorId: $employee->employee_id,
            actorRoleId: $actorRoleId,
            actorName: $actorName,
            conversationId: 'staging-' . $staging->id . '-' . $sessionId,
            message: $userText,
            attachmentCount: 0,
            modelTier: $config['tier'],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCache(string $cacheKey): array
    {
        $state = Cache::store(self::CACHE_STORE)->get($cacheKey);
        $messages = is_array($state) ? ($state['messages'] ?? null) : null;

        return is_array($messages) ? $messages : [];
    }

    private function cacheKey(StagingTicket $staging, Employee $employee, string $sessionId): string
    {
        $safeSession = preg_replace('/[^a-zA-Z0-9\-]/', '', $sessionId);
        $safeSession = $safeSession ?: 'default';

        return "ai_ticket_qa:staging{$staging->id}:emp{$employee->employee_id}:{$safeSession}";
    }

    /**
     * Konteks tiket dibangun ULANG tiap giliran (bukan disimpan sekali di
     * cache) supaya kalau admin mengklik Re-analyze DI TENGAH sesi tanya-
     * jawab, pertanyaan berikutnya otomatis melihat ai_analysis yang
     * terbaru — tanpa ini, chat akan tetap merujuk ke saran AI yang sudah
     * ditimpa.
     */
    private function systemPrompt(StagingTicket $staging, Employee $employee): string
    {
        $customerName = $staging->customer?->basicData?->name_1;
        $rawBody = (string) ($staging->body ?: $staging->email_body_html);
        $body = trim(preg_replace('/\s+/', ' ', strip_tags($rawBody)) ?? '');
        $body = Str::limit($body, 4000, '… [terpotong]');

        $lines = [
            'Anda membantu seorang validator EcoSystem menjawab pertanyaan tentang SATU staging ticket yang '
                . 'sedang divalidasi. Jawab HANYA berdasar konteks tiket ini — jangan mengarang nomor tiket, nama '
                . 'orang, atau data lain yang tidak ada di bawah. Kalau pertanyaannya tidak bisa dijawab dari '
                . 'konteks ini, katakan terus terang alih-alih menebak. Jawab singkat dan langsung, sesuai bahasa '
                . 'pertanyaan validator.',
            '',
            '=== Tiket ===',
            'Channel: ' . ($staging->channel ?? '-'),
            'Customer: ' . ($customerName ?? '-'),
            'Deskripsi/Subject: ' . ($staging->description ?? '-'),
            'Isi pesan: ' . ($body !== '' ? $body : '(tidak ada isi tambahan)'),
        ];

        $analysis = $staging->ai_analysis;
        if (is_array($analysis)) {
            $lines[] = '';
            $lines[] = '=== Analisa AI sebelumnya (sudah tampil di layar validator) ===';
            $lines[] = 'Overview: ' . ($analysis['overview'] ?? '-');
            $lines[] = 'Dugaan root cause: ' . ($analysis['root_cause_hypothesis'] ?? '-');

            $steps = $analysis['resolution_steps'] ?? [];
            if (is_array($steps) && !empty($steps)) {
                $lines[] = 'Langkah penyelesaian yang disarankan:';
                foreach ($steps as $i => $step) {
                    $lines[] = ($i + 1) . '. ' . $step;
                }
            }

            $bestFit = $analysis['suggested_assignees'] ?? [];
            if (is_array($bestFit) && !empty($bestFit)) {
                $lines[] = '';
                $lines[] = 'Kandidat paling cocok mengerjakan tiket ini, SUDAH terurut dari yang paling cocok '
                    . '(gabungan kecocokan modul, pengalaman isu serupa, dan workload):';
                foreach ($bestFit as $i => $c) {
                    $lines[] = ($i + 1) . '. ' . ($c['name'] ?? '-')
                        . ($c['is_module_lead'] ?? false ? ' (Module Lead)' : '')
                        . ' — ' . ($c['similar_issues_handled'] ?? 0) . ' tiket isu serupa pernah ditangani, '
                        . 'workload ' . round($c['workload_pct'] ?? 0) . '%';
                }
            }

            $consultants = $analysis['customer_consultants'] ?? [];
            if (is_array($consultants) && !empty($consultants)) {
                $lines[] = '';
                $lines[] = 'Konsultan yang pernah menangani customer ini: ' . collect($consultants)
                    ->map(fn (array $c) => ($c['name'] ?? '-') . ' (' . ($c['tickets_count'] ?? 0) . ' tiket)')
                    ->implode(', ');
            }

            $similar = $analysis['similar_tickets'] ?? [];
            if (is_array($similar) && !empty($similar)) {
                $lines[] = '';
                $lines[] = 'Tiket lama dengan isu serupa:';
                foreach ($similar as $t) {
                    $lines[] = '- ' . ($t['ticket_number'] ?? '-') . ' oleh ' . ($t['consultant_name'] ?? '-')
                        . ': ' . ($t['excerpt'] ?? '-');
                }
            }
        }

        $credential = CustomerCredential::contextNotesFor(
            [$staging->customer_id, $staging->end_customer_id],
            $employee,
        );
        if (null !== $credential) {
            $lines[] = '';
            $lines[] = '=== Catatan credential/akses customer ini (konteks internal, jangan dikutip ke customer) ===';
            $lines[] = $credential;
        }

        return implode("\n", $lines);
    }
}
