<?php

namespace App\Services\Contracts;

use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\EmployeeHrProfile;
use App\Models\Notification;
use App\Services\Letters\LetterService;
use App\Support\Contracts\SigningRules as S;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The signing workflow of a contract after it has been e-stamped by Legal (see SigningRules for the stages).
 *
 * Legal (send, forward, release, withdraw) is checked by the route (slug general.contracts.signing); the employee and the approver
 * are checked here against the contract itself, so no menu slug is needed for them. Every step writes a contract_sign_events row
 * and tells the next person through the notification bell.
 *
 * Failures a person can fix are thrown as \DomainException (English text shown as it is).
 */
class ContractSigningService
{
    public function __construct(private ContractSignatureStamper $stamper) {}

    // ── Legal ─────────────────────────────────────────────────────────────

    /**
     * Send the stamped contract to the employee to review and sign.
     *
     * @throws \DomainException
     */
    public function send(EmployeeContract $c, int $actorId, ?string $deadline, ?string $note, ?int $approverId = null): EmployeeContract
    {
        $this->assertCan($c, S::ACTION_SEND, 'send this contract to the employee');

        if (($c->lifecycle_status ?? '') === 'draft') {
            throw new \DomainException('A Draft cannot be sent for signing. Set the contract to Active first.');
        }
        if (!$this->hasStampedFile($c)) {
            throw new \DomainException('Upload the e-stamped PDF first (Contract document → Upload stamped copy).');
        }
        if ($error = S::deadlineError($deadline)) {
            throw new \DomainException($error);
        }
        if (!EmployeeHrProfile::signaturePathOf((int) $c->employee_id)) {
            throw new \DomainException('The employee has no signature in the master data yet (My Profile → HR information), so they cannot sign.');
        }

        $approverId ??= $this->templateApprover($c);
        [$approverName, $approverTitle] = $approverId ? $this->signerIdentity($approverId) : [null, null];

        return DB::transaction(function () use ($c, $actorId, $deadline, $note, $approverId, $approverName, $approverTitle) {
            $this->dropSignedPdf($c);
            $c->update([
                'sign_stage'           => S::WITH_EMPLOYEE,
                'sign_deadline'        => $deadline ?: null,
                'sign_sent_at'         => now(),
                'sign_sent_by'         => $actorId,
                'sign_file_hash'       => hash_file('sha256', Storage::disk('local')->path($c->signed_file_path)),
                'employee_signed_at'   => null,
                'approver_employee_id' => $approverId,
                'approver_name'        => $approverName,
                'approver_title'       => $approverTitle,
                'approver_signed_at'   => null,
                'released_at'          => null,
                'released_by'          => null,
                'signed_pdf_path'      => null,
            ]);
            $this->log($c, 'sent', $actorId, $note, ['deadline' => $deadline, 'file_hash' => $c->sign_file_hash]);
            $this->notify((int) $c->employee_id, 'contract_sign_request',
                "Contract {$c->contract_number} is waiting for your review and signature" . ($deadline ? ' before ' . $this->day($deadline) : '') . '.',
                $this->reviewLink($c), $actorId);

            return $c->refresh();
        });
    }

    /**
     * Forward the employee-signed contract to the approver of its template.
     *
     * @throws \DomainException
     */
    public function forward(EmployeeContract $c, int $actorId, int $approverId, ?string $note = null): EmployeeContract
    {
        $this->assertCan($c, S::ACTION_FORWARD, 'forward this contract');

        if (!EmployeeHrProfile::signaturePathOf($approverId)) {
            throw new \DomainException('The approver has no signature in the master data yet, so they cannot sign.');
        }
        if ($approverId === (int) $c->employee_id) {
            throw new \DomainException('The employee cannot approve their own contract.');
        }
        [$name, $title] = $this->signerIdentity($approverId);

        return DB::transaction(function () use ($c, $actorId, $approverId, $name, $title, $note) {
            $c->update(['sign_stage' => S::WITH_APPROVER, 'approver_employee_id' => $approverId, 'approver_name' => $name, 'approver_title' => $title]);
            $this->log($c, 'forwarded', $actorId, $note, ['approver_id' => $approverId, 'approver' => $name]);
            $this->notify($approverId, 'contract_sign_forwarded',
                "Contract {$c->contract_number} needs your review and signature.", $this->reviewLink($c), $actorId);

            return $c->refresh();
        });
    }

