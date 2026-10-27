<?php

namespace App\Support\Letters;

use App\Models\LetterTypeSetting;
use App\Support\Contracts\ContractRules;
use Illuminate\Support\Carbon;

/**
 * The wording of a letter template, written in the Word-style editor: HTML with {{placeholders}}.
 * This class knows which placeholders exist, fills them for a real letter or with sample data (the
 * editor's Preview), and renders the text. Pure — no database — so it is easy to test.
 *
 * Placeholders: the ones every letter has (BASE) plus one per field of the template, named after the
 * field key — `{{purpose}}`, `{{location}}`… A money field also answers `{{amount_words}}` when it has
 * no field of that name itself.
 */
class LetterBody
{
    /** Placeholders every letter has, key → meaning (shown in the editor's Fields menu). */
    public const BASE = [
        'letter_number'    => 'Letter number',
        'letter_date'      => 'Letter date (2 October 2026)',
        'subject'          => 'Letter subject / title',
        'recipient'        => 'Recipient — who the letter is addressed to',
        'city'             => 'Signing city',
        'company'          => 'Company name',
        'signer_name'      => 'Signatory name',
        'signer_title'     => 'Signatory position',
        'signature'        => 'Signature image of the signatory (shown once the letter is signed)',
        'signature_block'  => 'The signing block: city and date, signature, name and position — aligned right',
        'employee_name'    => 'Employee name',
        'employee_id'      => 'Employee ID',
        'position'         => 'Employee position',
        'department'       => 'Employee department',
        'join_date'        => 'Employee join date',
        'employee_details' => 'Employee table: name, ID, position, department',
        'employee_concerned_line' => 'A line naming the employee concerned (empty when the letter has none)',
        'halaman_baru'     => 'Page break — the text after it starts on a new page (use the Page break button)',
    ];

    public const FIELD_TYPES = ['text' => 'Short text', 'textarea' => 'Long text', 'date' => 'Date', 'money' => 'Amount (Rp)', 'items' => 'List of items', 'lines' => 'Lines with an amount'];

    /** Keys a template's own field may not take. */
    public static function reserved(): array
    {
        return array_keys(self::BASE);
    }

    /** @return array<string,string> every placeholder a template with these fields may use */
    public static function placeholders(array $fields): array
    {
        $out = self::BASE;
        foreach ($fields as $key => $field) {
            $out[$key] = $field['label'] ?? $key;
            if (($field['type'] ?? '') === 'money' && !isset($fields[$key . '_words'])) {
                $out[$key . '_words'] = ($field['label'] ?? $key) . ' in words';
            }
            if (($field['type'] ?? '') === 'lines') {
                $out[$key . '_total'] = ($field['label'] ?? $key) . ' — total of the amounts';
            }
        }

        return $out;
    }

    /** Placeholders in the text that do not exist for this template (a typing mistake). @return string[] */
    public static function unknown(string $html, array $fields): array
    {
        return ContractRules::unknownPlaceholders($html, array_keys(self::placeholders($fields)));
    }

    public static function render(string $html, array $values): string
    {
        return ContractRules::render($html, $values);
    }

    // ── Values ───────────────────────────────────────────────────────────────

