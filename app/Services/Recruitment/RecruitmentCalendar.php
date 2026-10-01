<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\Interview;
use App\Models\Recruitment\RecruitmentSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Builds the events every Recruitment calendar shows — the week on the
 * Dashboard and the day / week / month views of the Schedule tab.
 *
 * The interviews in the database are always the base. When the calendar
 * source chosen in Settings is an outside one (Microsoft Outlook), that
 * account's other events are merged in: a mirrored interview exists on both
 * sides and is shown once, as the interview; everything else is shown as a
 * plain calendar event. If the outside calendar cannot be read the calendar
 * still renders from the interviews, with `error` saying what is missing.
 *
 * Events are plain arrays, ready to be handed to the page as JSON.
 */
class RecruitmentCalendar
{
    public function __construct(private CalendarProviders $providers)
    {
    }

    /**
     * @return array{events: Collection, error: ?string, external: bool, provider: string, mailbox: ?string}
     *         `events` is a flat list sorted by start time.
     */
    public function between(Carbon $from, Carbon $to): array
    {
        $provider = $this->providers->external();
        $external = $provider !== null;

        $interviews = Interview::with(['candidate.position', 'candidate.jobOpening', 'interviewers.basicData'])
            ->pending()
            ->whereBetween('scheduled_at', [$from, $to])
            ->get();

        [$outsideEvents, $error] = $provider ? $this->outsideEvents($provider, $from, $to) : [[], null];
        $mirroredIds = $interviews->pluck('ms_graph_event_id')->filter()->flip();

        $events = $interviews
            ->map(fn (Interview $interview) => self::interviewEvent($interview, $external))
            ->concat(
                collect($outsideEvents)
                    ->reject(fn (array $event) => $mirroredIds->has($event['id']))
                    ->map(fn (array $event) => self::outsideEvent($event))
            )
            ->sortBy(fn (array $event) => $event['date'] . sprintf('%04d', $event['startMinutes']))
            ->values();

        return [
            'events'   => $events,
            'error'    => $error,
            'external' => $external,
            'provider' => $this->providers->currentKey(),
            'mailbox'  => RecruitmentSetting::current()->effectiveOrganizerEmail(),
        ];
    }

    /**
     * One interview as a calendar event.
     *
     * @param  bool  $external  whether an outside calendar is in use — only then is a sync state reported
     */
    public static function interviewEvent(Interview $interview, bool $external = false): array
    {
        $candidate = $interview->candidate;

        $participants = [];
        if ($candidate) {
            $participants[] = ['name' => $candidate->name, 'role' => 'Candidate', 'detail' => $candidate->email];
        }
        foreach ($interview->interviewers as $interviewer) {
            $participants[] = ['name' => $interviewer->basicData->full_name ?? $interviewer->eci, 'role' => 'Interviewer', 'detail' => null];
        }

        return [
            ...self::timing($interview->scheduled_at, $interview->endsAt()),
            'id'           => "interview-{$interview->id}",
            'type'         => 'interview',
            'title'        => $interview->displayTitle(),
            'where'        => $interview->whereLabel(),
            'joinUrl'      => $interview->joinUrl(),
            'stage'        => $interview->stageLabel(),
            'status'       => $interview->statusLabel(),
            'position'     => $candidate?->positionLabel(),
            'notes'        => $interview->notes,
            'participants' => $participants,
            'candidateUrl' => $candidate ? route('general.recruitment.candidates.show', $candidate) : null,
            'edit'         => $interview->isPending() ? self::editPayload($interview) : null,
            'cancelUrl'    => $interview->isUpcoming() ? route('general.recruitment.schedule.cancel', $interview) : null,
            'synced'       => $external ? $interview->hasTeamsSync() : null,
            'syncUrl'      => $external && !$interview->hasTeamsSync() && $interview->isUpcoming()
                ? route('general.recruitment.schedule.sync', $interview)
                : null,
        ];
    }

