@extends('dashboard')
@section('title', 'Recruitment - Schedule')
@section('page-title', 'Schedule')
@section('page-subtitle', 'The interview calendar: every scheduled interview, who takes part, and when.')

@php
    // Capabilities of the Schedule tab, as ticked in Management → Roles.
    $canCreate = $canDo('general.recruitment.schedule', 'create');
    $canEdit   = $canDo('general.recruitment.schedule', 'edit');
    $canCancel = $canDo('general.recruitment.schedule', 'delete');

    $calendarUrl = fn ($onDate, ?string $inView = null) => route('general.recruitment.schedule.index', [
        'view' => $inView ?? $view, 'date' => $onDate->format('Y-m-d'),
    ]);
    $dayUrl = route('general.recruitment.schedule.index', ['view' => 'day', 'date' => '__DATE__']);
    $navButton = 'rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 text-xs font-semibold shadow-sm';
    $viewIcons = ['day' => 'calendar-day', 'workweek' => 'calendar-week', 'week' => 'calendar-week', 'month' => 'calendar-days'];
    $events = $calendar['events'];
@endphp

@section('content')
<div class="w-full space-y-6 px-1 lg:px-2">

    @include('hr-general.recruitment.components.hub-tabs')
    @include('hr-general.recruitment.components.form-errors')

    @if($calendar['error'])
        <div class="flex items-start gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
            <i class="fas fa-triangle-exclamation mt-0.5"></i>
            <span>{{ $calendar['error'] }}</span>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        {{-- Toolbar: new event · view switcher · calendar source --}}
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
            @if($canCreate)
                <button type="button" onclick="openCandidateModal({ mode: 'existing' })"
                    class="inline-flex items-center gap-1.5 px-4 py-2 primary-gradient text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-all shadow-sm">
                    <i class="fas fa-calendar-plus text-xs"></i> Schedule Interview
                </button>
            @endif

            <div class="inline-flex rounded-lg border border-gray-200 p-0.5 text-xs font-semibold" role="group" aria-label="Calendar view">
                @foreach($views as $key => $label)
                    <a href="{{ $calendarUrl($date, $key) }}" @if($view === $key) aria-current="true" @endif
                        class="px-3 py-1.5 rounded-md inline-flex items-center gap-1.5 {{ $view === $key ? 'primary-gradient text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                        <i class="fas fa-{{ $viewIcons[$key] }} text-[10px]"></i> {{ $label }}
                    </a>
                @endforeach
            </div>

            <span class="ml-auto inline-flex items-center gap-1.5 text-[11px] text-gray-500">
                @if($calendar['external'])
                    <i class="fab fa-microsoft"></i> Outlook calendar: {{ $calendar['mailbox'] }}
                @else
                    <i class="fas fa-database"></i> Calendar source: this system
                @endif
            </span>
        </div>

        {{-- Navigation --}}
        <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
            <a href="{{ $calendarUrl(now()) }}" class="{{ $navButton }} px-3 py-1.5 inline-flex items-center gap-1.5">
                <i class="far fa-calendar-check text-[11px]"></i> Today
            </a>
            <div class="flex items-center gap-1">
                <a href="{{ $calendarUrl($previous) }}" class="{{ $navButton }} px-2.5 py-1.5" aria-label="Previous">
                    <i class="fas fa-chevron-left text-[10px]"></i>
                </a>
                <a href="{{ $calendarUrl($next) }}" class="{{ $navButton }} px-2.5 py-1.5" aria-label="Next">
                    <i class="fas fa-chevron-right text-[10px]"></i>
                </a>
            </div>
            <h3 class="text-base font-bold text-gray-800">{{ $rangeLabel }}</h3>
        </div>

        <div class="p-4 flex flex-col lg:flex-row gap-5">
            {{-- Side: mini month + which calendars are shown --}}
            <aside class="lg:w-56 shrink-0 space-y-5">
                @include('hr-general.recruitment.components.calendar-mini-month', [
                    'monthDays' => $monthDays, 'shownDays' => $days, 'events' => $events, 'url' => $calendarUrl,
                ])

                <div class="border-t border-gray-100 pt-4">
                    <h4 class="text-xs font-bold text-gray-800 mb-2">Calendars</h4>
                    <ul class="space-y-2.5 text-xs">
                        <li class="flex items-start gap-2">
                            <span class="w-3 h-3 mt-0.5 rounded-sm shrink-0" style="background: var(--primary-color);"></span>
                            <span>
                                <span class="block text-gray-800">Recruitment interviews</span>
                                <span class="block text-[11px] text-gray-400">From the Selection Process</span>
                            </span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="w-3 h-3 mt-0.5 rounded-sm shrink-0 {{ $calendar['external'] ? 'bg-gray-400' : 'border border-dashed border-gray-400' }}"></span>
                            <span>
                                <span class="block {{ $calendar['external'] ? 'text-gray-800' : 'text-gray-500' }}">Outlook calendar</span>
                                @if($calendar['external'])
                                    <span class="block text-[11px] text-gray-400 break-all">{{ $calendar['mailbox'] }}</span>
                                @else
                                    <span class="block text-[11px] text-gray-400 leading-snug">
                                        Not connected. Once it is, the account's other events appear here in grey, attendees are invited by email and Teams links are generated.
                                    </span>
                                    @if($can('general.recruitment.settings'))
                                        <a href="{{ route('general.recruitment.settings.edit') }}" class="text-[11px] font-semibold" style="color: var(--primary-color);">Calendar settings</a>
                                    @endif
                                @endif
                            </span>
                        </li>
                    </ul>
                </div>
            </aside>

            <div class="flex-1 min-w-0">
                @if($view === 'month')
                    @include('hr-general.recruitment.components.calendar-month-grid', [
                        'days' => $days, 'events' => $events, 'dayUrl' => $dayUrl, 'allowCreate' => $canCreate,
                    ])
                @else
                    @include('hr-general.recruitment.components.calendar-time-grid', [
                        'days' => $days, 'events' => $events, 'height' => 600, 'allowCreate' => $canCreate,
                    ])
                @endif
            </div>
        </div>
    </div>
</div>

@include('hr-general.recruitment.components.calendar-event-modal', [
    'events' => $events, 'canEdit' => $canEdit, 'canCancel' => $canCancel,
    'canOpenCandidate' => $can('general.recruitment.candidates'), 'dayUrl' => null,
])
@if($canCreate)
    @include('hr-general.recruitment.components.candidate-modal')
@endif
@if($canEdit)
    @include('hr-general.recruitment.components.reschedule-modal')
@endif
@endsection

@if($canCreate)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Arriving from a candidate's page: open the form with that candidate selected.
        const prefillCandidateId = @json($prefillCandidateId);
        if (prefillCandidateId) {
            openCandidateModal({ mode: 'existing', candidateId: prefillCandidateId });
        }
    });
</script>
@endpush
@endif
