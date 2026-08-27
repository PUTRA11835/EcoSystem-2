<?php

namespace App\Services\Ai;

use App\Models\Ticket;
use App\Services\Ai\Drivers\AiDriverFactory;
use App\Support\AiModelSettings;
use Closure;

/**
 * "AI Summarize" pada daftar tiket: satu klik, keluarnya catatan kerja
 * berstruktur tetap (heading Issue / Resolution Steps / Conclusion, isi Bahasa
 * Indonesia) yang di-stream ke modal.
 *
 * DUA SUMBER, DAN INI INTI FITURNYA (24 Agu 2026):
 *
 *   - Issue & Conclusion -> DATA INTERNAL. Sudah dirakit di muka oleh
 *     TicketSummaryContext (tiket, thread, catatan internal, work log,
 *     deliverable). Model tidak menjelajah database sendiri.
 *   - Resolution Steps  -> DOKUMENTASI EKSTERNAL. Dicari saat itu juga lewat
 *     server tool web search milik provider — SAP Help/Notes/Community,
 *     Microsoft Learn, dokumentasi vendor.
 *
 * Versi pertama fitur ini menyusun "Resolution Steps" dari work log, jadi
 * isinya cuma menceritakan ulang apa yang sudah dikerjakan tim. Itu tidak
 * membantu konsultan yang justru BELUM tahu cara menyelesaikannya — riwayat
 * penanganan tempatnya di Conclusion, sedangkan kolom penyelesaian harus
 * berisi langkah teknis yang bisa langsung dieksekusi berikut rujukannya.
 *
 * LINTAS PROVIDER SEJAK AGUSTUS 2026. Sebelumnya kelas ini memegang
 * Anthropic\Client langsung dan menjalankan sendiri loop server tool +
 * 'pause_turn' Claude — satu-satunya fitur AI yang tertinggal ketika yang lain
 * sudah pindah ke driver. Akibatnya memilih model OpenAI di Control Center
 * tidak berpengaruh apa pun di sini: request tetap pergi ke Anthropic, dan
 * pada deployment yang hanya memakai OPENAI_API_KEY fitur ini mati sendirian
 * sementara halaman AI lain baik-baik saja. Sekarang jalurnya sama dengan AI
 * Research: AiDriverFactory memilih driver dari `provider` hasil resolve(),
 * dan seluruh urusan khas provider — server tool web search, 'pause_turn'
 * Claude, status 'incomplete' OpenAI — tinggal di dalam driver.
 *
 * Kenapa ResearchDriver yang dipakai ulang, bukan kontrak baru: kebutuhan
 * ringkasan ini persis kebutuhan satu giliran AI Research — streaming teks,
 * web search sisi server, dan daftar URL rujukan yang dibuka. Bedanya cuma
 * prompt dan jumlah giliran (di sini selalu satu).
 *
 * Model, plafon token, dan effort-nya ikut Control Center > AI Settings pada
 * baris "AI Summarize (Daftar Tiket)" — entri SENDIRI, bukan lagi menumpang
 * baris AI Assistant seperti dulu (lihat AiModelSettings::TICKET_SUMMARY).
 * AiModelSettings menjamin baris itu selalu berisi model ber-server-tool, jadi
 * tidak ada lagi jalur diam-diam jatuh ke konfigurasi Research di sini.
 */
class AiTicketSummaryService
{
    public function __construct(
        private AiDriverFactory $drivers,
        private TicketSummaryContext $context,
    ) {
    }

