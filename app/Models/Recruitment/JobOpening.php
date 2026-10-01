<?php

namespace App\Models\Recruitment;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One posting of a vacancy on one platform. Whether it is open is never
 * stored — it is derived from `opens_at` / `closes_at` against the current
 * time, so a posting closes by itself once its deadline passes.
 */
class JobOpening extends Model
{
    protected $table = 'recruitment_job_openings';

    protected $fillable = [
        'request_number', 'position_title', 'platform_id', 'posting_url',
        'department_id', 'position_id', 'employment_type_id', 'location',
        'opens_at', 'closes_at', 'quota', 'salary_min', 'salary_max',
        'recruiter_employee_id', 'description', 'requirements',
        'publish_to_website', 'website_published_at', 'created_by_employee_id',
    ];

    protected $casts = [
        'opens_at'             => 'datetime',
        'closes_at'            => 'datetime',
        'website_published_at' => 'datetime',
        'publish_to_website'   => 'boolean',
        'quota'                => 'integer',
        'salary_min'           => 'decimal:2',
        'salary_max'           => 'decimal:2',
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN      = 'open';
    public const STATUS_CLOSED    = 'closed';

    public const STATUSES = [
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_OPEN      => 'Open',
        self::STATUS_CLOSED    => 'Closed',
    ];

    public const STATUS_BADGES = [
        self::STATUS_SCHEDULED => 'bg-amber-100 text-amber-700',
        self::STATUS_OPEN      => 'bg-green-100 text-green-700',
        self::STATUS_CLOSED    => 'bg-gray-100 text-gray-500',
    ];

    public function candidates()
    {
        return $this->hasMany(Candidate::class, 'job_opening_id');
    }

    public function platform()
    {
        return $this->belongsTo(RecruitmentOption::class, 'platform_id');
    }

    public function employmentType()
    {
        return $this->belongsTo(RecruitmentOption::class, 'employment_type_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /** The documents this opening asks applicants for; `pivot->is_required` tells required from optional. */
    public function requestedDocuments()
    {
        return $this->belongsToMany(RecruitmentOption::class, 'recruitment_job_opening_documents', 'job_opening_id', 'document_type_id')
            ->withPivot('is_required')
            ->orderByDesc('recruitment_job_opening_documents.is_required')
            ->orderBy('recruitment_options.sort_order');
    }

    /** For the candidate forms: [{typeId, name, required, hint}] */
    public function documentRequirements(): array
    {
        return $this->requestedDocuments->map(fn (RecruitmentOption $type) => [
            'typeId'   => $type->id,
            'name'     => $type->name,
            'required' => (bool) $type->pivot->is_required,
            'hint'     => $type->rulesHint(),
        ])->values()->all();
    }

    public function recruiter()
    {
        return $this->belongsTo(Employee::class, 'recruiter_employee_id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id', 'employee_id');
    }

    public function status(): string
    {
        $now = now();

        if ($this->opens_at && $this->opens_at->gt($now)) {
            return self::STATUS_SCHEDULED;
        }

        if ($this->closes_at && $this->closes_at->lte($now)) {
            return self::STATUS_CLOSED;
        }

        return self::STATUS_OPEN;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status()];
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGES[$this->status()];
    }

    public function isOpen(): bool
    {
        return $this->status() === self::STATUS_OPEN;
    }

    /** Same rule as status(), expressed in SQL so lists can filter and count on it. */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        $now = now();
        $hasOpened = fn (Builder $q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', $now);

        return match ($status) {
            self::STATUS_SCHEDULED => $query->where('opens_at', '>', $now),
            self::STATUS_CLOSED    => $query->where($hasOpened)->where('closes_at', '<=', $now),
            self::STATUS_OPEN      => $query->where($hasOpened)
                ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', $now)),
            default => $query,
        };
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->withStatus(self::STATUS_OPEN);
    }
}
