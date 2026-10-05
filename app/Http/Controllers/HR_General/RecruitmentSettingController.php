<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentOption;
use App\Models\Recruitment\RecruitmentSetting;
use App\Services\Recruitment\CalendarProviders;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecruitmentSettingController extends Controller
{
    public function edit(CalendarProviders $providers)
    {
        return view('hr-general.recruitment.settings', [
            'settings'        => RecruitmentSetting::current(),
            'timezones'       => RecruitmentSetting::TIMEZONES,
            'providers'       => CalendarProviders::PROVIDERS,
            'currentProvider' => $providers->currentKey(),
            'sharedMailbox'   => config('services.microsoft_graph.sender_email'),
            'optionTypes'     => RecruitmentOption::TYPES,
            'options'         => RecruitmentOption::ordered()->get()->groupBy('type'),
        ]);
    }

    public function update(Request $request, CalendarProviders $providers)
    {
        $data = $request->validate([
            'calendar_provider'        => ['required', Rule::in(array_keys(CalendarProviders::PROVIDERS))],
            'organizer_email'          => 'nullable|email|max:150',
            'default_timezone'         => ['required', Rule::in(array_keys(RecruitmentSetting::TIMEZONES))],
        ]);

        $settings = RecruitmentSetting::current();
        $requested = $data['calendar_provider'];

        // Everything but the source is saved first, so the check below runs
        // against the account that was just entered.
        $settings->update([...$data, 'calendar_provider' => $settings->calendar_provider]);
        RecruitmentSetting::forgetCache();

        // Switching to an outside calendar only takes effect once that calendar
        // actually answers — otherwise every interview would fail to sync.
        if ($requested !== $providers->currentKey() && ($problem = $this->connectionProblem($providers, $requested))) {
            return back()->with('error', 'The other settings were saved, but the calendar source was not changed. ' . $problem);
        }

        $settings->update(['calendar_provider' => $requested]);
        RecruitmentSetting::forgetCache();

        return back()->with('success', 'Recruitment settings saved.');
    }

    /** Reads the Microsoft account's calendar for today to prove the connection works end to end. */
    public function testConnection(CalendarProviders $providers)
    {
        $problem = $this->connectionProblem($providers, CalendarProviders::MICROSOFT);

        return $problem
            ? back()->with('error', $problem)
            : back()->with('success', 'Connected to the Outlook calendar of ' . RecruitmentSetting::current()->effectiveOrganizerEmail()
                . '. It can now be chosen as the calendar source.');
    }

    /** Null when the given calendar source can be read right now, otherwise why not. */
    private function connectionProblem(CalendarProviders $providers, string $key): ?string
    {
        // The internal calendar is this database: there is nothing to reach.
        if ($key === CalendarProviders::INTERNAL) {
            return null;
        }

        $provider = $providers->microsoft();

        if ($reason = $provider->unavailableReason()) {
            return $reason;
        }

        try {
            $provider->listBetween(now()->startOfDay(), now()->endOfDay());
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    public function storeOption(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(RecruitmentOption::TYPES))],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('recruitment_options', 'name')->where('type', $request->input('type')),
            ],
        ], ['name.unique' => 'That option already exists in this list.']);

        RecruitmentOption::create([
            ...$data,
            'sort_order' => (int) RecruitmentOption::ofType($data['type'])->max('sort_order') + 1,
            'is_active'  => true,
            // A new document type starts as a file; each job opening decides whether it asks for it.
            ...($data['type'] === RecruitmentOption::TYPE_DOCUMENT_TYPE ? ['submission' => 'file'] : []),
        ]);

        return back()->with('success', 'Option added.');
    }

    public function updateOption(Request $request, RecruitmentOption $option)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('recruitment_options', 'name')->where('type', $option->type)->ignore($option->id),
            ],
            'is_active' => 'nullable|boolean',
            ...($option->type === RecruitmentOption::TYPE_DOCUMENT_TYPE ? [
                'submission'     => ['required', Rule::in(array_keys(RecruitmentOption::SUBMISSIONS))],
                'file_formats'   => 'nullable|array',
                'file_formats.*' => [Rule::in(array_keys(RecruitmentOption::FILE_FORMATS))],
            ] : []),
        ], ['name.unique' => 'That option already exists in this list.']);

        $option->update([
            'name'      => $data['name'],
            'is_active' => $request->boolean('is_active'),
            ...($option->type === RecruitmentOption::TYPE_DOCUMENT_TYPE ? [
                'submission'   => $data['submission'],
                // Every format ticked is the same as none ticked: no restriction.
                'file_formats' => count($data['file_formats'] ?? []) === count(RecruitmentOption::FILE_FORMATS) ? null : ($data['file_formats'] ?? null),
            ] : []),
        ]);

        // The Settings page saves a row the moment it changes, in the background.
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => "\"{$option->name}\" saved."]);
        }

        return back()->with('success', 'Option updated.');
    }

    public function destroyOption(RecruitmentOption $option)
    {
        if ($option->isInUse()) {
            return back()->with('error', "\"{$option->name}\" is used by existing records and cannot be deleted. Deactivate it instead to hide it from the dropdowns.");
        }

        $option->delete();

        return back()->with('success', 'Option deleted.');
    }
}
