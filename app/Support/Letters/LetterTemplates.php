<?php

namespace App\Support\Letters;

use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * The letter templates of HR & General → Letter Templates → Create Letter.
 *
 * BUILT_IN is what ships in code: the fields of each template and, for the system letters (BPJS, external
 * consultants), their wording in a Blade, resources/views/hr-general/letters/pdf/{key}.blade.php.
 * The internal templates are also kept as data (App\Models\Letters\LetterTemplateDef): HR edits their names, fields and
 * wording in Settings → Template Designs and adds templates of their own, and all() is the merge of the two — every
 * caller reads all(), never BUILT_IN. Words every letter shares (Number, Subject, Sincerely…) are in lang/{id,en}/letters.php.
 *
 * Field types: text · textarea · date · money · items (rows of name / qty / note).
 * `employee`: 'required' — the letter is about an employee; 'optional'; or false.
 */
class LetterTemplates
{
    public const CUSTOM = 'custom_letter';

    public const BUILT_IN = [
        'employment_certificate' => [
            'label'       => 'Surat Keterangan Kerja',
            'label_en'    => 'Employment Certificate',
            'description' => 'States that the employee works at the company: name, employee ID, position, department and join date are filled in automatically.',
            'employee'    => 'required',
            'fields'      => [
                'purpose' => ['type' => 'text', 'label' => 'Purpose of the letter', 'required' => true, 'placeholder' => 'e.g. visa application, bank loan'],
            ],
        ],
        'employment_reference' => [
            'label'       => 'Surat Referensi Kerja',
            'label_en'    => 'Employment Reference Letter',
            'description' => 'A reference for the employee\'s work: employee data is filled in automatically, with the period worked and remarks.',
            'employee'    => 'required',
            'fields'      => [
                'end_date' => ['type' => 'date', 'label' => 'Last working day', 'required' => false, 'help' => 'Empty: the employee still works here.'],
                'remarks'  => ['type' => 'textarea', 'label' => 'Remarks on the employee\'s work', 'required' => false],
            ],
        ],
        'assignment_letter' => [
            'label'       => 'Surat Tugas',
            'label_en'    => 'Assignment Letter',
            'description' => 'Assigns the employee to a task at a location for a period, on top of the employee and company data.',
            'employee'    => 'required',
            'fields'      => [
                'location'    => ['type' => 'text', 'label' => 'Location', 'required' => true],
                'period_from' => ['type' => 'date', 'label' => 'From', 'required' => true],
                'period_to'   => ['type' => 'date', 'label' => 'Until', 'required' => true],
                'task'        => ['type' => 'textarea', 'label' => 'Assignment', 'required' => true],
            ],
        ],
        'goods_receipt' => [
            'label'       => 'Tanda Terima',
            'label_en'    => 'Goods Receipt',
            'description' => 'Proof that items were handed over: the items received and who they were received from.',
            'employee'    => 'optional',
            'fields'      => [
                'received_from' => ['type' => 'text', 'label' => 'Received from', 'required' => true],
                'items'         => ['type' => 'items', 'label' => 'Items received', 'required' => true],
            ],
        ],
        'payment_receipt' => [
            'label'       => 'Kwitansi',
            'label_en'    => 'Payment Receipt',
            'description' => 'Proof of a payment received: the amount, the amount in words and what the payment is for.',
            'employee'    => 'optional',
            'fields'      => [
                'received_from' => ['type' => 'text', 'label' => 'Received from', 'required' => true],
                'amount'        => ['type' => 'money', 'label' => 'Amount (Rp)', 'required' => true],
                'amount_words'  => ['type' => 'text', 'label' => 'Amount in words', 'required' => false, 'help' => 'Empty: written out automatically in the letter\'s language.'],
                'purpose'       => ['type' => 'textarea', 'label' => 'For payment of', 'required' => true],
            ],
        ],
        // The offering letter of HR & General → Offering Letter, available here so HR can write one without taking a candidate
        // through the offering process. It shares the "Offering Letter" letter type with that menu: same letterhead, number code and
        // default language. About a candidate (typed here), not an employee.
        'offering_letter' => [
            'label'       => 'Surat Penawaran Kerja',
            'label_en'    => 'Offering Letter',
            'description' => 'Offers a position to a candidate: position, joining date, compensation and benefits. Written here directly — no candidate has to go through the offering process.',
            'employee'    => false,
            'fields'      => [
                'candidate_name'  => ['type' => 'text', 'label' => 'Candidate name', 'required' => true],
                'candidate_email' => ['type' => 'text', 'label' => 'Candidate email', 'required' => false, 'help' => 'Where Send emails the signed letter.'],
                'position_title'  => ['type' => 'text', 'label' => 'Position offered', 'required' => true],
                'job_description' => ['type' => 'textarea', 'label' => 'Responsibilities', 'required' => false, 'help' => 'What the candidate will be doing in this position.'],
                'joining_date'    => ['type' => 'date', 'label' => 'Joining date', 'required' => true],
                'compensation'    => ['type' => 'lines', 'label' => 'Compensation', 'required' => true, 'help' => 'One line per component (base salary, allowances…) with its monthly amount; the total is added up in the letter.'],
                'salary_type'     => ['type' => 'text', 'label' => 'Gross or nett', 'required' => false, 'placeholder' => 'Gross'],
                'benefits'        => ['type' => 'textarea', 'label' => 'Benefits', 'required' => false],
                'probation'       => ['type' => 'textarea', 'label' => 'Probation clause', 'required' => false, 'help' => 'The sentence to print, e.g. "A probation period of 3 (three) months applies from your joining date." Empty: nothing is printed.'],
                'notes'           => ['type' => 'textarea', 'label' => 'Notes', 'required' => false],
                'response_days'   => ['type' => 'text', 'label' => 'Days to respond', 'required' => false, 'placeholder' => '3'],
            ],
        ],
        // Dibuat oleh Payroll Suite → BPJS → Letters (bulk, lampiran daftar karyawan); `system` = tidak ada di pilihan Create Letter,
        // tetapi tetap muncul di Letter Register dan di pengaturan kop surat (Settings → letterhead).
        'bpjs_deactivation' => [
            'label'       => 'Surat Penonaktifan BPJS Kesehatan',
            'label_en'    => 'BPJS Health Deactivation Letter',
            'description' => 'Asks BPJS Kesehatan to deactivate the membership of the employees listed in the attachment. Generated from Finance → BPJS → Letters.',
            'employee'    => false,
            'system'      => true,
            'fields'      => [],
        ],
        // Surat konsultan eksternal — dibuat dari menu External (Assignment Letter / Employment Certificate / Reference Letter).
        // `system` = tidak ada di pilihan Create Letter internal, tetapi tetap tercatat di Letter Register dan bisa diberi kop surat.
        'ext_assignment_letter' => [
            'label'       => 'Surat Tugas Konsultan',
            'label_en'    => 'External Assignment Letter',
            'description' => 'Assigns an external consultant to a task at a client location for a period. Created from External → Assignment Letter.',
            'employee'    => 'required',
            'system'      => true,
            'fields'      => [
                'addressed_to' => ['type' => 'text', 'label' => 'Addressed to', 'required' => false],
                'contract_no'  => ['type' => 'text', 'label' => 'Related external contract', 'required' => false],
                'task'         => ['type' => 'textarea', 'label' => 'Assignment / purpose', 'required' => true],
                'location'     => ['type' => 'text', 'label' => 'Assignment location', 'required' => true],
                'period_from'  => ['type' => 'date', 'label' => 'Assignment start', 'required' => true],
                'period_to'    => ['type' => 'date', 'label' => 'Assignment end', 'required' => true],
            ],
        ],
        'ext_employment_certificate' => [
            'label'       => 'Surat Keterangan Kerja Konsultan',
            'label_en'    => 'External Employment Certificate',
            'description' => 'States that an external consultant is engaged on a project. Created from External → Employment Certificate.',
            'employee'    => 'required',
            'system'      => true,
            'fields'      => [
                'addressed_to' => ['type' => 'text', 'label' => 'Addressed to', 'required' => false],
                'contract_no'  => ['type' => 'text', 'label' => 'Related external contract', 'required' => false],
                'purpose'      => ['type' => 'text', 'label' => 'Purpose', 'required' => true],
                'location'     => ['type' => 'text', 'label' => 'Assignment location', 'required' => false],
                'period_from'  => ['type' => 'date', 'label' => 'Assignment start', 'required' => false],
                'period_to'    => ['type' => 'date', 'label' => 'Assignment end', 'required' => false],
            ],
        ],
        'ext_reference_letter' => [
            'label'       => 'Surat Referensi Konsultan',
            'label_en'    => 'External Reference Letter',
            'description' => 'A professional reference for an external consultant\'s engagement. Created from External → Reference Letter.',
            'employee'    => 'required',
            'system'      => true,
            'fields'      => [
                'addressed_to' => ['type' => 'text', 'label' => 'Addressed to', 'required' => false],
                'contract_no'  => ['type' => 'text', 'label' => 'Related external contract', 'required' => false],
                'remarks'      => ['type' => 'textarea', 'label' => 'Reference summary', 'required' => false],
                'location'     => ['type' => 'text', 'label' => 'Assignment location', 'required' => false],
                'period_from'  => ['type' => 'date', 'label' => 'Assignment start', 'required' => false],
                'period_to'    => ['type' => 'date', 'label' => 'Assignment end', 'required' => false],
            ],
        ],
    ];

