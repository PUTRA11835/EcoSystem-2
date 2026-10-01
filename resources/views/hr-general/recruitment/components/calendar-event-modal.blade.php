{{--
    The detail card of a calendar event, modelled on Outlook's: title, time,
    where, the participants (candidate and interviewers, the people who would
    be the attendees of the Outlook event) and the basics of the interview.

    It also carries the page's events to the browser once, as
    window.recruitmentCalendarEvents[id]; every calendar component opens this
    card with openCalendarEvent(id).

    Actions follow the person's role on the Schedule tab. "Reschedule" hands
    the interview to components/reschedule-modal, which the page must include
    when $canEdit is true.

    Parameters:
      $events           — Collection of event arrays
      $canEdit          — may reschedule / sync
      $canCancel        — may cancel
      $canOpenCandidate — may open the candidate page
      $dayUrl           — optional; Day view URL with "__DATE__", shown as "Open in Schedule"
--}}
@php
    $canEdit = $canEdit ?? false;
    $canCancel = $canCancel ?? false;
    $dayUrl = $dayUrl ?? null;
@endphp
<div id="calendarEventModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4"
    onclick="if (event.target === this) closeCalendarEvent()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md max-h-[92vh] flex flex-col overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="calEventTitle">
        <div id="calEventStripe" class="h-1.5 shrink-0"></div>
        <div class="px-5 pt-4 pb-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p id="calEventType" class="text-[10px] font-bold uppercase tracking-wider text-gray-400"></p>
                <h3 id="calEventTitle" class="text-base font-bold text-gray-900 break-words"></h3>
            </div>
            <button type="button" onclick="closeCalendarEvent()" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="px-5 pb-4 space-y-3 text-xs overflow-y-auto">
            <div class="flex gap-3">
                <i class="fas fa-clock w-4 mt-0.5 text-gray-400 text-center"></i>
                <div>
                    <p id="calEventWhen" class="font-semibold text-gray-800"></p>
                    <p id="calEventDuration" class="text-gray-500"></p>
                </div>
            </div>
            <div class="flex gap-3" id="calEventWhereRow">
                <i class="fas fa-location-dot w-4 mt-0.5 text-gray-400 text-center"></i>
                <div class="min-w-0">
                    <p id="calEventWhere" class="text-gray-800 break-words"></p>
                    <a id="calEventJoin" href="#" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1.5 mt-1.5 px-3 py-1.5 primary-gradient text-white font-semibold rounded-lg hover:opacity-90">
                        <i class="fas fa-video"></i> Join meeting
                    </a>
                </div>
            </div>
            <div class="flex gap-3" id="calEventPeopleRow">
                <i class="fas fa-user-group w-4 mt-0.5 text-gray-400 text-center"></i>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-800 mb-1">Participants</p>
                    <ul id="calEventPeople" class="space-y-1.5"></ul>
                </div>
            </div>
            <div class="flex gap-3" id="calEventInfoRow">
                <i class="fas fa-circle-info w-4 mt-0.5 text-gray-400 text-center"></i>
                <dl id="calEventInfo" class="min-w-0 flex-1 space-y-1"></dl>
            </div>
            <div class="flex gap-3" id="calEventNotesRow">
                <i class="fas fa-align-left w-4 mt-0.5 text-gray-400 text-center"></i>
                <p id="calEventNotes" class="text-gray-700 whitespace-pre-line break-words"></p>
            </div>
            <div class="flex gap-3" id="calEventSyncRow">
                <i class="fab fa-microsoft w-4 mt-0.5 text-gray-400 text-center"></i>
                <div>
                    <p id="calEventSync" class="text-gray-700"></p>
                    @if($canEdit)
                        <button type="button" id="calEventSyncButton" class="font-semibold hover:underline" style="color: var(--primary-color);">Send to Outlook now</button>
                    @endif
                </div>
            </div>
        </div>

        <div id="calEventActions" class="px-5 py-3 border-t border-gray-100 flex flex-wrap justify-end gap-2">
            @if($dayUrl)
                <a id="calEventDayLink" href="#" class="px-3 py-2 text-xs font-semibold border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50">Open in Schedule</a>
            @endif
            @if($canOpenCandidate ?? false)
                <a id="calEventCandidate" href="#" class="px-3 py-2 text-xs font-semibold border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50">Open candidate</a>
            @endif
            @if($canCancel)
                <button type="button" id="calEventCancel" class="px-3 py-2 text-xs font-semibold border border-red-200 text-red-600 rounded-lg hover:bg-red-50">Cancel interview</button>
            @endif
            @if($canEdit)
                <button type="button" id="calEventReschedule" class="px-3 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">
                    <i class="fas fa-pen text-[10px]"></i> Reschedule
                </button>
            @endif
        </div>
    </div>
</div>

