<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Letterhead;
use App\Models\Letters\Letter;
use App\Models\Letters\LetterTemplateDef;
use App\Support\Contracts\ContractHtmlSanitizer;
use App\Support\Contracts\PageSettings;
use App\Support\Letters\LetterBody;
use App\Support\Letters\LetterTemplates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * HR & General → Letter Templates → Settings → Template Designs (slug general.letter-templates).
 *
 * The wording of each letter template — Bahasa Indonesia and English — written in the Word-style editor, with the fields a
 * letter of it asks for. HR first sees the template (a PDF with sample data), then edits it, or makes a new one.
 *
 * V see & preview · C add a template · E edit / reset to default · D delete a template HR made (never a built-in one).
 */
class LetterDesignController extends Controller
{
    /** The list is a step of Settings; the old address lands there. */
    public function index()
    {
        return redirect()->route('general.letters.settings.index', ['section' => 'designs']);
    }

    public function create()
    {
        return $this->form(new LetterTemplateDef([
            'employee' => 'optional', 'fields' => [],
            'body_id'  => '<p style="text-align:center"><strong><u>JUDUL SURAT</u></strong></p><p style="text-align:center">Nomor: {{letter_number}}</p><p><br></p><p>{{signature_block}}</p>',
            'body_en'  => '<p style="text-align:center"><strong><u>LETTER TITLE</u></strong></p><p style="text-align:center">Number: {{letter_number}}</p><p><br></p><p>{{signature_block}}</p>',
        ]));
    }

    public function edit(int $design)
    {
        return $this->form(LetterTemplateDef::findOrFail($design));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $key = $this->newKey($data['label_en']);
        $def = LetterTemplateDef::create([...$data, 'template_key' => $key, 'is_system' => false, 'created_by' => session('user.id'), 'updated_by' => session('user.id')]);

        return redirect()->route('general.letters.settings.index', ['section' => 'designs'])->with('success', "Template “{$def->label}” created. Switch it on for Create Letter in Letter Types if it is off.");
    }

    public function update(Request $request, int $design)
    {
        $def = LetterTemplateDef::findOrFail($design);
        $def->update([...$this->validated($request, $def), 'updated_by' => session('user.id')]);

        return redirect()->route('general.letters.settings.index', ['section' => 'designs'])
            ->with('success', "Template “{$def->label}” saved. Letters already generated keep the wording they were made with; new ones use this.");
    }

    public function reset(int $design)
    {
        $def = LetterTemplateDef::findOrFail($design);
        abort_unless($def->is_system, 404);
        $def->resetToDefault();

        return back()->with('success', "Template “{$def->label}” is back to its default wording.");
    }

    public function destroy(int $design)
    {
        $def = LetterTemplateDef::findOrFail($design);

        if ($def->is_system) {
            return back()->withErrors(['template' => 'A built-in template cannot be deleted — edit it, or switch it off in Settings → Letter Types.']);
        }
        if ($used = Letter::where('template_key', $def->template_key)->count()) {
            return back()->withErrors(['template' => "“{$def->label}” has {$used} letter(s) in the register, so it is kept. Switch it off in Settings → Letter Types instead."]);
        }

        $def->delete();

        return redirect()->route('general.letters.settings.index', ['section' => 'designs'])->with('success', "Template “{$def->label}” deleted.");
    }

    /** The template as it is saved, with sample data — what HR looks at before changing it. */
    public function show(Request $request, int $design)
    {
        $def = LetterTemplateDef::findOrFail($design);
        $language = $request->query('lang') === 'en' ? 'en' : 'id';
        $fields = LetterTemplates::normalizeFields((array) $def->fields);

        return $this->pdf($def->template_key, $def->bodyFor($language), $fields, $language, $def->closingFor($language), $def->pageStored(), 'template-' . $def->template_key);
    }

