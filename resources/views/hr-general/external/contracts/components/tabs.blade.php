{{--
    Tab bar of External → External Contract: the contracts and their Settings (contract types and position types of the
    external contract forms). Each tab shows only for roles that hold its slug; a lone tab is not drawn.
--}}
@include('partials.hub-tabs', ['hubHideSingle' => true, 'hubTabs' => [
    [
        'label' => 'Contracts', 'icon' => 'file-signature', 'route' => 'general.external.contracts.index',
        'is' => ['general/external/contracts', 'general/external/contracts/consultant/*'], 'gate' => 'general.external.contracts', 'strict' => true,
    ],
    [
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'general.external.contracts.settings.index',
        'is' => 'general/external/contracts/settings*', 'gate' => 'general.external.contracts.settings', 'strict' => true,
    ],
]])