    /**
     * What the "Reschedule" form needs to open on an interview. Used by the
     * calendar and by the candidate page, so both edit the same record
     * through the same form.
     */
    public static function editPayload(Interview $interview): array
    {
        return [
            'action'          => route('general.recruitment.schedule.update', $interview),
            'candidate'       => $interview->candidate->name ?? '',
            'title'           => $interview->title,
            'stage'           => $interview->stage,
            'date'            => $interview->scheduled_at->format('Y-m-d'),
            'start_time'      => $interview->scheduled_at->format('H:i'),
            'end_time'        => $interview->endsAt()->format('H:i'),
            'mode'            => $interview->mode,
            'location'        => $interview->location,
            'meeting_url'     => $interview->meeting_url,
            'notes'           => $interview->notes,
            'interviewer_ids' => $interview->interviewers->pluck('employee_id')->all(),
        ];
    }

    /**
     * Side-by-side placement for events that overlap within one day of the
     * time grid: each event gets a `lane` and the number of `lanes` its
     * overlap group needs, so the grid can split the column between them.
     *
     * @param  Collection<int, array>  $dayEvents  events of ONE day
     * @return Collection<int, array>  the same events, sorted, with `lane` and `lanes` added
     */
    public static function lanes(Collection $dayEvents): Collection
    {
        $placed = [];
        $group = [];      // indexes into $placed of the current overlap group
        $groupEnd = -1;
        $laneEnds = [];   // lane => minute it becomes free

        $closeGroup = function () use (&$placed, &$group, &$laneEnds) {
            foreach ($group as $index) {
                $placed[$index]['lanes'] = count($laneEnds);
            }
            $group = [];
            $laneEnds = [];
        };

        foreach ($dayEvents->sortBy('startMinutes')->values() as $event) {
            if ($group && $event['startMinutes'] >= $groupEnd) {
                $closeGroup();
            }

            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $event['startMinutes']) {
                $lane++;
            }

            // A very short event still occupies the height of its label.
            $laneEnds[$lane] = max($event['endMinutes'], $event['startMinutes'] + 30);
            $groupEnd = max($groupEnd, $laneEnds[$lane]);

            $placed[] = [...$event, 'lane' => $lane, 'lanes' => 1];
            $group[] = array_key_last($placed);
        }

        $closeGroup();

        return collect($placed);
    }

    private static function outsideEvent(array $event): array
    {
        return [
            ...self::timing($event['start'], $event['end']),
            'id'           => 'outlook-' . md5($event['id']),
            'type'         => 'outlook',
            'title'        => $event['subject'],
            'where'        => $event['location'],
            'joinUrl'      => $event['join_url'],
            'stage'        => null,
            'status'       => null,
            'position'     => null,
            'notes'        => null,
            'participants' => array_map(
                fn (string $name) => ['name' => $name, 'role' => 'Attendee', 'detail' => null],
                $event['attendees']
            ),
            'candidateUrl' => null,
            'edit'         => null,
            'cancelUrl'    => null,
            'synced'       => null,
            'syncUrl'      => null,
        ];
    }

    /** The time fields shared by every event; an event running past midnight is cut at the end of its day. */
    private static function timing(Carbon $start, Carbon $end): array
    {
        $startMinutes = $start->hour * 60 + $start->minute;
        $endMinutes = $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 24 * 60;
        $minutes = max(0, (int) $start->diffInMinutes($end));

        return [
            'date'         => $start->format('Y-m-d'),
            'start'        => $start->format('H:i'),
            'end'          => $end->format('H:i'),
            'startMinutes' => $startMinutes,
            'endMinutes'   => max($endMinutes, $startMinutes),
            'when'         => $start->format('D, d M Y') . ' · ' . $start->format('H:i') . '–' . $end->format('H:i'),
            'duration'     => self::durationLabel($minutes),
        ];
    }

    private static function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? "{$hours} h " : '') . ($rest || !$hours ? "{$rest} min" : ''));
    }

    /** @return array{0: array, 1: ?string} events, and why they are missing if they are */
    private function outsideEvents(MicrosoftGraphCalendarService $provider, Carbon $from, Carbon $to): array
    {
        if ($reason = $provider->unavailableReason()) {
            return [[], $reason . ' Until then only interviews recorded here are shown.'];
        }

        try {
            return [$provider->listBetween($from, $to), null];
        } catch (\Throwable $e) {
            Log::error('Recruitment: failed to load the outside calendar', ['error' => $e->getMessage()]);

            return [[], $e->getMessage() . ' Until then only interviews recorded here are shown.'];
        }
    }
}
