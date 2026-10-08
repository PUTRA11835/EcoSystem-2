{{--
    The two tabs of HR & General → Contract. Each is rendered only when one of the person's roles has the View box of
    its slug ticked (Management → Roles) — 'strict': the group slug `general.contracts` is only the parent in Menu
    Access and never opens a tab by itself.
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Contracts', 'icon' => 'file-signature', 'route' => 'general.contracts.list',
        'is' => 'general/contracts/list*', 'gate' => 'general.contracts.list', 'strict' => true,
    ],
    [
        'label' => 'Templates', 'icon' => 'file-lines', 'route' => 'general.contracts.templates.index',
        'is' => 'general/contracts/templates*', 'gate' => 'general.contracts.templates', 'strict' => true,
    ],
]])

{{-- Accent colour + unsaved-changes warning, the same as the Letter Templates hub. --}}
@include('partials.accent-remap')
@include('hr-general.recruitment.components.unsaved-guard')
@include('hr-general.contracts.components.confirm-forms')
