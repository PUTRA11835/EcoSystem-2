<?php

namespace App\Services\Letters;

use App\Models\Employee;
use App\Models\EmployeeHrProfile;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterCode;
use App\Models\Letters\LetterRequest;
use App\Models\Letters\LetterSignatory;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What happens to a letter of the Letter Templates hub, whichever tab it is
 * done from: generated (numbered) → signed with the master-data signature →
 * sent; changed and generated again; voided. And an employee's request,
 * from submitted to done.
 */
class LetterService
{
    public function __construct(
        private LetterNumberService $numbers,
        private LetterMailer $mailer,
    ) {}

    /**
     * Saves a new template or custom letter and gives it its number — the one
     * typed, or the next of the outgoing counter. With $request, the letter
     * answers that employee request, which is then in progress.
     */
    public function generate(array $data, int $byEmployeeId, ?LetterRequest $request = null): Letter
    {
        return DB::transaction(function () use ($data, $byEmployeeId, $request) {
            $date = Carbon::parse($data['letter_date']);
            $typed = trim((string) ($data['letter_number'] ?? ''));

            if ($typed !== '') {
                [$number, $sequence] = [$typed, null];
            } else {
                [$number, $sequence] = $this->numbers->takeOutgoing($date, $this->codeOf($data['letter_code_id'] ?? null), $data['language']);
            }

            $letter = new Letter([
                ...$data,
                'direction'         => Letter::DIRECTION_OUTGOING,
                'letter_number'     => $number,
                'number_sequence'   => $sequence,
                'number_year'       => $date->year,
                'letter_request_id' => $request?->id,
                'created_by'        => $byEmployeeId,
                'updated_by'        => $byEmployeeId,
            ]);
            $letter->status = 'active';
            $letter->save();

            if ($request) {
                $request->forceFill([
                    'status' => LetterRequest::IN_PROGRESS, 'letter_id' => $letter->id,
                    'handled_by' => $byEmployeeId, 'handled_at' => now(),
                ])->save();
            }

            return $letter;
        });
    }

    /**
     * Saves a changed letter. A number generated from the format follows the
     * letter's code, language and date (same running number, same year); a
     * number typed by hand is kept. What was signed is no longer what the
     * letter says, so a signature comes off.
     *
     * @return bool whether a signature was removed
     */
    public function regenerate(Letter $letter, array $data, int $byEmployeeId): bool
    {
        return DB::transaction(function () use ($letter, $data, $byEmployeeId) {
            $date = Carbon::parse($data['letter_date']);
            $typed = trim((string) ($data['letter_number'] ?? ''));
            $generated = $letter->number_sequence !== null && $letter->letter_number === $this->numbers->outgoingNumber(
                $letter->letter_date, $letter->number_sequence, $letter->code?->code, $letter->languageCode()
            );

            if ($typed !== '' && $typed !== $letter->letter_number) {
                $data['letter_number'] = $typed;
                $data['number_sequence'] = null;
            } elseif ($generated && $date->year === (int) $letter->number_year) {
                $renumbered = $this->numbers->outgoingNumber($date, $letter->number_sequence, $this->codeOf($data['letter_code_id'] ?? null), $data['language']);
                $data['letter_number'] = $this->numbers->outgoingNumberUsed($renumbered, $letter->id) ? $letter->letter_number : $renumbered;
            } else {
                $data['letter_number'] = $letter->letter_number;
            }

            $wasSigned = $letter->isSigned();
            $letter->fill([...$data, 'updated_by' => $byEmployeeId]);
            $changed = $letter->isDirty();
            $letter->save();

            if ($wasSigned && $changed) {
                $letter->removeSignature();

                return true;
            }

            return false;
        });
    }

    /** Why the letter cannot be signed yet, or null. */
    public function signingProblem(Letter $letter): ?string
    {
        if (!$letter->signatory_employee_id) {
            return 'Pick the signatory from the employee list (edit the letter) before signing it — the signature is taken from their master data.';
        }

        // Settings → Signers decides who may sign; someone taken off that list since the letter was written cannot.
        if (!self::canSign((int) $letter->signatory_employee_id)) {
            return "{$letter->signatory_name} can no longer sign letters (Letter Templates → Settings → Signers). Edit the letter and pick another signer.";
        }

        if (!EmployeeHrProfile::signaturePathOf($letter->signatory_employee_id)) {
            return "{$letter->signatory_name} has no signature in the employee master data yet. Upload it there first, then sign the letter.";
        }

        return null;
    }

