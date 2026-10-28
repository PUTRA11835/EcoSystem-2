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
    .uge-opt:has(input:checked) { background: var(--uge-tint); color: #7f1d1d; font-weight: 600; }
    .uge-opt input, .uge-check input { accent-color: var(--uge-accent); margin-top: 3px; flex-shrink: 0; width: 15px; height: 15px; }
    .uge-opt em { display: block; font-style: normal; font-size: 11px; color: #9ca3af; font-weight: 400; }
    .uge-none { padding: 10px; font-size: 12px; color: #9ca3af; text-align: center; }
    .uge-err { color: #b91c1c; font-size: 12px; margin-top: 4px; }
    .uge-errbox { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
    .uge-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.55; }
    .uge-info { background: var(--uge-tint); border: 1px solid #fecaca; color: #7f1d1d; border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; line-height: 1.55; }
    .uge-tabs { display: inline-flex; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 8px; }
    .uge-tabs button { padding: 6px 14px; font-size: 12px; background: #fff; color: #4b5563; border: 0; cursor: pointer; }
    .uge-tabs button.is-on { background: var(--uge-accent); color: #fff; font-weight: 600; }
    .uge-preview { border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; min-height: 460px; background: #fff; }
    .uge-preview img { max-width: 100%; height: auto; border: 1px solid #e5e7eb; border-radius: 8px; }
    .uge-preview h2 { font-size: 18px; font-weight: 700; margin: 18px 0 8px; }
    .uge-preview h3 { font-size: 15px; font-weight: 700; margin: 14px 0 6px; }
    .uge-preview p, .uge-preview li { font-size: 14px; line-height: 1.65; }
    .uge-preview ul { list-style: disc; padding-left: 22px; } .uge-preview ol { list-style: decimal; padding-left: 22px; }
    .uge-preview table { border-collapse: collapse; } .uge-preview th, .uge-preview td { border: 1px solid #e5e7eb; padding: 4px 8px; font-size: 13px; }
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
                    <label>Content (Markdown)</label>
                    <div class="uge-tabs" role="tablist">
                        <button type="button" class="is-on" data-pane="write">Write</button>
                        <button type="button" data-pane="preview">Preview</button>
                    </div>
                    <textarea id="uge-body" class="uge-in" name="body" required>{{ old('body', $topic['body']) }}</textarea>
                    <div id="uge-preview" class="uge-preview" hidden></div>
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
                Link topics with <code>[[topic-key]]</code>. Use <code>## Heading</code> for sections and <code>&gt; [!NOTE]</code> for call-outs.
                Images are files in <code>public/images/guide</code>: <code>![Alt text](images/guide/name.png)</code>.
                No em dashes, raw HTML or external links. Never put passwords or real customer or employee data in the guide.
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.getElementById('uge-body');
    var box = document.getElementById('uge-preview');
    var tabs = document.querySelectorAll('.uge-tabs button');
    var token = document.querySelector('meta[name="csrf-token"]');

    function show(pane) {
        tabs.forEach(function (b) { b.classList.toggle('is-on', b.dataset.pane === pane); });
        body.hidden = pane === 'preview';
        box.hidden = pane !== 'preview';
        if (pane !== 'preview') { return; }
        box.textContent = 'Loading...';
        fetch(@json(route('admin.user-guide.preview')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
            body: JSON.stringify({ body: body.value, key: document.getElementById('uge-key').value, locale: @json($locale) })
        }).then(function (r) { return r.json(); }).then(function (j) {
            box.innerHTML = j.html || '<em>Nothing to preview.</em>';
        }).catch(function () { box.textContent = 'Preview failed. Try again.'; });
    }
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
    tabs.forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.pane); }); });
})();
</script>
@endpush
