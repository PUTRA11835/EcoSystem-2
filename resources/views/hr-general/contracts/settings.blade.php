@extends('dashboard')
@section('title', $external ? 'External Contract — Settings' : 'Contract — Settings')
@section('page-title', $external ? 'External Contract' : 'Contract')
@section('page-subtitle', $external
    ? 'The contract types and position types offered on the external contract form.'
    : 'The contract types and position types offered on the contract form of internal employees.')

@php
    $canCreate = $canDo($slug, 'create');
    $canEdit   = $canDo($slug, 'edit');
    $canDelete = $canDo($slug, 'delete');
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-300 bg-white';
    $label = 'block text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1';
    $reopen = session('open_add');
    $modalIds = ['addTypeModal', 'addPositionModal', 'addSeveralModal'];

    $defaultType = $contractTypes->firstWhere('is_default', true);
    $defaultPosition = $positionTypes->firstWhere('is_default', true);
    $tiles = [
        ['Contract types', $contractTypes->where('is_active', true)->count() . ' on', 'of ' . $contractTypes->count() . ' in total', 'file-signature', 'bg-indigo-50 text-indigo-600'],
        ['Position types', $positionTypes->where('is_active', true)->count() . ' on', 'of ' . $positionTypes->count() . ' in total', 'id-badge', 'bg-sky-50 text-sky-600'],
        ['Default contract type', $defaultType?->label ?? 'None set', 'preselected on a new contract', 'star', 'bg-amber-50 text-amber-600'],
        ['Default position type', $defaultPosition?->label ?? 'None set', 'preselected on a new contract', 'star', 'bg-amber-50 text-amber-600'],
    ];
@endphp

@section('content')
<div class="w-full space-y-5 px-1 lg:px-2">
    @if($external)
        @include('hr-general.external.contracts.components.tabs')
        @include('partials.accent-remap')
        @include('hr-general.contracts.components.confirm-forms')
    @else
        @include('hr-general.contracts.components.tabs')
    @endif
    @include('hr-general.recruitment.components.form-errors')
    @error('option')
        <div class="flex items-start gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3" role="alert">
            <i class="fas fa-circle-exclamation mt-0.5"></i><span>{{ $message }}</span>
        </div>
    @enderror

    {{-- At a glance --}}
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach($tiles as [$name, $value, $note, $icon, $tone])
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3.5 flex items-center gap-3 min-w-0">
                <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $tone }}"><i class="fas fa-{{ $icon }}"></i></span>
                <div class="min-w-0">
                    <p class="text-[11px] text-gray-500">{{ $name }}</p>
                    <p class="text-sm font-bold text-gray-900 truncate" title="{{ $value }}">{{ $value }}</p>
                    <p class="text-[11px] text-gray-400 truncate">{{ $note }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- How it works: out of the way once read --}}
    <details class="group bg-white rounded-xl border border-gray-200 shadow-sm">
        <summary class="flex items-center justify-between gap-3 px-5 py-3 cursor-pointer select-none list-none">
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-800"><i class="fas fa-circle-info text-sky-500"></i> How these lists are used</span>
            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform group-open:rotate-180"></i>
        </summary>
        <div class="px-5 pb-4 pt-1 grid md:grid-cols-3 gap-4 text-sm text-gray-600 border-t border-gray-100">
            <div class="pt-3">
                <p class="font-semibold text-gray-800 mb-1">Contract types</p>
                <p>What the form asks first. A <strong>built-in</strong> type is the engine's, so its numbering and date rules follow it: rename it, reorder it, switch it off. A <strong>custom</strong> type is an alias of a built-in one — “Probation” that behaves like PKWT.</p>
            </div>
            <div class="pt-3">
                <p class="font-semibold text-gray-800 mb-1">Position types</p>
                <p>The kind of position a contract is for. A template written for a position type is picked automatically for contracts of that type.</p>
            </div>
            <div class="pt-3">
                <p class="font-semibold text-gray-800 mb-1">Default, order and deleting</p>
                <p>The <i class="fas fa-star text-amber-400 text-xs"></i> default is preselected on a new contract; the arrows set the dropdown order. An option contracts or templates hold can't be deleted — switch it off and old contracts keep it.</p>
            </div>
        </div>
    </details>
    <div class="flex xl:flex-row flex-col gap-3 mt-6 px-1 lg:px-2">
    @include('hr-general.contracts.components.settings-options', [
        'listId' => 'ctList', 'kind' => 'contract_type', 'rows' => $contractTypes, 'usage' => $usage, 'routes' => $routes,
        'baseLabels' => $baseLabels, 'canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete,
        'title' => 'Contract types',
        'hint' => $external ? 'How a consultant is paid. The terms of the contract (rate unit, hours per manday, proration) follow the built-in type.' : 'Fixed-term, indefinite-term, internship, addendum — and any alias you add.',
        'addModal' => 'addTypeModal', 'addLabel' => 'Add contract type',
    ])

    @include('hr-general.contracts.components.settings-options', [
        'listId' => 'ptList', 'kind' => 'position_type', 'rows' => $positionTypes, 'usage' => $usage, 'routes' => $routes,
        'baseLabels' => $baseLabels, 'canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete,
        'title' => 'Position types',
        'hint' => 'The kind of position a contract is for' . ($external ? ' (for example Consultant).' : ' (for example Sales, Management Trainee or Internship).'),
        'addModal' => 'addPositionModal', 'addLabel' => 'Add position type', 'bulkModal' => 'addSeveralModal',
    ])
    </div>

    @unless($canEdit)
        <p class="text-xs text-gray-500"><i class="fas fa-lock mr-1"></i> You can view these lists. Changing them needs the Edit permission of this tab.</p>
    @endunless
