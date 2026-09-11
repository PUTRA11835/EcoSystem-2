<?php

namespace App\Console\Commands;

use Anthropic\Client;
use Anthropic\Core\FileParam;
use Illuminate\Console\Command;

/**
 * Upload (atau tambah versi baru untuk) Agent Skill custom
 * "laravel-word-report-generator" ke workspace Anthropic — sekali jalan,
 * bukan bagian dari alur request user. Skill ini dipakai ClaudeReportService
 * untuk fitur generate laporan .docx/.pdf dari template Word.
 *
 * Pola env var (ANTHROPIC_WORD_REPORT_SKILL_ID) sengaja sejajar dengan
 * ANTHROPIC_TICKET_ANALYZER_SKILL_ID yang sudah ada — bedanya skill itu
 * dibuat manual di Anthropic Console, skill ini di-upload lewat command ini.
 *
 * Pertama kali: `php artisan claude:upload-skill` → buat skill baru, salin
 * skill_id yang ditampilkan ke ANTHROPIC_WORD_REPORT_SKILL_ID di .env.
 * Setelah SKILL.md berubah: jalankan command yang sama lagi (skill_id sudah
 * ada di .env) → otomatis push sebagai versi baru pada skill yang sama.
 */
class UploadWordReportSkill extends Command
{
    protected $signature = 'claude:upload-skill
        {--skill-id= : Upload sebagai versi baru untuk skill ID ini (default: baca dari ANTHROPIC_WORD_REPORT_SKILL_ID)}';

    protected $description = 'Upload SKILL.md laravel-word-report-generator ke Anthropic Skills API (buat baru atau tambah versi)';

    private const SKILL_SLUG = 'laravel-word-report-generator';

    public function handle(Client $client): int
    {
        $skillPath = base_path('.claude/skills/' . self::SKILL_SLUG . '/SKILL.md');

        if (!is_file($skillPath)) {
            $this->error("SKILL.md tidak ditemukan di {$skillPath}");

            return self::FAILURE;
        }

        $file = FileParam::fromString(
            (string) file_get_contents($skillPath),
            self::SKILL_SLUG . '/SKILL.md',
            'text/markdown',
        );

        $skillId = $this->option('skill-id') ?: config('services.anthropic.word_report_skill_id');

        try {
            if ($skillId) {
                $version = $client->beta->skills->versions->create(
                    skillID: $skillId,
                    files: [$file],
                    betas: ['skills-2025-10-02'],
                );

                $this->info("Versi baru berhasil di-upload untuk skill {$skillId}.");
                $this->line("Version ID: {$version->id}");

                return self::SUCCESS;
            }

            $skill = $client->beta->skills->create(
                files: [$file],
                displayTitle: 'Laravel Word Report Generator',
                betas: ['skills-2025-10-02'],
            );

            $this->info("Skill baru berhasil dibuat: {$skill->id}");
            $this->newLine();
            $this->line('Tambahkan baris berikut ke .env:');
            $this->line("ANTHROPIC_WORD_REPORT_SKILL_ID={$skill->id}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Upload gagal: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
