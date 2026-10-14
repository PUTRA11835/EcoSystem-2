{{--
    Left-hand navigation of a module's Settings page (Overtime, Reimbursement,
    Purchase Request, Cash Advance).

    The links are built from the page itself: every <h2> / <h3> inside
    [data-settings-content] becomes an entry (an <h3> is indented under the
    <h2> above it), and the entry of the section being read is highlighted
    while scrolling. A heading marked data-no-nav is skipped.

    Parameters:
      $title      — module name shown above the list
--}}
<aside class="w-full lg:w-60 shrink-0 self-start" data-settings-nav>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h2 class="text-sm font-bold text-gray-900" data-no-nav>{{ $title }} Settings</h2>
        </div>

        <nav class="p-2 max-h-[70vh] overflow-y-auto" aria-label="Settings sections" data-settings-links></nav>
    </div>
</aside>

@once
@push('scripts')
<script>
    (function () {
        const links   = document.querySelector('[data-settings-links]');
        const content = document.querySelector('[data-settings-content]');
        const aside   = document.querySelector('[data-settings-nav]');
        if (!links || !content || !aside) return;

        const shell = content.parentElement;
        const wide  = window.matchMedia('(min-width: 1024px)');

        // The page itself is a scroll container of the layout, so a sticky sidebar
        // is not reliable. Instead the shell takes the height left under the
        // header and ONLY the content column scrolls: the navigation never moves.
        function fit() {
            if (wide.matches) {
                const top = shell.getBoundingClientRect().top + window.scrollY;
                shell.style.height = 'calc(100vh - ' + Math.max(top, 0) + 'px - 1.5rem)';
                content.style.overflowY = 'auto';
                content.style.height = '100%';
                aside.style.height = '100%';
                aside.style.position = 'static';
                links.style.maxHeight = 'none';
            } else {
                [shell, content, aside, links].forEach(el => { el.style.height = ''; el.style.overflowY = ''; el.style.maxHeight = ''; });
            }
        }

        wide.addEventListener('change', fit);
        window.addEventListener('resize', fit);
        fit();

        const slug = function (text, index) {
            return 'sec-' + index + '-' + text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        };

        const headings = Array.from(content.querySelectorAll('h2, h3')).filter(function (h) {
            return !h.hasAttribute('data-no-nav') && h.textContent.trim() !== '';
        });

        const entries = headings.map(function (heading, index) {
            heading.id = heading.id || slug(heading.textContent.trim(), index);
            heading.style.scrollMarginTop = '0.75rem';

            const sub = heading.tagName === 'H3';
            const link = document.createElement('a');
            link.href = '#' + heading.id;
            link.textContent = heading.textContent.trim().replace(/\s+/g, ' ');
            link.className = 'block transition-colors text-gray-600 hover:text-gray-900 hover:bg-gray-50 '
                + (sub
                    ? 'ml-4 pl-3 py-1.5 text-xs font-medium border-l-2 border-gray-200 rounded-r-lg'
                    : 'mt-2 first:mt-0 px-3 py-2 text-xs font-bold uppercase tracking-wide rounded-lg');
            link.addEventListener('click', function (event) {
                event.preventDefault();
                heading.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.replaceState(null, '', '#' + heading.id);
            });

            links.appendChild(link);

            return { heading: heading, link: link, sub: sub };
        });

        if (entries.length === 0) return;

        function highlight() {
            const base = wide.matches ? content.getBoundingClientRect().top : 0;
            let current = entries[0];

            entries.forEach(function (entry) {
                if (entry.heading.getBoundingClientRect().top - base <= 80) current = entry;
            });

            entries.forEach(function (entry) {
                const on = entry === current;
                entry.link.classList.toggle('bg-gray-100', on);
                entry.link.style.color = on ? 'var(--primary-color)' : '';
                if (entry.sub) entry.link.style.borderLeftColor = on ? 'var(--primary-color)' : '';
            });
        }

        content.addEventListener('scroll', highlight, { passive: true });
        window.addEventListener('scroll', highlight, { passive: true });
        highlight();
    })();
</script>
@endpush
@endonce
