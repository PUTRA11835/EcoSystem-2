<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\Interview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates, reschedules and cancels interviews. The Selection Process page
 * (add candidate + first interview) and the Schedule page both go through
 * here, so an interview behaves the same wherever it was entered:
 *
 *   - scheduling a stage moves the candidate to the status of that stage;
 *   - the calendar source chosen in Settings is kept in step, best-effort —
 *     with the internal calendar there is nothing to do; with an outside one
 *     (Microsoft Outlook) a failure never fails the save, it is reported
 *     through syncWarning() instead.
 */
class InterviewScheduler
{
    private ?string $syncWarning = null;

    public function __construct(private CalendarProviders $providers)
    {
    }

    /**
     * Validation rules for the interview fields of a form. The form asks for a
     * date with a start and an end time; the duration is derived from them.
     *
     * @param  string  $prefix  e.g. "interview." when nested in a candidate form
     */
    public static function rules(string $prefix = ''): array
    {
        return [
            "{$prefix}title"             => 'nullable|string|max:200',
            "{$prefix}stage"             => 'required|in:' . implode(',', array_keys(Interview::STAGES)),
            "{$prefix}date"              => 'required|date_format:Y-m-d',
            "{$prefix}start_time"        => 'required|date_format:H:i',
            "{$prefix}end_time"          => "required|date_format:H:i|after:{$prefix}start_time",
            "{$prefix}interviewer_ids"   => 'nullable|array',
            "{$prefix}interviewer_ids.*" => 'integer|exists:employee,employee_id',
            "{$prefix}mode"              => 'required|in:online,onsite',
            "{$prefix}location"          => 'nullable|string|max:255',
            "{$prefix}meeting_url"       => 'nullable|url|max:500',
            "{$prefix}notes"             => 'nullable|string',
        ];
    }

    /** Human-readable names for the nested fields, so error messages do not read "interview.end time". */
    public static function attributeNames(string $prefix = ''): array
    {
        return [
            "{$prefix}title"       => 'event title',
            "{$prefix}stage"       => 'stage',
            "{$prefix}date"        => 'interview date',
            "{$prefix}start_time"  => 'start time',
            "{$prefix}end_time"    => 'end time',
            "{$prefix}mode"        => 'mode',
            "{$prefix}location"    => 'location',
            "{$prefix}meeting_url" => 'meeting link',
            "{$prefix}notes"       => 'interview notes',
        ];
    }

    public function schedule(Candidate $candidate, array $data): Interview
    {
        $interview = DB::transaction(function () use ($candidate, $data) {
            $interview = $candidate->interviews()->create([
                ...$this->attributes($data),
                'status'                 => Interview::STATUS_SCHEDULED,
                'created_by_employee_id' => session('user.id'),
            ]);

            $interview->interviewers()->sync($data['interviewer_ids'] ?? []);
            $this->advanceCandidate($candidate, $interview);

            return $interview;
        });

        $this->sync($interview, fn (MicrosoftGraphCalendarService $calendar) => $calendar->createEvent($interview));

        return $interview;
    }

    public function reschedule(Interview $interview, array $data): Interview
    {
        DB::transaction(function () use ($interview, $data) {
            $interview->update([
                ...$this->attributes($data),
                'status' => Interview::STATUS_RESCHEDULED,
            ]);

            $interview->interviewers()->sync($data['interviewer_ids'] ?? []);

            // Moving the date of an old interview must not pull a candidate who
            // has since progressed back to that interview's stage.
            if ($interview->wasChanged('stage')) {
                $this->advanceCandidate($interview->candidate, $interview);
            }
        });

        $interview->unsetRelation('interviewers');
        $this->sync($interview, fn (MicrosoftGraphCalendarService $calendar) => $calendar->updateEvent($interview));

        return $interview;
    }

    public function cancel(Interview $interview): void
    {
        $interview->update(['status' => Interview::STATUS_CANCELLED]);

        $this->sync($interview, fn (MicrosoftGraphCalendarService $calendar) => $calendar->deleteEvent($interview));
    }

    /**
     * Push an interview to the outside calendar on request — for one whose
     * automatic sync failed, or that was scheduled while the internal
     * calendar was in use. Returns whether the outside calendar now has it.
     */
    public function resync(Interview $interview): bool
    {
        $this->sync($interview, fn (MicrosoftGraphCalendarService $calendar) => $calendar->updateEvent($interview));

        return $this->syncWarning === null;
    }

    /** Set when the last operation saved fine but could not be mirrored to Teams. */
    public function syncWarning(): ?string
    {
        return $this->syncWarning;
    }

    private function attributes(array $data): array
    {
        $online = $data['mode'] === 'online';

        return [
            'title'            => $data['title'] ?? null,
            'stage'            => $data['stage'],
            'scheduled_at'     => Carbon::createFromFormat('Y-m-d H:i', "{$data['date']} {$data['start_time']}"),
            'duration_minutes' => self::minutesBetween($data['start_time'], $data['end_time']),
            'mode'             => $data['mode'],
            'location'         => $online ? null : ($data['location'] ?? null),
            'meeting_url'      => $online ? ($data['meeting_url'] ?? null) : null,
            'notes'            => $data['notes'] ?? null,
        ];
    }

    private static function minutesBetween(string $start, string $end): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));

        return ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);
    }

    /** A hired candidate's status is owned by the Offer workflow and is never moved back by a schedule change. */
    private function advanceCandidate(?Candidate $candidate, Interview $interview): void
    {
        if ($candidate && !$candidate->isHired()) {
            $candidate->transitionTo($interview->stage);
        }
    }

    private function sync(Interview $interview, \Closure $action): void
    {
        $this->syncWarning = null;
        $calendar = $this->providers->external();

        // The internal calendar is the interviews table itself: nothing to mirror.
        if (!$calendar) {
            return;
        }

        try {
            $action($calendar);
        } catch (\Throwable $e) {
            Log::error('Recruitment: calendar sync failed', [
                'interview_id' => $interview->id,
                'error'        => $e->getMessage(),
            ]);
            $this->syncWarning = 'The schedule was saved, but it is not on the Outlook calendar yet: ' . $e->getMessage();
        }
    }
}
