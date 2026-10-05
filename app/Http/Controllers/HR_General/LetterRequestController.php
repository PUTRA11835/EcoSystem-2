<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterRequest;
use App\Models\Letters\LetterRequestType;
use App\Services\Letters\LetterMailer;
use App\Services\Letters\LetterService;
use Illuminate\Http\Request;

/**
 * HR & General → Letter Templates → Requests (slug general.letters.requests):
 * the letters employees asked for in My Letter Requests.
 *
 *   pending ──Process──▶ Create Letter, pre-filled ──Generate──▶ in progress
 *   in progress ──Sign (master-data signature)──▶ ──Complete & Send──▶ done
 *   pending / in progress ──Reject (with reason)──▶ rejected
 *
 * Done means done: the employee can download the letter whatever happens to
 * the email. A failed email is offered for Resend; a delivered one is never
 * sent twice.
 *
 * V see the requests · E process / reject / sign / complete & send / resend.
 * Processing also needs the Create box of Create Letter.
 */
class LetterRequestController extends Controller
{
    use HandlesRecruitmentTables;

    public function __construct(private LetterService $letters) {}

    public function index(Request $request, LetterMailer $mailer)
    {
        $filters = [
            // Opens on what is still waiting; "all" shows the history too.
            'status'   => (string) $request->query('status', 'open'),
            'type'     => (string) $request->query('type'),
            'employee' => trim((string) $request->query('employee')),
        ];
        $perPage = $this->perPage($request);

        $requests = LetterRequest::with(['employee.basicData', 'letter', 'handler.basicData'])
            ->when($filters['status'] === 'open', fn ($q) => $q->open())
            ->when(isset(LetterRequest::STATUSES[$filters['status']]), fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['type'] === 'other', fn ($q) => $q->where('is_other', true))
            ->when(ctype_digit($filters['type']), fn ($q) => $q->where('request_type_id', (int) $filters['type']))
            ->when($filters['employee'] !== '', fn ($q) => $q->whereHas('employee', fn ($e) => $e
                ->where('eci', 'like', "%{$filters['employee']}%")
                ->orWhereHas('basicData', fn ($b) => $b
                    ->where('first_name', 'like', "%{$filters['employee']}%")
                    ->orWhere('last_name', 'like', "%{$filters['employee']}%")
                    ->orWhere('nick_name', 'like', "%{$filters['employee']}%"))))
            ->orderByRaw("FIELD(status, 'pending', 'in_progress', 'done', 'rejected', 'cancelled')")
            ->orderByRaw('needed_by IS NULL, needed_by')
            ->orderBy('created_at')
            ->paginate($perPage)->withQueryString();

        return view('hr-general.letters.requests', [
            'requests'       => $requests,
            'filters'        => $filters,
            'hasFilters'     => $filters['status'] !== 'open' || $filters['type'] !== '' || $filters['employee'] !== '',
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            // "open" (pending + in progress) is the filter's default row, see the view.
            'statusOptions'  => ['all' => 'All, with history'] + LetterRequest::STATUSES,
            'typeOptions'    => LetterRequestType::ordered()->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all()
                + ['other' => 'Other (typed by the employee)'],
            'canEdit'        => $this->employeeCan('general.letters.requests', 'edit'),
            'canCompose'     => $this->employeeCan(LetterComposeController::SLUG, 'create'),
            // The email each signed letter starts with, adjusted by HR before it is sent.
            'emails'         => $requests->getCollection()
                ->map(fn (LetterRequest $r) => $r->activeLetter())->filter()
                ->filter(fn (Letter $letter) => $letter->isSigned())
                ->mapWithKeys(fn (Letter $letter) => [$letter->id => $mailer->defaultLetterEmail($letter)]),
        ]);
    }

    /** Opens Create Letter with the employee, template, language and purpose of the request filled in. */
    public function process(LetterRequest $letterRequest)
    {
        abort_unless($this->employeeCan(LetterComposeController::SLUG, 'create'), 403, 'Processing a request needs the Create box of Letter Templates → Create Letter.');

        if (!$letterRequest->isOpen() || $letterRequest->activeLetter()) {
            return back()->with('error', 'This request is no longer waiting for a letter.');
        }

        return redirect()->route('general.letters.compose.index', ['request' => $letterRequest->id]);
    }

    public function reject(Request $request, LetterRequest $letterRequest)
    {
        if (!$letterRequest->isOpen()) {
            return back()->with('error', 'Only a pending or in-progress request can be rejected.');
        }

        $reason = $request->validate(['reject_reason' => 'required|string|max:1000'], [], ['reject_reason' => 'reason'])['reject_reason'];

        // A letter already generated for it stays in the register, voided with the same reason.
        if ($letter = $letterRequest->activeLetter()) {
            $this->letters->void($letter, "Request rejected: {$reason}", (int) session('user.id'));
            $letterRequest->refresh();
        }

        $this->letters->reject($letterRequest, $reason, (int) session('user.id'));

        return back()->with('success', "The request of {$letterRequest->employeeName()} was rejected. They can see the reason in My Letter Requests.");
    }

    public function sign(LetterRequest $letterRequest)
    {
        $letter = $letterRequest->activeLetter();
        if (!$letterRequest->isOpen() || !$letter) {
            return back()->with('error', 'Generate the letter for this request first.');
        }

        if ($error = $this->letters->signingProblem($letter)) {
            return back()->with('error', $error);
        }

        $this->letters->sign($letter, (int) session('user.id'));

        return back()->with('success', "Letter {$letter->letter_number} signed. Complete & Send it to {$letterRequest->employeeName()}.");
    }

    /** Done, in the employee's My Letter Requests, and emailed to them. */
    public function complete(Request $request, LetterRequest $letterRequest)
    {
        $letter = $letterRequest->activeLetter();
        if (!$letterRequest->isOpen() || !$letter || !$letter->isSigned()) {
            return back()->with('error', 'Sign the letter before completing the request.');
        }

        if (!$letter->recipient_email) {
            $letter->forceFill(['recipient_email' => Letter::workEmailOf($letterRequest->employee_id)])->save();
        }

        $email = $this->validatedEmail($request);
        // No email address anywhere still completes the request: the email is recorded as failed.
        $error = $this->letters->complete($letterRequest, $email['subject'], $email['body'], (int) session('user.id'));

        return $error
            ? back()->with('warning', "The request is done and the letter is in {$letterRequest->employeeName()}'s My Letter Requests, but the email failed: {$error}. Use Resend to try again.")
            : back()->with('success', "Done: {$letterRequest->employeeName()} can download the letter in My Letter Requests, and it was emailed to {$letter->recipient_email}.");
    }

    /** Only an email that failed is sent again — a delivered one never is. */
    public function resend(Request $request, LetterRequest $letterRequest)
    {
        $letter = $letterRequest->activeLetter();
        if (!$letterRequest->isDone() || !$letter || $letter->email_status !== Letter::EMAIL_FAILED) {
            return back()->with('error', 'Only an email that failed can be sent again.');
        }

        $email = $this->validatedEmail($request);
        $error = $this->letters->send($letter, $email['subject'], $email['body']);

        return $error
            ? back()->with('warning', "The email failed again: {$error}")
            : back()->with('success', "Letter {$letter->letter_number} emailed to {$letter->recipient_email}.");
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function validatedEmail(Request $request): array
    {
        return $request->validate([
            'subject' => 'required|string|max:255',
            'body'    => 'required|string|max:10000',
        ], [], ['body' => 'message']);
    }
}
