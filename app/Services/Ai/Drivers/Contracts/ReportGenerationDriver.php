<?php

namespace App\Services\Ai\Drivers\Contracts;

use App\Models\Employee;
use App\Services\Ai\Tools\AiTool;

/**
 * Word Report Generator dipecah jadi 3 fase (lihat ReportGeneratorService),
 * masing-masing SATU panggilan/percakapan AI terpisah dan bounded -- bukan
 * satu loop tool-calling raksasa yang menggabungkan semuanya (alur lama),
 * yang bikin context tiap giliran terus menumpuk (lambat, gampang kehabisan
 * token) dan tidak punya checkpoint kalau gagal di tengah jalan.
 *
 * Tiap method di sini implementasinya boleh melakukan tool-calling loop
 * SENDIRI di dalam providernya (mis. extractStructure() cuma 1 giliran,
 * pullData()/assembleDocument() bisa beberapa giliran) -- tapi TIDAK PERNAH
 * membawa riwayat lintas fase; fase berikutnya menerima ringkasan hasil
 * fase sebelumnya lewat $prompt (dibangun ReportGeneratorService), bukan
 * percakapan mentah.
 *
 * Ketiganya WAJIB mengenali giliran AI berhenti TANPA hasil (structure/data/
 * file kosong) tapi ADA teks balasan sebagai PERTANYAAN klarifikasi (lihat
 * SKILL.md Tahap 2 poin 5) -- bukan error. Kembalikan sebagai `question`,
 * JANGAN throw.
 */
interface ReportGenerationDriver
{
    /**
     * Tahap 1 -- baca & petakan struktur dokumen template. Tidak perlu tool
     * data ataupun code execution, jadi satu giliran saja.
     *
     * @return array{structure: ?array, question: ?string}
     */
    public function extractStructure(
        string $model,
        ?string $effort,
        int $maxTokens,
        string $templateAbsolutePath,
        string $templateFilename,
        string $prompt,
    ): array;

    /**
     * Tahap 2 -- kenali topik laporan dari peta struktur (sudah ada di
     * $prompt), tarik semua data terkait lewat $tools. Tidak menyentuh file
     * template sama sekali di fase ini.
     *
     * @param array<string, AiTool> $tools keyed by tool name
     * @return array{data: array, question: ?string}
     */
    public function pullData(
        Employee $employee,
        string $model,
        ?string $effort,
        int $maxTokens,
        string $prompt,
        array $tools,
    ): array;

    /**
     * Tahap 3-5 -- copy & edit in-place file template pakai data yang SUDAH
     * lengkap di $prompt (hasil fase 1+2), convert ke PDF, lalu ringkasan.
     * Tidak butuh $tools lagi -- tidak ada data baru yang perlu ditarik.
     *
     * @return array{summary: ?string, docx: ?string, pdf: ?string, question: ?string} isi file mentah (raw bytes); docx/pdf/summary null & question terisi kalau AI berhenti untuk minta klarifikasi
     */
    public function assembleDocument(
        string $model,
        ?string $effort,
        int $maxTokens,
        string $templateAbsolutePath,
        string $templateFilename,
        string $prompt,
    ): array;
}
