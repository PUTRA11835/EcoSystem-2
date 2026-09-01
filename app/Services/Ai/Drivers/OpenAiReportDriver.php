<?php

namespace App\Services\Ai\Drivers;

use App\Models\Employee;
use App\Services\Ai\Drivers\Contracts\ReportGenerationDriver;
use App\Services\Ai\Tools\AiTool;
use OpenAI\Client;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\Output\CodeInterpreter\CodeFileOutput;
use OpenAI\Responses\Responses\Output\OutputCodeInterpreterToolCall;
use OpenAI\Responses\Responses\Output\OutputFunctionToolCall;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputText;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputTextAnnotationsContainerFile;
use RuntimeException;
use Throwable;

/**
 * GPT side of Word Report Generator. OpenAI has no Agent Skills equivalent
 * (lihat AnthropicReportDriver) -- instruksi skill sudah di-inline oleh
 * ReportGeneratorService langsung ke dalam $prompt tiap fase (dulu driver
 * ini yang membaca+strip SKILL.md sendiri lewat buildInstructions(); sekarang
 * dipusatkan di service supaya dipakai kedua provider dari satu tempat).
 *
 * code_interpreter (setara code execution container-nya Claude) dipakai
 * HANYA di assembleDocument() (fase 3) -- extractStructure() dan pullData()
 * adalah panggilan Responses API biasa tanpa tool sandbox sama sekali,
 * supaya dua fase ringan itu tetap murah & cepat.
 *
 * $tools (AiTool registry) SAMA PERSIS dengan yang dipakai driver Claude —
 * cuma diterjemahkan ke bentuk `function` tool milik Responses API di sini.
 */
class OpenAiReportDriver implements ReportGenerationDriver
{
    private const MAX_TOOL_ITERATIONS = 12;

    /** Fase 2 (tarik data) cuma tool-calling murni, tidak butuh sebanyak fase dokumen. */
    private const MAX_DATA_ITERATIONS = 8;

    /**
     * Rate limit TPM OpenAI adalah jendela 60 detik yang BERGULIR: token dari
     * panggilan sebelumnya (mis. fase 2 yang baru selesai di job yang sama)
     * keluar dari hitungan setelah semenit, jadi menunggu sebentar lalu
     * mengulang panggilan yang SAMA hampir selalu berhasil -- jauh lebih halus
     * daripada menggagalkan seluruh job dan menyuruh user menekan tombol.
     * Baru menyerah (lempar RuntimeException, previous = RateLimitException
     * supaya GenerateWordReportJob bisa menandainya "paused") setelah ini habis.
     */
    private const RATE_LIMIT_MAX_RETRIES = 3;

    /** Plafon jeda per percobaan -- supaya satu panggilan tidak menggantung job sampai timeout (900s). */
    private const RATE_LIMIT_MAX_WAIT_SECONDS = 12;

    public function __construct(private Client $client)
    {
    }

