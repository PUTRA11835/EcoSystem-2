<?php

namespace App\Services\Ai;

use App\Models\Employee;
use App\Models\WordReport;
use App\Services\Ai\Drivers\AiDriverFactory;
use App\Services\Ai\Drivers\Contracts\ReportGenerationDriver;
use App\Services\Ai\Tools\AggregateDataTool;
use App\Services\Ai\Tools\AiTool;
use App\Services\Ai\Tools\ExplainWorkflowTool;
use App\Services\Ai\Tools\GetDeliveryProjectsTool;
use App\Services\Ai\Tools\GetSlaSummaryTool;
use App\Services\Ai\Tools\GetTicketsTool;
use App\Services\Ai\Tools\ListTablesTool;
use App\Services\Ai\Tools\QueryDataTool;
use App\Services\SlaService;
use App\Support\AiModelSettings;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Generate laporan .docx/.pdf dari template Word yang diupload user.
 * Provider-agnostic orchestrator: bangun prompt + registry tool data (SAMA
 * PERSIS dengan AiChatService — lihat docblock properti $tools), lalu
 * serahkan panggilan API sesungguhnya ke AnthropicReportDriver atau
 * OpenAiReportDriver (dipilih lewat AiModelSettings::WORD_REPORT, sama
 * seperti AiChatService/AiTicketAnalyzerService memilih provider mereka).
 *
 * Kelas ini sendiri TIDAK tahu apa pun soal Agent Skills / code_interpreter
 * / bentuk API provider — itu semua ada di driver masing-masing.
 *
 * DIPECAH JADI 3 FASE (lihat ReportGenerationDriver) -- Tahap 1/2/3-5 di
 * SKILL.md dulunya satu percakapan AI berkelanjutan sampai 12 iterasi
 * tool-calling, dan tiap iterasi mengirim ulang SELURUH riwayat sebelumnya
 * (termasuk output code-execution yang bisa besar) -- makin jauh
 * iterasinya, makin besar & lambat tiap panggilan, dan makin gampang
 * kehabisan max_tokens di tengah jalan. Sekarang tiap fase adalah panggilan
 * AI terpisah & bounded, hasilnya disimpan ke $report SEBELUM lanjut ke
 * fase berikutnya -- generate() jadi resumable: dipanggil ulang (retry job,
 * atau setelah user menjawab pertanyaan klarifikasi) otomatis melompati
 * fase yang $report->phase-nya sudah lewat, tidak mengulang dari nol.
 */
class ReportGeneratorService
{
    /**
     * Tahap 1/2 cuma menghasilkan JSON outline / memanggil tool data -- tidak
     * pernah butuh sebesar max_tokens yang dikonfigurasi admin untuk Tahap 3
     * (susun dokumen, yang genuinely bisa panjang: bash script + hasil edit +
     * ringkasan). Membatasi max_tokens per-fase begini langsung mengecilkan
     * token sungguhan yang dipakai tiap panggilan (bukan cuma plafonnya) --
     * pengurangan real terhadap tekanan rate limit TPM provider, dipakai
     * SEBAGAI TAMBAHAN plafon admin (min(...), bukan menggantikannya).
     */
    private const STRUCTURE_MAX_TOKENS_CAP = 4000;
    private const DATA_MAX_TOKENS_CAP = 8000;

    /** @var array<string, AiTool> */
    private array $tools;

    public function __construct(private AiDriverFactory $drivers, SlaService $slaService)
    {
        // Registri tool SENGAJA sama dengan AiChatService — lihat docblock
        // kelas ini. Jangan tambah tool baru di sini; tambahkan di
        // AiChatService dulu supaya kedua fitur tetap konsisten.
        $this->tools = [
            'get_tickets' => new GetTicketsTool(),
            'get_sla_summary' => new GetSlaSummaryTool($slaService),
            'get_delivery_projects' => new GetDeliveryProjectsTool(),
            'explain_workflow' => new ExplainWorkflowTool(),
            'list_tables' => new ListTablesTool(),
            'query_data' => new QueryDataTool(),
            'aggregate_data' => new AggregateDataTool(),
        ];
    }