</div>

@if($canCreate)
    {{-- Add a custom contract type --}}
    <div id="addTypeModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="addTypeTitle">
        <form method="POST" action="{{ route($routes['store']) }}" class="bg-white rounded-xl shadow-xl w-full max-w-md" data-unsaved-ignore>@csrf
            <input type="hidden" name="kind" value="contract_type">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <div>
                    <h3 id="addTypeTitle" class="text-sm font-bold text-gray-900">Add a contract type</h3>
                    <p class="text-xs text-gray-500 mt-0.5">It behaves like the built-in type you pick: same numbering, date rules and templates.</p>
                </div>
                <button type="button" data-close class="text-gray-400 hover:text-gray-600 shrink-0" aria-label="Close"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div><label class="{{ $label }}" for="typeName">Name <span class="text-red-500">*</span></label>
                    <input id="typeName" name="name" maxlength="100" required value="{{ $reopen === 'contract_type' ? old('name') : '' }}" class="{{ $input }}" placeholder="e.g. Probation"></div>
                <div><label class="{{ $label }}" for="typeBase">Behaves like <span class="text-red-500">*</span></label>
                    <select id="typeBase" name="base" required data-searchable="true" data-search-placeholder="Search…" class="{{ $input }}">
                        @foreach($baseChoices as $code => $name)<option value="{{ $code }}" @selected($reopen === 'contract_type' && old('base') === $code)>{{ $name }}</option>@endforeach
                    </select></div>
                <div><label class="{{ $label }}" for="typeDesc">Description</label>
                    <input id="typeDesc" name="description" maxlength="255" value="{{ $reopen === 'contract_type' ? old('description') : '' }}" class="{{ $input }}" placeholder="Optional hint for the person choosing"></div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end gap-2">
                <button type="button" data-close class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Add contract type</button>
            </div>
        </form>
    </div>

    {{-- Add a position type --}}
    <div id="addPositionModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="addPositionTitle">
        <form method="POST" action="{{ route($routes['store']) }}" class="bg-white rounded-xl shadow-xl w-full max-w-md" data-unsaved-ignore>@csrf
            <input type="hidden" name="kind" value="position_type">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <h3 id="addPositionTitle" class="text-sm font-bold text-gray-900">Add a position type</h3>
                <button type="button" data-close class="text-gray-400 hover:text-gray-600 shrink-0" aria-label="Close"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div><label class="{{ $label }}" for="posName">Name <span class="text-red-500">*</span></label>
                    <input id="posName" name="name" maxlength="100" required value="{{ $reopen === 'position_type' ? old('name') : '' }}" class="{{ $input }}" placeholder="e.g. Management Trainee"></div>
                <div><label class="{{ $label }}" for="posDesc">Description</label>
                    <input id="posDesc" name="description" maxlength="255" value="{{ $reopen === 'position_type' ? old('description') : '' }}" class="{{ $input }}" placeholder="Optional hint"></div>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end gap-2">
                <button type="button" data-close class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Add position type</button>
            </div>
        </form>
    </div>

    {{-- Add several position types --}}
    <div id="addSeveralModal" class="hidden fixed inset-0 bg-black/40 z-50 items-center justify-center p-4" style="display:none" role="dialog" aria-modal="true" aria-labelledby="addSeveralTitle">
        <form method="POST" action="{{ route($routes['store']) }}" class="bg-white rounded-xl shadow-xl w-full max-w-md" data-unsaved-ignore>@csrf
            <input type="hidden" name="kind" value="position_bulk">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                <div>
                    <h3 id="addSeveralTitle" class="text-sm font-bold text-gray-900">Add several position types</h3>
                    <p class="text-xs text-gray-500 mt-0.5">One name per line. Names already in the list are skipped.</p>
                </div>
                <button type="button" data-close class="text-gray-400 hover:text-gray-600 shrink-0" aria-label="Close"><i class="fas fa-xmark"></i></button>
            </div>
            <div class="px-5 py-4">
                <label class="sr-only" for="posNames">Position type names, one per line</label>
                <textarea id="posNames" name="names" rows="7" required maxlength="5000" class="{{ $input }}" placeholder="Sales&#10;Office&#10;Management Trainee">{{ $reopen === 'position_bulk' ? old('names') : '' }}</textarea>
            </div>
            <div class="px-5 py-3 border-t border-gray-100 bg-gray-50 rounded-b-xl flex justify-end gap-2">
                <button type="button" data-close class="px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Add all</button>
            </div>
        </form>
    </div>
