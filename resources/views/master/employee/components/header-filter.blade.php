{{--
    Table header cell with its filter behind the small funnel icon — the same floating-popover look as the
    KPI Evaluation, Leave & Permit and Recruitment tables, for a table that is filled by JavaScript.

    The state lives in `window.EMP_F` (one key per filter). The page reads it through getCurrentFilters() and
    refetches through applyFilters(); this component only draws the popovers and keeps them in sync.

    Parameters:
      $key          — filter key in EMP_F (and the query parameter name)
      $label        — column title
      $type         — 'search' | 'options' | 'checkboxes'
      $options      — [value => label] for 'options' / 'checkboxes' (checkboxes may also be filled later with
                      ehfSetOptions('<key>', [...]) when the list comes from the API)
      $allLabel     — 'options': label of the "no filter" row
      $placeholder  — 'search' placeholder
      $thClass / $thStyle — extra class / style for the <th>
--}}
@php
    $type = $type ?? 'search';
    $options = $options ?? [];
    $popoverId = 'ehf-' . $key;
    $optionRow = 'w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors';
@endphp
<th class="text-left px-4 py-3.5 text-xs font-semibold text-gray-700 uppercase tracking-wider border-b border-gray-200 {{ $thClass ?? '' }}" @if(!empty($thStyle)) style="{{ $thStyle }}" @endif>
    <div class="flex items-center justify-between gap-1.5">
        <span>{{ $label }}</span>
        <button type="button" data-ehf-btn="{{ $key }}" onclick="ehfToggle(event, '{{ $key }}')"
            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all text-gray-400 hover:text-gray-600"
            title="Filter {{ $label }}" aria-label="Filter {{ $label }}" aria-haspopup="true">
            <i class="fas fa-filter text-[10px]"></i>
            <span data-ehf-dot class="hidden absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full ring-2 ring-white" style="background: var(--primary-color);"></span>
        </button>
    </div>

    <div id="{{ $popoverId }}" data-ehf-type="{{ $type }}"
        class="header-filter-popover hidden {{ $type === 'checkboxes' ? 'w-64' : ($type === 'search' ? 'w-60' : 'w-48') }} bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden text-left normal-case font-normal"
        onclick="event.stopPropagation()">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · {{ $label }}</span>
            <button type="button" data-ehf-clear onclick="ehfClear('{{ $key }}')" class="hidden text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
        </div>

        @if($type === 'search')
            <div class="p-2.5">
                <div class="relative">
                    <input type="text" data-ehf-input autocomplete="off" placeholder="{{ $placeholder ?? 'Type to search…' }}" aria-label="Filter {{ $label }}"
                        oninput="ehfSearch('{{ $key }}', this.value)"
                        onkeydown="if (event.key === 'Enter') { event.preventDefault(); ehfSearch('{{ $key }}', this.value, 0); }"
                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-200 transition-all font-normal">
                    <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <p class="text-[10px] text-gray-400 mt-1.5">Results update as you type.</p>
            </div>
        @elseif($type === 'options')
            <div class="py-1 max-h-64 overflow-y-auto" data-ehf-options>
                @foreach(['' => $allLabel ?? 'All'] + $options as $optionValue => $optionLabel)
                    <button type="button" data-value="{{ $optionValue }}" onclick="ehfPick('{{ $key }}', this.dataset.value)" class="{{ $optionRow }} text-gray-700">
                        <span class="truncate">{{ $optionLabel }}</span>
                        <i class="fas fa-check text-[10px] shrink-0 hidden" data-ehf-check></i>
                    </button>
                @endforeach
            </div>
        @else
            <div class="p-2 border-b border-gray-100" data-ehf-searchbox @if(count($options) <= 7 && empty($dynamic)) hidden @endif>
                <div class="relative">
                    <input type="text" autocomplete="off" placeholder="Search…" aria-label="Search {{ $label }} options" oninput="ehfFilterOptions('{{ $key }}', this.value)"
                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-200 font-normal">
                    <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
            </div>
            <div class="py-1.5 max-h-64 overflow-y-auto" data-ehf-options>
                @foreach($options as $optionValue => $optionLabel)
                    <label class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer" data-ehf-item>
                        <input type="checkbox" value="{{ $optionValue }}" onchange="ehfCheck('{{ $key }}')" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="truncate">{{ $optionLabel }}</span>
                    </label>
                @endforeach
            </div>
            <p class="hidden px-3 pb-2 text-xs text-gray-400" data-ehf-empty>No match.</p>
        @endif
    </div>
</th>

