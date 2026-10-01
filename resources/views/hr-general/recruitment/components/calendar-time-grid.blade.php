{{--
    Outlook-style time grid — one column per day, hours down the side, each
    event a block placed by its start and end time. Serves the Day, Work week
    and Week views of the Schedule tab and the week on the Dashboard.

    Clicking an event opens the detail card (components/calendar-event-modal,
    which the page must include). With $allowCreate, clicking an empty slot
    opens the schedule form on that date and time.

    Parameters:
      $days        — Collection<Carbon>, the columns
      $events      — Collection of event arrays (RecruitmentCalendar::between()['events'])
      $height      — px height of the scrollable area
      $allowCreate — bool
--}}
@php
    $hourHeight = 48;
    $allowCreate = $allowCreate ?? false;
    $today = now()->format('Y-m-d');
    $nowMinutes = now()->hour * 60 + now()->minute;
    $shown = $days->map->format('Y-m-d')->flip();
    $byDate = $events->filter(fn ($event) => $shown->has($event['date']))->groupBy('date');

    // Open on the working day: an hour before the first event, 07:00 at the latest.
    $firstStart = $byDate->flatten(1)->min('startMinutes');
    $scrollMinutes = max(0, min($firstStart ?? 480, 480) - 60);
    $columns = '3.5rem repeat(' . $days->count() . ', minmax(0, 1fr))';
@endphp
<div class="border border-gray-200 rounded-lg bg-white overflow-hidden">
    <div class="overflow-x-auto">
        <div style="min-width: {{ $days->count() > 1 ? 760 : 300 }}px;">
            {{-- Day headers --}}
            <div class="grid border-b border-gray-200" style="grid-template-columns: {{ $columns }};">
                <div></div>
                @foreach($days as $day)
                    @php $isToday = $day->format('Y-m-d') === $today; @endphp
                    <div class="px-3 py-2 border-l border-gray-100 border-t-2" style="border-top-color: {{ $isToday ? 'var(--primary-color)' : 'transparent' }};">
                        <div class="text-xl leading-none {{ $isToday ? 'font-bold' : 'font-medium text-gray-700' }}" @if($isToday) style="color: var(--primary-color);" @endif>{{ $day->day }}</div>
                        <div class="text-[11px] mt-1 {{ $isToday ? 'font-semibold' : 'text-gray-500' }}" @if($isToday) style="color: var(--primary-color);" @endif>{{ $day->format('D') }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Hours --}}
            <div class="overflow-y-auto" style="max-height: {{ $height }}px;" data-time-grid-scroll="{{ $scrollMinutes / 60 * $hourHeight }}">
                <div class="grid" style="grid-template-columns: {{ $columns }}; height: {{ 24 * $hourHeight }}px;">
                    <div class="relative">
                        @for($hour = 1; $hour < 24; $hour++)
                            <span class="absolute right-2 text-[10px] text-gray-400 leading-none" style="top: {{ $hour * $hourHeight - 5 }}px;">{{ sprintf('%02d:00', $hour) }}</span>
                        @endfor
                    </div>

                    @foreach($days as $day)
                        @php
                            $dateKey = $day->format('Y-m-d');
                            $dayEvents = \App\Services\Recruitment\RecruitmentCalendar::lanes($byDate->get($dateKey, collect()));
                        @endphp
                        {{-- Hour and half-hour lines are painted as a background, so the column stays one element. --}}
                        <div class="relative border-l border-gray-100 {{ $allowCreate ? 'cursor-pointer' : '' }} {{ $dateKey === $today ? 'bg-gray-50/60' : '' }}"
                            style="background-image: linear-gradient(to bottom, #e5e7eb 1px, transparent 1px), linear-gradient(to bottom, #f3f4f6 1px, transparent 1px); background-size: 100% {{ $hourHeight }}px, 100% {{ $hourHeight / 2 }}px;"
                            @if($allowCreate) data-date="{{ $dateKey }}" data-hour-height="{{ $hourHeight }}" onclick="calendarSlotClick(event, this)" title="Click an empty slot to schedule an interview" @endif>

                            @foreach($dayEvents as $event)
                                @php
                                    $top = $event['startMinutes'] / 60 * $hourHeight;
                                    $blockHeight = max(($event['endMinutes'] - $event['startMinutes']) / 60 * $hourHeight, 22) - 2;
                                    $width = 100 / $event['lanes'];
                                    $isInterview = $event['type'] === 'interview';
                                @endphp
                                <button type="button" data-event-id="{{ $event['id'] }}" onclick="event.stopPropagation(); openCalendarEvent('{{ $event['id'] }}')"
                                    title="{{ $event['start'] }}–{{ $event['end'] }} {{ $event['title'] }}"
                                    class="absolute overflow-hidden text-left rounded px-1.5 py-0.5 text-[11px] leading-tight border-l-[3px] transition hover:brightness-95 {{ $isInterview ? 'text-gray-900' : 'bg-gray-100 border-gray-400 text-gray-700' }}"
                                    style="top: {{ $top }}px; height: {{ $blockHeight }}px; left: calc({{ $event['lane'] * $width }}% + 2px); width: calc({{ $width }}% - 4px);
                                        @if($isInterview) background: rgba(var(--primary-rgb), 0.14); border-left-color: var(--primary-color); @endif">
                                    <span class="block font-semibold truncate">{{ $event['title'] }}</span>
                                    @if($blockHeight >= 34)
                                        <span class="block truncate text-gray-600">{{ $event['start'] }}–{{ $event['end'] }}{{ $event['where'] ? ' · ' . $event['where'] : '' }}</span>
                                    @endif
                                </button>
                            @endforeach

                            @if($dateKey === $today)
                                <div class="absolute left-0 right-0 pointer-events-none" style="top: {{ $nowMinutes / 60 * $hourHeight }}px;">
                                    <div class="h-px" style="background: var(--primary-color);"></div>
                                    <div class="absolute -left-1 -top-1 w-2 h-2 rounded-full" style="background: var(--primary-color);"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-time-grid-scroll]').forEach(el => { el.scrollTop = Number(el.dataset.timeGridScroll); });
    });

    // An empty slot of the time grid: start the schedule form there, snapped to the half hour.
    function calendarSlotClick(event, column) {
        if (typeof openCandidateModal !== 'function') return;
        const offset = event.clientY - column.getBoundingClientRect().top;
        const minutes = Math.min(Math.floor(offset / Number(column.dataset.hourHeight) * 2) * 30, 23 * 60 + 30);
        const pad = n => String(n).padStart(2, '0');
        openCandidateModal({ mode: 'existing', date: column.dataset.date, startTime: pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60) });
    }
</script>
@endpush
@endonce
