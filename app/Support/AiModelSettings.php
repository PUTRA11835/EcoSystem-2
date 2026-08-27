<?php

namespace App\Support;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sumber kebenaran TUNGGAL untuk model AI yang dipakai EcoSystem.
 *
 * Sebelum ini, model dan plafon token ditulis langsung di dalam
 * AiResearchService::modelConfigFor() dan AiChatService::modelConfigFor(),
 * jadi menurunkan Opus ke Sonnet demi menekan biaya berarti deploy ulang.
 * Sekarang pemiliknya adalah super admin lewat Control Center → AI Settings,
 * dan kelas ini yang membaca keputusannya.
 *
 * SEJAK Agustus 2026 katalognya LINTAS PROVIDER — bukan Claude saja. Setiap
 * model membawa `provider` ('anthropic'|'openai'); AiChatService/
 * AiResearchService/AiTicketAnalyzerService membaca `provider` hasil
 * resolve() untuk memilih driver (lihat App\Services\Ai\Drivers\AiDriverFactory)
 * yang benar-benar bicara ke API provider itu. Kelas ini sendiri TIDAK
 * memanggil API mana pun — cuma sumber kebenaran ketentuan mana yang aktif.
 *
 * SATU MODEL PER ASISTEN — bukan beberapa preset (dulu "Fast"/"Balanced"/
 * "Reasoning"/"Deep research") yang dipilih lewat radio "Active". Preset itu
 * dibuang karena user tidak pernah memilih tier sendiri (selalu memakai yang
 * admin tandai aktif), jadi menyimpan beberapa preset yang tidak dipakai
 * hanya menambah tempat untuk salah pilih di form admin. Struktur
 * penyimpanan sekarang rata: satu {provider, model, max_tokens, effort} per
 * asisten, bukan {active, tiers: {...}}.
 *
 * DUA HAL YANG TIDAK BOLEH DISERAHKAN KE ADMIN — ditegakkan di sanitize():
 *
 *   1. AI Research & Ticket Analyzer HARUS memakai model yang mendukung
 *      server-side web tool (web_search_20260209/web_fetch_20260209 di
 *      Anthropic, tool `web_search` bawaan Responses API di OpenAI). Model
 *      tanpa itu (mis. Haiku 4.5) TIDAK ditawarkan untuk dua asisten itu —
 *      kalau sampai terpilih, halamannya mati total dengan 400 dari API.
 *      Katalognya karena itu disaring per-asisten, bukan satu daftar global.
 *
 *   2. Effort/reasoning bukan satu skala universal — Claude memakai
 *      low/medium/high/xhigh/max (output_config.effort), OpenAI memakai
 *      minimal/low/medium/high (reasoning.effort), dan sebagian model
 *      (Haiku 4.5) menolak parameter ini sama sekali, bukan mengabaikannya.
 *      Karena itu setiap entri katalog membawa daftar `efforts` MILIKNYA
 *      SENDIRI, dan nilai yang tersimpan divalidasi terhadap daftar model
 *      yang benar-benar aktif — bukan daftar global satu ukuran untuk semua.
 *
 * Nilai yang tersimpan di DB SELALU dilewatkan sanitize() saat DIBACA, bukan
 * hanya saat disimpan: baris app_configs bisa saja diedit langsung lewat SQL,
 * atau tertinggal menyebut model yang sudah pensiun. Jalur baca tidak boleh
 * bisa dijatuhkan oleh isi tabel.
 */
final class AiModelSettings
{
    /** Kunci baris di tabel app_configs. */
    public const KEY = 'ai.models';

    public const RESEARCH = 'research';
    public const INTERNAL = 'internal';
    public const TICKET_ANALYZER = 'ticket_analyzer';

    /**
     * Tombol "AI Summarize" di daftar tiket (AiTicketSummaryService).
     *
     * Punya entri SENDIRI sejak Agustus 2026. Sebelumnya fitur ini menumpang
     * konfigurasi AI Assistant (INTERNAL) dan diam-diam jatuh ke konfigurasi AI
     * Research kalau model INTERNAL kebetulan tidak punya server tool. Akibatnya
     * admin yang mengubah AI Assistant ikut mengubah biaya & latensi ringkasan
     * tiket tanpa tahu, dan sebaliknya tidak ada cara mengatur ringkasan tiket
     * sendiri. Beban kerjanya juga jelas berbeda dari chat internal: satu
     * giliran, keluaran pendek berstruktur tetap, tapi WAJIB memakai web search.
     * Layak punya barisnya sendiri di Control Center.
     */
    public const TICKET_SUMMARY = 'ticket_summary';

