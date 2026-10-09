<?php

namespace App\Services\Ai;

use App\Models\AiUsageLog;
use App\Support\AiModelSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mencatat pemakaian token tiap panggilan API AI untuk halaman Control Center →
 * AI Usage.
 *
 * Dipanggil dari driver (satu-satunya tempat angka usage dari provider terlihat).
 * TIDAK PERNAH melempar: gagal mencatat tidak boleh menjatuhkan jawaban AI yang
 * sudah berhasil dibuat dan sudah dibayar.
 *
 * Biaya adalah ESTIMASI dari harga katalog AiModelSettings (USD per 1 juta
 * token). Tarif cache mengikuti skema Anthropic: baca 0,1x dan tulis 1,25x
 * harga input. Biaya tool server (web search, code execution) tidak ikut
 * dihitung karena API tidak melaporkannya sebagai token.
 */
final class AiUsageRecorder
{
    private const CACHE_READ_MULTIPLIER = 0.1;
    private const CACHE_WRITE_MULTIPLIER = 1.25;

    /**
     * @param int $inputTokens Token input TANPA bagian cache (konvensi Anthropic).
     */
    public static function record(
        string $provider,
        string $model,
        string $source,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
    ): void {
        if (0 === $inputTokens + $outputTokens + $cacheReadTokens + $cacheWriteTokens) {
            return;
        }

        try {
            AiUsageLog::create([
                'provider' => $provider,
                'model' => $model,
                'source' => $source,
                'employee_id' => self::currentEmployeeId(),
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'cache_read_tokens' => max(0, $cacheReadTokens),
                'cache_write_tokens' => max(0, $cacheWriteTokens),
                'cost_usd' => self::estimateCost($model, $inputTokens, $outputTokens, $cacheReadTokens, $cacheWriteTokens),
            ]);
        } catch (Throwable $e) {
            Log::warning('AI usage could not be recorded', ['error' => $e->getMessage(), 'model' => $model]);
        }
    }

    /** Anthropic: objek usage (Messages\Usage / MessageDeltaUsage, atau varian Beta). */
    public static function recordAnthropic(string $model, string $source, ?object $usage): void
    {
        if (null === $usage) {
            return;
        }

        self::record(
            'anthropic',
            $model,
            $source,
            (int) ($usage->inputTokens ?? 0),
            (int) ($usage->outputTokens ?? 0),
            (int) ($usage->cacheReadInputTokens ?? 0),
            (int) ($usage->cacheCreationInputTokens ?? 0),
        );
    }

    /**
     * Anthropic streaming: message_start membawa input + cache, message_delta
     * membawa output kumulatif (dan kadang input terbaru, yang menang bila ada).
     */
    public static function recordAnthropicStream(string $model, string $source, ?object $startUsage, ?object $deltaUsage): void
    {
        if (null === $startUsage && null === $deltaUsage) {
            return;
        }

        self::record(
            'anthropic',
            $model,
            $source,
            (int) ($deltaUsage->inputTokens ?? $startUsage->inputTokens ?? 0),
            (int) ($deltaUsage->outputTokens ?? $startUsage->outputTokens ?? 0),
            (int) ($deltaUsage->cacheReadInputTokens ?? $startUsage->cacheReadInputTokens ?? 0),
            (int) ($deltaUsage->cacheCreationInputTokens ?? $startUsage->cacheCreationInputTokens ?? 0),
        );
    }

    /** OpenAI Responses API: CreateResponseUsage. input_tokens sudah TERMASUK cached_tokens. */
    public static function recordOpenAi(string $model, string $source, ?object $usage): void
    {
        if (null === $usage) {
            return;
        }

        $cached = (int) ($usage->inputTokensDetails->cachedTokens ?? 0);

        self::record(
            'openai',
            $model,
            $source,
            max(0, (int) $usage->inputTokens - $cached),
            (int) $usage->outputTokens,
            $cached,
        );
    }

    public static function estimateCost(
        string $model,
        int $input,
        int $output,
        int $cacheRead = 0,
        int $cacheWrite = 0,
    ): float {
        $price = AiModelSettings::catalog()[$model] ?? null;

        if (null === $price) {
            return 0.0;
        }

        $in = $price['price_in'];

        return (
            $input * $in
            + $cacheRead * $in * self::CACHE_READ_MULTIPLIER
            + $cacheWrite * $in * self::CACHE_WRITE_MULTIPLIER
            + $output * $price['price_out']
        ) / 1_000_000;
    }

    private static function currentEmployeeId(): ?int
    {
        try {
            $user = session('user');
        } catch (Throwable) {
            return null;
        }

        return is_array($user) && 'employee' === ($user['type'] ?? null) && isset($user['id'])
            ? (int) $user['id']
            : null;
    }
}