    /**
     * @param  array  $fields     the template's field definitions
     * @param  array  $input      what was typed for them (the letter's saved fields)
     * @param  array|null $employee the employee snapshot saved with the letter
     * @param  array  $ctx        lang, letter_number, letter_date, city, company, signer_name, signer_title, signature (data URI|null)
     */
    public static function values(array $fields, array $input, ?array $employee, array $ctx): array
    {
        $lang = ($ctx['lang'] ?? 'id') === LetterTypeSetting::LANGUAGE_ENGLISH ? 'en' : 'id';
        $date = fn ($value) => $value ? Carbon::parse($value)->locale($lang)->translatedFormat('j F Y') : '';
        $money = fn ($amount) => $lang === 'en'
            ? 'IDR ' . number_format((float) $amount, 0, '.', ',')
            : 'Rp ' . number_format((float) $amount, 0, ',', '.') . ',-';

        $signature = $ctx['signature'] ?? null;
        $signatureImg = $signature ? '<img src="' . e($signature) . '" style="max-height:64pt;max-width:160pt">' : '';
        $closing = $ctx['closing'] ?? ($lang === 'en' ? 'Sincerely,' : 'Hormat kami,');

        $values = [
            'letter_number' => (string) ($ctx['letter_number'] ?? ''),
            'letter_date'   => $date($ctx['letter_date'] ?? null),
            'subject'       => (string) ($ctx['subject'] ?? ''),
            'recipient'     => (string) ($ctx['recipient'] ?? ''),
            'city'          => (string) ($ctx['city'] ?? ''),
            'company'       => (string) ($ctx['company'] ?? ''),
            'signer_name'   => (string) ($ctx['signer_name'] ?? ''),
            'signer_title'  => (string) ($ctx['signer_title'] ?? ''),
            'signature'     => ['html' => $signatureImg],
            'signature_block' => ['html' =>
                '<table align="right" style="width:210pt;page-break-inside:avoid"><tbody>'
                . '<tr><td>' . e($ctx['city'] ?? '') . ', ' . e($date($ctx['letter_date'] ?? null)) . '<br>' . e($closing) . '</td></tr>'
                . '<tr><td style="height:70pt;vertical-align:middle">' . $signatureImg . '</td></tr>'
                . '<tr><td><strong><u>' . e($ctx['signer_name'] ?? '') . '</u></strong><br>' . e($ctx['signer_title'] ?? '') . '</td></tr>'
                . '</tbody></table>'],
            'employee_name' => (string) ($employee['name'] ?? ''),
            'employee_id'   => (string) ($employee['eci'] ?? ''),
            'position'      => (string) ($employee['position'] ?? ''),
            'department'    => (string) ($employee['department'] ?? ''),
            'join_date'     => $date($employee['join_date'] ?? null),
            'employee_details' => ['html' => self::employeeDetails($employee, $lang)],
            'employee_concerned_line' => ['html' => $employee
                ? '<p>' . ($lang === 'en' ? 'Employee concerned' : 'Karyawan terkait') . ': ' . e($employee['name'] ?? '')
                    . (!empty($employee['position']) ? ' (' . e($employee['position']) . ')' : '') . '</p>'
                : ''],
        ];

        foreach ($fields as $key => $field) {
            $raw = $input[$key] ?? null;
            $empty = $raw === null || $raw === '' || $raw === [];

            if ($empty && !empty($field['empty'][$lang])) {
                $values[$key] = (string) $field['empty'][$lang];
                continue;
            }

            $values[$key] = match ($field['type'] ?? 'text') {
                'date'     => $date($raw),
                'money'    => $empty ? '' : $money($raw),
                'textarea' => ['html' => nl2br(e((string) $raw))],
                'items'    => ['html' => self::itemsTable((array) $raw, $lang)],
                'lines'    => ['html' => self::linesTable((array) $raw, $money)],
                default    => (string) $raw,
            };

            if (($field['type'] ?? '') === 'lines') {
                $values[$key . '_total'] = $money(collect((array) $raw)->sum(fn ($line) => (float) ($line['amount'] ?? 0)));
            }

            if (($field['type'] ?? '') === 'money' && !isset($fields[$key . '_words'])) {
                $values[$key . '_words'] = $empty ? '' : ucfirst(LetterTemplates::amountInWords((float) $raw, $lang));
            }
        }

        // A money field and a text field "amount_words" typed over it: what was typed wins.
        foreach ($fields as $key => $field) {
            if (str_ends_with($key, '_words') && ($field['type'] ?? 'text') === 'text' && trim((string) ($input[$key] ?? '')) === '') {
                $base = substr($key, 0, -6);
                if (($fields[$base]['type'] ?? '') === 'money' && ($input[$base] ?? '') !== '' && isset($input[$base])) {
                    $values[$key] = ucfirst(LetterTemplates::amountInWords((float) $input[$base], $lang));
                }
            }
        }

        return $values;
    }