    public function sign(Letter $letter, int $byEmployeeId): void
    {
        $letter->signWithMasterSignature($byEmployeeId);
    }

    /**
     * Emails the signed letter. The outcome is kept on the letter: delivered
     * (no Resend from then on) or failed with its reason (Resend offered).
     *
     * @return string|null null when delivered, else why it failed
     */
    public function send(Letter $letter, string $subject, string $body): ?string
    {
        try {
            $this->mailer->sendLetter($letter, $letter->toPdf()->output(), $subject, $body);
            $letter->forceFill(['sent_at' => now(), 'email_status' => Letter::EMAIL_SENT, 'email_error' => null])->save();

            return null;
        } catch (\Throwable $e) {
            Log::error('Letters: failed to email a letter', ['letter_id' => $letter->id, 'error' => $e->getMessage()]);
            $letter->forceFill(['email_status' => Letter::EMAIL_FAILED, 'email_error' => $e->getMessage()])->save();

            return $e->getMessage();
        }
    }

    /**
     * Finishes an employee's request: it is done — the letter is in their My
     * Letter Requests whatever happens next — and the letter is emailed to
     * them. A failed email leaves the request done, with Resend for HR.
     *
     * @return string|null null when the email was delivered, else why it failed
     */
    public function complete(LetterRequest $request, string $subject, string $body, int $byEmployeeId): ?string
    {
        $letter = $request->activeLetter();

        $request->forceFill(['status' => LetterRequest::DONE, 'handled_by' => $byEmployeeId, 'handled_at' => now()])->save();
        $error = $this->send($letter, $subject, $body);

        $this->notifyEmployee($request, 'done', "Your {$request->typeLabel()} is ready — download it from My Letter Requests."
            . ($error ? '' : ' It was also emailed to you.'));

        return $error;
    }

    public function reject(LetterRequest $request, string $reason, int $byEmployeeId): void
    {
        $request->forceFill([
            'status' => LetterRequest::REJECTED, 'reject_reason' => $reason,
            'handled_by' => $byEmployeeId, 'handled_at' => now(),
        ])->save();

        $this->notifyEmployee($request, 'rejected', "Your request for a {$request->typeLabel()} was rejected: {$reason}");
    }

    /** A numbered letter is never deleted: it stays in the register, marked void with the reason. */
    public function void(Letter $letter, string $reason, int $byEmployeeId): void
    {
        $letter->forceFill(['status' => 'void', 'void_reason' => $reason, 'voided_by' => $byEmployeeId, 'voided_at' => now()])->save();

        // A request whose letter was voided goes back to HR: it needs a new letter.
        if ($letter->request && $letter->request->isOpen()) {
            $letter->request->forceFill(['status' => LetterRequest::PENDING, 'letter_id' => null])->save();
        }
    }

    // ── Notifications ────────────────────────────────────────────────────────

    /** Tells everyone who can open the Requests tab that an employee asked for a letter. */
    public function notifyHrOfRequest(LetterRequest $request): void
    {
        $hr = DB::table('employee_role_assignment as a')
            ->join('role_menu as rm', 'rm.role_id', '=', 'a.role_id')
            ->join('menu as m', 'm.id', '=', 'rm.menu_id')
            ->join('employee as e', 'e.employee_id', '=', 'a.employee_id')
            ->where('m.slug', 'general.letters.requests')
            ->where('rm.can_view', true)
            ->where('e.is_active', 1)
            ->where('a.employee_id', '!=', $request->employee_id)
            ->distinct()
            ->pluck('a.employee_id');

        foreach ($hr as $employeeId) {
            $this->notify((int) $employeeId, $request, 'letter_request_new',
                "{$request->employeeName()} asked for a {$request->typeLabel()}: {$request->purpose}", '/general/letters/requests');
        }
    }

    private function notifyEmployee(LetterRequest $request, string $outcome, string $message): void
    {
        $this->notify($request->employee_id, $request, "letter_request_{$outcome}", $message, '/general/my-letter-requests');
    }