    /** @throws \DomainException */
    public function release(EmployeeContract $c, int $actorId): EmployeeContract
    {
        $this->assertCan($c, S::ACTION_RELEASE, 'release this contract');

        return DB::transaction(function () use ($c, $actorId) {
            $c->update(['sign_stage' => S::RELEASED, 'released_at' => now(), 'released_by' => $actorId]);
            $this->log($c, 'released', $actorId);
            $this->notify((int) $c->employee_id, 'contract_released',
                "Your contract {$c->contract_number} is approved and ready to download.", $this->reviewLink($c), $actorId);

            return $c->refresh();
        });
    }

    /** Take the contract out of the signing process; what was signed is dropped and Legal can start again. @throws \DomainException */
    public function cancel(EmployeeContract $c, int $actorId, ?string $note = null): EmployeeContract
    {
        $this->assertCan($c, S::ACTION_CANCEL, 'withdraw this contract from signing');

        return DB::transaction(function () use ($c, $actorId, $note) {
            $this->dropSignedPdf($c);
            $c->update([
                'sign_stage' => null, 'sign_deadline' => null, 'sign_sent_at' => null, 'sign_sent_by' => null, 'sign_file_hash' => null,
                'employee_signed_at' => null, 'approver_signed_at' => null, 'released_at' => null, 'released_by' => null, 'signed_pdf_path' => null,
            ]);
            $this->log($c, 'cancelled', $actorId, $note);

            return $c->refresh();
        });
    }

    // ── Employee / approver ───────────────────────────────────────────────

    /**
     * The employee signs: their signature from the master data goes on every page, and the contract returns to Legal.
     *
     * @throws \DomainException
     */
    public function employeeSign(EmployeeContract $c, int $employeeId): EmployeeContract
    {
        if ((int) $c->employee_id !== $employeeId) {
            throw new \DomainException('This is not your contract.');
        }
        $this->assertCan($c, S::ACTION_EMPLOYEE_SIGN, 'sign this contract');
        if (!EmployeeHrProfile::signaturePathOf($employeeId)) {
            throw new \DomainException('Your signature is not in the master data yet. Ask HR to add it to your HR information.');
        }

        return DB::transaction(function () use ($c, $employeeId) {
            $c->employee_signed_at = now();
            $result = $this->buildSignedPdf($c);
            $c->update(['sign_stage' => S::WITH_LEGAL, 'employee_signed_at' => $c->employee_signed_at, 'signed_pdf_path' => $result['path']]);
            $this->log($c, 'employee_signed', $employeeId, null, ['overlay' => $result['path'] !== null, 'pages' => $result['pages'], 'reason' => $result['reason']]);
            if ($c->sign_sent_by) {
                $this->notify((int) $c->sign_sent_by, 'contract_signed',
                    "{$this->nameOf($employeeId)} signed contract {$c->contract_number}. It is waiting for you to forward it to the approver.",
                    route('general.contracts.document', $c->contract_id), $employeeId);
            }

            return $c->refresh();
        });
    }

    /**
     * The approver signs: the approval signature is added to every page too, and the contract returns to Legal to be printed.
     *
     * @throws \DomainException
     */
    public function approverSign(EmployeeContract $c, int $approverId): EmployeeContract
    {
        if ((int) $c->approver_employee_id !== $approverId) {
            throw new \DomainException('This contract is not assigned to you for approval.');
        }
        $this->assertCan($c, S::ACTION_APPROVER_SIGN, 'approve this contract');
        if (!EmployeeHrProfile::signaturePathOf($approverId)) {
            throw new \DomainException('Your signature is not in the master data yet. Ask HR to add it to your HR information.');
        }

        return DB::transaction(function () use ($c, $approverId) {
            $c->approver_signed_at = now();
            $result = $this->buildSignedPdf($c);
            $c->update(['sign_stage' => S::APPROVED, 'approver_signed_at' => $c->approver_signed_at, 'signed_pdf_path' => $result['path'] ?? $c->signed_pdf_path]);
            $this->log($c, 'approver_signed', $approverId, null, ['overlay' => $result['path'] !== null, 'pages' => $result['pages'], 'reason' => $result['reason']]);
            if ($c->sign_sent_by) {
                $this->notify((int) $c->sign_sent_by, 'contract_signed',
                    "Contract {$c->contract_number} is approved and signed. It is ready to be printed and released to the employee.",
                    route('general.contracts.document', $c->contract_id), $approverId);
            }

            return $c->refresh();
        });
    }

