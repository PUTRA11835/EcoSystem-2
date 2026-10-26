<?php

namespace Tests\Unit\Contracts;

use App\Support\Contracts\ContractHtmlSanitizer;
use App\Support\Contracts\ContractPlaceholders;
use App\Support\Contracts\ContractRules as R;
use App\Support\Contracts\DefaultTemplates;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Aturan murni menu Contract (HC-D66): status, nomor, tanggal, gaji, template, placeholder, kesiapan. */
class ContractRulesTest extends TestCase
{
    // ── jenis ────────────────────────────────────────────────────────────

    #[DataProvider('typeCases')]
    public function test_normalize_type(?string $in, ?string $expected): void
    {
        $this->assertSame($expected, R::normalizeType($in));
    }

    public static function typeCases(): array
    {
        return [['PKWT', 'PKWT'], ['spkwt', 'PKWT'], [' pkwtt ', 'PKWTT'], ['Permanent', 'PKWTT'], ['EXT', 'EXTERNAL'], ['PKS', 'EXTERNAL'], ['Freelance', null], [null, null], ['', null]];
    }

    // ── status ───────────────────────────────────────────────────────────

    #[DataProvider('statusCases')]
    public function test_effective_status(?string $status, bool $active, ?string $end, string $expected): void
    {
        $this->assertSame($expected, R::effectiveStatus($status, $active, $end, '2026-10-07'));
    }

    public static function statusCases(): array
    {
        return [
            'draft stays draft even if flags say active' => ['draft', true, null, 'draft'],
            'terminated wins over dates'               => ['terminated', true, '2030-01-01', 'terminated'],
            'active, no end'                           => ['active', true, null, 'active'],
            'active, ends today is still active'       => ['active', true, '2026-10-07', 'active'],
            'active, ended yesterday -> expired'       => ['active', true, '2026-10-06', 'expired'],
            'legacy row, active, no status'            => [null, true, null, 'active'],
            'legacy row, active, past end'             => [null, true, '2020-01-01 00:00:00', 'expired'],
            'legacy row, inactive, no status'          => [null, false, null, 'inactive'],
            'stored expired'                           => ['expired', false, '2020-01-01', 'expired'],
        ];
    }

    public function test_only_active_status_sets_is_active(): void
    {
        $this->assertTrue(R::isActiveFor('active'));
        $this->assertFalse(R::isActiveFor('draft'));
        $this->assertFalse(R::isActiveFor('terminated'));
    }

    // ── nomor ────────────────────────────────────────────────────────────

    public function test_format_number(): void
    {
        $p = ['PKWT' => 'SPKWT', 'PKWTT' => 'SPKWTT', 'EXTERNAL' => 'PKS'];
        $this->assertSame('SPKWT/2026/10/010001', R::formatNumber('PKWT', Carbon::parse('2026-10-07'), '01', 1, 4, $p));
        $this->assertSame('SPKWTT/2026/08/010042', R::formatNumber('PKWTT', Carbon::parse('2026-08-31'), '01', 42, 4, $p));
        $this->assertSame('PKS/2027/01/1212345', R::formatNumber('EXTERNAL', Carbon::parse('2027-01-02'), '12', 12345, 4, $p), 'urut melebihi digit tidak dipotong');
    }

    // ── tanggal ──────────────────────────────────────────────────────────

    #[DataProvider('dateCases')]
    public function test_date_error(string $type, ?string $start, ?string $end, ?string $expectedFragment): void
    {
        $error = R::dateError($type, $start, $end, 60);
        $expectedFragment === null ? $this->assertNull($error) : $this->assertStringContainsString($expectedFragment, (string) $error);
    }

