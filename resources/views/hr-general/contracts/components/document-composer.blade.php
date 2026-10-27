{{--
    "Generate document" step of a contract form (internal Contract and External Contract).

    HR fills in the fields, presses Generate, and the contract text — the template of the contract type / position type with every
    field filled in — appears below in the Word-like editor, where Legal, HR and RPMO can adjust it before the contract is saved.
    What is in the editor when the form is saved is stored as the contract's own text (employee_contract.body_html, flagged
    body_customized): later template edits no longer touch it. A form saved without generating keeps following its template.

    Live tokens ({{nomor_kontrak}}, {{meterai}}, the signature images, {{halaman_baru}}) stay in the generated text on purpose:
    they are filled when the document is shown, once the number exists and the contract is no longer a Draft.

    Parameters:
      $dcForm      id of the <form> this sits in
      $dcUrl       POST url that answers with the generated document (JSON)
      $dcExternal  true on the External Contract form (the editor then offers the consultant fields)
      $dcContract  the EmployeeContract being edited, or null for a new one
--}}
@php
    $dcContract = $dcContract ?? null;
    $dcExternal = (bool) ($dcExternal ?? false);
    $dcHasText = $dcContract && $dcContract->body_customized && $dcContract->body_html;
    $dcLetterhead = \App\Models\Letterhead::forLetter(\App\Services\Contracts\ContractService::LETTER_TYPE);
    $dcPage = $dcHasText && $dcContract->page_json
        ? \App\Support\Contracts\PageSettings::resolve($dcContract->page_json, $dcLetterhead !== null)
        : null;
    $dcStored = $dcContract && $dcContract->page_json ? $dcContract->page_json : [];
@endphp

<section id="dcBox" class="md:col-span-3 col-span-full border border-gray-200 rounded-xl overflow-hidden bg-white" data-url="{{ $dcUrl }}" data-form="{{ $dcForm }}">
    <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h4 class="text-sm font-bold text-gray-800"><i class="far fa-file-lines mr-1 text-gray-500"></i> Contract document</h4>
            <p class="text-[11px] text-gray-500 mt-0.5" id="dcHint">
                @if($dcHasText)
                    This contract has its own adjusted text. Edit it below, or generate it again from the template.
                @else
                    Fill in the fields above, then generate the document to read it and adjust it before saving. Without generating, the contract follows its template.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="dcGenerate" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90 shadow-sm">
                <i class="fas fa-wand-magic-sparkles mr-1"></i> <span id="dcGenerateText">{{ $dcHasText ? 'Generate again' : 'Generate document' }}</span>
            </button>
            <button type="button" id="dcReset" class="{{ $dcHasText ? '' : 'hidden' }} px-3 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 bg-white" title="Forget the adjusted text; the contract follows its template again">
                <i class="fas fa-rotate-left mr-1"></i> Back to template
            </button>
        </div>
    </div>

    <div id="dcStatus" class="hidden px-4 py-2 text-xs border-b" role="status"></div>

    <input type="hidden" name="body_html" id="dcBody" value="">
    <input type="hidden" name="body_customized" id="dcCustom" value="">
    <input type="hidden" name="reset_body" id="dcResetFlag" value="">
    {{-- Stand-in for the "letterhead on" switch the editor listens to; the generated template decides it. --}}
    <input type="checkbox" id="dcLetterhead" class="hidden" tabindex="-1" aria-hidden="true" checked>
    <input type="hidden" id="dcTypeProbe" value="{{ $dcExternal ? 'EXTERNAL' : 'PKWT' }}">

    <div id="dcEditorWrap" class="{{ $dcHasText ? '' : 'hidden' }} p-3">
        <div class="mb-2 text-[11px] text-gray-500 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span id="dcTemplateNote"></span>
            <span><i class="fas fa-circle-info mr-1"></i> Fields in <code class="bg-gray-100 px-1 rounded">@{{ … }}</code> (number, stamp, signatures) are filled in when the document is shown.</span>
        </div>
        @include('hr-general.contracts.components.word-editor', [
            'weHtml'       => $dcHasText ? \App\Support\Contracts\ContractHtmlSanitizer::clean($dcContract->body_html) : '<p><br></p>',
            'weInput'      => '',
            'weForm'       => $dcForm,
            'weFields'     => ['employee' => \App\Support\Contracts\ContractPlaceholders::EMPLOYEE, 'external' => \App\Support\Contracts\ContractPlaceholders::EXTERNAL],
            'weTypeSelect' => 'dcTypeProbe',
            'weLhToggle'   => '#dcLetterhead',
            'weLetterhead' => $dcLetterhead?->backgroundDataUri(),
            'wePreviewUrl' => route('general.contracts.templates.preview'),
            'wePage'       => $dcPage
                ? ['size' => $dcPage['size'] ?? 'A4', 'orientation' => $dcPage['orientation'] ?? 'portrait',
                   'margins' => ['top' => $dcStored['margins']['top'] ?? null, 'right' => $dcStored['margins']['right'] ?? null, 'bottom' => $dcStored['margins']['bottom'] ?? null, 'left' => $dcStored['margins']['left'] ?? null]]
                : ['size' => 'A4', 'orientation' => 'portrait', 'margins' => ['top' => null, 'right' => null, 'bottom' => null, 'left' => null]],
            'wePageDefaults' => ['plain' => \App\Support\Contracts\PageSettings::defaultMarginsCm(false), 'letterhead' => \App\Support\Contracts\PageSettings::defaultMarginsCm(true)],
        ])
    </div>
