<?php

namespace App\Models\Letters;

use App\Models\Employee;
use App\Support\Letters\LetterTemplates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A letter an employee asked HR for (My Letter Requests), handled in Letter
 * Templates → Requests: pending → in progress (HR generated the letter) →
 * done (signed and sent) · rejected · cancelled by the employee.
 */
class LetterRequest extends Model
{
    protected $table = 'letter_requests';

    protected $fillable = ['employee_id', 'request_type_id', 'request_type_name', 'is_other', 'template_key', 'language', 'purpose', 'needed_by', 'notes'];

    protected $casts = [
        'needed_by'  => 'date',
        'handled_at' => 'datetime',
        'is_other'   => 'boolean',
    ];

    public const PENDING     = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const DONE        = 'done';
    public const REJECTED    = 'rejected';
    public const CANCELLED   = 'cancelled';

    public const STATUSES = [
        self::PENDING     => 'Pending',
        self::IN_PROGRESS => 'In Progress',
        self::DONE        => 'Done',
        self::REJECTED    => 'Rejected',
        self::CANCELLED   => 'Cancelled',
    ];

    public const STATUS_BADGES = [
        self::PENDING     => 'bg-yellow-100 text-yellow-800',
        self::IN_PROGRESS => 'bg-blue-100 text-blue-700',
        self::DONE        => 'bg-green-100 text-green-700',
        self::REJECTED    => 'bg-red-100 text-red-700',
        self::CANCELLED   => 'bg-gray-100 text-gray-600',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function handler()
    {
        return $this->belongsTo(Employee::class, 'handled_by', 'employee_id');
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class, 'letter_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING, self::IN_PROGRESS]);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::IN_PROGRESS], true);
    }

    public function isDone(): bool
    {
        return $this->status === self::DONE;
    }

    public function requestType()
    {
        return $this->belongsTo(LetterRequestType::class, 'request_type_id');
    }

    /** The letter asked for, as it was named when asked — an option of Settings, or the employee's own words for "Other". */
    public function typeLabel(): string
    {
        return $this->request_type_name ?: LetterTemplates::label($this->template_key);
    }

    /** Answered with a template (pre-filled), or — "Other" and options without one — with a custom letter. */
    public function usesTemplate(): bool
    {
        return LetterTemplates::exists($this->template_key);
    }

    /** The letter, once HR generated it and it was not voided. */
    public function activeLetter(): ?Letter
    {
        $letter = $this->letter;

        return $letter && !$letter->isVoid() ? $letter : null;
    }

    public function employeeName(): string
    {
        $basic = $this->employee?->basicData;

        return trim($basic?->full_name ?? '') ?: ($basic?->nick_name ?: (string) $this->employee?->eci);
    }
}