    public static function dateCases(): array
    {
        return [
            'pkwt ok'                  => ['PKWT', '2026-07-27', '2027-01-26', null],
            'pkwt needs end'           => ['PKWT', '2026-07-27', null, 'needs an end date'],
            'pkwt end before start'    => ['PKWT', '2026-07-27', '2026-07-01', 'on or after'],
            'pkwt exactly 60 months'   => ['PKWT', '2026-01-01', '2031-01-01', null],
            'pkwt over 60 months'      => ['PKWT', '2026-01-01', '2031-01-02', 'cannot be longer'],
            'pkwtt no end'             => ['PKWTT', '2026-07-27', null, null],
            'pkwtt with end refused'   => ['PKWTT', '2026-07-27', '2027-01-26', 'no end date'],
            'external open ended'      => ['EXTERNAL', '2026-07-27', null, null],
            'start required'           => ['PKWT', null, '2027-01-01', 'Start date is required'],
        ];
    }

    // ── gaji ─────────────────────────────────────────────────────────────

    #[DataProvider('amountCases')]
    public function test_parse_amount(mixed $in, float $expected): void
    {
        $this->assertSame($expected, R::parseAmount($in));
    }

    public static function amountCases(): array
    {
        return [
            ['4.500.000', 4500000.0], ['4,500,000', 4500000.0], ['4.500.000,50', 4500000.5], ['4,500,000.50', 4500000.5],
            ['4500000', 4500000.0], ['4500000.5', 4500000.5], ['750,5', 750.5], ['Rp 1.250.000', 1250000.0], ['', 0.0], ['abc', 0.0], [250000, 250000.0], ["-5", -5.0], ["Rp -1.000", -1000.0],
        ];
    }

    public function test_clean_components_and_total(): void
    {
        $rows = R::cleanComponents([
            ['name' => ' Tunjangan Transportasi ', 'amount' => '750.000'],
            ['name' => '', 'amount' => '999'],
            ['name' => 'Uang Makan', 'amount' => '-5'],
            'bukan baris',
            ['name' => 'Tanpa nilai'],
        ]);
        $this->assertSame([
            ['name' => 'Tunjangan Transportasi', 'amount' => 750000.0],
            ['name' => 'Uang Makan', 'amount' => 0.0],
            ['name' => 'Tanpa nilai', 'amount' => 0.0],
        ], $rows);
        $this->assertSame(5250000.0, R::totalSalary(4500000.0, $rows));
        $this->assertSame(0.0, R::totalSalary(null, []));
        $this->assertSame([], R::cleanComponents('x'));
        $this->assertCount(20, R::cleanComponents(array_fill(0, 30, ['name' => 'x', 'amount' => 1])));
        $this->assertSame('Rp 5.750.000', R::money(5750000));
    }

    // ── template ─────────────────────────────────────────────────────────

    public function test_pick_template_priority(): void
    {
        $t = fn (int $id, string $type, ?string $pos, bool $system, string $updated, string $status = 'active') =>
            ['id' => $id, 'contract_type' => $type, 'position' => $pos, 'is_system_default' => $system, 'updated_at' => $updated, 'status' => $status];

        $all = [
            $t(1, 'PKWT', null, true, '2026-01-01'),
            $t(2, 'PKWT', null, false, '2026-03-01'),
            $t(3, 'PKWT', null, false, '2026-05-01'),
            $t(4, 'PKWT', 'Consultant', false, '2026-02-01'),
            $t(5, 'PKWT', 'Analyst', false, '2026-02-01', 'inactive'),
            $t(6, 'PKWTT', null, true, '2026-01-01'),
        ];

        $this->assertSame(4, R::pickTemplate($all, 'PKWT', 'consultant ')['id'], 'Position cocok menang (tak peduli huruf besar)');
        $this->assertSame(3, R::pickTemplate($all, 'PKWT', 'Analyst')['id'], 'template nonaktif dilewati, lalu umum terbaru');
        $this->assertSame(3, R::pickTemplate($all, 'PKWT', null)['id']);
        $this->assertSame(1, R::pickTemplate([$all[0], $all[5]], 'PKWT', 'Apa saja')['id'], 'tanpa yang lain: bawaan sistem');
        $this->assertSame(6, R::pickTemplate($all, 'PKWTT', 'Consultant')['id'], 'jenis harus sama');
        $this->assertNull(R::pickTemplate($all, 'EXTERNAL', null));
    }

