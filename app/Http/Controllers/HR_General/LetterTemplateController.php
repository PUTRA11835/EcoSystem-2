<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRole;
use App\Models\Letterhead;
use App\Models\Letters\LetterCode;
use App\Models\Letters\LetterRequestType;
use App\Models\Letters\LetterSignatory;
use App\Models\Letters\LetterSetting;
use App\Models\LetterTypeSetting;
use App\Services\Letters\LetterNumberService;
use App\Services\Letters\LetterService;
use App\Support\Letters\LetterTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & General → Letter Templates → Settings (slug general.letter-templates):
 *
 *   Templates     per letter type — in use, the language and letter code a
 *                 new letter starts with
 *   Signers       who can be picked as the signatory on Create Letter, role
 *                 first: everyone holding a role, or one person of it
 *   Requests      the letters employees can choose in My Letter Requests,
 *                 and whether they can ask for "Other"
 *   Numbering     company, signing city, outgoing / incoming number formats,
 *                 the IN / EN segment of a number
 *   Letter codes  OF / FI / SM / OL… printed in a number
 *   Letterheads   one full-page background image each, ticked per letter type
 *
 * C add letterhead / code / request option · E change any of it ·
 * D delete letterhead / unused code / request option.
 */
class LetterTemplateController extends Controller
{
    public function index(LetterNumberService $numbers)
    {
        $settings = LetterSetting::current();
        $types = Letterhead::letterTypes();
        $saved = LetterTypeSetting::whereIn('letter_type', array_keys($types))->get()->keyBy('letter_type');
        $today = now();
        $sequence = $numbers->peek(LetterNumberService::OUTGOING, $today->year);
        $employees = LetterService::employeeOptions();

        return view('hr-general.letters.settings', [
            'settings'       => $settings,
            'letterheads'    => Letterhead::orderBy('name')->get(),
            'letterTypes'    => $types,
            'typeSettings'   => collect($types)->map(fn ($label, string $type) => $saved[$type] ?? LetterTypeSetting::for($type)),
            'languages'      => LetterTypeSetting::LANGUAGES,
            'codes'          => LetterCode::ordered()->get(),
            'requestTypes'   => LetterRequestType::ordered()->get(),
            // Settings → Signers, role first: the entries, the roles to pick from with their members,
            // and who can sign right now (what Create Letter offers).
            'signers'        => LetterSignatory::with('role')->ordered()->get(),
            'roles'          => $roles = EmployeeRole::orderBy('name')->get(['id', 'name']),
            'roleMembers'    => LetterService::roleMembers($roles->pluck('id')->all()),
            'signingRoles'   => LetterService::signingRoles(),
            'employees'      => $employees,
            'templateLabels' => LetterTemplates::labels(),
            'tokens'         => LetterSetting::TOKENS,
            'nextSequence'   => $sequence,
            'nextIncoming'   => $numbers->peek(LetterNumberService::INCOMING, $today->year),
        ]);
    }

    /**
     * Per letter type: in use, and the language and code a new letter starts
     * with. Who signs is picked on each letter — the HR / admin member writing
     * it, or another one.
     */
    public function updateTemplates(Request $request)
    {
        $types = Letterhead::letterTypes();

        $data = $request->validate([
            'types'                  => 'required|array',
            'types.*.language'       => ['required', Rule::in(array_keys(LetterTypeSetting::LANGUAGES))],
            'types.*.letter_code_id' => 'nullable|integer|exists:letter_codes,id',
        ]);

        DB::transaction(function () use ($data, $types, $request) {
            foreach (array_intersect_key($data['types'], $types) as $type => $values) {
                LetterTypeSetting::updateOrCreate(['letter_type' => $type], [
                    'language'       => $values['language'],
                    'letter_code_id' => $values['letter_code_id'] ?? null,
                    // The offering letter is always in use.
                    'is_active'      => $type === Letterhead::TYPE_OFFERING_LETTER || $request->boolean("types.{$type}.is_active"),
                ]);
            }
        });

        return back()->with('success', 'Letter template settings saved.');
    }

    // ── Who can sign (Create Letter) ─────────────────────────────────────────

