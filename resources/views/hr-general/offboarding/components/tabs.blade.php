{{-- The two tabs of HR & General → Offboarding. A tab shows only when the person holds its slug ('strict': never through `general`). --}}
@include('partials.hub-tabs', ['hubTabs' => [
    ['label' => 'Offboardings', 'icon' => 'user-minus', 'route' => 'general.offboarding.index', 'is' => 'general/offboarding', 'gate' => 'general.offboarding.list', 'strict' => true],
    ['label' => 'Settings', 'icon' => 'sliders', 'route' => 'general.offboarding.settings.index', 'is' => 'general/offboarding/settings*', 'gate' => 'general.offboarding.settings', 'strict' => true],
], 'hubHideSingle' => true])
@include('partials.accent-remap')
@include('hr-general.contracts.components.confirm-forms')