    /** @var array<string, array>|null */
    private static ?array $merged = null;

    /** Forget the merged list — after a design was saved or deleted. */
    public static function forget(): void
    {
        self::$merged = null;
    }

    /**
     * Every template, keyed: the built-in ones with what HR changed in Template Designs laid over them, then the templates
     * HR made. Without the table (a fresh install, unit tests) it is the built-in list.
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        if (self::$merged !== null) {
            return self::$merged;
        }

        $all = self::BUILT_IN;

        try {
            foreach (\App\Models\Letters\LetterTemplateDef::orderBy('id')->get() as $def) {
                $base = self::BUILT_IN[$def->template_key] ?? [];
                $all[$def->template_key] = [
                    ...$base,
                    'label'       => $def->label,
                    'label_en'    => $def->label_en,
                    'description' => (string) $def->description,
                    'employee'    => match ($def->employee) { 'required' => 'required', 'optional' => 'optional', default => false },
                    'fields'      => self::normalizeFields((array) $def->fields),
                    'designed'    => true,
                    'custom'      => !$def->is_system && !isset(self::BUILT_IN[$def->template_key]),
                ];
            }
        } catch (\Throwable) {
            // no table yet
        }

        return self::$merged = $all;
    }

    /**
     * The wording of a template in a language, with its field definitions, closing and paper — saved on a letter when it is
     * generated so the letter prints the same even after the template is changed. Null: no wording designed (a system letter
     * still written in Blade).
     */
    public static function designSnapshot(string $key, string $language): ?array
    {
        try {
            $def = \App\Models\Letters\LetterTemplateDef::where('template_key', $key)->first();
        } catch (\Throwable) {
            return null;
        }

        $body = $def?->bodyFor($language);
        if (!$def || trim(strip_tags((string) $body)) === '') {
            return null;
        }

        return [
            'body'    => $body,
            'fields'  => self::normalizeFields((array) $def->fields),
            'closing' => $def->closingFor($language),
            'page'    => $def->pageStored(),
        ];
    }