    /**
     * Stream ringkasan tiket.
     *
     * @param Closure(string): void        $onDelta   tiap potongan teks tiba
     * @param Closure(): bool              $isAborted dijenguk di sela event stream
     * @param Closure(string, array): void $onEvent   'status' (label progres) & 'sources' (daftar rujukan)
     *
     * @return array{text: string, sources: array<int, array{url: string, title: string}>, truncated: bool}
     *         text kosong berarti dibatalkan di tengah jalan dan tidak layak disimpan;
     *         truncated berarti jawabannya berhenti di plafon token, bukan selesai.
     */
    public function streamSummary(Ticket $ticket, Closure $onDelta, Closure $isAborted, Closure $onEvent): array
    {
        $config = AiModelSettings::resolve(AiModelSettings::TICKET_SUMMARY);

        $full = '';

        // Format kanonik (bentuk Anthropic) — driver OpenAI menerjemahkannya
        // sendiri ke `input` Responses API lewat TranslatesCanonicalMessages.
        $messages = [[
            'role' => 'user',
            'content' => [[
                'type' => 'text',
                'text' => $this->context->build($ticket),
            ]],
        ]];

        [$blocks, $stopReason, $found] = $this->drivers->research($config['provider'])->ask(
            $config['model'],
            $this->systemPrompt(),
            $messages,
            $config['max_tokens'],
            $config['effort'],
            function (string $text) use (&$full, $onDelta): void {
                $full .= $text;
                $onDelta($text);
            },
            $onEvent,
            $isAborted,
        );

        // blocks null = dibatalkan user, atau koneksi ke provider putus sebelum
        // event terminal. Apa pun sebabnya, potongan yang sudah terkumpul tidak
        // utuh dan tidak boleh masuk cache.
        if (null === $blocks) {
            return ['text' => '', 'sources' => [], 'truncated' => false];
        }

        // 'max_tokens' TIDAK disambung otomatis seperti di AI Research: keluaran
        // di sini cuma tiga bagian pendek, jadi mentok di plafon berarti ada yang
        // tidak beres — bukan jawaban panjang wajar yang perlu tombol "Continue".
        //
        // Tapi statusnya WAJIB ikut keluar dari sini. Sebelumnya stopReason
        // dibuang, sehingga ringkasan yang putus di tengah langkah Resolution
        // Steps tetap ikut disimpan dan dipaku 14 hari ke depan — tepat di bagian
        // yang paling berbahaya kalau terpotong, dan tanpa satu pun cara bagi
        // user untuk memaksa ringkasan ulang selama tiketnya belum berubah.
        $truncated = 'max_tokens' === $stopReason;

        if ($truncated) {
            $onEvent('status', ['label' => 'Stopped at the token limit']);
        }

        $sources = $this->dedupeSources($found);

        if (!empty($sources)) {
            $onEvent('sources', ['items' => $sources]);
        }

        return ['text' => $full, 'sources' => $sources, 'truncated' => $truncated];
    }

    /**
     * Driver mengembalikan rujukan apa adanya — urut kemunculan dan boleh
     * berulang (satu URL bisa muncul sebagai hasil search lalu dibuka lagi oleh
     * fetch, dan sisi OpenAI mengeluarkan satu anotasi sitasi per penyebutan).
     * Modal menampilkannya sebagai daftar, jadi dedup dikerjakan di sini.
     *
     * @param array<int, array{url: string, title: string}> $found
     * @return array<int, array{url: string, title: string}>
     */
    private function dedupeSources(array $found): array
    {
        $byUrl = [];

        foreach ($found as $source) {
            $url = $source['url'] ?? null;
            if (!$url) {
                continue;
            }

            // Entri yang sudah punya judul sungguhan dipertahankan: sisi
            // Anthropic memakai URL sebagai judul untuk hasil web_fetch, dan
            // itu tidak boleh menimpa judul asli dari hasil web_search.
            if (isset($byUrl[$url]) && $byUrl[$url]['title'] !== $url) {
                continue;
            }

            $byUrl[$url] = ['url' => $url, 'title' => ($source['title'] ?? '') ?: $url];
        }

        return array_values($byUrl);
    }