    public function test_pick_template_follows_the_position_type_before_the_position(): void
    {
        $t = fn (int $id, string $type, ?string $pos, ?string $posType, bool $system = false, string $status = 'active') =>
            ['id' => $id, 'contract_type' => $type, 'position' => $pos, 'position_type' => $posType, 'is_system_default' => $system, 'updated_at' => '2026-05-01', 'status' => $status];

        $all = [
            $t(1, 'PKWT', null, null, true),
            $t(2, 'PKWT', null, null),                 // general, written by HR
            $t(3, 'PKWT', 'Account Manager', null),    // an exact job title
            $t(4, 'PKWT', null, 'Sales'),              // a position type
            $t(5, 'PKWT', null, 'Staff', false, 'inactive'),
            $t(6, 'INTERNSHIP', null, 'Internship'),
            $t(7, 'EXTERNAL', null, 'Management Trainee'),
        ];

        $this->assertSame(4, R::pickTemplate($all, 'PKWT', 'Account Manager', 'sales')['id'], 'position type wins over a job title, whatever the case');
        $this->assertSame(3, R::pickTemplate($all, 'PKWT', 'Account Manager', null)['id'], 'no position type: the job title');
        $this->assertSame(3, R::pickTemplate($all, 'PKWT', 'Account Manager', 'Unknown')['id'], 'an unknown position type falls back to the job title');
        $this->assertSame(2, R::pickTemplate($all, 'PKWT', null, 'Staff')['id'], 'an inactive template is skipped, then the general one');
        $this->assertSame(2, R::pickTemplate($all, 'PKWT', null, null)['id'], 'a template of a position type is never "general"');
        $this->assertSame(6, R::pickTemplate($all, 'INTERNSHIP', null, 'Internship')['id']);
        $this->assertSame(7, R::pickTemplate($all, 'EXTERNAL', null, 'Management Trainee')['id']);
        $this->assertNull(R::pickTemplate($all, 'ADDENDUM', null, 'Sales'), 'the type has to match');
    }

    public function test_internship_and_addendum_types(): void
    {
        $this->assertSame('INTERNSHIP', R::normalizeType('Internship'));
        $this->assertSame('INTERNSHIP', R::normalizeType(' magang '));
        $this->assertSame('ADDENDUM', R::normalizeType('addendum'));
        $this->assertNull(R::normalizeType('Freelance'));
        $this->assertSame(['PKWT', 'PKWTT', 'INTERNSHIP', 'ADDENDUM'], R::INTERNAL_TYPES);

        $this->assertStringContainsString('needs an end date', (string) R::dateError('INTERNSHIP', '2026-07-01', null, 60));
        $this->assertNull(R::dateError('INTERNSHIP', '2026-07-01', '2026-12-31', 60));
        $this->assertNull(R::dateError('ADDENDUM', '2026-07-01', null, 60), 'an addendum may be open-ended');
        $this->assertNull(R::dateError('ADDENDUM', '2026-07-01', '2026-12-31', 60));
    }

    public function test_alias_keys_cover_every_spelling_of_a_built_in_type(): void
    {
        $this->assertEqualsCanonicalizing(['PKWT', 'SPKWT'], R::aliasKeysOf('PKWT'));
        $this->assertContains('PERMANENT', R::aliasKeysOf('PKWTT'));
        $this->assertContains('MAGANG', R::aliasKeysOf('INTERNSHIP'));
        $this->assertSame(['SOMETHING'], R::aliasKeysOf('SOMETHING'), 'an unknown type stands for itself');
    }

    public function test_every_internal_type_has_a_number_prefix(): void
    {
        $prefixes = config('hc_contract.number.prefixes');
        foreach ([...R::INTERNAL_TYPES, 'EXTERNAL'] as $type) {
            $this->assertArrayHasKey($type, $prefixes, $type);
        }
        $this->assertSame('SPM/2026/10/010001', R::formatNumber('INTERNSHIP', Carbon::parse('2026-10-07'), '01', 1, 4, $prefixes));
    }

