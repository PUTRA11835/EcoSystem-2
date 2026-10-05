<?php

namespace App\Models\Letters;

use App\Models\Concerns\SignsWithMasterSignature;
use App\Models\Employee;
use App\Models\EmployeeAddress;
use App\Models\Letterhead;
use App\Models\LetterTypeSetting;
use App\Models\Recruitment\Offer;
use App\Support\Letters\LetterTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One row of the Letter Register (HR & General → Letter Templates): every
 * letter in or out — generated from a template, written as a custom letter,
 * an offering letter (kept in step by Offer, read-only here), or logged by
 * hand.
 *
 * A generated letter is rendered from what was saved with it (`fields` holds
 * the employee and company data as they were), so it prints the same later.
 * A numbered outgoing letter is never deleted, only voided, and its number is
 * never given out again (LetterNumberService).
 */
class Letter extends Model
{
    use SignsWithMasterSignature;
    use SoftDeletes;

    protected $table = 'letters';

    protected $fillable = [
        'direction', 'source', 'template_key', 'source_id',
        'letter_number', 'number_sequence', 'number_year', 'agenda_number', 'letter_code_id',
        'letter_date', 'received_date', 'counterparty', 'recipient_email', 'subject',
        'employee_id', 'language', 'letterhead_id', 'use_letterhead', 'fields',
        'signatory_name', 'signatory_title', 'signatory_employee_id',
        'delivered_via', 'receipt_number', 'notes', 'letter_request_id',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'letter_date'    => 'date',
        'received_date'  => 'date',
        'fields'         => 'array',
        'use_letterhead' => 'boolean',
        'signed_at'      => 'datetime',
        'voided_at'      => 'datetime',
        'sent_at'        => 'datetime',
    ];

    public const DIRECTION_OUTGOING = 'outgoing';
    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTIONS = [
        self::DIRECTION_OUTGOING => 'Outgoing',
        self::DIRECTION_INCOMING => 'Incoming',
    ];

    public const SOURCE_MANUAL   = 'manual';
    public const SOURCE_TEMPLATE = 'template';
    public const SOURCE_CUSTOM   = 'custom';
    public const SOURCE_OFFERING = 'offering_letter';

    public const SOURCES = [
        self::SOURCE_TEMPLATE => 'Template',
        self::SOURCE_CUSTOM   => 'Custom',
        self::SOURCE_OFFERING => 'Offering Letter',
        self::SOURCE_MANUAL   => 'Logged by hand',
    ];

    /** What the tables show. */
    public const STATUSES = [
        'draft'    => 'Draft',
        'signed'   => 'Signed',
        'sent'     => 'Sent',
        'logged'   => 'Logged',
        'received' => 'Received',
        'void'     => 'Void',
    ];

    public const STATUS_BADGES = [
        'draft'    => 'bg-amber-100 text-amber-700',
        'signed'   => 'bg-indigo-100 text-indigo-700',
        'sent'     => 'bg-blue-100 text-blue-700',
        'logged'   => 'bg-gray-100 text-gray-700',
        'received' => 'bg-green-100 text-green-700',
        'void'     => 'bg-red-100 text-red-700',
    ];

    public const EMAIL_SENT   = 'sent';
    public const EMAIL_FAILED = 'failed';

    public const FILE_DISK = 'local';

    // ── Relations ────────────────────────────────────────────────────────────

