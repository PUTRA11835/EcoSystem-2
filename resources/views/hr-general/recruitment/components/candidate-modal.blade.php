{{--
    "Add candidate / schedule interview" modal — ONE form embedded on both the
    Selection Process page and the Schedule page, so a candidate and their
    interview are entered the same way from either place.

    Two modes, switched inside the modal:
      new      — creates a candidate, optionally with a first interview
                 (posts to candidates.store)
      existing — schedules an interview for a candidate already in the
                 pipeline (posts to schedule.store)

    What the modal offers follows the person's role (Management → Roles):
    "new" needs Create on Selection Process, "existing" and the interview
    section need Create on Schedule. A page should include this component
    only when at least one of the two holds.

    A section that is switched off carries [data-block-off] and has its inputs
    disabled, so they are neither validated by the browser nor submitted.

    Expects the variables of RecruitmentFormOptions::forCandidateModal().
    Open it with openCandidateModal({ mode, candidateId, date, startTime, lockMode }):
    `date` / `startTime` prefill the interview (a slot clicked on the calendar),
    `lockMode` hides the new / existing switch (the candidate's own page).
--}}
@php
    $allowNew = $canDo('general.recruitment.candidates', 'create');
    $allowInterview = $canDo('general.recruitment.schedule', 'create');
    $reopen = old('_modal') === 'candidate';
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
@endphp

<div id="candidateModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        <form id="candidateModalForm" method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0"
            data-new-action="{{ route('general.recruitment.candidates.store') }}"
            data-existing-action="{{ route('general.recruitment.schedule.store') }}">
            @csrf
            <input type="hidden" name="_modal" value="candidate">
            <input type="hidden" name="_modal_mode" id="candidateModalMode" value="{{ old('_modal_mode') }}">

            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="candidateModalTitle" class="text-sm font-bold text-gray-800">Add Candidate</h3>
                <button type="button" onclick="closeCandidateModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="px-5 py-4 space-y-4 overflow-y-auto">
                @if($allowNew && $allowInterview)
                    <div class="inline-flex rounded-lg border border-gray-200 p-0.5 text-xs font-semibold" data-mode-switch>
                        <button type="button" data-mode-button="new" onclick="setCandidateModalMode('new')" class="px-3 py-1.5 rounded-md">New candidate</button>
                        <button type="button" data-mode-button="existing" onclick="setCandidateModalMode('existing')" class="px-3 py-1.5 rounded-md">Existing candidate</button>
                    </div>
                @endif

                {{-- Existing candidate --}}
                @if($allowInterview)
                    <div data-mode-block="existing" data-block-off class="hidden">
                        <label for="cmCandidateId" class="{{ $label }}">Candidate <span class="text-red-500">*</span></label>
                        <select name="candidate_id" id="cmCandidateId" required data-searchable="true" data-search-placeholder="Search candidate…" class="{{ $input }}">
                            <option value="">-- Select candidate --</option>
                            @foreach($activeCandidates as $activeCandidate)
                                <option value="{{ $activeCandidate->id }}" @selected((string) old('candidate_id') === (string) $activeCandidate->id)>
                                    {{ $activeCandidate->name }} — {{ $activeCandidate->positionLabel() }} ({{ $activeCandidate->statusLabel() }})
                                </option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Only candidates still in the selection process are listed.</p>

                        {{-- What is already on file for the chosen candidate; filled when one is picked. --}}
                        <div id="cmCandidateSummary" class="hidden mt-3 border border-gray-200 rounded-lg bg-gray-50 px-3 py-2.5 text-xs" aria-live="polite">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="font-semibold text-gray-700"><i class="fas fa-id-card mr-1 text-gray-400"></i> Saved candidate data</span>
                                @if($can('general.recruitment.candidates'))
                                    <a id="cmCandidateLink" href="#" target="_blank" rel="noopener" class="font-semibold" style="color: var(--primary-color);">
                                        Open candidate <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                                    </a>
                                @endif
                            </div>
                            <dl id="cmCandidateFacts" class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1"></dl>
                        </div>
                    </div>
                @endif

                {{-- New candidate --}}
                @if($allowNew)
                    <div data-mode-block="new" data-block-off class="hidden space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="cmName" class="{{ $label }}">Candidate Name <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="cmName" required maxlength="150" value="{{ old('name') }}" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="cmPosition" class="{{ $label }}">Position <span class="text-red-500">*</span></label>
                                <select name="position_id" id="cmPosition" required data-searchable="true" class="{{ $input }}">
                                    <option value="">-- Select position --</option>
                                    @foreach($positions as $position)
                                        <option value="{{ $position->id }}" @selected((string) old('position_id') === (string) $position->id)>{{ $position->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="cmJob" class="{{ $label }}">Job Opening <span class="font-normal text-gray-400">(optional)</span></label>
                                <select name="job_opening_id" id="cmJob" class="{{ $input }}">
                                    <option value="">-- None --</option>
                                    @foreach($openJobs as $job)
                                        <option value="{{ $job->id }}" data-position="{{ $job->position_id }}" data-platform="{{ $job->platform_id }}"
                                            @selected((string) old('job_opening_id') === (string) $job->id)>
                                            {{ $job->position_title }} ({{ $job->request_number }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="cmSource" class="{{ $label }}">Source <span class="font-normal text-gray-400">(optional)</span></label>
                                <select name="source_id" id="cmSource" class="{{ $input }}">
                                    <option value="">-- None --</option>
                                    @foreach($sources as $source)
                                        <option value="{{ $source->id }}" @selected(old('source_other') !== '1' && (string) old('source_id') === (string) $source->id)>{{ $source->name }}</option>
                                    @endforeach
                                    <option value="other" @selected(old('source_other') === '1')>Other…</option>
                                </select>
                            </div>
                        </div>

                        <div id="cmSourceDetailBox" @class(['hidden' => old('source_other') !== '1'])>
                            <label for="cmSourceDetail" class="{{ $label }}">Other Source <span class="text-red-500">*</span></label>
                            <input type="text" name="source_detail" id="cmSourceDetail" maxlength="150" @required(old('source_other') === '1') value="{{ old('source_detail') }}"
                                placeholder="e.g. Referred by Budi (Finance)" class="{{ $input }}">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="cmEmail" class="{{ $label }}">Email</label>
                                <input type="email" name="email" id="cmEmail" maxlength="150" value="{{ old('email') }}" class="{{ $input }}">
                            </div>
                            <div>
                                <label for="cmPhone" class="{{ $label }}">Phone Number</label>
                                <input type="text" name="phone" id="cmPhone" maxlength="30" value="{{ old('phone') }}" class="{{ $input }}">
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="{{ $label }} mb-0">Documents</span>
                                <button type="button" onclick="addDocumentRow('cmDocuments')" class="text-xs font-semibold" style="color: var(--primary-color);">
                                    <i class="fas fa-plus text-[10px]"></i> Add document
                                </button>
                            </div>
                            @include('hr-general.recruitment.components.document-fields', [
                                'jobSelect' => 'cmJob', 'rows' => 'cmDocuments', 'attached' => [], 'canAdd' => true, 'autoRows' => true,
                                'requirements' => $openJobs->mapWithKeys(fn ($job) => [$job->id => $job->documentRequirements()]),
                            ])
                        </div>

                        <div data-status-field>
                            <label for="cmStatus" class="{{ $label }}">Status <span class="text-red-500">*</span></label>
                            <select name="status" id="cmStatus" required class="{{ $input }}">
                                @foreach($manualStatuses as $value => $statusLabel)
                                    <option value="{{ $value }}" @selected(old('status') === $value)>{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="cmNotes" class="{{ $label }}">Internal Notes</label>
                            <textarea name="notes" id="cmNotes" rows="2" class="{{ $input }}">{{ old('notes') }}</textarea>
                        </div>

                        @if($allowInterview)
                            <label class="flex items-center gap-2 text-sm text-gray-700 pt-1">
                                <input type="checkbox" name="schedule_interview" id="cmScheduleInterview" value="1"
                                    @checked(!$reopen || old('schedule_interview'))
                                    onchange="syncCandidateModal()"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                Schedule an interview now
                            </label>
                        @endif
                    </div>
                @endif

                {{-- Interview --}}
                @if($allowInterview)
                    <div data-interview-block data-block-off class="hidden border-t border-gray-100 pt-4">
                        <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wider mb-3">Interview Schedule</h4>
                        @include('hr-general.recruitment.components.interview-fields', ['prefix' => 'cmInterview', 'useOld' => $reopen])
                        <p class="text-[11px] text-gray-400 mt-2">
                            <i class="fas fa-circle-info mr-1"></i>
                            The candidate's status follows the interview stage, and the interview appears on the Schedule calendar{{ $calendarExternal ? ' and in Outlook' : '' }}.
                        </p>
                    </div>
                @endif
            </div>

            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeCandidateModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const modal = document.getElementById('candidateModal');
        const form = document.getElementById('candidateModalForm');
        const allowed = { new: @json($allowNew), existing: @json($allowInterview) };
        const byId = id => document.getElementById(id);

        function setBlock(block, visible) {
            if (!block) return;
            block.classList.toggle('hidden', !visible);
            block.toggleAttribute('data-block-off', !visible);
            block.querySelectorAll('input, select, textarea').forEach(el => { el.disabled = !visible; });
        }

        window.syncCandidateModal = function () {
            const mode = byId('candidateModalMode').value;
            const isNew = mode === 'new';
            const scheduleBox = byId('cmScheduleInterview');
            const withInterview = !isNew || (scheduleBox !== null && scheduleBox.checked);

            form.action = isNew ? form.dataset.newAction : form.dataset.existingAction;
            byId('candidateModalTitle').textContent = isNew ? 'Add Candidate' : 'Schedule Interview';

            modal.querySelectorAll('[data-mode-block]').forEach(block => setBlock(block, block.dataset.modeBlock === mode));
            setBlock(modal.querySelector('[data-interview-block]'), withInterview);

            // With an interview, the stage decides the status — the status field would only contradict it.
            if (isNew) setBlock(modal.querySelector('[data-status-field]'), !withInterview);

            modal.querySelectorAll('[data-mode-button]').forEach(button => {
                const active = button.dataset.modeButton === mode;
                button.classList.toggle('primary-gradient', active);
                button.classList.toggle('text-white', active);
                button.classList.toggle('text-gray-600', !active);
            });

            modal.querySelector('[data-interview-fields]')?.dispatchEvent(new Event('interview:sync'));
            // Switching a section on re-enables all its inputs; let each document row re-apply its type's rules.
            modal.querySelectorAll('[data-document-row]').forEach(syncDocumentRow);
        };

        window.setCandidateModalMode = function (mode) {
            if (!allowed[mode]) mode = allowed.new ? 'new' : 'existing';
            byId('candidateModalMode').value = mode;
            syncCandidateModal();
        };

        window.openCandidateModal = function (options) {
            options = options || {};
            setCandidateModalMode(options.mode || 'new');

            modal.querySelector('[data-mode-switch]')?.classList.toggle('hidden', !!options.lockMode);

            // Reopened after a failed save: show the saved data of the candidate that is still selected.
            if (!options.candidateId && byId('cmCandidateId')?.value) {
                byId('cmCandidateId').dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (options.candidateId && byId('cmCandidateId')) {
                byId('cmCandidateId').value = String(options.candidateId);
                byId('cmCandidateId').dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (options.date && byId('cmInterviewDate')) {
                byId('cmInterviewDate').value = options.date;
            }
            if (options.startTime && byId('cmInterviewStart')) {
                // Cleared first, so the end time is suggested afresh from the new start.
                byId('cmInterviewEnd').value = '';
                modal.querySelector('[data-interview-fields]').dispatchEvent(new Event('interview:sync'));
                byId('cmInterviewStart').value = options.startTime;
                byId('cmInterviewStart').dispatchEvent(new Event('input', { bubbles: true }));
            }

            modal.classList.remove('hidden');
        };

        window.closeCandidateModal = function () {
            modal.classList.add('hidden');
        };

        // Picking an existing candidate brings up what is already saved for them,
        // and sets the interview stage to the one their current status calls for.
        const summaries = @json($candidateSummaries);

        byId('cmCandidateId')?.addEventListener('change', function () {
            const summary = summaries[this.value];
            const panel = byId('cmCandidateSummary');
            panel.classList.toggle('hidden', !summary);
            if (!summary) return;

            const facts = [
                ['Position', summary.position],
                ['Status', summary.status],
                ['Job opening', summary.jobOpening],
                ['Source', summary.source],
                ['Email', summary.email],
                ['Phone', summary.phone],
                ['Documents', summary.documents ? summary.documents + ' attached' : 'None attached'],
                ['Last interview', summary.lastInterview || 'None yet'],
                ['Internal notes', summary.notes],
            ].filter(([, value]) => value);

            byId('cmCandidateFacts').replaceChildren(...facts.map(([label, value]) => {
                const row = document.createElement('div');
                row.className = 'flex gap-2 min-w-0' + (label === 'Last interview' || label === 'Internal notes' ? ' sm:col-span-2' : '');
                const dt = document.createElement('dt');
                dt.className = 'w-24 shrink-0 text-gray-500';
                dt.textContent = label;
                const dd = document.createElement('dd');
                dd.className = 'text-gray-800 min-w-0 break-words';
                dd.textContent = value;
                row.append(dt, dd);
                return row;
            }));

            if (byId('cmCandidateLink')) byId('cmCandidateLink').href = summary.url;

            const stage = byId('cmInterviewStage');
            if (summary.stage && stage && !stage.disabled) {
                stage.value = summary.stage;
                stage.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // "Other…" in Source reveals the box to type the source into.
        byId('cmSource')?.addEventListener('change', function () {
            const other = this.value === 'other';
            byId('cmSourceDetailBox').classList.toggle('hidden', !other);
            byId('cmSourceDetail').required = other;
            if (other) byId('cmSourceDetail').focus();
        });

        // Picking a job opening fills in its position and platform when those are still empty.
        byId('cmJob')?.addEventListener('change', function () {
            const option = this.selectedOptions[0];
            [['cmPosition', option?.dataset.position], ['cmSource', option?.dataset.platform]].forEach(([id, value]) => {
                const target = byId(id);
                if (value && !target.value) {
                    target.value = value;
                    target.dispatchEvent(new Event('change'));
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            if (byId('cmDocuments')) addDocumentRow('cmDocuments');

            @if($reopen)
                openCandidateModal({ mode: @json(old('_modal_mode', 'new')) });
            @endif
        });
    })();
</script>
@endpush
