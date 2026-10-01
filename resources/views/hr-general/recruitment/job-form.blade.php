@extends('dashboard')

@php
    $isNew = !$job->exists;
    // Capabilities of the Job Openings tab, as ticked in Management → Roles.
    $editable = $canDo('general.recruitment.jobs', $isNew ? 'create' : 'edit');
    $pageTitle = $isNew ? 'New Job Opening' : ($editable ? 'Edit Job Opening' : 'Job Opening');

    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $card = 'bg-white rounded-xl border border-gray-200 shadow-sm';
    $cardHead = 'px-5 py-3.5 border-b border-gray-100';

    $value = fn (string $field) => old($field, $job->{$field});
    $isSelected = fn (string $field, $option) => (string) $value($field) === (string) $option;
    $dateTime = fn (string $field) => old($field, $job->{$field}?->format('Y-m-d\TH:i'));
    $money = fn (string $field) => old($field, $job->{$field} === null ? null : (float) $job->{$field});
    // What this opening asks for per document type. A new opening starts from the defaults HR set in Settings.
    $savedLevels = $job->exists ? $job->requestedDocuments->mapWithKeys(fn ($type) => [$type->id => $type->pivot->is_required ? 'required' : 'optional']) : collect();
    $documentLevel = fn ($type) => old("documents.{$type->id}", $job->exists ? ($savedLevels[$type->id] ?? 'none') : ($type->is_active ? ($type->requirement ?? 'none') : 'none'));
@endphp