    // ── placeholder ──────────────────────────────────────────────────────

    public function test_render_escapes_fills_and_keeps_unknown(): void
    {
        $out = R::render('<p>{{ nama }} / {{NAMA}} / {{kosong}} / {{typo_key}} / {{rincian}}</p>', [
            'nama' => 'A <b>&</b> "q"', 'kosong' => '  ', 'rincian' => ['html' => '<i>ok</i>'],
        ]);
        $this->assertSame('<p>A &lt;b&gt;&amp;&lt;/b&gt; &quot;q&quot; / A &lt;b&gt;&amp;&lt;/b&gt; &quot;q&quot; / - / {{typo_key}} / <i>ok</i></p>', $out);
    }

    public function test_generated_document_keeps_its_live_tokens(): void
    {
        $values = ['nama_karyawan' => 'Siti', 'gaji' => 'Rp 5.000.000'];
        $html = '<p>{{nomor_kontrak}} — {{nama_karyawan}} — {{gaji}}</p><p>{{halaman_baru}}</p><p>{{meterai}}</p>';

        // Generating (keepPageBreaks): fields are filled, the page break stays a token the editor shows and the document turns into a break.
        $kept = R::render($html, $values, true);
        $this->assertStringContainsString('{{nomor_kontrak}}', $kept);
        $this->assertStringContainsString('Siti', $kept);
        $this->assertStringContainsString('{{halaman_baru}}', $kept);
        $this->assertStringNotContainsString('page-break-after', $kept);

        // Showing the stored text: the break becomes real again and the live tokens are filled.
        $shown = R::render($kept, $values + ['nomor_kontrak' => 'SPKWT/2026/10/010001', 'meterai' => ['html' => '<b>M</b>']]);
        $this->assertStringContainsString('SPKWT/2026/10/010001', $shown);
        $this->assertStringContainsString('page-break-after', $shown);
        $this->assertStringContainsString('<b>M</b>', $shown);

        foreach (R::LIVE_TOKENS as $token) {
            $this->assertContains($token, ContractPlaceholders::keysFor('PKWT'), $token . ' must be a known placeholder');
        }
    }

    public function test_unknown_placeholders_are_found(): void
    {
        $known = ContractPlaceholders::keysFor('PKWT');
        $this->assertSame([], R::unknownPlaceholders('{{nomor_kontrak}} {{ NIK }}', $known));
        $this->assertSame(['tarif'], R::unknownPlaceholders('{{tarif}} {{tarif}} {{nama_karyawan}}', $known), 'tarif hanya milik External');
        $this->assertContains('tarif', ContractPlaceholders::keysFor('EXTERNAL'));
    }

    public function test_default_templates_only_use_known_placeholders_of_their_type(): void
    {
        foreach (DefaultTemplates::all() as $tpl) {
            $this->assertSame([], R::unknownPlaceholders($tpl['body_html'], ContractPlaceholders::keysFor($tpl['contract_type'])), $tpl['name']);
            $this->assertNotNull(R::normalizeType($tpl['contract_type']));
        }
        $this->assertCount(3, DefaultTemplates::all());
    }

    public function test_default_templates_survive_the_sanitizer_unchanged_in_substance(): void
    {
        // Disimpan ulang dari editor = disaring; teks, placeholder, nomor huruf (a. b. I. II.) dan tabel tak boleh hilang.
        foreach (DefaultTemplates::all() as $tpl) {
            $clean = ContractHtmlSanitizer::clean($tpl['body_html']);
            $plain = fn (string $h) => preg_replace('/\s+/', '', html_entity_decode(strip_tags($h)));

            $this->assertSame($plain($tpl['body_html']), $plain($clean), $tpl['name'] . ': teks berubah setelah disaring');
            $this->assertSame(substr_count($tpl['body_html'], '<table'), substr_count($clean, '<table'), $tpl['name'] . ': tabel');
            $this->assertSame(substr_count($tpl['body_html'], 'list-style-type'), substr_count($clean, 'list-style-type'), $tpl['name'] . ': gaya daftar');
            $this->assertSame([], R::unknownPlaceholders($clean, ContractPlaceholders::keysFor($tpl['contract_type'])), $tpl['name']);
        }
    }