    /** The text as it is being edited (not saved yet), with sample data — the editor's Preview. */
    public function previewDraft(Request $request)
    {
        $data = $request->validate([
            'body_html'    => 'nullable|string|max:300000',
            'language'     => ['required', Rule::in(['id', 'en'])],
            'fields_json'  => 'nullable|string|max:100000',
            'closing'      => 'nullable|string|max:100',
            'template_key' => 'nullable|string|max:60',
            ...$this->pageRules(),
        ]);

        $fields = LetterTemplates::normalizeFields($this->fieldsFromInput((array) json_decode($data['fields_json'] ?? '[]', true), false));
        $stored = PageSettings::store($data['page_size'] ?? null, $data['page_orientation'] ?? null, [
            'top' => $data['margin_top'] ?? null, 'right' => $data['margin_right'] ?? null, 'bottom' => $data['margin_bottom'] ?? null, 'left' => $data['margin_left'] ?? null,
        ]);

        return $this->pdf($data['template_key'] ?? '', ContractHtmlSanitizer::clean($data['body_html'] ?? ''), $fields, $data['language'], $data['closing'] ?? null, $stored, 'preview');
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private function pdf(string $key, string $body, array $fields, string $language, ?string $closing, array $pageStored, string $name)
    {
        $letterhead = $key !== '' ? Letterhead::forLetter($key) : null;
        $page = PageSettings::resolve($pageStored, $letterhead !== null);
        $values = LetterBody::sampleValues($fields, $language, $closing);

        $html = view('hr-general.letters.pdf.designed', [
            'title'      => 'Preview',
            'lang'       => $language,
            'bodyHtml'   => LetterBody::render($body !== '' ? $body : '<p>(no wording written for this language yet)</p>', $values),
            'letterhead' => $letterhead,
            'page'       => $page,
        ])->render();

        return Pdf::loadHTML($html)->setPaper(PageSettings::paper(['page' => $page]))->stream($name . '.pdf');
    }

    private function form(LetterTemplateDef $def)
    {
        $fields = old('fields') !== null ? $this->rowsFromOld((array) old('fields')) : $this->rowsOf($def);
        $stored = $def->exists ? $def->pageStored() : PageSettings::store(null, null, []);

        return view('hr-general.letters.designs.form', [
            'def'            => $def,
            'rows'           => $fields,
            'bodyId'         => ContractHtmlSanitizer::clean(old('body_id', $def->body_id)),
            'bodyEn'         => ContractHtmlSanitizer::clean(old('body_en', $def->body_en)),
            'base'           => LetterBody::BASE,
            'fieldTypes'     => LetterBody::FIELD_TYPES,
            'letterheadUri'  => $def->exists ? Letterhead::forLetter($def->template_key)?->backgroundDataUri() : null,
            'pageSizes'      => PageSettings::sizeOptions(),
            'page'           => ['size' => old('page_size', $stored['size']), 'orientation' => old('page_orientation', $stored['orientation']), 'margins' => [
                'top' => old('margin_top', $stored['margins']['top']), 'right' => old('margin_right', $stored['margins']['right']),
                'bottom' => old('margin_bottom', $stored['margins']['bottom']), 'left' => old('margin_left', $stored['margins']['left']),
            ]],
            'pageDefaults'   => ['plain' => PageSettings::defaultMarginsCm(false), 'letterhead' => PageSettings::defaultMarginsCm(true)],
        ]);
    }

    /** The field rows the form starts with. */
    private function rowsOf(LetterTemplateDef $def): array
    {
        $rows = [];
        foreach ((array) $def->fields as $key => $field) {
            $rows[] = [
                'key' => $key, 'label' => $field['label'] ?? $key, 'type' => $field['type'] ?? 'text', 'required' => (bool) ($field['required'] ?? false),
                'help' => $field['help'] ?? ($field['placeholder'] ?? ''), 'empty_id' => $field['empty']['id'] ?? '', 'empty_en' => $field['empty']['en'] ?? '',
            ];
        }

        return $rows;
    }

    private function rowsFromOld(array $input): array
    {
        return array_values(array_map(fn ($r) => [
            'key' => $r['key'] ?? '', 'label' => $r['label'] ?? '', 'type' => $r['type'] ?? 'text', 'required' => !empty($r['required']),
            'help' => $r['help'] ?? '', 'empty_id' => $r['empty_id'] ?? '', 'empty_en' => $r['empty_en'] ?? '',
        ], $input));
    }

    private function validated(Request $request, ?LetterTemplateDef $def): array
    {
        $data = $request->validate([
            'label'       => 'required|string|max:150',
            'label_en'    => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'employee'    => ['required', Rule::in(['required', 'optional', 'none'])],
            'closing_id'  => 'nullable|string|max:100',
            'closing_en'  => 'nullable|string|max:100',
            'body_id'     => 'nullable|string|max:300000',
            'body_en'     => 'nullable|string|max:300000',
            'fields'      => 'nullable|array|max:30',
            ...$this->pageRules(),
        ], [], ['label' => 'name (Bahasa Indonesia)', 'label_en' => 'name (English)', 'body_id' => 'Bahasa Indonesia text', 'body_en' => 'English text']);

        $fields = $this->fieldsFromInput((array) ($data['fields'] ?? []), true);

        $bodies = ['body_id' => ContractHtmlSanitizer::clean($data['body_id'] ?? ''), 'body_en' => ContractHtmlSanitizer::clean($data['body_en'] ?? '')];
        if (trim(strip_tags($bodies['body_id'])) === '' && trim(strip_tags($bodies['body_en'])) === '') {
            throw ValidationException::withMessages(['body_id' => 'Write the letter text in at least one language.']);
        }
        foreach (['body_id' => 'Bahasa Indonesia', 'body_en' => 'English'] as $column => $name) {
            if ($unknown = LetterBody::unknown($bodies[$column], $fields)) {
                throw ValidationException::withMessages([$column => "Unknown field in the {$name} text: {{" . implode('}}, {{', $unknown) . '}}. Use the Fields menu, or add the field above.']);
            }
        }

        return [
            'label'       => trim($data['label']),
            'label_en'    => trim($data['label_en']),
            'description' => ($data['description'] ?? null) ?: null,
            'employee'    => $data['employee'],
            'fields'      => $fields,
            'closing_id'  => ($data['closing_id'] ?? null) ?: null,
            'closing_en'  => ($data['closing_en'] ?? null) ?: null,
            ...$bodies,
            ...$this->pageColumns($data),
        ];
    }

    /**
     * Field rows (from the form or the preview) as the template keeps them: key → definition. Keys are lower-case words, not
     * one a letter already has (letter_number…), and each only once.
     */
    private function fieldsFromInput(array $rows, bool $strict): array
    {
        $out = [];
        foreach (array_values($rows) as $i => $row) {
            $key = Str::lower(trim((string) ($row['key'] ?? '')));
            $label = trim((string) ($row['label'] ?? ''));
            if ($key === '' && $label === '') {
                continue;
            }

            $error = match (true) {
                !preg_match('/^[a-z][a-z0-9_]{0,39}$/', $key)  => 'The field key must be lower-case letters, digits and underscores, starting with a letter (e.g. received_from).',
                in_array($key, LetterBody::reserved(), true)    => "“{$key}” is already filled in by the system — pick another key.",
                isset($out[$key])                              => "The field key “{$key}” is used twice.",
                $label === ''                                  => "Give the field “{$key}” a label.",
                default                                        => null,
            };
            if ($error) {
                if ($strict) {
                    throw ValidationException::withMessages(["fields.{$i}.key" => $error]);
                }
                continue;
            }

            $type = isset(LetterBody::FIELD_TYPES[$row['type'] ?? '']) ? $row['type'] : 'text';
            $field = ['type' => $type, 'label' => Str::limit($label, 150, ''), 'required' => !empty($row['required'])];
            if (trim((string) ($row['help'] ?? '')) !== '') {
                $field['help'] = Str::limit(trim($row['help']), 255, '');
            }
            $empty = ['id' => trim((string) ($row['empty_id'] ?? '')), 'en' => trim((string) ($row['empty_en'] ?? ''))];
            if ($empty['id'] !== '' || $empty['en'] !== '') {
                $field['empty'] = $empty;
            }
            $out[$key] = $field;
        }

        return $out;
    }

    /** `tpl_` + the English name, made unique among every template key. */
    private function newKey(string $labelEn): string
    {
        $base = 'tpl_' . (Str::slug($labelEn, '_') ?: 'letter');
        $base = Str::limit($base, 50, '');
        $key = $base;

        for ($n = 2; LetterTemplates::exists($key) || $key === LetterTemplates::CUSTOM || LetterTemplateDef::where('template_key', $key)->exists(); $n++) {
            $key = $base . '_' . $n;
        }

        return $key;
    }

    private function pageColumns(array $d): array
    {
        $p = PageSettings::store($d['page_size'] ?? null, $d['page_orientation'] ?? null, [
            'top' => $d['margin_top'] ?? null, 'right' => $d['margin_right'] ?? null, 'bottom' => $d['margin_bottom'] ?? null, 'left' => $d['margin_left'] ?? null,
        ]);

        return ['page_size' => $p['size'], 'page_orientation' => $p['orientation'], 'margin_top' => $p['margins']['top'], 'margin_right' => $p['margins']['right'],
            'margin_bottom' => $p['margins']['bottom'], 'margin_left' => $p['margins']['left']];
    }

    private function pageRules(): array
    {
        $m = 'nullable|numeric|min:' . PageSettings::MARGIN_MIN_CM . '|max:' . PageSettings::MARGIN_MAX_CM;

        return ['page_size' => ['nullable', Rule::in(array_keys(PageSettings::SIZES))], 'page_orientation' => ['nullable', Rule::in(['portrait', 'landscape'])],
            'margin_top' => $m, 'margin_right' => $m, 'margin_bottom' => $m, 'margin_left' => $m];
    }
}
