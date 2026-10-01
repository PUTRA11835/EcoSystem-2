<?php

namespace App\Models\Recruitment;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Interview extends Model
{
    protected $table = 'recruitment_interviews';

    protected $fillable = [
        'candidate_id', 'title', 'stage', 'scheduled_at', 'duration_minutes',
        'mode', 'location', 'meeting_url', 'status',
        'ms_graph_event_id', 'teams_meeting_url', 'notes',
        'created_by_employee_id',
    ];

    protected $casts = [
        'scheduled_at'     => 'datetime',
        'duration_minutes' => 'integer',
    ];

    /**
     * Keyed by the candidate status the stage corresponds to: scheduling an
     * interview for a stage moves the candidate to the status of the same key.
     * "Offer" is intentionally not here — it is the Offer workflow
     * (RecruitmentOfferController), not a scheduled interview.
     */
    public const STAGES = [
        Candidate::STATUS_HR_INTERVIEW   => 'HR Interview',
        Candidate::STATUS_USER_INTERVIEW => 'User Interview',
        Candidate::STATUS_TECH_TEST      => 'Technical Test',
    ];

    /**
     * Stored statuses. There is deliberately no stored "completed": an
     * interview that was not cancelled is over once its end time has passed
     * (see displayStatus()), so nobody has to confirm it by hand.
     */
    public const STATUS_SCHEDULED   = 'scheduled';
    public const STATUS_RESCHEDULED = 'rescheduled';
    public const STATUS_CANCELLED   = 'cancelled';

    /** Statuses of an interview that was not called off. */
    public const PENDING_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_RESCHEDULED];

    /** What is shown to people — the stored status combined with the clock. */
    public const DISPLAY_STATUSES = [
        'scheduled'   => ['label' => 'Scheduled',   'badge' => 'bg-indigo-100 text-indigo-700'],
        'rescheduled' => ['label' => 'Rescheduled', 'badge' => 'bg-indigo-100 text-indigo-700'],
        'completed'   => ['label' => 'Completed',   'badge' => 'bg-green-100 text-green-700'],
        'cancelled'   => ['label' => 'Cancelled',   'badge' => 'bg-red-100 text-red-700'],
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function interviewers()
    {
        return $this->belongsToMany(Employee::class, 'recruitment_interview_interviewers', 'interview_id', 'employee_id', 'id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id', 'employee_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', self::PENDING_STATUSES);
    }

    public function isPending(): bool
    {
        return in_array($this->status, self::PENDING_STATUSES, true);
    }

    public function stageLabel(): string
    {
        return self::STAGES[$this->stage] ?? $this->stage;
    }

    /** True once the scheduled end time is behind us. */
    public function hasEnded(): bool
    {
        return $this->endsAt()->isPast();
    }

    /** Still ahead and not called off — the only interviews that can be rescheduled or cancelled. */
    public function isUpcoming(): bool
    {
        return $this->isPending() && !$this->hasEnded();
    }

    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return 'cancelled';
        }

        if ($this->hasEnded()) {
            return 'completed';
        }

        return $this->status === self::STATUS_RESCHEDULED ? 'rescheduled' : 'scheduled';
    }

    public function statusLabel(): string
    {
        return self::DISPLAY_STATUSES[$this->displayStatus()]['label'];
    }

    public function statusBadge(): string
    {
        return self::DISPLAY_STATUSES[$this->displayStatus()]['badge'];
    }

    public function endsAt(): Carbon
    {
        return $this->scheduled_at->clone()->addMinutes($this->duration_minutes ?: 60);
    }

    /** Interviewer names, comma separated — "-" when none assigned. */
    public function interviewerNames(): string
    {
        return $this->interviewers
            ->map(fn (Employee $e) => $e->basicData->full_name ?? $e->eci)
            ->implode(', ') ?: '-';
    }

    /** The event title: the one HR typed, or "{stage} - {candidate}" when left empty. */
    public function displayTitle(): string
    {
        return $this->title ?: $this->stageLabel() . ' - ' . ($this->candidate->name ?? 'Candidate');
    }

    /** Link to join an online interview: HR's own link if given, otherwise the generated Teams one. */
    public function joinUrl(): ?string
    {
        return $this->mode === 'online' ? ($this->meeting_url ?: $this->teams_meeting_url) : null;
    }

    public function whereLabel(): string
    {
        return $this->mode === 'online' ? 'Online' : ($this->location ?: 'Onsite');
    }

    /** Whether the interview has been mirrored to the outside calendar. */
    public function hasTeamsSync(): bool
    {
        return !empty($this->ms_graph_event_id);
    }
}
