<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A dropdown option of the Recruitment module, maintained from the Settings
 * tab. `type` says which dropdown the row belongs to.
 */
class RecruitmentOption extends Model
{
    protected $table = 'recruitment_options';

    protected $fillable = ['type', 'name', 'sort_order', 'is_active', 'requirement', 'submission', 'file_formats'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'file_formats' => 'array',
    ];

    public const TYPE_PLATFORM        = 'platform';
    public const TYPE_EMPLOYMENT_TYPE = 'employment_type';
    public const TYPE_DOCUMENT_TYPE   = 'document_type';

    public const TYPES = [
        self::TYPE_PLATFORM        => 'Job Source Platforms',
        self::TYPE_EMPLOYMENT_TYPE => 'Employment Types',
        self::TYPE_DOCUMENT_TYPE   => 'Document Types',
    ];

    // ── Document rules (document types only) ─────────────────────────────────

    /** How a new job opening asks for a document type by default. */
    public const REQUIREMENTS = [
        'required' => 'Required',
        'optional' => 'Optional',
        'none'     => 'Not requested',
    ];

    /** How a document is handed in. */
    public const SUBMISSIONS = [
        'file' => 'File',
        'link' => 'Link',
        'both' => 'File or link',
    ];

    /** Accepted file formats HR can tick; none ticked = all of them. */
    public const FILE_FORMATS = [
        'pdf'        => ['label' => 'PDF',        'extensions' => ['pdf']],
        'word'       => ['label' => 'Word',       'extensions' => ['doc', 'docx']],
        'image'      => ['label' => 'Image',      'extensions' => ['jpg', 'jpeg', 'png']],
        'excel'      => ['label' => 'Excel',      'extensions' => ['xls', 'xlsx']],
        'powerpoint' => ['label' => 'PowerPoint', 'extensions' => ['ppt', 'pptx']],
    ];

    public function acceptsFile(): bool
    {
        return $this->submission !== 'link';
    }

    public function acceptsLink(): bool
    {
        return in_array($this->submission, ['link', 'both'], true);
    }

    /** Format keys in force — the ticked ones, or every format when none is ticked. */
    public function formatKeys(): array
    {
        return array_values(array_intersect(array_keys(self::FILE_FORMATS), $this->file_formats ?: array_keys(self::FILE_FORMATS)));
    }

    /** @return string[] file extensions a file of this type may have */
    public function extensions(): array
    {
        return collect($this->formatKeys())->flatMap(fn (string $key) => self::FILE_FORMATS[$key]['extensions'])->all();
    }

    /** One line for forms and lists, e.g. "PDF only" or "File (PDF, Word) or link". */
    public function rulesHint(): string
    {
        $all = count($this->formatKeys()) === count(self::FILE_FORMATS);
        $labels = collect($this->formatKeys())->map(fn (string $key) => self::FILE_FORMATS[$key]['label']);
        $files = $all ? 'Any file format' : ($labels->count() === 1 ? $labels->first() . ' only' : $labels->implode(', '));

        return match (true) {
            !$this->acceptsFile() => 'Link only',
            $this->acceptsLink()  => ($all ? 'File' : "File ({$labels->implode(', ')})") . ' or link',
            default               => $files,
        };
    }

    /** What the browser needs to enforce the same rules while the form is filled in. */
    public function rulesForForms(): array
    {
        return [
            'name'   => $this->name,
            'file'   => $this->acceptsFile(),
            'link'   => $this->acceptsLink(),
            'accept' => collect($this->extensions())->map(fn (string $ext) => ".{$ext}")->implode(','),
            'hint'   => $this->rulesHint(),
        ];
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Options to offer in a dropdown: the active ones, plus $keepId so a
     * record that already points at a since-deactivated option still shows it.
     */
    public static function choices(string $type, ?int $keepId = null): Collection
    {
        return static::ofType($type)
            ->where(function (Builder $q) use ($keepId) {
                $q->where('is_active', true);

                if ($keepId) {
                    $q->orWhere('id', $keepId);
                }
            })
            ->ordered()
            ->get();
    }

    /** True while any record still references this option — such options are deactivated, not deleted. */
    public function isInUse(): bool
    {
        return match ($this->type) {
            self::TYPE_PLATFORM => DB::table('recruitment_candidates')->where('source_id', $this->id)->exists()
                || DB::table('recruitment_job_openings')->where('platform_id', $this->id)->exists(),
            self::TYPE_EMPLOYMENT_TYPE => DB::table('recruitment_job_openings')->where('employment_type_id', $this->id)->exists(),
            self::TYPE_DOCUMENT_TYPE => DB::table('recruitment_candidate_documents')->where('document_type_id', $this->id)->exists()
                || DB::table('recruitment_job_opening_documents')->where('document_type_id', $this->id)->exists(),
            default => false,
        };
    }
}
