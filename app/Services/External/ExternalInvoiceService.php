<?php

namespace App\Services\External;

use App\Support\Contracts\ContractRules;
use App\Support\External\ExternalTimesheetRules as R;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * External → Invoices & Payments: invoice konsultan atas lembar jam yang sudah disetujui — unggah (konsultan atau HR atas nama),
 * verifikasi atau tolak (penyetuju tahap invoice_verification), lalu tandai Paid.
 *
 * Berkas PDF disimpan di disk PRIVAT `external-invoices/{lembar}/` dan hanya disajikan lewat controller dengan pemeriksaan izin.
 * Jumlah invoice boleh berbeda dari bruto yang disetujui (ditandai di layar) — Finance yang menilai saat verifikasi.
 */
class ExternalInvoiceService
{
    public const DISK = 'local';

    public function __construct(private ExternalWorkflowService $workflow) {}

    /**
     * @param  array{invoice_no:string,invoice_date:string,amount:string|float}  $d
     * @throws \DomainException
     */
    public function upload(int $sheetId, array $d, UploadedFile $file, int $actorId): void
    {
        $sheet = DB::table('external_timesheets')->where('id', $sheetId)->first();
        if (!$sheet || $sheet->status !== R::APPROVED) {
            throw new \DomainException('An invoice can only be uploaded for an approved timesheet.');
        }
        $invoice = DB::table('external_invoices')->where('timesheet_id', $sheetId)->first();
        if ($invoice && !R::canInvoiceTransition($invoice->status, R::INV_UPLOADED)) {
            throw new \DomainException('This invoice is already ' . strtolower(R::INVOICE_LABELS[$invoice->status]) . ' and can no longer be replaced.');
        }

        $number = trim((string) ($d['invoice_no'] ?? ''));
        $date = (string) ($d['invoice_date'] ?? '');
        $amount = ContractRules::parseAmount($d['amount'] ?? 0);
        if ($number === '') {
            throw new \DomainException('Enter the invoice number.');
        }
        if (!\DateTime::createFromFormat('Y-m-d', $date)) {
            throw new \DomainException('Enter a valid invoice date.');
        }
        if ($amount <= 0 || $amount >= 1.0E+13) {
            throw new \DomainException('Enter a valid invoice amount.');
        }
        if ((string) @file_get_contents($file->getRealPath(), false, null, 0, 5) !== '%PDF-') {
            throw new \DomainException('Upload the invoice as a PDF file.');
        }

        $path = $file->storeAs("external-invoices/{$sheetId}", now()->format('YmdHis') . '-' . Str::random(8) . '.pdf', self::DISK);
        $terms = DB::table('external_contract_terms')->where('contract_id', $sheet->contract_id)->first();
        $row = [
            'invoice_no' => mb_substr($number, 0, 100), 'invoice_date' => $date, 'amount' => $amount,
            'file_path' => $path, 'file_name' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 0, 200),
            'status' => R::INV_UPLOADED, 'due_date' => R::dueDate((string) $sheet->approved_at, (int) ($terms->payment_term_days ?? 14)),
            'uploaded_by' => $actorId, 'rejected_at' => null, 'rejected_by' => null, 'reject_reason' => null, 'updated_at' => now(),
        ];