@once
@push('scripts')
<script>
    // ── Header filters for the Employee table (state in window.EMP_F) ──
    window.EMP_F = { status: '', employee: '', department: [], modules: [], home_base: [], position: [], employee_group: [], division: [], lock: '' };
    let _ehfOpen = null, _ehfTimer = null;

    const ehfPop = key => document.getElementById('ehf-' + key);
    const ehfIsActive = key => Array.isArray(EMP_F[key]) ? EMP_F[key].length > 0 : EMP_F[key] !== '';

    function ehfToggle(e, key) {
        e.stopPropagation();
        const pop = ehfPop(key), btn = e.currentTarget;
        const wasHidden = pop.classList.contains('hidden');
        ehfCloseAll();
        if (!wasHidden) return;
        pop.classList.remove('hidden');
        // Fixed positioning: the table scrolls inside an overflow container that would clip the popover.
        pop.style.cssText = 'position:fixed;margin:0;z-index:9999;top:-9999px;left:-9999px;';
        const pw = pop.offsetWidth || 220, ph = pop.offsetHeight || 200, r = btn.getBoundingClientRect();
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        let top = r.bottom + 4;
        if (top + ph > vh - 8 && r.top - ph - 4 > 8) top = r.top - ph - 4;
        pop.style.left = Math.min(Math.max(8, r.right - pw), vw - pw - 8) + 'px';
        pop.style.top = Math.max(8, Math.min(top, vh - ph - 8)) + 'px';
        _ehfOpen = { btn, pop };
        const input = pop.querySelector('input[type="text"]');
        if (input) setTimeout(() => { input.focus(); input.select(); }, 50);
    }

    function ehfCloseAll() {
        document.querySelectorAll('[data-ehf-type]').forEach(p => { p.classList.add('hidden'); p.style.cssText = ''; });
        _ehfOpen = null;
    }

    function ehfRun(delay) {
        clearTimeout(_ehfTimer);
        _ehfTimer = setTimeout(() => { if (typeof applyFilters === 'function') applyFilters(); }, delay);
    }

    // Funnel colour + dot + "Clear" link follow the state; option check marks follow it too.
    function ehfMark(key) {
        const active = ehfIsActive(key), pop = ehfPop(key);
        const btn = document.querySelector('[data-ehf-btn="' + key + '"]');
        if (!pop || !btn) return;
        btn.style.color = active ? 'var(--primary-color)' : '';
        btn.classList.toggle('text-gray-400', !active);
        btn.querySelector('[data-ehf-dot]').classList.toggle('hidden', !active);
        pop.querySelector('[data-ehf-clear]').classList.toggle('hidden', !active);
        if (pop.dataset.ehfType === 'options') {
            pop.querySelectorAll('[data-value]').forEach(b => {
                const on = b.dataset.value === EMP_F[key];
                b.classList.toggle('font-semibold', on);
                b.classList.toggle('bg-gray-50', on);
                b.style.color = on ? 'var(--primary-color)' : '';
                b.querySelector('[data-ehf-check]').classList.toggle('hidden', !on);
            });
        }
    }

    function ehfSearch(key, value, delay = 400) { EMP_F[key] = value.trim(); ehfMark(key); ehfRun(delay); }
    function ehfPick(key, value) { EMP_F[key] = value; ehfMark(key); ehfCloseAll(); ehfRun(0); }
    function ehfCheck(key) {
        EMP_F[key] = Array.from(ehfPop(key).querySelectorAll('input[type="checkbox"]:checked')).map(c => c.value);
        ehfMark(key); ehfRun(250);
    }

    function ehfClear(key) {
        EMP_F[key] = Array.isArray(EMP_F[key]) ? [] : '';
        ehfSync(key); ehfRun(0);
    }

    function ehfResetAll() {
        Object.keys(EMP_F).forEach(k => { EMP_F[k] = Array.isArray(EMP_F[k]) ? [] : ''; ehfSync(k); });
    }

    // Draw one popover from EMP_F (used by Clear, Reset and when a saved filter is restored).
    function ehfSync(key) {
        const pop = ehfPop(key);
        if (!pop) return;
        const input = pop.querySelector('[data-ehf-input]');
        if (input) input.value = EMP_F[key];
        pop.querySelectorAll('input[type="checkbox"]').forEach(c => { c.checked = EMP_F[key].includes(c.value); });
        ehfMark(key);
    }
    const ehfSyncAll = () => Object.keys(EMP_F).forEach(ehfSync);

    // Options that come from the API (e.g. modules).
    function ehfSetOptions(key, values) {
        const box = ehfPop(key).querySelector('[data-ehf-options]');
        box.textContent = '';
        values.forEach(v => {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer';
            label.setAttribute('data-ehf-item', '');
            const cb = document.createElement('input');
            cb.type = 'checkbox'; cb.value = v; cb.className = 'rounded border-gray-300 text-indigo-600 focus:ring-indigo-500';
            cb.checked = EMP_F[key].includes(v);
            cb.addEventListener('change', () => ehfCheck(key));
            const text = document.createElement('span');
            text.className = 'truncate'; text.textContent = v;
            label.append(cb, text);
            box.appendChild(label);
        });
        const sb = ehfPop(key).querySelector('[data-ehf-searchbox]');
        if (sb) sb.hidden = values.length <= 7;
    }

    function ehfFilterOptions(key, term) {
        term = term.trim().toLowerCase();
        const pop = ehfPop(key);
        let shown = 0;
        pop.querySelectorAll('[data-ehf-item]').forEach(item => {
            const ok = term === '' || item.textContent.toLowerCase().includes(term);
            item.classList.toggle('hidden', !ok);
            if (ok) shown++;
        });
        pop.querySelector('[data-ehf-empty]').classList.toggle('hidden', shown > 0);
    }

    document.addEventListener('click', e => { if (!e.target.closest('[data-ehf-type]') && !e.target.closest('[data-ehf-btn]')) ehfCloseAll(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') ehfCloseAll(); });
    window.addEventListener('scroll', e => { if (_ehfOpen && !(e.target.closest && e.target.closest('[data-ehf-type]'))) ehfCloseAll(); }, true);
</script>
@endpush
@endonce
