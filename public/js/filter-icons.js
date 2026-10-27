/**
 * Table-header filter buttons show what they do: a magnifier for a search box, a caret for a dropdown list, a checklist for
 * ticking several values, a calendar for dates, sliders for a range — instead of the same funnel everywhere.
 *
 * The shared header-filter components already draw the right icon on the server. This covers the tables that build their own
 * popovers (KPI templates, Leave & Permit, …): it looks at what is inside each popover and swaps the funnel. A funnel button is
 * recognised by its icon (`fa-filter`) sitting inside a table header, whose onclick names the popover.
 */
(function () {
    const ICON = { search: 'fa-magnifying-glass', dropdown: 'fa-caret-down', list: 'fa-list-check', date: 'fa-calendar-days', range: 'fa-sliders' };

    function popoverOf(btn) {
        if (btn.dataset.hfTarget && document.getElementById(btn.dataset.hfTarget)) return document.getElementById(btn.dataset.hfTarget);
        // toggleHF(event, 'id') / hf(event,'id') / ehfToggle(event, 'key'): the quoted name is the popover (or its key)
        const names = Array.from(((btn.getAttribute('onclick') || '').matchAll(/['"]([\w-]+)['"]/g))).map(m => m[1]);
        for (const name of names.reverse()) {
            const el = document.getElementById(name) || document.getElementById('ehf-' + name);
            if (el) return el;
        }
        return btn.closest('th')?.querySelector('.header-filter-popover') || null;
    }

    function kindOf(pop) {
        if (pop.querySelector('input[type=date], [data-date]')) return 'date';
        if (pop.querySelector('input[type=text], input[type=search], input:not([type])') ) return 'search';
        if (pop.querySelector('input[type=number]')) return 'range';
        if (pop.querySelector('input[type=checkbox]')) return 'list';
        return 'dropdown';   // a list of options to pick from (often filled in later by the page)
    }

    function enhance(root) {
        (root || document).querySelectorAll('th button:not([data-hf-icon])').forEach(btn => {
            const icon = btn.querySelector('i.fa-filter');
            if (!icon) return;
            const pop = popoverOf(btn);
            if (!pop) return;

            const kind = kindOf(pop);
            icon.classList.remove('fa-filter');
            icon.classList.add(ICON[kind]);
            btn.dataset.hfIcon = kind;

            const title = btn.getAttribute('title') || '';
            if (kind === 'search' && /^filter\b/i.test(title)) btn.setAttribute('title', title.replace(/^filter/i, 'Search'));
        });
    }

    const run = () => enhance(document);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
    window.addEventListener('load', run);
    window.FilterIcons = { enhance };
})();
