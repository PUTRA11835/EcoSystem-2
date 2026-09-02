<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Beta\Messages\BetaCodeExecutionTool20260521;
use Anthropic\Beta\Messages\BetaContainerParams;
use Anthropic\Beta\Messages\BetaSkillParams;
use Anthropic\Client;
use Anthropic\Core\FileParam;
use App\Models\Employee;
use App\Services\Ai\Drivers\Contracts\ReportGenerationDriver;
use App\Services\Ai\Tools\AiTool;
use RuntimeException;
use Throwable;

/**
 * Claude side of Word Report Generator — Agent Skill custom
 * "laravel-word-report-generator" (lihat
 * .claude/skills/laravel-word-report-generator/SKILL.md) + code execution
 * container, dipakai HANYA di assembleDocument() (fase 3 -- lihat
 * ReportGenerationDriver). extractStructure() dan pullData() adalah
 * panggilan biasa TANPA container/skill/code-execution, supaya dua fase
 * ringan itu tetap murah & cepat -- cuma fase yang benar-benar butuh
 * unzip/edit XML/convert PDF yang memakai sandbox.
 *
 * Pola container/skills/betas di assembleDocument() SENGAJA sama persis
 * dengan AnthropicTicketAnalysisDriver (skill "sap-ticket-analyzer").
 */
class AnthropicReportDriver implements ReportGenerationDriver
{
    private const MAX_TOOL_ITERATIONS = 12;

    /** Fase 2 (tarik data) cuma tool-calling murni, tidak butuh sebanyak fase dokumen. */
    private const MAX_DATA_ITERATIONS = 8;

    private const BETAS = [
        'code-execution-2025-08-25',
        'skills-2025-10-02',
        'files-api-2025-04-14',
    ];

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

        $response = $this->client->beta->messages->create(
            model: $model,
            maxTokens: $maxTokens,
            messages: [[
                'role' => 'user',
                'content' => [
                    ['type' => 'document', 'source' => ['type' => 'file', 'file_id' => $fileId]],
                    ['type' => 'text', 'text' => $prompt],
                ],
            ]],
            outputConfig: $effort ? ['effort' => $effort] : null,
            betas: ['files-api-2025-04-14'],
        );

        $text = $this->extractText($response->content);

        if (null === $text) {
            throw new RuntimeException('Ekstrak struktur dokumen (Claude) tidak menghasilkan balasan apa pun.');
        }

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
        $messages = [['role' => 'user', 'content' => $prompt]];
        $toolDefs = array_map(fn (AiTool $tool) => $tool->definition(), array_values($tools));

        $finalText = null;
        $pulledData = [];

        for ($i = 0; $i < self::MAX_DATA_ITERATIONS; ++$i) {
            $response = $this->client->beta->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                tools: $toolDefs,
                messages: $messages,
                outputConfig: $effort ? ['effort' => $effort] : null,
            );

            $messages[] = ['role' => 'assistant', 'content' => $response->content];

            if ('tool_use' !== $response->stopReason) {
                $finalText = $this->extractText($response->content);
                break;
            }

            [$results, $collected] = $this->runToolsAndCollect($employee, $response->content, $tools);
            array_push($pulledData, ...$collected);
            $messages[] = ['role' => 'user', 'content' => $results];
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
        $skillId = config('services.anthropic.word_report_skill_id');
        if (!$skillId) {
            throw new RuntimeException('ANTHROPIC_WORD_REPORT_SKILL_ID belum diatur di .env (jalankan `php artisan claude:upload-skill`).');
        }

        $fileId = $this->uploadTemplate($templateAbsolutePath, $templateFilename);

        $messages = [[
            'role' => 'user',
            'content' => [
                ['type' => 'document', 'source' => ['type' => 'file', 'file_id' => $fileId]],
                ['type' => 'text', 'text' => $prompt],
            ],
        ]];

        $container = BetaContainerParams::with(skills: [
            BetaSkillParams::with(skillID: $skillId, type: 'custom', version: 'latest'),
        ]);

        $toolDefs = [BetaCodeExecutionTool20260521::with()];

