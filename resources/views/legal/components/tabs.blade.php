{{-- Tabs of the Legal page: Dashboard | Register | Settings — three real routes, each with its own permission (a tab nobody may open is not drawn). --}}
@include('partials.hub-tabs', ['hubTabs' => [
    ['label' => 'Dashboard', 'icon' => 'gauge', 'route' => 'legal', 'is' => 'legal', 'gate' => 'legal', 'strict' => true],
    ['label' => 'Register', 'icon' => 'book', 'route' => 'finance.legal-basis.index', 'is' => 'legal/basis', 'gate' => 'finance.legal-basis', 'strict' => true],
    ['label' => 'Settings', 'icon' => 'sliders', 'route' => 'finance.legal-basis.settings', 'is' => 'legal/basis/settings*', 'gate' => 'finance.legal-basis.settings', 'strict' => true],
], 'hubHideSingle' => true])
