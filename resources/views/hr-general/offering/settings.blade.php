@extends('dashboard')
@section('title', 'Offering Letter - Settings')
@section('page-title', 'Offering Settings')
@section('page-subtitle', 'The letter number, the compensation components and the base salary rule of offering letters.')

@php
    // Capabilities of this tab, as ticked in Management → Roles.
    $canEdit   = $canDo('general.recruitment.offers.settings', 'edit');
    $canCreate = $canDo('general.recruitment.offers.settings', 'create');
    $canDelete = $canDo('general.recruitment.offers.settings', 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-gray-50 disabled:text-gray-500';
    $rowInput = 'w-full border border-gray-200 rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $help = 'text-[11px] text-gray-400 mt-1';
    $action = 'hr-general.recruitment.components.icon-action';
    $setting = fn (string $key) => old($key, $settings->{$key});
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.offering.components.hub-tabs')
    @include('hr-general.recruitment.components.form-errors')

    <form action="{{ route('general.recruitment.offers.settings.update') }}" method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm">
        @csrf
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Letter</h3>
            <p class="text-[11px] text-gray-400 mt-0.5">What a new offering letter starts with. A letter that is already saved keeps its own number and text.</p>
        </div>

        <fieldset @disabled(!$canEdit) class="px-5 py-4 space-y-5">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2">
                    <label for="offer_number_format" class="{{ $label }}">Letter Number Format <span class="text-red-500">*</span></label>
                    <input type="text" name="offer_number_format" id="offer_number_format" required maxlength="100"
                        value="{{ $setting('offer_number_format') }}" class="{{ $input }} font-mono">
                    <p class="{{ $help }}">
                        <code>{seq}</code> running number, restarts every year and continues across languages ·
                        <code>{lang}</code> the letter's language: IN (Indonesian) or EN (English) ·
                        <code>{day}</code> <code>{month}</code> <code>{year}</code> <code>{yy}</code> of the offer date ·
                        <code>{roman}</code> month in Roman numerals. Everything else is printed as typed.
                    </p>
                    <p class="text-[11px] text-gray-600 mt-1">Next letter dated today: <strong id="numberPreview" class="font-mono"></strong></p>
                    {{-- What is shared with the letters of Letter Templates, and what is not. --}}
                    <div class="mt-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-[11px] text-blue-900 leading-relaxed">
                        <p class="font-semibold"><i class="fas fa-circle-info mr-1"></i> How this relates to Letter Templates → Settings</p>
                        <ul class="mt-1 list-disc list-inside space-y-0.5">
                            <li><strong>The format is its own</strong> — this one. Changing the number format in Letter Templates does not change offering letter numbers.</li>
                            <li><strong>The running number <code>{seq}</code> is shared</strong> with every other outgoing letter, so one register has no two letters with the same number.
                                After an employment certificate takes 005, the next offering letter gets 006.</li>
                            <li><strong>What <code>{lang}</code> prints</strong> (IN / EN) is set once in Letter Templates → Settings, for all letters.</li>
                        </ul>
                    </div>
                </div>
                <div>
                    <label for="offer_number_digits" class="{{ $label }}">Digits of the Running Number <span class="text-red-500">*</span></label>
                    <input type="number" name="offer_number_digits" id="offer_number_digits" required min="1" max="6"
                        value="{{ $setting('offer_number_digits') }}" class="{{ $input }}">
                    <p class="{{ $help }}">3 prints the first letter of the year as 001.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 border-t border-gray-100 pt-4">
                <div>
                    <label for="offer_signing_city" class="{{ $label }}">Signing City <span class="text-red-500">*</span></label>
                    <input type="text" name="offer_signing_city" id="offer_signing_city" required maxlength="100"
                        value="{{ $setting('offer_signing_city') }}" class="{{ $input }}">
                    <p class="{{ $help }}">Printed before the date above the signatures.</p>
                </div>
                <div>
                    <label for="offer_response_days" class="{{ $label }}">Days to Respond <span class="text-red-500">*</span></label>
                    <input type="number" name="offer_response_days" id="offer_response_days" required min="1" max="60"
                        value="{{ $setting('offer_response_days') }}" class="{{ $input }}">
                    <p class="{{ $help }}">How long the candidate has to sign and return the letter.</p>
                </div>
                <div>
                    <label for="offer_base_salary_min_percent" class="{{ $label }}">Minimum Base Salary Percentage <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" name="offer_base_salary_min_percent" id="offer_base_salary_min_percent" required min="0" max="100" step="0.01"
                            value="{{ $setting('offer_base_salary_min_percent') }}" class="{{ $input }} pr-8">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span>
                    </div>
                    <p class="{{ $help }}">Base salary as a share of base salary + fixed allowances. A letter below it is flagged while it is written, not blocked.</p>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4">
                <span id="ratioNoteLabel" class="{{ $label }}">Base Salary Percentage Note <span class="text-red-500">*</span></span>
                <div class="border border-gray-200 rounded-lg focus-within:ring-2 focus-within:ring-indigo-200 {{ $canEdit ? 'bg-white' : 'bg-gray-50' }}">
                    @if($canEdit)
                        <div class="flex flex-wrap items-center gap-1 px-2 py-1.5 border-b border-gray-100 bg-gray-50 rounded-t-lg" role="toolbar" aria-label="Formatting">
                            @foreach(['bold' => 'Bold (Ctrl+B)', 'italic' => 'Italic (Ctrl+I)', 'underline' => 'Underline (Ctrl+U)'] as $command => $tip)
                                <button type="button" data-command="{{ $command }}" title="{{ $tip }}" aria-label="{{ $tip }}" aria-pressed="false"
                                    class="w-7 h-7 inline-flex items-center justify-center rounded-md text-gray-600 hover:bg-gray-200 aria-pressed:bg-indigo-100 aria-pressed:text-indigo-700">
                                    <i class="fas fa-{{ $command }} text-xs"></i>
                                </button>
                            @endforeach
                            <span class="w-px h-5 bg-gray-200 mx-1"></span>
                            <span class="text-[11px] text-gray-500 mr-0.5">Insert:</span>
                            @foreach($ratioPlaceholders as $placeholder => $meaning)
                                <button type="button" data-insert="{{ $placeholder }}" title="{{ $meaning }}"
                                    class="px-2 py-0.5 rounded-md border border-gray-200 bg-white text-[11px] font-mono text-gray-600 hover:bg-gray-100">{{ $placeholder }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div id="ratioNoteEditor" role="textbox" aria-multiline="true" aria-labelledby="ratioNoteLabel" @if($canEdit) contenteditable="true" @endif
                        class="min-h-[5.5rem] px-3 py-2 text-sm text-gray-700 leading-relaxed focus:outline-none">{!! $ratioNote !!}</div>
                </div>
                <input type="hidden" name="offer_ratio_note" id="offer_ratio_note">
                <p class="{{ $help }}">
                    Shown under the base salary percentage in the offering letter form. The placeholders follow this page by themselves:
                    @foreach($ratioPlaceholders as $placeholder => $meaning)
                        <code>{{ $placeholder }}</code> {{ strtolower($meaning) }}{{ $loop->last ? '.' : ',' }}
                    @endforeach
                </p>
                <div class="mt-2 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2 text-[11px] text-gray-600">
                    <span class="font-semibold text-gray-700">As it shows now, with the saved settings and components:</span>
                    <div class="mt-0.5 leading-relaxed">{!! $ratioPreview !!}</div>
                </div>
            </div>
        </fieldset>

        @if($canEdit)
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save</button>
            </div>
        @endif
    </form>

    <!-- Compensation components -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-800">Compensation Components</h3>
            <p class="text-[11px] text-gray-400 mt-0.5">
                The amounts a letter can carry, under the name printed on it — an English letter prints the English name, or the name itself when that is empty.
                A fixed allowance counts in the base salary percentage; a variable one does not.
                <strong>Default</strong> is what a new letter starts with — a rupiah amount, or a percentage of the letter's base salary — so HR does not retype the same figure every time (for example the base salary at the UMK, or a BPJS allowance as a share of it). It can still be changed on each letter.
                Every change is saved as you make it. An inactive component disappears from new letters but stays on the letters that already carry it.
            </p>
        </div>

        @if($canEdit)
            <div class="hidden sm:grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_11rem_15rem_7.5rem] gap-x-3 py-2 bg-gray-50 border-b border-gray-100 text-[10px] font-bold uppercase tracking-wider text-gray-500"
                style="padding-left: 1.25rem; padding-right: {{ $canDelete ? '3.75rem' : '1.25rem' }};">
                <span>Name</span><span>English name</span><span>Counts as</span><span>Default</span><span>Offered</span>
            </div>
        @endif

        <ul class="divide-y divide-gray-100">
            @foreach($components as $component)
                <li class="px-5 py-2.5 flex items-center gap-2">
                    @if($canEdit)
                        <form action="{{ route('general.recruitment.offers.settings.components.update', $component) }}" method="POST" data-autosave
                            class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_11rem_15rem_7.5rem] sm:items-center gap-x-3 gap-y-2">
                            @csrf
                            <input type="text" name="name" value="{{ $component->name }}" required maxlength="100" aria-label="Component name"
                                class="{{ $rowInput }} {{ $component->is_active ? '' : 'text-gray-400' }}">
                            <input type="text" name="name_en" value="{{ $component->name_en }}" maxlength="100" placeholder="English name" aria-label="{{ $component->name }} in English"
                                class="{{ $rowInput }}">

                            @if($component->isBase())
                                <span class="text-[11px] font-semibold text-gray-500"><i class="fas fa-lock text-[9px] mr-1"></i> Base salary</span>
                                <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-1.5 items-center">
                                    <span class="text-[11px] font-semibold text-gray-500 px-1">Rp (e.g. UMK)</span>
                                    <input type="hidden" name="default_type" value="amount">
                                    <input type="number" name="default_value" value="{{ $component->default_value !== null ? (float) $component->default_value : '' }}" min="0" step="any"
                                        placeholder="No default" aria-label="{{ $component->name }} default value" class="{{ $rowInput }} text-right">
                                </div>
                                <span class="w-4 text-center text-xs" data-save-state aria-live="polite"></span>
                            @else
                                <div>
                                    <select name="kind" aria-label="{{ $component->name }} counts as">
                                        @foreach($kinds as $value => $kindLabel)
                                            <option value="{{ $value }}" @selected($component->kind === $value)>{{ $kindLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-1.5 items-center">
                                    <div>
                                        <select name="default_type" aria-label="{{ $component->name }} default is">
                                            @foreach(\App\Models\Recruitment\OfferComponent::DEFAULT_TYPES as $value => $typeLabel)
                                                <option value="{{ $value }}" @selected($component->default_type === $value)>{{ $typeLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="number" name="default_value" value="{{ $component->default_value !== null ? (float) $component->default_value : '' }}" min="0" step="any"
                                        placeholder="No default" aria-label="{{ $component->name }} default value" class="{{ $rowInput }} text-right">
                                </div>
                                <div class="flex items-center gap-2">
                                    @include('hr-general.recruitment.components.toggle-switch', [
                                        'toggleName' => 'is_active', 'toggleChecked' => $component->is_active, 'toggleTitle' => 'Offered on new letters',
                                    ])
                                    <span class="w-4 text-center text-xs" data-save-state aria-live="polite"></span>
                                </div>
                            @endif
                        </form>
                    @else
                        <span class="flex-1 min-w-0 text-xs {{ $component->is_active ? 'text-gray-800' : 'text-gray-400' }}">
                            {{ $component->name }}@if($component->name_en) <span class="text-gray-400">/ {{ $component->name_en }}</span>@endif
                            <span class="block text-[11px] text-gray-500">{{ $component->isBase() ? 'Base salary' : $kinds[$component->kind] }}{{ $component->is_active ? '' : ' · Inactive' }}</span>
                        </span>
                    @endif

                    @if($canDelete)
                        @if($component->isBase())
                            <span class="w-8 shrink-0"></span>
                        @else
                            @include($action, [
                                'icon' => 'trash', 'tone' => 'red', 'label' => 'Delete ' . $component->name,
                                'post' => route('general.recruitment.offers.settings.components.destroy', $component),
                                'confirm' => 'Delete "' . $component->name . '"?', 'confirmTitle' => 'Delete Component', 'confirmOk' => 'Delete',
                            ])
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>

        @if($canCreate)
            <form action="{{ route('general.recruitment.offers.settings.components.store') }}" method="POST"
                class="px-5 py-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center gap-2">
                @csrf
                <input type="text" name="name" required maxlength="100" placeholder="New component, e.g. Tunjangan Komunikasi" aria-label="New component name"
                    class="{{ $rowInput }} flex-1 min-w-0">
                <input type="text" name="name_en" maxlength="100" placeholder="English name, e.g. Communication Allowance" aria-label="New component name in English"
                    class="{{ $rowInput }} flex-1 min-w-0">
                <div class="sm:w-44">
                    <select name="kind" aria-label="New component counts as">
                        @foreach($kinds as $value => $kindLabel)
                            <option value="{{ $value }}">{{ $kindLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-1.5 items-center sm:w-60">
                    <div>
                        <select name="default_type" aria-label="New component default is">
                            @foreach(\App\Models\Recruitment\OfferComponent::DEFAULT_TYPES as $value => $typeLabel)
                                <option value="{{ $value }}">{{ $typeLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="number" name="default_value" min="0" step="any" placeholder="Default (optional)" aria-label="New component default value"
                        class="{{ $rowInput }} text-right">
                </div>
                @include($action, ['icon' => 'plus', 'tone' => 'blue', 'label' => 'Add component', 'submit' => true])
            </form>
        @endif
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-xl px-5 py-4 text-xs text-gray-600">
        <p class="font-semibold text-gray-700 mb-1"><i class="fas fa-circle-info mr-1"></i> Letter layout and letterhead</p>
        <p>
            The wording and layout of the PDF are fixed in the system. It is printed on
            @if($letterhead)
                the letterhead <strong>{{ $letterhead->name }}</strong>
            @else
                plain paper — no letterhead is ticked for Offering Letter yet
            @endif
            @if($can('general.letter-templates'))
                (<a href="{{ route('general.letters.settings.index') }}" class="font-semibold" style="color: var(--primary-color);">Letter Templates</a>).
            @else
                (set in HR &amp; General → Letter Templates).
            @endif
        </p>
    </div>
</div>

@include('hr-general.recruitment.components.confirm-forms')
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ── Letter number: show what the format produces while it is typed ──
        const format = document.getElementById('offer_number_format');
        const digits = document.getElementById('offer_number_digits');
        const now = new Date();
        const two = n => String(n).padStart(2, '0');
        const roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        function previewNumber() {
            const tokens = {
                '{seq}': String(@json($nextSequence)).padStart(Math.min(Math.max(Number(digits.value) || 1, 1), 6), '0'),
                '{day}': two(now.getDate()), '{month}': two(now.getMonth() + 1), '{roman}': roman[now.getMonth()],
                '{year}': String(now.getFullYear()), '{yy}': String(now.getFullYear()).slice(-2),
                '{lang}': @json(\App\Models\LetterTypeSetting::numberCode(\App\Models\LetterTypeSetting::languageFor(\App\Models\Letterhead::TYPE_OFFERING_LETTER))),
            };
            document.getElementById('numberPreview').textContent =
                format.value.replace(/\{(seq|lang|day|month|roman|year|yy)\}/g, token => tokens[token]);
        }

        format.addEventListener('input', previewNumber);
        digits.addEventListener('input', previewNumber);
        previewNumber();

        // ── Note editor: bold / italic / underline and placeholders, sent as HTML ──
        const editor = document.getElementById('ratioNoteEditor');
        const noteField = document.getElementById('offer_ratio_note');
        const commands = document.querySelectorAll('[data-command]');

        noteField.value = editor.innerHTML;
        editor.closest('form').addEventListener('submit', () => { noteField.value = editor.innerHTML; });

        if (editor.isContentEditable) {
            const syncToolbar = () => {
                if (!editor.contains(document.getSelection()?.anchorNode)) return;
                commands.forEach(button => button.setAttribute('aria-pressed', document.queryCommandState(button.dataset.command)));
            };

            // A toolbar click must not take the focus (and the selection) away from the text.
            document.querySelectorAll('[data-command], [data-insert]').forEach(button => {
                button.addEventListener('mousedown', event => event.preventDefault());
            });
            commands.forEach(button => button.addEventListener('click', () => {
                editor.focus();
                document.execCommand(button.dataset.command);
                syncToolbar();
            }));
            document.querySelectorAll('[data-insert]').forEach(button => button.addEventListener('click', () => {
                editor.focus();
                document.execCommand('insertText', false, button.dataset.insert);
            }));

            // Pasted text arrives without its outside formatting.
            editor.addEventListener('paste', event => {
                event.preventDefault();
                document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
            });

            document.addEventListener('selectionchange', syncToolbar);
        }

        // ── Components: a row saves itself the moment it changes ──
        document.querySelectorAll('form[data-autosave]').forEach(form => {
            const state = form.querySelector('[data-save-state]');
            const name = form.querySelector('input[name="name"]');
            const active = form.querySelector('input[name="is_active"]');
            let savedName = name.value;

            const mark = (html, title) => { state.innerHTML = html; state.title = title || ''; };

            async function save() {
                if (!name.value.trim()) { name.value = savedName; return; }
                mark('<i class="fas fa-circle-notch fa-spin text-gray-400"></i>', 'Saving…');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST', body: new FormData(form), credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const body = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'The change could not be saved.');
                    }

                    savedName = name.value;
                    if (active) name.classList.toggle('text-gray-400', !active.checked);
                    mark('<i class="fas fa-check text-green-600"></i>', 'Saved');
                    window.UnsavedGuard?.markSaved(form); // saved in the background: nothing left unsaved
                    setTimeout(() => { if (state.title === 'Saved') mark('', ''); }, 2500);
                } catch (error) {
                    name.value = savedName;
                    mark('<i class="fas fa-triangle-exclamation text-red-500"></i>', error.message);
                    showToast(error.message, 'error', 6000);
                }
            }

            form.addEventListener('change', save);
            form.addEventListener('submit', event => { event.preventDefault(); name.blur(); });
        });
    });
</script>
@endpush
