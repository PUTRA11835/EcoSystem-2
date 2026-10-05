@extends('dashboard')
@section('title', 'Letter Templates — Settings')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Set up letters in order: how they are numbered, what each letter uses, what they are printed on, and what employees can ask for.')

@php
    // Capabilities of this tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.letter-templates', 'create');
    $canEdit   = $canDo('general.letter-templates', 'edit');
    $canDelete = $canDo('general.letter-templates', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $file = 'block w-full text-xs text-gray-600 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border file:border-gray-200 file:bg-white file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-50';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $help = 'text-[11px] text-gray-400 mt-1';
    $inputInline = str_replace('w-full ', '', $input); // for fields that set their own width
    $saveButton = 'px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90';
    $deleteButton = 'w-8 h-8 inline-flex items-center justify-center rounded-lg border bg-white transition-all shrink-0 border-red-200 text-red-600 hover:bg-red-50';

    // The set-up flow, in order. The open step is kept in the address (?section=), so saving comes back to it.
    $steps = [
        'numbering'   => ['Numbering & Codes', 'How a letter number is built, and the codes in it', 'hashtag'],
        'types'       => ['Letter Types', 'The code and language each letter uses', 'file-lines'],
        'signers'     => ['Signers', 'Who can be picked to sign a letter', 'signature'],
        'letterheads' => ['Letterheads', 'The page each letter is printed on', 'image'],
        'requests'    => ['Employee Requests', 'What employees can ask HR for', 'inbox'],
    ];
    $section = request('section');
    if (!isset($steps[$section])) {
        $section = match (true) {
            old('_letterhead') !== null                                      => 'letterheads',
            old('_new_code') !== null || old('outgoing_number_format') !== null => 'numbering',
            old('types') !== null                                            => 'types',
            old('_signer') !== null                                          => 'signers',
            default                                                          => 'numbering',
        };
    }
    $offering = \App\Models\Letterhead::TYPE_OFFERING_LETTER;
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    {{-- ── The flow ─────────────────────────────────────────────────────── --}}
    <nav class="bg-white rounded-xl border border-gray-200 shadow-sm p-2 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-2" aria-label="Settings steps">
        @foreach($steps as $key => [$stepTitle, $stepHint, $stepIcon])
            <button type="button" data-step="{{ $key }}" onclick="showSettingsStep('{{ $key }}')"
                class="settings-step relative flex items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors {{ $section === $key ? 'bg-indigo-50 ring-1 ring-indigo-200' : 'hover:bg-gray-50' }}">
                <span class="step-number w-7 h-7 shrink-0 rounded-full flex items-center justify-center text-xs font-bold {{ $section === $key ? 'primary-gradient text-white' : 'bg-gray-100 text-gray-500' }}">{{ $loop->iteration }}</span>
                <span class="min-w-0">
                    <span class="block text-xs font-bold text-gray-800"><i class="fas fa-{{ $stepIcon }} text-[10px] text-gray-400 mr-1"></i>{{ $stepTitle }}</span>
                    <span class="block text-[10px] text-gray-500 leading-snug">{{ $stepHint }}</span>
                </span>
                @unless($loop->last)
                    <i class="fas fa-chevron-right hidden xl:block absolute -right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-300"></i>
                @endunless
            </button>
        @endforeach
    </nav>

    {{-- ── ① Numbering & codes ──────────────────────────────────────────── --}}
    <section data-panel="numbering" class="{{ $section === 'numbering' ? '' : 'hidden' }}">
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
            <form action="{{ route('general.letters.settings.numbering.update') }}" method="POST" class="xl:col-span-3 bg-white rounded-xl border border-gray-200 shadow-sm">
                @csrf
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Number Format</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        One running number for every outgoing letter — whatever its code or language, offering letters included — restarting every year.
                        A number once given out is never given out again.
                    </p>
                </div>
                <fieldset @disabled(!$canEdit) class="px-5 py-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div class="sm:col-span-3">
                            <label for="outgoingFormat" class="{{ $label }}">Outgoing letters <span class="text-red-500">*</span></label>
                            <input type="text" name="outgoing_number_format" id="outgoingFormat" required maxlength="100" data-format="outgoing"
                                value="{{ old('outgoing_number_format', $settings->outgoing_number_format) }}" class="{{ $input }} font-mono">
                        </div>
                        <div>
                            <label for="outgoingDigits" class="{{ $label }}">Digits</label>
                            <input type="number" name="outgoing_number_digits" id="outgoingDigits" required min="1" max="6" data-digits="outgoing"
                                value="{{ old('outgoing_number_digits', $settings->outgoing_number_digits) }}" class="{{ $input }}">
                        </div>
                        <p class="sm:col-span-4 -mt-1 text-[11px] text-gray-600">Next, code OF, Indonesian: <strong class="font-mono" data-preview="outgoing"></strong></p>

                        <div class="sm:col-span-3">
                            <label for="incomingFormat" class="{{ $label }}">Incoming letters (agenda number) <span class="text-red-500">*</span></label>
                            <input type="text" name="incoming_agenda_format" id="incomingFormat" required maxlength="100" data-format="incoming"
                                value="{{ old('incoming_agenda_format', $settings->incoming_agenda_format) }}" class="{{ $input }} font-mono">
                        </div>
                        <div>
                            <label for="incomingDigits" class="{{ $label }}">Digits</label>
                            <input type="number" name="incoming_agenda_digits" id="incomingDigits" required min="1" max="6" data-digits="incoming"
                                value="{{ old('incoming_agenda_digits', $settings->incoming_agenda_digits) }}" class="{{ $input }}">
                        </div>
                        <p class="sm:col-span-4 -mt-1 text-[11px] text-gray-600">Next incoming letter: <strong class="font-mono" data-preview="incoming"></strong> — a running number of its own.</p>
                    </div>

                    {{-- What this does to offering letters, which keep their own format. --}}
                    <div class="rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-[11px] text-blue-900 leading-relaxed">
                        <p class="font-semibold"><i class="fas fa-circle-info mr-1"></i> Does this change offering letter numbers?</p>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            <li><strong>The format — no.</strong> Offering letters keep their own format, in Recruitment → Offering Settings.</li>
                            <li><strong>The running number — shared.</strong> Offering letters take <code>{seq}</code> from the same counter as every outgoing letter, so no two letters in the register share a number.</li>
                            <li><strong>The {lang} codes below — yes,</strong> they are used by every letter, offering letters included.</li>
                        </ul>
                    </div>

                    <details class="rounded-lg bg-gray-50 px-3 py-2 text-[11px] text-gray-600">
                        <summary class="cursor-pointer font-semibold text-gray-700">Tokens you can use</summary>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                            @foreach($tokens as $token => $meaning)
                                <span><code class="px-1 rounded bg-white border border-gray-200">{{ $token }}</code> {{ $meaning }}</span>
                            @endforeach
                        </div>
                        <p class="mt-2 text-gray-500">Everything else is printed as typed. The Offering Letter keeps its own format (Offering Settings) on the same running number.</p>
                    </details>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @foreach($languages as $code => $languageLabel)
                            <div>
                                <label for="langCode-{{ $code }}" class="{{ $label }}">{lang} for {{ $languageLabel }}</label>
                                <input type="text" name="language_codes[{{ $code }}]" id="langCode-{{ $code }}" required maxlength="5" data-lang-code="{{ $code }}"
                                    value="{{ old("language_codes.{$code}", $settings->languageCode($code)) }}" class="{{ $input }} uppercase font-mono">
                            </div>
                        @endforeach
                        <p class="sm:col-span-2 -mt-1 {{ $help }}">IN rather than the ISO code ID, because the letters already issued carry IN. Change it only at the start of a year.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 border-t border-gray-100 pt-4">
                        <div>
                            <label for="companyName" class="{{ $label }}">Company name <span class="text-red-500">*</span></label>
                            <input type="text" name="company_name" id="companyName" required maxlength="150" value="{{ old('company_name', $settings->company_name) }}" class="{{ $input }}">
                            <p class="{{ $help }}">Printed in the letters ("an employee of …").</p>
                        </div>
                        <div>
                            <label for="signingCity" class="{{ $label }}">Signing city <span class="text-red-500">*</span></label>
                            <input type="text" name="signing_city" id="signingCity" required maxlength="100" value="{{ old('signing_city', $settings->signing_city) }}" class="{{ $input }}">
                            <p class="{{ $help }}">Printed above the signature, before the date.</p>
                        </div>
                    </div>
                </fieldset>
                @if($canEdit)
                    <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                        <button type="submit" class="{{ $saveButton }}">Save Number Format</button>
                    </div>
                @endif
            </form>

            <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Letter Codes</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        What <code>{code}</code> prints — e.g. EC/09/<strong>OF</strong>/IN/21018/2026. Which letter uses which code is set in
                        <button type="button" onclick="showSettingsStep('types')" class="font-semibold" style="color: var(--primary-color);">② Letter Types</button>.
                        A code already on letters can be switched off, not deleted or renamed.
                    </p>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($codes as $code)
                        <form action="{{ route('general.letters.settings.codes.update', $code) }}" method="POST" class="px-4 py-2.5 flex items-center gap-2">
                            @csrf
                            <fieldset @disabled(!$canEdit) class="contents">
                                <input type="text" name="code" required maxlength="10" value="{{ $code->code }}" aria-label="Code" class="{{ $inputInline }} w-20 uppercase font-mono font-semibold">
                                <input type="text" name="name" maxlength="100" value="{{ $code->name }}" placeholder="What it stands for" aria-label="What it stands for" class="{{ $inputInline }} min-w-0 flex-1">
                                @include('hr-general.recruitment.components.toggle-switch', ['toggleName' => 'is_active', 'toggleChecked' => $code->is_active, 'toggleTitle' => 'Offered on new letters'])
                            </fieldset>
                            @if($canEdit)
                                @include('hr-general.recruitment.components.icon-action', ['icon' => 'check', 'tone' => 'green', 'label' => 'Save code', 'submit' => true])
                            @endif
                            @if($canDelete)
                                <button type="submit" form="deleteCode{{ $code->id }}" title="Delete code" aria-label="Delete code" class="{{ $deleteButton }}"><i class="fas fa-trash text-xs"></i></button>
                            @endif
                        </form>
                    @endforeach
                </div>
                @if($canCreate)
                    <form action="{{ route('general.letters.settings.codes.store') }}" method="POST" class="px-4 py-3 border-t border-gray-100 flex items-center gap-2 bg-gray-50/50 rounded-b-xl">
                        @csrf
                        <input type="hidden" name="_new_code" value="1">
                        <input type="text" name="code" required maxlength="10" placeholder="Code" aria-label="New code" value="{{ old('_new_code') ? old('code') : '' }}" class="{{ $inputInline }} w-20 uppercase font-mono">
                        <input type="text" name="name" maxlength="100" placeholder="What it stands for" aria-label="What it stands for" class="{{ $inputInline }} min-w-0 flex-1">
                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 whitespace-nowrap">
                            <i class="fas fa-plus text-[10px]"></i> Add
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @include('hr-general.letters.components.next-step', ['next' => 'types', 'nextLabel' => '② Letter Types — pick the code and language of each letter'])
    </section>

    {{-- ── ② Letter types ───────────────────────────────────────────────── --}}
    <section data-panel="types" class="{{ $section === 'types' ? '' : 'hidden' }}">
        <form action="{{ route('general.letters.settings.templates.update') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            @csrf
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-800">Letter Types</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">
                    What a new letter of each type starts with — the example shows the number it would get. Language and code can still be changed on each letter.
                    Who can sign is set in the next step.
                </p>
            </div>
            <fieldset @disabled(!$canEdit) class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-2.5">Letter</th>
                            <th class="px-4 py-2.5">In use</th>
                            <th class="px-4 py-2.5">Default language</th>
                            <th class="px-4 py-2.5 min-w-32">Code</th>
                            <th class="px-4 py-2.5">Example number</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($letterTypes as $type => $typeLabel)
                            @php
                                $setting = $typeSettings[$type];
                                $old = fn (string $field, $default) => old("types.{$type}.{$field}", $default);
                            @endphp
                            <tr data-type-row="{{ $type }}" class="hover:bg-gray-50/60">
                                <td class="px-5 py-2.5">
                                    <span class="font-semibold text-gray-800">{{ $typeLabel }}</span>
                                    @if($type === $offering)
                                        <span class="block text-[10px] text-gray-400">Written in Recruitment → Offering Letter</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($type === $offering)
                                        <span class="text-[11px] text-gray-400">Always</span>
                                    @else
                                        @include('hr-general.recruitment.components.toggle-switch', [
                                            'toggleName' => "types[{$type}][is_active]", 'toggleChecked' => (bool) $old('is_active', $setting->is_active ?? true),
                                        ])
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @include('hr-general.recruitment.components.language-switch', [
                                        'switchName' => "types[{$type}][language]", 'switchId' => "language-{$type}", 'languages' => $languages,
                                        'switchValue' => $old('language', $setting->language ?? 'id'), 'switchDisabled' => !$canEdit,
                                    ])
                                </td>
                                <td class="px-4 py-2.5">
                                    <select name="types[{{ $type }}][letter_code_id]" aria-label="Code of {{ $typeLabel }}" data-type-code class="{{ $input }}">
                                        <option value="" data-code="">-- None --</option>
                                        @foreach($codes as $code)
                                            <option value="{{ $code->id }}" data-code="{{ $code->code }}" @selected((string) $old('letter_code_id', $setting->letter_code_id) === (string) $code->id)>
                                                {{ $code->code }}{{ $code->name ? ' — ' . $code->name : '' }}{{ $code->is_active ? '' : ' (off)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if($type === $offering)
                                        <span class="text-[11px] text-gray-400">Its own format, same running number</span>
                                    @else
                                        <span class="font-mono text-[11px] text-gray-700" data-type-preview></span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </fieldset>
            @if($canEdit)
                <div class="px-5 py-3 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="{{ $saveButton }}">Save Letter Types</button>
                </div>
            @endif
        </form>
        @include('hr-general.letters.components.next-step', ['next' => 'signers', 'nextLabel' => '③ Signers — who can be picked to sign a letter'])
    </section>

    {{-- ── ③ Signers ────────────────────────────────────────────────────── --}}
    <section data-panel="signers" class="{{ $section === 'signers' ? '' : 'hidden' }}">
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6 items-start">
            <div class="xl:col-span-3 bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Who Can Sign</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        Role first: let <strong>everyone holding a role</strong> sign, or pick <strong>one person</strong> of it — e.g. only the Finance Manager of a finance role.
                        On Create Letter the signer is chosen the same way: role, then name. For someone outside HR, HR confirms with them outside the app before
                        applying their signature; the letter records who applied it.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider text-[10px] font-bold border-b border-gray-200">
                            <tr>
                                <th class="px-5 py-2.5">Role</th>
                                <th class="px-4 py-2.5">Who</th>
                                <th class="px-4 py-2.5 min-w-48">Position printed</th>
                                <th class="px-4 py-2.5">Can sign</th>
                                <th class="px-4 py-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($signers as $signer)
                                @php
                                    $person = $signer->employee_id ? $employees->firstWhere('id', $signer->employee_id) : null;
                                    $holds = in_array((int) $signer->employee_id, $roleMembers[$signer->role_id] ?? [], true);
                                @endphp
                                <tr>
                                    <td class="px-5 py-2.5 font-semibold text-gray-800">{{ $signer->role?->name ?? 'Deleted role' }}</td>
                                    <td class="px-4 py-2.5">
                                        @if($signer->isWholeRole())
                                            <span class="text-gray-700">Everyone in this role</span>
                                            <span class="block text-[10px] text-gray-400">{{ count($roleMembers[$signer->role_id] ?? []) }} active {{ \Illuminate\Support\Str::plural('person', count($roleMembers[$signer->role_id] ?? [])) }}</span>
                                        @else
                                            <span class="text-gray-800">{{ $person['name'] ?? 'Employee #' . $signer->employee_id }}</span>
                                            @if(!$person)
                                                <span class="block text-[10px] text-red-600">No longer an active employee</span>
                                            @elseif(!$holds)
                                                <span class="block text-[10px] text-red-600">No longer holds this role — cannot sign</span>
                                            @elseif(!$person['has_signature'])
                                                <span class="block text-[10px] text-amber-600"><i class="fas fa-triangle-exclamation text-[9px]"></i> No signature in the master data yet</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5" colspan="3">
                                        <form action="{{ route('general.letters.settings.signers.update', $signer) }}" method="POST" class="flex items-center gap-3">
                                            @csrf
                                            <input type="hidden" name="_signer" value="{{ $signer->id }}">
                                            <fieldset @disabled(!$canEdit) class="contents">
                                                <input type="text" name="title" maxlength="150" value="{{ $signer->title }}" aria-label="Position printed under the signature"
                                                    placeholder="{{ $signer->isWholeRole() ? 'Their own position' : (($person['position'] ?? '') ?: 'Their own position') }}" class="{{ $inputInline }} min-w-0 flex-1">
                                                @include('hr-general.recruitment.components.toggle-switch', ['toggleName' => 'is_active', 'toggleChecked' => $signer->is_active, 'toggleTitle' => 'Can be picked to sign'])
                                            </fieldset>
                                            <span class="flex items-center gap-1.5 ml-auto">
                                                @if($canEdit)
                                                    @include('hr-general.recruitment.components.icon-action', ['icon' => 'check', 'tone' => 'green', 'label' => 'Save', 'submit' => true])
                                                @endif
                                                @if($canDelete)
                                                    <button type="submit" form="deleteSigner{{ $signer->id }}" title="Remove" aria-label="Remove" class="{{ $deleteButton }}"><i class="fas fa-trash text-xs"></i></button>
                                                @endif
                                            </span>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-6 text-center text-xs text-gray-400">Nobody can sign letters yet — add a role below.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($canCreate)
                    {{-- Role first; the person list then holds that role's members. --}}
                    <form action="{{ route('general.letters.settings.signers.store') }}" method="POST" class="px-5 py-3 border-t border-gray-100 grid grid-cols-1 md:grid-cols-12 gap-3 items-end bg-gray-50/50 rounded-b-xl">
                        @csrf
                        <input type="hidden" name="_signer" value="new">
                        <div class="md:col-span-4">
                            <label for="signerRole" class="{{ $label }}">1. Role <span class="text-red-500">*</span></label>
                            <select name="role_id" id="signerRole" required data-searchable="true" data-search-placeholder="Search role…" class="{{ $input }}">
                                <option value="">-- Pick a role --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }} ({{ count($roleMembers[$role->id] ?? []) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-4">
                            <label for="signerEmployee" class="{{ $label }}">2. Who</label>
                            <select name="employee_id" id="signerEmployee" data-searchable="true" data-search-placeholder="Search name…" class="{{ $input }}">
                                <option value="">Everyone in this role</option>
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label for="signerTitle" class="{{ $label }}">Position printed</label>
                            <input type="text" name="title" id="signerTitle" maxlength="150" value="{{ old('_signer') === 'new' ? old('title') : '' }}" placeholder="Optional, e.g. Finance Manager" class="{{ $input }}">
                        </div>
                        <div class="md:col-span-1">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 whitespace-nowrap">
                                <i class="fas fa-plus text-xs"></i> Add
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="xl:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-800">Can Sign Now</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">What Create Letter offers: role, then the people of it. A role with nobody active in it is left out.</p>
                </div>
                <div class="px-5 py-3 space-y-3">
                    @forelse($signingRoles as $role)
                        <div>
                            <p class="text-xs font-bold text-gray-700">{{ $role['name'] }}</p>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                @foreach($role['people'] as $person)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $person['has_signature'] ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-600' }}"
                                        title="{{ $person['position'] ?: 'No position' }}{{ $person['has_signature'] ? '' : ' — no signature in the master data yet' }}">
                                        {{ $person['name'] }}@unless($person['has_signature']) <i class="fas fa-triangle-exclamation text-amber-500 text-[9px]"></i>@endunless
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-red-600">Nobody can sign letters — Create Letter cannot be used until a role with active members is added.</p>
                    @endforelse
                </div>
            </div>
        </div>
        @include('hr-general.letters.components.next-step', ['next' => 'letterheads', 'nextLabel' => '④ Letterheads — the page each letter is printed on'])
    </section>

    {{-- ── ④ Letterheads ────────────────────────────────────────────────── --}}
    <section data-panel="letterheads" class="{{ $section === 'letterheads' ? '' : 'hidden' }}">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Letterheads</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        One full A4 page (logo, contact details, footer) the letter text is printed on — upload an image, a PDF or a Word file.
                        A letter type uses one letterhead; without one it is printed on plain paper.
                    </p>
                </div>
                @if($canCreate)
                    {{-- Only opens the form — the form saves with its own button. --}}
                    <button type="button" onclick="openLetterheadModal()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 shadow-sm whitespace-nowrap">
                        <i class="fas fa-plus text-xs"></i> Add Letterhead
                    </button>
                @endif
            </div>

            <div class="p-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                @forelse($letterheads as $letterhead)
                    <div class="rounded-xl border border-gray-200 flex gap-3 p-3">
                        @if($letterhead->backgroundPath())
                            <a href="{{ route('general.letters.settings.image', $letterhead) }}?v={{ $letterhead->updated_at?->timestamp }}" target="_blank"
                                class="block w-20 shrink-0 border border-gray-200 rounded-md bg-gray-50 p-1" title="Open the full image">
                                <img src="{{ route('general.letters.settings.image', $letterhead) }}?v={{ $letterhead->updated_at?->timestamp }}"
                                    alt="Background of {{ $letterhead->name }}" class="w-full aspect-[210/297] object-fill bg-white">
                            </a>
                        @else
                            <p class="w-20 shrink-0 aspect-[210/297] flex items-center justify-center border border-dashed border-red-200 rounded-md text-center text-[9px] text-red-500 px-1">
                                Image missing
                            </p>
                        @endif
                        <div class="min-w-0 flex-1 flex flex-col">
                            <p class="text-sm font-bold text-gray-800 truncate" title="{{ $letterhead->name }}">{{ $letterhead->name }}</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @forelse($letterhead->letter_types ?? [] as $type)
                                    <span class="px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 text-[10px] font-semibold">{{ $letterTypes[$type] ?? $type }}</span>
                                @empty
                                    <span class="px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 text-[10px] font-semibold">Not used by any letter</span>
                                @endforelse
                            </div>
                            <div class="mt-auto pt-2 flex items-center justify-end gap-1.5">
                                @if($canEdit)
                                    @include('hr-general.recruitment.components.icon-action', [
                                        'icon' => 'pen', 'tone' => 'blue', 'label' => 'Edit letterhead',
                                        'onclick' => 'openLetterheadModal(JSON.parse(this.dataset.payload))',
                                        'data' => [
                                            'id' => $letterhead->id, 'name' => $letterhead->name, 'types' => $letterhead->letter_types ?? [],
                                            'action' => route('general.letters.settings.update', $letterhead),
                                            'image' => $letterhead->backgroundPath()
                                                ? route('general.letters.settings.image', $letterhead) . '?v=' . $letterhead->updated_at?->timestamp : null,
                                        ],
                                    ])
                                @endif
                                @if($canDelete)
                                    @include('hr-general.recruitment.components.icon-action', [
                                        'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete letterhead',
                                        'post' => route('general.letters.settings.destroy', $letterhead),
                                        'confirm' => 'Delete the letterhead "' . $letterhead->name . '"? Letters that use it are printed on plain paper afterwards.',
                                        'confirmTitle' => 'Delete Letterhead', 'confirmOk' => 'Delete',
                                    ])
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="sm:col-span-2 xl:col-span-3 py-6 text-center text-sm text-gray-400">
                        No letterhead yet. Letters are printed on plain paper until one is added and switched on for them.
                    </p>
                @endforelse
            </div>
        </div>
        @include('hr-general.letters.components.next-step', ['next' => 'requests', 'nextLabel' => '⑤ Employee Requests — what employees can ask for'])
    </section>

    {{-- ── ⑤ Employee requests ──────────────────────────────────────────── --}}
    <section data-panel="requests" class="{{ $section === 'requests' ? '' : 'hidden' }}">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Employee Request Options</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">
                        The letters employees can choose in <strong>My Letter Requests</strong>. Linked to a template, Requests → Process opens that template filled in;
                        without one, a custom letter titled with the name. A request keeps the name it was asked under.
                    </p>
                </div>
                <form action="{{ route('general.letters.settings.request-types.settings') }}" method="POST" class="shrink-0">
                    @csrf
                    <fieldset @disabled(!$canEdit) class="flex items-center gap-2">
                        @include('hr-general.recruitment.components.toggle-switch', [
                            'toggleName' => 'allow_other_requests', 'toggleChecked' => $settings->allow_other_requests,
                            'toggleText' => 'Allow "Other"', 'toggleTitle' => 'Employees can type a letter that is not on the list',
                        ])
                        @if($canEdit)
                            @include('hr-general.recruitment.components.icon-action', ['icon' => 'check', 'tone' => 'green', 'label' => 'Save', 'submit' => true])
                        @endif
                    </fieldset>
                </form>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($requestTypes as $requestType)
                    <form action="{{ route('general.letters.settings.request-types.update', $requestType) }}" method="POST" class="px-5 py-2.5 flex flex-wrap items-center gap-3">
                        @csrf
                        <fieldset @disabled(!$canEdit) class="contents">
                            <input type="text" name="name" required maxlength="150" value="{{ $requestType->name }}" aria-label="Letter name" class="{{ $input }} flex-1 min-w-56">
                            <select name="template_key" aria-label="Answered with" class="{{ $inputInline }} w-64">
                                <option value="">Answered with a custom letter</option>
                                @foreach($templateLabels as $key => $templateName)
                                    <option value="{{ $key }}" @selected($requestType->template_key === $key)>Template: {{ $templateName }}</option>
                                @endforeach
                            </select>
                            @include('hr-general.recruitment.components.toggle-switch', ['toggleName' => 'is_active', 'toggleChecked' => $requestType->is_active])
                        </fieldset>
                        <div class="flex items-center gap-1.5 ml-auto">
                            @if($canEdit)
                                @include('hr-general.recruitment.components.icon-action', ['icon' => 'check', 'tone' => 'green', 'label' => 'Save option', 'submit' => true])
                            @endif
                            @if($canDelete)
                                <button type="submit" form="deleteRequestType{{ $requestType->id }}" title="Delete option" aria-label="Delete option" class="{{ $deleteButton }}"><i class="fas fa-trash text-xs"></i></button>
                            @endif
                        </div>
                    </form>
                @empty
                    <p class="px-5 py-6 text-center text-xs text-gray-400">
                        No options yet{{ $settings->allow_other_requests ? ' — employees can only use "Other".' : ', and "Other" is off: employees cannot request a letter.' }}
                    </p>
                @endforelse
            </div>
            @if($canCreate)
                <form action="{{ route('general.letters.settings.request-types.store') }}" method="POST" class="px-5 py-3 border-t border-gray-100 flex flex-wrap items-center gap-3 bg-gray-50/50 rounded-b-xl">
                    @csrf
                    <input type="text" name="name" required maxlength="150" placeholder="e.g. Surat Keterangan Domisili" aria-label="New letter name" class="{{ $input }} flex-1 min-w-56">
                    <select name="template_key" aria-label="Answered with" class="{{ $inputInline }} w-64">
                        <option value="">Answered with a custom letter</option>
                        @foreach($templateLabels as $key => $templateName)
                            <option value="{{ $key }}">Template: {{ $templateName }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                        <i class="fas fa-plus text-xs"></i> Add Option
                    </button>
                </form>
            @endif
        </div>
    </section>
</div>

{{-- Delete forms live outside the rows: forms cannot be nested. --}}
@if($canDelete)
    @foreach($codes as $code)
        <form id="deleteCode{{ $code->id }}" action="{{ route('general.letters.settings.codes.destroy', $code) }}" method="POST" class="hidden"
            data-confirm="Delete the letter code {{ $code->code }}?" data-confirm-title="Delete Letter Code" data-confirm-ok="Delete">@csrf</form>
    @endforeach
    @foreach($signers as $signer)
        <form id="deleteSigner{{ $signer->id }}" action="{{ route('general.letters.settings.signers.destroy', $signer) }}" method="POST" class="hidden"
            data-confirm="Remove {{ $signer->employee_id ? 'this signer' : 'everyone in ' . ($signer->role?->name ?? 'this role') }} from who can sign? Letters already signed keep their signature."
            data-confirm-title="Remove Signer" data-confirm-ok="Remove">@csrf</form>
    @endforeach
    @foreach($requestTypes as $requestType)
        <form id="deleteRequestType{{ $requestType->id }}" action="{{ route('general.letters.settings.request-types.destroy', $requestType) }}" method="POST" class="hidden"
            data-confirm="Remove &quot;{{ $requestType->name }}&quot; from what employees can request? Requests already made keep their name."
            data-confirm-title="Delete Request Option" data-confirm-ok="Delete">@csrf</form>
    @endforeach
@endif

{{-- Add / edit a letterhead — "Add Letterhead" only opens it; "Save Letterhead" saves. --}}
@if($canCreate || $canEdit)
    <div id="letterheadModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 items-center justify-center p-4" style="display: none;">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[92vh] flex flex-col">
            <form id="letterheadForm" method="POST" enctype="multipart/form-data" class="flex flex-col min-h-0">
                @csrf
                <input type="hidden" name="_letterhead" id="letterheadKey" value="new">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-gray-800" id="letterheadTitle">Add Letterhead</h3>
                    <button type="button" onclick="closeLetterheadModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="px-5 py-4 space-y-4 overflow-y-auto">
                    <div>
                        <label for="letterheadName" class="{{ $label }}">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="letterheadName" required maxlength="100" placeholder="e.g. Eclectic Consulting 2026" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="letterheadImage" class="{{ $label }}">Letterhead File <span class="text-red-500" id="letterheadImageRequired">*</span></label>
                        {{-- An image is used as it is; a PDF or Word file is turned into an A4 image here, in the browser, before it is sent. --}}
                        <input type="file" name="background" id="letterheadImage" accept=".jpg,.jpeg,.png,.pdf,.docx,.doc" class="{{ $file }}">
                        <p class="{{ $help }}" id="letterheadImageHelp"></p>
                        <div class="mt-2 flex items-start gap-3">
                            <div class="w-24 shrink-0 aspect-[210/297] border border-gray-200 rounded-md bg-gray-50 p-1 flex items-center justify-center">
                                <img id="letterheadPreview" alt="Letterhead preview" class="hidden w-full h-full object-fill bg-white">
                                <i id="letterheadPreviewEmpty" class="fas fa-file-image text-gray-300 text-xl"></i>
                            </div>
                            <p id="letterheadStatus" class="text-[11px] text-gray-500 leading-relaxed" aria-live="polite">
                                Image (JPG / PNG), PDF or Word (.docx) — the first page is used, stretched to A4.
                                For a PDF or Word file, use one whose page is empty apart from the letterhead: anything in the body is printed on every letter.
                            </p>
                        </div>
                    </div>
                    <div>
                        <span class="{{ $label }}">Use this letterhead for</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5">
                            @foreach($letterTypes as $type => $typeLabel)
                                @include('hr-general.recruitment.components.toggle-switch', [
                                    'toggleName' => 'letter_types[]', 'toggleValue' => $type, 'toggleChecked' => false, 'toggleText' => $typeLabel,
                                ])
                            @endforeach
                        </div>
                        <p class="{{ $help }}">A letter is printed on one letterhead: switching it on here switches it off on the letterhead that had it.</p>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" onclick="closeLetterheadModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button type="submit" id="letterheadSubmit" class="inline-flex items-center gap-1.5 {{ $saveButton }}">
                        <i class="fas fa-floppy-disk text-[10px]"></i> <span>Save Letterhead</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@include('hr-general.recruitment.components.confirm-forms')
@endsection

@push('scripts')
<script>
    (function () {
        const byId = id => document.getElementById(id);

        // ── Steps: one at a time, kept in the address so saving comes back to the same step ──
        // Leaving a step with unsaved changes asks first (unsaved-guard); { force: true } skips that.
        window.showSettingsStep = async function (step, options = {}) {
            const current = document.querySelector('[data-panel]:not(.hidden)');
            if (!options.force && current && current.dataset.panel !== step && window.UnsavedGuard
                && !(await window.UnsavedGuard.confirmLeave(current, 'Go to another step'))) {
                return;
            }
            document.querySelectorAll('[data-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.panel !== step));
            document.querySelectorAll('.settings-step').forEach(button => {
                const active = button.dataset.step === step;
                button.classList.toggle('bg-indigo-50', active);
                button.classList.toggle('ring-1', active);
                button.classList.toggle('ring-indigo-200', active);
                const number = button.querySelector('.step-number');
                number.classList.toggle('primary-gradient', active);
                number.classList.toggle('text-white', active);
                number.classList.toggle('bg-gray-100', !active);
                number.classList.toggle('text-gray-500', !active);
            });
            const url = new URL(window.location.href);
            url.searchParams.set('section', step);
            history.replaceState(null, '', url);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        // ── Signers: role first, then "everyone" or one of the people holding it ──
        const roleMembers = {{ Js::from(collect($roleMembers)->map(fn ($ids) => array_values($ids))) }};
        const employeeNames = {{ Js::from($employees->mapWithKeys(fn ($p) => [$p['id'] => $p['name'] . ($p['position'] ? ' — ' . $p['position'] : '') . ($p['has_signature'] ? '' : ' (no signature yet)')])) }};
        const signerRole = byId('signerRole');
        const signerEmployee = byId('signerEmployee');
        function fillSignerPeople(selected) {
            if (!signerRole || !signerEmployee) return;
            const ids = roleMembers[signerRole.value] || [];
            signerEmployee.innerHTML = '';
            signerEmployee.add(new Option(signerRole.value ? 'Everyone in this role (' + ids.length + ')' : 'Pick a role first', ''));
            ids.map(id => [id, employeeNames[id]]).filter(([, name]) => name)
                .sort((a, b) => a[1].localeCompare(b[1]))
                .forEach(([id, name]) => signerEmployee.add(new Option(name, id, false, String(id) === String(selected))));
            signerEmployee.disabled = !signerRole.value;
        }
        signerRole?.addEventListener('change', () => fillSignerPeople(''));
        fillSignerPeople(@json((string) old('employee_id', '')));

        // ── Number formats: what each produces, and the example number of every letter type ──
        const now = new Date();
        const two = n => String(n).padStart(2, '0');
        const roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        const next = { outgoing: @json($nextSequence), incoming: @json($nextIncoming) };

        function build(kind, code, lang) {
            const format = document.querySelector(`[data-format="${kind}"]`).value;
            const digits = Number(document.querySelector(`[data-digits="${kind}"]`).value) || 1;
            const langCode = (document.querySelector(`[data-lang-code="${lang}"]`)?.value || '').toUpperCase();
            const tokens = {
                '{seq}': String(next[kind]).padStart(Math.min(Math.max(digits, 1), 6), '0'),
                '{code}': code, '{lang}': langCode,
                '{day}': two(now.getDate()), '{month}': two(now.getMonth() + 1), '{roman}': roman[now.getMonth()],
                '{year}': String(now.getFullYear()), '{yy}': String(now.getFullYear()).slice(-2),
            };
            return format.replace(/\{(seq|code|lang|day|month|roman|year|yy)\}/g, token => tokens[token]);
        }

        function refreshNumbers() {
            document.querySelector('[data-preview="outgoing"]').textContent = build('outgoing', 'OF', 'id');
            document.querySelector('[data-preview="incoming"]').textContent = build('incoming', '', 'id');
            document.querySelectorAll('[data-type-row]').forEach(row => {
                const target = row.querySelector('[data-type-preview]');
                if (!target) return;
                const select = row.querySelector('[data-type-code]');
                const code = select.options[select.selectedIndex]?.dataset.code || '';
                const lang = row.querySelector('input[type="radio"]:checked')?.value || 'id';
                target.textContent = build('outgoing', code, lang);
            });
        }

        document.addEventListener('input', event => {
            if (event.target.matches('[data-format], [data-digits], [data-lang-code]')) refreshNumbers();
        });
        document.addEventListener('change', event => {
            if (event.target.closest('[data-type-row]')) refreshNumbers();
        });
        document.addEventListener('DOMContentLoaded', refreshNumbers);

        // ── Letterhead form: add a new one, or change one ──
        const modal = byId('letterheadModal');
        if (!modal) return;
        const storeUrl = @json(route('general.letters.settings.store'));

        window.openLetterheadModal = function (letterhead, keep) {
            letterhead = letterhead || {};
            const editing = !!letterhead.id;
            byId('letterheadForm').action = editing ? letterhead.action : storeUrl;
            byId('letterheadKey').value = editing ? letterhead.id : 'new';
            byId('letterheadTitle').textContent = editing ? 'Edit Letterhead' : 'Add Letterhead';
            byId('letterheadSubmit').querySelector('span').textContent = editing ? 'Save Changes' : 'Save Letterhead';
            byId('letterheadName').value = letterhead.name || '';
            byId('letterheadImage').value = '';
            // A new letterhead needs its file; an existing one only to replace it.
            byId('letterheadImage').required = !editing;
            byId('letterheadImageRequired').classList.toggle('hidden', editing);
            byId('letterheadImageHelp').textContent = editing ? 'Choose a file only to replace the current letterhead.' : '';
            showPreview(editing ? letterhead.image : null);
            setStatus(defaultStatus);
            setBusy(false);
            const types = letterhead.types || [];
            modal.querySelectorAll('input[name="letter_types[]"]').forEach(box => { box.checked = types.includes(box.value); });
            modal.classList.remove('hidden');
            modal.style.display = 'flex';
        };

        window.closeLetterheadModal = function () {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        };

        // ── A PDF or Word letterhead becomes an A4 image in the browser ──
        // DomPDF prints a letter on an image only, and the server has no PDF / Word converter, so the
        // first page is drawn here — PDF.js for a PDF, docx-preview + html2canvas for Word — and the
        // file input is given that JPG instead. The server checks it like any uploaded image.
        const A4 = { width: 2480, height: 3508 }; // 300 dpi
        const fileInput = byId('letterheadImage');
        const submitButton = byId('letterheadSubmit');
        const statusLine = byId('letterheadStatus');
        const defaultStatus = statusLine.innerHTML;
        const libraries = {};

        function setStatus(html, tone) {
            statusLine.innerHTML = html;
            statusLine.className = 'text-[11px] leading-relaxed ' + ({ error: 'text-red-600', ok: 'text-green-700', busy: 'text-indigo-700' }[tone] || 'text-gray-500');
        }

        function setBusy(busy) {
            submitButton.disabled = busy;
            submitButton.classList.toggle('opacity-50', busy);
        }

        function showPreview(src) {
            byId('letterheadPreview').classList.toggle('hidden', !src);
            byId('letterheadPreviewEmpty').classList.toggle('hidden', !!src);
            if (src) byId('letterheadPreview').src = src;
        }

        function loadScript(url) {
            return libraries[url] ??= new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = url;
                script.onload = resolve;
                script.onerror = () => reject(new Error('Could not load ' + url));
                document.head.appendChild(script);
            });
        }

        // Any page drawn on a canvas, stretched onto a white A4 page, as a JPG file.
        function toA4Jpeg(source, name) {
            const canvas = document.createElement('canvas');
            canvas.width = A4.width;
            canvas.height = A4.height;
            const context = canvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, A4.width, A4.height);
            context.drawImage(source, 0, 0, A4.width, A4.height);
            return new Promise((resolve, reject) => canvas.toBlob(blob => blob
                ? resolve(new File([blob], name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }))
                : reject(new Error('The page could not be turned into an image.')), 'image/jpeg', 0.92));
        }

        async function pdfToImage(file) {
            await loadScript('https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js');
            window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
            const pdf = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;
            const page = await pdf.getPage(1);
            const base = page.getViewport({ scale: 1 });
            const viewport = page.getViewport({ scale: A4.width / base.width });
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(viewport.width);
            canvas.height = Math.round(viewport.height);
            const context = canvas.getContext('2d');
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            await page.render({ canvasContext: context, viewport }).promise;
            return { image: await toA4Jpeg(canvas, file.name), pages: pdf.numPages };
        }

        async function docxToImage(file) {
            await loadScript('https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js');
            await loadScript('https://cdn.jsdelivr.net/npm/docx-preview@0.3.5/dist/docx-preview.min.js');
            await loadScript('https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js');

            // Rendered off screen at its own page size, headers and footers included.
            const host = document.createElement('div');
            host.style.cssText = 'position:fixed;left:-10000px;top:0;background:#fff;';
            document.body.appendChild(host);
            try {
                await window.docx.renderAsync(await file.arrayBuffer(), host, null, {
                    inWrapper: false, renderHeaders: true, renderFooters: true, breakPages: true,
                    ignoreLastRenderedPageBreak: true, experimental: true, useBase64URL: true,
                });
                const page = host.querySelector('section.docx') || host.firstElementChild;
                if (!page) throw new Error('The Word file has no page to read.');
                const pages = host.querySelectorAll('section.docx').length || 1;
                const canvas = await window.html2canvas(page, {
                    backgroundColor: '#ffffff', scale: A4.width / page.offsetWidth, useCORS: true, logging: false,
                });
                return { image: await toA4Jpeg(canvas, file.name), pages };
            } finally {
                host.remove();
            }
        }

        fileInput.addEventListener('change', async function () {
            const file = this.files[0];
            if (!file) {
                setStatus(defaultStatus);
                return;
            }
            const extension = file.name.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png'].includes(extension)) {
                showPreview(URL.createObjectURL(file));
                setStatus('This image is used as it is, stretched to A4.', 'ok');
                return;
            }
            if (extension === 'doc') {
                this.value = '';
                showPreview(null);
                setStatus('An old Word file (.doc) cannot be read here. Open it in Word and save it as .docx or PDF, then pick that file.', 'error');
                return;
            }
            if (!['pdf', 'docx'].includes(extension)) {
                this.value = '';
                setStatus('Pick an image (JPG / PNG), a PDF or a Word (.docx) file.', 'error');
                return;
            }

            setBusy(true);
            setStatus('<i class="fas fa-spinner fa-spin mr-1"></i> Turning the first page of ' + (extension === 'pdf' ? 'the PDF' : 'the Word file') + ' into an image…', 'busy');
            try {
                const { image, pages } = extension === 'pdf' ? await pdfToImage(file) : await docxToImage(file);
                // The input now carries the JPG, so the form sends the image instead of the PDF / Word file.
                const transfer = new DataTransfer();
                transfer.items.add(image);
                this.files = transfer.files;
                showPreview(URL.createObjectURL(image));
                setStatus('Turned into an A4 image (' + Math.round(image.size / 1024) + ' KB).'
                    + (pages > 1 ? ' The file has ' + pages + ' pages — only the first is used.' : '')
                    + ' Check the preview, then save.', 'ok');
            } catch (error) {
                console.error(error);
                this.value = '';
                showPreview(null);
                setStatus('This file could not be turned into an image (' + (error.message || error) + '). Try saving it as PDF, or export the page as a JPG / PNG.', 'error');
            } finally {
                setBusy(false);
            }
        });

        @if(old('_letterhead') !== null)
            // Reopened after a failed save, with what was typed.
            document.addEventListener('DOMContentLoaded', () => {
                const key = @json(old('_letterhead'));
                const existing = {{ Js::from($letterheads->mapWithKeys(fn ($l) => [$l->id => ['action' => route('general.letters.settings.update', $l)]])) }};
                openLetterheadModal({
                    id: key === 'new' ? null : key, action: existing[key]?.action,
                    name: @json(old('name')), types: @json(old('letter_types', [])),
                });
            });
        @endif
    })();
</script>
@endpush
