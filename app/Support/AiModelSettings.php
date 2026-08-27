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
 * DUA HAL YANG TIDAK BOLEH DISERAHKAN KE ADMIN — ditegakkan di sanitize():
 *
 *   1. AI Research HARUS memakai model yang mendukung server tool
 *      web_search_20260209 / web_fetch_20260209 (Opus 5 / Sonnet 5 ke atas).
 *      Haiku 4.5 TIDAK mendukungnya; kalau sampai terpilih, halaman Research
 *      mati total dengan 400 dari API. Karena itu katalognya disaring
 *      per-asisten, bukan satu daftar global.
 *
 *   2. `effort` (output_config) DITOLAK oleh Haiku 4.5 — parameternya error,
 *      bukan diabaikan. Jadi effort dipaksa null untuk model yang tidak
 *      mendukungnya, berapa pun yang tersimpan di DB.
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

    /**
     * Model yang boleh dipilih. Daftar TERTUTUP — bukan input teks bebas,
     * supaya salah ketik ("claude-sonet-5") ketahuan di form, bukan saat user
     * pertama menekan Enter dan API menjawab 404.
     *
     * - server_tools : mendukung web_search/web_fetch varian _20260209
     * - effort       : menerima output_config.effort (Haiku 4.5 menolaknya)
     * - max_output   : plafon max_tokens milik model itu sendiri
     * - price_in/out : USD per 1 juta token, untuk ditampilkan di form
     */
    private const CATALOG = [
        'claude-opus-5' => [
            'label' => 'Claude Opus 5',
            'server_tools' => true,
            'effort' => true,
            'max_output' => 128000,
            'context' => '1M',
            'price_in' => 5.0,
            'price_out' => 25.0,
            'note' => 'Paling kuat, paling mahal — 5× harga Haiku per token masuk.',
        ],
        'claude-sonnet-5' => [
            'label' => 'Claude Sonnet 5',
            'server_tools' => true,
            'effort' => true,
            'max_output' => 128000,
            'context' => '1M',
            'price_in' => 3.0,
            'price_out' => 15.0,
            'note' => 'Keseimbangan mutu dan biaya; pilihan baku untuk keduanya.',
        ],
        'claude-haiku-4-5' => [
            'label' => 'Claude Haiku 4.5',
            'server_tools' => false,
            'effort' => false,
            'max_output' => 64000,
            'context' => '200K',
            'price_in' => 1.0,
            'price_out' => 5.0,
            'note' => 'Termurah dan tercepat. TIDAK bisa dipakai AI Research (tanpa web search).',
        ],
    ];

    private const EFFORTS = ['low', 'medium', 'high', 'xhigh', 'max'];

    /** Plafon max_tokens terendah yang masih masuk akal untuk sebuah jawaban. */
    private const MIN_MAX_TOKENS = 512;

    /**
     * Keadaan awal — sama persis dengan angka yang dulu hardcoded di kedua
     * service, supaya migrasi ini tidak mengubah perilaku apa pun sampai
     * admin benar-benar menyentuh formnya.
     */
    private const DEFAULTS = [
        self::RESEARCH => [
            'active' => 'default',
            'tiers' => [
                'default' => [
                    'label' => 'Balanced',
                    'model' => 'claude-sonnet-5',
                    'max_tokens' => 32000,
                    'effort' => 'medium',
                ],
                'deep' => [
                    'label' => 'Deep research',
                    'model' => 'claude-opus-5',
                    'max_tokens' => 64000,
                    'effort' => 'high',
                ],
            ],
        ],
        self::INTERNAL => [
            'active' => 'default',
            'tiers' => [
                'fast' => [
                    'label' => 'Fast',
                    'model' => 'claude-haiku-4-5',
                    'max_tokens' => 2048,
                    'effort' => null,
                ],
                'default' => [
                    'label' => 'Balanced',
                    'model' => 'claude-sonnet-5',
                    'max_tokens' => 4096,
                    'effort' => 'medium',
                ],
                'reasoning' => [
                    'label' => 'Reasoning',
                    'model' => 'claude-opus-5',
                    'max_tokens' => 8000,
                    'effort' => 'high',
                ],
            ],
        ],
    ];

    /** Cache per-request: satu giliran chat membacanya lebih dari sekali. */
    private static ?array $resolved = null;

    /**
     * Konfigurasi yang BENAR-BENAR dipakai sebuah asisten sekarang.
     *
     * @return array{tier: string, label: string, model: string, max_tokens: int, effort: ?string}
     */
    public static function resolve(string $assistant): array
    {
        $settings = self::all()[$assistant] ?? self::DEFAULTS[$assistant];
        $tier = $settings['active'];

        return ['tier' => $tier] + $settings['tiers'][$tier];
    }

    /**
     * Seluruh pengaturan, sudah tersanitasi. Dipakai form admin.
     *
     * @return array<string, array{active: string, tiers: array<string, array>}>
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
        if (self::RESEARCH !== $assistant) {
            return self::CATALOG;
        }

        return array_filter(self::CATALOG, static fn (array $m) => $m['server_tools']);
    }

    /**
     * Apakah model ini mendukung server tool web_search/web_fetch (_20260209)?
     *
     * Dipakai fitur yang WAJIB mencari ke luar — AI Research, dan AI Summarize
     * pada daftar tiket — untuk memastikan pilihan admin tidak menjatuhkan
     * fiturnya dengan 400 dari API.
     */
    public static function supportsServerTools(string $model): bool
    {
        return (bool) (self::CATALOG[$model]['server_tools'] ?? false);
    }

    public static function catalog(): array
    {
        return self::CATALOG;
    }

    /** @return string[] */
    public static function efforts(): array
    {
        return self::EFFORTS;
    }

    public static function assistants(): array
    {
        return [
            self::RESEARCH => 'AI Research',
            self::INTERNAL => 'AI Assistant',
        ];
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Paksa struktur apa pun menjadi bentuk yang pasti diterima API.
     *
     * Bekerja per-tier dari DEFAULTS, bukan dari input: tier yang tidak dikenal
     * dibuang, tier yang hilang dikembalikan ke bawaannya. Jadi tidak ada
     * bentuk input yang bisa menghasilkan konfigurasi setengah jadi.
     *
     * @param array<string, mixed> $input
     */
    private static function sanitize(array $input): array
    {
        $out = [];

        foreach (self::DEFAULTS as $assistant => $defaults) {
            $given = is_array($input[$assistant] ?? null) ? $input[$assistant] : [];
            $givenTiers = is_array($given['tiers'] ?? null) ? $given['tiers'] : [];

            $tiers = [];

            foreach ($defaults['tiers'] as $tierKey => $tierDefault) {
                $tier = is_array($givenTiers[$tierKey] ?? null) ? $givenTiers[$tierKey] : [];

                $model = (string) ($tier['model'] ?? $tierDefault['model']);

                // Model tak dikenal, atau model tanpa server tool untuk Research:
                // kembalikan ke bawaan tier ini — bukan ke model lain yang
                // kebetulan tersedia, supaya hasilnya bisa ditebak.
                if (!isset(self::CATALOG[$model])
                    || (self::RESEARCH === $assistant && !self::CATALOG[$model]['server_tools'])) {
                    $model = $tierDefault['model'];
                }

                $maxTokens = (int) ($tier['max_tokens'] ?? $tierDefault['max_tokens']);
                $maxTokens = max(self::MIN_MAX_TOKENS, min($maxTokens, self::CATALOG[$model]['max_output']));

                $effort = $tier['effort'] ?? $tierDefault['effort'];
                $effort = is_string($effort) && in_array($effort, self::EFFORTS, true) ? $effort : null;

                // Haiku 4.5 MENOLAK output_config.effort — bukan mengabaikannya.
                if (!self::CATALOG[$model]['effort']) {
                    $effort = null;
                }

                $tiers[$tierKey] = [
                    'label' => $tierDefault['label'],
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'effort' => $effort,
                ];
            }

            $active = (string) ($given['active'] ?? $defaults['active']);

            if (!isset($tiers[$active])) {
                $active = $defaults['active'];
            }

            $out[$assistant] = ['active' => $active, 'tiers' => $tiers];
        }

        return $out;
    }
}
