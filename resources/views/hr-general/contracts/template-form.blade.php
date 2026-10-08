@extends('dashboard')
@section('title', $template->exists ? 'Edit Contract Template' : 'Add Contract Template')
@section('page-title', 'Contract')
@section('page-subtitle', 'Create reusable contract content for employees or external consultants.')

@php
    $isEdit = $template->exists;
    $locked = $isEdit && $template->is_system_default;
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $type = old('contract_type', $template->contract_type);
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">
    @include('hr-general.contracts.components.tabs')
    @include('hr-general.recruitment.components.form-errors')

    <form method="POST" id="templateForm"
          action="{{ $isEdit ? route('general.contracts.templates.update', $template->id) : route('general.contracts.templates.store') }}" class="space-y-6">
        @csrf
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9"><label class="{{ $label }}" for="tName">Template name</label>
                <input id="tName" name="name" value="{{ old('name', $template->name) }}" required maxlength="150" class="{{ $input }}"></div>
            <div class="md:col-span-3"><label class="{{ $label }}" for="tStatus">Status</label>
                <select id="tStatus" name="status" class="{{ $input }}" @disabled($locked)>
                    <option value="active" @selected(old('status', $template->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $template->status) === 'inactive')>Inactive</option>
                </select>
                @if($locked)<p class="text-[10px] text-gray-400 mt-1">A system default is always active.</p>@endif</div>

            <div class="md:col-span-4"><label class="{{ $label }}" for="tType">Contract type</label>
                <select id="tType" name="contract_type" class="{{ $input }}" @disabled($locked)>
                    @foreach($types as $value => $name)<option value="{{ $value }}" @selected($type === $value)>{{ $name }}</option>@endforeach
                </select>
                @if($locked)<input type="hidden" name="contract_type" value="{{ $template->contract_type }}">@endif</div>
            <div class="md:col-span-8" id="tPositionWrap"><label class="{{ $label }}" for="tPosition">Position</label>
                <select id="tPosition" name="position" class="{{ $input }}" @disabled($locked)>
                    <option value="">All positions</option>
                    @foreach($positions as $p)<option value="{{ $p }}" @selected(old('position', $template->position) === $p)>{{ $p }}</option>@endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">A template for one position is used before a general one.</p></div>
            <div class="md:col-span-12 hidden" id="tExternalNote">
                <div class="text-xs bg-sky-50 border border-sky-200 text-sky-900 rounded-lg px-4 py-2.5"><i class="fas fa-circle-info mr-1"></i> This template will appear in the template selector of an external consultant contract. Position does not apply.</div>
            </div>

            <div class="md:col-span-12"><label class="{{ $label }}" for="tDesc">Description</label>
                <input id="tDesc" name="description" value="{{ old('description', $template->description) }}" maxlength="500" class="{{ $input }}"></div>

            <div class="md:col-span-6"><label class="{{ $label }}" for="tSigner">Default company signatory</label>
                <select id="tSigner" name="signatory_employee_id" class="{{ $input }}">
                    <option value="">None — choose on each contract</option>
                    @foreach($signatories as $p)<option value="{{ $p['id'] }}" @selected((string) old('signatory_employee_id', $template->signatory_employee_id) === (string) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>@endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Used when a contract of this template does not pick a signer itself. People come from Letter Templates → Settings → Signers.</p></div>

            <div class="md:col-span-12">
                <input type="hidden" name="use_letterhead" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="use_letterhead" value="1" class="rounded border-gray-300" @checked(old('use_letterhead', $template->use_letterhead))> Enable letterhead
                </label>
                <p class="text-[11px] text-gray-400 ml-6">Prints the company letterhead behind the contract. The letterhead itself is set in Letter Templates → Settings, letter type “Employment Contract”.
                    @unless($letterhead) <strong class="text-amber-700">None is set yet.</strong>@endunless
                    @if($canDo('general.letter-templates'))<a href="{{ route('general.letters.settings.index', ['section' => 'letterheads']) }}" class="underline font-semibold">Manage letterheads</a>@endif</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 pt-4">
                <h3 class="text-sm font-bold text-gray-800">Contract Text</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Click a placeholder to insert it where the cursor is. Placeholders are filled in when the contract is shown or printed.</p>
                @foreach(['employee' => 'Employee placeholders (PKWT / PKWTT)', 'external' => 'External consultant placeholders'] as $group => $title)
                    <div data-group="{{ $group }}" class="mt-2 flex flex-wrap gap-1 items-center">
                        <span class="text-[11px] text-gray-500 mr-1">{{ $title }}:</span>
                        @foreach($placeholders[$group] as $key => $meaning)
                            @php $token = '{{' . $key . '}}'; @endphp
                            <button type="button" data-insert="{{ $token }}" title="{{ $meaning }}"
                                class="px-2 py-0.5 rounded-md border border-gray-200 bg-white text-[11px] font-mono text-gray-600 hover:bg-indigo-50 hover:border-indigo-200">{{ $token }}</button>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="m-5 border border-gray-200 rounded-lg overflow-hidden">
                <div class="flex flex-wrap items-center gap-1 px-2 py-1.5 bg-gray-50 border-b border-gray-200" id="editorBar">
                    <select data-block class="text-xs border border-gray-200 rounded-md px-1.5 py-1 bg-white" style="width:auto">
                        <option value="p">Normal</option><option value="h1">Heading 1</option><option value="h2">Heading 2</option><option value="h3">Heading 3</option>
                    </select>
                    @foreach([['bold','bold'],['italic','italic'],['underline','underline'],['strikeThrough','strikethrough']] as [$cmd, $icon])
                        <button type="button" data-command="{{ $cmd }}" class="w-7 h-7 rounded-md text-gray-600 hover:bg-gray-200 aria-pressed:bg-indigo-100 aria-pressed:text-indigo-700" title="{{ ucfirst($cmd) }}"><i class="fas fa-{{ $icon }} text-xs"></i></button>
                    @endforeach
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    @foreach([['insertOrderedList','list-ol'],['insertUnorderedList','list-ul'],['indent','indent'],['outdent','outdent']] as [$cmd, $icon])
                        <button type="button" data-command="{{ $cmd }}" class="w-7 h-7 rounded-md text-gray-600 hover:bg-gray-200" title="{{ $cmd }}"><i class="fas fa-{{ $icon }} text-xs"></i></button>
                    @endforeach
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    @foreach([['justifyLeft','align-left'],['justifyCenter','align-center'],['justifyRight','align-right'],['justifyFull','align-justify']] as [$cmd, $icon])
                        <button type="button" data-command="{{ $cmd }}" class="w-7 h-7 rounded-md text-gray-600 hover:bg-gray-200" title="{{ $cmd }}"><i class="fas fa-{{ $icon }} text-xs"></i></button>
                    @endforeach
                    <span class="w-px h-5 bg-gray-200 mx-1"></span>
                    <select data-list-style class="text-xs border border-gray-200 rounded-md px-1.5 py-1 bg-white" style="width:auto" title="Numbering style of the list under the cursor">
                        <option value="">List style…</option><option value="decimal">1. 2. 3.</option><option value="lower-alpha">a. b. c.</option>
                        <option value="upper-alpha">A. B. C.</option><option value="lower-roman">i. ii. iii.</option><option value="upper-roman">I. II. III.</option><option value="disc">Bullets</option>
                    </select>
                    <button type="button" data-command="removeFormat" class="w-7 h-7 rounded-md text-gray-600 hover:bg-gray-200" title="Clear formatting"><i class="fas fa-eraser text-xs"></i></button>
                </div>
                <div id="bodyEditor" contenteditable="true" role="textbox" aria-multiline="true"
                     class="min-h-[28rem] max-h-[70vh] overflow-y-auto px-8 py-6 bg-white text-[13px] leading-relaxed focus:outline-none" style="font-family: 'Times New Roman', Times, serif">{!! \App\Support\Contracts\ContractHtmlSanitizer::clean(old('body_html', $template->body_html)) !!}</div>
                <input type="hidden" name="body_html" id="bodyHtml">
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('general.contracts.templates.index') }}" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 bg-white">Cancel</a>
            <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">{{ $isEdit ? 'Save Template' : 'Create Template' }}</button>
        </div>
    </form>
