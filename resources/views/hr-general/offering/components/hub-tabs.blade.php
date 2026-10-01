{{--
    The two tabs of the Offering Letter menu. Each is rendered only when one of
    the person's roles has the View box of its slug ticked (Management →
    Roles) — 'strict' for the same reason as the Recruitment hub tabs.
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label'  => 'Offering Letter Table',
        'icon'   => 'file-signature',
        'route'  => 'general.recruitment.offers.index',
        'is'     => 'general/recruitment/offers',
        'gate'   => 'general.recruitment.offers',
        'strict' => true,
    ],
    [
        'label'  => 'Offering Settings',
        'icon'   => 'sliders',
        'route'  => 'general.recruitment.offers.settings.edit',
        'is'     => 'general/recruitment/offers/settings*',
        'gate'   => 'general.recruitment.offers.settings',
        'strict' => true,
    ],
]])