    /** Field definitions as the rest of the code expects them (every key present). */
    public static function normalizeFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $field) {
            $out[$name] = [
                ...$field,
                'type'     => isset(LetterBody::FIELD_TYPES[$field['type'] ?? '']) ? $field['type'] : 'text',
                'label'    => (string) ($field['label'] ?? $name),
                'required' => (bool) ($field['required'] ?? false),
            ];
        }

        return $out;
    }

    public static function exists(?string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function get(string $key): array
    {
        return self::all()[$key];
    }

    /** @return array<string, string> [key => label] of the templates, without the custom letter */
    public static function labels(): array
    {
        return array_map(fn (array $template) => $template['label'], self::all());
    }

    /** @return array<string, string> every letter type of the hub: the templates plus the custom letter */
    public static function letterTypes(): array
    {
        return self::labels() + [self::CUSTOM => 'Custom Letter'];
    }

    public static function label(?string $key): string
    {
        return self::letterTypes()[$key] ?? (string) $key;
    }

    public static function needsEmployee(string $key): bool
    {
        return (self::get($key)['employee'] ?? false) === 'required';
    }

    /** Validation rules of a template's own fields, under `fields.*`. */
    public static function rules(string $key): array
    {
        $rules = [];

        foreach (self::get($key)['fields'] as $name => $field) {
            $presence = $field['required'] ? 'required' : 'nullable';

            match ($field['type']) {
                'date'     => $rules["fields.{$name}"] = [$presence, 'date'],
                'money'    => $rules["fields.{$name}"] = [$presence, 'numeric', 'min:0', 'max:999999999999999'],
                'textarea' => $rules["fields.{$name}"] = [$presence, 'string', 'max:5000'],
                // A row left empty is dropped (cleanFields); whether one item is left is checked after that.
                'items'    => $rules += [
                    "fields.{$name}"        => [$presence, 'array'],
                    "fields.{$name}.*.name" => ['nullable', 'string', 'max:255'],
                    "fields.{$name}.*.qty"  => ['nullable', 'string', 'max:50'],
                    "fields.{$name}.*.note" => ['nullable', 'string', 'max:255'],
                ],
                // Amounts arrive as typed, with thousands separators; cleanFields() turns them into numbers.
                'lines'    => $rules += [
                    "fields.{$name}"          => [$presence, 'array'],
                    "fields.{$name}.*.name"   => ['nullable', 'string', 'max:255'],
                    "fields.{$name}.*.amount" => ['nullable', 'string', 'max:30'],
                ],
                default    => $rules["fields.{$name}"] = [$presence, 'string', 'max:255'],
            };
        }

        if ($key === 'assignment_letter' || $key === 'ext_assignment_letter') {
            $rules['fields.period_to'][] = 'after_or_equal:fields.period_from';
        }

        return $rules;
    }

    /** Attribute names for validation messages: `fields.purpose` → "purpose of the letter". */
    public static function attributes(string $key): array
    {
        $names = [];
        foreach (self::get($key)['fields'] as $name => $field) {
            $names["fields.{$name}"] = mb_strtolower($field['label']);
        }

        return $names;
    }

    /**
     * The employee data a letter prints, as it is when the letter is
     * generated — saved with the letter so a reprint looks the same later.
     */
    public static function employeeSnapshot(?Employee $employee): ?array
    {
        if (!$employee) {
            return null;
        }

        $basic = $employee->basicData;

        return [
            'name'       => trim($basic?->full_name ?? '') ?: ($basic?->nick_name ?: $employee->eci),
            'eci'        => $employee->eci,
            'position'   => $basic?->position,
            'department' => $basic?->department,
            'join_date'  => $basic?->since_date ? Carbon::parse($basic->since_date)->toDateString() : null,
        ];
    }

    /** Only the fields a template has, with item rows that were left empty dropped. */
    public static function cleanFields(string $key, array $input): array
    {
        $clean = [];

        foreach (self::get($key)['fields'] as $name => $field) {
            $value = $input[$name] ?? null;

            if ($field['type'] === 'items') {
                $value = array_values(array_filter(array_map(fn ($row) => [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'qty'  => trim((string) ($row['qty'] ?? '')),
                    'note' => trim((string) ($row['note'] ?? '')),
                ], (array) $value), fn ($row) => $row['name'] !== ''));
            } elseif ($field['type'] === 'lines') {
                $value = array_values(array_filter(array_map(fn ($row) => [
                    'name'   => trim((string) ($row['name'] ?? '')),
                    'amount' => (float) (preg_replace('/\D/', '', preg_replace('/,\d{1,2}$/', '', trim((string) ($row['amount'] ?? '')))) ?: 0),
                ], (array) $value), fn ($row) => $row['name'] !== '' && $row['amount'] > 0));
            } elseif ($field['type'] === 'money') {
                $value = $value === null || $value === '' ? null : (float) $value;
            } else {
                $value = is_string($value) ? trim($value) : $value;
            }

            if (in_array($field['type'], ['items', 'lines'], true) && $field['required'] && $value === []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "fields.{$name}" => 'Enter at least one ' . ($field['type'] === 'lines' ? 'line (name and amount)' : 'item') . ' in ' . mb_strtolower($field['label']) . '.',
                ]);
            }

            $clean[$name] = $value;
        }

        return $clean;
    }

    // ── Amount in words ─────────────────────────────────────────────────────

    /** "satu juta lima ratus ribu rupiah" / "one million five hundred thousand rupiah". */
    public static function amountInWords(float $amount, string $language): string
    {
        $amount = (int) round($amount);

        $words = $language === 'en' ? self::english($amount) : self::indonesian($amount);

        return trim($words) . ' rupiah';
    }

    private static function indonesian(int $n): string
    {
        $units = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        return trim(match (true) {
            $n === 0             => 'nol',
            $n < 12              => $units[$n],
            $n < 20              => self::indonesian($n - 10) . ' belas',
            $n < 100             => self::indonesian(intdiv($n, 10)) . ' puluh ' . ($n % 10 ? self::indonesian($n % 10) : ''),
            $n < 200             => 'seratus ' . ($n - 100 ? self::indonesian($n - 100) : ''),
            $n < 1000            => self::indonesian(intdiv($n, 100)) . ' ratus ' . ($n % 100 ? self::indonesian($n % 100) : ''),
            $n < 2000            => 'seribu ' . ($n - 1000 ? self::indonesian($n - 1000) : ''),
            $n < 1000000         => self::indonesian(intdiv($n, 1000)) . ' ribu ' . ($n % 1000 ? self::indonesian($n % 1000) : ''),
            $n < 1000000000      => self::indonesian(intdiv($n, 1000000)) . ' juta ' . ($n % 1000000 ? self::indonesian($n % 1000000) : ''),
            $n < 1000000000000   => self::indonesian(intdiv($n, 1000000000)) . ' miliar ' . ($n % 1000000000 ? self::indonesian($n % 1000000000) : ''),
            default              => self::indonesian(intdiv($n, 1000000000000)) . ' triliun ' . ($n % 1000000000000 ? self::indonesian($n % 1000000000000) : ''),
        });
    }

    private static function english(int $n): string
    {
        $small = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
            'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $scales = [1000000000000 => 'trillion', 1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'];

        if ($n < 20) {
            return $small[$n];
        }
        if ($n < 100) {
            return $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $small[$n % 10] : '');
        }
        if ($n < 1000) {
            return $small[intdiv($n, 100)] . ' hundred' . ($n % 100 ? ' ' . self::english($n % 100) : '');
        }

        foreach ($scales as $value => $name) {
            if ($n >= $value) {
                return self::english(intdiv($n, $value)) . " {$name}" . ($n % $value ? ' ' . self::english($n % $value) : '');
            }
        }

        return (string) $n;
    }
}
