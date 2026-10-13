<?php

namespace Tests\Unit\External;

use App\Support\Contracts\ContractPlaceholders;
use App\Support\External\ExternalRules as R;
use App\Support\Letters\LetterTemplates;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Aturan murni seksi External: skema, periode, tarif, ketentuan kontrak, jenis surat konsultan. */
class ExternalRulesTest extends TestCase
{
    // ── skema ────────────────────────────────────────────────────────────

    #[DataProvider('schemeCases')]
    public function test_scheme_key(?string $in, ?string $expected): void
    {
        $this->assertSame($expected, R::schemeKey($in));
    }

    public static function schemeCases(): array
    {
        return [
            'kunci' => ['mandays', 'mandays'], 'label ESH/seeder' => ['Mandays', 'mandays'], 'man-days' => ['Man-Days', 'mandays'],
            'monthly' => ['Monthly', 'monthly'], 'manmonth ESH' => ['Manmonth', 'monthly'], 'man month' => ['man month', 'monthly'],
            'fixed price label' => ['Fixed price', 'fixed_price'], 'fixed_price kunci' => ['fixed_price', 'fixed_price'], 'freelance' => ['Freelance', 'fixed_price'],
            'hourly' => ['hourly', 'hourly'], 'kosong' => ['', null], 'null' => [null, null], 'tak dikenal' => ['whatever', null],
        ];
    }

    public function test_scheme_aliases_cover_key_and_labels_for_sql_filter(): void
    {
        $this->assertContains('mandays', R::schemeAliases('mandays'));
        $this->assertContains('manmonth', R::schemeAliases('monthly'));
        $this->assertContains('fixedprice', R::schemeAliases('fixed_price'));
        $this->assertSame([], R::schemeAliases('bogus'));
    }

    public function test_scheme_label_and_rate_unit(): void
    {
        $this->assertSame('Mandays', R::schemeLabel('Mandays'));
        $this->assertSame('Fixed price', R::schemeLabel('freelance'));
        $this->assertSame('Custom text', R::schemeLabel('Custom text'));
        $this->assertSame('—', R::schemeLabel(null));
        $this->assertSame('/ day', R::rateUnit('mandays'));
        $this->assertSame('/ month', R::rateUnit('Monthly'));
        $this->assertSame('', R::rateUnit(null));
        $this->assertSame('mandays', R::defaultBillingUnit(null));
        $this->assertSame('months', R::defaultBillingUnit('monthly'));
        $this->assertSame('package', R::defaultBillingUnit('fixed_price'));
    }

    // ── periode ──────────────────────────────────────────────────────────

    #[DataProvider('periodCases')]
    public function test_period_state(?string $start, ?string $end, string $expected): void
    {
        $this->assertSame($expected, R::periodState($start, $end, '2026-10-12'));
    }

    public static function periodCases(): array
    {
        return [
            'tanpa tanggal' => [null, null, 'none'], 'mendatang' => ['2026-10-13', '2027-01-01', 'upcoming'],
            'berjalan' => ['2026-09-26', '2027-01-31', 'in_period'], 'hari terakhir masih berjalan' => ['2026-01-01', '2026-10-12', 'in_period'],
            'hari mulai' => ['2026-10-12', '2027-01-01', 'in_period'], 'berakhir' => ['2026-01-01', '2026-10-11', 'ended'],
            'tanpa akhir' => ['2026-01-01', null, 'in_period'], 'hanya akhir, lewat' => [null, '2026-01-01', 'ended'],
            'datetime dipotong' => ['2026-09-01 00:00:00', '2026-12-01 00:00:00', 'in_period'],
        ];
    }

    // ── tarif ────────────────────────────────────────────────────────────

    public function test_money(): void
    {
        $this->assertSame('Rp 2.700.000', R::money('2700000.00', 'IDR'));
        $this->assertSame('Rp 2.700.000', R::money(2700000, null));
        $this->assertSame('USD 1.500,50', R::money('1500.50', 'usd'));
        $this->assertSame('—', R::money(null, 'IDR'));
        $this->assertSame('—', R::money('', 'IDR'));
        $this->assertSame('Rp 0', R::money(0, 'IDR'));
    }

    // ── ketentuan kontrak ────────────────────────────────────────────────