@section('title', 'Recruitment - ' . $pageTitle)
@section('page-title', $pageTitle)
@section('page-subtitle', $isNew ? 'Record a job posting published on a platform.' : $job->position_title . ' · ' . $job->request_number)

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')

    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('general.recruitment.jobs.index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 bg-white text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all shadow-sm">
            <i class="fas fa-arrow-left text-xs"></i> Back to job openings
        </a>
        @unless($isNew)
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold {{ $job->statusBadge() }}">{{ $job->statusLabel() }}</span>
        @endunless
    </div>

    @include('hr-general.recruitment.components.form-errors')

    <form method="POST" action="{{ $isNew ? route('general.recruitment.jobs.store') : route('general.recruitment.jobs.update', $job) }}">
        @csrf
        <fieldset @disabled(!$editable) class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">

            <!-- Posting -->
            <div class="{{ $card }} xl:col-span-2">
                <div class="{{ $cardHead }}">
                    <h3 class="text-sm font-bold text-gray-800">Posting</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">What was published, where, and for how long.</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label for="position_title" class="{{ $label }}">Job Title <span class="text-red-500">*</span></label>
                            <input type="text" name="position_title" id="position_title" required maxlength="150" value="{{ $value('position_title') }}" class="{{ $input }}">
                        </div>
                        <div>
                            <label for="platform_id" class="{{ $label }}">Platform <span class="text-red-500">*</span></label>
                            <select name="platform_id" id="platform_id" required class="{{ $input }}">
                                <option value="">-- Select --</option>
                                @foreach($platforms as $platform)
                                    <option value="{{ $platform->id }}" @selected($isSelected('platform_id', $platform->id))>{{ $platform->name }}{{ $platform->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="posting_url" class="{{ $label }}">Posting Link <span class="font-normal text-gray-400">(optional)</span></label>
                        <input type="url" name="posting_url" id="posting_url" maxlength="500" placeholder="https://..." value="{{ $value('posting_url') }}" class="{{ $input }}">
                    </div>

                    <div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="opens_at" class="{{ $label }}">Opening Date &amp; Time <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="opens_at" id="opens_at" required value="{{ $dateTime('opens_at') }}" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="closes_at" class="{{ $label }}">Closing Date &amp; Time</label>
                                <input type="datetime-local" name="closes_at" id="closes_at" value="{{ $dateTime('closes_at') }}" class="{{ $input }}">
                            </div>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1.5">
                            <i class="fas fa-circle-info mr-1"></i>
                            The status is worked out from these dates: Scheduled before the opening, Open during the period, Closed once the closing time has passed. Leave the closing date empty to keep it open until closed manually.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="employment_type_id" class="{{ $label }}">Employee Type <span class="text-red-500">*</span></label>
                            <select name="employment_type_id" id="employment_type_id" required class="{{ $input }}">
                                <option value="">-- Select --</option>
                                @foreach($employmentTypes as $employmentType)
                                    <option value="{{ $employmentType->id }}" @selected($isSelected('employment_type_id', $employmentType->id))>{{ $employmentType->name }}{{ $employmentType->is_active ? '' : ' (inactive)' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="location" class="{{ $label }}">Location</label>
                            <select name="location" id="location" data-searchable="true" data-search-placeholder="Search city..." class="{{ $input }}">
                                <option value="">-- Select city --</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city }}" @selected($isSelected('location', $city))>{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="department_id" class="{{ $label }}">Department</label>
                            <select name="department_id" id="department_id" data-searchable="true" class="{{ $input }}">
                                <option value="">-- Select --</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" @selected($isSelected('department_id', $department->id))>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="position_id" class="{{ $label }}">Position</label>
                            <select name="position_id" id="position_id" data-searchable="true" class="{{ $input }}">
                                <option value="">-- Select --</option>
                                @foreach($positions as $position)
                                    <option value="{{ $position->id }}" @selected($isSelected('position_id', $position->id))>{{ $position->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div>
                            <label for="description" class="{{ $label }}">Description</label>
                            <textarea name="description" id="description" rows="8" class="{{ $input }}">{{ $value('description') }}</textarea>
                        </div>
                        <div>
                            <label for="requirements" class="{{ $label }}">Requirements</label>
                            <textarea name="requirements" id="requirements" rows="8" class="{{ $input }}">{{ $value('requirements') }}</textarea>
                        </div>
                    </div>

                    <div>
                        <span class="{{ $label }}">Documents Requested From Applicants</span>
                        <p class="text-[11px] text-gray-400 mb-2">Choose per document whether this opening requires it, accepts it optionally, or does not ask for it. The accepted form of each document is set on the Settings tab.</p>
                        <div class="border border-gray-200 rounded-lg divide-y divide-gray-100">
                            @forelse($documentTypes as $documentType)
                                @php $level = $documentLevel($documentType); @endphp
                                {{-- A deactivated type is only listed while this opening still asks for it. --}}
                                @continue(!$documentType->is_active && $level === 'none')
                                <div class="flex flex-wrap items-center gap-3 px-3 py-2">
                                    <div class="flex-1 min-w-[10rem]">
                                        <label for="document-{{ $documentType->id }}" class="text-sm font-semibold text-gray-800">{{ $documentType->name }}{{ $documentType->is_active ? '' : ' (inactive)' }}</label>
                                        <span class="block text-[11px] text-gray-500">{{ $documentType->rulesHint() }}</span>
                                    </div>
                                    <div class="w-44">
                                        <select name="documents[{{ $documentType->id }}]" id="document-{{ $documentType->id }}">
                                            @foreach(\App\Models\Recruitment\RecruitmentOption::REQUIREMENTS as $key => $levelLabel)
                                                <option value="{{ $key }}" @selected($level === $key)>{{ $levelLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            @empty
                                <p class="px-3 py-3 text-xs text-gray-400">No document types yet — add them on the Settings tab.</p>
                            @endforelse
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1.5">Candidates linked to this opening show these documents as a checklist, and a missing required one is flagged.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <!-- Internal information -->
                <div class="{{ $card }}">
                    <div class="{{ $cardHead }}">
                        <h3 class="text-sm font-bold text-gray-800">Internal Information</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">For the recruitment team only — never part of the public posting.</p>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        <div>
                            <label for="quota" class="{{ $label }}">Positions Needed <span class="text-red-500">*</span></label>
                            <input type="number" min="1" name="quota" id="quota" required value="{{ $value('quota') }}" class="{{ $input }}">
                        </div>
                        <div class="grid grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2 gap-3">
                            <div>
                                <label for="salary_min" class="{{ $label }}">Minimum Salary (Rp)</label>
                                <input type="number" min="0" step="1" name="salary_min" id="salary_min" value="{{ $money('salary_min') }}" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="salary_max" class="{{ $label }}">Maximum Salary (Rp)</label>
                                <input type="number" min="0" step="1" name="salary_max" id="salary_max" value="{{ $money('salary_max') }}" class="{{ $input }}">
                            </div>
                        </div>
                        <div>
                            <label for="recruiter_employee_id" class="{{ $label }}">Recruiter</label>
                            <select name="recruiter_employee_id" id="recruiter_employee_id" data-searchable="true" class="{{ $input }}">
                                <option value="">-- Select --</option>
                                @foreach($recruiters as $employeeId => $name)
                                    <option value="{{ $employeeId }}" @selected($isSelected('recruiter_employee_id', $employeeId))>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Career website -->
                <div class="{{ $card }}">
                    <div class="{{ $cardHead }}">
                        <h3 class="text-sm font-bold text-gray-800">Career Website</h3>
                    </div>
                    <div class="px-5 py-4">
                        <label class="flex items-start gap-2 text-sm text-gray-700">
                            <input type="hidden" name="publish_to_website" value="0">
                            <input type="checkbox" name="publish_to_website" value="1" @checked(old('publish_to_website', $job->publish_to_website))
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                Publish this opening on the career website
                                <span class="block text-[11px] text-gray-400 mt-0.5">The career website is not connected yet. The choice is saved now and will take effect once it is.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="mt-5 flex justify-end gap-2">
            <a href="{{ route('general.recruitment.jobs.index') }}" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 bg-white hover:bg-gray-50 rounded-lg">{{ $editable ? 'Cancel' : 'Back' }}</a>
            @if($editable)
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                    {{ $isNew ? 'Create Job Opening' : 'Save Changes' }}
                </button>
            @endif
        </div>
    </form>
</div>
@endsection