</section>

<script>
(function () {
    const box = document.getElementById('dcBox');
    if (!box) return;
    const form = document.getElementById(box.dataset.form);
    const $ = id => document.getElementById(id);
    const wrap = $('dcEditorWrap');
    let generated = {{ $dcHasText ? 'true' : 'false' }};     // the editor holds a document that goes with the form

    const editor = () => wrap.querySelector('[data-word-editor]')?._we;

    function status(kind, text) {
        const s = $('dcStatus');
        s.className = 'px-4 py-2 text-xs border-b ' + ({
            error: 'bg-red-50 border-red-200 text-red-700',
            ok: 'bg-green-50 border-green-200 text-green-800',
            warn: 'bg-amber-50 border-amber-200 text-amber-900',
        }[kind] || 'bg-gray-50 border-gray-200 text-gray-700');
        s.textContent = text;
    }

    // Pesan berdaftar: judul + baris; baris yang punya url menjadi tautan (tab baru agar isian form tidak hilang).
    function statusList(kind, heading, rows) {
        status(kind, heading);
        const s = $('dcStatus');
        if (!rows.length) return;
        const ul = document.createElement('ul');
        ul.className = 'mt-1.5 space-y-1';
        rows.forEach(r => {
            const li = document.createElement('li');
            li.className = 'flex items-start gap-1.5';
            const dot = document.createElement('span'); dot.textContent = '•'; li.appendChild(dot);
            const body = document.createElement('span');
            if (r.url) {
                const a = document.createElement('a');
                a.href = r.url; a.target = '_blank'; a.rel = 'noopener';
                a.className = 'font-semibold underline decoration-dotted hover:opacity-80';
                a.textContent = r.text;
                body.appendChild(a);
                const hint = document.createElement('span'); hint.textContent = ' — ' + (r.tab ? 'open the ' + r.tab + ' tab' : 'open the page') + ' ↗'; hint.className = 'opacity-75';
                body.appendChild(hint);
            } else {
                body.textContent = r.text;
            }
            if (r.why) { const w = document.createElement('span'); w.textContent = ' (' + r.why + ')'; w.className = 'opacity-75'; body.appendChild(w); }
            li.appendChild(body); ul.appendChild(li);
        });
        s.appendChild(ul);
        s.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    const issueRows = list => (list || []).map(i => ({ text: i.label, url: i.url, tab: i.tab_label, why: i.why }));

    async function generate() {
        const btn = $('dcGenerate');
        const fd = new FormData(form);
        ['body_html', 'body_customized', 'reset_body'].forEach(k => fd.delete(k));
        btn.disabled = true; $('dcGenerateText').textContent = 'Generating…';
        try {
            const res = await fetch(box.dataset.url, {
                method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json().catch(() => null);
            if (!res.ok || (json && json.success === false)) {
                if (json === null) {
                    // Bukan JSON (halaman galat peladen / sesi habis): sebutkan penyebab yang paling mungkin, bukan pesan umum.
                    status('error', ({
                        419: 'Your session has expired. Reload the page (your form is kept if you saved it as a Draft) and try again.',
                        401: 'You are signed out. Sign in again, then generate the document.',
                        403: 'You do not have permission to generate this document. Ask an administrator for the Create or Edit permission of this menu.',
                        404: 'The consultant or contract was not found. Reload the page.',
                        500: 'The server hit an error while generating the document (code 500). Tell the administrator the time of this attempt.',
                    })[res.status] || ('The server answered with an unexpected result (code ' + res.status + '). Reload the page and try again.'));
                    return;
                }
                const fieldErrors = Object.values(json.errors || {}).flat();
                if (json.issues && json.issues.length) {
                    statusList('error', json.message || 'Some data is missing:', issueRows(json.issues));
                } else if (fieldErrors.length) {
                    statusList('error', fieldErrors.length === 1 ? 'Please correct this field:' : 'Please correct these ' + fieldErrors.length + ' fields:', fieldErrors.map(t => ({ text: t })));
                } else {
                    status('error', json.message || 'The document could not be generated for an unknown reason (the server gave no detail).');
                }
                return;
            }

            wrap.classList.remove('hidden');
            const root = wrap.querySelector('[data-word-editor]');
            const api = root._we;
            // Letterhead and paper of the template this contract was generated from.
            $('dcLetterhead').checked = !!json.use_letterhead;
            $('dcLetterhead').dispatchEvent(new Event('change'));
            if (json.page) api.setPage({ size: json.page.size, orient: json.page.orientation, m: json.page.margins });
            api.setHTML(json.html);
            api.repaginate && api.repaginate();
            window.dispatchEvent(new Event('resize'));

            generated = true;
            $('dcTemplateNote').innerHTML = '<i class="far fa-file-lines mr-1"></i> Template: <strong></strong>';
            $('dcTemplateNote').querySelector('strong').textContent = json.template?.name || '';
            $('dcResetFlag').value = '';
            $('dcHint').textContent = 'Read the document below and adjust it where needed. It is stored with the contract when you save.';
            const gaps = json.gaps || [];
            if (gaps.length) {
                statusList('warn', 'Generated, but ' + gaps.length + ' detail' + (gaps.length === 1 ? ' is' : 's are') + ' empty in the contract text. Fill them in on the person’s page, then generate again:', issueRows(gaps));
            } else {
                status(json.warnings && json.warnings.length ? 'warn' : 'ok',
                    json.warnings && json.warnings.length
                        ? 'Generated. These fields are not known for this contract type: ' + json.warnings.join(', ') + '.'
                        : 'Generated from the template “' + (json.template?.name || '') + '”. Adjust the text below if needed, then save the contract.');
            }
            wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            status('error', 'Could not reach the server (network problem or the page was left open too long). Check your connection, reload the page and try again.');
        } finally {
            btn.disabled = false; $('dcGenerateText').textContent = 'Generate again';
        }
    }

    $('dcGenerate').addEventListener('click', async () => {
        if (generated && !(await (window.showConfirm
            ? window.showConfirm('Generate the document again? The adjustments you made in the editor are replaced by a fresh document.', 'Generate Again', 'primary', { okText: 'Generate', cancelText: 'Cancel' })
            : Promise.resolve(confirm('Generate the document again? Your adjustments are replaced.'))))) return;
        generate();
    });

    $('dcReset').addEventListener('click', async () => {
        const ok = await (window.showConfirm
            ? window.showConfirm('Forget the adjusted text? The contract follows its template again when you save.', 'Back to Template', 'danger', { okText: 'Back to template', cancelText: 'Cancel' })
            : Promise.resolve(confirm('Forget the adjusted text?')));
        if (!ok) return;
        generated = false;
        $('dcResetFlag').value = '1';
        wrap.classList.add('hidden');
        $('dcReset').classList.add('hidden');
        $('dcGenerateText').textContent = 'Generate document';
        $('dcHint').textContent = 'The contract follows its template again once you save.';
        status('warn', 'The adjusted text will be dropped when you save the contract.');
    });

    // On save the editor's text travels with the form — only when a generated document is in the editor.
    form.addEventListener('submit', () => {
        const api = editor();
        if (generated && api && !wrap.classList.contains('hidden')) {
            $('dcBody').value = api.getHTML();
            $('dcCustom').value = '1';
        } else {
            $('dcBody').value = '';
            $('dcCustom').value = '';
        }
    }, true);

    // The editor's field list and its PDF preview follow the built-in type the chosen contract type stands for.
    const typeSelect = $('fType');
    const probe = $('dcTypeProbe');
    if (typeSelect && probe) {
        const sync = () => { probe.value = (window.contractTypeBase || {})[typeSelect.value] || typeSelect.value; probe.dispatchEvent(new Event('change')); };
        typeSelect.addEventListener('change', sync);
        sync();
    }

    // A changed contract type invalidates what was generated, so say so.
    ['fType', 'fScheme', 'fTpl', 'fPosType'].forEach(id => $(id)?.addEventListener('change', () => {
        if (generated) status('warn', 'A field that decides the template changed. Generate the document again to apply it.');
    }));

    // The modal of the internal form is reused for every contract: it clears the box, or loads the text a contract already has.
    window.dcClear = function () {
        generated = false;
        wrap.classList.add('hidden');
        $('dcReset').classList.add('hidden');
        $('dcStatus').className = 'hidden';
        $('dcBody').value = ''; $('dcCustom').value = ''; $('dcResetFlag').value = '';
        $('dcGenerateText').textContent = 'Generate document';
        $('dcHint').textContent = 'Fill in the fields above, then generate the document to read it and adjust it before saving. Without generating, the contract follows its template.';
    };
    window.dcLoad = function (html, page) {
        const api = editor();
        // The editor starts when the page has loaded; a call made while the page is still parsing waits for it.
        if (!api) { document.addEventListener('DOMContentLoaded', () => window.dcLoad(html, page), { once: true }); return; }
        window.dcClear();
        wrap.classList.remove('hidden');
        if (page) api.setPage({ size: page.size, orient: page.orientation, m: page.margins || {} });
        api.setHTML(html);
        api.repaginate && api.repaginate();
        window.dispatchEvent(new Event('resize'));
        generated = true;
        $('dcReset').classList.remove('hidden');
        $('dcGenerateText').textContent = 'Generate again';
        $('dcHint').textContent = 'This contract has its own adjusted text. Edit it below, or generate it again from the template.';
    };
})();
</script>