{{-- Cancel and sync post to the interview's own URL; the action is set when the card opens. --}}
@if($canCancel || $canEdit)
    <form id="calendarEventActionForm" method="POST" class="hidden">@csrf</form>
@endif

@push('scripts')
<script>
    window.recruitmentCalendarEvents = @json($events->keyBy('id'));

    (function () {
        const byId = id => document.getElementById(id);
        const dayUrl = @json($dayUrl);
        let current = null;

        const show = (id, visible) => byId(id)?.classList.toggle('hidden', !visible);

        function postTo(url) {
            const form = byId('calendarEventActionForm');
            form.action = url;
            form.submit();
        }

        window.closeCalendarEvent = function () {
            byId('calendarEventModal').classList.add('hidden');
        };

        window.openCalendarEvent = function (id) {
            const event = window.recruitmentCalendarEvents[id];
            if (!event) return;
            current = event;
            const isInterview = event.type === 'interview';

            byId('calEventStripe').style.background = isInterview ? 'var(--primary-color)' : '#9ca3af';
            byId('calEventType').textContent = isInterview ? 'Recruitment interview' : 'Outlook calendar event';
            byId('calEventTitle').textContent = event.title;
            byId('calEventWhen').textContent = event.when;
            byId('calEventDuration').textContent = event.duration;

            show('calEventWhereRow', !!(event.where || event.joinUrl));
            byId('calEventWhere').textContent = event.where || '';
            show('calEventJoin', !!event.joinUrl);
            if (event.joinUrl) byId('calEventJoin').href = event.joinUrl;

            const people = byId('calEventPeople');
            people.replaceChildren(...event.participants.map(person => {
                const item = document.createElement('li');
                item.className = 'flex items-center gap-2';

                const avatar = document.createElement('span');
                avatar.className = 'w-6 h-6 rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold flex items-center justify-center shrink-0';
                avatar.textContent = person.name.trim().charAt(0).toUpperCase();

                const text = document.createElement('span');
                text.className = 'min-w-0 flex-1';
                const name = document.createElement('span');
                name.className = 'block text-gray-800 truncate';
                name.textContent = person.name;
                text.append(name);
                if (person.detail) {
                    const detail = document.createElement('span');
                    detail.className = 'block text-[11px] text-gray-400 truncate';
                    detail.textContent = person.detail;
                    text.append(detail);
                }

                const role = document.createElement('span');
                role.className = 'shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold '
                    + (person.role === 'Candidate' ? 'bg-amber-50 text-amber-700' : 'bg-gray-100 text-gray-600');
                role.textContent = person.role;

                item.append(avatar, text, role);
                return item;
            }));
            show('calEventPeopleRow', event.participants.length > 0);

            const info = [['Stage', event.stage], ['Position', event.position], ['Status', event.status]].filter(([, value]) => value);
            byId('calEventInfo').replaceChildren(...info.map(([label, value]) => {
                const row = document.createElement('div');
                row.className = 'flex gap-2';
                const dt = document.createElement('dt');
                dt.className = 'w-16 shrink-0 text-gray-500';
                dt.textContent = label;
                const dd = document.createElement('dd');
                dd.className = 'text-gray-800';
                dd.textContent = value;
                row.append(dt, dd);
                return row;
            }));
            show('calEventInfoRow', info.length > 0);

            show('calEventNotesRow', !!event.notes);
            byId('calEventNotes').textContent = event.notes || '';

            // Only reported while an outside calendar is the source.
            show('calEventSyncRow', event.synced !== null);
            byId('calEventSync').textContent = event.synced ? 'On the Outlook calendar — attendees have been invited.' : 'Not on the Outlook calendar yet.';
            show('calEventSyncButton', !!event.syncUrl);

            show('calEventCandidate', !!event.candidateUrl);
            if (event.candidateUrl && byId('calEventCandidate')) byId('calEventCandidate').href = event.candidateUrl;
            if (byId('calEventDayLink')) byId('calEventDayLink').href = dayUrl.replace('__DATE__', event.date);
            show('calEventReschedule', !!event.edit);
            show('calEventCancel', !!event.cancelUrl);

            const actions = byId('calEventActions');
            actions.classList.toggle('hidden', !Array.from(actions.children).some(el => !el.classList.contains('hidden')));

            byId('calendarEventModal').classList.remove('hidden');
        };

        byId('calEventReschedule')?.addEventListener('click', function () {
            closeCalendarEvent();
            openRescheduleModal(current.edit);
        });

        byId('calEventCancel')?.addEventListener('click', async function () {
            const message = 'Cancel "' + current.title + '"? It is removed from the calendar'
                + (current.synced ? ' and the attendees are notified by Outlook.' : '.');
            if (await showConfirm(message, 'Cancel Interview', 'danger', { okText: 'Cancel interview', cancelText: 'Keep' })) {
                postTo(current.cancelUrl);
            }
        });

        byId('calEventSyncButton')?.addEventListener('click', () => postTo(current.syncUrl));

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCalendarEvent(); });
    })();
</script>
@endpush
