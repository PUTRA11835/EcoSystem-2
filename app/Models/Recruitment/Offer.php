<?php

namespace App\Models\Recruitment;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * An offering letter. The candidate's name, contact and position are kept on
 * the letter itself — `candidate_id` only links it back to the selection
 * process, and is empty for a letter written for someone entered by hand.
 */
class Offer extends Model
{
    protected $table = 'recruitment_offers';

    protected $fillable = [
        'letter_number', 'number_sequence', 'offer_date',
        'candidate_id', 'candidate_name', 'candidate_email', 'candidate_phone', 'position_title',
        'job_description', 'benefits', 'joining_date', 'has_probation', 'salary_type',
        'compensation', 'total_compensation', 'notes', 'signatory_name', 'signatory_title',
        'sent_at', 'decision', 'decided_at', 'created_by_employee_id', 'hired_employee_id',
    ];

    protected $casts = [
        'offer_date'         => 'date',
        'joining_date'       => 'date',
        'has_probation'      => 'boolean',
        'compensation'       => 'array',
        'total_compensation' => 'float',
        'sent_at'            => 'datetime',
        'decided_at'         => 'datetime',
    ];

    public const DECISION_PENDING  = 'pending';
    public const DECISION_ACCEPTED = 'accepted';
    public const DECISION_REJECTED = 'rejected';

    public const SALARY_TYPES = ['gross' => 'Gross', 'nett' => 'Nett'];

    /** What the list shows: a pending letter is a draft until it has been emailed. */
    public const STATUSES = [
        'draft'    => 'Draft',
        'sent'     => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ];

    public const STATUS_BADGES = [
        'draft'    => 'bg-amber-100 text-amber-700',
        'sent'     => 'bg-blue-100 text-blue-700',
        'accepted' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700',
    ];

    private const ROMAN_MONTHS = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function hiredEmployee()
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id', 'employee_id');
    }

    public function isPending(): bool
    {
        return $this->decision === self::DECISION_PENDING;
    }

    public function status(): string
    {
        return match (true) {
            !$this->isPending() => $this->decision,
            $this->sent_at !== null => 'sent',
            default => 'draft',
        };
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'draft' => $query->where('decision', self::DECISION_PENDING)->whereNull('sent_at'),
            'sent'  => $query->where('decision', self::DECISION_PENDING)->whereNotNull('sent_at'),
            default => $query->where('decision', $status),
        };
    }

    /** The compensation lines of the letter, as saved: [{component_id, name, kind, amount}]. */
    public function lines(): Collection
    {
        return collect($this->compensation ?? []);
    }

    /**
     * Base salary as a percentage of base salary + fixed allowances — the
     * ratio the wage regulation puts a minimum on. Null when there is no base
     * salary to measure.
     */
    public function baseSalaryPercent(): ?float
    {
        return self::baseSalaryPercentOf($this->lines());
    }

    public static function baseSalaryPercentOf(Collection $lines): ?float
    {
        $base = (float) $lines->where('kind', OfferComponent::KIND_BASE)->sum('amount');
        $fixed = (float) $lines->where('kind', OfferComponent::KIND_FIXED)->sum('amount');

        return $base > 0 ? round($base / ($base + $fixed) * 100, 2) : null;
    }

    // ── Letter number ────────────────────────────────────────────────────────

    /** The next running number for a letter dated $date; it restarts every year. */
    public static function nextSequence(CarbonInterface $date): int
    {
        return (int) static::whereYear('offer_date', $date->year)->max('number_sequence') + 1;
    }

    /** A letter number in the format set in Offering Letter → Settings. */
    public static function numberFor(CarbonInterface $date, int $sequence): string
    {
        $settings = RecruitmentSetting::current();

        return strtr($settings->offer_number_format, [
            '{seq}'   => str_pad((string) $sequence, $settings->offer_number_digits, '0', STR_PAD_LEFT),
            '{day}'   => $date->format('d'),
            '{month}' => $date->format('m'),
            '{roman}' => self::ROMAN_MONTHS[$date->month],
            '{year}'  => $date->format('Y'),
            '{yy}'    => $date->format('y'),
        ]);
    }
}
