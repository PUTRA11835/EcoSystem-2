{{--
    Recruitment hub tab list — one place for the five pages inside the
    Recruitment hub, following the hub-tabs-attendance.blade.php pattern.

    Which tabs a person sees is decided entirely in Management → Roles: a tab
    is rendered only when one of the person's roles has the View box of that
    tab's slug ticked. Every tab is 'strict' for that reason — almost every
    role holds the umbrella `general` slug, and without 'strict' the shared
    partial's `|| $can('general')` fallback would show all five tabs to people
    whose role was never granted them.

    Offers is intentionally NOT here — it has its own top-level sidebar entry
    (see partials/sidebar.blade.php) instead of being a tab inside this hub.
--}}
@php
    $hubTabs = [
        [
            'label'  => 'Dashboard',
            'icon'   => 'chart-pie',
            'route'  => 'general.recruitment.index',
            'is'     => 'general/recruitment',
            'gate'   => 'general.recruitment',
            'strict' => true,
        ],
        [
            'label'  => 'Selection Process',
            'icon'   => 'people-arrows',
            'route'  => 'general.recruitment.candidates.index',
            'is'     => 'general/recruitment/candidates*',
            'gate'   => 'general.recruitment.candidates',
            'strict' => true,
        ],
        [
            'label'  => 'Schedule',
            'icon'   => 'calendar-check',
            'route'  => 'general.recruitment.schedule.index',
            'is'     => 'general/recruitment/schedule*',
            'gate'   => 'general.recruitment.schedule',
            'strict' => true,
        ],
        [
            'label'  => 'Job Openings',
            'icon'   => 'briefcase',
            'route'  => 'general.recruitment.jobs.index',
            'is'     => 'general/recruitment/jobs*',
            'gate'   => 'general.recruitment.jobs',
            'strict' => true,
        ],
        [
            'label'  => 'Settings',
            'icon'   => 'sliders',
            'route'  => 'general.recruitment.settings.edit',
            'is'     => 'general/recruitment/settings*',
            'gate'   => 'general.recruitment.settings',
            'strict' => true,
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])

{{-- Warns before unsaved changes on this module's pages are lost. --}}
@include('hr-general.recruitment.components.unsaved-guard')
