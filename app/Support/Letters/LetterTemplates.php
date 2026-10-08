<?php

namespace App\Support\Letters;

use App\Models\Employee;
use Illuminate\Support\Carbon;

/**
 * The letter templates of HR & General → Letter Templates → Create Letter.
 *
 * Each template is written in code: its fields here, its wording — Bahasa
 * Indonesia and English side by side — in one Blade,
 * resources/views/hr-general/letters/pdf/{key}.blade.php. Words every letter
 * shares (Number, Subject, Sincerely…) are in lang/{id,en}/letters.php.
 * A new template is one entry here plus one Blade.
 *
 * Field types: text · textarea · date · money · items (rows of name / qty / note).
 * `employee`: 'required' — the letter is about an employee; 'optional'; or false.
 */
class LetterTemplates
{
    public const CUSTOM = 'custom_letter';

    public const TEMPLATES = [
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
    ];

    public static function exists(?string $key): bool
    {
        return isset(self::TEMPLATES[$key]);
    }

    public static function get(string $key): array
    {
        return self::TEMPLATES[$key];
    }

    /** @return array<string, string> [key => label] of the templates, without the custom letter */
    public static function labels(): array
    {
        return array_map(fn (array $template) => $template['label'], self::TEMPLATES);
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
        return (self::TEMPLATES[$key]['employee'] ?? false) === 'required';
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
                default    => $rules["fields.{$name}"] = [$presence, 'string', 'max:255'],
            };
        }

        if ($key === 'assignment_letter') {
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
            } elseif ($field['type'] === 'money') {
                $value = $value === null || $value === '' ? null : (float) $value;
            } else {
                $value = is_string($value) ? trim($value) : $value;
            }

            if ($field['type'] === 'items' && $field['required'] && $value === []) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "fields.{$name}" => 'Enter at least one item in ' . mb_strtolower($field['label']) . '.',
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
