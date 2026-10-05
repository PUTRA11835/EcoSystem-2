<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterCode;
use App\Models\LetterTypeSetting;
use App\Services\Letters\LetterNumberService;
use App\Services\Letters\LetterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & General → Letter Templates → Letter Register (slug general.letters.register):
 * every letter in and out in one table — generated here, offering letters,
 * and letters logged by hand (an incoming letter gets an agenda number, an
 * outgoing one a number of the shared outgoing counter).
 *
 * V see and download · C log a letter by hand · E edit what was logged, the
 * delivery details, upload the signed / stamped scan · D void an outgoing
 * letter (its number stays), delete a logged incoming letter.
 */
class LetterRegisterController extends Controller
{
    use HandlesRecruitmentTables;

    public function __construct(private LetterNumberService $numbers, private LetterService $letters) {}

    public function index(Request $request)
    {
        $filters = [
            'direction' => (string) $request->query('direction'),
            'search'    => trim((string) $request->query('search')),
            'source'    => (string) $request->query('source'),
            'code'      => (string) $request->query('code'),
            'status'    => (string) $request->query('status'),
            'employee'  => (string) $request->query('employee'),
        ];
        $perPage = $this->perPage($request);

        $letters = Letter::with(['code', 'employee.basicData'])
            ->when(isset(Letter::DIRECTIONS[$filters['direction']]), fn ($q) => $q->where('direction', $filters['direction']))
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('letter_number', 'like', "%{$filters['search']}%")
                ->orWhere('agenda_number', 'like', "%{$filters['search']}%")
                ->orWhere('subject', 'like', "%{$filters['search']}%")
                ->orWhere('counterparty', 'like', "%{$filters['search']}%")
                ->orWhere('notes', 'like', "%{$filters['search']}%")))
            ->when(isset(Letter::SOURCES[$filters['source']]), fn ($q) => $q->where('source', $filters['source']))
            ->when(ctype_digit($filters['code']), fn ($q) => $q->where('letter_code_id', (int) $filters['code']))
            ->when(isset(Letter::STATUSES[$filters['status']]), fn ($q) => $q->withStatus($filters['status']))
            ->when(ctype_digit($filters['employee']), fn ($q) => $q->where('employee_id', (int) $filters['employee']))
            ->orderByDesc('letter_date')->orderByDesc('id')
            ->paginate($perPage)->withQueryString();

        $counts = Letter::query()->selectRaw('direction, COUNT(*) as total')->groupBy('direction')->pluck('total', 'direction');

        return view('hr-general.letters.register', [
            'letters'        => $letters,
            'filters'        => $filters,
            'hasFilters'     => collect($filters)->except('direction')->filter(fn ($v) => $v !== '')->isNotEmpty(),
            'perPage'        => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'counts'         => ['' => $counts->sum()] + $counts->all(),
            'codeOptions'    => LetterCode::ordered()->pluck('code', 'id')->mapWithKeys(fn ($code, $id) => [(string) $id => $code])->all(),
            'employeeName'   => ctype_digit($filters['employee']) ? Employee::with('basicData')->find((int) $filters['employee'])?->basicData?->full_name : null,
        ]);
    }

    public function create(Request $request)
    {
        $direction = $request->query('direction') === Letter::DIRECTION_INCOMING ? Letter::DIRECTION_INCOMING : Letter::DIRECTION_OUTGOING;

        return $this->form(new Letter(['direction' => $direction, 'source' => Letter::SOURCE_MANUAL, 'language' => 'id', 'letter_date' => now()]));
    }

    public function store(Request $request)
    {
        $direction = $request->input('direction') === Letter::DIRECTION_INCOMING ? Letter::DIRECTION_INCOMING : Letter::DIRECTION_OUTGOING;
        $data = $this->validatedManual($request, $direction);

        $letter = DB::transaction(function () use ($data, $direction) {
            $date = Carbon::parse($data['letter_date']);
            $letter = new Letter([...$data, 'direction' => $direction, 'source' => Letter::SOURCE_MANUAL,
                'created_by' => session('user.id'), 'updated_by' => session('user.id')]);

            if ($direction === Letter::DIRECTION_INCOMING) {
                // The agenda number is ours; the letter number is the sender's.
                [$letter->agenda_number, $letter->number_sequence] = $this->numbers->takeAgenda(Carbon::parse($data['received_date'] ?? $data['letter_date']));
                $letter->number_year = Carbon::parse($data['received_date'] ?? $data['letter_date'])->year;
            } elseif (trim((string) ($data['letter_number'] ?? '')) === '') {
                $code = LetterCode::whereKey($data['letter_code_id'] ?? 0)->value('code');
                [$letter->letter_number, $letter->number_sequence] = $this->numbers->takeOutgoing($date, $code, $data['language']);
                $letter->number_year = $date->year;
            } else {
                $letter->number_year = $date->year;
            }

            $letter->status = 'active';
            $letter->save();

            return $letter;
        });

        if ($file = $request->file('file')) {
            $letter->storeFinalFile($file);
        }

        $what = $direction === Letter::DIRECTION_INCOMING ? "Incoming letter logged as {$letter->agenda_number}" : "Outgoing letter {$letter->letter_number} logged";

        return redirect()->route('general.letters.register.index', ['direction' => $direction])->with('success', "{$what}.");
    }

    public function edit(Letter $letter)
    {
        if ($letter->isVoid()) {
            return redirect()->route('general.letters.register.index')->with('error', 'A voided letter can no longer be changed.');
        }

        return $this->form($letter);
    }

    /** What was logged by hand; for a generated or offering letter only its delivery and archive details. */
    public function update(Request $request, Letter $letter)
    {
        abort_if($letter->isVoid(), 403, 'A voided letter can no longer be changed.');

        if ($letter->isManual()) {
            $data = $this->validatedManual($request, $letter->direction, $letter);
            if ($letter->isOutgoing() && trim((string) ($data['letter_number'] ?? '')) === '') {
                unset($data['letter_number']); // emptied: keeps the number it was given
            }
        } else {
            $data = $request->validate([
                'delivered_via'  => 'nullable|string|max:150',
                'receipt_number' => 'nullable|string|max:150',
                'notes'          => 'nullable|string|max:2000',
                'file'           => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png',
            ]);
            unset($data['file']);
        }

        $letter->fill([...$data, 'updated_by' => session('user.id')])->save();

        if ($file = $request->file('file')) {
            $letter->storeFinalFile($file);
        }

        return redirect()->route('general.letters.register.index', ['direction' => $letter->direction])->with('success', 'Letter saved.');
    }

    /** An outgoing letter is never deleted: it stays, voided with the reason, and its number is not given out again. */
    public function void(Request $request, Letter $letter)
    {
        abort_unless($letter->isOutgoing() && !$letter->isVoid() && !$letter->isOffering(), 403);

        $reason = $request->validate(['void_reason' => 'required|string|max:1000'], [], ['void_reason' => 'reason'])['void_reason'];
        $this->letters->void($letter, $reason, (int) session('user.id'));

        return back()->with('success', "Letter {$letter->letter_number} voided. Its number stays in the register.");
    }

    /** Only an incoming letter logged by hand can be deleted. */
    public function destroy(Letter $letter)
    {
        abort_unless($letter->isManual() && !$letter->isOutgoing(), 403, 'Outgoing letters are voided, not deleted.');

        $letter->delete();

        return back()->with('success', 'Incoming letter deleted.');
    }

    // ── Files (any tab of the hub that lists letters) ────────────────────────

    /** The letter as a PDF: generated here, the offering letter, or the file of a logged letter. */
    public function pdf(Letter $letter)
    {
        if ($letter->isGenerated()) {
            return $letter->toPdf()->stream($letter->fileName());
        }

        if ($letter->isOffering() && $letter->offer) {
            return redirect()->route('general.recruitment.offers.print', $letter->offer);
        }

        return $this->file($letter);
    }

    /** The signed / stamped scan, or the received letter. */
    public function file(Letter $letter)
    {
        abort_unless($path = $letter->finalPath(), 404, 'No file was uploaded for this letter.');

        return Storage::disk(Letter::FILE_DISK)->response($path, $letter->final_name ?: basename($path));
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function form(Letter $letter)
    {
        return view('hr-general.letters.register-form', [
            'letter'    => $letter,
            'codes'     => LetterCode::ordered()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $letter->letter_code_id))->get(),
            'languages' => LetterTypeSetting::LANGUAGES,
        ]);
    }

    private function validatedManual(Request $request, string $direction, ?Letter $letter = null): array
    {
        $incoming = $direction === Letter::DIRECTION_INCOMING;

        $data = $request->validate([
            'letter_number'  => [$incoming ? 'nullable' : 'nullable', 'string', 'max:100'],
            'letter_code_id' => 'nullable|integer|exists:letter_codes,id',
            'language'       => [$incoming ? 'nullable' : 'required', Rule::in(array_keys(LetterTypeSetting::LANGUAGES))],
            'letter_date'    => 'required|date',
            'received_date'  => [$incoming ? 'required' : 'nullable', 'date'],
            'counterparty'   => 'required|string|max:255',
            'subject'        => 'required|string|max:255',
            'delivered_via'  => 'nullable|string|max:150',
            'receipt_number' => 'nullable|string|max:150',
            'notes'          => 'nullable|string|max:2000',
            'file'           => 'nullable|file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx',
        ], [], [
            'counterparty' => $incoming ? 'sender' : 'recipient',
            'letter_date'  => 'letter date',
            'file'         => $incoming ? 'scan of the letter' : 'final file',
        ]);
        unset($data['file']);

        $typed = trim((string) ($data['letter_number'] ?? ''));
        if (!$incoming && $typed !== '' && $typed !== $letter?->letter_number && $this->numbers->outgoingNumberUsed($typed, $letter?->id)) {
            throw ValidationException::withMessages(['letter_number' => 'That letter number is already used.']);
        }

        $data['language'] ??= 'id';

        return $data;
    }
}
