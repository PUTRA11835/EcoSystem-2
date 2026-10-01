{{--
    The interview inputs, shared by the add-candidate / schedule-interview
    modal and the reschedule modal. All names are nested under `interview[...]`,
    matching InterviewScheduler::rules('interview.').

    The selected interviewers sit at the very top as chips, so who is on the
    panel is visible without scrolling the employee list.

    Parameters:
      $prefix          — unique id prefix for this instance
      $stages          — Interview::STAGES
      $interviewers    — list of ['id', 'name', 'has_email']
      $calendarExternal — whether an outside calendar (Outlook) sends the
                          invitations and generates the meeting link
      $useOld          — repopulate from old() after a failed validation
--}}
@php
    $old = fn (string $key, $default = null) => ($useOld ?? false) ? old("interview.{$key}", $default) : $default;
    $oldInterviewers = array_map('intval', (array) $old('interviewer_ids', []));
    $input = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-200';
    $label = 'block text-xs font-semibold text-gray-600 mb-1';
    $isOnsite = $old('mode') === 'onsite';
@endphp
<div class="space-y-3" data-interview-fields>

    {{-- Interviewers --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <span class="{{ $label }} mb-0">Interviewers</span>
            <span class="text-[11px] text-gray-400" data-interviewer-count></span>
        </div>
        <div class="min-h-[2.25rem] flex flex-wrap items-center gap-1.5 px-2 py-1.5 mb-2 bg-gray-50 border border-gray-200 rounded-lg" data-interviewer-chips aria-live="polite">
            <span class="text-xs text-gray-400" data-interviewer-empty>No interviewer selected yet — pick from the list below.</span>
        </div>
        <div class="border border-gray-200 rounded-lg">
            <div class="relative border-b border-gray-100">
                <input type="text" placeholder="Search employee…" data-interviewer-search aria-label="Search interviewers" autocomplete="off"
                    class="w-full pl-8 pr-3 py-2 text-sm rounded-t-lg focus:outline-none">
                <i class="fas fa-search text-[11px] absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            </div>
            <div class="max-h-32 overflow-y-auto p-2 space-y-0.5" data-interviewer-list>
                @foreach($interviewers as $interviewer)
                    <label class="flex items-center gap-2 text-xs text-gray-700 px-1 py-1 rounded hover:bg-gray-50 cursor-pointer" data-name="{{ $interviewer['name'] }}">
                        <input type="checkbox" name="interview[interviewer_ids][]" value="{{ $interviewer['id'] }}"
                            @checked(in_array((int) $interviewer['id'], $oldInterviewers, true))
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="flex-1 min-w-0 truncate">{{ $interviewer['name'] }}</span>
                        @if($calendarExternal && !$interviewer['has_email'])
                            <span class="shrink-0 px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-semibold" title="No email address on file — this person will not receive the calendar invitation.">no email</span>
                        @endif
                    </label>
                @endforeach
                <p class="hidden text-xs text-gray-400 px-1 py-1" data-interviewer-nomatch>No employee matches.</p>
            </div>
        </div>
        <p class="text-[11px] text-gray-400 mt-1">
            @if($calendarExternal)
                Interviewers and the candidate are invited by email, and the interview appears in their own Outlook / Teams calendar.
            @else
                The interviewers are listed as participants on the Schedule calendar. Email invitations start once the Outlook calendar is connected in Settings.
            @endif
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="sm:col-span-2">
            <label for="{{ $prefix }}Title" class="{{ $label }}">Event Title</label>
            <input type="text" name="interview[title]" id="{{ $prefix }}Title" maxlength="200" value="{{ $old('title') }}"
                placeholder="Optional — defaults to “Stage - Candidate name”" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $prefix }}Stage" class="{{ $label }}">Stage <span class="text-red-500">*</span></label>
            <select name="interview[stage]" id="{{ $prefix }}Stage" required class="{{ $input }}">
                @foreach($stages as $value => $stageLabel)
                    <option value="{{ $value }}" @selected($old('stage') === $value)>{{ $stageLabel }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="col-span-2 sm:col-span-1">
            <label for="{{ $prefix }}Date" class="{{ $label }}">Date <span class="text-red-500">*</span></label>
            <input type="date" name="interview[date]" id="{{ $prefix }}Date" required value="{{ $old('date') }}" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $prefix }}Start" class="{{ $label }}">Start Time <span class="text-red-500">*</span></label>
            <input type="time" name="interview[start_time]" id="{{ $prefix }}Start" required value="{{ $old('start_time') }}" data-interview-start class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $prefix }}End" class="{{ $label }}">End Time <span class="text-red-500">*</span></label>
            <input type="time" name="interview[end_time]" id="{{ $prefix }}End" required value="{{ $old('end_time') }}" data-interview-end class="{{ $input }}">
        </div>
        <div class="col-span-2 sm:col-span-1">
            <label for="{{ $prefix }}Mode" class="{{ $label }}">Mode <span class="text-red-500">*</span></label>
            <select name="interview[mode]" id="{{ $prefix }}Mode" required data-interview-mode class="{{ $input }}">
                <option value="online" @selected(!$isOnsite)>Online</option>
                <option value="onsite" @selected($isOnsite)>Onsite</option>
            </select>
        </div>
    </div>
    <p class="text-[11px] text-gray-400 -mt-1" data-interview-duration></p>

    {{-- One full-width field that follows the mode: a meeting link when online, a place when onsite. --}}
    <div data-interview-online class="{{ $isOnsite ? 'hidden' : '' }}">
        <label for="{{ $prefix }}MeetingUrl" class="{{ $label }}">Meeting Link</label>
        <input type="url" name="interview[meeting_url]" id="{{ $prefix }}MeetingUrl" maxlength="500" value="{{ $old('meeting_url') }}"
            placeholder="https://… (Google Meet, Zoom, …)" class="{{ $input }}">
        <p class="text-[11px] text-gray-400 mt-1">
            @if($calendarExternal)
                Leave empty to have a Microsoft Teams meeting link generated automatically.
            @else
                Paste the link of the meeting you created (Teams, Google Meet, Zoom, …). Once the Outlook calendar is connected, a Teams link is generated when this is left empty.
            @endif
        </p>
    </div>
    <div data-interview-onsite class="{{ $isOnsite ? '' : 'hidden' }}">
        <label for="{{ $prefix }}Location" class="{{ $label }}">Location</label>
        <input type="text" name="interview[location]" id="{{ $prefix }}Location" maxlength="255" value="{{ $old('location') }}"
            placeholder="Office address, floor, meeting room…" class="{{ $input }}">
    </div>

    <div>
        <label for="{{ $prefix }}Notes" class="{{ $label }}">Interview Notes</label>
        <textarea name="interview[notes]" id="{{ $prefix }}Notes" rows="2" placeholder="Shown on the calendar event."
            class="{{ $input }}">{{ $old('notes') }}</textarea>
    </div>