    /** The values the editor's Preview and the template list use: a made-up employee and a sample for each field. */
    public static function sampleValues(array $fields, string $lang, ?string $closing = null): array
    {
        $id = $lang !== LetterTypeSetting::LANGUAGE_ENGLISH;
        $input = [];
        foreach ($fields as $key => $field) {
            $input[$key] = match ($field['type'] ?? 'text') {
                'date'     => now()->toDateString(),
                'money'    => 1500000,
                'textarea' => ($id ? 'Contoh isi ' : 'Sample text for ') . ($field['label'] ?? $key) . '.',
                'items'    => [['name' => $id ? 'Laptop' : 'Laptop', 'qty' => '1 unit', 'note' => ''], ['name' => $id ? 'Tas laptop' : 'Laptop bag', 'qty' => '1', 'note' => $id ? 'Baru' : 'New']],
                'lines'    => [['name' => $id ? 'Gaji Pokok' : 'Base Salary', 'amount' => 4500000], ['name' => $id ? 'Tunjangan Transportasi' : 'Transport Allowance', 'amount' => 750000]],
                default    => ($id ? 'Contoh ' : 'Sample ') . mb_strtolower($field['label'] ?? $key),
            };
            if (str_ends_with($key, '_words')) {
                $input[$key] = '';
            }
        }

        return self::values($fields, $input, [
            'name' => $id ? 'Nama Karyawan' : 'Employee Name', 'eci' => 'ECI-0001', 'position' => 'Management Trainee',
            'department' => 'Human Capital', 'join_date' => now()->subYears(2)->toDateString(),
        ], [
            'lang' => $lang, 'letter_number' => 'SAMPLE', 'subject' => $id ? 'Perihal contoh' : 'Sample subject', 'recipient' => $id ? 'Nama Penerima' : 'Recipient Name', 'letter_date' => now()->toDateString(), 'city' => 'Yogyakarta',
            'company' => 'PT Contoh Perusahaan', 'signer_name' => $id ? 'Nama Penandatangan' : 'Signatory Name',
            'signer_title' => $id ? 'Jabatan Penandatangan' : 'Signatory Position', 'signature' => null, 'closing' => $closing,
        ]);
    }

    /** The wording a custom letter starts with when no template is picked: title, number, recipient, signing block. */
    public static function blank(string $language): string
    {
        $en = $language === LetterTypeSetting::LANGUAGE_ENGLISH;

        return '<p style="text-align:center"><strong><u>{{subject}}</u></strong></p>'
            . '<p style="text-align:center">' . ($en ? 'Number' : 'Nomor') . ': {{letter_number}}</p>'
            . '<p>' . ($en ? 'To:' : 'Kepada Yth.') . '<br><strong>{{recipient}}</strong>' . ($en ? '' : '<br>Di Tempat') . '</p>'
            . '<p><br></p><p><br></p><p>{{signature_block}}</p>';
    }

    private static function employeeDetails(?array $employee, string $lang): string
    {
        $labels = $lang === 'en'
            ? ['name' => 'Name', 'eci' => 'Employee ID', 'position' => 'Position', 'department' => 'Department']
            : ['name' => 'Nama', 'eci' => 'No. Karyawan', 'position' => 'Jabatan', 'department' => 'Departemen'];

        $rows = '';
        foreach ($labels as $key => $label) {
            if (!empty($employee[$key])) {
                $rows .= '<tr><td style="width:120pt">' . e($label) . '</td><td style="width:12pt">:</td><td>' . e($employee[$key]) . '</td></tr>';
            }
        }

        return $rows === '' ? '' : '<table style="margin-left:20pt"><tbody>' . $rows . '</tbody></table>';
    }

    /** Compensation-style lines: a bullet and the name on the left, the amount on the right. */
    private static function linesTable(array $lines, \Closure $money): string
    {
        $html = '<table><tbody>';
        foreach (array_values($lines) as $line) {
            $html .= '<tr><td>&bull; ' . e($line['name'] ?? '') . '</td><td style="padding-left:28pt;white-space:nowrap">' . e($money($line['amount'] ?? 0)) . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }

    private static function itemsTable(array $items, string $lang): string
    {
        $head = $lang === 'en' ? ['No.', 'Item', 'Quantity', 'Notes'] : ['No.', 'Nama Barang', 'Jumlah', 'Keterangan'];
        $cell = 'border:0.75pt solid #444;padding:4pt 6pt;vertical-align:top';
        $html = '<table style="width:100%;border-collapse:collapse"><tbody><tr>';
        foreach ($head as $i => $h) {
            $html .= '<td style="' . $cell . ';background-color:#eeeeee;font-weight:bold' . ($i === 0 ? ';width:24pt' : ($i === 2 ? ';width:70pt' : '')) . '">' . e($h) . '</td>';
        }
        $html .= '</tr>';
        foreach (array_values($items) as $n => $item) {
            $html .= '<tr><td style="' . $cell . '">' . ($n + 1) . '</td><td style="' . $cell . '">' . e($item['name'] ?? '') . '</td>'
                . '<td style="' . $cell . '">' . e($item['qty'] ?? '') . '</td><td style="' . $cell . '">' . e($item['note'] ?? '') . '</td></tr>';
        }

        return $html . '</tbody></table>';
    }
}
