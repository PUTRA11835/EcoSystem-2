{{--
    The five tabs of HR & General → Letter Templates. Each is rendered only
    when one of the person's roles has the View box of its slug ticked
    (Management → Roles) — 'strict': the group slug `general.letters` is only
    the parent in Menu Access and never opens a tab by itself.
--}}
@include('partials.hub-tabs', ['hubTabs' => [
    [
        'label' => 'Dashboard', 'icon' => 'chart-pie', 'route' => 'general.letters.dashboard',
        'is' => 'general/letters/dashboard*', 'gate' => 'general.letters.dashboard', 'strict' => true,
    ],
    [
        'label' => 'Requests', 'icon' => 'inbox', 'route' => 'general.letters.requests.index',
        'is' => 'general/letters/requests*', 'gate' => 'general.letters.requests', 'strict' => true,
        'badge' => fn () => \App\Models\Letters\LetterRequest::where('status', \App\Models\Letters\LetterRequest::PENDING)->count(),
    ],
    [
        'label' => 'Letter Register', 'icon' => 'book', 'route' => 'general.letters.register.index',
        'is' => 'general/letters/register*', 'gate' => 'general.letters.register', 'strict' => true,
    ],
    [
        'label' => 'Create Letter', 'icon' => 'pen-to-square', 'route' => 'general.letters.compose.index',
        'is' => 'general/letters/compose*', 'gate' => 'general.letters.compose', 'strict' => true,
    ],
    [
        'label' => 'Settings', 'icon' => 'sliders', 'route' => 'general.letters.settings.index',
        'is' => 'general/letters/settings*', 'gate' => 'general.letter-templates', 'strict' => true,
    ],
]])

{{-- Warns before unsaved changes on this module's pages are lost. --}}
@include('hr-general.recruitment.components.unsaved-guard')
