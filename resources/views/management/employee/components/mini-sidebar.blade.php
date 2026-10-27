{{--
    The mini sidebar of Management → Employee: jump to a list or the rules of the tab you are on, and switch to another tab without going
    back to the main menu. Sticks beside the content on a wide screen; on a narrow one it is a row of pills above it.

    Needs $panel (EmployeeSettingsController::panel()). Optional $extraAnchors: [[id, label, icon], ...] shown before the lists (Qualification's modules).
    The content it points at carries the ids sec-{section}-{field} (a list), sec-{section}-rules and the ones in $extraAnchors.
--}}
@php
    $section = $panel['section'];
    $tabs = [
        'basic-data' => ['Basic Data', 'fa-id-card'], 'address' => ['Address', 'fa-location-dot'], 'identification' => ['Identification', 'fa-fingerprint'],
        'family' => ['Family', 'fa-people-roof'], 'education' => ['Education', 'fa-graduation-cap'], 'qualification' => ['Qualification', 'fa-award'],
        'contract' => ['Contract', 'fa-file-contract'], 'bank' => ['Bank', 'fa-building-columns'], 'payment' => ['Payment', 'fa-wallet'], 'attachment' => ['Attachment', 'fa-paperclip'],
    ];
    $anchors = [];
    foreach ($extraAnchors ?? [] as [$id, $label, $icon]) {
        $anchors[] = [$id, $label, $icon, null];
    }
    foreach ($panel['lists'] as $fieldKey => $list) {
        $anchors[] = ["sec-{$section}-{$fieldKey}", $list['label'], 'fa-list', collect($list['rows'])->where('is_active', true)->count() . '/' . count($list['rows'])];
    }
    if (count($panel['rules']) || count($panel['lists'])) {
        $anchors[] = ["sec-{$section}-rules", $section === 'contract' ? 'What employees see' : 'Rules', 'fa-sliders', count($panel['rules']) ?: null];
    }
    $link = 'flex items-center gap-2 whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-300';
@endphp

<aside id="employeeMiniNav" class="lg:w-52 shrink-0 lg:sticky lg:top-24 lg:self-start lg:max-h-[calc(100vh-7rem)] space-y-3 lg:pt-4" aria-label="Employee settings navigation">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-2">
        <p class="hidden lg:block px-3 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">On this page</p>
        <nav class="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible">
            @foreach($anchors as [$id, $label, $icon, $count])
                <a href="#{{ $id }}" data-spy="{{ $id }}" class="{{ $link }} text-gray-600 hover:bg-gray-50 hover:text-gray-900">
                    <i class="fas {{ $icon }} w-3.5 text-center text-[11px] text-gray-400"></i>
                    <span class="truncate">{{ $label }}</span>
                    @if($count !== null)<span class="ml-auto pl-1 text-[10px] font-semibold text-gray-400">{{ $count }}</span>@endif
                </a>
            @endforeach
        </nav>
    </div>

    <div class="hidden lg:block bg-white rounded-xl border border-gray-200 shadow-sm p-2">
        <p class="px-3 pt-1 pb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">Employee settings</p>
        <nav class="flex flex-col gap-0.5">
            @foreach($tabs as $key => [$label, $icon])
                @continue(!$can(\App\Support\Management\EmployeeMasterSettings::SLUGS[$key]))
                <a href="{{ route("management.employee.{$key}.index") }}" @if($key === $section) aria-current="page" @endif
                   class="{{ $link }} {{ $key === $section ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                    <i class="fas {{ $icon }} w-3.5 text-center text-[11px] {{ $key === $section ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                    <span class="truncate">{{ $label }}</span>
                </a>
            @endforeach
        </nav>
    </div>
</aside>

<script>
(function () {
    // Highlight the entry of the block being read, and glide to a block on click.
    const links = [...document.querySelectorAll('#employeeMiniNav [data-spy]')];
    const targets = links.map(a => document.getElementById(a.dataset.spy)).filter(Boolean);
    if (!targets.length) return;
    const on = 'bg-indigo-50 text-indigo-700 font-semibold';
    const mark = id => links.forEach(a => {
        const active = a.dataset.spy === id;
        on.split(' ').forEach(c => a.classList.toggle(c, active));
        a.classList.toggle('text-gray-600', !active);
    });
    mark(targets[0].id);
    const seen = new Map();
    const io = new IntersectionObserver(entries => {
        entries.forEach(e => seen.set(e.target.id, e.isIntersecting ? e.boundingClientRect.top : null));
        const visible = [...seen].filter(([, top]) => top !== null).sort((a, b) => a[1] - b[1]);
        if (visible.length) mark(visible[0][0]);
    }, { rootMargin: '-10% 0px -60% 0px' });
    targets.forEach(t => io.observe(t));
    links.forEach(a => a.addEventListener('click', ev => {
        const t = document.getElementById(a.dataset.spy);
        if (!t) return;
        ev.preventDefault();
        t.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        mark(t.id);
    }));
})();
</script>