    public const WORD_REPORT = 'word_report';

    /**
     * Fase 3 Word Report Generator (assembleDocument -- lihat
     * ReportGeneratorService) sengaja punya entri model TERPISAH dari
     * WORD_REPORT (fase 1/2, structure/data): fase itu satu-satunya yang
     * butuh code execution/code_interpreter (paling berat, paling banyak
     * token), dan kalau model-nya SAMA dengan fase 1/2, ketiganya berebut
     * TPM pool yang sama -- lebih parah lagi kalau assistant lain (Research/
     * Ticket Analyzer) juga dipasang ke model yang sama. Memisah entrinya
     * biar admin bisa taruh fase 3 di model dengan TPM pool sendiri tanpa
     * mengubah fase 1/2 yang sudah terbukti murah & jarang gagal.
     */
    public const WORD_REPORT_DOCUMENT = 'word_report_document';

    /** Skala effort Claude (output_config.effort). */
    private const CLAUDE_EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

    /** Skala reasoning effort OpenAI Responses API (reasoning.effort) — kosakata berbeda dari Claude. */
    private const OPENAI_EFFORTS = ['minimal', 'low', 'medium', 'high'];

    /**
     * Model yang boleh dipilih. Daftar TERTUTUP — bukan input teks bebas,
     * supaya salah ketik ("claude-sonet-5") ketahuan di form, bukan saat user
     * pertama menekan Enter dan API menjawab 404.
     *
     * - provider     : 'anthropic' | 'openai' — menentukan driver mana yang dipakai
     *                  (App\Services\Ai\Drivers\AiDriverFactory), BUKAN cuma label.
     * - server_tools : mendukung web search/fetch bawaan provider (Anthropic:
     *                  web_search_20260209/web_fetch_20260209; OpenAI: tool
     *                  `web_search` Responses API) — syarat AI Research & Ticket Analyzer.
     * - efforts      : daftar TERTUTUP nilai effort/reasoning yang diterima model INI.
     *                  Array kosong = model menolak parameter effort sama sekali.
     * - max_output   : plafon max_tokens/max_output_tokens milik model itu sendiri
     * - price_in/out : USD per 1 juta token, untuk ditampilkan di form
     */
    private const CATALOG = [
        'claude-opus-5' => [
            'label' => 'Claude Opus 5',
            'provider' => 'anthropic',
            'server_tools' => true,
            'efforts' => self::CLAUDE_EFFORTS,
            'max_output' => 128000,
            'context' => '1M',
            'price_in' => 5.0,
            'price_out' => 25.0,
            'note' => 'Paling kuat di keluarga Claude, dan paling mahal: 5× harga Haiku per token masuk.',
        ],
        'claude-sonnet-5' => [
            'label' => 'Claude Sonnet 5',
            'provider' => 'anthropic',
            'server_tools' => true,
            'efforts' => self::CLAUDE_EFFORTS,
            'max_output' => 128000,
            'context' => '1M',
            'price_in' => 3.0,
            'price_out' => 15.0,
            'note' => 'Keseimbangan mutu dan biaya; pilihan baku Claude untuk keduanya.',
        ],
        'claude-fable-5' => [
            'label' => 'Claude Fable 5',
            'provider' => 'anthropic',
            'server_tools' => true,
            'efforts' => self::CLAUDE_EFFORTS,
            'max_output' => 128000,
            'context' => '1M',
            'price_in' => 10.0,
            'price_out' => 50.0,
            'note' => 'Model Claude paling mampu, dengan reasoning selalu aktif. 2× harga Opus 5 per token.',
        ],
        'claude-haiku-4-5' => [
            'label' => 'Claude Haiku 4.5',
            'provider' => 'anthropic',
            'server_tools' => false,
            'efforts' => [],
            'max_output' => 64000,
            'context' => '200K',
            'price_in' => 1.0,
            'price_out' => 5.0,
            'note' => 'Termurah dan tercepat. TIDAK bisa dipakai AI Research (tanpa web search).',
        ],
        'gpt-5.6-sol' => [
            'label' => 'GPT-5.6 Sol',
            'provider' => 'openai',
            'server_tools' => true,
            'efforts' => self::OPENAI_EFFORTS,
            'max_output' => 128000,
            'context' => '1.05M',
            'price_in' => 4.0,
            'price_out' => 20.0,
            'note' => 'Flagship OpenAI untuk reasoning dan coding kompleks. Setara kelas Opus 5.',
        ],
        'gpt-5.6-terra' => [
            'label' => 'GPT-5.6 Terra',
            'provider' => 'openai',
            'server_tools' => true,
            'efforts' => self::OPENAI_EFFORTS,
            'max_output' => 128000,
            'context' => '1.05M',
            'price_in' => 2.0,
            'price_out' => 12.0,
            'note' => 'Keseimbangan mutu dan biaya di keluarga GPT-5.6. Setara kelas Sonnet 5.',
        ],
        'gpt-5.6-luna' => [
            'label' => 'GPT-5.6 Luna',
            'provider' => 'openai',
            'server_tools' => true,
            'efforts' => self::OPENAI_EFFORTS,
            'max_output' => 128000,
            'context' => '1.05M',
            'price_in' => 0.20,
            'price_out' => 1.20,
            'note' => 'Termurah di keluarga GPT-5.6, untuk volume tinggi.',
        ],
    ];

