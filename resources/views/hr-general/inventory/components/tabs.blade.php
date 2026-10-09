{{--
    The four tabs of General Affairs → Inventory & Assets. Each is rendered only when one of the person's roles has the
    View box of its slug ticked (Management → Roles) — 'strict': the group slug `general.inventory` is only the parent
    in Menu Access and never opens a tab by itself.
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Overview', 'icon' => 'chart-pie', 'route' => 'general.inventory.overview',
        'is' => 'general/inventory/overview*', 'gate' => 'general.inventory.overview', 'strict' => true,
    ],
    [
        'label' => 'Inventory', 'icon' => 'boxes-stacked', 'route' => 'general.inventory.items.index',
        'is' => 'general/inventory/items*', 'gate' => 'general.inventory.items', 'strict' => true,
    ],
    [
        'label' => 'Assets', 'icon' => 'laptop', 'route' => 'general.inventory.assets.index',
        'is' => 'general/inventory/assets*', 'gate' => 'general.inventory.assets', 'strict' => true,
    ],
    [
        'label' => 'Settings', 'icon' => 'gear', 'route' => 'general.inventory.settings.index',
        'is' => 'general/inventory/settings*', 'gate' => 'general.inventory.settings', 'strict' => true,
    ],
]])

@include('partials.accent-remap')
