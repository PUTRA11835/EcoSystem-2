{{--
    Outlook-style month view: a grid of whole weeks and, beside it, the agenda
    of the selected day (time, duration, title — click for the detail card).

    Parameters:
      $days        — Collection<Carbon>, whole Sunday-first weeks covering the month
      $date        — Carbon, the anchor date; its month is the one shown, and it starts selected
      $events      — Collection of event arrays
      $dayUrl      — URL of the Day view with the date replaced by "__DATE__"
      $allowCreate — bool
--}}
@php
    $allowCreate = $allowCreate ?? false;
    $today = now()->format('Y-m-d');
    $byDate = $events->groupBy('date');
    $chipLimit = 2;
@endphp
<div class="flex flex-col xl:flex-row gap-4" data-month-calendar data-selected="{{ $date->format('Y-m-d') }}" data-day-url="{{ $dayUrl }}">
    <div class="flex-1 min-w-0 overflow-x-auto">
        <div class="min-w-[640px] border border-gray-200 rounded-lg overflow-hidden bg-white">
            <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="px-2 py-2 text-[11px] font-semibold text-gray-500">{{ $dayName }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @foreach($days as $day)
                    @php
                        $dateKey = $day->format('Y-m-d');
                        $dayEvents = $byDate->get($dateKey, collect());
                        $outside = !$day->isSameMonth($date);
                        $isToday = $dateKey === $today;
                    @endphp
                    <div role="button" tabindex="0" data-month-day="{{ $dateKey }}" onclick="selectCalendarDay('{{ $dateKey }}')"
                        onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); selectCalendarDay('{{ $dateKey }}'); }"
                        aria-label="{{ $day->format('l, d F Y') }}, {{ $dayEvents->count() }} events"
                        class="min-h-[84px] p-1.5 border-b border-r border-gray-100 cursor-pointer outline-none ring-inset focus-visible:ring-2 focus-visible:ring-indigo-300 {{ $outside ? 'bg-gray-50/70' : 'bg-white hover:bg-gray-50' }}">
                        <div class="text-xs mb-1 {{ $isToday ? 'font-bold' : ($outside ? 'text-gray-400' : 'text-gray-700') }}" @if($isToday) style="color: var(--primary-color);" @endif>
                            {{ $day->day === 1 || $loop->first ? $day->format('j M') : $day->day }}
                        </div>

                        @if($dayEvents->count() <= $chipLimit)
                            <div class="space-y-1">
                                @foreach($dayEvents as $event)
                                    <div class="truncate rounded px-1.5 py-0.5 text-[10px] font-semibold border-l-[3px] {{ $event['type'] === 'interview' ? 'text-gray-900' : 'bg-gray-100 border-gray-400 text-gray-700' }}"
                                        @if($event['type'] === 'interview') style="background: rgba(var(--primary-rgb), 0.14); border-left-color: var(--primary-color);" @endif>
                                        {{ $event['title'] }}
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded bg-gray-100 text-center text-[11px] font-semibold text-gray-600 py-0.5">+{{ $dayEvents->count() }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Agenda of the selected day; filled by selectCalendarDay(). --}}
    <aside class="xl:w-72 shrink-0 border border-gray-200 rounded-lg bg-white" aria-live="polite">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
            <h4 class="text-sm font-bold text-gray-800" data-agenda-title></h4>
            <div class="flex items-center gap-3 text-xs font-semibold">
                <a href="#" data-agenda-day-link style="color: var(--primary-color);">Open day</a>
                @if($allowCreate)
                    <button type="button" data-agenda-add style="color: var(--primary-color);"><i class="fas fa-plus text-[10px]"></i> New</button>
                @endif
            </div>
        </div>
        <div class="p-3 space-y-2 max-h-[460px] overflow-y-auto" data-agenda-list></div>
    </aside>
</div>

@push('scripts')
<script>
    function selectCalendarDay(dateKey) {
        const root = document.querySelector('[data-month-calendar]');
        const events = Object.values(window.recruitmentCalendarEvents || {})
            .filter(event => event.date === dateKey)
            .sort((a, b) => a.startMinutes - b.startMinutes);

        root.dataset.selected = dateKey;
        root.querySelectorAll('[data-month-day]').forEach(cell => {
            const selected = cell.dataset.monthDay === dateKey;
            cell.style.boxShadow = selected ? 'inset 0 0 0 2px var(--primary-color)' : '';
            cell.setAttribute('aria-pressed', String(selected));
        });

        const [year, month, day] = dateKey.split('-').map(Number);
        root.querySelector('[data-agenda-title]').textContent = new Date(year, month - 1, day)
            .toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
        root.querySelector('[data-agenda-day-link]').href = root.dataset.dayUrl.replace('__DATE__', dateKey);

        const list = root.querySelector('[data-agenda-list]');
        list.replaceChildren();

        if (!events.length) {
            const empty = document.createElement('p');
            empty.className = 'text-xs text-gray-400 text-center py-8';
            empty.textContent = 'Nothing scheduled on this day.';
            list.append(empty);
            return;
        }

        events.forEach(event => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'w-full flex gap-3 text-left rounded-lg px-2 py-2 hover:bg-gray-50 transition';
            item.addEventListener('click', () => openCalendarEvent(event.id));

            const bar = document.createElement('span');
            bar.className = 'w-1 rounded-full shrink-0';
            bar.style.background = event.type === 'interview' ? 'var(--primary-color)' : '#9ca3af';

            const time = document.createElement('span');
            time.className = 'w-14 shrink-0 text-xs';
            const start = document.createElement('span');
            start.className = 'block text-gray-800';
            start.textContent = event.start;
            const duration = document.createElement('span');
            duration.className = 'block text-[11px] text-gray-400';
            duration.textContent = event.duration;
            time.append(start, duration);

            const text = document.createElement('span');
            text.className = 'min-w-0 text-xs';
            const title = document.createElement('span');
            title.className = 'block font-semibold text-gray-800 truncate';
            title.textContent = event.title;
            const people = document.createElement('span');
            people.className = 'block text-[11px] text-gray-500 truncate';
            people.textContent = event.participants.map(person => person.name).join(', ');
            text.append(title, people);

            item.append(bar, time, text);
            list.append(item);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-month-calendar]');
        selectCalendarDay(root.dataset.selected);

        root.querySelector('[data-agenda-add]')?.addEventListener('click', () => {
            openCandidateModal({ mode: 'existing', date: root.dataset.selected });
        });
    });
</script>
@endpush
