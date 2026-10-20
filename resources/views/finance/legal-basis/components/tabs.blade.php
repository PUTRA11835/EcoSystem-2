{{--
    Tabs of Business & Legal → Legal Basis. Each is shown only when one of the person's roles has the View box of its slug ticked
    (Management → Roles / Menu List → Access) — 'strict': every tab has its own slug, so Admin decides who sees the register and who may
    change the settings.
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Register', 'icon' => 'book', 'route' => 'finance.legal-basis.index',
        'is' => 'legal/basis', 'gate' => 'finance.legal-basis', 'strict' => true,
    ],
    [
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'finance.legal-basis.settings',
        'is' => 'legal/basis/settings*', 'gate' => 'finance.legal-basis.settings', 'strict' => true,
    ],
], 'hubHideSingle' => true])