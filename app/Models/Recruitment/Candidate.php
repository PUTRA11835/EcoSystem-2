<?php

namespace App\Models\Recruitment;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $table = 'recruitment_candidates';

    protected $fillable = [
        'job_opening_id', 'position_id', 'source_id', 'source_detail', 'name', 'email', 'phone',
        'status', 'notes', 'hired_employee_id',
    ];

    public const STATUS_HR_INTERVIEW   = 'interview_hr';
    public const STATUS_USER_INTERVIEW = 'interview_user';
    public const STATUS_TECH_TEST      = 'tes_teknis';
    public const STATUS_OFFER          = 'offering';
    public const STATUS_REJECTED       = 'ditolak';
    public const STATUS_HIRED          = 'diterima';

    // Selection pipeline order, used for the charts & status dropdowns.
    public const STATUSES = [
        self::STATUS_HR_INTERVIEW   => 'HR Interview',
        self::STATUS_USER_INTERVIEW => 'User Interview',
        self::STATUS_TECH_TEST      => 'Technical Test',
        self::STATUS_OFFER          => 'Offer',
        self::STATUS_REJECTED       => 'Rejected',
        self::STATUS_HIRED          => 'Hired',
    ];

    /**
     * Statuses HR may pick directly. "Hired" is intentionally excluded — it is
     * only reachable by accepting an Offer (see
     * RecruitmentOfferController::accept()), so an account is never created
     * without going through the offer letter step.
     */
    public const MANUAL_STATUSES = [
        self::STATUS_HR_INTERVIEW, self::STATUS_USER_INTERVIEW, self::STATUS_TECH_TEST,
        self::STATUS_OFFER, self::STATUS_REJECTED,
    ];

    /** Still somewhere in the pipeline — neither rejected nor hired. */
    public const ACTIVE_STATUSES = [
        self::STATUS_HR_INTERVIEW, self::STATUS_USER_INTERVIEW, self::STATUS_TECH_TEST, self::STATUS_OFFER,
    ];

    public const STATUS_BADGES = [
        self::STATUS_HR_INTERVIEW   => 'bg-blue-100 text-blue-700',
        self::STATUS_USER_INTERVIEW => 'bg-indigo-100 text-indigo-700',
        self::STATUS_TECH_TEST      => 'bg-purple-100 text-purple-700',
        self::STATUS_OFFER          => 'bg-amber-100 text-amber-700',
        self::STATUS_REJECTED       => 'bg-red-100 text-red-700',
        self::STATUS_HIRED          => 'bg-green-100 text-green-700',
    ];

    public function jobOpening()
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function source()
    {
        return $this->belongsTo(RecruitmentOption::class, 'source_id');
    }

    public function documents()
    {
        return $this->hasMany(CandidateDocument::class, 'candidate_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(CandidateStatusHistory::class, 'candidate_id');
    }

    public function interviews()
    {
        return $this->hasMany(Interview::class, 'candidate_id');
    }

    public function offers()
    {
        return $this->hasMany(Offer::class, 'candidate_id');
    }

    /** Most recent offer, if any — a candidate normally has at most one active offer. */
    public function latestOffer()
    {
        return $this->hasOne(Offer::class, 'candidate_id')->latestOfMany();
    }

    public function hiredEmployee()
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id', 'employee_id');
    }

    /**
     * Required documents of the candidate's job opening that are not attached
     * yet. Empty when no job opening is chosen — nothing is asked for then.
     *
     * @return \Illuminate\Support\Collection<int, RecruitmentOption>
     */
    public function missingRequiredDocuments()
    {
        if (!$this->jobOpening) {
            return collect();
        }

        $attached = $this->documents->pluck('document_type_id')->filter()->flip();

        return $this->jobOpening->requestedDocuments
            ->filter(fn (RecruitmentOption $type) => $type->pivot->is_required && !$attached->has($type->id))
            ->values();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isHired(): bool
    {
        return $this->status === self::STATUS_HIRED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status] ?? 'bg-gray-100 text-gray-600';
    }

    /** The position applied for; falls back to the job opening title for candidates entered before positions were tracked. */
    public function positionLabel(): string
    {
        return $this->position->name ?? $this->jobOpening->position_title ?? '-';
    }

    /**
     * The only way a candidate's status should change: it writes the history
     * row the timeline is built from. Returns false when nothing changed.
     */
    public function transitionTo(string $status): bool
    {
        if ($this->status === $status) {
            return false;
        }

        $from = $this->status;
        $this->update(['status' => $status]);
        $this->recordStatus($from, $status);

        return true;
    }

    public function recordStatus(?string $from, string $to): void
    {
        $this->statusHistories()->create([
            'from_status'            => $from,
            'to_status'              => $to,
            'changed_by_employee_id' => session('user.id'),
            'changed_at'             => now(),
        ]);
    }
}
