<?php

namespace App\Support\DataTransfer;

use App\Models\Letterhead;
use App\Models\Letters\LetterCode;
use App\Models\Letters\LetterRequestType;
use App\Models\LetterTypeSetting;
use App\Support\Letters\LetterTemplates;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Letter Templates → Settings: the per-letter template settings, the letter
 * codes and the request options. Letterheads carry an image and are left to
 * the screen.
 */
class LetterDatasets
{
    private static function yn(bool $v): string
    {
        return $v ? 'Yes' : 'No';
    }

    public static function templates(): Dataset
    {
        $types     = fn () => Letterhead::letterTypes();
        $languages = LetterTypeSetting::LANGUAGES;
        $codes     = fn () => LetterCode::ordered()->pluck('code', 'id')->all();

        return new Dataset(
            'letters.templates', 'Letter Template Settings', 'general.letter-templates',
            [
                ['key' => 'letter_type', 'label' => 'Letter Type', 'required' => true, 'get' => fn ($r) => $r->label,
                 'parse' => fn ($v) => Cell::choice($v, $types(), 'letter type'), 'rules' => ['required'],
                 'hint' => 'Fixed list: ' . implode(', ', $types()) . '. Only these types can be updated.', 'example' => array_values($types())[0] ?? ''],
                ['key' => 'language', 'label' => 'Language', 'required' => true, 'get' => fn ($r) => $languages[$r->language] ?? $r->language,
                 'parse' => fn ($v) => Cell::choice($v, $languages, 'language'), 'rules' => ['required', Rule::in(array_keys($languages))],
                 'hint' => 'Bahasa Indonesia or English (or id / en).', 'example' => 'Bahasa Indonesia'],
                ['key' => 'is_active', 'label' => 'In Use', 'get' => fn ($r) => self::yn((bool) $r->is_active),
                 'parse' => fn ($v) => Cell::yesNo($v, true), 'rules' => ['boolean'],
                 'hint' => 'Yes/No. Blank = Yes. The offering letter is always in use.', 'example' => 'Yes'],
                ['key' => 'letter_code_id', 'label' => 'Letter Code', 'get' => fn ($r) => $r->code_label,
                 'parse' => fn ($v) => Cell::choice($v, $codes(), 'letter code'), 'rules' => ['nullable', 'integer'],
                 'hint' => 'A code from Letter Codes: ' . implode(', ', $codes()) . '. Optional.', 'example' => ''],
            ],
            function (Request $request) use ($types, $languages) {
                $saved = LetterTypeSetting::with('code')->get()->keyBy('letter_type');

                return collect($types())->map(function ($label, $type) use ($saved) {
                    $row = $saved[$type] ?? LetterTypeSetting::for($type);

                    return (object) [
                        'label'      => $label,
                        'language'   => LetterTypeSetting::languageFor($type),
                        'is_active'  => $row->exists ? $row->is_active : true,
                        'code_label' => $row->code?->code ?? '',
                    ];
                })->values();
            },
            [
                'permission' => ['general.letter-templates', 'edit'],
                'key'        => 'letter_type',
                'find'       => fn (array $v) => LetterTypeSetting::where('letter_type', $v['letter_type'])->first(),
                'save'       => function (array $v, $existing, int $actor) {
                    return LetterTypeSetting::updateOrCreate(['letter_type' => $v['letter_type']], [
                        'language'       => $v['language'],
                        'letter_code_id' => $v['letter_code_id'] ?: null,
                        'is_active'      => $v['letter_type'] === Letterhead::TYPE_OFFERING_LETTER ? true : (bool) $v['is_active'],
                    ]);
                },
            ]
        );
    }

    public static function codes(): Dataset
    {
        return new Dataset(
            'letters.codes', 'Letter Codes', 'general.letter-templates',
            [
                ['key' => 'code', 'label' => 'Code', 'required' => true, 'get' => fn ($r) => $r->code,
                 'parse' => fn ($v) => ($t = Cell::text($v)) === null ? null : strtoupper($t),
                 'rules' => ['required', 'string', 'max:10', 'alpha_num'],
                 'hint' => 'Up to 10 letters/digits, printed in letter numbers. An existing code is updated.', 'example' => 'HR'],
                ['key' => 'name', 'label' => 'Name', 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['nullable', 'string', 'max:100'], 'example' => 'Human Resources'],
                ['key' => 'is_active', 'label' => 'Active', 'get' => fn ($r) => self::yn((bool) $r->is_active),
                 'parse' => fn ($v) => Cell::yesNo($v, true), 'rules' => ['boolean'], 'hint' => 'Yes/No. Blank = Yes.', 'example' => 'Yes'],
            ],
            fn (Request $request) => LetterCode::ordered()->get(),
            [
                'permission' => ['general.letter-templates', 'create'],
                'key'        => 'code',
                'find'       => fn (array $v) => LetterCode::where('code', $v['code'])->first(),
                'save'       => function (array $v, ?LetterCode $existing, int $actor) {
                    $data = ['name' => $v['name'], 'is_active' => (bool) $v['is_active']];

                    return $existing
                        ? tap($existing)->update($data)
                        : LetterCode::create($data + ['code' => $v['code'], 'sort_order' => (int) LetterCode::max('sort_order') + 1]);
                },
            ]
        );
    }

    public static function requestTypes(): Dataset
    {
        $templates = array_combine(array_keys(LetterTemplates::all()), array_keys(LetterTemplates::all()));

        return new Dataset(
            'letters.request-types', 'Letter Request Options', 'general.letter-templates',
            [
                ['key' => 'name', 'label' => 'Name', 'required' => true, 'get' => fn ($r) => $r->name,
                 'parse' => fn ($v) => Cell::text($v), 'rules' => ['required', 'string', 'max:150'],
                 'hint' => 'What employees see in My Letter Requests. An existing name is updated.', 'example' => 'Surat Keterangan Kerja'],
                ['key' => 'template_key', 'label' => 'Answered With', 'get' => fn ($r) => $r->template_key,
                 'parse' => fn ($v) => Cell::choice($v, $templates, 'template'), 'rules' => ['nullable', Rule::in(array_keys($templates))],
                 'hint' => 'Template key: ' . implode(', ', array_keys($templates)) . '. Blank = a custom letter.', 'example' => ''],
                ['key' => 'is_active', 'label' => 'Active', 'get' => fn ($r) => self::yn((bool) $r->is_active),
                 'parse' => fn ($v) => Cell::yesNo($v, true), 'rules' => ['boolean'], 'hint' => 'Yes/No. Blank = Yes.', 'example' => 'Yes'],
            ],
            fn (Request $request) => LetterRequestType::ordered()->get(),
            [
                'permission' => ['general.letter-templates', 'create'],
                'key'        => 'name',
                'find'       => fn (array $v) => LetterRequestType::where('name', $v['name'])->first(),
                'save'       => function (array $v, ?LetterRequestType $existing, int $actor) {
                    $data = ['template_key' => $v['template_key'] ?: null, 'is_active' => (bool) $v['is_active']];

                    return $existing
                        ? tap($existing)->update($data)
                        : LetterRequestType::create($data + ['name' => $v['name'], 'sort_order' => (int) LetterRequestType::max('sort_order') + 1]);
                },
            ]
        );
    }
}