    public function test_clean_terms_full(): void
    {
        $t = R::cleanTerms(['engagement_scheme' => 'Mandays', 'rate' => '2.700.000,50', 'currency' => 'usd', 'billing_unit' => ' mandays ',
            'hours_per_manday' => '7,5', 'proration' => 'calendar_days', 'payment_term_days' => '30'], true);

        $this->assertSame('mandays', $t['engagement_scheme']);
        $this->assertSame(2700000.5, $t['rate']);
        $this->assertSame('USD', $t['currency']);
        $this->assertSame('mandays', $t['billing_unit']);
        $this->assertSame(7.5, $t['hours_per_manday']);
        $this->assertSame('calendar_days', $t['proration']);
        $this->assertSame(30, $t['payment_term_days']);
    }

    public function test_clean_terms_defaults_and_rate_is_left_out_without_permission(): void
    {
        $t = R::cleanTerms(['engagement_scheme' => 'monthly', 'rate' => '999', 'currency' => 'USD'], false);

        $this->assertArrayNotHasKey('rate', $t);
        $this->assertArrayNotHasKey('currency', $t);
        $this->assertSame('months', $t['billing_unit']);
        $this->assertSame(8.0, $t['hours_per_manday']);
        $this->assertSame('working_days', $t['proration']);
        $this->assertSame(14, $t['payment_term_days']);
    }

    public function test_clean_terms_empty_rate_becomes_null_with_default_currency(): void
    {
        $t = R::cleanTerms(['engagement_scheme' => 'hourly', 'rate' => '', 'currency' => ''], true);

        $this->assertNull($t['rate']);
        $this->assertSame('IDR', $t['currency']);
    }

    #[DataProvider('badTerms')]
    public function test_clean_terms_rejects(array $input, string $message): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($message);
        R::cleanTerms($input + ['engagement_scheme' => 'mandays'], true);
    }

    public static function badTerms(): array
    {
        return [
            'skema kosong' => [['engagement_scheme' => ''], 'Choose the contract type'],
            'skema tak dikenal' => [['engagement_scheme' => 'bogus'], 'Choose the contract type'],
            'jam 0' => [['hours_per_manday' => '0'], 'between 1 and 24'],
            'jam 25' => [['hours_per_manday' => '25'], 'between 1 and 24'],
            'prorata asing' => [['proration' => 'weekly'], 'prorated'],
            'termin negatif' => [['payment_term_days' => '-1'], 'between 0 and 365'],
            'termin 366' => [['payment_term_days' => '366'], 'between 0 and 365'],
            'mata uang asing' => [['currency' => 'XXX'], 'Currency must be one of'],
            'tarif negatif' => [['rate' => '-5'], 'valid rate'],
        ];
    }

    // ── surat konsultan ──────────────────────────────────────────────────

    public function test_documents_map_to_registered_system_letter_templates(): void
    {
        $this->assertCount(3, R::DOCUMENTS);
        foreach (R::DOCUMENTS as $key => $doc) {
            $this->assertTrue(LetterTemplates::exists($doc['template']), $doc['template'] . ' terdaftar di LetterTemplates');
            $this->assertTrue(LetterTemplates::get($doc['template'])['system'] ?? false, $doc['template'] . ' disembunyikan dari Create Letter');
            $this->assertTrue(LetterTemplates::needsEmployee($doc['template']));
            $this->assertSame($key, R::documentKeyOf($doc['template']));
            $this->assertFileExists(resource_path('views/hr-general/letters/pdf/' . $doc['template'] . '.blade.php'));
            $this->assertSame('general.external.' . $doc['path'], $doc['slug']);
        }
        $this->assertNull(R::documentKeyOf('assignment_letter'));
        $this->assertSame(['ext_assignment_letter', 'ext_employment_certificate', 'ext_reference_letter'], R::templateKeys());
    }

    public function test_assignment_letter_period_rule_matches_internal_one(): void
    {
        $this->assertContains('after_or_equal:fields.period_from', LetterTemplates::rules('ext_assignment_letter')['fields.period_to']);
        $this->assertNotNull(R::letterPeriodError(null, null, true));
        $this->assertNotNull(R::letterPeriodError('2026-10-02', '2026-10-01', false));
        $this->assertNull(R::letterPeriodError('2026-10-01', '2026-10-01', true));
        $this->assertNull(R::letterPeriodError('', '', false));
        $this->assertNull(R::letterPeriodError('2026-10-01', '', false));
    }

    public function test_external_contract_placeholders_include_the_new_commercial_terms(): void
    {
        $keys = ContractPlaceholders::keysFor('EXTERNAL');
        $this->assertContains('jam_per_manday', $keys);
        $this->assertContains('termin_pembayaran', $keys);
        $this->assertNotContains('jam_per_manday', ContractPlaceholders::keysFor('PKWT'));
    }
}