        $old = $invoice?->file_path;
        try {
            if ($invoice) {
                // Bersyarat pada status lama: invoice yang baru saja diverifikasi tidak boleh tertimpa unggahan lain.
                $moved = DB::table('external_invoices')->where('id', $invoice->id)->where('status', $invoice->status)->update($row);
                if ($moved !== 1) {
                    throw new \DomainException('This invoice was just changed by someone else. Reload the page.');
                }
            } else {
                DB::table('external_invoices')->insert($row + ['timesheet_id' => $sheetId, 'created_at' => now()]);
            }
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);   // jangan tinggalkan PDF yatim bila penulisan gagal
            throw $e;
        }
        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }

        $name = $this->consultantName((int) $sheet->employee_id);
        $this->workflow->notify($this->workflow->approversOf($sheetId, R::STAGE_INVOICE, (int) $sheet->employee_id), 'external_invoice_pending_verification',
            "{$name} uploaded invoice {$number} for timesheet {$sheet->sheet_no}.", "/general/external/timesheet/{$sheetId}", $actorId, (int) $sheet->employee_id);
    }

    /** @throws \DomainException */
    public function verify(int $sheetId, int $actorId): void
    {
        $inv = $this->mustMove($sheetId, R::INV_VERIFIED);
        $this->move($inv, ['status' => R::INV_VERIFIED, 'verified_at' => now(), 'verified_by' => $actorId, 'updated_at' => now()]);
        $this->tell($sheetId, 'external_invoice_verified', 'was verified. Payment follows the payment term.', $inv, $actorId);
    }

    /** @throws \DomainException */
    public function reject(int $sheetId, int $actorId, string $reason): void
    {
        $reason = trim($reason);
        $inv = $this->mustMove($sheetId, R::INV_REJECTED);
        if ($reason === '') {
            throw new \DomainException('Give a reason so the consultant can send a corrected invoice.');
        }
        $this->move($inv, [
            'status' => R::INV_REJECTED, 'rejected_at' => now(), 'rejected_by' => $actorId, 'reject_reason' => mb_substr($reason, 0, 2000), 'updated_at' => now(),
        ]);
        $this->tell($sheetId, 'external_invoice_rejected', 'was rejected: ' . $reason, $inv, $actorId);
    }

    /** @throws \DomainException */
    public function markPaid(int $sheetId, int $actorId, string $paidDate, string $reference): void
    {
        $inv = $this->mustMove($sheetId, R::INV_PAID);
        if (!\DateTime::createFromFormat('Y-m-d', $paidDate)) {
            throw new \DomainException('Enter the payment date.');
        }
        $this->move($inv, [
            'status' => R::INV_PAID, 'paid_date' => $paidDate, 'paid_by' => $actorId, 'payment_ref' => mb_substr(trim($reference), 0, 150) ?: null, 'updated_at' => now(),
        ]);
        $this->tell($sheetId, 'external_payment_paid', 'was paid on ' . date('d M Y', strtotime($paidDate)) . '.', $inv, $actorId);
    }

    /** Berkas invoice untuk disajikan (null bila tak ada). @return array{path:string,name:string}|null */
    public function file(int $sheetId): ?array
    {
        $inv = DB::table('external_invoices')->where('timesheet_id', $sheetId)->first();
        if (!$inv || !$inv->file_path || !Storage::disk(self::DISK)->exists($inv->file_path)) {
            return null;
        }

        return ['path' => $inv->file_path, 'name' => $inv->file_name ?: 'invoice.pdf'];
    }

    // ── internal ──────────────────────────────────────────────────────────

    /** Pembaruan bersyarat pada status yang dibaca tadi: dua orang yang bertindak bersamaan tidak saling menimpa. @throws \DomainException */
    private function move(object $inv, array $values): void
    {
        if (DB::table('external_invoices')->where('id', $inv->id)->where('status', $inv->status)->update($values) !== 1) {
            throw new \DomainException('This invoice was just changed by someone else. Reload the page.');
        }
    }

    private function mustMove(int $sheetId, string $to): object
    {
        $inv = DB::table('external_invoices')->where('timesheet_id', $sheetId)->first();
        if (!$inv) {
            throw new \DomainException('There is no invoice for this timesheet yet.');
        }
        if (!R::canInvoiceTransition($inv->status, $to)) {
            throw new \DomainException('This invoice is ' . strtolower(R::INVOICE_LABELS[$inv->status]) . '; that step is not possible now.');
        }

        return $inv;
    }

    private function tell(int $sheetId, string $type, string $what, object $inv, int $actorId): void
    {
        $employeeId = (int) DB::table('external_timesheets')->where('id', $sheetId)->value('employee_id');
        $this->workflow->notify([$employeeId], $type, "Your invoice {$inv->invoice_no} {$what}", "/general/external/my-timesheet?sheet={$sheetId}", $actorId);
    }

    private function consultantName(int $employeeId): string
    {
        return $this->workflow->names([$employeeId])[$employeeId] ?? 'A consultant';
    }
}
