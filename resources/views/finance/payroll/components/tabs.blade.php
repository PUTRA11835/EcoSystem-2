{{--
    Tabs of Commercial & Finance → Payroll. Each is rendered only when one of the person's roles has the View box of its
    slug ticked (Management → Roles) — 'strict': the group slug `finance.payroll` is only the parent in Menu Access.
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Periods', 'icon' => 'calendar-days', 'route' => 'finance.payroll.periods',
        'is' => 'finance/payroll/periods*', 'gate' => 'finance.payroll.periods', 'strict' => true,
    ],
    [
        'label' => 'Simulation', 'icon' => 'flask', 'route' => 'finance.payroll.simulation',
        'is' => 'finance/payroll/simulation*', 'gate' => 'finance.payroll.simulation', 'strict' => true,
    ],
    [
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'finance.payroll.settings',
        'is' => 'finance/payroll/settings*', 'gate' => 'finance.payroll.settings', 'strict' => true,
    ],
]
, 'hubHideSingle' => true])

{{-- Accent colour: the indigo accents of these pages follow Settings → Appearance, like the sidebar. --}}
@include('partials.accent-remap')

{{-- Money fields: 1.000.000,00 --}}
@include('partials.money-input')

@if(!($enabled ?? true))
    <div class="mb-6 flex items-start gap-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3" role="status">
        <i class="fas fa-power-off mt-0.5"></i>
        <p><span class="font-semibold">Payroll is not switched on yet.</span> Settings and Simulation work, but periods cannot be created or calculated until someone with the “Switch payroll on/off” permission switches it on in <a href="{{ route('finance.payroll.settings') }}" class="underline font-semibold">Payroll → Settings</a>.</p>
    </div>
@endif
