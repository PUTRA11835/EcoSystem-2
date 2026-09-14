<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Beta\Messages\BetaCacheControlEphemeral;
use Anthropic\Beta\Messages\BetaCodeExecutionTool20260521;
use Anthropic\Beta\Messages\BetaContainerParams;
use Anthropic\Beta\Messages\BetaSkillParams;
use Anthropic\Beta\Messages\BetaTextBlockParam;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver;
use Closure;
use RuntimeException;

/**
 * Claude side of Ticket Analyzer — Agent Skills custom "sap-ticket-analyzer"
 * + code execution container, moved here verbatim from what used to live
 * directly in AiTicketAnalyzerService::analyze() before GPT became a second
 * provider. There is no OpenAI equivalent of Agent Skills — see
 * OpenAiTicketAnalysisDriver for how that side compensates instead.
 *
 * STREAMING sejak 10 Sep 2026: dulu satu panggilan `create()` non-
 * streaming (validator menatap progress bar palsu berbasis waktu selama itu
 * berjalan — bisa menitan). Sekarang `createStream()`, murni supaya
 * AiTicketAnalyzerService bisa meneruskan status ASLI ('status' event) ke
 * StagingTicketController::analyze() → browser. Kontrak return TIDAK
 * berubah: masih satu string JSON utuh di akhir — ini respons terstruktur,
 * bukan prosa yang berguna ditampilkan sepotong-sepotong, jadi teks yang
 * mengalir HANYA diakumulasi secara internal, tidak diteruskan ke $onEvent.
 */
class AnthropicTicketAnalysisDriver implements TicketAnalysisDriver
{
    public function __construct(private Client $client)
    {
    }

    public function analyze(
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens,
        ?string $effort,
        Closure $onEvent,
        Closure $isAborted,
    ): string {
        $skillId = config('services.anthropic.ticket_analyzer_skill_id');
        if (!$skillId) {
            throw new RuntimeException('ANTHROPIC_TICKET_ANALYZER_SKILL_ID belum diatur di .env.');
        }

        $stream = $this->client->beta->messages->createStream(
            maxTokens: $maxTokens,
            model: $model,
            // Cache breakpoint: system prompt ini (instruksi + daftar modul aktif)
            // IDENTIK di setiap panggilan analyze() lintas SEMUA staging ticket —
            // beda dari user message yang isinya spesifik per tiket. Anthropic
            // cache hit memangkas biaya & latensi pemrosesan bagian ini sampai
            // ~90% selama modul aktif tidak berubah (TTL breakpoint 5 menit
            // bawaan cukup untuk validator yang memvalidasi beberapa tiket
            // berturut-turut). Tidak berefek (dan tidak error) kalau prompt-nya
            // di bawah ambang token minimum caching — cuma jadi no-op.
            system: [BetaTextBlockParam::with(text: $systemPrompt, cacheControl: BetaCacheControlEphemeral::with())],
            messages: [['role' => 'user', 'content' => $userMessage]],
            outputConfig: $effort ? ['effort' => $effort] : null,
            container: BetaContainerParams::with(skills: [
                BetaSkillParams::with(skillID: $skillId, type: 'custom', version: 'latest'),
            ]),
            tools: [BetaCodeExecutionTool20260521::with()],
            betas: ['code-execution-2025-08-25', 'skills-2025-10-02'],
        );

        $full = '';
        $sawText = false;

        foreach ($stream as $event) {
            if ($isAborted()) {
                $stream->close();
                throw new RuntimeException('Analisa dibatalkan (koneksi ditutup).');
            }

            if ('content_block_start' === $event->type) {
                $this->emitProgress($event->contentBlock, $onEvent, $sawText);
            } elseif ('content_block_delta' === $event->type && $event->delta instanceof BetaTextDelta) {
                $full .= $event->delta->text;
            }
        }

        if ('' === trim($full)) {
            throw new RuntimeException('Claude tidak mengembalikan teks analisa.');
        }

        return $full;
    }

    /**
     * Status ASLI dari event stream (bukan persentase karangan) — tiga type
     * block yang dikenali dikonfirmasi langsung dari SDK, bukan tebakan:
     * 'server_tool_use' dengan name 'code_execution' cocok persis dengan
     * BetaCodeExecutionTool20260521::$name, 'code_execution_tool_result'
     * cocok BetaCodeExecutionToolResultBlock::$type, 'text' blok jawaban
     * biasa. Type lain (kalau suatu saat SDK menambah varian baru) diam
     * saja — lebih baik status tidak berubah daripada menampilkan label
     * yang salah.
     */
    private function emitProgress(object $block, Closure $onEvent, bool &$sawText): void
    {
        $type = $block->type ?? '';

        if ('server_tool_use' === $type && 'code_execution' === ($block->name ?? '')) {
            $onEvent('status', ['label' => 'Running code execution…']);
        } elseif ('code_execution_tool_result' === $type) {
            $onEvent('status', ['label' => 'Reading code execution result…']);
        } elseif ('text' === $type && !$sawText) {
            $sawText = true;
            $onEvent('status', ['label' => 'Writing analysis…']);
        }
    }
}