@endif

@push('scripts')
<script>
(function () {
    const MODALS = @json($modalIds);
    const show = el => { el.style.display = 'flex'; el.classList.remove('hidden'); };
    const hide = el => { el.style.display = 'none'; el.classList.add('hidden'); };
    const modals = MODALS.map(id => document.getElementById(id)).filter(Boolean);
    let opener = null;

    // Add dialogs: open, focus the first field, close on Esc / backdrop / buttons, give focus back.
    document.querySelectorAll('[data-open]').forEach(b => b.addEventListener('click', () => {
        const m = document.getElementById(b.dataset.open);
        if (!m) return;
        opener = b;
        show(m);
        m.querySelector('input:not([type=hidden]), textarea')?.focus();
    }));
    modals.forEach(m => m.addEventListener('click', e => {
        if (e.target === m || e.target.closest('[data-close]')) { hide(m); opener?.focus(); }
    }));
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modals.some(m => m.style.display === 'flex')) { modals.forEach(hide); opener?.focus(); }
    });

    // A refused add reopens its dialog with what was typed.
    @if($reopen)
        const reopen = { contract_type: 'addTypeModal', position_type: 'addPositionModal', position_bulk: 'addSeveralModal' }[@json($reopen)];
        const reopenModal = reopen && document.getElementById(reopen);
        if (reopenModal) show(reopenModal);
    @endif

    document.querySelectorAll('[data-list-body]').forEach(body => {
        const card = body.closest('section');
        const search = card.querySelector('[data-list-search]');
        const filters = [...card.querySelectorAll('[data-list-filter]')];
        const empty = body.querySelector('[data-list-empty]');
        let state = '';

        // Search + On / Off filter
        function apply() {
            const q = (search.value || '').trim().toLowerCase();
            let visible = 0;
            body.querySelectorAll('[data-row]').forEach(r => {
                const ok = (!q || r.dataset.text.includes(q)) && (!state || r.dataset.active === state);
                r.classList.toggle('hidden', !ok);
                if (ok) visible++;
            });
            if (empty) empty.classList.toggle('hidden', visible > 0 || !body.querySelector('[data-row]'));
        }
        search.addEventListener('input', apply);
        filters.forEach(b => b.addEventListener('click', () => {
            state = b.dataset.listFilter;
            filters.forEach(x => x.setAttribute('aria-pressed', String(x === b)));
            apply();
        }));

        // Only one default per list: ticking one clears the others (the server keeps it to one as well).
        body.addEventListener('change', e => {
            if (!e.target.matches('[data-default]') || !e.target.checked) return;
            body.querySelectorAll('[data-default]').forEach(c => { if (c !== e.target) c.checked = false; refresh(c.closest('tr')); });
        });

        // Save is only available for a row that really changed.
        function refresh(row) {
            if (!row) return;
            const fields = row.querySelectorAll('input[form]:not([type=hidden])');
            const dirty = [...fields].some(f => f.type === 'checkbox' ? f.checked !== f.defaultChecked : f.value !== f.defaultValue);
            const save = row.querySelector('[data-save]');
            if (save) save.disabled = !dirty;
            row.classList.toggle('bg-amber-50/40', dirty);
        }
        body.addEventListener('input', e => refresh(e.target.closest('tr[data-row]')));
        body.addEventListener('change', e => refresh(e.target.closest('tr[data-row]')));
    });
})();
</script>
@endpush
@endsection