    public function code()
    {
        return $this->belongsTo(LetterCode::class, 'letter_code_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function letterhead()
    {
        return $this->belongsTo(Letterhead::class, 'letterhead_id');
    }

    public function request()
    {
        return $this->belongsTo(LetterRequest::class, 'letter_request_id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class, 'source_id');
    }

    /** Who applied the signature — HR, when the signatory is someone else (e.g. the Finance Manager). */
    public function signedBy()
    {
        return $this->belongsTo(Employee::class, 'signed_by_employee_id', 'employee_id');
    }

    /** Signed by someone other than the signatory, on their behalf. */
    public function signedOnBehalf(): bool
    {
        return $this->isSigned() && $this->signed_by_employee_id && (int) $this->signed_by_employee_id !== (int) $this->signatory_employee_id;
    }

    // ── State ────────────────────────────────────────────────────────────────

    public function isOutgoing(): bool
    {
        return $this->direction === self::DIRECTION_OUTGOING;
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /** Written in this hub — from a template or as a custom letter — so its PDF is generated here. */
    public function isGenerated(): bool
    {
        return in_array($this->source, [self::SOURCE_TEMPLATE, self::SOURCE_CUSTOM], true);
    }

    public function isOffering(): bool
    {
        return $this->source === self::SOURCE_OFFERING;
    }

    public function isManual(): bool
    {
        return $this->source === self::SOURCE_MANUAL;
    }

    public function status(): string
    {
        return match (true) {
            $this->isVoid()                                   => 'void',
            !$this->isOutgoing()                              => 'received',
            $this->isManual()                                 => 'logged',
            $this->email_status === self::EMAIL_SENT || $this->sent_at !== null => 'sent',
            $this->signed_at !== null                         => 'signed',
            default                                           => 'draft',
        };
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'void'     => $query->where('status', 'void'),
            'received' => $query->where('status', '!=', 'void')->where('direction', self::DIRECTION_INCOMING),
            'logged'   => $query->where('status', '!=', 'void')->where('direction', self::DIRECTION_OUTGOING)->where('source', self::SOURCE_MANUAL),
            'sent'     => $query->where('status', '!=', 'void')->where('direction', self::DIRECTION_OUTGOING)->where('source', '!=', self::SOURCE_MANUAL)
                ->where(fn ($q) => $q->where('email_status', self::EMAIL_SENT)->orWhereNotNull('sent_at')),
            'signed'   => $query->where('status', '!=', 'void')->where('direction', self::DIRECTION_OUTGOING)->where('source', '!=', self::SOURCE_MANUAL)
                ->whereNull('sent_at')->where(fn ($q) => $q->whereNull('email_status')->orWhere('email_status', '!=', self::EMAIL_SENT))->whereNotNull('signed_at'),
            'draft'    => $query->where('status', '!=', 'void')->where('direction', self::DIRECTION_OUTGOING)->where('source', '!=', self::SOURCE_MANUAL)
                ->whereNull('sent_at')->where(fn ($q) => $q->whereNull('email_status')->orWhere('email_status', '!=', self::EMAIL_SENT))->whereNull('signed_at'),
            default    => $query,
        };
    }

    /**
     * Send is offered once the letter is signed and has somewhere to go, and
     * only until it has been delivered: after a failed email it reads
     * "Resend"; after a delivered one it is gone, so nobody is emailed twice.
     */
    public function canBeSent(): bool
    {
        return $this->isGenerated() && !$this->isVoid() && $this->isSigned()
            && $this->recipient_email && $this->email_status !== self::EMAIL_SENT;
    }

    /** A generated letter that was not sent yet can still be changed and generated again. */
    public function isEditable(): bool
    {
        return $this->isGenerated() && !$this->isVoid() && $this->email_status !== self::EMAIL_SENT;
    }

    // ── Labels ───────────────────────────────────────────────────────────────

    /** "Surat Keterangan Kerja", "Custom Letter", "Offering Letter", or the subject of a logged letter. */
    public function typeLabel(): string
    {
        return match ($this->source) {
            self::SOURCE_TEMPLATE => LetterTemplates::label($this->template_key),
            self::SOURCE_CUSTOM   => 'Custom Letter',
            self::SOURCE_OFFERING => 'Offering Letter',
            default               => self::DIRECTIONS[$this->direction] . ' letter',
        };
    }

    /** The letter type its letterhead, language and defaults are set for in Settings. */
    public function letterType(): string
    {
        return $this->source === self::SOURCE_TEMPLATE ? (string) $this->template_key : LetterTemplates::CUSTOM;
    }

    public function languageCode(): string
    {
        return isset(LetterTypeSetting::LANGUAGES[$this->language]) ? $this->language : LetterTypeSetting::LANGUAGE_INDONESIAN;
    }

    public function fileName(): string
    {
        $name = preg_replace('/[^\w\s-]/u', '', $this->subject ?: $this->typeLabel());

        return trim(preg_replace('/\s+/', ' ', $name)) . ' - ' . preg_replace('/[^\w-]/', '-', (string) $this->letter_number) . '.pdf';
    }

    // ── PDF ──────────────────────────────────────────────────────────────────

    protected function signatureFolder(): string
    {
        return "letters/{$this->id}";
    }

    /**
     * The letter as a PDF, in its own language: the Blade of its template (or
     * the custom letter), on its letterhead, signed when it is signed.
     * $preview: not saved yet — prints a placeholder where the number goes.
     */
    public function toPdf(bool $preview = false): \Barryvdh\DomPDF\PDF
    {
        $view = $this->source === self::SOURCE_TEMPLATE ? "hr-general.letters.pdf.{$this->template_key}" : 'hr-general.letters.pdf.custom_letter';
        $language = $this->languageCode();

        $previous = App::getLocale();
        App::setLocale($language);

        $fields = $this->fields ?? [];
        $settings = LetterSetting::current();

        try {
            $html = view($view, [
                'letter'     => $this,
                'f'          => $fields,
                'employee'   => $fields['employee'] ?? null,
                'lang'       => $language,
                'company'    => $fields['company'] ?? $settings->company_name,
                'city'       => $fields['city'] ?? $settings->signing_city,
                'number'     => $this->letter_number ?: __('letters.preview'),
                'letterhead' => $this->use_letterhead ? ($this->letterhead ?? Letterhead::forLetter($this->letterType())) : null,
                'signature'  => $preview ? null : $this->signatureDataUri(),
                // "2 Oktober 2026" / "2 October 2026"; "Rp 1.500.000,-" / "IDR 1,500,000".
                'date'       => fn ($value) => $value ? Carbon::parse($value)->locale($language)->translatedFormat('j F Y') : '',
                'money'      => fn ($amount) => $language === LetterTypeSetting::LANGUAGE_ENGLISH
                    ? 'IDR ' . number_format((float) $amount, 0, '.', ',')
                    : 'Rp ' . number_format((float) $amount, 0, ',', '.') . ',-',
            ])->render();
        } finally {
            App::setLocale($previous);
        }

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait');
    }

    // ── Files ────────────────────────────────────────────────────────────────

    public function finalPath(): ?string
    {
        return $this->final_path && Storage::disk(self::FILE_DISK)->exists($this->final_path) ? $this->final_path : null;
    }

    /** Keeps the uploaded signed / stamped scan (or the received letter), replacing the one before. */
    public function storeFinalFile(\Illuminate\Http\UploadedFile $file): void
    {
        $old = $this->final_path;
        $path = $file->store("letters/{$this->id}", self::FILE_DISK);

        $this->forceFill(['final_path' => $path, 'final_name' => $file->getClientOriginalName()])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::FILE_DISK)->delete($old);
        }
    }

    // ── Who the letter goes to ───────────────────────────────────────────────

    /** The employee's work email (Master Employee → Address), else the email they sign in with. */
    public static function workEmailOf(?int $employeeId): ?string
    {
        if (!$employeeId) {
            return null;
        }

        return EmployeeAddress::where('employee_id', $employeeId)->whereNotNull('email_work')->where('email_work', '!=', '')->value('email_work')
            ?: DB::table('auth_users')->where('employee_id', $employeeId)->value('email');
    }

    // ── Offering letters ─────────────────────────────────────────────────────

    /** Keeps the register row of an offering letter in step with the letter (Offer::booted). */
    public static function syncFromOffer(Offer $offer): void
    {
        $row = static::withTrashed()->firstOrNew(['source' => self::SOURCE_OFFERING, 'source_id' => $offer->id]);

        $row->fill([
            'direction'             => self::DIRECTION_OUTGOING,
            'letter_number'         => $offer->letter_number,
            'number_sequence'       => $offer->number_sequence,
            'number_year'           => $offer->offer_date?->year,
            'letter_code_id'        => $row->letter_code_id ?? LetterCode::where('code', 'OL')->value('id'),
            'letter_date'           => $offer->offer_date,
            'counterparty'          => $offer->candidate_name,
            'recipient_email'       => $offer->candidate_email,
            'subject'               => 'Offering Letter - ' . $offer->position_title,
            'language'              => $offer->languageCode(),
            'signatory_name'        => $offer->signatory_name,
            'signatory_title'       => $offer->signatory_title,
            'signatory_employee_id' => $offer->signatory_employee_id,
            'created_by'            => $row->created_by ?? $offer->created_by_employee_id,
        ]);
        $row->forceFill([
            'signed_at'    => $offer->signed_at,
            'sent_at'      => $offer->sent_at,
            'email_status' => $offer->sent_at ? self::EMAIL_SENT : null,
        ])->save();
    }
}
