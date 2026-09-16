<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug Menu Access untuk hub "Approval Workflow" (Keputusan D180).
 *
 * 🔴 LATAR BELAKANG. Sebelum ini, alur persetujuan Overtime/Reimbursement/
 * Purchase Request/Cash Advance/Cash Advance Report hidup DI DALAM halaman
 * Settings masing-masing modul, dijaga SATU slug yang sama dengan aturan
 * (rules) modul itu — siapa yang boleh mengubah aturan otomatis boleh
 * mengubah alur, dan sebaliknya.
 *
 * Pemilik sistem meminta PEMISAHAN HAK yang eksplisit: alur persetujuan
 * dipindah jadi menu tersendiri ("Approval Workflow"), dengan slug BARU per
 * modul — supaya bisa diberikan/dicabut TERPISAH dari hak mengubah aturan,
 * dan supaya siapa pun yang diberi hak ini melalui persetujuan MANUAL di
 * Control Center, bukan otomatis ikut mendapat karena sudah pegang slug lain.
 *
 * Lima slug BARU, bukan menggantikan yang lama:
 *   - `general.settings.overtime` dkk. TETAP menjaga halaman Settings
 *     (BAGIAN 2 — Aturan, yang tersisa setelah BAGIAN 1 dipindah keluar).
 *   - Slug baru di bawah ini menjaga HALAMAN "Approval Workflow" yang baru,
 *     DAN rute POST pengubah langkah (store/update/destroy/move) — keduanya
 *     dipindah ke slug baru supaya pemisahan haknya nyata di level rute,
 *     bukan cuma disembunyikan di tampilan (lihat routes/hr-general.php).
 *
 * Cash Advance & Cash Advance Report SENGAJA dipisah jadi DUA slug berbeda
 * meski keduanya berbagi SATU set rute POST (D136) — pemisahan hak antara
 * keduanya diperiksa ULANG di dalam controller
 * (CashAdvanceSettingController::assertCanManageWorkflow()), pola yang sama
 * dengan bagaimana CashAdvanceController memeriksa ulang canEditDocument()
 * di dalam method meski gerbang rutenya satu slug halaman.
 *
 * Parent Cash Advance/Cash Advance Report sengaja `management`, BUKAN
 * `general` — mengikuti alasan D141: supaya haknya bisa diberikan ke
 * Finance/Accounting/Direksi tanpa ikut membuka apa pun di bawah `general`.
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain — pemilik sistem membagikannya secara manual
 * lewat Control Center -> Menu Access, persis "pengawasan ketat" yang diminta.
 */
return new class extends Migration
{
    private const GENERAL_PAGES = [
        'general.approval-workflow.overtime'         => 'Approval Workflow — Overtime',
        'general.approval-workflow.reimbursement'    => 'Approval Workflow — Reimbursement',
        'general.approval-workflow.purchase-request' => 'Approval Workflow — Purchase Request',
    ];

    private const MANAGEMENT_PAGES = [
        'management.approval-workflow.cash-advance'        => 'Approval Workflow — Cash Advance',
        'management.approval-workflow.cash-advance-report' => 'Approval Workflow — Cash Advance Report',
    ];

    public function up(): void
    {
        MenuRegistrar::register('general', self::GENERAL_PAGES, 120, 'page');
        MenuRegistrar::register('management', self::MANAGEMENT_PAGES, 20, 'page');
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::GENERAL_PAGES));
        MenuRegistrar::remove(array_keys(self::MANAGEMENT_PAGES));
    }
};