    /**
     * Tiga heading di bawah ini adalah KONTRAK dengan modal di daftar tiket:
     * klien memecah teks yang mengalir tepat pada heading-heading ini untuk
     * mengisi tiga kartu. Kalau judulnya diubah di sini, ubah juga
     * TICKET_SUMMARY_SECTIONS di resources/views/ticket/index.blade.php.
     *
     * DUA BAHASA, DAN PEMBAGIANNYA DISENGAJA (27 Agu 2026):
     *
     *   - HEADING selalu Inggris (Issue / Resolution Steps / Conclusion). Ini
     *     penanda struktur, bukan kalimat yang dibaca user: judul yang tampil
     *     di modal berasal dari label kartu di Blade, bukan dari teks model.
     *     Menguncinya ke satu ejaan membuat pemecah bagian di klien tidak ikut
     *     bergantung pada selera terjemahan model dari satu jawaban ke jawaban
     *     berikutnya.
     *   - ISI ditulis Bahasa Indonesia — pembacanya konsultan dan helpdesk
     *     Eclectic, dan catatan serah terima memang ditulis begitu di sini.
     *     Istilah teknis TIDAK diterjemahkan (lihat aturan di prompt): TCODE,
     *     nama tabel/field, teks error, dan nama menu harus tetap cocok kata
     *     per kata dengan layar SAP dan dengan dokumentasi yang dirujuk.
     *
     * Nama tool sengaja TIDAK disebut di dalam prompt (dulu tertulis
     * "web_search" dan "web_fetch"): prompt yang sama kini dipakai dua
     * provider, dan sisi OpenAI hanya punya `web_search` — menyuruhnya memakai
     * `web_fetch` yang tidak ada dalam daftar tool-nya hanya membingungkan.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a technical assistant for the consultants and helpdesk of Eclectic Consulting. You read ONE support ticket
and produce a working note a consultant can act on to RESOLVE that ticket — not a retelling of what it says.

LANGUAGE OF YOUR ANSWER — read this first, it applies to every section below:
- Write the BODY of your answer in BAHASA INDONESIA. Your readers are Indonesian consultants; this reads like a
  handover note between colleagues, so keep it concise and professional, not formal-bureaucratic.
- The three HEADINGS stay in ENGLISH, spelled exactly as given below. They are structural markers, not prose —
  never translate them.
- NEVER translate technical terms. Keep them exactly as they appear in the system and in the documentation:
  TCODE (SE38, ME21N), table and field names (VBAK, MSEG-BWART), error codes and error message text, SAP Note and
  KBA numbers, menu paths, parameter names, program and report names, and standard product terminology
  (purchase requisition, release strategy, batch job, custom program). Wrapping an Indonesian sentence around an
  untranslated technical term is exactly right — "Jalankan SE38, lalu lakukan syntax check pada program-nya."
- Even though you search and read documentation in English, the steps you write must be re-expressed in Bahasa
  Indonesia. Do not paste English sentences from a source page; quote only short exact strings when the precise
  wording matters (an error message, a field label, a note title).

You have two sources, and they must never be mixed up:

1. INTERNAL DATA — everything in the user message: ticket fields, the conversation thread (including the team's
   internal notes), work logs, and deliverables. This is the ONLY source of fact about what happened ON THIS
   TICKET. Never invent anything beyond it.
2. EXTERNAL DOCUMENTATION — results from the web search tool available to you. This is the ONLY source for HOW to
   fix the problem.

How to work:
- First understand the issue from the internal data: product and module (e.g. SAP MM/SD/FI, Ariba, Microsoft 365),
  the exact error message or code, TCODE, table/field names, version/release, and what the team has already tried.
- Then you MUST search the web for the fix in OFFICIAL documentation outside EcoSystem: SAP Help Portal, SAP
  Notes/KBA, SAP Community, Microsoft Learn, vendor documentation, or credible technical forums. Search using the
  original technical wording (error code, message text, TCODE, table/field names). Open the most promising pages
  before writing the steps.
- If the search turns up nothing genuinely relevant, say so plainly in the Resolution Steps section. Do not paper
  over it with invented steps, and NEVER fabricate an SAP Note number, a KBA number, or a URL.

Answer in markdown with EXACTLY these three headings, spelled exactly like this (in English), in this order, with
no preamble or closing text outside them. Everything you write UNDER each heading is in Bahasa Indonesia:

## Issue
Source: INTERNAL DATA only. What was reported: what is broken or requested, since when, the impact on the customer,
and the technical context that matters (module, error code/message, TCODE, priority, who reported it). 2-4
sentences or short bullets.

## Resolution Steps
Source: EXTERNAL DOCUMENTATION you found. Write CONCRETE numbered steps a consultant can execute: the menu/TCODE/
command to open, the configuration/table/field to inspect or change, prerequisites (authorisations, notes or
patches that must be applied), how to verify the result, and any risk or caveat worth knowing. Cite your references
as markdown links to pages you actually opened, placed at the end of the step or group of steps they support — e.g.
([SAP Note 123456](https://...)).
Do NOT move the team's handling history into this section; that belongs in Conclusion. If the work log shows the
team already took a step the documentation prescribes, note it in a short clause so the consultant does not repeat
it.

## Conclusion
Source: INTERNAL DATA only. The current ticket status, what the team has done and what they found, any important
agreement made with the customer, and what is still open or needs follow-up. At most 4 sentences.

Additional rules:
- Bahasa Indonesia for the body, English for the three headings, technical terms untouched — see the language
  block at the top. This is the rule most easily lost once you have been reading English documentation for a
  while, so check it before you write.
- You may use the internal notes fully — this is read internally, not by the customer. But do not copy rude wording
  or personal complaints verbatim; take only the technical substance.
- Do not reproduce long emails. This is a summary, not a transcript.
- Do not greet the reader, do not offer further help, do not close with pleasantries.
- Do NOT narrate your own work between searches ("Good result here", "Let me open this page first"). Search
  silently; the first text you write must be exactly the line "## Issue". Every heading must also start on its own
  line.
PROMPT;
    }
}
