<?php

namespace Tests\Unit\Letters;

use App\Support\Letters\DefaultDesigns;
use App\Support\Letters\LetterBody;
use App\Support\Letters\LetterTemplates;
use Tests\TestCase;

/** Aturan murni isi surat dari editor: placeholder, pengisian nilai, template bawaan. */
class LetterBodyTest extends TestCase
{
    private const FIELDS = [
        'purpose' => ['type' => 'text', 'label' => 'Purpose', 'required' => true],
        'amount'  => ['type' => 'money', 'label' => 'Amount', 'required' => true],
        'task'    => ['type' => 'textarea', 'label' => 'Task', 'required' => false],
        'items'   => ['type' => 'items', 'label' => 'Items', 'required' => false],
        'end'     => ['type' => 'date', 'label' => 'End', 'required' => false, 'empty' => ['id' => 'sampai sekarang', 'en' => 'to date']],
    ];

    private function ctx(string $lang = 'id'): array
    {
        return ['lang' => $lang, 'letter_number' => '001/OF', 'letter_date' => '2026-10-26', 'city' => 'Jakarta', 'company' => 'PT Maju',
            'signer_name' => 'Siti', 'signer_title' => 'HR', 'signature' => null];
    }

    public function test_placeholders_include_base_fields_and_money_words(): void
    {
        $keys = array_keys(LetterBody::placeholders(self::FIELDS));

        foreach (['letter_number', 'signature_block', 'purpose', 'amount', 'amount_words'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_unknown_placeholders_are_found(): void
    {
        $this->assertSame([], LetterBody::unknown('<p>{{purpose}} {{letter_number}} {{amount_words}}</p>', self::FIELDS));
        $this->assertSame(['nope'], LetterBody::unknown('<p>{{purpose}} {{nope}}</p>', self::FIELDS));
    }

    public function test_values_are_formatted_by_type_and_language(): void
    {
        $input = ['purpose' => 'visa <b>', 'amount' => 1500000, 'task' => "a\nb", 'items' => [['name' => 'Laptop', 'qty' => '1', 'note' => '']], 'end' => null];

        $id = LetterBody::values(self::FIELDS, $input, ['name' => 'Budi', 'eci' => 'E1', 'position' => 'QA', 'department' => 'IT', 'join_date' => '2024-02-01'], $this->ctx('id'));
        $this->assertSame('Rp 1.500.000,-', $id['amount']);
        $this->assertSame('Satu juta lima ratus ribu rupiah', $id['amount_words']);
        $this->assertSame('1 Februari 2024', $id['join_date']);
        $this->assertSame('26 Oktober 2026', $id['letter_date']);
        $this->assertSame('sampai sekarang', $id['end']);
        $this->assertStringContainsString('a<br />', $id['task']['html']);
        $this->assertStringContainsString('Nama Barang', $id['items']['html']);

        $en = LetterBody::values(self::FIELDS, $input, null, $this->ctx('en'));
        $this->assertSame('IDR 1,500,000', $en['amount']);
        $this->assertSame('to date', $en['end']);
        $this->assertSame('', $en['employee_concerned_line']['html']);
    }

    public function test_render_escapes_typed_text_and_keeps_html_values(): void
    {
        $values = LetterBody::values(self::FIELDS, ['purpose' => '<script>x</script>'], null, $this->ctx());
        $html = LetterBody::render('<p>{{purpose}}</p>{{signature_block}}', $values);

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Jakarta, 26 Oktober 2026', $html);
        $this->assertStringContainsString('Hormat kami,', $html);
    }

    public function test_every_built_in_default_only_uses_known_placeholders(): void
    {
        foreach (DefaultDesigns::bodies() as $key => $design) {
            $fields = LetterTemplates::normalizeFields(LetterTemplates::BUILT_IN[$key]['fields']);

            foreach (['body_id', 'body_en'] as $column) {
                $this->assertSame([], LetterBody::unknown($design[$column], $fields), "{$key} {$column}");
                $this->assertTrue(
                    str_contains($design[$column], '{{signature_block}}') || str_contains($design[$column], '{{signature}}'),
                    "{$key} {$column} has a signing block"
                );
            }
        }
    }

    public function test_sample_values_leave_no_placeholder_unfilled(): void
    {
        foreach (DefaultDesigns::bodies() as $key => $design) {
            $fields = LetterTemplates::normalizeFields(LetterTemplates::BUILT_IN[$key]['fields']);
            foreach (['id', 'en'] as $lang) {
                $html = LetterBody::render($design["body_{$lang}"], LetterBody::sampleValues($fields, $lang));
                $this->assertDoesNotMatchRegularExpression('/\{\{[^}]+\}\}/', $html, "{$key} {$lang}");
            }
        }
    }

    public function test_lines_field_prints_a_table_and_a_total(): void
    {
        $fields = LetterTemplates::normalizeFields(['pay' => ['type' => 'lines', 'label' => 'Pay', 'required' => true]]);
        $this->assertArrayHasKey('pay_total', LetterBody::placeholders($fields));

        $values = LetterBody::values($fields, ['pay' => [['name' => 'Base', 'amount' => 8000000], ['name' => 'Transport', 'amount' => 1000000]]], null, $this->ctx('en'));
        $this->assertSame('IDR 9,000,000', $values['pay_total']);
        $this->assertStringContainsString('Base', $values['pay']['html']);
        $this->assertStringContainsString('IDR 8,000,000', $values['pay']['html']);
    }

    public function test_offering_letter_template_is_built_in_and_needs_a_line(): void
    {
        $this->assertTrue(LetterTemplates::exists('offering_letter'));
        $this->assertFalse(LetterTemplates::needsEmployee('offering_letter'));
        $this->assertSame('lines', LetterTemplates::get('offering_letter')['fields']['compensation']['type']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        LetterTemplates::cleanFields('offering_letter', [
            'candidate_name' => 'A', 'position_title' => 'B', 'joining_date' => '2026-12-01', 'compensation' => [['name' => '', 'amount' => '']],
        ]);
    }

    public function test_typed_amounts_in_lines_become_numbers(): void
    {
        $clean = LetterTemplates::cleanFields('offering_letter', [
            'candidate_name' => 'A', 'position_title' => 'B', 'joining_date' => '2026-12-01',
            'compensation' => [['name' => 'Base', 'amount' => '8.000.000'], ['name' => '', 'amount' => '5']],
        ]);

        $this->assertSame([['name' => 'Base', 'amount' => 8000000.0]], $clean['compensation']);
    }
}