    /**
     * Asisten yang HANYA boleh memakai model ber-`server_tools`.
     *
     * Satu daftar, dipakai dua kali: catalogFor() (apa yang ditawarkan form
     * admin) dan sanitize() (apa yang benar-benar diterima saat disimpan &
     * dibaca). Dulu daftarnya ditulis ulang di kedua tempat, jadi menambah
     * asisten baru berarti harus ingat mengubah dua tempat -- kalau yang
     * pertama terlewat, form menawarkan model yang diam-diam ditolak.
     */
    private const NEEDS_SERVER_TOOLS = [
        self::RESEARCH,
        self::TICKET_ANALYZER,
        self::TICKET_SUMMARY,
        self::WORD_REPORT,
        self::WORD_REPORT_DOCUMENT,
    ];

    /** Plafon max_tokens terendah yang masih masuk akal untuk sebuah jawaban. */
    private const MIN_MAX_TOKENS = 512;

    /**
     * Keadaan awal — SATU model per asisten, bukan beberapa preset yang
     * dipilih lewat radio "Active". Angka-angkanya sama persis dengan yang
     * dulu jadi preset "Balanced"/"Reasoning" default, supaya migrasi dari
     * struktur tier lama tidak mengubah perilaku apa pun sampai admin
     * benar-benar menyentuh formnya. Lihat catatan penghapusan tier di
     * docblock kelas ini.
     */
    private const DEFAULTS = [
        self::RESEARCH => [
            'model' => 'claude-sonnet-5',
            'max_tokens' => 32000,
            'effort' => 'medium',
        ],
        self::INTERNAL => [
            'model' => 'claude-sonnet-5',
            'max_tokens' => 4096,
            'effort' => 'medium',
        ],
        self::TICKET_ANALYZER => [
            'model' => 'claude-opus-5',
            'max_tokens' => 4096,
            'effort' => 'high',
        ],
        self::TICKET_SUMMARY => [
            // OpenAI, bukan Claude: ringkasan tiket adalah fitur bervolume
            // paling tinggi di antara semua asisten (satu klik per tiket, oleh
            // siapa pun yang membuka daftar tiket), dan gpt-5.6-terra memberi
            // web search yang sama dengan harga per token jauh di bawah Opus 5.
            'model' => 'gpt-5.6-terra',
            // 16000 = angka yang dulu dipatok mati di dalam
            // AiTicketSummaryService (SUMMARY_MAX_TOKENS). Tiga bagian pendek
            // tidak pernah butuh lebih, tapi sekarang angkanya terlihat dan
            // bisa diubah admin tanpa deploy.
            'max_tokens' => 16000,
            // 'medium', bukan 'high' -- ini tombol latensi paling berpengaruh:
            // effort tinggi membuat model berpikir lama sebelum dan di antara
            // pencarian, sementara user menatap modal menunggu.
            'effort' => 'medium',
        ],
        self::WORD_REPORT => [
            'model' => 'claude-opus-5',
            // 32000, bukan 16000 -- tugas ini multi-giliran (baca template,
            // beberapa tool call, code execution/interpreter) dan pada GPT
            // reasoning effort tinggi ikut memakan jatah max_output_tokens,
            // jadi 16000 gampang bikin satu giliran berhenti 'incomplete'
            // di tengah jalan sebelum sempat menghasilkan file.
            'max_tokens' => 32000,
            'effort' => 'high',
        ],
        self::WORD_REPORT_DOCUMENT => [
            // Model default beda dari WORD_REPORT (lihat docblock konstanta
            // ini) -- gpt-5.6-terra, bukan gpt-5.6-luna, supaya fase paling
            // berat ini punya TPM pool sendiri, terpisah dari fase 1/2 dan
            // dari assistant lain yang kebetulan dipasang ke luna juga.
            'model' => 'gpt-5.6-terra',
            'max_tokens' => 32000,
            'effort' => 'medium',
        ],
    ];

