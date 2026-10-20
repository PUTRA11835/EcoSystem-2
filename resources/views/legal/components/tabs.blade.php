{{-- Tabs of the Legal page: Dashboard | Legal Basis — two real routes, each with its own permission (a tab nobody may open is not drawn). --}}
@include('partials.hub-tabs', ['hubTabs' => [
    ['label' => 'Dashboard', 'icon' => 'gauge', 'route' => 'legal', 'is' => 'legal', 'gate' => 'legal', 'strict' => true],
    ['label' => 'Legal Basis', 'icon' => 'scale-balanced', 'route' => 'finance.legal-basis.index', 'is' => 'legal/basis*', 'gate' => 'finance.legal-basis', 'strict' => true],
], 'hubHideSingle' => true])
