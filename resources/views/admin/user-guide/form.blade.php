@extends('dashboard')

@php
    $isNew = $topic['is_new'];
    $pick = old('requires', $topic['requires']);
    $has = fn (string $v) => in_array($v, (array) $pick, true);
    $localeLabel = config('user_guide.locales')[$locale] ?? $locale;
    $title = $isNew ? 'New topic' : 'Edit topic';
@endphp

@section('title', 'User Guide Editor')
@section('page-title', $title)
@section('page-subtitle', $isNew ? 'Create a new topic for ' . $localeLabel : $topic['key'] . ' (' . $localeLabel . ')')

@push('styles')
@include('user-guide._body-css')
<style>
    .uge-scope { --uge-accent: rgb(var(--primary-rgb, 153, 27, 27)); --uge-accent-dark: rgb(var(--primary-dark-rgb, 127, 29, 29)); --uge-ring: rgba(var(--primary-rgb, 153, 27, 27), 0.18); --uge-tint: rgba(var(--primary-rgb, 153, 27, 27), 0.08); font-family: inherit; }
    .uge-scope input, .uge-scope select, .uge-scope textarea, .uge-scope button { font-family: inherit; }
    .uge-form { display: grid; gap: 20px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 1100px) { .uge-form { grid-template-columns: minmax(0, 1fr) 340px; } }
    .uge-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; }
    .uge-card h3 { font-size: 13px; font-weight: 700; color: #111827; margin: 0 0 12px; }
    .uge-field { margin-bottom: 14px; }
    .uge-field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
    .uge-field small { display: block; font-size: 11px; color: #6b7280; margin-top: 4px; line-height: 1.5; }
    .uge-in { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 8px 10px; font-size: 14px; background: #fff; color: #111827; }
    .uge-in:focus { outline: 2px solid var(--uge-ring); border-color: var(--uge-accent); }
    .uge-in[readonly] { background: #f3f4f6; color: #6b7280; }
    textarea.uge-in { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; line-height: 1.55; min-height: 460px; resize: vertical; }
    .uge-count { display: inline-block; min-width: 20px; padding: 0 6px; margin-left: 6px; border-radius: 999px; background: #f3f4f6; color: #6b7280; font-size: 11px; font-weight: 700; text-align: center; }
    .uge-count.on { background: var(--uge-accent); color: #fff; }
    .uge-picker-q { margin-bottom: 6px; }
    .uge-picker-list { max-height: 190px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 4px; background: #fff; scrollbar-width: thin; }
    .uge-picker-list.is-single { max-height: none; overflow: visible; }
    .uge-opt { display: flex; align-items: flex-start; gap: 8px; padding: 6px 8px; border-radius: 6px; cursor: pointer; font-size: 13px; color: #1f2937; margin: 0; font-weight: 400; }
    .uge-opt:hover { background: #f9fafb; }
    .uge-opt:has(input:checked) { background: var(--uge-tint); color: var(--uge-accent-dark); font-weight: 600; }
    .uge-opt input, .uge-check input { accent-color: var(--uge-accent); margin-top: 3px; flex-shrink: 0; width: 15px; height: 15px; }
    .uge-opt em { display: block; font-style: normal; font-size: 11px; color: #9ca3af; font-weight: 400; }
    .uge-none { padding: 10px; font-size: 12px; color: #9ca3af; text-align: center; }
    .uge-err { color: #b91c1c; font-size: 12px; margin-top: 4px; }
    .uge-errbox { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
    .uge-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.55; }
    .uge-info { background: var(--uge-tint); border: 1px solid var(--uge-ring); color: var(--uge-accent-dark); border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.55; }
    .uge-tabs { display: inline-flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 8px; }
    .uge-tabs button { padding: 6px 14px; font-size: 12px; background: #fff; color: #4b5563; border: 0; cursor: pointer; }
    .uge-tabs button.is-on { background: var(--uge-accent); color: #fff; font-weight: 600; }
    .uge-editor { position: relative; }
    .uge-toolbar { position: sticky; top: 0; z-index: 20; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; padding: 6px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px 8px 0 0; }
    .uge-toolbar[hidden], .uge-tablebar[hidden] { display: none; }
    .uge-toolbar button, .uge-tablebar button { display: inline-flex; align-items: center; gap: 6px; min-width: 32px; height: 32px; padding: 0 8px; justify-content: center; border: 1px solid transparent; border-radius: 6px; background: transparent; color: #374151; font-size: 12px; font-weight: 600; cursor: pointer; }
    .uge-toolbar button:hover, .uge-tablebar button:hover { background: #fff; border-color: #d1d5db; }
    .uge-toolbar button.is-on { background: var(--uge-tint); color: var(--uge-accent-dark); border-color: var(--uge-ring); }
    .uge-toolbar button:focus-visible, .uge-toolbar select:focus-visible, .uge-tablebar button:focus-visible { outline: 2px solid var(--uge-accent); outline-offset: 1px; }
    .uge-toolbar select { height: 32px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; font-size: 12px; padding: 0 6px; color: #374151; }
    .uge-sep { width: 1px; align-self: stretch; margin: 4px 4px; background: #e5e7eb; }
    .uge-pop { position: absolute; top: 100%; z-index: 30; margin-top: 4px; min-width: 220px; max-width: min(340px, 92vw); padding: 8px; background: #fff; border: 1px solid #d1d5db; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,.12); }
    .uge-pop[hidden] { display: none; }
    .uge-pop .uge-pop-item { display: flex; width: 100%; justify-content: flex-start; height: auto; padding: 6px 8px; text-align: left; font-weight: 500; }
    .uge-pop .uge-dot { width: 10px; height: 10px; border-radius: 999px; flex: none; }
    .uge-pop label { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; margin: 0 8px 6px 0; }
    .uge-pop input[type=number] { width: 56px; border: 1px solid #d1d5db; border-radius: 6px; padding: 3px 6px; font-size: 13px; }
    .uge-pop ul { list-style: none; margin: 6px 0 0; padding: 0; max-height: 220px; overflow-y: auto; }
    .uge-pop ul button { display: block; width: 100%; height: auto; padding: 6px 8px; text-align: left; font-weight: 500; }
    .uge-pop ul small { display: block; font-size: 11px; color: #9ca3af; font-weight: 400; }
    .uge-tablebar { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px 6px; background: var(--uge-tint); border: 1px solid #e5e7eb; border-top: 0; }
    .uge-tablebar span { align-self: center; font-size: 11px; font-weight: 700; color: var(--uge-accent-dark); margin-right: 4px; }
    .uge-visual { margin: 0; min-height: 460px; padding: 16px 20px; border: 1px solid #e5e7eb; border-top: 0; border-radius: 0 0 8px 8px; background: #fff; outline: none; cursor: text; }
    .uge-visual:focus { box-shadow: 0 0 0 2px var(--uge-ring); }
    .uge-visual[hidden], .uge-preview[hidden] { display: none; }
    .uge-visual .callout-title { user-select: none; cursor: default; }
    .uge-visual td, .uge-visual th { outline: 1px dashed #e5e7eb; min-width: 3rem; }
    .uge-visual a { cursor: text; }
    .uge-preview { margin: 0; min-height: 460px; padding: 16px 20px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
    .uge-status { min-height: 18px; margin-top: 6px; font-size: 12px; color: var(--uge-accent-dark); }
    .uge-hint { font-size: 11px; color: #6b7280; margin-top: 4px; line-height: 1.5; }
    @media (max-width: 640px) { .uge-visual, .uge-preview { padding: 12px; } }
    .uge-btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 600; border: 0; cursor: pointer; text-decoration: none; }
    .uge-btn.primary { background: var(--uge-accent); color: #fff; } .uge-btn.primary:hover { background: var(--uge-accent-dark); }
    .uge-btn.ghost { background: #fff; color: #374151; border: 1px solid #d1d5db; }
    .uge-btn.danger { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
    .uge-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .uge-check { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; color: #374151; }
    .uge-help { font-size: 12px; color: #4b5563; line-height: 1.7; }
    .uge-help code { background: #f3f4f6; border-radius: 4px; padding: 1px 5px; }
</style>
@endpush

@section('content')
<div class="uge-scope">
    @if ($errors->any())
        <div class="uge-errbox"><strong>Not saved.</strong> Fix the points below and try again.</div>
    @endif
    @if ($topic['stale'])
        <div class="uge-warn">The bundled version of this topic has changed since you edited it. Your edited version is what readers see. Review the bundled file, or use <strong>Restore default</strong> to adopt it.</div>
    @endif
    @if ($topic['borrowed'])
        <div class="uge-info">There is no {{ $localeLabel }} version yet. The text below is the English one, as a starting point to translate. Nothing changes until you save.</div>
    @endif

    <div class="uge-form">
        <form method="POST" id="uge-main"
              action="{{ $isNew ? route('admin.user-guide.store') : route('admin.user-guide.update', ['key' => $topic['key']]) }}">
            @csrf
            @unless ($isNew) @method('PUT') @endunless
            <input type="hidden" name="locale" value="{{ $locale }}">

            <div class="uge-card">
                <div class="uge-field">
                    <label for="uge-key">Key</label>
                    <input id="uge-key" class="uge-in" name="key" value="{{ old('key', $topic['key']) }}" {{ $isNew ? '' : 'readonly' }} maxlength="100" placeholder="e.g. my-new-topic">
                    @error('key')<div class="uge-err">{{ $message }}</div>@enderror
                    @if ($isNew)<small>Lowercase letters, digits and hyphens. Used in links like [[my-new-topic]] and cannot be changed later.</small>@endif
                </div>
                <div class="uge-field">
                    <label for="uge-title">Title</label>
                    <input id="uge-title" class="uge-in" name="title" value="{{ old('title', $topic['title']) }}" maxlength="200" required>
                    @error('title')<div class="uge-err">{{ $message }}</div>@enderror
                </div>
                <div class="uge-field">
                    <label for="uge-summary">Summary</label>
                    <input id="uge-summary" class="uge-in" name="summary" value="{{ old('summary', $topic['summary']) }}" maxlength="500">
                    <small>One sentence shown under the title.</small>
                    @error('summary')<div class="uge-err">{{ $message }}</div>@enderror
                </div>

                <div class="uge-field">
                    <label id="uge-content-label">Content</label>
                    <div class="uge-tabs" role="tablist" aria-labelledby="uge-content-label">
                        <button type="button" role="tab" class="is-on" data-pane="visual" aria-selected="true">Visual</button>
                        <button type="button" role="tab" data-pane="markdown" aria-selected="false">Markdown</button>
                        <button type="button" role="tab" data-pane="preview" aria-selected="false">Preview</button>
                    </div>
                    <div class="uge-editor">
                        <div class="uge-toolbar" id="uge-toolbar" role="toolbar" aria-label="Text formatting">
                            <select data-cmd="block" aria-label="Paragraph style" title="Paragraph style">
                                <option value="p">Paragraph</option>
                                <option value="h2">Heading</option>
                                <option value="h3">Subheading</option>
                            </select>
                            <span class="uge-sep"></span>
                            <button type="button" data-cmd="bold" title="Bold (Ctrl+B)" aria-label="Bold"><i class="fas fa-bold"></i></button>
                            <button type="button" data-cmd="italic" title="Italic (Ctrl+I)" aria-label="Italic"><i class="fas fa-italic"></i></button>
                            <button type="button" data-cmd="strike" title="Strikethrough" aria-label="Strikethrough"><i class="fas fa-strikethrough"></i></button>
                            <button type="button" data-cmd="code" title="Screen name: a menu, button or field name exactly as shown on screen" aria-label="Screen name"><i class="fas fa-code"></i> Screen name</button>
                            <span class="uge-sep"></span>
                            <button type="button" data-cmd="ul" title="Bulleted list" aria-label="Bulleted list"><i class="fas fa-list-ul"></i></button>
                            <button type="button" data-cmd="ol" title="Numbered list" aria-label="Numbered list"><i class="fas fa-list-ol"></i></button>
                            <button type="button" data-cmd="outdent" title="Decrease indent (Shift+Tab in a list)" aria-label="Decrease indent"><i class="fas fa-outdent"></i></button>
                            <button type="button" data-cmd="indent" title="Increase indent (Tab in a list)" aria-label="Increase indent"><i class="fas fa-indent"></i></button>
                            <span class="uge-sep"></span>
                            <button type="button" data-pop="uge-pop-callout" title="Call-out box (Note, Tip, Important, Warning)" aria-label="Call-out box"><i class="fas fa-sticky-note"></i> Call-out</button>
                            <button type="button" data-pop="uge-pop-table" title="Insert a table" aria-label="Insert table"><i class="fas fa-table"></i> Table</button>
                            <button type="button" data-pop="uge-pop-topics" title="Link to another topic" aria-label="Link to topic"><i class="fas fa-link"></i> Topic link</button>
                            <button type="button" data-cmd="unlink" title="Remove link" aria-label="Remove link"><i class="fas fa-unlink"></i></button>
                            <span class="uge-sep"></span>
                            <button type="button" data-cmd="clear" title="Clear formatting" aria-label="Clear formatting"><i class="fas fa-eraser"></i></button>
                            <button type="button" data-cmd="undo" title="Undo (Ctrl+Z)" aria-label="Undo"><i class="fas fa-undo"></i></button>
                            <button type="button" data-cmd="redo" title="Redo (Ctrl+Y)" aria-label="Redo"><i class="fas fa-redo"></i></button>

                            <div class="uge-pop" id="uge-pop-callout" hidden>
                                <button type="button" class="uge-pop-item" data-callout="note"><span class="uge-dot" style="background:#3b82f6"></span> Note</button>
                                <button type="button" class="uge-pop-item" data-callout="tip"><span class="uge-dot" style="background:#10b981"></span> Tip</button>
                                <button type="button" class="uge-pop-item" data-callout="important"><span class="uge-dot" style="background:#f59e0b"></span> Important</button>
                                <button type="button" class="uge-pop-item" data-callout="warning"><span class="uge-dot" style="background:#ef4444"></span> Warning</button>
                                <button type="button" class="uge-pop-item" data-callout="remove"><i class="fas fa-times" style="width:10px"></i> Remove call-out</button>
                            </div>
                            <div class="uge-pop" id="uge-pop-table" hidden>
                                <label>Rows <input type="number" name="rows" value="3" min="2" max="30"></label>
                                <label>Columns <input type="number" name="cols" value="3" min="1" max="8"></label>
                                <button type="button" class="uge-pop-item" data-table-new><i class="fas fa-plus" style="width:10px"></i> Insert table</button>
                            </div>
                            <div class="uge-pop" id="uge-pop-topics" hidden>
                                <input type="search" class="uge-in" placeholder="Search topics..." autocomplete="off" aria-label="Search topics">
                                <ul></ul>
                                <div class="uge-none" hidden>No match.</div>
                            </div>
                        </div>
                        <div class="uge-tablebar" id="uge-tablebar" hidden>
                            <span>Table</span>
                            <button type="button" data-cell="row-add">+ Row</button>
                            <button type="button" data-cell="col-add">+ Column</button>
                            <button type="button" data-cell="row-del">- Row</button>
                            <button type="button" data-cell="col-del">- Column</button>
                            <button type="button" data-cell="table-del">Delete table</button>
                        </div>
                        <div id="uge-visual" class="guide-body uge-visual" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Topic content" spellcheck="true">{!! $editorHtml !!}</div>
                        <textarea id="uge-body" class="uge-in" name="body" hidden>{{ old('body', $topic['body']) }}</textarea>
                        <div id="uge-preview" class="guide-body uge-preview" hidden></div>
                        <div class="uge-status" id="uge-status" aria-live="polite"></div>
                    </div>
                    <div class="uge-hint">The Visual tab looks like the guide itself. Ctrl+S saves. Markdown is the same text in plain form, for advanced edits.</div>
                    @error('body')@foreach ($errors->get('body') as $m)<div class="uge-err">{{ $m }}</div>@endforeach @enderror
                </div>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="uge-card">
                <h3>Placement</h3>
                @include('admin.user-guide._picker', ['id' => 'uge-chapter', 'label' => 'Chapter', 'name' => 'chapter', 'single' => true, 'search' => false,
                    'picked' => [old('chapter', $topic['chapter'])],
                    'items' => collect($chapters)->map(fn ($def, $id) => ['value' => $id, 'label' => $def['title'][$locale] ?? $def['title']['en']])->values()->all()])
                @error('chapter')<div class="uge-err" style="margin-top:-8px;margin-bottom:12px">{{ $message }}</div>@enderror
                <div class="uge-field">
                    <label for="uge-order">Order in chapter</label>
                    <input id="uge-order" class="uge-in" type="number" min="0" max="9999" name="order" form="uge-main" value="{{ old('order', $topic['order']) }}">
                    <small>Lower numbers come first.</small>
                    @error('order')<div class="uge-err">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="uge-card">
                <h3>Visible to</h3>
                <small style="display:block;font-size:11px;color:#6b7280;margin-bottom:10px;line-height:1.5">Leave everything unticked to show the topic to all employees. Otherwise an employee needs any one of the ticked choices.</small>
                @include('admin.user-guide._picker', ['id' => 'uge-menus', 'label' => 'Menu access', 'picked' => (array) $pick,
                    'items' => collect($options['menus'])->map(fn ($m) => ['value' => $m->slug, 'label' => $m->name, 'hint' => $m->slug])->all()])
                @include('admin.user-guide._picker', ['id' => 'uge-roles', 'label' => 'Role', 'picked' => (array) $pick,
                    'items' => collect($options['roles'])->map(fn ($r) => ['value' => '@role:' . $r, 'label' => $r])->all()])
                <label class="uge-check">
                    <input type="checkbox" name="requires[]" form="uge-main" value="@approver" @checked($has('@approver'))>
                    <span>Designated approvers</span>
                </label>
                @error('requires')@foreach ($errors->get('requires') as $m)<div class="uge-err">{{ $m }}</div>@endforeach @enderror
            </div>

            <div class="uge-card">
                <div class="uge-actions">
                    <button class="uge-btn primary" type="submit" form="uge-main"><i class="fas fa-save text-xs"></i> Save</button>
                    <a class="uge-btn ghost" href="{{ route('admin.user-guide.index', ['lang' => $locale]) }}">Back</a>
                    @if (!$isNew)
                        <a class="uge-btn ghost" href="{{ route('user-guide.index', ['lang' => $locale]) }}#topic-{{ $topic['key'] }}" target="_blank" rel="noopener">View</a>
                    @endif
                </div>
                @if (!$isNew && $topic['state'] !== 'default')
                    <form id="uge-reset" method="POST" action="{{ route('admin.user-guide.destroy', ['key' => $topic['key']]) }}"
                          style="margin-top:12px" onsubmit="return confirm('{{ $topic['has_file'] ? 'Discard your edits and restore the default version?' : 'Delete this custom topic?' }}')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="locale" value="{{ $locale }}">
                        <button class="uge-btn danger" type="submit">{{ $topic['has_file'] ? 'Restore default' : 'Delete topic' }}</button>
                    </form>
                @endif
            </div>

            <div class="uge-card uge-help">
                <h3>Writing tips</h3>
                Use the toolbar in the Visual tab: <strong>Heading</strong> for sections, <strong>Screen name</strong> for menu and button names exactly as shown on screen, <strong>Call-out</strong> for boxes, <strong>Topic link</strong> to link another topic. In the Markdown tab: <code>[[topic-key]]</code> links a topic, <code>## Heading</code> makes a section and <code>&gt; [!NOTE]</code> a call-out.
                Images are files in <code>public/images/guide</code>: <code>![Alt text](images/guide/name.png)</code>.
                No em dashes (except exact screen text inside <code>`code`</code>), raw HTML or external links. Never put passwords or real customer or employee data in the guide.
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/guide-editor.js') }}?v={{ @filemtime(public_path('js/guide-editor.js')) }}"></script>
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]');
    GuideEditor.init({
        form: document.getElementById('uge-main'),
        visual: document.getElementById('uge-visual'),
        textarea: document.getElementById('uge-body'),
        preview: document.getElementById('uge-preview'),
        toolbar: document.getElementById('uge-toolbar'),
        tablebar: document.getElementById('uge-tablebar'),
        status: document.getElementById('uge-status'),
        tabs: document.querySelectorAll('.uge-tabs button'),
        previewUrl: @json(route('admin.user-guide.preview')),
        csrf: function () { return token ? token.content : ''; },
        key: function () { return document.getElementById('uge-key').value; },
        locale: @json($locale),
        titles: @json($link['titles']),
        topics: @json($link['topics']),
        calloutTitles: @json($calloutTitles)
    });

    document.querySelectorAll('.uge-picker').forEach(function (box) {
        var q = box.querySelector('.uge-picker-q') || document.createElement('input'), none = box.querySelector('.uge-none'), count = box.querySelector('[data-count]');
        var opts = box.querySelectorAll('.uge-opt');
        function tally() { if (!count) { return; } var n = box.querySelectorAll('input:checked').length; count.textContent = n; count.classList.toggle('on', n > 0); }
        q.addEventListener('input', function () {
            var term = q.value.trim().toLowerCase(), shown = 0;
            opts.forEach(function (o) { var hit = !term || o.dataset.text.indexOf(term) !== -1; o.hidden = !hit; if (hit) { shown++; } });
            none.hidden = shown > 0;
        });
        box.addEventListener('change', tally);
        tally();
    });
})();
</script>
@endpush