    /** Cache per-request: satu giliran chat membacanya lebih dari sekali. */
    private static ?array $resolved = null;

    /**
     * Konfigurasi yang BENAR-BENAR dipakai sebuah asisten sekarang.
     *
     * 'tier' dipertahankan di sini murni sebagai label pelacakan untuk jalur
     * yang sudah ada (AiConversation::model_tier, AuditLog::logAiPrompt) —
     * sejak preset Fast/Balanced/Reasoning/Deep research dihapus (lihat
     * docblock kelas), tidak ada lagi "tier" yang dipilih, jadi nilainya
     * sekarang adalah model yang benar-benar dipakai. Itu informasi yang
     * lebih berguna untuk menelusuri biaya ketimbang label abstrak, dan
     * mengganti nama key ini berarti mengubah 3 service AI + kolom DB yang
     * sudah ada tanpa manfaat tambahan.
     *
     * @return array{tier: string, provider: string, model: string, max_tokens: int, effort: ?string}
     */
    public static function resolve(string $assistant): array
    {
        $settings = self::all()[$assistant] ?? self::DEFAULTS[$assistant];

        return ['tier' => $settings['model']] + $settings;
    }

    /**
     * Seluruh pengaturan, sudah tersanitasi. Dipakai form admin.
     *
     * @return array<string, array{provider: string, model: string, max_tokens: int, effort: ?string}>
     */
    public static function all(): array
    {
        if (null !== self::$resolved) {
            return self::$resolved;
        }

        $stored = [];

        try {
            $stored = AppConfig::getJson(self::KEY, []);
        } catch (Throwable $e) {
            // Tabel app_configs belum ada (fresh install), DB sedang bermasalah,
            // atau isinya bukan JSON. Apa pun sebabnya, halaman AI tidak boleh
            // ikut mati — jatuh ke bawaan dan catat.
            Log::warning('AI model settings unreadable, falling back to defaults', [
                'error' => $e->getMessage(),
            ]);
        }

        return self::$resolved = self::sanitize(is_array($stored) ? $stored : []);
    }

    /**
     * Simpan pilihan admin. Input mentah dari form — sanitize() yang menjaga
     * agar yang mendarat di DB tidak pernah berupa kombinasi yang ditolak API.
     *
     * @param array<string, mixed> $input
     */
    public static function save(array $input): void
    {
        $clean = self::sanitize($input);

        AppConfig::setJson(self::KEY, $clean, 'Model AI aktif per asisten (Control Center → AI Settings)');

        self::$resolved = $clean;
    }

    /** Katalog model yang boleh dipilih oleh sebuah asisten. */
    public static function catalogFor(string $assistant): array
    {
        // Research genuinely butuh flag `server_tools` ini (web_search/web_fetch).
        // Ticket Analyzer & Word Report dipinjamkan filter yang SAMA sebagai proxy
        // kasar, tapi alasannya beda per provider — jangan disamakan kalau nanti diubah:
        //   - Sisi Anthropic: AnthropicTicketAnalysisDriver/AnthropicReportDriver
        //     butuh Agent Skills + code execution container. Belum ada konfirmasi
        //     model mana saja yang mendukungnya di luar Opus 5/Sonnet 5/Fable 5,
        //     jadi sementara ikut disaring ke katalog "server tools" biar tidak
        //     sengaja kepilih model yang belum tentu jalan (mis. Haiku 4.5).
        //   - Sisi OpenAI: OpenAiTicketAnalysisDriver TIDAK butuh web_search sama
        //     sekali — cuma butuh Structured Outputs, dan OpenAiReportDriver cuma
        //     butuh tool `code_interpreter` bawaan Responses API — keduanya
        //     didukung hampir semua model GPT modern. Filter ini kebetulan tidak
        //     menyingkirkan model apa pun di katalog OpenAI hari ini (semua entri
        //     gpt-5.6-* punya server_tools:true), tapi kalau suatu saat ada model
        //     GPT tanpa web search ditambahkan ke katalog, ia akan tersaring keluar
        //     dari Ticket Analyzer/Word Report tanpa alasan teknis yang valid.
        if (!in_array($assistant, self::NEEDS_SERVER_TOOLS, true)) {
            return self::CATALOG;
        }

        return array_filter(self::CATALOG, static fn (array $m) => $m['server_tools']);
    }