</div>

@once
@push('scripts')
<script>
    // Behaviour for every [data-interview-fields] block on the page.
    document.addEventListener('DOMContentLoaded', function () {
        const toMinutes = value => {
            const [h, m] = value.split(':').map(Number);
            return h * 60 + m;
        };
        const toTime = minutes => String(Math.floor(minutes / 60)).padStart(2, '0') + ':' + String(minutes % 60).padStart(2, '0');

        document.querySelectorAll('[data-interview-fields]').forEach(function (block) {
            const mode = block.querySelector('[data-interview-mode]');
            const online = block.querySelector('[data-interview-online]');
            const onsite = block.querySelector('[data-interview-onsite]');
            const start = block.querySelector('[data-interview-start]');
            const end = block.querySelector('[data-interview-end]');
            const durationText = block.querySelector('[data-interview-duration]');
            const search = block.querySelector('[data-interviewer-search]');
            const list = block.querySelector('[data-interviewer-list]');
            const chips = block.querySelector('[data-interviewer-chips]');
            const empty = block.querySelector('[data-interviewer-empty]');
            const count = block.querySelector('[data-interviewer-count]');
            // Only a starting suggestion for the end time; the interview lasts from start to end as entered.
            const suggestedMinutes = 60;
            // The end time follows the start time until someone sets it by hand.
            let endTouched = end.value !== '';

            function syncMode() {
                // While an enclosing form section is switched off ([data-block-off]) its inputs stay disabled.
                const blockOff = !!block.closest('[data-block-off]');
                const isOnsite = mode.value === 'onsite';
                onsite.classList.toggle('hidden', !isOnsite);
                online.classList.toggle('hidden', isOnsite);
                onsite.querySelector('input').disabled = blockOff || !isOnsite;
                online.querySelector('input').disabled = blockOff || isOnsite;
            }

            function syncDuration() {
                if (!start.value || !end.value) { durationText.textContent = ''; return; }
                const minutes = toMinutes(end.value) - toMinutes(start.value);
                if (minutes <= 0) {
                    durationText.textContent = 'The end time must be after the start time.';
                    durationText.classList.add('text-red-500');
                    return;
                }
                durationText.classList.remove('text-red-500');
                const h = Math.floor(minutes / 60), m = minutes % 60;
                durationText.textContent = 'Duration: ' + [h ? h + ' h' : '', m ? m + ' min' : ''].filter(Boolean).join(' ');
            }

            function syncInterviewers() {
                const labels = Array.from(list.querySelectorAll('label'));
                const picked = labels.filter(label => label.querySelector('input').checked);

                chips.querySelectorAll('[data-chip]').forEach(chip => chip.remove());
                picked.forEach(label => {
                    const chip = document.createElement('span');
                    chip.dataset.chip = '';
                    chip.className = 'inline-flex items-center gap-1 pl-2.5 pr-1 py-0.5 rounded-full bg-indigo-50 text-indigo-800 text-xs font-semibold';
                    chip.append(label.dataset.name);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'w-4 h-4 rounded-full hover:bg-indigo-200 flex items-center justify-center';
                    remove.setAttribute('aria-label', 'Remove ' + label.dataset.name);
                    remove.innerHTML = '<i class="fas fa-times text-[9px]"></i>';
                    remove.addEventListener('click', () => {
                        label.querySelector('input').checked = false;
                        list.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    chip.append(remove);
                    chips.append(chip);
                });

                empty.classList.toggle('hidden', picked.length > 0);
                count.textContent = picked.length ? picked.length + ' selected' : '';

                // Selected people also float to the top of the list; the rest stay alphabetical.
                labels
                    .sort((a, b) => (b.querySelector('input').checked - a.querySelector('input').checked)
                        || a.dataset.name.localeCompare(b.dataset.name))
                    .forEach(label => list.insertBefore(label, list.querySelector('[data-interviewer-nomatch]')));
            }

            mode.addEventListener('change', syncMode);
            list.addEventListener('change', syncInterviewers);
            end.addEventListener('input', () => { endTouched = end.value !== ''; syncDuration(); });
            start.addEventListener('input', () => {
                if (start.value && (!endTouched || !end.value)) {
                    end.value = toTime(Math.min(toMinutes(start.value) + suggestedMinutes, 23 * 60 + 59));
                }
                syncDuration();
            });
            search.addEventListener('input', function () {
                const term = search.value.trim().toLowerCase();
                let shown = 0;
                list.querySelectorAll('label').forEach(label => {
                    const match = term === '' || label.dataset.name.toLowerCase().includes(term);
                    label.classList.toggle('hidden', !match);
                    if (match) shown++;
                });
                list.querySelector('[data-interviewer-nomatch]').classList.toggle('hidden', shown > 0);
            });

            // Fired by whoever fills or shows the block (modal open, reschedule prefill).
            block.addEventListener('interview:sync', () => {
                endTouched = end.value !== '';
                syncMode(); syncDuration(); syncInterviewers();
            });
            syncMode(); syncDuration(); syncInterviewers();
        });
    });
</script>
@endpush
@endonce