    public function generate(WordReport $report): void
    {
        $employee = Employee::findOrFail($report->employee_id);
        $config = AiModelSettings::resolve(AiModelSettings::WORD_REPORT);
        $driver = $this->drivers->report($config['provider']);

        if (WordReport::PHASE_STRUCTURE === $report->phase) {
            $result = $driver->extractStructure(
                model: $config['model'],
                effort: $config['effort'],
                maxTokens: min($config['max_tokens'], self::STRUCTURE_MAX_TOKENS_CAP),
                templateAbsolutePath: Storage::path($report->template_path),
                templateFilename: $report->template_original_name,
                prompt: $this->buildStructurePrompt($report),
            );

            $advanced = $this->handlePhaseResult($report, $result['question'], fn () => $report->update([
                'structure_map' => $result['structure'],
                'phase' => WordReport::PHASE_DATA,
            ]));

            if (!$advanced) {
                return;
            }
        }

        if (WordReport::PHASE_DATA === $report->phase) {
            $result = $driver->pullData(
                employee: $employee,
                model: $config['model'],
                effort: $config['effort'],
                maxTokens: min($config['max_tokens'], self::DATA_MAX_TOKENS_CAP),
                prompt: $this->buildDataPrompt($report),
                tools: $this->tools,
            );

            $advanced = $this->handlePhaseResult($report, $result['question'], fn () => $report->update([
                'pulled_data' => $result['data'],
                'phase' => WordReport::PHASE_DOCUMENT,
            ]));

            if (!$advanced) {
                return;
            }
        }

        if (WordReport::PHASE_DOCUMENT === $report->phase) {
            // Config TERPISAH dari fase 1/2 (lihat AiModelSettings::WORD_REPORT_DOCUMENT)
            // -- fase ini satu-satunya yang butuh code execution, paling berat, dan
            // sengaja diberi model dengan TPM pool sendiri supaya tidak berebut kuota
            // dengan fase 1/2 (atau assistant lain yang kebetulan model-nya sama).
            $documentConfig = AiModelSettings::resolve(AiModelSettings::WORD_REPORT_DOCUMENT);
            $documentDriver = $this->drivers->report($documentConfig['provider']);

            $result = $documentDriver->assembleDocument(
                model: $documentConfig['model'],
                effort: $documentConfig['effort'],
                maxTokens: $documentConfig['max_tokens'],
                templateAbsolutePath: Storage::path($report->template_path),
                templateFilename: $report->template_original_name,
                prompt: $this->buildDocumentPrompt($report),
            );

            if (empty($result['docx']) && empty($result['pdf'])) {
                if (!empty($result['question'])) {
                    $this->recordQuestion($report, $result['question']);

                    return;
                }

                throw new RuntimeException('Tidak ada file .docx/.pdf yang dihasilkan.');
            }

            $this->storeGeneratedFiles($report, $result);
            $report->update(['phase' => null]);
        }
    }

    /**
     * Kalau driver mengembalikan pertanyaan klarifikasi, catat & hentikan
     * (return false -- caller di generate() langsung `return`). Kalau
     * tidak, jalankan $onSuccess() (simpan hasil fase + majukan `phase`) dan
     * return true supaya generate() lanjut ke blok fase berikutnya.
     */
    private function handlePhaseResult(WordReport $report, ?string $question, callable $onSuccess): bool
    {
        if (!empty($question)) {
            $this->recordQuestion($report, $question);

            return false;
        }

        $onSuccess();

        return true;
    }

    /**
     * AI berhenti untuk minta klarifikasi (SKILL.md Tahap 2 poin 5) — bukan
     * kegagalan. Simpan pertanyaannya, job TIDAK menandai failed (lihat
     * GenerateWordReportJob).
     */
    private function recordQuestion(WordReport $report, string $question): void
    {
        $qaLog = $report->qa_log ?? [];
        $qaLog[] = ['question' => $question, 'answer' => null];

        $report->update([
            'status' => WordReport::STATUS_AWAITING_INPUT,
            'question' => $question,
            'qa_log' => $qaLog,
        ]);
    }

    private function buildStructurePrompt(WordReport $report): string
    {
        $lines = [$this->skillMarkdown()];

        if ($report->instructions) {
            $lines[] = 'Instruksi tambahan dari user: ' . $report->instructions;
        }

        $lines[] = 'Tugas SEKARANG: kerjakan HANYA Tahap 1 (Baca & Petakan Struktur Dokumen) untuk file '
            . 'template yang dilampirkan. Jangan tarik data apa pun dan jangan mengisi apa pun dulu.';
        $lines[] = 'Balas HANYA dengan JSON valid (tanpa markdown code fence), bentuk: '
            . '{"outline": [{"section": string, "kind": "static"|"data", "note": string}, ...]}. '
            . 'Jika template terlalu ambigu untuk dipetakan, balas dengan teks biasa (bukan JSON) '
            . 'berisi SATU pertanyaan klarifikasi ke user.';

        return implode("\n\n", $lines);
    }

