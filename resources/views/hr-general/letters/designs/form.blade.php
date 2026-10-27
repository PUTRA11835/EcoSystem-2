@extends('dashboard')
@section('title', $def->exists ? 'Edit Letter Template' : 'New Letter Template')
@section('page-title', 'Letter Templates')
@section('page-subtitle', 'Write the letter as in Word, in Bahasa Indonesia and English, and list the fields HR fills in when creating it.')

@php
    $isEdit = $def->exists;
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $cell = 'w-full border border-gray-200 rounded-md px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $employeeMode = old('employee', $def->employee ?: 'optional');
    $fieldMap = collect($rows)->filter(fn ($r) => trim($r['key']) !== '')
        ->mapWithKeys(fn ($r) => [strtolower(trim($r['key'])) => ['label' => $r['label'], 'type' => $r['type']]])->all();
    $placeholders = ['employee' => \App\Support\Letters\LetterBody::placeholders($fieldMap)];
    $startRows = $rows ?: [];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.letters.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    <form method="POST" id="designForm" autocomplete="off"
        action="{{ $isEdit ? route('general.letters.settings.designs.update', $def->id) : route('general.letters.settings.designs.store') }}" class="space-y-6">
        @csrf

        {{-- 1. What the template is --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-6">
                <label class="{{ $label }}" for="dLabel">Name (Bahasa Indonesia)</label>
                <input id="dLabel" name="label" value="{{ old('label', $def->label) }}" required maxlength="150" class="{{ $input }}" placeholder="e.g. Surat Keterangan Kerja">
            </div>
            <div class="md:col-span-6">
                <label class="{{ $label }}" for="dLabelEn">Name (English)</label>
                <input id="dLabelEn" name="label_en" value="{{ old('label_en', $def->label_en) }}" required maxlength="150" class="{{ $input }}" placeholder="e.g. Employment Certificate">
            </div>
            <div class="md:col-span-8">
                <label class="{{ $label }}" for="dDesc">What it is for</label>
                <input id="dDesc" name="description" value="{{ old('description', $def->description) }}" maxlength="1000" class="{{ $input }}"
                    placeholder="Shown next to the template on Create Letter">
            </div>
            <div class="md:col-span-4">
                <label class="{{ $label }}" for="dEmployee">Employee</label>
                <select id="dEmployee" name="employee" class="{{ $input }}">
                    <option value="required" @selected($employeeMode === 'required')>Required — the letter is about an employee</option>
                    <option value="optional" @selected($employeeMode === 'optional')>Optional</option>
                    <option value="none" @selected($employeeMode === 'none')>Not used</option>
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Fills <code>@{{employee_name}}</code>, <code>@{{position}}</code>… from the master data.</p>
            </div>
            <div class="md:col-span-6">
                <label class="{{ $label }}" for="dClosingId">Closing line (Bahasa Indonesia)</label>
                <input id="dClosingId" name="closing_id" value="{{ old('closing_id', $def->closing_id) }}" maxlength="100" class="{{ $input }}" placeholder="Hormat kami,">
            </div>
            <div class="md:col-span-6">
                <label class="{{ $label }}" for="dClosingEn">Closing line (English)</label>
                <input id="dClosingEn" name="closing_en" value="{{ old('closing_en', $def->closing_en) }}" maxlength="100" class="{{ $input }}" placeholder="Sincerely,">
                <p class="text-[10px] text-gray-400 mt-1">Printed above the signature in <code>@{{signature_block}}</code>; empty = the usual one.</p>
            </div>
        </div>

        {{-- 2. The fields HR fills in --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Fields</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">What HR types when creating a letter from this template. Each field becomes a <strong>Field</strong> in the text below, named by its key — e.g. key <code>purpose</code> is written <code>@{{purpose}}</code>.</p>
                </div>
                <button type="button" id="addFieldRow" class="px-3 py-2 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 text-xs font-semibold rounded-lg shadow-sm whitespace-nowrap">
                    <i class="fas fa-plus text-[10px]"></i> Add a field
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="py-2 pr-2 min-w-32">Key</th>
                            <th class="py-2 pr-2 min-w-40">Label HR sees</th>
                            <th class="py-2 pr-2 min-w-32">Type</th>
                            <th class="py-2 pr-2 text-center">Required</th>
                            <th class="py-2 pr-2 min-w-40">Hint</th>
                            <th class="py-2 pr-2 min-w-32" title="Printed when the field is left empty">If empty, print (ID)</th>
                            <th class="py-2 pr-2 min-w-32">If empty, print (EN)</th>
                            <th class="py-2 w-8"></th>
                        </tr>
                    </thead>
                    <tbody id="fieldRows">
                        @foreach($startRows as $i => $row)
                            @include('hr-general.letters.designs.field-row', ['i' => $i, 'row' => $row, 'cell' => $cell, 'fieldTypes' => $fieldTypes])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p id="noFields" class="text-[11px] text-gray-400 py-3 {{ $startRows ? 'hidden' : '' }}">No fields — the letter is made of the employee data and the text alone.</p>
            <template id="fieldRowTemplate">
                @include('hr-general.letters.designs.field-row', ['i' => '__I__', 'row' => ['key' => '', 'label' => '', 'type' => 'text', 'required' => false, 'help' => '', 'empty_id' => '', 'empty_en' => ''], 'cell' => $cell, 'fieldTypes' => $fieldTypes])
            </template>
        </div>

        {{-- 3. The wording --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Letter Text</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        Format it as in Word — fonts, tables, indents on the ruler, page breaks. <strong>Fields</strong> inserts what is filled in for each letter;
                        <code>@{{signature_block}}</code> prints the city, date, signature and signer. <strong>Preview</strong> shows the PDF with sample data. Press <kbd class="px-1 border rounded bg-gray-50">F1</kbd> for shortcuts.
                    </p>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-0.5 self-start" role="tablist" id="langTabs">
                    <button type="button" data-lang="id" class="px-3 py-1.5 rounded-md text-xs font-semibold bg-white text-indigo-700 shadow-sm">Bahasa Indonesia</button>
                    <button type="button" data-lang="en" class="px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 hover:text-gray-800">English</button>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mb-3">
                A letter is printed in the language HR picks on Create Letter; if that language has no text, the other one is used.
                The letterhead comes from Document Identity
                @if($isEdit) (letter type “{{ $def->label_en }}”)@else (set once the template is saved)@endif.
            </p>

            @include('hr-general.contracts.components.word-editor', [
                'weHtml'         => $bodyId,
                'weInput'        => 'bodyCurrent',
                'weForm'         => 'designForm',
                'weFields'       => $placeholders,
                'weTypeSelect'   => 'noTypeSelect',
                'weLhToggle'     => '',
                'weLetterhead'   => $letterheadUri,
                'wePreviewUrl'   => route('general.letters.settings.designs.preview'),
                'wePreviewExtra' => '.design-extra',
                'weAllowEmpty'   => true,
                'wePage'         => $page,
                'wePageDefaults' => $pageDefaults,
            ])
            <input type="hidden" id="bodyCurrent">
            <input type="hidden" name="body_id" id="bodyId">
            <input type="hidden" name="body_en" id="bodyEn">
            <input type="hidden" class="design-extra" name="language" id="extraLanguage" value="id">
            <input type="hidden" class="design-extra" name="fields_json" id="extraFields" value="[]">
            <input type="hidden" class="design-extra" name="closing" id="extraClosing" value="">
            <input type="hidden" class="design-extra" name="template_key" value="{{ $def->template_key }}">
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('general.letters.settings.index', ['section' => 'designs']) }}" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 bg-white">Cancel</a>
            @if($isEdit && $def->is_system)
                <button type="submit" form="resetForm" onclick="return confirm('Put the default wording and fields back? Your changes to this template are lost; letters already made are not affected.')"
                    class="px-4 py-2 text-xs font-semibold text-amber-700 border border-amber-200 rounded-lg hover:bg-amber-50 bg-white">
                    <i class="fas fa-rotate-left text-[10px]"></i> Reset to default
                </button>
            @endif
            <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">{{ $isEdit ? 'Save Template' : 'Create Template' }}</button>
        </div>
    </form>

    @if($isEdit && $def->is_system)
        <form id="resetForm" method="POST" action="{{ route('general.letters.settings.designs.reset', $def->id) }}" class="hidden">@csrf</form>
    @endif
</div>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('designForm');
    const rowsBody = document.getElementById('fieldRows');
    const template = document.getElementById('fieldRowTemplate');
    const root = document.querySelector('[data-word-editor]');
    const base = @json(\App\Support\Letters\LetterBody::BASE);
    const bodies = { id: @json($bodyId), en: @json($bodyEn) };
    let lang = 'id';
    let next = {{ count($startRows) + 100 }};

    const editor = () => root && root._we;

    // ── fields: the rows, the editor's Fields menu, the preview's field list ──────────
    function rows() {
        return Array.from(rowsBody.querySelectorAll('[data-field-row]')).map(tr => {
            const v = n => (tr.querySelector('[data-f="' + n + '"]') || {}).value || '';
            return { key: v('key').trim().toLowerCase(), label: v('label').trim(), type: v('type') || 'text',
                required: !!(tr.querySelector('[data-f="required"]') || {}).checked, help: v('help'), empty_id: v('empty_id'), empty_en: v('empty_en') };
        });
    }
    function syncFields() {
        const list = rows().filter(r => r.key);
        const set = Object.assign({}, base);
        list.forEach(r => {
            set[r.key] = r.label || r.key;
            if (r.type === 'money') set[r.key + '_words'] = (r.label || r.key) + ' in words';
        });
        if (root) { root.dataset.fields = JSON.stringify({ employee: set }); root.dispatchEvent(new Event('we-fields')); }
        document.getElementById('extraFields').value = JSON.stringify(list);
        document.getElementById('noFields').classList.toggle('hidden', rowsBody.querySelector('[data-field-row]') !== null);
    }
    document.getElementById('addFieldRow').addEventListener('click', () => {
        rowsBody.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__I__/g, String(next++)));
        syncFields();
        const last = rowsBody.querySelector('[data-field-row]:last-child [data-f="key"]'); if (last) last.focus();
    });
    rowsBody.addEventListener('click', e => { if (e.target.closest('[data-remove-row]')) { e.target.closest('[data-field-row]').remove(); syncFields(); } });
    rowsBody.addEventListener('input', syncFields);
    rowsBody.addEventListener('change', syncFields);
    // keys are lower-case words: tidy as typed
    rowsBody.addEventListener('input', e => { if (e.target.matches('[data-f="key"]')) e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'); });

    // ── language tabs: one editor, two texts ────────────────────────────────────────────
    const closing = { id: document.getElementById('dClosingId'), en: document.getElementById('dClosingEn') };
    function syncClosing() { document.getElementById('extraClosing').value = closing[lang].value; }
    Object.values(closing).forEach(i => i.addEventListener('input', syncClosing));

    document.getElementById('langTabs').addEventListener('click', e => {
        const btn = e.target.closest('[data-lang]');
        if (!btn || btn.dataset.lang === lang || !editor()) return;
        bodies[lang] = editor().getHTML();
        lang = btn.dataset.lang;
        editor().setHTML(bodies[lang] || '<p><br></p>');
        document.getElementById('extraLanguage').value = lang;
        syncClosing();
        document.querySelectorAll('#langTabs [data-lang]').forEach(b => {
            const on = b.dataset.lang === lang;
            b.className = 'px-3 py-1.5 rounded-md text-xs font-semibold ' + (on ? 'bg-white text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-800');
        });
    });

    form.addEventListener('submit', () => {
        if (editor()) bodies[lang] = editor().getHTML();
        document.getElementById('bodyId').value = bodies.id || '';
        document.getElementById('bodyEn').value = bodies.en || '';
    });

    syncFields();
    syncClosing();
})();
</script>
@endpush
@endsection
