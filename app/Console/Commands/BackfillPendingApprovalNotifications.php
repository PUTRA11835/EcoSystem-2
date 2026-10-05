<?php

namespace App\Console\Commands;

use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceReport;
use App\Models\Notification;
use App\Models\Overtime\OvertimeRequest;
use App\Models\PurchaseRequest\PurchaseRequest;
use App\Models\Reimbursement\ReimbursementRequest;
use App\Support\ApprovalRecipients;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Backfill notifikasi "perlu tindakan Anda" (Keputusan HC-D39) untuk dokumen
 * yang SUDAH menunggu persetujuan SEBELUM fitur ini ada.
 *
 * 🔴 LATAR BELAKANG. `notifyApprovers()` di 5 service (Overtime, Reimbursement,
 * Purchase Request, Cash Advance, Cash Advance Report) hanya terpanggil pada
 * transisi BARU — submit()/approve() yang terjadi SETELAH kode itu di-deploy.
 * Dokumen yang sudah berstatus "menunggu" SEBELUM itu tidak pernah memicu
 * panggilan tersebut, sehingga `pendingIdsFor()` (dipakai Command Center)
 * tetap menghitungnya sebagai pending, padahal tidak ada baris di tabel
 * `notifications` — itulah sebab Command Center bilang "6 Pending Approval"
 * tapi halaman Notifications menunjukkan 0 (Keputusan HC-D41).
 *
 * SEKALI JALAN, tapi AMAN dijalankan berulang: pesan tiap notifikasi selalu
 * memuat nomor dokumen (`request_no`/`report_no`), jadi kombinasi
 * (employee_id, type, nomor dokumen) sudah unik per penerima per dokumen —
 * dicek sebelum menulis, menjalankan ulang tidak membuat duplikat.
 */
class BackfillPendingApprovalNotifications extends Command
{
    protected $signature = 'notifications:backfill-pending-approvals {--dry-run : Hitung saja, jangan tulis apa pun}';

    protected $description = 'Backfill notifikasi penyetuju untuk dokumen yang sudah menunggu sebelum fitur notifikasi ada (Keputusan HC-D39/HC-D41)';

    /** @var array<string, array{model: class-string<Model>, settings: class-string, type: string, label: string, link: string}> */
    private const MODULES = [
        'overtime' => [
            'model'    => OvertimeRequest::class,
            'settings' => \App\Models\Overtime\OvertimeSetting::class,
            'type'     => 'overtime_pending_approval',
            'label'    => 'Overtime request',
            'link'     => '/general/overtime',
        ],
        'reimbursement' => [
            'model'    => ReimbursementRequest::class,
            'settings' => \App\Models\Reimbursement\ReimbursementSetting::class,
            'type'     => 'reimbursement_pending_approval',
            'label'    => 'Reimbursement request',
            'link'     => '/general/reimbursement',
        ],
        'purchase_request' => [
            'model'    => PurchaseRequest::class,
            'settings' => \App\Models\PurchaseRequest\PurchaseRequestSetting::class,
            'type'     => 'purchase_request_pending_approval',
            'label'    => 'Purchase request',
            'link'     => '/general/purchase-request',
        ],
        'cash_advance' => [
            'model'    => CashAdvance::class,
            'settings' => \App\Models\CashAdvance\CashAdvanceSetting::class,
            'type'     => 'cash_advance_pending_approval',
            'label'    => 'Cash advance request',
            'link'     => '/general/cash-advance',
        ],
        'cash_advance_report' => [
            'model'    => CashAdvanceReport::class,
            'settings' => \App\Models\CashAdvance\CashAdvanceSetting::class,
            'type'     => 'cash_advance_report_pending_approval',
            'label'    => 'Cash advance report',
            'link'     => '/general/cash-advance-report',
        ],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $totalDocs    = 0;
        $totalSent    = 0;
        $totalSkipped = 0;

        foreach (self::MODULES as $cfg) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $cfg['model'];

            $docs = $modelClass::whereNotNull('current_step_order')
                ->with('approvals')
                ->get();

            if ($docs->isEmpty()) {
                $this->info("{$cfg['label']}: tidak ada dokumen menunggu.");
                continue;
            }

            $this->info("{$cfg['label']}: {$docs->count()} dokumen menunggu ditemukan.");

            /** @var class-string $settingsClass */
            $settingsClass = $cfg['settings'];
            $allowSelf     = $settingsClass::current()->allow_self_approval;

            foreach ($docs as $doc) {
                $step = $doc->currentApproval();
                if (!$step) {
                    continue;
                }

                $totalDocs++;
                $docNo       = $doc->request_no ?? $doc->report_no ?? ('#' . $doc->getKey());
                $isFirstStep = $step->order_seq === $doc->approvals->min('order_seq');
                $message     = $isFirstStep
                    ? "{$cfg['label']} {$docNo} needs your approval."
                    : "{$cfg['label']} {$docNo} was approved at the previous step and now needs your approval.";

                foreach (ApprovalRecipients::forStep($step) as $employeeId) {
                    if ($employeeId === (int) $doc->employee_id && !$allowSelf) {
                        continue;
                    }

                    $already = Notification::where('employee_id', $employeeId)
                        ->where('type', $cfg['type'])
                        ->where('preview', $message)
                        ->exists();

                    if ($already) {
                        $totalSkipped++;
                        continue;
                    }

                    $totalSent++;
                    if (!$dryRun) {
                        Notification::create([
                            'employee_id' => $employeeId,
                            'type'        => $cfg['type'],
                            'preview'     => $message,
                            'link'        => $cfg['link'],
                        ]);
                    }
                }
            }
        }

        $this->newLine();
        $this->info("Dokumen menunggu diperiksa: {$totalDocs}");
        $this->info(($dryRun ? '[DRY RUN] Akan dikirim' : 'Notifikasi dikirim') . ": {$totalSent}");
        $this->info("Dilewati (sudah pernah dikirim): {$totalSkipped}");

        return 0;
    }
}