    /**
     * The employee or the approver sends it back to Legal with a reason.
     *
     * @throws \DomainException
     */
    public function requestRevision(EmployeeContract $c, int $actorId, string $note): EmployeeContract
    {
        $note = trim($note);
        if ($note === '') {
            throw new \DomainException('Say what has to be changed.');
        }
        $isEmployee = (int) $c->employee_id === $actorId && $c->sign_stage === S::WITH_EMPLOYEE;
        $isApprover = (int) $c->approver_employee_id === $actorId && $c->sign_stage === S::WITH_APPROVER;
        if (!$isEmployee && !$isApprover) {
            throw new \DomainException('This contract is not waiting for you.');
        }
        $this->assertCan($c, S::ACTION_REVISION, 'send this contract back');

        return DB::transaction(function () use ($c, $actorId, $note, $isEmployee) {
            $this->dropSignedPdf($c);
            $c->update(['sign_stage' => S::REVISION, 'employee_signed_at' => null, 'approver_signed_at' => null, 'signed_pdf_path' => null]);
            $this->log($c, 'revision', $actorId, $note, ['by' => $isEmployee ? 'employee' : 'approver']);
            if ($c->sign_sent_by) {
                $this->notify((int) $c->sign_sent_by, 'contract_revision',
                    "{$this->nameOf($actorId)} asked for a change to contract {$c->contract_number}: " . mb_substr($note, 0, 140),
                    route('general.contracts.document', $c->contract_id), $actorId);
            }

            return $c->refresh();
        });
    }

    // ── Files and trail ───────────────────────────────────────────────────

    /**
     * The file a person opens: the signed copy when there is one, else the stamped original.
     *
     * @return array{path:string,name:string,signed:bool}|null
     */
    public function fileOf(EmployeeContract $c): ?array
    {
        $disk = Storage::disk('local');
        if ($c->signed_pdf_path && $disk->exists($c->signed_pdf_path)) {
            return ['path' => $disk->path($c->signed_pdf_path), 'name' => $this->downloadName($c, '-signed'), 'signed' => true];
        }
        if ($this->hasStampedFile($c)) {
            return ['path' => $disk->path($c->signed_file_path), 'name' => $this->downloadName($c, ''), 'signed' => false];
        }

        return null;
    }

    /** Page count of the file under review (null when the reader cannot tell). */
    public function pageCount(EmployeeContract $c): ?int
    {
        $file = $this->fileOf($c);

        return $file ? $this->stamper->pageCount((string) file_get_contents($file['path'])) : null;
    }

    /** @return Collection<int,object> newest first */
    public function events(EmployeeContract $c): Collection
    {
        return DB::table('contract_sign_events')->where('contract_id', $c->contract_id)->orderByDesc('id')->get()
            ->map(function ($e) {
                $e->meta = $e->meta ? json_decode($e->meta, true) : [];

                return $e;
            });
    }

    public function hasStampedFile(EmployeeContract $c): bool
    {
        return $c->signed_file_path && Storage::disk('local')->exists($c->signed_file_path);
    }

    /** The approver set on the contract's template (null when none is set). */
    public function templateApprover(EmployeeContract $c): ?int
    {
        $id = $c->template_id ? ContractTemplate::whereKey($c->template_id)->value('approver_employee_id') : null;

        return $id ? (int) $id : null;
    }

    // ── internals ─────────────────────────────────────────────────────────

    /** @throws \DomainException */
    private function assertCan(EmployeeContract $c, string $action, string $what): void
    {
        if (!S::can($c->sign_stage, $action)) {
            $where = S::LEGAL_LABELS[$c->sign_stage] ?? 'not in the signing process';
            throw new \DomainException("You cannot {$what} now: it is {$where}.");
        }
    }

