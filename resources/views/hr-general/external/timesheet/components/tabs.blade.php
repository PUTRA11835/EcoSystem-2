{{-- The three tabs of External → Timesheet & Payment. A tab shows only when the person holds its slug ('strict': never through `general`). --}}
@include('partials.hub-tabs', ['hubTabs' => [
    ['label' => 'Timesheets', 'icon' => 'clock', 'route' => 'general.external.timesheet.index', 'is' => 'general/external/timesheet', 'gate' => 'general.external.timesheet', 'strict' => true],
    ['label' => 'Invoices & Payments', 'icon' => 'file-invoice-dollar', 'route' => 'general.external.timesheet.invoices', 'is' => 'general/external/timesheet/invoices*', 'gate' => 'general.external.timesheet.invoices', 'strict' => true],
    ['label' => 'Settings', 'icon' => 'sliders', 'route' => 'general.external.timesheet.settings.index', 'is' => 'general/external/timesheet/settings*', 'gate' => 'general.external.timesheet.settings', 'strict' => true],
], 'hubHideSingle' => true])
@include('partials.accent-remap')
@include('hr-general.contracts.components.confirm-forms')
