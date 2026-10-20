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
                <select id="tType" name="contract_type" data-searchable="true" data-search-placeholder="Search contract type…" class="{{ $input }}" @disabled($locked)>
                    @foreach($types as $value => $name)<option value="{{ $value }}" @selected($type === $value)>{{ $name }}</option>@endforeach
                </select>
                @if($locked)<input type="hidden" name="contract_type" value="{{ $template->contract_type }}">@endif</div>
            <div class="md:col-span-4"><label class="{{ $label }}" for="tPositionType">Position type</label>
                <select id="tPositionType" name="position_type" data-searchable="true" data-search-placeholder="Search position type…" class="{{ $input }}" @disabled($locked)>
                    <option value="">All position types</option>
                    @php $ptSet = $type === 'EXTERNAL' ? $positionTypeSets['external'] : $positionTypeSets['internal']; @endphp
                    @foreach($ptSet as $pt)<option value="{{ $pt }}" @selected(old('position_type', $template->position_type) === $pt)>{{ $pt }}</option>@endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">A template written for a position type is used before a general one. Kept in the Settings tab of the menu.</p></div>
            <div class="md:col-span-4" id="tPositionWrap"><label class="{{ $label }}" for="tPosition">Position</label>
                <select id="tPosition" name="position" data-searchable="true" data-search-placeholder="Search position…" class="{{ $input }}" @disabled($locked)>
                    <option value="">All positions</option>
                    @foreach($positions as $p)<option value="{{ $p }}" @selected(old('position', $template->position) === $p)>{{ $p }}</option>@endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">An exact job title; used after the position type.</p></div>
            <div class="md:col-span-12 hidden" id="tExternalNote">
                <div class="text-xs bg-sky-50 border border-sky-200 text-sky-900 rounded-lg px-4 py-2.5"><i class="fas fa-circle-info mr-1"></i> This template will appear in the template selector of an external consultant contract. A position type from the External Contract Settings can be set; Position does not apply.</div>
            </div>

            <div class="md:col-span-12"><label class="{{ $label }}" for="tDesc">Description</label>
                <input id="tDesc" name="description" value="{{ old('description', $template->description) }}" maxlength="500" class="{{ $input }}"></div>

            <div class="md:col-span-6"><label class="{{ $label }}" for="tSigner">Default company signatory</label>
                <select id="tSigner" name="signatory_employee_id" class="{{ $input }}">
                    <option value="">None — choose on each contract</option>
                    @foreach($signatories as $p)<option value="{{ $p['id'] }}" @selected((string) old('signatory_employee_id', $template->signatory_employee_id) === (string) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>@endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Used when a contract of this template does not pick a signer itself. People come from Letter Templates → Settings → Signers.</p></div>

            <div class="md:col-span-6"><label class="{{ $label }}" for="tApprover">Approver</label>
                <select id="tApprover" name="approver_employee_id" data-searchable="true" data-search-placeholder="Search approver…" class="{{ $input }}">
                    <option value="">None — Legal picks one when forwarding</option>
                    @foreach($signatories as $p)<option value="{{ $p['id'] }}" @selected((string) old('approver_employee_id', $template->approver_employee_id) === (string) $p['id'])>{{ $p['name'] }}@if(!empty($p['position'])) — {{ $p['position'] }}@endif</option>@endforeach
                </select>
                <p class="text-[10px] text-gray-400 mt-1">Reviews and signs every contract made from this template after the employee has signed. Needs a signature in the master data.</p></div>

            <div class="md:col-span-12">
                <input type="hidden" name="use_letterhead" value="0">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" id="tLetterhead" name="use_letterhead" value="1" class="rounded border-gray-300" @checked(old('use_letterhead', $template->use_letterhead))> Enable letterhead
                </label>
                <p class="text-[11px] text-gray-400 ml-6">Prints the company letterhead behind the contract. The letterhead itself is set in Letter Templates → Settings, letter type “Employment Contract”.
                    @unless($letterhead) <strong class="text-amber-700">None is set yet.</strong>@endunless
                    @if($canDo('general.letters.settings.letterheads'))<a href="{{ route('general.document-identity.index', ['section' => 'letterheads']) }}" class="underline font-semibold">Manage letterheads</a>@endif</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <div class="mb-3">
                <h3 class="text-sm font-bold text-gray-800">Contract Text</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Write and format the contract here as in Word — fonts, headings, lists, tables, indents on the ruler, page breaks. Use <strong>Fields</strong> to insert what is filled in from the employee and the contract. Press <kbd class="px-1 border rounded bg-gray-50">F1</kbd> for the keyboard shortcuts.</p>
            </div>
            @include('hr-general.contracts.components.word-editor', [
                'weHtml'       => \App\Support\Contracts\ContractHtmlSanitizer::clean(old('body_html', $template->body_html)),
                'weInput'      => 'bodyHtml',
                'weForm'       => 'templateForm',
                'weFields'     => $placeholders,
                'weTypeSelect' => 'tType',
                'weLhToggle'   => '#tLetterhead',
                'weLetterhead' => $letterheadUri ?? null,
                'wePreviewUrl' => route('general.contracts.templates.preview'),
                'wePage'       => [
                    'size'        => old('page_size', $page['size']),
                    'orientation' => old('page_orientation', $page['orientation']),
                    'margins'     => [
                        'top'    => old('margin_top', $page['margins']['top']),
                        'right'  => old('margin_right', $page['margins']['right']),
                        'bottom' => old('margin_bottom', $page['margins']['bottom']),
                        'left'   => old('margin_left', $page['margins']['left']),
                    ],
                ],
                'wePageDefaults' => $pageDefaults,
            ])
            <input type="hidden" name="body_html" id="bodyHtml">
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('general.contracts.templates.index') }}" class="px-4 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 bg-white">Cancel</a>
            <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">{{ $isEdit ? 'Save Template' : 'Create Template' }}</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const typeSel = document.getElementById('tType');
    const typeSets = @json($positionTypeSets);
    const ptSel = document.getElementById('tPositionType');

    // The position types offered follow the menu the contract type belongs to (internal / external).
    function fillPositionTypes(ext) {
        const keep = ptSel.value;
        const names = typeSets[ext ? 'external' : 'internal'] || [];
        ptSel.innerHTML = '<option value="">All position types</option>' + names.map(n => `<option value="${n.replace(/"/g, '&quot;')}">${n.replace(/</g, '&lt;')}</option>`).join('');
        ptSel.value = names.includes(keep) ? keep : '';
    }

    // Consultants have no position; the Fields menu of the editor follows the type by itself.
    function syncType() {
        const ext = typeSel.value === 'EXTERNAL';
        document.getElementById('tPositionWrap').classList.toggle('hidden', ext);
        document.getElementById('tExternalNote').classList.toggle('hidden', !ext);
        if (ptSel.dataset.ext !== String(ext)) { fillPositionTypes(ext); ptSel.dataset.ext = String(ext); }
    }
    ptSel.dataset.ext = String(typeSel.value === 'EXTERNAL');
    typeSel.addEventListener('change', syncType);
    syncType();
})();
</script>
@endpush
@endsection
