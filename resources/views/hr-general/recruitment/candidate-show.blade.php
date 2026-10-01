@extends('dashboard')
@section('title', 'Recruitment - ' . $candidate->name)
@section('page-title', $candidate->name)
@section('page-subtitle', $candidate->positionLabel() . ' · ' . $candidate->statusLabel())

@php
    // Capabilities of the Selection Process tab, as ticked in Management → Roles.
    $canManage = $canDo('general.recruitment.candidates', 'edit');
    $canDelete = $canDo('general.recruitment.candidates', 'delete');
    // Interviews are the Schedule tab's records; its boxes decide what can be done with them here.
    $onSchedule = $can('general.recruitment.schedule');
    $canSchedule = $onSchedule && $canDo('general.recruitment.schedule', 'create') && $candidate->isActive();
    $canReschedule = $onSchedule && $canDo('general.recruitment.schedule', 'edit');
    $canCancelInterview = $onSchedule && $canDo('general.recruitment.schedule', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $selected = fn (string $field, $value) => (string) old($field, $candidate->{$field}) === (string) $value;
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')

    <div>
        <a href="{{ route('general.recruitment.candidates.index') }}"
            class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 bg-white text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50 transition-all shadow-sm">
            <i class="fas fa-arrow-left text-xs"></i> Back to list
        </a>
    </div>

    @include('hr-general.recruitment.components.form-errors')

    @if($candidate->status === \App\Models\Recruitment\Candidate::STATUS_OFFER && $can('general.recruitment.offers'))
        <div class="flex items-center justify-between gap-3 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3">
            <span><i class="fas fa-file-signature mr-1"></i> This candidate is at the Offer stage. The offering letter and the hiring decision are handled on the Offering Letter page.</span>
            <a href="{{ route('general.recruitment.offers.index', ['candidate_id' => $candidate->id]) }}"
                class="shrink-0 px-3 py-1.5 rounded-lg bg-amber-600 text-white font-semibold hover:opacity-90">
                {{ $candidate->latestOffer ? 'Open Offering Letter' : 'Write Offering Letter' }}
            </a>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">

        <!-- Candidate details -->
        <form id="candidateForm" method="POST" enctype="multipart/form-data"
            action="{{ route('general.recruitment.candidates.update', $candidate) }}"
            class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
            @csrf
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 rounded-t-xl flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800">Candidate Details</h3>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $candidate->statusBadge() }}">{{ $candidate->statusLabel() }}</span>
            </div>

            <fieldset @disabled(!$canManage) class="px-5 py-4 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="name" class="block text-xs font-semibold text-gray-600 mb-1">Candidate Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" required maxlength="150" value="{{ old('name', $candidate->name) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="position_id" class="block text-xs font-semibold text-gray-600 mb-1">Position <span class="text-red-500">*</span></label>
                        <select name="position_id" id="position_id" required data-searchable="true" class="{{ $input }}">
                            <option value="">-- Select position --</option>
                            @foreach($positions as $position)
                                <option value="{{ $position->id }}" @selected($selected('position_id', $position->id))>{{ $position->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="job_opening_id" class="block text-xs font-semibold text-gray-600 mb-1">Job Opening <span class="font-normal text-gray-400">(optional)</span></label>
                        <select name="job_opening_id" id="job_opening_id" class="{{ $input }}">
                            <option value="">-- None --</option>
                            @foreach($jobs as $job)
                                <option value="{{ $job->id }}" @selected($selected('job_opening_id', $job->id))>{{ $job->position_title }} ({{ $job->request_number }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="source_id" class="block text-xs font-semibold text-gray-600 mb-1">Source <span class="font-normal text-gray-400">(optional)</span></label>
                        <select name="source_id" id="source_id" class="{{ $input }}">
                            <option value="">-- None --</option>
                            @foreach($sources as $source)
                                <option value="{{ $source->id }}" @selected($selected('source_id', $source->id))>{{ $source->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-gray-600 mb-1">Email</label>
                        <input type="email" name="email" id="email" maxlength="150" value="{{ old('email', $candidate->email) }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="phone" class="block text-xs font-semibold text-gray-600 mb-1">Phone Number</label>
                        <input type="text" name="phone" id="phone" maxlength="30" value="{{ old('phone', $candidate->phone) }}" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="status" class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                    @if($candidate->isHired())
                        <input type="text" value="{{ $candidate->statusLabel() }}" disabled class="{{ $input }} sm:w-1/2">
                        <p class="text-[11px] text-gray-400 mt-1">Set by the accepted offer; it can no longer be changed here.</p>
                    @else
                        <select name="status" id="status" required class="{{ $input }} sm:w-1/2">
                            @foreach($manualStatuses as $value => $label)
                                <option value="{{ $value }}" @selected($selected('status', $value))>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">Each change is recorded on the timeline. Scheduling an interview updates the status by itself.</p>
                    @endif
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="block text-xs font-semibold text-gray-600">Documents</span>
                        @if($canManage)
                            <button type="button" onclick="addDocumentRow('candidateDocuments')" class="text-xs font-semibold" style="color: var(--primary-color);">
                                <i class="fas fa-plus text-[10px]"></i> Add document
                            </button>
                        @endif
                    </div>

                    @if($candidate->documents->isNotEmpty())
                        <ul class="border border-gray-200 rounded-lg divide-y divide-gray-100 mb-2">
                            @foreach($candidate->documents as $document)
                                <li class="flex items-center gap-3 px-3 py-2 text-xs">
                                    <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 text-[10px] font-semibold shrink-0">{{ $document->typeLabel() }}</span>
                                    <a href="{{ route('general.recruitment.candidates.documents.download', [$candidate, $document]) }}" target="_blank" rel="noopener"
                                        class="flex-1 min-w-0 truncate font-semibold text-gray-800 hover:underline">
                                        <i class="fas fa-{{ $document->isLink() ? 'link' : 'paperclip' }} text-[10px] text-gray-400 mr-1"></i>{{ $document->original_name }}
                                    </a>
                                    <span class="text-gray-400 shrink-0">{{ $document->created_at?->format('d M Y') }}</span>
                                    @if($canManage)
                                        <button type="button" class="text-red-500 hover:text-red-700 shrink-0" title="Remove document" aria-label="Remove {{ $document->original_name }}"
                                            onclick="confirmAndSubmit('deleteDocument{{ $document->id }}', 'Remove this document from the candidate?', 'Remove Document')">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @elseif(!$canManage)
                        <p class="text-xs text-gray-400">No documents attached.</p>
                    @endif

                    @include('hr-general.recruitment.components.document-fields', [
                        'jobSelect' => 'job_opening_id', 'rows' => 'candidateDocuments', 'canAdd' => $canManage, 'autoRows' => false,
                        'attached' => $candidate->documents->pluck('document_type_id')->filter()->unique()->all(),
                        'requirements' => $jobs->mapWithKeys(fn ($job) => [$job->id => $job->documentRequirements()]),
                    ])
                    @if($canManage)
                        <p class="text-[11px] text-gray-400 mt-1">New documents are saved when you save the candidate.</p>
                    @endif
                </div>

                <div>
                    <label for="notes" class="block text-xs font-semibold text-gray-600 mb-1">Internal Notes</label>
                    <textarea name="notes" id="notes" rows="4" placeholder="Visible to the recruitment team only." class="{{ $input }}">{{ old('notes', $candidate->notes) }}</textarea>
                </div>
            </fieldset>

            @if($canManage || $canDelete)
                <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-between gap-2">
                    <div>
                        @if($canDelete)
                            @include('hr-general.recruitment.components.icon-action', [
                                'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete candidate',
                                'onclick' => "confirmAndSubmit('deleteCandidate', 'Delete this candidate together with their documents and interview history? This cannot be undone.', 'Delete Candidate')",
                            ])
                        @endif
                    </div>
                    @if($canManage)
                        <div class="flex items-center gap-3">
                            <span id="unsavedHint" class="hidden text-[11px] text-amber-600"><i class="fas fa-circle text-[6px] align-middle mr-1"></i>Unsaved changes</span>
                            <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button>
                        </div>
                    @endif
                </div>
            @endif
        </form>

        <!-- Selection timeline -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3.5 border-b border-gray-100 bg-gray-50 rounded-t-xl flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Selection Timeline</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Status changes and interviews, newest first. The interviews are the same ones shown on the Schedule calendar.</p>
                </div>
                @if($canSchedule)
                    <button type="button" onclick="openCandidateModal({ mode: 'existing', candidateId: {{ $candidate->id }}, lockMode: true })"
                        class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm whitespace-nowrap">
                        <i class="fas fa-calendar-plus text-[10px]"></i> Schedule Interview
                    </button>
                @endif
            </div>
            <ol class="px-5 py-4 space-y-4">
                @forelse($timeline as $entry)
                    <li class="flex gap-3">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-[11px]
                            {{ $entry['kind'] === 'interview' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">
                            <i class="fas fa-{{ $entry['kind'] === 'interview' ? 'calendar-check' : 'flag' }}"></i>
                        </span>
                        <div class="min-w-0 text-xs">
                            <p class="font-semibold text-gray-800">{{ $entry['title'] }}</p>
                            @if($entry['detail'])
                                <p class="text-gray-500 mt-0.5">{{ $entry['detail'] }}</p>
                            @endif
                            @if($entry['kind'] === 'interview')
                                <p class="mt-1">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $entry['badge']->statusBadge() }}">{{ $entry['badge']->statusLabel() }}</span>
                                </p>
                                <div class="mt-2 flex items-center gap-1.5">
                                    @if($entry['badge']->joinUrl() && $entry['badge']->isUpcoming())
                                        @include('hr-general.recruitment.components.icon-action', [
                                            'icon' => 'video', 'tone' => 'green', 'label' => 'Join meeting', 'href' => $entry['badge']->joinUrl(), 'newTab' => true,
                                        ])
                                    @endif
                                    @if($canReschedule && $entry['edit'])
                                        @include('hr-general.recruitment.components.icon-action', [
                                            'icon' => 'calendar-days', 'tone' => 'indigo', 'label' => 'Reschedule',
                                            'data' => $entry['edit'], 'onclick' => 'openRescheduleModal(JSON.parse(this.dataset.payload))',
                                        ])
                                    @endif
                                    @if($canCancelInterview && $entry['badge']->isUpcoming())
                                        @include('hr-general.recruitment.components.icon-action', [
                                            'icon' => 'ban', 'tone' => 'red', 'label' => 'Cancel interview',
                                            'onclick' => "confirmAndSubmit('cancelInterview{$entry['badge']->id}', 'Cancel this interview? It is removed from the Schedule calendar.', 'Cancel Interview', 'Cancel interview')",
                                        ])
                                    @endif
                                </div>
                            @else
                                <p class="text-gray-400 mt-0.5">{{ $entry['at']->format('d M Y, H:i') }}</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="text-xs text-gray-400 text-center py-6">Nothing recorded yet.</li>
                @endforelse
            </ol>
        </div>
    </div>
</div>

{{-- Separate forms: HTML does not allow them nested inside the details form. --}}
@if($canDelete)
    <form id="deleteCandidate" method="POST" action="{{ route('general.recruitment.candidates.destroy', $candidate) }}" class="hidden">@csrf</form>
@endif
@if($canManage)
    @foreach($candidate->documents as $document)
        <form id="deleteDocument{{ $document->id }}" method="POST" class="hidden"
            action="{{ route('general.recruitment.candidates.documents.destroy', [$candidate, $document]) }}">@csrf</form>
    @endforeach
@endif
@if($canCancelInterview)
    @foreach($candidate->interviews->filter->isUpcoming() as $interview)
        <form id="cancelInterview{{ $interview->id }}" method="POST" class="hidden"
            action="{{ route('general.recruitment.schedule.cancel', $interview) }}">@csrf</form>
    @endforeach
@endif

{{-- The same schedule and reschedule forms the calendar uses, so an interview changed here changes there. --}}
@if($canSchedule)
    @include('hr-general.recruitment.components.candidate-modal')
@endif
@if($canReschedule)
    @include('hr-general.recruitment.components.reschedule-modal')
@endif
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('candidateForm');
        const hint = document.getElementById('unsavedHint');
        // Only someone who can edit can have unsaved changes.
        let dirty = @json($canManage && $errors->any());
        let leaving = false;

        const LEAVE_MESSAGE = 'You have unsaved changes on this candidate. If you continue, they will be lost.';
        const confirmLeave = () => showConfirm(LEAVE_MESSAGE, 'Leave without saving?', 'danger', { okText: 'Continue', cancelText: 'Stay' });

        function markDirty() {
            dirty = true;
            hint.classList.remove('hidden');
        }

        if (hint) {
            if (dirty) hint.classList.remove('hidden');
            form.addEventListener('input', markDirty);
            form.addEventListener('change', markDirty);
        }
        form.addEventListener('submit', () => { leaving = true; });

        // The interview forms on this page reload it too, which would drop unsaved edits of the details.
        document.addEventListener('submit', async function (event) {
            if (event.target === form || !dirty || leaving) return;

            event.preventDefault();
            if (await confirmLeave()) {
                leaving = true;
                event.target.submit();
            }
        }, true);

        // Links inside the app: ask with the in-app modal before navigating away.
        document.addEventListener('click', async function (event) {
            const link = event.target.closest('a[href]');
            if (!link || !dirty || leaving) return;
            if (link.target === '_blank' || link.hasAttribute('download')) return;

            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

            event.preventDefault();
            if (await confirmLeave()) {
                leaving = true;
                window.location.href = link.href;
            }
        }, true);

        // Closing the tab, reloading, or the browser's Back button can only be
        // guarded by the browser's own prompt.
        window.addEventListener('beforeunload', function (event) {
            if (dirty && !leaving) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        // Delete and cancel actions submit other forms; those also discard unsaved edits.
        window.confirmAndSubmit = async function (formId, message, title, okText) {
            const fullMessage = dirty ? message + ' Your unsaved changes will be lost.' : message;
            if (await showConfirm(fullMessage, title, 'danger', { okText: okText || 'Delete', cancelText: 'Back' })) {
                leaving = true;
                document.getElementById(formId).submit();
            }
        };
    })();
</script>
@endpush
