@extends('dashboard')
@section('title', 'Create Letter')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Create a letter from a template or write a custom one, in Bahasa Indonesia or English — then sign it with the master data signature and send it.')

@php
    use App\Models\Letters\Letter;
    use App\Support\Letters\LetterTemplates;

    $canCreate = $canDo('general.letters.compose', 'create');
    $canEdit   = $canDo('general.letters.compose', 'edit');
    $canDelete = $canDo('general.letters.compose', 'delete');
    $editing   = $letter !== null;
    $canSubmit = $editing ? $canEdit : $canCreate;

    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $help  = 'text-[11px] text-gray-400 mt-1';
    $filterForm = 'composeFilters';
    $filter = 'hr-general.recruitment.components.header-filter';
    $action = 'hr-general.recruitment.components.icon-action';

    // What the form starts with: what was typed (after a failed save), the letter being edited, the request being answered, or the type's defaults.
    $fields = old('fields', $letter?->fields ?? []);
    $templateKey = old('template_key', $letter?->template_key ?? $letterRequest?->template_key ?? $templates->keys()->first());
    $type = $mode === 'custom' ? LetterTemplates::CUSTOM : $templateKey;
    $defaults = $typeDefaults[$type] ?? $typeDefaults->first();
    $requestEmployee = $letterRequest ? $employees->firstWhere('id', $letterRequest->employee_id) : null;
    $start = [
        'language'              => old('language', $letter?->language ?? $letterRequest?->language ?? $defaults['language']),
        'letter_date'           => old('letter_date', $letter?->letter_date?->toDateString() ?? now()->toDateString()),
        'letter_number'         => old('letter_number', $letter?->letter_number),
        'letter_code_id'        => old('letter_code_id', $letter?->letter_code_id ?? $defaults['letter_code_id']),
        'employee_id'           => old('employee_id', $letter?->employee_id ?? $letterRequest?->employee_id),
        'signatory_employee_id' => old('signatory_employee_id', $letter?->signatory_employee_id ?? $defaults['signatory_employee_id']),
        'signatory_name'        => old('signatory_name', $letter?->signatory_name ?? $defaults['signatory_name']),
        'signatory_title'       => old('signatory_title', $letter?->signatory_title ?? $defaults['signatory_title']),
        'recipient_email'       => old('recipient_email', $letter?->recipient_email ?? $requestEmployee['email'] ?? null),
        'notes'                 => old('notes', $letter?->notes),
        'subject'               => old('subject', $letter?->subject ?? $letterRequest?->request_type_name),
        // A custom letter answering a request starts titled with what was asked for, addressed to the employee.
        'counterparty'          => old('counterparty', $letter?->counterparty ?? $letterRequest?->employeeName()),
        'body'                  => old('body', $letter?->fields['body'] ?? null),
        'use_letterhead'        => old('_token') ? (bool) old('use_letterhead') : ($letter?->use_letterhead ?? true),
    ];
    // A request's purpose is what the letter is for.
    if ($letterRequest && empty($fields['purpose'])) {
        $fields['purpose'] = $letterRequest->purpose;
    }
    $sampleNumber = app(\App\Services\Letters\LetterNumberService::class)->outgoingNumber(now(), $nextSequence, 'OF', $start['language']);
    $me = (int) session('user.id');
    // The confirmation before signing: plain for your own signature; for someone else's, a reminder to agree it with them first.
    $signConfirm = fn ($row) => (int) $row->signatory_employee_id === $me
        ? "Sign letter {$row->letter_number} with your signature from the employee master data?"
        : "This applies the signature of {$row->signatory_name} from the employee master data to letter {$row->letter_number}. "
            . "Have you confirmed with {$row->signatory_name} (outside the app) that they agree?";
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    @if($letterRequest)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs bg-amber-50 border border-amber-200 text-amber-800 rounded-lg px-4 py-3">
            <span class="font-semibold"><i class="fas fa-inbox mr-1"></i> Answering the request of {{ $letterRequest->employeeName() }}</span>
            <span>{{ $letterRequest->typeLabel() }} · {{ strtoupper($letterRequest->language) }}{{ $letterRequest->is_other ? ' · Other (typed by the employee)' : '' }}</span>
            <span>Purpose: {{ $letterRequest->purpose }}</span>
            @if($letterRequest->needed_by)<span>Needed by {{ $letterRequest->needed_by->format('d M Y') }}</span>@endif
            @if($letterRequest->notes)<span class="italic">"{{ $letterRequest->notes }}"</span>@endif
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <form id="composeForm" method="POST" autocomplete="off"
            action="{{ $editing ? route('general.letters.compose.update', $letter) : route('general.letters.compose.store') }}"
            class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
            @csrf
            <input type="hidden" name="mode" value="{{ $mode }}">
            @if($letterRequest && !$editing)
                <input type="hidden" name="letter_request_id" value="{{ $letterRequest->id }}">
            @endif

            <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">
                        {{ $editing ? 'Edit Letter ' . $letter->letter_number : ($mode === 'custom' ? 'New Custom Letter' : 'New Letter from a Template') }}
                    </h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        {{ $editing ? 'Saving generates the letter again; a signed letter has to be signed again.' : 'Preview first if you like — it saves nothing and uses up no number.' }}
                    </p>
                </div>
                @if(!$editing && !$letterRequest)
                    <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="tablist">
                        <a href="{{ route('general.letters.compose.index', ['mode' => 'template']) }}"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold {{ $mode === 'template' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                            <i class="fas fa-file-lines mr-1"></i> From Template
                        </a>
                        @if($customActive)
                            <a href="{{ route('general.letters.compose.index', ['mode' => 'custom']) }}"
                                class="px-3 py-1.5 rounded-md text-xs font-semibold {{ $mode === 'custom' ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                                <i class="fas fa-pen-nib mr-1"></i> Custom Letter
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            <fieldset @disabled(!$canSubmit) class="px-5 py-4 space-y-4">
                {{-- Language: chosen per letter; the template's default comes from Settings. --}}
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="block text-xs font-semibold text-gray-700">Letter Language <span class="text-red-500">*</span></span>
                        <p class="text-[11px] text-gray-500 mt-0.5">The letter, its date and its email are written in it, and a generated number prints IN / EN.</p>
                    </div>
                    @include('hr-general.recruitment.components.language-switch', [
                        'switchName' => 'language', 'switchId' => 'composeLanguage', 'switchValue' => $start['language'], 'languages' => $languages,
                    ])
                </div>

                @if($mode === 'template')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="templateKey" class="{{ $label }}">Letter Template <span class="text-red-500">*</span></label>
                            @if($editing || $letterRequest)
                                <input type="hidden" name="template_key" value="{{ $templateKey }}">
                                <p class="text-sm font-semibold text-gray-800 py-2">{{ LetterTemplates::label($templateKey) }}</p>
                            @else
                                <select name="template_key" id="templateKey" required class="{{ $input }}">
                                    @foreach($templates as $key => $template)
                                        <option value="{{ $key }}" @selected($templateKey === $key)>{{ $template['label'] }} — {{ $template['label_en'] }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div id="employeeBlock">
                            <label for="employeeId" class="{{ $label }}">Employee <span class="text-red-500" id="employeeRequired">*</span></label>
                            @if($letterRequest)
                                <input type="hidden" name="employee_id" value="{{ $letterRequest->employee_id }}">
                                <p class="text-sm font-semibold text-gray-800 py-2">{{ $letterRequest->employeeName() }}</p>
                            @else
                                <select name="employee_id" id="employeeId" data-searchable="true" data-search-placeholder="Name, employee ID…" class="{{ $input }}">
                                    <option value="">-- Pick an employee --</option>
                                    @foreach($employees as $person)
                                        <option value="{{ $person['id'] }}" @selected((string) $start['employee_id'] === (string) $person['id'])>
                                            {{ $person['name'] }} ({{ $person['eci'] }}){{ $person['position'] ? ' — ' . $person['position'] : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                            <p class="{{ $help }}">Name, employee ID, position, department and join date are filled in from the master data.</p>
                        </div>
                    </div>

                    {{-- The fields of each template; only the chosen template's are enabled and sent. --}}
                    @foreach($templates as $key => $template)
                        <div data-template-fields="{{ $key }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 {{ $templateKey === $key ? '' : 'hidden' }}">
                            @foreach($template['fields'] as $name => $field)
                                @php $fieldId = "f-{$key}-{$name}"; $current = $templateKey === $key ? ($fields[$name] ?? null) : null; @endphp
                                <div class="{{ in_array($field['type'], ['textarea', 'items'], true) ? 'md:col-span-2' : '' }}">
                                    <label for="{{ $fieldId }}" class="{{ $label }}">{{ $field['label'] }} @if($field['required'])<span class="text-red-500">*</span>@endif</label>
                                    @switch($field['type'])
                                        @case('textarea')
                                            <textarea name="fields[{{ $name }}]" id="{{ $fieldId }}" rows="3" maxlength="5000" @required($field['required']) class="{{ $input }}">{{ $current }}</textarea>
                                            @break
                                        @case('date')
                                            <input type="date" name="fields[{{ $name }}]" id="{{ $fieldId }}" value="{{ $current }}" @required($field['required']) class="{{ $input }}">
                                            @break
                                        @case('money')
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">Rp</span>
                                                <input type="text" inputmode="numeric" name="fields[{{ $name }}]" id="{{ $fieldId }}" data-money
                                                    value="{{ $current !== null && $current !== '' ? number_format((float) preg_replace('/\D/', '', (string) $current), 0, ',', '.') : '' }}"
                                                    @required($field['required']) class="{{ $input }} pl-9 text-right">
                                            </div>
                                            @break
                                        @case('items')
                                            <div data-items="{{ $key }}" class="space-y-2">
                                                @foreach(($current ?: [['name' => '', 'qty' => '', 'note' => '']]) as $i => $item)
                                                    <div data-item-row class="grid grid-cols-12 gap-2">
                                                        <input type="text" name="fields[{{ $name }}][{{ $i }}][name]" value="{{ $item['name'] ?? '' }}" placeholder="Item" maxlength="255" class="{{ $input }} col-span-5">
                                                        <input type="text" name="fields[{{ $name }}][{{ $i }}][qty]" value="{{ $item['qty'] ?? '' }}" placeholder="Qty" maxlength="50" class="{{ $input }} col-span-2">
                                                        <input type="text" name="fields[{{ $name }}][{{ $i }}][note]" value="{{ $item['note'] ?? '' }}" placeholder="Notes" maxlength="255" class="{{ $input }} col-span-4">
                                                        <button type="button" data-item-remove class="col-span-1 text-red-500 hover:text-red-700" title="Remove this item" aria-label="Remove this item"><i class="fas fa-trash text-xs"></i></button>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <button type="button" data-item-add="{{ $key }}" data-item-name="{{ $name }}" class="mt-2 text-xs font-semibold" style="color: var(--primary-color);">
                                                <i class="fas fa-plus text-[10px]"></i> Add an item
                                            </button>
                                            @break
                                        @default
                                            <input type="text" name="fields[{{ $name }}]" id="{{ $fieldId }}" value="{{ $current }}" maxlength="255"
                                                placeholder="{{ $field['placeholder'] ?? '' }}" @required($field['required']) class="{{ $input }}">
                                    @endswitch
                                    @if(!empty($field['help']))<p class="{{ $help }}">{{ $field['help'] }}</p>@endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label for="subject" class="{{ $label }}">Subject <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" id="subject" required maxlength="255" value="{{ $start['subject'] }}" class="{{ $input }}">
                        </div>
                        <div class="md:col-span-2">
                            <label for="counterparty" class="{{ $label }}">Recipient / Addressed To</label>
                            <input type="text" name="counterparty" id="counterparty" maxlength="255" value="{{ $start['counterparty'] }}" class="{{ $input }}">
                        </div>
                        <div class="md:col-span-2">
                            <label for="body" class="{{ $label }}">Letter Text <span class="text-red-500">*</span></label>
                            <textarea name="body" id="body" rows="12" required maxlength="20000" class="{{ $input }} leading-relaxed">{{ $start['body'] }}</textarea>
                            <p class="{{ $help }}">A blank line starts a new paragraph. The title, number, date and signature are added around it.</p>
                        </div>
                        <div class="md:col-span-2">
                            @include('hr-general.recruitment.components.toggle-switch', [
                                'toggleName' => 'use_letterhead', 'toggleChecked' => $start['use_letterhead'], 'toggleText' => 'Print on the letterhead (set for Custom Letter in Settings)',
                            ])
                        </div>
                    </div>
                @endif

                <div class="border-t border-gray-100 pt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="letterDate" class="{{ $label }}">Letter Date <span class="text-red-500">*</span></label>
                        <input type="date" name="letter_date" id="letterDate" required value="{{ $start['letter_date'] }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="letterCode" class="{{ $label }}">Letter Code</label>
                        <select name="letter_code_id" id="letterCode" class="{{ $input }}">
                            <option value="">-- None --</option>
                            @foreach($codes as $code)
                                <option value="{{ $code->id }}" @selected((string) $start['letter_code_id'] === (string) $code->id)>{{ $code->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="letterNumber" class="{{ $label }}">Letter Number</label>
                        <input type="text" name="letter_number" id="letterNumber" maxlength="100" value="{{ $start['letter_number'] }}" class="{{ $input }}"
                            placeholder="{{ $editing ? '' : 'Empty: generated' }}">
                        <p class="{{ $help }}">
                            @if($editing)
                                A generated number follows the code, language and date; the running number stays.
                            @else
                                Next generated, e.g.: <span class="font-mono">{{ $sampleNumber }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- The signer: role first, then the people of it who may sign (Settings → Signers). --}}
                @php
                    $startRole = old('signatory_role_id')
                        ?: collect($signingRoles)->first(fn ($role) => collect($role['people'])->contains('id', (int) $start['signatory_employee_id']))['id'] ?? null;
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="signatoryRole" class="{{ $label }}">Signer Role <span class="text-red-500">*</span></label>
                        <select name="signatory_role_id" id="signatoryRole" required class="{{ $input }}">
                            @foreach($signingRoles as $role)
                                <option value="{{ $role['id'] }}" @selected((string) $startRole === (string) $role['id'])>{{ $role['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="signatory" class="{{ $label }}">Signer <span class="text-red-500">*</span></label>
                        <select name="signatory_employee_id" id="signatory" required data-searchable="true" data-search-placeholder="Search name…" class="{{ $input }}">
                            {{-- Filled for the role picked; this is the first fill, for pages without script. --}}
                            @foreach(collect($signingRoles)->firstWhere('id', (int) $startRole)['people'] ?? [] as $person)
                                <option value="{{ $person['id'] }}" @selected((string) $start['signatory_employee_id'] === (string) $person['id'])>
                                    {{ $person['name'] }}{{ $person['id'] === $me ? ' (you)' : '' }}{{ $person['has_signature'] ? '' : ' (no signature yet)' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="signatoryName" class="{{ $label }}">Signatory Name <span class="text-red-500">*</span></label>
                        <input type="text" name="signatory_name" id="signatoryName" required maxlength="150" value="{{ $start['signatory_name'] }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="signatoryTitle" class="{{ $label }}">Signatory Position <span class="text-red-500">*</span></label>
                        <input type="text" name="signatory_title" id="signatoryTitle" required maxlength="150" value="{{ $start['signatory_title'] }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-4 -mt-2">
                        @if(collect($signingRoles)->isEmpty())
                            <p class="text-[11px] text-red-600">Nobody can sign letters yet — add a role in Settings → Signers.</p>
                        @else
                            <p class="{{ $help }}">Pick the role, then the person. You by default when your role may sign; who can sign is set in Settings → Signers.</p>
                            <p id="signatoryOnBehalf" class="hidden text-[11px] text-amber-700 mt-1">
                                <i class="fas fa-handshake text-[10px]"></i> You sign for someone else: confirm with them before applying their signature.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="recipientEmail" class="{{ $label }}">Send To (email)</label>
                        <input type="email" name="recipient_email" id="recipientEmail" maxlength="150" value="{{ $start['recipient_email'] }}" class="{{ $input }}">
                        <p class="{{ $help }}">Where Send emails the signed letter. Picking an employee fills in their work email.</p>
                    </div>
                    <div>
                        <label for="notes" class="{{ $label }}">Archive Note</label>
                        <textarea name="notes" id="notes" rows="2" maxlength="2000" class="{{ $input }}" placeholder="Optional, kept with the letter in the register">{{ $start['notes'] }}</textarea>
                    </div>
                </div>
            </fieldset>

            <div class="px-5 py-4 border-t border-gray-100 flex flex-wrap justify-end gap-2">
                <a href="{{ route('general.letters.compose.index', ['mode' => $mode]) }}" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">
                    {{ $editing || $letterRequest ? 'Cancel' : 'Reset Form' }}
                </a>
                <button type="submit" formaction="{{ route('general.letters.compose.preview') }}" formtarget="_blank" formnovalidate
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold border rounded-lg hover:bg-indigo-50" style="color: var(--primary-color); border-color: var(--primary-color);">
                    <i class="fas fa-eye text-[10px]"></i> Preview
                </button>
                @if($canSubmit)
                    <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                        <i class="fas fa-file-pdf text-[10px]"></i> {{ $editing ? 'Save & Generate Again' : 'Generate & Open PDF' }}
                    </button>
                @endif
            </div>
        </form>

        <div class="space-y-4">
            @if($mode === 'template')
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-5 py-3.5 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-800">Templates</h3>
                        <p class="text-[10px] text-gray-400 mt-0.5">Switched on in Settings; each is written in Bahasa Indonesia and English.</p>
                    </div>
                    <div class="p-3 space-y-2">
                        @foreach($templates as $key => $template)
                            <div class="rounded-lg border px-3 py-2.5 {{ $templateKey === $key ? 'border-indigo-200 bg-indigo-50/50' : 'border-gray-200' }}" data-template-card="{{ $key }}">
                                <p class="text-xs font-bold text-gray-800">{{ $template['label'] }} <span class="font-normal text-gray-400">· {{ $template['label_en'] }}</span></p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $template['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="text-[11px] text-cyan-800 bg-cyan-50 border border-cyan-100 rounded-xl px-4 py-3 leading-relaxed">
                <p class="font-semibold mb-1"><i class="fas fa-circle-info mr-1"></i> How a letter goes out</p>
                Generate it (it gets its number) → <strong>Sign</strong> it with the signatory's signature from the employee master data →
                <strong>Send</strong> it by email. Every letter lands in the Letter Register; a numbered letter is voided, never deleted, and its number is never given out again.
            </div>
        </div>
    </div>

    {{-- Letters generated here. --}}
    <form id="{{ $filterForm }}" method="GET" action="{{ route('general.letters.compose.index') }}" data-filter-form class="hidden">
        <input type="hidden" name="mode" value="{{ $mode }}">
    </form>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-800">Generated Letters</h3>
                <p class="text-[10px] text-gray-400 mt-0.5">Template and custom letters. Use the <i class="fas fa-filter text-[9px]"></i> icons in the table header to search &amp; filter.</p>
            </div>
            <div data-live-region="toolbar">
                @if($hasFilters)
                    <a href="{{ route('general.letters.compose.index', ['mode' => $mode]) }}"
                        class="px-3 py-2 bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm flex items-center gap-1.5 whitespace-nowrap">
                        <i class="fas fa-rotate-left text-[10px]"></i> Reset Filter
                    </a>
                @endif
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200 select-none">
                    <tr>
                        <th class="px-4 py-3 w-12">No.</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'search', 'label' => 'Letter', 'type' => 'search',
                            'value' => $filters['search'], 'placeholder' => 'Number, subject, recipient…', 'thClass' => 'min-w-56',
                        ])
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'type', 'label' => 'Type', 'type' => 'options',
                            'value' => $filters['type'], 'options' => $typeOptions, 'allLabel' => 'All types', 'thClass' => 'min-w-36',
                        ])
                        <th class="px-4 py-3 min-w-24">Date</th>
                        @include($filter, [
                            'form' => $filterForm, 'name' => 'status', 'label' => 'Status', 'type' => 'options',
                            'value' => $filters['status'], 'options' => $statusOptions, 'allLabel' => 'All statuses', 'thClass' => 'min-w-28',
                        ])
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700" data-live-region="rows">
                    @forelse($letters as $row)
                        @php $status = $row->status(); @endphp
                        <tr class="hover:bg-gray-50 align-top {{ $row->isVoid() ? 'opacity-60' : '' }}">
                            <td class="px-4 py-3 text-gray-400">{{ $letters->firstItem() + $loop->index }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-gray-800 {{ $row->isVoid() ? 'line-through' : '' }}">{{ $row->letter_number }}</span>
                                @if($row->language === 'en')<span class="ml-1 px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[9px] font-bold">EN</span>@endif
                                <span class="block text-gray-700">{{ $row->subject }}</span>
                                <span class="block text-[10px] text-gray-400">{{ $row->counterparty ?: '-' }}@if($row->request) · for a request @endif</span>
                            </td>
                            <td class="px-4 py-3">{{ $row->typeLabel() }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row->letter_date->format('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ Letter::STATUS_BADGES[$status] }}">{{ Letter::STATUSES[$status] }}</span>
                                @if($row->isSigned())
                                    <span class="block text-[10px] text-gray-400 mt-0.5">
                                        <i class="fas fa-signature text-[9px]"></i> {{ $row->signatory_name }}
                                        @if($row->signedOnBehalf())
                                            · applied by {{ $row->signedBy?->basicData?->full_name ?: $row->signedBy?->eci }}
                                        @endif
                                    </span>
                                @endif
                                @if($row->email_status === Letter::EMAIL_FAILED)
                                    <span class="block text-[10px] text-red-600 mt-0.5" title="{{ $row->email_error }}"><i class="fas fa-triangle-exclamation"></i> Email failed</span>
                                @endif
                                @if($row->isVoid() && $row->void_reason)
                                    <span class="block text-[10px] text-red-600 mt-0.5">{{ $row->void_reason }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1.5">
                                    @include($action, ['icon' => 'file-pdf', 'tone' => 'gray', 'label' => $row->isSigned() ? 'Open the signed PDF' : 'Open the PDF (not signed yet)', 'href' => route('general.letters.pdf', $row), 'newTab' => true])
                                    @if($canEdit && $row->isEditable())
                                        @include($action, ['icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit & generate again', 'href' => route('general.letters.compose.edit', $row)])
                                        @include($action, [
                                            'icon' => 'signature', 'tone' => 'green',
                                            'label' => $row->isSigned() ? 'Sign again with the master data signature' : 'Sign with the master data signature',
                                            'post' => route('general.letters.compose.sign', $row),
                                            'confirm' => $signConfirm($row),
                                            'confirmTitle' => (int) $row->signatory_employee_id === $me ? 'Sign Letter' : 'Sign on Behalf of ' . $row->signatory_name,
                                            'confirmOk' => (int) $row->signatory_employee_id === $me ? 'Sign' : 'Confirmed — sign',
                                        ])
                                    @endif
                                    @if($canEdit && $row->canBeSent())
                                        @include($action, [
                                            'icon' => $row->email_status === Letter::EMAIL_FAILED ? 'rotate-right' : 'paper-plane',
                                            'tone' => $row->email_status === Letter::EMAIL_FAILED ? 'amber' : 'indigo',
                                            'label' => $row->email_status === Letter::EMAIL_FAILED ? 'Resend the failed email' : 'Send',
                                            'onclick' => 'openLetterSendModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'action' => route('general.letters.compose.send', $row),
                                                'title' => $row->request && $row->request->isOpen() ? 'Complete & Send' : 'Send Letter',
                                                'again' => $row->email_status === Letter::EMAIL_FAILED,
                                                'number' => $row->letter_number, 'name' => $row->counterparty, 'email' => $row->recipient_email,
                                                ...($emails[$row->id] ?? []),
                                            ],
                                        ])
                                    @endif
                                    @if($canDelete && !$row->isVoid())
                                        @include($action, [
                                            'icon' => 'ban', 'tone' => 'red', 'label' => 'Void',
                                            'onclick' => 'openReasonModal(JSON.parse(this.dataset.payload))',
                                            'data' => [
                                                'action' => route('general.letters.compose.void', $row), 'field' => 'void_reason',
                                                'title' => 'Void Letter', 'button' => 'Void',
                                                'intro' => "Void letter {$row->letter_number}? It stays in the register marked void, and its number is not given out again.",
                                            ],
                                        ])
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-gray-400">{{ $hasFilters ? 'No letters match these filters.' : 'No letters generated yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div data-live-region="pagination">
            @include('hr-general.recruitment.components.pagination', ['paginator' => $letters, 'form' => $filterForm])
        </div>
    </div>
</div>

@include('hr-general.letters.components.modals')
@include('hr-general.recruitment.components.confirm-forms')
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('composeForm');
        if (!form) return;
        const byId = id => document.getElementById(id);
        const editing = @json($editing);
        const me = @json($me);
        const templates = {{ Js::from($templates->map(fn ($t) => ['employee' => $t['employee']])) }};
        const typeDefaults = {{ Js::from($typeDefaults) }};
        const employees = {{ Js::from($employees->keyBy('id')) }};
        const signatories = {{ Js::from($signatories->keyBy('id')) }};
        // Role → the people of it who may sign, as Create Letter offers them.
        const signingRoles = {{ Js::from(collect($signingRoles)->keyBy('id')) }};

        function setSelect(select, value) {
            if (!select) return;
            select.value = value == null ? '' : String(value);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Only the chosen template's fields are shown, required and sent.
        function showTemplate(key) {
            form.querySelectorAll('[data-template-fields]').forEach(group => {
                const active = group.dataset.templateFields === key;
                group.classList.toggle('hidden', !active);
                group.querySelectorAll('input, textarea, select, button').forEach(el => { el.disabled = !active; });
            });
            document.querySelectorAll('[data-template-card]').forEach(card => {
                const active = card.dataset.templateCard === key;
                card.classList.toggle('border-indigo-200', active);
                card.classList.toggle('bg-indigo-50/50', active);
                card.classList.toggle('border-gray-200', !active);
            });

            const takes = templates[key]?.employee;
            const block = byId('employeeBlock');
            if (block) {
                block.classList.toggle('hidden', !takes);
                const select = byId('employeeId');
                if (select) { select.required = takes === 'required'; select.disabled = !takes; }
                byId('employeeRequired')?.classList.toggle('hidden', takes !== 'required');
            }
        }

        // A new letter starts with the type's defaults from Settings: language, code, signatory.
        function applyDefaults(type) {
            const d = typeDefaults[type];
            if (!d || editing) return;
            const radio = form.querySelector(`input[name="language"][value="${d.language}"]`);
            if (radio) radio.checked = true;
            setSelect(byId('letterCode'), d.letter_code_id);
            const role = Object.values(signingRoles).find(r => (r.people || []).some(p => String(p.id) === String(d.signatory_employee_id)));
            if (role) { byId('signatoryRole').value = String(role.id); byId('signatoryRole').dispatchEvent(new Event('change', { bubbles: true })); }
            fillSigners(d.signatory_employee_id);
            byId('signatoryName').value = d.signatory_name || '';
            byId('signatoryTitle').value = d.signatory_title || '';
        }

        const templateSelect = byId('templateKey');
        if (templateSelect) {
            templateSelect.addEventListener('change', () => { showTemplate(templateSelect.value); applyDefaults(templateSelect.value); });
        }
        const startTemplate = templateSelect ? templateSelect.value : form.querySelector('input[name="template_key"]')?.value;
        if (startTemplate) showTemplate(startTemplate);

        // Role first: the signer list holds the people of the role picked; you stay picked when your role is.
        function fillSigners(keepId) {
            const select = byId('signatory');
            const role = signingRoles[byId('signatoryRole')?.value];
            if (!select || !role) return;
            const people = role.people || [];
            const wanted = people.some(p => String(p.id) === String(keepId)) ? keepId
                : (people.some(p => p.id === me) ? me : people[0]?.id);
            select.innerHTML = '';
            people.forEach(p => select.add(new Option(
                p.name + (p.id === me ? ' (you)' : '') + (p.has_signature ? '' : ' (no signature yet)'), p.id, false, String(p.id) === String(wanted))));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        byId('signatoryRole')?.addEventListener('change', () => fillSigners(byId('signatory')?.value));

        // Someone else signs: a reminder to agree it with them before applying their signature.
        const showOnBehalf = () => byId('signatoryOnBehalf')?.classList.toggle('hidden', !byId('signatory') || Number(byId('signatory').value) === me);
        byId('signatory')?.addEventListener('change', function () {
            showOnBehalf();
            const person = signatories[this.value];
            if (!person) return;
            byId('signatoryName').value = person.name;
            if (person.position) byId('signatoryTitle').value = person.position;
        });
        showOnBehalf();

        // Picking the employee fills in where the letter is emailed, unless something else was typed.
        let autoEmail = byId('recipientEmail')?.value || '';
        byId('employeeId')?.addEventListener('change', function () {
            const input = byId('recipientEmail');
            const person = employees[this.value];
            if (input && (input.value === '' || input.value === autoEmail)) {
                input.value = person?.email || '';
                autoEmail = input.value;
            }
        });

        // Money as typed, with thousands separators.
        form.addEventListener('input', event => {
            if (!event.target.matches('[data-money]')) return;
            const digits = event.target.value.replace(/\D/g, '');
            event.target.value = digits === '' ? '' : Number(digits).toLocaleString('id-ID');
        });

        // Items of a receipt: add / remove rows.
        form.addEventListener('click', event => {
            const add = event.target.closest('[data-item-add]');
            if (add) {
                const list = form.querySelector(`[data-items="${add.dataset.itemAdd}"]`);
                const index = Date.now();
                const name = add.dataset.itemName;
                const row = list.querySelector('[data-item-row]').cloneNode(true);
                row.querySelectorAll('input').forEach(input => {
                    input.value = '';
                    input.name = input.name.replace(/\[\d+\]\[(name|qty|note)\]$/, `[${index}][$1]`);
                });
                list.appendChild(row);
                return;
            }
            const remove = event.target.closest('[data-item-remove]');
            if (remove) {
                const list = remove.closest('[data-items]');
                if (list.querySelectorAll('[data-item-row]').length > 1) remove.closest('[data-item-row]').remove();
                else remove.closest('[data-item-row]').querySelectorAll('input').forEach(input => { input.value = ''; });
            }
        });

        @if(session('openPdf'))
            window.open(@json(session('openPdf')), '_blank');
        @endif
    })();
</script>
@endpush
