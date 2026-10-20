{{--
    Mini sidebar for long pages: jump to a section, and the entry of the section being read (or the one just clicked) stays highlighted.
    Parameters:
      $navItems  list of ['id' => section element id, 'label' => text, 'icon' => 'fa-…'] or ['heading' => text] (a small caption between entries)
      $navLabel  (optional) aria-label / caption, default "On this page"
    Give each target element `class="pst-section"` (adds the scroll offset under the sticky header) and the matching id.
    Place the nav as the first child of a `lg:grid lg:grid-cols-[14.5rem_minmax(0,1fr)]` wrapper so it can stick.
--}}
@php
    $navLabel = $navLabel ?? 'On this page';
    $navUid = 'pn' . substr(md5(json_encode($navItems)), 0, 6);
@endphp

@once
<style>
    .pst-nav a { display: flex; align-items: center; gap: .6rem; padding: .45rem .7rem; border-radius: .5rem; font-size: .8125rem; color: #4b5563; border-left: 3px solid transparent; transition: background-color .12s, color .12s; }
    .pst-nav a:hover { background: #f3f4f6; color: #111827; }
    .pst-nav a.is-active { background: rgba(var(--primary-rgb), .10); color: rgb(var(--primary-rgb)); border-left-color: rgb(var(--primary-rgb)); font-weight: 600; }
    .pst-nav a i { width: 1rem; text-align: center; font-size: .75rem; }
    .pst-nav .pst-head { padding: .6rem .75rem .2rem; font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; }
    .pst-section { scroll-margin-top: 6rem; }
    html { scroll-behavior: smooth; }
    @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
    @media (max-width: 1023px) { .pst-nav .pst-head { display: none; } }
</style>
@endonce

<nav class="pst-nav bg-white rounded-xl border border-gray-200 shadow-sm p-2 flex lg:flex-col gap-1 overflow-x-auto lg:sticky lg:top-24 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto" aria-label="{{ $navLabel }}" data-page-nav="{{ $navUid }}">
    <p class="hidden lg:block px-3 pt-2 pb-1 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">{{ $navLabel }}</p>
    @foreach($navItems as $item)
        @if(isset($item['heading']))
            <p class="pst-head">{{ $item['heading'] }}</p>
        @else
            <a href="#{{ $item['id'] }}" data-target="{{ $item['id'] }}" class="whitespace-nowrap"><i class="fas {{ $item['icon'] ?? 'fa-circle' }}"></i><span>{{ $item['label'] }}</span></a>
        @endif
    @endforeach
</nav>

<script>
(function () {
    function init() {
        var nav = document.querySelector('[data-page-nav="{{ $navUid }}"]');
        if (!nav) return;
        var links = Array.prototype.slice.call(nav.querySelectorAll('a[data-target]'));
        // Looked up now (after the whole page is parsed) — at the time the nav is printed the sections below it do not exist yet.
        var sections = links.map(function (a) { return document.getElementById(a.dataset.target); });
        var pinned = null, pinnedUntil = 0;   // entry just clicked: kept while the smooth scroll runs; a section near the page end may never reach the top

        function activate(id) {
            links.forEach(function (a) {
                var on = a.dataset.target === id;
                a.classList.toggle('is-active', on);
                if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
                if (on && nav.scrollWidth > nav.clientWidth) a.scrollIntoView({ block: 'nearest', inline: 'center' });
            });
        }

        // The last section whose top has passed the reading line (just under the header) is the one being read.
        function current() {
            var line = 140, id = links[0].dataset.target;
            sections.forEach(function (s) { if (s && s.getBoundingClientRect().top <= line) id = s.id; });
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) id = links[links.length - 1].dataset.target;
            return id;
        }

        var ticking = false;
        function update() {
            if (pinned && Date.now() > pinnedUntil) pinned = null;   // the person scrolled on by hand (wheel, touch, keys, scrollbar)
            if (pinned || ticking) return;
            ticking = true;
            requestAnimationFrame(function () { activate(current()); ticking = false; });
        }

        links.forEach(function (a) {
            a.addEventListener('click', function () { pinned = a.dataset.target; pinnedUntil = Date.now() + 900; activate(pinned); });
        });
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        var fromHash = (location.hash || '').slice(1);
        if (fromHash && links.some(function (a) { return a.dataset.target === fromHash; })) { pinned = fromHash; pinnedUntil = Date.now() + 900; activate(fromHash); }
        else activate(current());
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
