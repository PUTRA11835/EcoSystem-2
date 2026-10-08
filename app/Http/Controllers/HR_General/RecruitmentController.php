<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Recruitment\Candidate;
use App\Models\Recruitment\Interview;
use App\Models\Recruitment\JobOpening;
use App\Models\Recruitment\Offer;
use App\Services\Recruitment\RecruitmentCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecruitmentController extends Controller
{
    public function index(RecruitmentCalendar $calendar)
    {
        // Sunday-first, like the Outlook week this calendar stands in for.
        $weekStart = now()->startOfWeek(Carbon::SUNDAY);
        $weekEnd = $weekStart->clone()->endOfWeek(Carbon::SATURDAY);

        $statusCounts = Candidate::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalCandidates = (int) $statusCounts->sum();
        $rejected = (int) ($statusCounts[Candidate::STATUS_REJECTED] ?? 0);
        $offered = Offer::count();
        $accepted = Offer::where('decision', Offer::DECISION_ACCEPTED)->count();

        $stats = [
            // Only interviews still ahead, for candidates who are actually at an interview stage.
            'scheduled_interviews' => Interview::pending()
                ->where('scheduled_at', '>=', now())
                ->whereHas('candidate', fn ($q) => $q->whereIn('status', [
                    Candidate::STATUS_HR_INTERVIEW, Candidate::STATUS_USER_INTERVIEW,
                ]))
                ->count(),
            'active_candidates' => (int) $statusCounts->only(Candidate::ACTIVE_STATUSES)->sum(),
            'open_jobs'         => JobOpening::open()->count(),
            'offered'           => $offered,
            'accepted'          => $accepted,
            'acceptance_rate'   => $offered > 0 ? round($accepted / $offered * 100) : null,
            'total_candidates'  => $totalCandidates,
            'rejected'          => $rejected,
            'rejection_rate'    => $totalCandidates > 0 ? round($rejected / $totalCandidates * 100) : null,
        ];

        return view('hr-general.recruitment.index', [
            'stats'     => $stats,
            'weekStart' => $weekStart,
            'weekDays'  => collect(range(0, 6))->map(fn ($i) => $weekStart->clone()->addDays($i)),
            'calendar'  => $calendar->between($weekStart, $weekEnd),
            'charts'    => [
                'status'   => $this->statusChart($statusCounts),
                'source'   => $this->groupedChart('recruitment_options', 'source_id', 'No source', 'source_detail'),
                'position' => $this->groupedChart('positions', 'position_id', 'No position'),
            ],
        ]);
    }

    /** Candidates per selection status, in pipeline order (zeroes kept so the funnel shape stays readable). */
    private function statusChart($statusCounts): array
    {
        return [
            'labels' => array_values(Candidate::STATUSES),
            'values' => collect(Candidate::STATUSES)->keys()->map(fn ($s) => (int) ($statusCounts[$s] ?? 0))->all(),
        ];
    }

    /** Candidates per related master row (source platform / position), largest first, top 10. */
    private function groupedChart(string $table, string $foreignKey, string $emptyLabel, ?string $otherColumn = null): array
    {
        // A typed-in source has no platform row; it is counted as "Other" rather than "No source".
        $empty = $otherColumn
            ? "case when c.{$otherColumn} is not null then 'Other' else '{$emptyLabel}' end"
            : "'{$emptyLabel}'";

        $rows = DB::table('recruitment_candidates as c')
            ->leftJoin("{$table} as t", 't.id', '=', "c.{$foreignKey}")
            ->select(DB::raw("coalesce(t.name, {$empty}) as label"), DB::raw('count(*) as total'))
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'labels' => $rows->pluck('label')->all(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }
}
