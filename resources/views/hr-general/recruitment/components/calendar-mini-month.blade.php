{{--
    The small month beside the calendar, as in Outlook: jump to any date, see
    which days have something scheduled, and which days the main view covers.

    Parameters:
      $monthDays — Collection<Carbon>, whole Sunday-first weeks covering the month
      $date      — Carbon, the anchor date of the main view
      $shownDays — Collection<Carbon>, the days the main view is showing
      $events    — Collection of event arrays
      $url       — Closure(Carbon): string, the URL of the main view on a date
--}}
@php
    $today = now()->format('Y-m-d');
    $shown = $shownDays->map->format('Y-m-d')->flip();
    $busy = $events->pluck('date')->flip();
@endphp
<div>
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-semibold text-gray-800">{{ $date->format('F Y') }}</span>
        <span class="flex items-center gap-1 text-gray-500">
            <a href="{{ $url($date->clone()->startOfMonth()->subMonthNoOverflow()) }}" class="w-6 h-6 rounded hover:bg-gray-100 flex items-center justify-center" aria-label="Previous month">
                <i class="fas fa-chevron-up text-[10px]"></i>
            </a>
            <a href="{{ $url($date->clone()->startOfMonth()->addMonthNoOverflow()) }}" class="w-6 h-6 rounded hover:bg-gray-100 flex items-center justify-center" aria-label="Next month">
                <i class="fas fa-chevron-down text-[10px]"></i>
            </a>
        </span>
    </div>
    <div class="grid grid-cols-7 text-center text-[11px]">
        @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $initial)
            <span class="py-1 text-gray-400 font-semibold">{{ $initial }}</span>
        @endforeach
        @foreach($monthDays as $day)
            @php
                $dateKey = $day->format('Y-m-d');
                $isToday = $dateKey === $today;
                $inView = $shown->has($dateKey);
            @endphp
            <a href="{{ $url($day) }}" aria-label="{{ $day->format('l, d F Y') }}{{ $busy->has($dateKey) ? ', has events' : '' }}"
                class="relative py-1 {{ $inView && !$isToday ? 'bg-gray-100' : '' }} {{ $day->isSameMonth($date) ? 'text-gray-700' : 'text-gray-300' }} hover:bg-gray-200/70 {{ $isToday ? 'rounded-full text-white font-bold' : '' }}"
                @if($isToday) style="background: var(--primary-color); color: #fff;" @endif>
                {{ $day->day }}
                @if($busy->has($dateKey) && !$isToday)
                    <span class="absolute bottom-0 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full" style="background: var(--primary-color);"></span>
                @endif
            </a>
        @endforeach
    </div>
</div>
