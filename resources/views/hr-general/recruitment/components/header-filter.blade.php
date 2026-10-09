{{--
    A table header cell with its filter tucked behind the small funnel icon —
    the same floating-popover pattern as the KPI Evaluation and Leave & Permit
    tables, here as one reusable component.

    Filtering is server-side: every control belongs (through the `form`
    attribute) to one GET form on the page, so the filters, the rows-per-page
    choice and the pagination links all share the same query string.

    A search filter applies as you type: the page is fetched in the background
    and only the parts marked [data-live-region="…"] (the table rows, the
    pagination, the toolbar) are swapped in, so the popover and the caret stay
    where they are. Picking an option or ticking checkboxes reloads the page.

    Parameters:
      $form         — id of the GET form the filter submits
      $name         — query parameter
      $label        — column title
      $type         — 'search' | 'options' | 'checkboxes' | 'range' | 'daterange'
      $value        — current value (string; array for 'checkboxes'; ['min' => , 'max' => ] for 'range' and
                      ['from' => , 'to' => ] for 'daterange' — the query parameters are {name}_min / _max and
                      {name}_from / _to; a daterange input is a [data-date] box, wired by inventory's modal-helpers with ['dates' => true])
      $options      — [value => label] for 'options' / 'checkboxes'
      $placeholder  — 'search' input placeholder
      $allLabel     — 'options': label of the "no filter" row
      $default      — 'options': value that counts as "no filter" (default '')
      $exclusive    — 'checkboxes': a value that cannot be combined with the others
      $note         — small hint under the controls
      $align        — 'left' (default) | 'right'
      $thClass      — extra classes for the <th>
      $sortUrl / $sortMark — optional: the label becomes a sort link (url, ↑ ↓ ⇅ mark)
      $inline       — true: render only the funnel + popover (no <th>) so one <th> can hold two funnels
      $hideLabel    — true: omit the label text (the surrounding <th> already shows it)
--}}
@php
    $type = $type ?? 'search';
    $options = $options ?? [];
    $default = $default ?? '';
    $align = $align ?? 'left';
    $popoverId = 'hf-' . $form . '-' . $name;

    $isRange = in_array($type, ['range', 'daterange'], true);
    $rangeValue = $isRange ? (array) ($value ?? []) : [];
    $selected = $isRange ? '' : ($type === 'checkboxes' ? array_map('strval', (array) ($value ?? [])) : (string) ($value ?? ''));
    $active = $isRange
        ? array_filter($rangeValue, fn ($v) => $v !== '' && $v !== null) !== []
        : ($type === 'checkboxes' ? $selected !== [] : ($selected !== '' && $selected !== (string) $default));
    $optionRow = 'w-full flex items-center justify-between gap-2 px-3 py-1.5 text-xs text-left hover:bg-gray-50 transition-colors';
@endphp
@unless($inline ?? false)<th class="px-4 py-3 {{ $thClass ?? '' }}">@endunless
    <div class="flex items-center gap-1.5 {{ $align === 'right' ? 'justify-end' : 'justify-between' }}">
        @if(!empty($sortUrl))
            <a href="{{ $sortUrl }}" class="hover:text-gray-900">{{ $label }} <span class="text-gray-400">{{ $sortMark ?? '' }}</span></a>
        @elseif(!($hideLabel ?? false))
            <span>{{ $label }}</span>
        @endif
        <button type="button" data-hf-btn data-hf-target="{{ $popoverId }}" onclick="toggleHF(event, '{{ $popoverId }}')"
            class="relative p-1 rounded-md hover:bg-gray-200/70 transition-all {{ $active ? '' : 'text-gray-400 hover:text-gray-600' }}"
            @if($active) style="color: var(--primary-color);" @endif
            title="Filter {{ $label }}" aria-label="Filter {{ $label }}" aria-haspopup="true">
            <i class="fas fa-filter text-[10px]"></i>
            <span data-hf-dot class="{{ $active ? '' : 'hidden' }} absolute -top-0.5 -right-0.5 w-1.5 h-1.5 rounded-full ring-2 ring-white" style="background: var(--primary-color);"></span>
        </button>
    </div>

    <div id="{{ $popoverId }}" data-hf-form="{{ $form }}"
        class="header-filter-popover hidden {{ $type === 'search' ? 'w-60' : 'w-64' }} bg-white rounded-xl shadow-xl ring-1 ring-black/5 z-50 overflow-hidden text-left normal-case font-normal"
        onclick="event.stopPropagation()">
        <div class="px-3 py-2 border-b border-gray-100 flex items-center justify-between">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Filter · {{ $label }}</span>
            <button type="button" data-hf-clear onclick="hfClear('{{ $popoverId }}')" class="{{ $active ? '' : 'hidden' }} text-[10px] font-semibold text-red-500 hover:text-red-600">Clear</button>
        </div>

        @if($type === 'search')
            <div class="p-2.5">
                <div class="relative">
                    <input type="text" name="{{ $name }}" form="{{ $form }}" value="{{ $selected }}" autocomplete="off"
                        placeholder="{{ $placeholder ?? 'Type to search…' }}" aria-label="Filter {{ $label }}"
                        oninput="hfLive(this)"
                        onkeydown="if (event.key === 'Enter') { event.preventDefault(); hfLive(this, 0); }"
                        class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-200 transition-all font-normal">
                    <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <p class="text-[10px] text-gray-400 mt-1.5">{{ $note ?? 'Results update as you type.' }}</p>
            </div>
        @elseif($isRange)
            @php
                $isDate = $type === 'daterange';
                $parts = $isDate ? ['from' => 'From', 'to' => 'To'] : ['min' => 'Min', 'max' => 'Max'];
            @endphp
            <div class="p-2.5 space-y-2">
                @foreach($parts as $part => $partLabel)
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-400 mb-0.5">{{ $partLabel }}</label>
                        <input type="text" name="{{ $name }}_{{ $part }}" form="{{ $form }}" value="{{ $rangeValue[$part] ?? '' }}" autocomplete="off"
                            @if($isDate) data-date placeholder="Select date" @else inputmode="numeric" placeholder="Any" @endif
                            onkeydown="if (event.key === 'Enter') { event.preventDefault(); hfSubmit('{{ $form }}'); }"
                            aria-label="{{ $label }} {{ strtolower($partLabel) }}"
                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-200 font-normal">
                    </div>
                @endforeach
            </div>
            <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between gap-2">
                <span class="text-[10px] text-gray-400 leading-tight">{{ $note ?? '' }}</span>
                <button type="button" onclick="hfSubmit('{{ $form }}')"
                    class="px-2.5 py-1 text-[10px] font-semibold text-white primary-gradient rounded-md hover:opacity-90 shrink-0">Apply</button>
            </div>
        @elseif($type === 'options')
            <input type="hidden" name="{{ $name }}" form="{{ $form }}" value="{{ $selected }}" data-hf-value>
            @if(count($options) > 7)
                <div class="p-2 border-b border-gray-100">
                    <div class="relative">
                        <input type="text" autocomplete="off" placeholder="Search…" aria-label="Search {{ $label }} options" oninput="hfFilterOptions(this)"
                            class="w-full bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg pl-7 pr-2 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-200 font-normal">
                        <i class="fas fa-search text-[10px] absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    </div>
                </div>
            @endif
            <div class="py-1 max-h-64 overflow-y-auto" data-hf-options>
                @foreach([$default => $allLabel ?? 'All'] + $options as $optionValue => $optionLabel)
                    @php $isCurrent = (string) $optionValue === ($selected === '' ? (string) $default : $selected); @endphp
                    <button type="button" onclick="hfPick('{{ $popoverId }}', this.dataset.value)" data-value="{{ $optionValue }}"
                        class="{{ $optionRow }} {{ $isCurrent ? 'font-semibold bg-gray-50' : 'text-gray-700' }}"
                        @if($isCurrent) style="color: var(--primary-color);" @endif>
                        <span class="truncate">{{ $optionLabel }}</span>
                        @if($isCurrent)<i class="fas fa-check text-[10px] shrink-0"></i>@endif
                    </button>
                @endforeach
                <p class="hidden px-3 py-2 text-xs text-gray-400" data-hf-empty>No match.</p>
            </div>
        @else
            <div class="py-1.5 max-h-64 overflow-y-auto">
                @foreach($options as $optionValue => $optionLabel)
                    <label class="flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" name="{{ $name }}[]" form="{{ $form }}" value="{{ $optionValue }}"
                            @checked(in_array((string) $optionValue, $selected, true))
                            @if(isset($exclusive)) onchange="hfExclusive(this, '{{ $exclusive }}')" @endif
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="truncate">{{ $optionLabel }}</span>
                    </label>
                @endforeach
            </div>
            <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between gap-2">
                <span class="text-[10px] text-gray-400 leading-tight">{{ $note ?? '' }}</span>
                <button type="button" onclick="hfSubmit('{{ $form }}')"
                    class="px-2.5 py-1 text-[10px] font-semibold text-white primary-gradient rounded-md hover:opacity-90 shrink-0">Apply</button>
            </div>
        @endif
    </div>
@unless($inline ?? false)</th>@endunless

@once
@push('scripts')
<script>
    // ── Floating per-column header filter popovers ──
    let _hfOpen = null;

    function toggleHF(e, popoverId) {
        e.stopPropagation();
        const btn = e.currentTarget;
        const pop = document.getElementById(popoverId);
        if (!pop) return;
        const wasHidden = pop.classList.contains('hidden');
        closeAllHF();
        if (wasHidden) {
            pop.classList.remove('hidden');
            floatHF(btn, pop);
            _hfOpen = { btn, pop };
            const input = pop.querySelector('input[type="text"]');
            if (input) setTimeout(() => { input.focus(); input.select(); }, 50);
        }
    }

    // Fixed positioning: the table scrolls horizontally inside an overflow container that would clip the popover.
    function floatHF(btn, pop) {
        pop.style.position = 'fixed';
        pop.style.margin = '0';
        pop.style.zIndex = '9999';
        pop.style.top = '-9999px'; pop.style.left = '-9999px';
        const pw = pop.offsetWidth || 220, ph = pop.offsetHeight || 200;
        const r = btn.getBoundingClientRect();
        const vw = document.documentElement.clientWidth, vh = window.innerHeight;
        const left = Math.min(Math.max(8, r.right - pw), vw - pw - 8);
        let top = r.bottom + 4;
        if (top + ph > vh - 8 && r.top - ph - 4 > 8) top = r.top - ph - 4;
        top = Math.max(8, Math.min(top, vh - ph - 8));
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    function closeAllHF() {
        document.querySelectorAll('.header-filter-popover').forEach(p => {
            p.classList.add('hidden');
            p.style.position = p.style.top = p.style.left = p.style.zIndex = p.style.margin = '';
        });
        _hfOpen = null;
    }

    function hfSubmit(formId) {
        document.getElementById(formId).requestSubmit();
    }

    function hfPick(popoverId, value) {
        const pop = document.getElementById(popoverId);
        pop.querySelector('[data-hf-value]').value = value;
        hfSubmit(pop.dataset.hfForm);
    }

    function hfClear(popoverId) {
        const pop = document.getElementById(popoverId);
        pop.querySelectorAll('input[name]').forEach(input => {
            if (input.type === 'checkbox') input.checked = false;
            else { input.value = ''; input._flatpickr?.clear(false); }
        });

        const search = pop.querySelector('input[type="text"][name]');
        if (search) hfLive(search, 0);
        else hfSubmit(pop.dataset.hfForm);
    }

    // ── Search filters: apply while typing, without reloading the page ──
    let _hfTimer = null;
    let _hfRequest = 0;

    function hfLive(input, delay = 350) {
        hfMark(input.closest('.header-filter-popover'), input.value.trim() !== '');
        clearTimeout(_hfTimer);
        _hfTimer = setTimeout(() => hfRefresh(input.form), delay);
    }

    // The funnel's "active" look and the Clear link follow what is typed.
    function hfMark(pop, active) {
        const btn = document.querySelector('[data-hf-target="' + pop.id + '"]');
        btn.style.color = active ? 'var(--primary-color)' : '';
        btn.classList.toggle('text-gray-400', !active);
        btn.classList.toggle('hover:text-gray-600', !active);
        btn.querySelector('[data-hf-dot]').classList.toggle('hidden', !active);
        pop.querySelector('[data-hf-clear]').classList.toggle('hidden', !active);
    }

    async function hfRefresh(form) {
        const params = new URLSearchParams();
        Array.from(form.elements).forEach(el => {
            if (!el.name || el.disabled || el.value === '') return;
            if (el.type === 'checkbox' && !el.checked) return;
            params.append(el.name, el.value);
        });

        const url = form.action + (params.toString() ? '?' + params.toString() : '');
        const request = ++_hfRequest;

        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            const html = await response.text();
            if (request !== _hfRequest) return; // a newer keystroke has taken over

            const fresh = new DOMParser().parseFromString(html, 'text/html');
            const regions = document.querySelectorAll('[data-live-region]');
            const matched = Array.from(regions).filter(region => {
                const replacement = fresh.querySelector('[data-live-region="' + region.dataset.liveRegion + '"]');
                if (replacement) region.replaceWith(document.importNode(replacement, true));
                return !!replacement;
            });

            // Not the page we asked for (e.g. the session ended): go there properly.
            if (!response.ok || matched.length === 0) { window.location.href = url; return; }

            history.replaceState(null, '', url);
        } catch (error) {
            window.location.href = url;
        }
    }

    function hfFilterOptions(input) {
        const term = input.value.trim().toLowerCase();
        const list = input.closest('.header-filter-popover').querySelector('[data-hf-options]');
        let shown = 0;
        list.querySelectorAll('button').forEach(option => {
            const match = term === '' || option.textContent.toLowerCase().includes(term);
            option.classList.toggle('hidden', !match);
            if (match) shown++;
        });
        list.querySelector('[data-hf-empty]').classList.toggle('hidden', shown > 0);
    }

    function hfExclusive(box, exclusiveValue) {
        if (!box.checked) return;
        box.closest('.header-filter-popover').querySelectorAll('input[type="checkbox"]').forEach(other => {
            if (other !== box && (box.value === exclusiveValue || other.value === exclusiveValue)) other.checked = false;
        });
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.header-filter-popover') && !e.target.closest('[data-hf-btn]') && !e.target.closest('.flatpickr-calendar')) closeAllHF();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllHF(); });
    window.addEventListener('scroll', e => {
        if (_hfOpen && !(e.target.closest && e.target.closest('.header-filter-popover'))) closeAllHF();
    }, true);
    window.addEventListener('resize', () => { if (_hfOpen) floatHF(_hfOpen.btn, _hfOpen.pop); });

    // Keep the address bar clean: an empty filter is simply left out of the query string.
    document.addEventListener('submit', function (e) {
        if (!e.target.matches('form[data-filter-form]')) return;
        Array.from(e.target.elements).forEach(el => {
            if (el.name && el.value === '' && el.type !== 'checkbox') el.disabled = true;
        });
    });
</script>
@endpush
@endonce
