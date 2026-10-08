{{--
    Tabs of Commercial & Finance → PPh 21. Each is rendered only when one of the person's roles has the View box of
    its slug ticked (Management → Roles) — 'strict': the group slug `finance.pph21` is only the parent in Menu Access.
    A hub with a single visible tab shows no tab bar (the page title already says it).
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'finance.pph21.settings',
        'is' => 'finance/pph21/settings*', 'gate' => 'finance.pph21.settings', 'strict' => true,
    ],
    [
        'label' => 'Report', 'icon' => 'chart-column', 'route' => 'finance.pph21.report',
        'is' => 'finance/pph21/report*', 'gate' => 'finance.pph21.report', 'strict' => true,
    ],
]
, 'hubHideSingle' => true])

{{-- Accent colour: the indigo accents of these pages follow Settings → Appearance, like the sidebar. --}}
@include('partials.accent-remap')

{{-- Money fields: 1.000.000,00 --}}
@include('partials.money-input')