    /**
     * Who may sign, role first: everyone holding the role, or one person of it —
     * e.g. the Finance Manager of HO Finance Head, whom HR confirms with before
     * applying their signature.
     */
    public function storeSigner(Request $request)
    {
        $data = $request->validate([
            'role_id'     => 'required|integer|exists:employee_role,id',
            'employee_id' => 'nullable|integer|exists:employee,employee_id',
            'title'       => 'nullable|string|max:150',
        ], [], ['role_id' => 'role', 'employee_id' => 'signer']);

        $employeeId = $data['employee_id'] ?? null;
        if ($employeeId && !in_array((int) $employeeId, LetterService::roleMembers([$data['role_id']])[$data['role_id']] ?? [], true)) {
            throw ValidationException::withMessages(['employee_id' => 'That person does not hold the role picked.']);
        }

        $duplicate = LetterSignatory::where('role_id', $data['role_id'])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId), fn ($q) => $q->whereNull('employee_id'))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['employee_id' => $employeeId ? 'That person is already on the list for this role.' : 'Everyone in this role can already sign.']);
        }

        LetterSignatory::create([
            'role_id'     => $data['role_id'],
            'employee_id' => $employeeId,
            'title'       => $data['title'] ?? null,
            'is_active'   => true,
            'sort_order'  => (int) LetterSignatory::max('sort_order') + 1,
            'created_by'  => session('user.id'),
        ]);

        return back()->with('success', $employeeId ? 'Signer added.' : 'Everyone in the role can now sign letters.');
    }

    public function updateSigner(Request $request, LetterSignatory $signer)
    {
        $data = $request->validate(['title' => 'nullable|string|max:150']);

        $signer->update([...$data, 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Signer saved.');
    }

    /** Letters they already signed keep the signature; they can no longer be picked for new ones. */
    public function destroySigner(LetterSignatory $signer)
    {
        $signer->delete();

        return back()->with('success', 'Signer removed. Letters they already signed keep their signature.');
    }

    // ── What employees can request (My Letter Requests) ─────────────────────

    public function storeRequestType(Request $request)
    {
        $data = $this->validatedRequestType($request);

        LetterRequestType::create([...$data, 'is_active' => true, 'sort_order' => (int) LetterRequestType::max('sort_order') + 1]);

        return back()->with('success', "\"{$data['name']}\" can now be requested by employees.");
    }

    public function updateRequestType(Request $request, LetterRequestType $requestType)
    {
        $data = $this->validatedRequestType($request, $requestType);

        $requestType->update([...$data, 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', "Request option \"{$requestType->name}\" saved.");
    }

    /** Requests already made keep the name they were asked under. */
    public function destroyRequestType(LetterRequestType $requestType)
    {
        $requestType->delete();

        return back()->with('success', 'Request option deleted. Requests already made for it keep their name.');
    }

    /** Whether employees may ask for a letter that is not on the list ("Other"). */
    public function updateRequestSettings(Request $request)
    {
        LetterSetting::current()->update(['allow_other_requests' => $request->boolean('allow_other_requests')]);
        LetterSetting::forgetCache();

        return back()->with('success', $request->boolean('allow_other_requests')
            ? 'Employees can now also ask for a letter that is not on the list.'
            : 'Employees can only ask for the letters on the list.');
    }

    /** Company, signing city and the number formats. */
    public function updateNumbering(Request $request)
    {
        $data = $request->validate([
            'company_name'           => 'required|string|max:150',
            'signing_city'           => 'required|string|max:100',
            'outgoing_number_format' => ['required', 'string', 'max:100', 'regex:/\{seq\}/'],
            'outgoing_number_digits' => 'required|integer|between:1,6',
            'incoming_agenda_format' => ['required', 'string', 'max:100', 'regex:/\{seq\}/'],
            'incoming_agenda_digits' => 'required|integer|between:1,6',
            'language_codes'         => 'required|array',
            'language_codes.*'       => 'required|string|max:5|alpha_num',
        ], [
            'outgoing_number_format.regex' => 'The outgoing number format must contain {seq}, the running number.',
            'incoming_agenda_format.regex' => 'The agenda number format must contain {seq}, the running number.',
        ]);

        $data['language_codes'] = array_map('strtoupper', array_intersect_key($data['language_codes'], LetterTypeSetting::LANGUAGES));

        LetterSetting::current()->update($data);
        LetterSetting::forgetCache();

        return back()->with('success', 'Numbering settings saved.');
    }

    public function storeCode(Request $request)
    {
        $data = $this->validatedCode($request);

        LetterCode::create([...$data, 'is_active' => true, 'sort_order' => (int) LetterCode::max('sort_order') + 1]);

        return back()->with('success', "Letter code {$data['code']} added.");
    }

    public function updateCode(Request $request, LetterCode $code)
    {
        $data = $this->validatedCode($request, $code);

        // A code already on letters keeps its letters: only what it stands for and whether it is offered change.
        if ($code->isInUse() && $data['code'] !== $code->code) {
            return back()->with('error', "{$code->code} is printed on letters already, so the code itself cannot be changed. Add a new code instead.");
        }

        $code->update([...$data, 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', "Letter code {$code->code} saved.");
    }

    public function destroyCode(LetterCode $code)
    {
        if ($code->isInUse()) {
            return back()->with('error', "{$code->code} is printed on letters or set as a default already. Deactivate it instead, so old numbers keep their meaning.");
        }

        $code->delete();

        return back()->with('success', 'Letter code deleted.');
    }

    // ── Letterheads ──────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $this->validatedLetterhead($request);

        DB::transaction(function () use ($request, $data) {
            $letterhead = Letterhead::create(['name' => $data['name']]);
            $this->saveLetterhead($request, $letterhead, $data);
        });

        return back()->with('success', 'Letterhead added.');
    }

    public function update(Request $request, Letterhead $letterhead)
    {
        $data = $this->validatedLetterhead($request, $letterhead);

        DB::transaction(fn () => $this->saveLetterhead($request, $letterhead, $data));

        return back()->with('success', "Letterhead \"{$letterhead->name}\" saved.");
    }

    public function destroy(Letterhead $letterhead)
    {
        $letterhead->delete();

        return back()->with('success', 'Letterhead deleted.');
    }

    /** The image lives on a private disk; this is how the page shows it. */
    public function image(Letterhead $letterhead)
    {
        abort_unless($path = $letterhead->backgroundPath(), 404);

        return Storage::disk(Letterhead::DISK)->response($path);
    }

    // ── internal ─────────────────────────────────────────────────────────────

    private function validatedRequestType(Request $request, ?LetterRequestType $requestType = null): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:150', Rule::unique('letter_request_types', 'name')->ignore($requestType?->id)],
            // Empty: HR answers it with a custom letter titled with the name.
            'template_key' => ['nullable', Rule::in(array_keys(LetterTemplates::TEMPLATES))],
        ], ['name.unique' => 'That letter is already on the list.'], ['template_key' => 'answered with']);
    }

    private function validatedCode(Request $request, ?LetterCode $code = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('letter_codes', 'code')->ignore($code?->id)],
            'name' => 'nullable|string|max:100',
        ], ['code.unique' => 'That letter code already exists.']);
    }

    private function validatedLetterhead(Request $request, ?Letterhead $letterhead = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:100', Rule::unique('letterheads', 'name')->ignore($letterhead?->id)],
            // Required for a new letterhead; on an existing one, only to replace its image. A PDF or Word
            // letterhead is turned into this A4 image in the browser when it is picked (settings view).
            'background'     => [$letterhead ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
            'letter_types'   => 'nullable|array',
            'letter_types.*' => [Rule::in(array_keys(Letterhead::letterTypes()))],
        ], [
            'name.unique'         => 'A letterhead with that name already exists.',
            'background.required' => 'Upload the letterhead: an image, a PDF or a Word (.docx) file.',
            'background.image'    => 'The letterhead could not be turned into an image. Pick the image, PDF or Word file again and wait for its preview before saving.',
            'background.mimes'    => 'The letterhead could not be turned into an image. Pick the image, PDF or Word file again and wait for its preview before saving.',
            'background.max'      => 'The letterhead image is larger than 10 MB.',
        ]);
    }

    private function saveLetterhead(Request $request, Letterhead $letterhead, array $data): void
    {
        $types = array_values($data['letter_types'] ?? []);

        if ($file = $request->file('background')) {
            $old = $letterhead->background_path;
            $letterhead->background_path = $file->store("letterheads/{$letterhead->id}", Letterhead::DISK);

            if ($old) {
                Storage::disk(Letterhead::DISK)->delete($old);
            }
        }

        $letterhead->fill(['name' => $data['name'], 'letter_types' => $types])->save();

        // A letter is printed on one letterhead: ticking it here takes it off the others.
        Letterhead::whereKeyNot($letterhead->id)->get()
            ->filter(fn (Letterhead $other) => array_intersect($other->letter_types ?? [], $types))
            ->each(fn (Letterhead $other) => $other->update([
                'letter_types' => array_values(array_diff($other->letter_types, $types)),
            ]));
    }
}
