{{--
    Tabs of Commercial & Finance → BPJS. Each is rendered only when one of the person's roles has the View box of its
    slug ticked (Management → Roles) — 'strict': the group slug `finance.bpjs` is only the parent in Menu Access.
    A hub with a single visible tab shows no tab bar (the page title already says it).
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'finance.bpjs.settings',
        'is' => 'finance/bpjs/settings*', 'gate' => 'finance.bpjs.settings', 'strict' => true,
    ],
    [
        'label' => 'Report', 'icon' => 'chart-column', 'route' => 'finance.bpjs.report',
        'is' => 'finance/bpjs/report*', 'gate' => 'finance.bpjs.report', 'strict' => true,
    ],
    [
        'label' => 'Letters', 'icon' => 'file-lines', 'route' => 'finance.bpjs.letters',
        'is' => 'finance/bpjs/letters*', 'gate' => 'finance.bpjs.letters', 'strict' => true,
    ],
]
, 'hubHideSingle' => true])

{{-- Accent colour: the indigo accents of these pages follow Settings → Appearance, like the sidebar. --}}
@include('partials.accent-remap')

{{-- Money fields: 1.000.000,00 --}}
@include('partials.money-input')