</div>

@push('styles')
<style>
    #editorBar select { width: auto !important; display: inline-block; }
    @include('hr-general.contracts.components.paper-css', ['sel' => '#bodyEditor'])
</style>
@endpush

@push('scripts')
<script>
(function () {
    const editor = document.getElementById('bodyEditor');
    const hidden = document.getElementById('bodyHtml');
    const form = document.getElementById('templateForm');
    const typeSel = document.getElementById('tType');

    // Only the placeholders of the chosen type are offered; consultants have no position.
    function syncType() {
        const ext = typeSel.value === 'EXTERNAL';
        document.querySelector('[data-group="employee"]').classList.toggle('hidden', ext);
        document.querySelector('[data-group="external"]').classList.toggle('hidden', !ext);
        document.getElementById('tPositionWrap').classList.toggle('hidden', ext);
        document.getElementById('tExternalNote').classList.toggle('hidden', !ext);
    }
    typeSel.addEventListener('change', syncType);
    syncType();

    // A toolbar click must not take the focus (and the selection) away from the text.
    document.querySelectorAll('[data-command], [data-insert]').forEach(b => b.addEventListener('mousedown', e => e.preventDefault()));
    document.querySelectorAll('[data-command]').forEach(b => b.addEventListener('click', () => { editor.focus(); document.execCommand(b.dataset.command); sync(); }));
    document.querySelectorAll('[data-insert]').forEach(b => b.addEventListener('click', () => { editor.focus(); document.execCommand('insertText', false, b.dataset.insert); }));
    document.querySelector('[data-block]').addEventListener('change', e => { editor.focus(); document.execCommand('formatBlock', false, e.target.value); });

    // Numbering style of the list the cursor is in (execCommand cannot set it).
    document.querySelector('[data-list-style]').addEventListener('change', e => {
        let n = document.getSelection()?.anchorNode;
        while (n && n !== editor && !(['OL', 'UL'].includes(n.nodeName))) n = n.parentNode;
        if (n && n !== editor && e.target.value) n.style.listStyleType = e.target.value;
        e.target.value = '';
    });

    // Pasted text arrives without its outside formatting (also keeps foreign styles out of the contract).
    editor.addEventListener('paste', e => { e.preventDefault(); document.execCommand('insertText', false, e.clipboardData.getData('text/plain')); });

    function sync() {
        document.querySelectorAll('[data-command]').forEach(b => {
            try { b.setAttribute('aria-pressed', document.queryCommandState(b.dataset.command)); } catch (_) {}
        });
    }
    document.addEventListener('selectionchange', () => { if (editor.contains(document.getSelection()?.anchorNode)) sync(); });

    form.addEventListener('submit', e => {
        hidden.value = editor.innerHTML;
        if (!editor.textContent.trim()) { e.preventDefault(); window.showNotification('The contract text cannot be empty.', 'error'); }
    });
    hidden.value = editor.innerHTML;
})();
</script>
@endpush
@endsection
