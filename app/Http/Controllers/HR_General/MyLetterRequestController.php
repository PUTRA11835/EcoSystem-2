<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterRequest;
use App\Models\Letters\LetterRequestType;
use App\Models\Letters\LetterSetting;
use App\Models\LetterTypeSetting;
use App\Services\Letters\LetterService;
use App\Support\Letters\LetterTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * My Letter Requests (slug general.my-letter-requests, every employee through
 * the User System Registered role): ask HR for a letter — one of the options
 * HR keeps in Letter Templates → Settings, or "Other" in the employee's own
 * words — follow it, download it once done (the same file that was emailed).
 * Also lists the letters HR sent about the employee.
 *
 * The slug opens the page; WHOSE request a route touches is checked here —
 * every action is limited to the signed-in employee's own requests.
 */
class MyLetterRequestController extends Controller
{
    /** The option for a letter that is not on the list; the employee types what they need. */
    public const OTHER = 'other';

    public function __construct(private LetterService $letters) {}

    public function index()
    {
        $employeeId = (int) session('user.id');

        return view('hr-general.letters.my-requests', [
            'requests'    => LetterRequest::with('letter')->where('employee_id', $employeeId)->latest()->get(),
            // Letters about me that reached me: sent, or the answer to a request of mine that is done.
            'myLetters'   => Letter::where('employee_id', $employeeId)->where('status', '!=', 'void')
                ->whereIn('source', [Letter::SOURCE_TEMPLATE, Letter::SOURCE_CUSTOM])
                ->where(fn ($q) => $q->where('email_status', Letter::EMAIL_SENT)
                    ->orWhereHas('request', fn ($r) => $r->where('status', LetterRequest::DONE)))
                ->orderByDesc('letter_date')->get(),
            'types'       => $types = LetterRequestType::active()->ordered()->get(),
            'allowOther'  => LetterSetting::current()->allow_other_requests,
            'languages'   => LetterTypeSetting::LANGUAGES,
            // The language each option starts in (Settings); "other" is answered with a custom letter.
            'languageFor' => $types->mapWithKeys(fn (LetterRequestType $type) => [$type->id => $type->defaultLanguage()])->all()
                + [self::OTHER => LetterTypeSetting::languageFor(LetterTemplates::CUSTOM)],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $letterRequest = LetterRequest::create([...$data, 'employee_id' => (int) session('user.id')]);
        $letterRequest->forceFill(['status' => LetterRequest::PENDING])->save();
        $this->letters->notifyHrOfRequest($letterRequest);

        return back()->with('success', "Your request for a {$letterRequest->typeLabel()} was sent to HR.");
    }

    public function update(Request $request, LetterRequest $letterRequest)
    {
        $this->authorizeOwn($letterRequest);
        abort_unless($letterRequest->isPending(), 403, 'A request HR has started on can no longer be changed.');

        $letterRequest->update($this->validated($request));

        return back()->with('success', 'Your request was updated.');
    }

    public function cancel(LetterRequest $letterRequest)
    {
        $this->authorizeOwn($letterRequest);
        abort_unless($letterRequest->isPending(), 403, 'A request HR has started on can no longer be cancelled.');

        $letterRequest->forceFill(['status' => LetterRequest::CANCELLED])->save();

        return back()->with('success', 'Your request was cancelled.');
    }

    /** The finished letter: the signed / stamped scan if HR uploaded one, else the generated (signed) PDF. */
    public function download(LetterRequest $letterRequest)
    {
        $this->authorizeOwn($letterRequest);
        $letter = $letterRequest->activeLetter();
        abort_unless($letterRequest->isDone() && $letter, 404);

        return $this->serve($letter);
    }

    /** A letter about me that HR sent (listed on the page). */
    public function downloadLetter(Letter $letter)
    {
        abort_unless((int) $letter->employee_id === (int) session('user.id') && !$letter->isVoid() && $letter->isGenerated(), 404);
        abort_unless($letter->email_status === Letter::EMAIL_SENT || $letter->request?->isDone(), 404);

        return $this->serve($letter);
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function serve(Letter $letter)
    {
        if ($path = $letter->finalPath()) {
            return Storage::disk(Letter::FILE_DISK)->download($path, $letter->final_name ?: basename($path));
        }

        return $letter->toPdf()->download($letter->fileName());
    }

    /** Ownership, not only the slug: nobody reads or changes a colleague's request by guessing its id. */
    private function authorizeOwn(LetterRequest $letterRequest): void
    {
        abort_unless((int) $letterRequest->employee_id === (int) session('user.id'), 404);
    }

    /**
     * What was asked for: an active option of Settings — its name and template
     * kept with the request — or "Other" with the employee's own words,
     * answered with a custom letter.
     */
    private function validated(Request $request): array
    {
        $allowOther = LetterSetting::current()->allow_other_requests;
        $typeIds = LetterRequestType::active()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $data = $request->validate([
            'request_type' => ['required', Rule::in([...$typeIds, ...($allowOther ? [self::OTHER] : [])])],
            'other_type'   => ['exclude_unless:request_type,' . self::OTHER, 'required', 'string', 'max:150'],
            'language'     => ['required', Rule::in(array_keys(LetterTypeSetting::LANGUAGES))],
            'purpose'      => 'required|string|max:255',
            'needed_by'    => 'nullable|date|after_or_equal:today',
            'notes'        => 'nullable|string|max:2000',
        ], ['request_type.in' => 'Pick one of the letters you can request.'], [
            'request_type' => 'letter', 'other_type' => 'letter you need', 'needed_by' => 'needed by',
        ]);

        $type = $data['request_type'] === self::OTHER ? null : LetterRequestType::find($data['request_type']);

        return [
            'request_type_id'   => $type?->id,
            'request_type_name' => $type ? $type->name : trim($data['other_type']),
            'is_other'          => $type === null,
            'template_key'      => $type && LetterTemplates::exists($type->template_key) ? $type->template_key : null,
            ...\Illuminate\Support\Arr::only($data, ['language', 'purpose', 'needed_by', 'notes']),
        ];
    }
}