    public function extractStructure(
        string $model,
        ?string $effort,
        int $maxTokens,
        string $templateAbsolutePath,
        string $templateFilename,
        string $prompt,
    ): array {
        $fileId = $this->uploadTemplate($templateAbsolutePath, $templateFilename);

        $parameters = [
            'model' => $model,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_file', 'file_id' => $fileId],
                    ['type' => 'input_text', 'text' => $prompt],
                ],
            ]],
            'max_output_tokens' => $maxTokens,
            'store' => false,
        ];
        if ($effort) {
            $parameters['reasoning'] = ['effort' => $effort];
        }

        $response = $this->createWithRateLimitRetry($parameters);

        if ('completed' !== $response->status) {
            throw new RuntimeException($this->describeIncomplete($response));
        }

        $text = $response->outputText;
        $structure = $this->parseJsonObject($text);

        if (null === $structure) {
            // Bukan JSON valid -> anggap teks ini pertanyaan klarifikasi
            // (SKILL.md Tahap 1 bisa berhenti kalau template terlalu ambigu).
            return ['structure' => null, 'question' => $text];
        }

        return ['structure' => $structure, 'question' => null];
    }

    public function pullData(
        Employee $employee,
        string $model,
        ?string $effort,
        int $maxTokens,
        string $prompt,
        array $tools,
    ): array {
        $input = [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $prompt]]]];
        $toolDefs = array_map(fn (AiTool $tool) => $this->translateTool($tool->definition()), array_values($tools));

        $finalText = null;
        $pulledData = [];

        for ($i = 0; $i < self::MAX_DATA_ITERATIONS; ++$i) {
            $parameters = [
                'model' => $model,
                'input' => $input,
                'tools' => $toolDefs,
                'max_output_tokens' => $maxTokens,
                'store' => false,
            ];
            if ($effort) {
                $parameters['reasoning'] = ['effort' => $effort];
            }

            $response = $this->createWithRateLimitRetry($parameters);

            if ('completed' !== $response->status) {
                throw new RuntimeException($this->describeIncomplete($response));
            }

            foreach ($response->output as $item) {
                $arr = $item->toArray();
                unset($arr['status']);
                $input[] = $arr;
            }

            $functionCalls = array_values(array_filter(
                $response->output,
                fn ($item) => $item instanceof OutputFunctionToolCall,
            ));

            if (empty($functionCalls)) {
                $finalText = $response->outputText;
                break;
            }

            foreach ($functionCalls as $call) {
                [$output, $collected] = $this->runToolAndCollect($employee, $call, $tools);
                $input[] = $output;
                if (null !== $collected) {
                    $pulledData[] = $collected;
                }
            }
        }

        if (empty($pulledData)) {
            // Tidak ada tool yang berhasil dipanggil, tapi ada teks -> ini
            // pertanyaan klarifikasi (SKILL.md Tahap 2 poin 5), bukan kegagalan.
            return ['data' => [], 'question' => $finalText];
        }

        return ['data' => $pulledData, 'question' => null];
    }

    public function assembleDocument(
        string $model,
        ?string $effort,
        int $maxTokens,
        string $templateAbsolutePath,
        string $templateFilename,
        string $prompt,
    ): array {
        $fileId = $this->uploadTemplate($templateAbsolutePath, $templateFilename);

        $input = [[
            'role' => 'user',
            'content' => [
                ['type' => 'input_file', 'file_id' => $fileId],
                ['type' => 'input_text', 'text' => $prompt],
            ],
        ]];

        $toolDefs = [['type' => 'code_interpreter', 'container' => ['type' => 'auto', 'file_ids' => [$fileId]]]];

        $finalText = null;
        /** @var array<string, array<string, string>> $filesByContainer container_id => [file_id => mime_type] */
        $filesByContainer = [];

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; ++$i) {
            $parameters = [
                'model' => $model,
                'input' => $input,
                'tools' => $toolDefs,
                'max_output_tokens' => $maxTokens,
                'store' => false,
            ];
            if ($effort) {
                $parameters['reasoning'] = ['effort' => $effort];
            }

            $response = $this->createWithRateLimitRetry($parameters);

            if ('completed' !== $response->status) {
                throw new RuntimeException($this->describeIncomplete($response));
            }

            // Sertakan seluruh output apa adanya (termasuk item reasoning/
            // code_interpreter_call) sebagai giliran berikutnya -- 'store' => false
            // berarti tidak bisa pakai previous_response_id, jadi riwayat penuh
            // harus dikirim ulang setiap giliran. 'status' dibuang: itu field
            // output-only (mis. "completed") -- API menolaknya di sisi input
            // ("Unknown parameter: 'input[N].status'").
            foreach ($response->output as $item) {
                $arr = $item->toArray();
                unset($arr['status']);
                $input[] = $arr;
            }

            foreach ($response->output as $item) {
                if ($item instanceof OutputCodeInterpreterToolCall && $item->outputs) {
                    foreach ($item->outputs as $out) {
                        if ($out instanceof CodeFileOutput) {
                            foreach ($out->files as $f) {
                                $filesByContainer[$item->containerId][$f->fileId] = $f->mimeType;
                            }
                        }
                    }
                }

                // File yang model SEBUT di teks jawabannya (mis. "[Unduh
                // file](sandbox:/mnt/data/laporan.docx)") muncul sebagai
                // annotation `container_file_citation` pada output_text, BUKAN
                // (cuma) lewat outputs di atas -- tanpa scan ini, dokumen yang
                // sebenarnya SUDAH jadi keliru dianggap "tidak ada file" dan
                // teks ringkasannya (yang justru bilang "Selesai") disalah-
                // artikan sebagai pertanyaan klarifikasi (lihat SKILL.md Tahap
                // 2 poin 5 -- heuristik "teks tanpa file = pertanyaan").
                if ($item instanceof OutputMessage) {
                    foreach ($item->content as $content) {
                        if (!$content instanceof OutputMessageContentOutputText) {
                            continue;
                        }

                        foreach ($content->annotations as $annotation) {
                            if (!$annotation instanceof OutputMessageContentOutputTextAnnotationsContainerFile) {
                                continue;
                            }

                            $mimeType = $this->mimeFromFilename($annotation->filename);
                            if ($mimeType) {
                                $filesByContainer[$annotation->containerId][$annotation->fileId] = $mimeType;
                            }
                        }
                    }
                }
            }

            $functionCalls = array_values(array_filter(
                $response->output,
                fn ($item) => $item instanceof OutputFunctionToolCall,
            ));

            if (empty($functionCalls)) {
                $finalText = $response->outputText;
                break;
            }

            // assembleDocument() tidak declare tool `function` apa pun (cuma
            // code_interpreter) -- harusnya tidak pernah sampai sini, tapi
            // jaga-jaga supaya loop tidak macet kalau model tetap mencoba.
            foreach ($functionCalls as $call) {
                $input[] = [
                    'type' => 'function_call_output',
                    'call_id' => $call->callId,
                    'output' => json_encode(['error' => "Unexpected tool call in document phase: {$call->name}"]),
                ];
            }
        }

        if (null === $finalText) {
            throw new RuntimeException('Generate laporan (GPT) melebihi batas iterasi tool-calling (' . self::MAX_TOOL_ITERATIONS . 'x) tanpa hasil akhir.');
        }

        if (empty($filesByContainer)) {
            // Berhenti tanpa file, tapi ada teks -> ini pertanyaan klarifikasi
            // (SKILL.md Tahap 2 poin 5), bukan kegagalan.
            return ['summary' => null, 'docx' => null, 'pdf' => null, 'question' => $finalText];
        }

        return [
            'question' => null,
            'summary' => $finalText,
            ...$this->downloadGeneratedFiles($filesByContainer),
        ];
    }

    /**
     * Panggil Responses API, tapi telan RateLimitException dan ulangi setelah
     * jeda singkat (lihat RATE_LIMIT_MAX_RETRIES) -- mayoritas rate limit di
     * sini adalah lonjakan TPM sesaat (fase sebelumnya di job yang sama, atau
     * asisten lain di model yang sama) yang reda dalam hitungan detik. Kalau
     * sampai kehabisan percobaan, lempar RuntimeException dengan
     * RateLimitException sebagai `previous` -- GenerateWordReportJob membaca itu
     * untuk menandai laporan "paused" (bisa dilanjutkan), bukan "failed".
     *
     * @param array<string, mixed> $parameters
     */
    private function createWithRateLimitRetry(array $parameters): CreateResponse
    {
        $attempt = 0;

        while (true) {
            try {
                return $this->client->responses()->create($parameters);
            } catch (RateLimitException $e) {
                if (++$attempt > self::RATE_LIMIT_MAX_RETRIES) {
                    throw new RuntimeException($this->describeRateLimit($e), previous: $e);
                }

                $header = $e->response->getHeaderLine('retry-after');
                $suggested = is_numeric($header) ? (float) $header : 0.0;
                // Kalau API tidak menyebut retry-after, naik bertahap: 3s, 6s, 9s.
                $wait = min($suggested > 0 ? $suggested : 3.0 * $attempt, self::RATE_LIMIT_MAX_WAIT_SECONDS);

                usleep((int) (($wait + 0.5) * 1_000_000));
            }
        }
    }

    /**
     * OpenAI\Exceptions\RateLimitException cuma bawa pesan generik ("Request
     * rate limit has been exceeded.") -- detail sesungguhnya (limit request/
     * menit vs token/menit vs kuota habis, kapan boleh coba lagi) ada di body
     * JSON + header respons HTTP-nya, tapi SDK tidak membacanya. Baca manual
     * di sini supaya error yang sampai ke user cukup jelas untuk ditindaklanjuti.
     */
    private function describeRateLimit(RateLimitException $e): string
    {
        $response = $e->response;
        $body = json_decode((string) $response->getBody(), true);
        $detail = $body['error']['message'] ?? $body['error']['type'] ?? null;

        $retryAfter = $response->getHeaderLine('retry-after');
        $retryNote = $retryAfter !== '' ? " Coba lagi setelah ~{$retryAfter} detik." : '';

        return $detail
            ? "Rate limit OpenAI: {$detail}{$retryNote}"
            : "Rate limit OpenAI terlampaui (tidak ada detail tambahan dari API).{$retryNote}";
    }

    /**
     * `status: incomplete` tidak menjelaskan alasannya lewat pesan biasa --
     * alasan sesungguhnya (mis. kehabisan max_output_tokens di tengah
     * giliran, atau kena content filter) ada di `incomplete_details.reason`.
     */
    private function describeIncomplete(CreateResponse $response): string
    {
        $reason = $response->incompleteDetails?->reason;

        if ('max_output_tokens' === $reason) {
            return 'GPT berhenti karena kehabisan jatah output token (max_output_tokens) di tengah giliran -- '
                . 'naikkan batasnya di Control Center → AI Settings → Word Report Generator.';
        }

        return $reason
            ? "GPT report generation tidak selesai (status: {$response->status}, alasan: {$reason})."
            : "GPT report generation tidak selesai (status: {$response->status}).";
    }

    private function uploadTemplate(string $absolutePath, string $filename): string
    {
        $resource = fopen($absolutePath, 'r');
        if (false === $resource) {
            throw new RuntimeException("Gagal membaca file template di {$absolutePath}.");
        }

        $file = $this->client->files()->upload([
            'file' => $resource,
            'purpose' => 'user_data',
        ]);

        return $file->id;
    }

    /**
     * Annotation `container_file_citation` cuma bawa `filename`, bukan MIME
     * type -- tebak dari ekstensi, sama seperti downloadGeneratedFiles() di
     * bawah menebak dari MIME type CodeFileOutput. null = bukan .docx/.pdf,
     * abaikan (mis. file perantara yang model buat lalu hapus sendiri).
     */
    private function mimeFromFilename(string $filename): ?string
    {
        return match (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf' => 'application/pdf',
            default => null,
        };
    }

    /**
     * Parse balasan model sebagai objek JSON, toleran terhadap markdown
     * code fence (```json ... ```) walau prompt sudah minta tanpa fence.
     */
    private function parseJsonObject(string $text): ?array
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?? $trimmed;

        $decoded = json_decode(trim($trimmed), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $definition Anthropic-shaped AiTool::definition() (name/description/input_schema)
     * @return array<string, mixed>
     */
    private function translateTool(array $definition): array
    {
        return [
            'type' => 'function',
            'name' => $definition['name'],
            'description' => $definition['description'],
            'parameters' => $definition['input_schema'],
            // Skema AiTool tidak selalu strict-compliant (properti opsional,
            // tidak semua field wajib masuk 'required') -- strict:true akan
            // ditolak API untuk skema seperti itu.
            'strict' => false,
        ];
    }

    /**
     * @param array<string, AiTool> $tools
     * @return array{0: array<string, mixed>, 1: ?array{tool: string, input: array, output: array}} [item 'function_call_output' buat dikirim balik, entri pulled_data (null kalau tool gagal/tak dikenal)]
     */
    private function runToolAndCollect(Employee $employee, OutputFunctionToolCall $call, array $tools): array
    {
        $tool = $tools[$call->name] ?? null;

        if (!$tool) {
            return [[
                'type' => 'function_call_output',
                'call_id' => $call->callId,
                'output' => json_encode(['error' => "Unknown tool: {$call->name}"]),
            ], null];
        }

        try {
            $input = json_decode($call->arguments, true);
            $input = is_array($input) ? $input : [];
            $data = $tool->run($employee, $input);

            return [[
                'type' => 'function_call_output',
                'call_id' => $call->callId,
                'output' => json_encode($data),
            ], ['tool' => $call->name, 'input' => $input, 'output' => $data]];
        } catch (Throwable $e) {
            report($e);

            return [[
                'type' => 'function_call_output',
                'call_id' => $call->callId,
                'output' => json_encode(['error' => 'Something went wrong while running this tool.']),
            ], null];
        }
    }

    /**
     * @param array<string, array<string, string>> $filesByContainer container_id => [file_id => mime_type]
     * @return array{docx: ?string, pdf: ?string}
     */
    private function downloadGeneratedFiles(array $filesByContainer): array
    {
        $out = ['docx' => null, 'pdf' => null];

        foreach ($filesByContainer as $containerId => $files) {
            foreach ($files as $fileId => $mimeType) {
                $extension = match ($mimeType) {
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                    'application/pdf' => 'pdf',
                    default => null,
                };

                if (!$extension) {
                    continue;
                }

                $out[$extension] = $this->client->containers()->files()->content($containerId, $fileId);
            }
        }

        return $out;
    }
}