    public function test_default_templates_have_all_articles_of_the_esh_pdfs(): void
    {
        $count = fn (string $type) => preg_match_all('/PASAL \d+/', collect(DefaultTemplates::all())->firstWhere('contract_type', $type)['body_html']);

        $this->assertSame(14, $count('PKWT'));
        $this->assertSame(16, $count('PKWTT'));
        $this->assertSame(9, $count('EXTERNAL'));
    }

    public function test_sanitizer_keeps_everything_the_word_like_editor_produces(): void
    {
        $in = '<h1 style="text-align: center;">Judul</h1>'
            . '<p style="text-align: justify; line-height: 1.5; margin-left: 36pt; margin-right: 14.2pt; text-indent: -18pt; margin-top: 6pt;">Isi '
            . '<span style="font-family: Arial; font-size: 14pt; color: rgb(255, 0, 0); background-color: rgb(255, 255, 0); font-weight: bold;">merah</span> '
            . '<sub>x</sub><sup>2</sup> <s>coret</s></p>'
            . '<ol style="list-style-type: lower-alpha;"><li style="margin-left: 18pt;">a</li></ol>'
            . '<table style="width:100%;border-collapse: collapse"><tbody><tr><td colspan="2" style="border: 0.75pt solid #000000; width: 40%; text-align: right;">c</td></tr></tbody></table>'
            . '<p>{{halaman_baru}}</p>';
        $out = ContractHtmlSanitizer::clean($in);

        foreach (['<h1', 'text-align:center', 'line-height:1.5', 'margin-left:36pt', 'margin-right:14.2pt', 'text-indent:-18pt', 'margin-top:6pt',
            'font-family:Arial', 'font-size:14pt', 'color:rgb(255,0,0)', 'background-color:rgb(255,255,0)', 'font-weight:bold', '<sub>', '<sup>', '<s>',
            'list-style-type:lower-alpha', 'colspan="2"', 'border:0.75pt solid #000000', 'width:40%', 'border-collapse:collapse', '{{halaman_baru}}'] as $needle) {
            $this->assertStringContainsString($needle, str_replace([' ;', '; '], [';', ';'], $out), "hilang: $needle\n$out");
        }
    }

    public function test_sanitizer_still_drops_what_the_editor_must_never_store(): void
    {
        $out = ContractHtmlSanitizer::clean('<p style="position:absolute;behavior:url(x);background:url(javascript:1)" onclick="1">a</p><iframe src="x"></iframe><img src="x"><form><input></form><a href="javascript:1">l</a><style>p{}</style>');
        foreach (['position', 'behavior', 'javascript', 'onclick', '<iframe', '<img', '<form', '<input', '<a ', '<style'] as $bad) {
            $this->assertStringNotContainsString($bad, $out, $bad);
        }
    }

    public function test_page_break_paragraph_becomes_a_real_page_break(): void
    {
        $html = R::render('<p>Satu</p><p style="text-align:center">{{ halaman_baru }}</p><p>Dua {{halaman_baru}}</p>', []);
        $this->assertSame(2, substr_count($html, 'page-break-after:always'));
        $this->assertStringNotContainsString('halaman_baru', $html);
        $this->assertSame([], R::unknownPlaceholders('{{halaman_baru}}', ContractPlaceholders::keysFor('PKWT')));
        $this->assertSame([], R::unknownPlaceholders('{{halaman_baru}}', ContractPlaceholders::keysFor('EXTERNAL')));
    }