    /**
     * Apakah model ini mendukung server tool web search milik providernya?
     *
     * Satu-satunya pemakainya dulu adalah AiTicketSummaryService, yang memeriksa
     * model AI Assistant lalu diam-diam jatuh ke konfigurasi AI Research bila
     * modelnya tidak punya web search. Sejak AI Summarize punya entri sendiri
     * (TICKET_SUMMARY, dijamin ber-server-tool oleh sanitize()), pemeriksaan itu
     * tidak dibutuhkan lagi — tapi helper-nya dipertahankan karena ini satu-
     * satunya cara membaca flag `server_tools` untuk SATU model dari luar kelas
     * ini (requiresServerTools() menjawab pertanyaan berbeda: per asisten).
     */
    public static function supportsServerTools(string $model): bool
    {
        return (bool) (self::CATALOG[$model]['server_tools'] ?? false);
    }

    /**
     * Apakah asisten ini dibatasi ke model ber-server-tool?
     *
     * Dipakai form admin supaya keterangan "cuma model dengan web search yang
     * ditawarkan" muncul di baris yang memang dibatasi — tanpa menyalin lagi
     * daftar NEEDS_SERVER_TOOLS ke dalam Blade.
     */
    public static function requiresServerTools(string $assistant): bool
    {
        return in_array($assistant, self::NEEDS_SERVER_TOOLS, true);
    }

    public static function catalog(): array
    {
        return self::CATALOG;
    }

    public static function assistants(): array
    {
        return [
            self::RESEARCH => 'AI Research',
            self::INTERNAL => 'AI Assistant',
            self::TICKET_ANALYZER => 'Ticket Analyzer',
            self::TICKET_SUMMARY => 'AI Summarize (Daftar Tiket)',
            self::WORD_REPORT => 'Word Report Generator (Struktur & Data)',
            self::WORD_REPORT_DOCUMENT => 'Word Report Generator (Susun Dokumen)',
        ];
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Paksa struktur apa pun menjadi bentuk yang pasti diterima API.
     *
     * Bekerja per-asisten dari DEFAULTS, bukan dari input: asisten yang tidak
     * dikenal dibuang, field yang hilang dikembalikan ke bawaannya. Jadi tidak
     * ada bentuk input yang bisa menghasilkan konfigurasi setengah jadi.
     *
     * @param array<string, mixed> $input
     */
    private static function sanitize(array $input): array
    {
        $out = [];

        foreach (self::DEFAULTS as $assistant => $defaults) {
            $given = is_array($input[$assistant] ?? null) ? $input[$assistant] : [];

            $model = (string) ($given['model'] ?? $defaults['model']);

            // Model tak dikenal, atau model tanpa server tool untuk assistant yang
            // butuh code execution/web tool (Research, Ticket Analyzer, dan kedua
            // fase Word Report Generator -- lihat catalogFor()): kembalikan ke
            // bawaan asisten ini — bukan ke model lain yang kebetulan tersedia,
            // supaya hasilnya bisa ditebak.
            $needsServerTools = in_array($assistant, self::NEEDS_SERVER_TOOLS, true);
            if (!isset(self::CATALOG[$model])
                || ($needsServerTools && !self::CATALOG[$model]['server_tools'])) {
                $model = $defaults['model'];
            }

            $maxTokens = (int) ($given['max_tokens'] ?? $defaults['max_tokens']);
            $maxTokens = max(self::MIN_MAX_TOKENS, min($maxTokens, self::CATALOG[$model]['max_output']));

            // Effort divalidasi terhadap daftar MILIK MODEL INI, bukan satu skala
            // global — Claude dan OpenAI memakai kosakata berbeda (lihat docblock
            // kelas), dan sebagian model (Haiku 4.5) menolak parameter ini sama
            // sekali (efforts = []), bukan mengabaikan nilai yang tidak dikenal.
            $effort = $given['effort'] ?? $defaults['effort'];
            $effort = is_string($effort) && in_array($effort, self::CATALOG[$model]['efforts'], true)
                ? $effort
                : null;

            $out[$assistant] = [
                'provider' => self::CATALOG[$model]['provider'],
                'model' => $model,
                'max_tokens' => $maxTokens,
                'effort' => $effort,
            ];
        }

        return $out;
    }
}
