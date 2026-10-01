@extends('dashboard')
@section('title', 'Recruitment - Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'The calendar source and the dropdown lists used across Recruitment.')

@php
    // Capabilities of the Settings tab, as ticked in Management → Roles.
    $canEdit   = $canDo('general.recruitment.settings', 'edit');
    $canCreate = $canDo('general.recruitment.settings', 'create');
    $canDelete = $canDo('general.recruitment.settings', 'delete');
    $input ='w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $optionHelp = [
        'platform'        => 'Where job openings are posted and where candidates come from.',
        'employment_type' => 'Offered as "Employee Type" on a job opening.',
        'document_type'   => 'The documents a job opening can ask applicants for, and the rules each one follows when it is attached to a candidate.',
    ];
@endphp

@push('styles')
<style>
    /* Document Types rows: stacked on small screens, one aligned row of columns from lg up. */
    @media (min-width: 1024px) {
        .recruitment-document-grid { grid-template-columns: minmax(10rem, 1fr) 10rem 9rem minmax(18rem, 1.4fr) 6.5rem; }
    }
</style>
@endpush

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')
    @include('hr-general.recruitment.components.form-errors')

    <!-- Calendar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Calendar</h3>
            <p class="text-[11px] text-gray-400 mt-0.5">Interviews are always stored in this system. The calendar source decides whether they are also sent to an outside calendar.</p>
        </div>

        <form action="{{ route('general.recruitment.settings.update') }}" method="POST" id="calendarSettingsForm">
            @csrf
            <fieldset @disabled(!$canEdit) class="px-5 py-4 space-y-5">
                <div>
                    <span class="block text-xs font-semibold text-gray-600 mb-2">Calendar Source</span>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        @foreach($providers as $key => $provider)
                            @php $isCurrent = old('calendar_provider', $currentProvider) === $key; @endphp
                            <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer transition-colors {{ $isCurrent ? 'border-indigo-300 bg-indigo-50/50' : 'border-gray-200 hover:bg-gray-50' }}" data-provider-card>
                                <input type="radio" name="calendar_provider" value="{{ $key }}" @checked($isCurrent)
                                    class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="min-w-0">
                                    <span class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-800">
                                        {{ $provider['label'] }}
                                        @if($currentProvider === $key)
                                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-bold">In use</span>
                                        @endif
                                    </span>
                                    <span class="block text-[11px] text-gray-500 mt-1 leading-snug">{{ $provider['description'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">
                        <i class="fas fa-circle-info mr-1"></i>
                        Switching to Microsoft Outlook is checked against the account below first, and only takes effect if its calendar can be reached. Interviews scheduled before the switch can be sent over one by one from the Schedule tab.
                    </p>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <span class="block text-xs font-semibold text-gray-600">Microsoft Account</span>
                        <button type="submit" form="testConnectionForm"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-700 text-xs font-semibold rounded-lg hover:bg-gray-50">
                            <i class="fas fa-plug text-[10px]"></i> Test connection
                        </button>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <label for="organizer_email" class="sr-only">Microsoft account email</label>
                            <input type="email" name="organizer_email" id="organizer_email" maxlength="150"
                                value="{{ old('organizer_email', $settings->organizer_email) }}"
                                placeholder="{{ $sharedMailbox ?: 'e.g. hr@company.com' }}" class="{{ $input }}">
                            <p class="text-[11px] text-gray-400 mt-1">
                                The Outlook calendar of this account is the one interviews are created in and every HR user sees here.
                                @if($sharedMailbox)
                                    Leave empty to use the shared system mailbox ({{ $sharedMailbox }}).
                                @else
                                    No shared system mailbox is configured, so an address is needed here.
                                @endif
                            </p>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2.5 text-[11px] text-gray-600 self-start leading-relaxed">
                            <p class="font-semibold text-gray-700 mb-0.5">What is needed to switch it on</p>
                            The system's Microsoft Entra app registration must hold the <strong>Calendars.ReadWrite</strong> application permission with admin consent.
                            "Test connection" reports whether it does; nothing else needs to change in this system.
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4 sm:w-1/2 lg:w-1/3">
                    <div>
                        <label for="default_timezone" class="block text-xs font-semibold text-gray-600 mb-1">Timezone</label>
                        <select name="default_timezone" id="default_timezone" required class="{{ $input }}">
                            @foreach($timezones as $value => $label)
                                <option value="{{ $value }}" @selected(old('default_timezone', $settings->default_timezone) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="text-[11px] text-gray-400 mt-1">The timezone interview times are entered and shown in.</p>
                    </div>
                </div>
            </fieldset>
            @if($canEdit)
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button>
                </div>
            @endif
        </form>
        {{-- Its own form, outside the settings form; the button above submits it through the `form` attribute. --}}
        <form id="testConnectionForm" action="{{ route('general.recruitment.settings.test-connection') }}" method="POST" class="hidden">@csrf</form>
    </div>

    <!-- Dropdown lists -->
    @php
        $rowInput = 'w-full border border-gray-200 rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200';
        $documentType = \App\Models\Recruitment\RecruitmentOption::TYPE_DOCUMENT_TYPE;
        $action = 'hr-general.recruitment.components.icon-action';
    @endphp
    <div>
        <h3 class="text-sm font-bold text-gray-800">Dropdown Lists</h3>
        <p class="text-[11px] text-gray-400 mt-0.5 mb-3">
            Every change is saved as you make it and shows up in the dropdowns straight away — there is nothing to confirm.
            An inactive option disappears from the dropdowns but stays on the records that already use it.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            @foreach($optionTypes as $type => $title)
                @php $isDocuments = $type === $documentType; @endphp
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm {{ $isDocuments ? 'lg:col-span-2' : '' }}">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <h4 class="text-sm font-bold text-gray-800">{{ $title }}</h4>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $optionHelp[$type] }}</p>
                    </div>

                    @if($isDocuments && $canEdit)
                        {{-- 2.5rem = the delete button and its gap, so the headings line up with the row's columns. --}}
                        <div class="hidden lg:grid recruitment-document-grid gap-3 pl-4 py-2 bg-gray-50 border-b border-gray-100 text-[10px] font-bold uppercase tracking-wider text-gray-500"
                            style="padding-right: {{ $canDelete ? '3.5rem' : '1rem' }};">
                            <span>Document</span>
                            <span title="How a new job opening asks for this document by default">New openings ask for it as</span>
                            <span>Handed in as</span>
                            <span>Accepted file formats</span>
                            <span></span>
                        </div>
                    @endif

                    <ul class="divide-y divide-gray-100">
                        @forelse($options->get($type, collect()) as $option)
                            <li class="px-4 py-2.5 flex items-start gap-2">
                                @if($canEdit)
                                    <form action="{{ route('general.recruitment.settings.options.update', $option) }}" method="POST" data-autosave
                                        class="flex-1 min-w-0 {{ $isDocuments ? 'grid recruitment-document-grid lg:items-center gap-x-3 gap-y-2' : 'flex items-center gap-2' }}">
                                        @csrf
                                        <input type="text" name="name" value="{{ $option->name }}" required maxlength="100" aria-label="Option name"
                                            class="{{ $rowInput }} {{ $isDocuments ? '' : 'flex-1 min-w-0' }} {{ $option->is_active ? '' : 'text-gray-400' }}">

                                        @if($isDocuments)
                                            <div>
                                                <select name="requirement" aria-label="New job openings ask for {{ $option->name }} as">
                                                    @foreach(\App\Models\Recruitment\RecruitmentOption::REQUIREMENTS as $key => $requirementLabel)
                                                        <option value="{{ $key }}" @selected(($option->requirement ?? 'none') === $key)>{{ $requirementLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <select name="submission" aria-label="{{ $option->name }} is handed in as" data-submission>
                                                    @foreach(\App\Models\Recruitment\RecruitmentOption::SUBMISSIONS as $key => $submissionLabel)
                                                        <option value="{{ $key }}" @selected(($option->submission ?? 'file') === $key)>{{ $submissionLabel }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="flex flex-wrap gap-x-3 gap-y-1" data-formats>
                                                @foreach(\App\Models\Recruitment\RecruitmentOption::FILE_FORMATS as $key => $format)
                                                    <label class="flex items-center gap-1 text-[11px] text-gray-700">
                                                        <input type="checkbox" name="file_formats[]" value="{{ $key }}" @checked(in_array($key, $option->file_formats ?? [], true))
                                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        {{ $format['label'] }}
                                                    </label>
                                                @endforeach
                                                <span class="w-full text-[10px] text-gray-400" data-formats-note>None ticked = any of these formats.</span>
                                            </div>
                                        @endif

                                        <div class="flex items-center gap-2 shrink-0">
                                            <label class="flex items-center gap-1 text-[11px] text-gray-600" title="Shown in dropdowns">
                                                <input type="checkbox" name="is_active" value="1" @checked($option->is_active)
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                Active
                                            </label>
                                            <span class="w-4 text-center text-xs" data-save-state aria-live="polite"></span>
                                        </div>
                                    </form>
                                @else
                                    <span class="flex-1 min-w-0 text-xs {{ $option->is_active ? 'text-gray-800' : 'text-gray-400' }}">
                                        {{ $option->name }}
                                        @if($isDocuments)
                                            <span class="block text-[11px] text-gray-500">
                                                {{ \App\Models\Recruitment\RecruitmentOption::REQUIREMENTS[$option->requirement ?? 'none'] }} by default · {{ $option->rulesHint() }}
                                            </span>
                                        @endif
                                    </span>
                                    @unless($option->is_active)
                                        <span class="text-[10px] font-semibold text-gray-400 shrink-0">Inactive</span>
                                    @endunless
                                @endif
                                @if($canDelete)
                                    @include($action, [
                                        'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete ' . $option->name,
                                        'post' => route('general.recruitment.settings.options.destroy', $option),
                                        'confirm' => 'Delete "' . $option->name . '"?', 'confirmTitle' => 'Delete Option', 'confirmOk' => 'Delete',
                                    ])
                                @endif
                            </li>
                        @empty
                            <li class="px-4 py-6 text-xs text-gray-400 text-center">No options yet.</li>
                        @endforelse
                    </ul>

                    @if($canCreate)
                        <form action="{{ route('general.recruitment.settings.options.store') }}" method="POST" class="px-4 py-3 border-t border-gray-100 flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type }}">
                            <input type="text" name="name" required maxlength="100" placeholder="New option — press Enter to add" aria-label="New option for {{ $title }}"
                                class="{{ $rowInput }} flex-1 min-w-0 {{ $isDocuments ? 'lg:max-w-sm' : '' }}">
                            @include($action, ['icon' => 'plus', 'tone' => 'blue', 'label' => 'Add option to ' . $title, 'submit' => true])
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-xl px-5 py-4 text-xs text-gray-600">
        <p class="font-semibold text-gray-700 mb-1"><i class="fas fa-circle-info mr-1"></i> Dropdowns that are not managed here</p>
        <ul class="list-disc list-inside space-y-0.5">
            <li><strong>Location</strong> lists every city and regency of Indonesia from the system's regional reference data.</li>
            <li><strong>Department</strong> and <strong>Position</strong> come from the employee master data, so they match the rest of the system.</li>
        </ul>
    </div>
</div>

@include('hr-general.recruitment.components.confirm-forms')
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ── Dropdown lists: a row saves itself the moment it changes ──
        document.querySelectorAll('form[data-autosave]').forEach(form => {
            const state = form.querySelector('[data-save-state]');
            const name = form.querySelector('input[name="name"]');
            let savedName = name.value;

            const mark = (html, title) => { state.innerHTML = html; state.title = title || ''; };

            // A link-only document has no file, so its format boxes do not apply.
            const syncFormats = () => {
                const formats = form.querySelector('[data-formats]');
                if (!formats) return;
                const linkOnly = form.querySelector('[data-submission]').value === 'link';
                formats.querySelectorAll('input').forEach(box => { box.disabled = linkOnly; });
                formats.classList.toggle('opacity-50', linkOnly);
            };

            async function save() {
                if (!name.value.trim()) { name.value = savedName; return; }
                syncFormats();
                mark('<i class="fas fa-circle-notch fa-spin text-gray-400"></i>', 'Saving…');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST', body: new FormData(form), credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const body = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const problem = Object.values(body.errors || {}).flat()[0] || body.message || 'The change could not be saved.';
                        throw new Error(problem);
                    }

                    savedName = name.value;
                    name.classList.toggle('text-gray-400', !form.querySelector('input[name="is_active"]').checked);
                    mark('<i class="fas fa-check text-green-600"></i>', 'Saved');
                    setTimeout(() => { if (state.title === 'Saved') mark('', ''); }, 2500);
                } catch (error) {
                    name.value = savedName;
                    mark('<i class="fas fa-triangle-exclamation text-red-500"></i>', error.message);
                    showToast(error.message, 'error', 6000);
                }
            }

            form.addEventListener('change', save);
            form.addEventListener('submit', event => { event.preventDefault(); name.blur(); });
            syncFormats();
        });

        document.querySelectorAll('input[name="calendar_provider"]').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('[data-provider-card]').forEach(card => {
                    const chosen = card.querySelector('input').checked;
                    card.classList.toggle('border-indigo-300', chosen);
                    card.classList.toggle('bg-indigo-50/50', chosen);
                    card.classList.toggle('border-gray-200', !chosen);
                });
            });
        });

    });
</script>
@endpush