    public function test_sanitizer_keeps_formatting_and_placeholders_but_drops_scripts_and_images(): void
    {
        $dirty = '<p style="text-align:center">{{nomor_kontrak}} <strong>x</strong></p><script>alert(1)</script><img src="x" onerror="1"><a href="http://a">l</a><p onclick="1">y</p>';
        $clean = ContractHtmlSanitizer::clean($dirty);

        $this->assertStringContainsString('{{nomor_kontrak}}', $clean);
        $this->assertStringContainsString('text-align:center', $clean);
        $this->assertStringContainsString('<strong>x</strong>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<a ', $clean);
        $this->assertStringContainsString('list-style-type:lower-alpha', ContractHtmlSanitizer::clean('<ol style="list-style-type:lower-alpha"><li>a</li></ol>'), 'a. b. c. harus selamat');
        $this->assertSame('', ContractHtmlSanitizer::clean('  '));
    }

    // ── kesiapan ─────────────────────────────────────────────────────────

    public function test_readiness(): void
    {
        $items = [['key' => 'a', 'label' => 'A'], ['key' => 'b', 'label' => 'B'], ['key' => 'c', 'label' => 'C'], ['key' => 'd', 'label' => 'D']];

        $r = R::readiness($items, ['a' => true, 'b' => false, 'c' => true]);
        $this->assertSame(2, $r['done']);
        $this->assertSame(4, $r['total']);
        $this->assertSame(50, $r['percent']);
        $this->assertFalse($r['ready']);
        $this->assertSame(['B', 'D'], array_column($r['missing'], 'label'));

        $all = R::readiness($items, ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1]);
        $this->assertTrue($all['ready']);
        $this->assertSame(100, $all['percent']);
        $this->assertSame(100, R::readiness([], [])['percent'], 'tanpa syarat = siap');
    }

    public function test_optional_readiness_items_never_block_and_are_not_counted(): void
    {
        $items = [['key' => 'a', 'label' => 'A'], ['key' => 'card', 'label' => 'Card', 'optional' => true]];

        $r = R::readiness($items, ['a' => true]);
        $this->assertTrue($r['ready'], 'butir opsional yang kosong tidak menghalangi');
        $this->assertSame(100, $r['percent']);
        $this->assertSame(1, $r['total'], 'butir opsional tidak dihitung');
        $this->assertSame([], $r['missing']);
        $this->assertCount(2, $r['items'], 'tetapi tetap tampil di kartu');

        $r = R::readiness($items, ['card' => true]);
        $this->assertFalse($r['ready'], 'butir wajib yang kosong tetap menghalangi walau yang opsional terisi');
        $this->assertSame(0, $r['percent']);
    }

    public function test_readiness_config_covers_the_hr_revision(): void
    {
        $cfg = require __DIR__ . '/../../../config/hc_contract.php';
        $byKey = collect($cfg['readiness'])->keyBy('key');
        foreach (['npwp', 'bpjs_kes', 'bpjs_tk', 'doc_ktp', 'doc_kk', 'doc_cv', 'doc_ijazah'] as $key) {
            $this->assertTrue($byKey->has($key), "{$key} wajib ada");
            $this->assertEmpty($byKey[$key]['optional'] ?? null, "{$key} wajib (bukan opsional)");
        }
        $this->assertTrue($byKey['doc_npwp']['optional'] ?? false, 'kartu NPWP opsional');
    }

    public function test_every_readiness_item_has_a_checkable_rule(): void
    {
        $cfg = require __DIR__ . '/../../../config/hc_contract.php';
        $kinds = ['identification', 'address', 'hr', 'bank', 'attachment'];
        $keys = [];
        foreach ($cfg['readiness'] as $item) {
            [$kind] = explode(':', $item['check'], 2);
            $this->assertContains($kind, $kinds, $item['key']);
            $keys[] = $item['key'];
        }
        $this->assertSame($keys, array_unique($keys), 'kunci kesiapan harus unik');
        $this->assertArrayHasKey('PKWT', $cfg['number']['prefixes']);
    }
}
