<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Drivers\Concerns\TranslatesCanonicalMessages;
use App\Services\Ai\Drivers\Contracts\ResearchDriver;
use Closure;
use OpenAI\Client;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputText;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputTextAnnotationsUrlCitation;
use RuntimeException;

/**
 * GPT side of AiResearchService's turn, via the Responses API's built-in
 * `web_search` tool. Unlike Claude, OpenAI has no 'pause_turn' concept — a
 * turn either completes or comes back 'incomplete' (hit max_output_tokens) —
 * so there is no internal resume loop here, just one request per ask() call.
 */
class OpenAiResearchDriver implements ResearchDriver
{
    use TranslatesCanonicalMessages;

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
        $parameters = [
            'model' => $model,
            'instructions' => $systemPrompt,
            'input' => $this->toResponsesInput($messages),
            'max_output_tokens' => $maxTokens,
            // Sama alasannya dengan OpenAiChatDriver: percakapan AI Research
            // TIDAK PERNAH masuk database di sisi kita — store:false menjaga
            // janji yang sama berlaku di sisi OpenAI.
            'store' => false,
            'tools' => [
                ['type' => 'web_search'],
            ],
        ];

        if ($effort) {
            $parameters['reasoning'] = ['effort' => $effort];
        }

        $stream = $this->client->responses()->createStreamed($parameters);

        foreach ($stream as $event) {
            if ($isAborted()) {
                return [null, null, []];
            }

            switch ($event->event) {
                case 'response.output_text.delta':
                    $onDelta($event->response->delta);
                    break;

                case 'response.web_search_call.in_progress':
                case 'response.web_search_call.searching':
                    $onEvent('status', ['label' => 'Searching the web…']);
                    break;

                case 'response.web_search_call.completed':
                    $onEvent('status', ['label' => 'Reading the results…']);
                    break;

                case 'response.completed':
                case 'response.incomplete':
                case 'response.failed':
                    return $this->finalize($event->response->response);

                case 'error':
                    throw new RuntimeException('OpenAI stream error: ' . $event->response->message);
            }
        }

        // Stream ended without a terminal event (connection dropped mid-flight).
        return [null, null, []];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: array<int, array{url: string, title: string}>}
     */
    private function finalize(CreateResponse $response): array
    {
        if ('failed' === $response->status) {
            throw new RuntimeException('OpenAI response failed: ' . ($response->error?->message ?? 'unknown error'));
        }

        $content = [];
        $sources = [];

        foreach ($response->output as $item) {
            if (!$item instanceof OutputMessage) {
                continue;
            }

            foreach ($item->content as $part) {
                if (!$part instanceof OutputMessageContentOutputText) {
                    continue;
                }

                if ('' !== trim($part->text)) {
                    $content[] = ['type' => 'text', 'text' => $part->text];
                }

                foreach ($part->annotations as $annotation) {
                    if ($annotation instanceof OutputMessageContentOutputTextAnnotationsUrlCitation) {
                        $sources[] = ['url' => $annotation->url, 'title' => $annotation->title ?: $annotation->url];
                    }
                }
            }
        }

        $stopReason = 'incomplete' === $response->status ? 'max_tokens' : 'end_turn';

        return [$content, $stopReason, $sources];
    }
}
