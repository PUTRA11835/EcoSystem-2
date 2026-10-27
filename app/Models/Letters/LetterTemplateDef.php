<?php

namespace App\Models\Letters;

use App\Support\Letters\DefaultDesigns;
use App\Support\Letters\LetterTemplates;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A letter template kept as data (HR & General → Letter Templates → Settings → Template Designs): its names, the fields a
 * letter of it asks for, and its wording in both languages. The built-in templates (`is_system`) can be edited and reset to
 * the wording they shipped with, never deleted. Read through App\Support\Letters\LetterTemplates.
 */
class LetterTemplateDef extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Letters';

    protected $table = 'letter_template_defs';

    protected $fillable = [
        'template_key', 'label', 'label_en', 'description', 'employee', 'fields', 'body_id', 'body_en', 'closing_id', 'closing_en',
        'page_size', 'page_orientation', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'is_system', 'created_by', 'updated_by',
    ];

    protected $casts = ['fields' => 'array', 'is_system' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => LetterTemplates::forget());
        static::deleted(fn () => LetterTemplates::forget());
    }

    /** The wording for a language; the other language's when this one was left empty. */
    public function bodyFor(string $language): string
    {
        $own = $language === 'en' ? $this->body_en : $this->body_id;
        $other = $language === 'en' ? $this->body_id : $this->body_en;

        return trim((string) $own) !== '' ? (string) $own : (string) $other;
    }

    public function closingFor(string $language): ?string
    {
        return ($language === 'en' ? $this->closing_en : $this->closing_id) ?: null;
    }

    public function pageStored(): array
    {
        return \App\Support\Contracts\PageSettings::store($this->page_size, $this->page_orientation, [
            'top' => $this->margin_top, 'right' => $this->margin_right, 'bottom' => $this->margin_bottom, 'left' => $this->margin_left,
        ]);
    }

    /** Wording, fields and closing as they shipped (built-in templates only). */
    public function resetToDefault(): void
    {
        $default = DefaultDesigns::bodies()[$this->template_key] ?? null;
        $built = LetterTemplates::BUILT_IN[$this->template_key] ?? null;
        if (!$default || !$built) {
            return;
        }

        $fields = $built['fields'];
        foreach ($default['empty'] ?? [] as $field => $text) {
            $fields[$field]['empty'] = $text;
        }

        $this->forceFill([
            'label' => $built['label'], 'label_en' => $built['label_en'], 'description' => $built['description'],
            'fields' => $fields, 'body_id' => $default['body_id'], 'body_en' => $default['body_en'],
            'closing_id' => $default['closing']['id'] ?? null, 'closing_en' => $default['closing']['en'] ?? null,
            'page_size' => null, 'page_orientation' => null, 'margin_top' => null, 'margin_right' => null, 'margin_bottom' => null, 'margin_left' => null,
        ])->save();
    }
}
