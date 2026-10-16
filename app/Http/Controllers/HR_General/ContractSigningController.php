<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\EmployeeContract;
use App\Services\Contracts\ContractSigningService;
use App\Support\Contracts\SigningRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Legal's side of the contract signing workflow (HR → Contract, slug general.contracts.signing):
 * send the e-stamped PDF to the employee, forward it to the approver, release it, or withdraw it from signing.
 *
 * The employee and the approver sign from My Profile (MyContractController); nothing here lets Legal sign for them.
 * Only GET and POST (production blocks DELETE).
 */
class ContractSigningController extends Controller
{
    public function __construct(private ContractSigningService $signing) {}

    public function send(Request $request, int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $data = $request->validate([
            'sign_deadline'        => 'nullable|date',
            'note'                 => 'nullable|string|max:1000',
            'approver_employee_id' => 'nullable|integer',
        ]);

        return $this->run($contractId, fn () => $this->signing->send(
            $contract, (int) session('user.id'), $data['sign_deadline'] ?? null, $data['note'] ?? null,
            isset($data['approver_employee_id']) ? (int) $data['approver_employee_id'] : null
        ), 'Sent to ' . ($this->nameOfEmployee($contract) ?: 'the employee') . ' for review and signature.');
    }

    public function forward(Request $request, int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $data = $request->validate(['approver_employee_id' => 'required|integer', 'note' => 'nullable|string|max:1000']);

        return $this->run($contractId, fn () => $this->signing->forward(
            $contract, (int) session('user.id'), (int) $data['approver_employee_id'], $data['note'] ?? null
        ), 'Forwarded to the approver.');
    }

    public function release(int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);

        return $this->run($contractId, fn () => $this->signing->release($contract, (int) session('user.id')),
            'Released: the employee can now download the approved contract.');
    }

    public function cancel(Request $request, int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->run($contractId, fn () => $this->signing->cancel($contract, (int) session('user.id'), $data['note'] ?? null),
            'Withdrawn from signing. The stamped copy can be changed again.');
    }

    /** The copy with the signatures on every page (else the stamped original) — for Legal, who prints it. Carries the salary. */
    public function file(Request $request, int $contractId)
    {
        $employee = \App\Models\Employee::find(session('user.id'));
        abort_unless($employee && $employee->canAccessMenu('general.contracts.salary'), 403, 'Only holders of "View Salary" can open a contract.');

        $contract = EmployeeContract::findOrFail($contractId);
        $file = $this->signing->fileOf($contract);
        abort_if($file === null, 404);

        return response()->file($file['path'], [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $file['name'] . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /** The signing record as a PDF: who did what and when, the file hash, and the signatures. */
    public function record(int $contractId)
    {
        $contract = EmployeeContract::findOrFail($contractId);

        return Pdf::loadView('hr-general.contracts.signing-record', $this->recordData($contract))
            ->setPaper('a4')->stream('signing-record-' . $contractId . '.pdf');
    }

    /** Shared with My Profile (the employee and the approver may download the record of their own contract). */
    public function recordData(EmployeeContract $contract): array
    {
        $basic = \Illuminate\Support\Facades\DB::table('employee_basic_data')->where('employee_id', $contract->employee_id)->first(['first_name', 'last_name']);

        return [
            'contract' => $contract,
            'employeeName' => $basic ? trim($basic->first_name . ' ' . $basic->last_name) : null,
            // Only the signatures that were really applied are shown on the record.
            'signatures' => [
                'employee' => $contract->employee_signed_at ? $this->signatureUri((int) $contract->employee_id) : null,
                'approver' => $contract->approver_signed_at && $contract->approver_employee_id ? $this->signatureUri((int) $contract->approver_employee_id) : null,
            ],
            'events'   => $this->signing->events($contract)->reverse()->values(),
            'labels'   => [
                'sent' => 'Sent to the employee', 'employee_signed' => 'Signed by the employee', 'forwarded' => 'Forwarded to the approver',
                'approver_signed' => 'Approved and signed by the approver', 'released' => 'Released to the employee',
                'revision' => 'Change requested', 'cancelled' => 'Withdrawn from signing',
            ],
            'stageLabel' => SigningRules::LEGAL_LABELS[$contract->sign_stage] ?? 'Not in the signing process',
        ];
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function signatureUri(int $employeeId): ?string
    {
        $path = \App\Models\EmployeeHrProfile::signaturePathOf($employeeId);
        if (!$path) {
            return null;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk(\App\Models\EmployeeHrProfile::FILE_DISK);

        return 'data:' . $disk->mimeType($path) . ';base64,' . base64_encode($disk->get($path));
    }

    private function run(int $contractId, \Closure $action, string $success)
    {
        try {
            $action();
        } catch (\DomainException $e) {
            return redirect()->route('general.contracts.document', $contractId)->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('general.contracts.document', $contractId)->with('success', $success);
    }

    private function nameOfEmployee(EmployeeContract $contract): ?string
    {
        $basic = \Illuminate\Support\Facades\DB::table('employee_basic_data')->where('employee_id', $contract->employee_id)->first(['first_name', 'last_name']);

        return $basic ? trim($basic->first_name . ' ' . $basic->last_name) : null;
    }
}
