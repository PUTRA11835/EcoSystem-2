<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\EmployeeHrProfile;
use App\Services\Contracts\ContractSigningService;
use App\Support\Contracts\ContractRules;
use App\Support\Contracts\SigningRules as S;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * My Profile → Contract, the signer's side: the employee reviews and signs their own contract, the approver reviews and signs
 * the contracts forwarded to them.
 *
 * No menu slug: what a person may open is decided by the contract (its employee, its approver) and by its stage, the way the
 * paystub page works. Legal can neither sign for them nor see their signature image here.
 */
class MyContractController extends Controller
{
    public function __construct(private ContractSigningService $signing) {}

    /** Contracts waiting for me as the approver, and the ones I already signed. */
    public function approvals()
    {
        $me = $this->me();
        $rows = EmployeeContract::where('approver_employee_id', $me)
            ->whereIn('sign_stage', [S::WITH_APPROVER, S::APPROVED, S::RELEASED])
            ->orderByRaw("sign_stage = 'with_approver' desc")->orderByDesc('sign_sent_at')->get()
            ->map(function (EmployeeContract $c) {
                $c->setAttribute('person', $this->nameOf((int) $c->employee_id));

                return $c;
            });

        return view('profile.contract-approvals', ['rows' => $rows, 'pending' => $rows->where('sign_stage', S::WITH_APPROVER)->count()]);
    }

    /** The contract in detail, with what the viewer may do about it. */
    public function show(int $contractId)
    {
        $c = EmployeeContract::findOrFail($contractId);
        $role = $this->roleOf($c);
        $file = $this->signing->fileOf($c);

        $components = ContractRules::cleanComponents($c->salary_components);
        $mySignature = null;
        $path = EmployeeHrProfile::signaturePathOf($this->me());
        if ($path) {
            $disk = Storage::disk(EmployeeHrProfile::FILE_DISK);
            $mySignature = 'data:' . $disk->mimeType($path) . ';base64,' . base64_encode($disk->get($path));
        }

        $stage = $c->sign_stage;
        $canSign = ($role === 'employee' && $stage === S::WITH_EMPLOYEE) || ($role === 'approver' && $stage === S::WITH_APPROVER);
        $employeeView = S::employeeView($stage, $c->sign_deadline?->toDateString());

        return view('profile.contract-sign', [
            'contract'   => $c,
            'role'       => $role,
            'person'     => $this->nameOf((int) $c->employee_id),
            'stage'      => $stage,
            'canSign'    => $canSign,
            'statusView' => $role === 'employee' ? $employeeView : ['label' => S::LEGAL_LABELS[$stage] ?? '', 'tone' => S::BADGES[$stage] ?? 'bg-gray-100 text-gray-600', 'action' => null],
            'daysLeft'   => $canSign ? S::daysLeft($c->sign_deadline?->toDateString()) : null,
            'hasFile'    => $file !== null,
            'pages'      => $file ? $this->signing->pageCount($c) : null,
            'signedCopy' => $file['signed'] ?? false,
            'canDownload' => $role === 'employee' && $stage === S::RELEASED && $file !== null,
            'components' => $components,
            'total'      => ContractRules::totalSalary($c->salary !== null ? (float) $c->salary : null, $components),
            'mySignature' => $mySignature,
            'events'     => $this->signing->events($c)->reverse()->values(),
            'back'       => $role === 'approver' ? route('profile.contract-approvals') : route('profile.my') . '?section=contract',
        ]);
    }

