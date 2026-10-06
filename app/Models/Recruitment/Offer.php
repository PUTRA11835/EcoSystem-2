<?php

namespace App\Models\Recruitment;

use App\Models\Concerns\SignsWithMasterSignature;
use App\Models\Employee;
use App\Models\Letters\Letter;
use App\Services\Letters\LetterNumberService;
use App\Models\LetterTypeSetting;
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
    use SignsWithMasterSignature;

    protected $table = 'recruitment_offers';

    protected $fillable = [
        'letter_number', 'language', 'number_sequence', 'offer_date',
        'candidate_id', 'candidate_name', 'candidate_email', 'candidate_phone', 'position_title',
        'job_description', 'benefits', 'joining_date', 'has_probation', 'salary_type',
        'compensation', 'total_compensation', 'notes', 'signatory_name', 'signatory_title', 'signatory_employee_id',
        'sent_at', 'decision', 'decided_at', 'created_by_employee_id', 'hired_employee_id',
    ];

    protected $casts = [
        'offer_date'         => 'date',
        'joining_date'       => 'date',
        'has_probation'      => 'boolean',
        'compensation'       => 'array',
        'total_compensation' => 'float',
        'sent_at'            => 'datetime',
        'signed_at'          => 'datetime',
        'decided_at'         => 'datetime',
    ];

    public const DECISION_PENDING  = 'pending';
    public const DECISION_ACCEPTED = 'accepted';
    public const DECISION_REJECTED = 'rejected';

    public const SALARY_TYPES = ['gross' => 'Gross', 'nett' => 'Nett'];

    /** What the list shows: a pending letter is a draft until it is signed, and signed until it has been emailed. */
    public const STATUSES = [
        'draft'    => 'Draft',
        'signed'   => 'Signed',
        'sent'     => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ];

    public const STATUS_BADGES = [
        'draft'    => 'bg-amber-100 text-amber-700',
        'signed'   => 'bg-indigo-100 text-indigo-700',
        'sent'     => 'bg-blue-100 text-blue-700',
        'accepted' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700',
    ];

    private const ROMAN_MONTHS = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    /** Every offering letter is also a read-only row of the Letter Register. */
    protected static function booted(): void
    {
        static::saved(fn (self $offer) => Letter::syncFromOffer($offer));
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function signedBy()
    {
        return $this->belongsTo(Employee::class, 'signed_by_employee_id', 'employee_id');
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
            $this->isSigned() => 'signed',
            default => 'draft',
        };
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'draft'  => $query->where('decision', self::DECISION_PENDING)->whereNull('sent_at')->whereNull('signed_at'),
            'signed' => $query->where('decision', self::DECISION_PENDING)->whereNull('sent_at')->whereNotNull('signed_at'),
            'sent'   => $query->where('decision', self::DECISION_PENDING)->whereNotNull('sent_at'),
            default => $query->where('decision', $status),
        };
    }

    // ── Signature (App\Models\Concerns\SignsWithMasterSignature) ──────────

    protected function signatureFolder(): string
    {
        return "recruitment/offers/{$this->id}";
    }

    /** The compensation lines of the letter, as saved: [{component_id, name, name_en, kind, amount}]. */
    public function lines(): Collection
    {
        return collect($this->compensation ?? []);
    }

    /** The language the letter is printed in — a key of LetterTypeSetting::LANGUAGES. */
    public function languageCode(): string
    {
        return isset(LetterTypeSetting::LANGUAGES[$this->language]) ? $this->language : LetterTypeSetting::LANGUAGE_INDONESIAN;
    }

    public function isEnglish(): bool
    {
        return $this->languageCode() === LetterTypeSetting::LANGUAGE_ENGLISH;
    }

    /** A compensation line's name in the letter's language; a line without an English name keeps its own. */
    public function lineName(array $line): string
    {
        return $this->isEnglish() && !empty($line['name_en']) ? $line['name_en'] : $line['name'];
    }

    /** An amount as the letter writes it: "Rp 4.500.000,-" in Indonesian, "IDR 4,500,000" in English. */
    public function money($amount): string
    {
        return $this->isEnglish()
            ? 'IDR ' . number_format((float) $amount, 0, '.', ',')
            : 'Rp ' . number_format((float) $amount, 0, ',', '.') . ',-';
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

    /**
     * The running number the next letter dated $date would get — a preview
     * only. Offering letters share the outgoing counter of the Letter
     * Templates hub, which never gives a number out twice; the number is
     * taken in RecruitmentOfferController::store().
     */
    public static function nextSequence(CarbonInterface $date): int
    {
        return app(LetterNumberService::class)->peek(LetterNumberService::OUTGOING, $date->year);
    }

    /**
     * A letter number in the format set in Offering Letter → Settings. The
     * running number is one sequence whatever the language: {lang} only
     * changes the IN / EN segment.
     */
    public static function numberFor(CarbonInterface $date, int $sequence, ?string $language = null): string
    {
        $settings = RecruitmentSetting::current();

        return strtr($settings->offer_number_format, [
            '{seq}'   => str_pad((string) $sequence, $settings->offer_number_digits, '0', STR_PAD_LEFT),
            '{lang}'  => LetterTypeSetting::numberCode($language),
            '{day}'   => $date->format('d'),
            '{month}' => $date->format('m'),
            '{roman}' => self::ROMAN_MONTHS[$date->month],
            '{year}'  => $date->format('Y'),
            '{yy}'    => $date->format('y'),
        ]);
    }
}