    private function buildDataPrompt(WordReport $report): string
    {
        $lines = [$this->skillMarkdown()];

        if ($report->instructions) {
            $lines[] = 'Instruksi tambahan dari user: ' . $report->instructions;
        }

        $lines[] = 'Peta struktur dokumen (hasil Tahap 1):';
        $lines[] = json_encode($report->structure_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $this->appendQaLog($lines, $report);

        $lines[] = 'Gunakan tool yang tersedia (get_tickets, get_sla_summary, get_delivery_projects, list_tables, '
            . 'query_data, aggregate_data) untuk mengambil data nyata dari EcoSystem — jangan mengarang data apa pun.';
        $lines[] = 'Tugas SEKARANG: kerjakan HANYA Tahap 2 (Kenali topik, lalu tarik semua data terkait) memakai '
            . 'peta struktur di atas. Jangan menulis atau mengedit dokumen apa pun di fase ini.';
        $lines[] = 'Jika topik atau periode laporan tidak jelas dari struktur dokumen maupun instruksi user, '
            . 'tanyakan singkat (maks. 1 pertanyaan, dengan opsi jelas) SEBELUM menarik data — jangan menebak.';

        return implode("\n\n", $lines);
    }

    private function buildDocumentPrompt(WordReport $report): string
    {
        $lines = [$this->skillMarkdown()];

        $lines[] = 'Peta struktur dokumen (hasil Tahap 1):';
        $lines[] = json_encode($report->structure_map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $lines[] = 'Data yang sudah ditarik dari EcoSystem (hasil Tahap 2 — SUDAH LENGKAP, jangan memanggil '
            . 'tool data apa pun lagi, jangan menarik ulang atau mengarang data tambahan):';
        $lines[] = json_encode($report->pulled_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $lines[] = 'Tugas SEKARANG: kerjakan Tahap 3 (copy & edit in-place file template terlampir memakai data '
            . 'di atas), Tahap 4 (convert ke PDF), lalu Tahap 5 (ringkasan singkat ke user).';
        $lines[] = 'Hasil akhir: satu file .docx (format sama persis dengan template) dan satu file .pdf hasil '
            . 'konversinya, lalu tutup dengan ringkasan singkat (data apa yang dipakai, filter/periode, jumlah '
            . 'baris terisi, bagian yang datanya tidak ditemukan jika ada).';
        $lines[] = 'PENTING soal ringkasan: tulis dalam bahasa manusia/awam SEPENUHNYA -- user yang membaca '
            . 'BUKAN programmer. JANGAN sebut nama tabel database, nama function/tool, nama kolom mentah, '
            . 'query, atau istilah teknis apa pun. Sebutkan datanya secara natural, mis. "data tiket dan data '
            . 'pelanggan periode Juli 2026" (BUKAN "tabel `ticket` dan `customer`"), "hasil rekap status tiket" '
            . '(BUKAN "hasil agregasi dari function aggregate_data").';

        return implode("\n\n", $lines);
    }

    /**
     * Riwayat tanya-jawab putaran sebelumnya (kalau ada) -- supaya fase ini
     * tahu apa yang sudah ditanyakan & dijawab, tidak menanyakan hal yang
     * sama dua kali.
     */
    private function appendQaLog(array &$lines, WordReport $report): void
    {
        if (empty($report->qa_log)) {
            return;
        }

        $lines[] = 'Riwayat tanya-jawab sejauh ini (jangan tanyakan ulang hal yang sudah terjawab):';
        foreach ($report->qa_log as $qa) {
            $lines[] = '- Q: ' . $qa['question'];
            $lines[] = '  A: ' . ($qa['answer'] ?? '(belum dijawab)');
        }
    }

    /**
     * Isi SKILL.md (minus frontmatter YAML-nya) apa adanya sebagai konteks
     * di tiap fase -- dipakai kedua provider (dulu cuma OpenAI yang
     * meng-inline ini sendiri lewat instructions, sekarang dipusatkan di
     * sini supaya AnthropicReportDriver & OpenAiReportDriver tidak perlu
     * baca file ini masing-masing). Untuk fase 3 di sisi Claude, isi ini
     * beririsan dengan hosted Agent Skill yang dimuat driver lewat
     * container -- dobel-tersaji tapi murah (statis, bukan bagian yang
     * menumpuk per-iterasi), dan menjaga kelas ini provider-agnostic.
     */
    private function skillMarkdown(): string
    {
        $path = base_path('.claude/skills/laravel-word-report-generator/SKILL.md');
        $raw = is_file($path) ? (string) file_get_contents($path) : '';

        $body = preg_replace('/^---.*?---\s*/s', '', $raw) ?? $raw;

        return trim($body);
    }

    /**
     * @param array{summary: ?string, docx: ?string, pdf: ?string} $result
     */
    private function storeGeneratedFiles(WordReport $report, array $result): void
    {
        $dir = "word-reports/{$report->id}";

        if ($result['docx']) {
            $path = "{$dir}/report.docx";
            Storage::disk('public')->put($path, $result['docx']);
            $report->docx_path = $path;
        }

        if ($result['pdf']) {
            $path = "{$dir}/report.pdf";
            Storage::disk('public')->put($path, $result['pdf']);
            $report->pdf_path = $path;
        }

        $report->summary = $result['summary'];
        $report->save();
    }
}