    /** A notification that fails is logged, never allowed to undo what was done. */
    private function notify(int $employeeId, LetterRequest $request, string $type, string $message, string $link): void
    {
        try {
            Notification::create([
                'employee_id'      => $employeeId,
                'type'             => $type,
                'from_employee_id' => session('user.id'),
                'preview'          => mb_strimwidth($message, 0, 250, '…'),
                'link'             => $link,
            ]);
        } catch (\Throwable $e) {
            Log::error('Letters: failed to send a notification', ['letter_request_id' => $request->id, 'type' => $type, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Active employees of the master data for the pickers of letters — who a
     * letter is about, who signs it: name, employee ID, position, work email
     * and whether their signature is in the master data yet.
     */
    public static function employeeOptions(): Collection
    {
        $withSignature = array_flip(EmployeeHrProfile::employeeIdsWithSignature());
        $workEmails = DB::table('employee_address')->whereNotNull('email_work')->where('email_work', '!=', '')
            ->pluck('email_work', 'employee_id');
        $loginEmails = DB::table('auth_users')->whereNotNull('employee_id')->pluck('email', 'employee_id');

        return Employee::with('basicData:basic_data_id,employee_id,first_name,last_name,nick_name,position')
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->map(fn (Employee $employee) => [
                'id'            => $employee->employee_id,
                'eci'           => $employee->eci,
                'name'          => trim($employee->basicData?->full_name ?? '') ?: ($employee->basicData?->nick_name ?: $employee->eci),
                'position'      => $employee->basicData?->position ?? '',
                'email'         => $workEmails[$employee->employee_id] ?? $loginEmails[$employee->employee_id] ?? null,
                'has_signature' => isset($withSignature[$employee->employee_id]),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /** @return array<int, int[]> role id → the active employees holding it */
    public static function roleMembers(array $roleIds): array
    {
        return DB::table('employee_role_assignment as a')
            ->join('employee as e', 'e.employee_id', '=', 'a.employee_id')
            ->whereIn('a.role_id', $roleIds)
            ->where('e.is_active', 1)
            ->get(['a.role_id', 'a.employee_id'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->all();
    }

    /**
     * Who can sign letters, by role — Settings → Signers: for each role, its
     * name and the people of it who may sign (everyone holding it, or the
     * ones picked), with the position printed under their signature. A person
     * picked by name must still hold the role.
     *
     * @return Collection<int, array{id: int, name: string, people: array}>
     */
    public static function signingRoles(): Collection
    {
        $entries = LetterSignatory::active()->with('role')->ordered()->get();
        $members = self::roleMembers($entries->pluck('role_id')->unique()->all());
        $employees = self::employeeOptions()->keyBy('id');

        return $entries->groupBy('role_id')->map(function (Collection $rows, $roleId) use ($members, $employees) {
            $holders = $members[$roleId] ?? [];
            $whole = $rows->first(fn (LetterSignatory $row) => $row->isWholeRole());
            $picked = $rows->reject(fn (LetterSignatory $row) => $row->isWholeRole())->keyBy('employee_id');
            $ids = $whole ? $holders : array_values(array_intersect($picked->keys()->map(fn ($id) => (int) $id)->all(), $holders));

            $people = collect($ids)
                ->map(fn (int $id) => $employees->get($id))
                ->filter()
                ->map(fn (array $person) => [
                    ...$person,
                    'position' => $picked->get($person['id'])?->title ?: ($whole?->title ?: $person['position']),
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            return ['id' => (int) $roleId, 'name' => $rows->first()->role?->name ?? 'Role #' . $roleId, 'people' => $people->all()];
        })
            ->filter(fn (array $role) => count($role['people']) > 0)
            ->sortBy('name')
            ->values();
    }

    /**
     * Everyone who can be picked as the signatory of a letter of the hub
     * (Settings → Signers), once each: same shape as employeeOptions(), plus
     * `roles` (the role ids they may sign under) and the position printed
     * under their signature.
     */
    public static function signatoryOptions(): Collection
    {
        $people = [];
        foreach (self::signingRoles() as $role) {
            foreach ($role['people'] as $person) {
                $people[$person['id']] ??= [...$person, 'roles' => []];
                $people[$person['id']]['roles'][] = $role['id'];
            }
        }

        return collect($people)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public static function canSign(?int $employeeId): bool
    {
        return $employeeId !== null && self::signatoryOptions()->contains('id', $employeeId);
    }

    private function codeOf(?int $codeId): ?string
    {
        return $codeId ? LetterCode::whereKey($codeId)->value('code') : null;
    }
}