    /** The file under review, in the browser frame. Only the released contract may be downloaded, and only by its employee. */
    public function file(Request $request, int $contractId)
    {
        $c = EmployeeContract::findOrFail($contractId);
        $role = $this->roleOf($c);
        $file = $this->signing->fileOf($c);
        abort_if($file === null, 404);

        // The employee sees it from the moment it is sent (not while it is back with Legal for a change); the approver from the
        // moment it is forwarded to them.
        $open = $role === 'employee'
            ? [S::WITH_EMPLOYEE, S::WITH_LEGAL, S::WITH_APPROVER, S::APPROVED, S::RELEASED]
            : [S::WITH_APPROVER, S::APPROVED, S::RELEASED];
        abort_unless(in_array($c->sign_stage, $open, true), 403, 'This contract is not open to you at this stage.');

        $download = $request->boolean('download') && $role === 'employee' && $c->sign_stage === S::RELEASED;

        return response()->file($file['path'], [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline') . '; filename="' . $file['name'] . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    /** Sign: confirmed in the modal (the checkbox says every page was read). */
    public function sign(Request $request, int $contractId)
    {
        $c = EmployeeContract::findOrFail($contractId);
        $role = $this->roleOf($c);
        $request->validate(['confirm' => 'accepted'], ['confirm.accepted' => 'Confirm that you have read every page before you sign.']);

        try {
            $role === 'employee'
                ? $this->signing->employeeSign($c, $this->me())
                : $this->signing->approverSign($c, $this->me());
        } catch (\DomainException $e) {
            return redirect()->route('profile.contracts.show', $contractId)->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('profile.contracts.show', $contractId)->with('success', $role === 'employee'
            ? 'Signed. Your signature is on every page and the contract is with Legal now.'
            : 'Approved and signed. The contract is back with Legal to be printed.');
    }

    public function revision(Request $request, int $contractId)
    {
        $c = EmployeeContract::findOrFail($contractId);
        $this->roleOf($c);
        $data = $request->validate(['note' => 'required|string|max:1000']);

        try {
            $this->signing->requestRevision($c, $this->me(), $data['note']);
        } catch (\DomainException $e) {
            return redirect()->route('profile.contracts.show', $contractId)->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('profile.contracts.show', $contractId)->with('success', 'Sent back to Legal with your note.');
    }

    /** The signing record (who signed and when) — for the two people who signed, not for anyone else. */
    public function record(int $contractId)
    {
        $c = EmployeeContract::findOrFail($contractId);
        $this->roleOf($c);
        $legal = app(\App\Http\Controllers\HR_General\ContractSigningController::class);

        return Pdf::loadView('hr-general.contracts.signing-record', $legal->recordData($c))->setPaper('a4')->stream('signing-record-' . $contractId . '.pdf');
    }

    /**
     * The signing state of MY contracts, for the contract tab of My Profile: stage wording and the action link, per contract.
     */
    public function mine()
    {
        $me = $this->me();
        $today = now()->toDateString();

        $map = EmployeeContract::where('employee_id', $me)->whereNotNull('sign_stage')->get()
            ->mapWithKeys(function (EmployeeContract $c) use ($today) {
                $view = S::employeeView($c->sign_stage, $c->sign_deadline?->toDateString(), $today);

                return [$c->contract_id => [
                    'label'    => $view['label'],
                    'tone'     => $view['tone'],
                    'action'   => $view['action'],
                    'url'      => route('profile.contracts.show', $c->contract_id),
                    'deadline' => $c->sign_deadline?->format('d M Y'),
                    'days'     => S::daysLeft($c->sign_deadline?->toDateString(), $today),
                ]];
            });

        $approvals = EmployeeContract::where('approver_employee_id', $me)->where('sign_stage', S::WITH_APPROVER)->count();

        return response()->json(['success' => true, 'data' => $map, 'approvals' => $approvals, 'approvals_url' => route('profile.contract-approvals')]);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function me(): int
    {
        return (int) session('user.id');
    }

    /**
     * 'employee' when this is my contract, 'approver' when it is assigned to me for approval.
     * Anything else is a 404 — whoever guesses a number learns nothing about other people's contracts.
     */
    private function roleOf(EmployeeContract $c): string
    {
        $me = $this->me();
        abort_unless($me > 0 && (session('user.type') ?? 'employee') === 'employee', 403);

        if ((int) $c->employee_id === $me && $c->sign_stage !== null) {
            return 'employee';
        }
        if ((int) $c->approver_employee_id === $me && in_array($c->sign_stage, [S::WITH_APPROVER, S::APPROVED, S::RELEASED], true)) {
            return 'approver';
        }

        abort(404);
    }

    private function nameOf(int $employeeId): string
    {
        $b = DB::table('employee_basic_data')->where('employee_id', $employeeId)->first(['first_name', 'last_name']);

        return $b ? trim($b->first_name . ' ' . $b->last_name) : 'Employee';
    }
}
