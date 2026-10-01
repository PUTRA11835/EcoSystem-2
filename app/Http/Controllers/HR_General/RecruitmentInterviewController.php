<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\Interview;
use App\Services\Recruitment\InterviewScheduler;
use App\Services\Recruitment\RecruitmentCalendar;
use App\Services\Recruitment\RecruitmentFormOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The Schedule tab: a calendar over the interviews. It holds no data of its
 * own — every event is a row of `recruitment_interviews`, the same rows the
 * candidate page lists — so an interview scheduled, moved or cancelled from
 * either place is the same change, seen from both.
 */
class RecruitmentInterviewController extends Controller
{
    /** view => label, in the order of the view switcher. */
    public const VIEWS = [
        'day'      => 'Day',
        'workweek' => 'Work week',
        'week'     => 'Week',
        'month'    => 'Month',
    ];

    public function index(Request $request, RecruitmentCalendar $calendar)
    {
        $view = isset(self::VIEWS[$request->query('view')]) ? $request->query('view') : 'month';
        $date = $this->resolveDate($request->query('date'));

        // The mini month and the month view share one grid of whole Sunday-first
        // weeks; every other view is a slice of it, so one read serves them all.
        $gridStart = $date->clone()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $date->clone()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $days = match ($view) {
            'day'      => collect([$date->clone()]),
            'workweek' => $this->daysFrom($date->clone()->startOfWeek(Carbon::MONDAY), 5),
            'week'     => $this->daysFrom($date->clone()->startOfWeek(Carbon::SUNDAY), 7),
            'month'    => $this->daysFrom($gridStart, (int) $gridStart->diffInDays($gridEnd) + 1),
        };

        $step = fn (int $direction) => match ($view) {
            'day'   => $date->clone()->addDays($direction),
            'month' => $date->clone()->startOfMonth()->addMonthsNoOverflow($direction),
            default => $date->clone()->addWeeks($direction),
        };

        return view('hr-general.recruitment.schedule', [
            'view'      => $view,
            'views'     => self::VIEWS,
            'date'      => $date,
            'days'      => $days,
            'monthDays' => $this->daysFrom($gridStart, (int) $gridStart->diffInDays($gridEnd) + 1),
            'previous'  => $step(-1),
            'next'      => $step(1),
            'rangeLabel' => $this->rangeLabel($view, $date, $days),
            'calendar'  => $calendar->between($gridStart, $gridEnd),
            // Prefill for the "Schedule Interview" modal when arriving from a candidate's page.
            'prefillCandidateId' => $request->integer('candidate_id') ?: null,
            ...RecruitmentFormOptions::forCandidateModal(),
        ]);
    }

    public function store(Request $request, InterviewScheduler $scheduler)
    {
        $data = $request->validate([
            'candidate_id' => 'required|exists:recruitment_candidates,id',
            ...InterviewScheduler::rules('interview.'),
        ], [], InterviewScheduler::attributeNames('interview.'));

        $candidate = Candidate::findOrFail($data['candidate_id']);

        if ($candidate->isHired()) {
            return back()->with('error', 'This candidate has already been hired — no further interviews can be scheduled.');
        }

        $scheduler->schedule($candidate, $data['interview']);

        return $this->respond('Interview scheduled.', $scheduler);
    }

    public function update(Request $request, Interview $interview, InterviewScheduler $scheduler)
    {
        if (!$interview->isPending()) {
            return back()->with('error', 'A cancelled interview cannot be rescheduled — schedule a new one instead.');
        }

        $data = $request->validate(
            InterviewScheduler::rules('interview.'), [], InterviewScheduler::attributeNames('interview.')
        );

        $scheduler->reschedule($interview, $data['interview']);

        return $this->respond('Interview schedule updated.', $scheduler);
    }

    public function cancel(Interview $interview, InterviewScheduler $scheduler)
    {
        if (!$interview->isUpcoming()) {
            return back()->with('error', 'Only an interview that has not taken place yet can be cancelled.');
        }

        $scheduler->cancel($interview);

        return $this->respond('Interview cancelled.', $scheduler);
    }

    /** Push one interview to the outside calendar again — for an event showing "Not on Outlook yet". */
    public function sync(Interview $interview, InterviewScheduler $scheduler)
    {
        if (!$interview->isUpcoming()) {
            return back()->with('error', 'Only an interview that has not taken place yet can be synced.');
        }

        return $scheduler->resync($interview)
            ? back()->with('success', 'The interview is now on the Outlook calendar.')
            : back()->with('error', $scheduler->syncWarning());
    }

    private function resolveDate(?string $date): Carbon
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
            } catch (\Throwable) {
                // An impossible date in the URL simply falls back to today.
            }
        }

        return now()->startOfDay();
    }

    private function daysFrom(Carbon $start, int $count)
    {
        return collect(range(0, $count - 1))->map(fn (int $offset) => $start->clone()->addDays($offset));
    }

    private function rangeLabel(string $view, Carbon $date, $days): string
    {
        $first = $days->first();
        $last = $days->last();

        return match (true) {
            $view === 'month' => $date->format('F Y'),
            $view === 'day'   => $date->format('l, d F Y'),
            $first->isSameMonth($last) => $first->format('F j') . ' – ' . $last->format('j, Y'),
            $first->isSameYear($last)  => $first->format('F j') . ' – ' . $last->format('F j, Y'),
            default => $first->format('M j, Y') . ' – ' . $last->format('M j, Y'),
        };
    }

    private function respond(string $message, InterviewScheduler $scheduler)
    {
        $redirect = back()->with('success', $message);

        return $scheduler->syncWarning() ? $redirect->with('warning', $scheduler->syncWarning()) : $redirect;
    }
}