        $finalText = null;
        $fileIds = [];

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; ++$i) {
            $response = $this->client->beta->messages->create(
                model: $model,
                maxTokens: $maxTokens,
                container: $container,
                tools: $toolDefs,
                messages: $messages,
                outputConfig: $effort ? ['effort' => $effort] : null,
                betas: self::BETAS,
            );

            // Ikut sertakan blok apa adanya (termasuk bash_code_execution_tool_result)
            // supaya Claude "ingat" file yang sudah dibuat di iterasi sebelumnya.
            $messages[] = ['role' => 'assistant', 'content' => $response->content];

            foreach ($this->extractFileIds($response->content) as $id) {
                $fileIds[$id] = true;
            }

            if ('tool_use' !== $response->stopReason) {
                $finalText = $this->extractText($response->content);
                break;
            }

            $messages[] = ['role' => 'user', 'content' => $this->runCodeExecutionOnly($response->content)];
        }

        if (null === $finalText) {
            throw new RuntimeException('Generate laporan (Claude) melebihi batas iterasi tool-calling (' . self::MAX_TOOL_ITERATIONS . 'x) tanpa hasil akhir.');
        }

        if (empty($fileIds)) {
            // Berhenti tanpa file, tapi ada teks -> ini pertanyaan klarifikasi
            // (SKILL.md Tahap 2 poin 5), bukan kegagalan.
            return ['summary' => null, 'docx' => null, 'pdf' => null, 'question' => $finalText];
        }

        return [
            'question' => null,
            'summary' => $finalText,
            ...$this->downloadGeneratedFiles(array_keys($fileIds)),
        ];
    }

    private function uploadTemplate(string $absolutePath, string $filename): string
    {
        $resource = fopen($absolutePath, 'r');
        if (false === $resource) {
            throw new RuntimeException("Gagal membaca file template di {$absolutePath}.");
        }

        $file = $this->client->beta->files->upload(
            file: FileParam::fromResource(
                $resource,
                filename: $filename,
                contentType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
            betas: ['files-api-2025-04-14'],
        );

        return $file->id;
    }

    /**
     * @param array<string, AiTool> $tools
     * @param array<int, mixed> $content
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>} [tool_result blocks buat dikirim balik, entri pulled_data buat diakumulasi]
     */
    private function runToolsAndCollect(Employee $employee, array $content, array $tools): array
    {
        $results = [];
        $collected = [];

        foreach ($content as $block) {
            if ('tool_use' !== ($block->type ?? null)) {
                continue;
            }

            $tool = $tools[$block->name] ?? null;

            if (!$tool) {
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => json_encode(['error' => "Unknown tool: {$block->name}"]),
                    'is_error' => true,
                ];
                continue;
            }

            try {
                $data = $tool->run($employee, (array) $block->input);
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => json_encode($data),
                ];
                $collected[] = ['tool' => $block->name, 'input' => (array) $block->input, 'output' => $data];
            } catch (Throwable $e) {
                report($e);
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => json_encode(['error' => 'Something went wrong while running this tool.']),
                    'is_error' => true,
                ];
            }
        }

        return [$results, $collected];
    }

    /**
     * assembleDocument() cuma punya satu tool (code execution) dan itu
     * dijalankan otomatis oleh provider sendiri (bukan lewat tool_result
     * yang kita isi manual seperti AiTool) -- satu-satunya tool_use yang
     * bisa muncul di sini secara teori adalah dari toolDefs yang kita
     * declare, tapi karena toolDefs cuma BetaCodeExecutionTool20260521,
     * tidak ada tool_result manual yang perlu dibalas balik. Method ini
     * cuma jaga-jaga: kalau ternyata ADA tool_use tak dikenal (harusnya
     * tidak pernah terjadi), balas error is_error supaya loop tidak macet.
     *
     * @param array<int, mixed> $content
     * @return array<int, array<string, mixed>>
     */
    private function runCodeExecutionOnly(array $content): array
    {
        $results = [];

        foreach ($content as $block) {
            if ('tool_use' !== ($block->type ?? null)) {
                continue;
            }

            $results[] = [
                'type' => 'tool_result',
                'tool_use_id' => $block->id,
                'content' => json_encode(['error' => "Unexpected tool call in document phase: {$block->name}"]),
                'is_error' => true,
            ];
        }

        return $results;
    }

    /**
     * @param array<int, mixed> $content
     */
    private function extractText(array $content): ?string
    {
        foreach ($content as $block) {
            if ('text' === ($block->type ?? null)) {
                return $block->text;
            }
        }

        return null;
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
     * @param array<int, mixed> $content
     * @return array<int, string>
     */
    private function extractFileIds(array $content): array
    {
        $ids = [];

        foreach ($content as $block) {
            if ('bash_code_execution_tool_result' !== ($block->type ?? null)) {
                continue;
            }

            $resultBlock = $block->content ?? null;
            if (!$resultBlock || 'bash_code_execution_result' !== ($resultBlock->type ?? null)) {
                continue;
            }

            foreach ((array) $resultBlock->content as $output) {
                if (!empty($output->fileID)) {
                    $ids[] = $output->fileID;
                }
            }
        }

        return $ids;
    }

    /**
     * @param array<int, string> $fileIds
     * @return array{docx: ?string, pdf: ?string}
     */
    private function downloadGeneratedFiles(array $fileIds): array
    {
        $out = ['docx' => null, 'pdf' => null];

        foreach ($fileIds as $fileId) {
            $meta = $this->client->beta->files->retrieveMetadata($fileId, betas: ['files-api-2025-04-14']);
            $extension = strtolower((string) pathinfo($meta->filename, PATHINFO_EXTENSION));

            if (!in_array($extension, ['docx', 'pdf'], true)) {
                continue;
            }

            $out[$extension] = $this->client->beta->files->download($fileId, betas: ['files-api-2025-04-14']);
        }

        return $out;
    }
}
