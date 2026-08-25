<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\WebFetchTool20260209;
use Anthropic\Messages\WebSearchTool20260209;
use App\Services\Ai\Drivers\Contracts\ResearchDriver;
use Closure;

/**
 * Claude side of AiResearchService's turn — the server-tool (web_search/
 * web_fetch) loop that used to live directly in AiResearchService before GPT
 * became a second provider. Owns Claude's 'pause_turn' resume loop entirely
 * internally (see ResearchDriver's docblock for why that can't leak out).
 */
class AnthropicResearchDriver implements ResearchDriver
{
    /** Batas berapa kali 'pause_turn' boleh dilanjutkan dalam satu giliran. */
    private const MAX_PAUSE_RESUMES = 4;

    /** Batas pemakaian tiap server tool per request (biaya + waktu tunggu). */
    private const MAX_WEB_USES = 6;

    public function __construct(private Client $client)
    {
    }

    public function ask(
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens,
        ?string $effort,
        Closure $onDelta,
        Closure $onEvent,
        Closure $isAborted,
    ): array {
        $pauseResumes = 0;
        $sources = [];

        while (true) {
            if ($isAborted()) {
                return [null, null, $sources];
            }

            $stream = $this->client->messages->createStream(
                maxTokens: $maxTokens,
                messages: $messages,
                model: $model,
                system: $systemPrompt,
                thinking: ['type' => 'adaptive'],
                outputConfig: $effort ? ['effort' => $effort] : null,
                tools: $this->toolDefinitions(),
            );

            // MessageAccumulator melipat event stream kembali menjadi Message utuh.
            // Ini penting di sini: blok hasil server tool (web_search_tool_result)
            // harus dikirim balik apa adanya pada giliran berikutnya DALAM loop
            // pause_turn ini, dan menyusunnya manual dari event mentah rapuh —
            // biarkan SDK yang mengerjakan.
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
                return [null, null, $sources];
            }

            $message = $accumulator->message();

            // Objek SDK apa adanya — HANYA hidup di memori selama loop pause_turn
            // ini, tidak pernah keluar dari method ini. Untuk melanjutkan
            // 'pause_turn', giliran assistant harus dikirim balik utuh, dan objek
            // aslinya adalah bentuk paling setia. Yang dikembalikan ke pemanggil
            // (AiResearchService) adalah teks bersih dari toCanonicalTextBlocks(),
            // bukan ini.
            $messages[] = ['role' => 'assistant', 'content' => $message->content];
            $sources = $this->collectSources($message, $sources);

            $stopReason = $message->stopReason;

            if ('pause_turn' === $stopReason) {
                if (++$pauseResumes > self::MAX_PAUSE_RESUMES) {
                    $onEvent('notice', [
                        'kind' => 'search_limit',
                        'title' => 'The search ran longer than one turn allows',
                        'text' => 'This lookup paused ' . self::MAX_PAUSE_RESUMES . ' times and still is not finished, '
                            . 'so it was stopped here. Continue to let it carry on, or narrow the question.',
                        'can_continue' => true,
                    ]);

                    return [$this->toCanonicalTextBlocks($message), 'end_turn', $sources];
                }

                $onEvent('status', ['label' => 'Resuming the search…']);
                continue;
            }

            $normalized = 'max_tokens' === $stopReason ? 'max_tokens' : 'end_turn';

            return [$this->toCanonicalTextBlocks($message), $normalized, $sources];
        }
    }

    /**
     * Teruskan teks jawaban + tanda "sedang mencari" ke UI selagi stream berjalan.
     *
     * Blok thinking sengaja diabaikan: display-nya 'omitted' (bawaan), jadi
     * teksnya kosong dan tidak ada yang bisa ditampilkan.
     */
    private function emitProgress(object $event, Closure $onDelta, Closure $onEvent): void
    {
        switch ($event->type) {
            case 'content_block_start':
                $block = $event->contentBlock;

                switch ($block->type ?? '') {
                    case 'server_tool_use':
                        $onEvent('status', ['label' => 'web_fetch' === ($block->name ?? '')
                            ? 'Opening the source page…'
                            : 'Searching the web…']);
                        break;

                    case 'web_search_tool_result':
                    case 'web_fetch_tool_result':
                        $onEvent('status', ['label' => 'Reading the results…']);
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
     * Kumpulkan URL sumber dari blok hasil server tool giliran INI saja —
     * dedup lintas giliran jadi tanggung jawab AiResearchService (dia yang
     * pegang akumulator lintas panggilan ask()).
     *
     * Catatan bentuk data: pada web_search, `content` berisi ARRAY hasil saat
     * sukses, tapi berubah jadi OBJEK error (mis. max_uses_exceeded) saat gagal —
     * server tool tidak melempar exception, errornya ikut di body 200.
     *
     * @param array<int, array{url: string, title: string}> $sources
     * @return array<int, array{url: string, title: string}>
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
                            $sources[] = ['url' => $url, 'title' => ($result->title ?? null) ?: $url];
                        }
                    }
                    break;

                case 'web_fetch_tool_result':
                    $result = $block->content ?? null;
                    $url = is_object($result) ? ($result->url ?? null) : null;
                    if ($url) {
                        $sources[] = ['url' => $url, 'title' => $url];
                    }
                    break;
            }
        }

        return $sources;
    }

    /**
     * Ringkas giliran assistant menjadi blok teks kanonik saja — blok kerja
     * server tool (server_tool_use, web_search_tool_result) dan thinking
     * TIDAK ikut: giliran berikutnya (across ask() calls, bukan dalam loop
     * pause_turn) tidak membutuhkannya, dan bentuk union-nya tidak round-trip
     * dengan andal lewat cache/arsip (lihat AiResearchService::toHistory()).
     *
     * @return array<int, array{type: string, text: string}>
     */
    private function toCanonicalTextBlocks(object $message): array
    {
        $blocks = [];

        foreach ($message->content as $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : ($block->type ?? null);
            if ('text' !== $type) {
                continue;
            }

            $text = (string) (is_array($block) ? ($block['text'] ?? '') : ($block->text ?? ''));
            if ('' !== trim($text)) {
                $blocks[] = ['type' => 'text', 'text' => $text];
            }
        }

        return $blocks;
    }

    /**
     * Server tool milik Anthropic — dieksekusi di sisi mereka, kita cukup
     * mendeklarasikannya. Sengaja memakai kelas typed, bukan array biasa:
     * ToolUnion di SDK adalah union TANPA discriminator — array polos
     * dicocokkan ke varian pertama yang "muat".
     *
     * citations pada web_fetch sengaja TIDAK diaktifkan — lihat catatan
     * historis di dokumen fitur; daftar rujukan tetap ada lewat collectSources().
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
}
