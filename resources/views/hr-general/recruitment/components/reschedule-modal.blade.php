{{--
    Reschedule / edit an existing interview — the one form behind "Reschedule"
    on a calendar event and on the candidate's page, so both change the same
    record. Open it with openRescheduleModal(interview), where `interview` is
    RecruitmentCalendar::editPayload().

    Expects $stages, $interviewers and $calendarExternal.
--}}
<div id="rescheduleModal" class="hidden fixed inset-0 bg-black bg-opacity-40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[92vh] flex flex-col">
        <form id="rescheduleForm" method="POST" class="flex flex-col min-h-0">
            @csrf
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-800">Reschedule Interview</h3>
                    <p id="rescheduleCandidate" class="text-[11px] text-gray-400 mt-0.5"></p>
                </div>
                <button type="button" onclick="closeRescheduleModal()" class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="px-5 py-4 overflow-y-auto">
                @include('hr-general.recruitment.components.interview-fields', ['prefix' => 'rs', 'useOld' => false])
            </div>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button type="button" onclick="closeRescheduleModal()" class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-xs font-semibold text-white primary-gradient rounded-lg hover:opacity-90">Save Schedule</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openRescheduleModal(interview) {
        const form = document.getElementById('rescheduleForm');
        const set = (id, value) => {
            const el = document.getElementById(id);
            el.value = value ?? '';
            el.dispatchEvent(new Event('change', { bubbles: true }));
        };

        form.action = interview.action;
        document.getElementById('rescheduleCandidate').textContent = interview.candidate;
        set('rsTitle', interview.title);
        set('rsStage', interview.stage);
        set('rsDate', interview.date);
        set('rsStart', interview.start_time);
        set('rsEnd', interview.end_time);
        set('rsMode', interview.mode);
        set('rsLocation', interview.location);
        set('rsMeetingUrl', interview.meeting_url);
        set('rsNotes', interview.notes);

        form.querySelectorAll('input[name="interview[interviewer_ids][]"]').forEach(box => {
            box.checked = interview.interviewer_ids.includes(Number(box.value));
        });
        form.querySelector('[data-interview-fields]').dispatchEvent(new Event('interview:sync'));

        document.getElementById('rescheduleModal').classList.remove('hidden');
    }

    function closeRescheduleModal() {
        document.getElementById('rescheduleModal').classList.add('hidden');
    }
</script>
@endpush