    /**
     * Draw the signatures that exist so far on every page of the stamped original, store the result.
     *
     * @return array{path:?string,pages:int,reason:?string}
     */
    private function buildSignedPdf(EmployeeContract $c): array
    {
        $original = $c->signed_file_path ? Storage::disk('local')->get($c->signed_file_path) : null;
        if (!$original) {
            return ['path' => null, 'pages' => 0, 'reason' => 'There is no stamped PDF.'];
        }

        $signers = [];
        if ($c->employee_signed_at) {
            $signers[] = $this->signerImage((int) $c->employee_id, $this->nameOf((int) $c->employee_id), $c->employee_signed_at);
        }
        if ($c->approver_signed_at && $c->approver_employee_id) {
            $signers[] = $this->signerImage((int) $c->approver_employee_id, $c->approver_name ?: $this->nameOf((int) $c->approver_employee_id), $c->approver_signed_at);
        }
        $signers = array_values(array_filter($signers));

        $result = $this->stamper->overlay($original, $signers);
        if ($result['bytes'] === null) {
            return ['path' => null, 'pages' => 0, 'reason' => $result['reason']];
        }

        $this->dropSignedPdf($c);
        $path = 'contracts/' . $c->contract_id . '/signed-' . now()->format('YmdHis') . '-' . substr(md5((string) microtime(true)), 0, 6) . '.pdf';
        Storage::disk('local')->put($path, $result['bytes']);

        return ['path' => $path, 'pages' => $result['pages'], 'reason' => null];
    }

    /** @return array{image:string,label:string}|null */
    private function signerImage(int $employeeId, string $name, $at): ?array
    {
        $path = EmployeeHrProfile::signaturePathOf($employeeId);
        if (!$path) {
            return null;
        }

        return ['image' => Storage::disk(EmployeeHrProfile::FILE_DISK)->get($path), 'label' => $name . ' - ' . $at->format('d M Y H:i')];
    }

    private function dropSignedPdf(EmployeeContract $c): void
    {
        if ($c->signed_pdf_path) {
            Storage::disk('local')->delete($c->signed_pdf_path);
        }
    }

    /** @return array{0:?string,1:?string} name and title of a person from the signer list, else from the employee data */
    private function signerIdentity(int $employeeId): array
    {
        $person = LetterService::signatoryOptions()->firstWhere('id', $employeeId);
        if ($person) {
            return [$person['name'], $person['position'] ?? null];
        }
        $basic = DB::table('employee_basic_data')->where('employee_id', $employeeId)->first(['first_name', 'last_name', 'position']);

        return [$basic ? trim($basic->first_name . ' ' . $basic->last_name) : null, $basic->position ?? null];
    }

    private function nameOf(int $employeeId): string
    {
        $basic = DB::table('employee_basic_data')->where('employee_id', $employeeId)->first(['first_name', 'last_name']);

        return $basic ? trim($basic->first_name . ' ' . $basic->last_name) : 'Someone';
    }

    private function log(EmployeeContract $c, string $event, ?int $actorId, ?string $note = null, array $meta = []): void
    {
        DB::table('contract_sign_events')->insert([
            'contract_id'       => $c->contract_id,
            'event'             => $event,
            'actor_employee_id' => $actorId,
            'actor_name'        => $actorId ? $this->nameOf($actorId) : null,
            'note'              => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 1000) : null,
            'meta'              => $meta ? json_encode($meta) : null,
            'created_at'        => now(),
        ]);
    }

    private function notify(int $employeeId, string $type, string $message, string $link, ?int $fromId = null): void
    {
        try {
            Notification::create([
                'employee_id'      => $employeeId,
                'type'             => $type,
                'from_employee_id' => $fromId,
                'from_name'        => $fromId ? $this->nameOf($fromId) : null,
                'preview'          => mb_substr($message, 0, 250),
                'link'             => $link,
            ]);
        } catch (\Throwable $e) {
            // A failed bell must never undo a signature.
            \Illuminate\Support\Facades\Log::warning('Contract signing notification failed', ['error' => $e->getMessage(), 'type' => $type]);
        }
    }

    /** Where a signer opens the contract (the employee and the approver use the same page). */
    private function reviewLink(EmployeeContract $c): string
    {
        return route('profile.contracts.show', $c->contract_id);
    }

    private function day(string $date): string
    {
        return \Carbon\Carbon::parse($date)->format('d M Y');
    }

    private function downloadName(EmployeeContract $c, string $suffix): string
    {
        return str_replace(['/', '\\'], '-', (string) $c->contract_number) . $suffix . '.pdf';
    }
}
