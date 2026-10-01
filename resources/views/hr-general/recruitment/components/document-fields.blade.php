{{--
    The documents section of a candidate form, in one piece:

      1. what the chosen job opening asks for — kept in step with the job
         opening <select>: pick another opening and the list changes with it,
         pick none and it disappears. Each line says whether the document is
         required or optional, what form it may take, and whether the
         candidate already has it; "+" starts a row of that type;
      2. the rows being added: document type + file and/or link. A row follows
         the rules HR set for its type on the Settings tab — picking the type
         shows the file input, the link input, or both, and limits the file
         picker to the accepted formats. The controller enforces the same
         rules on save.

    Rows post as documents[i][type_id], documents[i][file], documents[i][url];
    add one from anywhere with addDocumentRow(containerId, typeId?).

    Parameters:
      $rows         — id to give the rows container
      $jobSelect    — id of the job opening <select> to follow
      $requirements — [job id => JobOpening::documentRequirements()]
      $attached     — document type ids the candidate already has
      $canAdd       — show the "+" buttons
      $autoRows     — true: prepare a row for every required document as soon
                      as a job opening is picked (the new-candidate form)
    Expects $documentTypes and $documentRules (RecruitmentFormOptions) in scope.
--}}
<div data-document-requirements data-job-select="{{ $jobSelect }}" data-rows="{{ $rows }}"
    data-attached='@json(array_values($attached ?? []))' data-can-add="{{ ($canAdd ?? false) ? '1' : '' }}" data-auto-rows="{{ ($autoRows ?? false) ? '1' : '' }}"
    class="hidden border border-gray-200 rounded-lg bg-gray-50 px-3 py-2.5 mb-2" aria-live="polite">
    <p class="text-[11px] font-semibold text-gray-700 mb-1.5"><i class="fas fa-clipboard-list mr-1 text-gray-400"></i> Requested by this job opening</p>
    <ul class="space-y-1.5" data-requirement-list></ul>
</div>

<div id="{{ $rows }}" class="space-y-2" data-document-rows></div>

@once
<template id="documentRowTemplate">
    <div class="flex items-start gap-2" data-document-row>
        <div class="w-44 shrink-0">
            <select data-name="type_id" aria-label="Document type" onchange="syncDocumentRow(this.closest('[data-document-row]'))">
                @foreach($documentTypes as $documentType)
                    <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-0 space-y-1.5">
            <input type="file" data-name="file" data-document-file aria-label="File"
                class="w-full text-xs text-gray-600 border border-gray-200 rounded-lg px-2 py-1.5 file:mr-3 file:py-1 file:px-2.5 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
            <input type="url" data-name="url" data-document-link aria-label="Link to the document" maxlength="500" placeholder="https://… link to the document"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-200">
            <p class="text-[11px] text-gray-400" data-document-hint></p>
        </div>
        <button type="button" onclick="this.closest('[data-document-row]').remove()" class="text-gray-400 hover:text-red-600 px-1 pt-2" title="Remove row" aria-label="Remove document row">
            <i class="fas fa-times"></i>
        </button>
    </div>
</template>

@push('scripts')
<script>
    (function () {
        const rules = @json($documentRules);
        let nextIndex = 0;

        // Show what the row's document type allows: a file, a link, or either.
        window.syncDocumentRow = function (row) {
            const rule = rules[row.querySelector('select').value] || { file: true, link: false, accept: '', hint: '' };
            const file = row.querySelector('[data-document-file]');
            const link = row.querySelector('[data-document-link]');

            file.classList.toggle('hidden', !rule.file);
            link.classList.toggle('hidden', !rule.link);
            file.accept = rule.accept;
            // Whatever was entered for an input the type no longer allows must not be sent.
            if (!rule.file) file.value = '';
            if (!rule.link) link.value = '';

            row.querySelector('[data-document-hint]').textContent = rule.hint + (rule.file ? ' · up to 10 MB' : '');
        };

        window.addDocumentRow = function (containerId, typeId) {
            const container = document.getElementById(containerId);
            const row = document.getElementById('documentRowTemplate').content.firstElementChild.cloneNode(true);
            const index = nextIndex++;

            row.querySelectorAll('[data-name]').forEach(el => {
                el.name = 'documents[' + index + '][' + el.dataset.name + ']';
                // A row added to a form section that is switched off must stay out of the submission too.
                el.disabled = !!container.closest('[data-block-off]');
            });

            if (typeId) row.querySelector('select').value = String(typeId);

            // Once in the document, the global enhancer (select-enhance.js) restyles the row's <select> by itself.
            container.appendChild(row);
            syncDocumentRow(row);

            return row;
        };
    })();
</script>
@endpush
@endonce

@push('scripts')
<script>
    window.recruitmentJobRequirements = Object.assign(window.recruitmentJobRequirements || {}, @json($requirements));
</script>
@endpush

@once
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-document-requirements]').forEach(function (panel) {
            const select = document.getElementById(panel.dataset.jobSelect);
            const rows = document.getElementById(panel.dataset.rows);
            const list = panel.querySelector('[data-requirement-list]');
            const attached = JSON.parse(panel.dataset.attached).map(String);
            if (!select) return;

            const hasRow = typeId => Array.from(rows.querySelectorAll('[data-document-row] select')).some(el => el.value === String(typeId));

            function render() {
                const requirements = window.recruitmentJobRequirements[select.value] || [];
                panel.classList.toggle('hidden', requirements.length === 0);

                list.replaceChildren(...requirements.map(requirement => {
                    const has = attached.includes(String(requirement.typeId));
                    const item = document.createElement('li');
                    item.className = 'flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs';

                    const icon = document.createElement('i');
                    icon.className = has ? 'fas fa-circle-check text-green-600'
                        : (requirement.required ? 'fas fa-circle-exclamation text-red-500' : 'far fa-circle text-gray-400');

                    const name = document.createElement('span');
                    name.className = 'font-semibold text-gray-800';
                    name.textContent = requirement.name;

                    const level = document.createElement('span');
                    level.className = 'px-1.5 py-0.5 rounded text-[10px] font-semibold '
                        + (requirement.required ? 'bg-red-50 text-red-700' : 'bg-gray-200 text-gray-600');
                    level.textContent = requirement.required ? 'Required' : 'Optional';

                    const hint = document.createElement('span');
                    hint.className = 'text-gray-500';
                    hint.textContent = requirement.hint;

                    const state = document.createElement('span');
                    state.className = 'ml-auto ' + (has ? 'text-green-700' : (requirement.required ? 'text-red-600 font-semibold' : 'text-gray-400'));
                    state.textContent = has ? 'Attached' : (requirement.required ? 'Missing' : 'Not attached');

                    item.append(icon, name, level, hint, state);

                    if (panel.dataset.canAdd && !has) {
                        const add = document.createElement('button');
                        add.type = 'button';
                        add.className = 'font-semibold';
                        add.style.color = 'var(--primary-color)';
                        add.title = 'Add a ' + requirement.name + ' row';
                        add.innerHTML = '<i class="fas fa-plus text-[10px]"></i>';
                        add.setAttribute('aria-label', 'Add ' + requirement.name);
                        add.addEventListener('click', () => addDocumentRow(panel.dataset.rows, requirement.typeId));
                        item.append(add);
                    }

                    return item;
                }));

                if (panel.dataset.autoRows) {
                    requirements
                        .filter(requirement => requirement.required && !hasRow(requirement.typeId))
                        .forEach(requirement => addDocumentRow(panel.dataset.rows, requirement.typeId));
                }
            }

            select.addEventListener('change', render);
            render();
        });
    });
</script>
@endpush
@endonce
