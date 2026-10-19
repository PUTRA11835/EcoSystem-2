<?php

namespace Tests\Unit\External;

use App\Support\External\ExternalBilling as B;
use App\Support\External\ExternalTimesheetRules as R;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Hitung penagihan dan aturan lembar jam/invoice konsultan eksternal. */
class ExternalTimesheetRulesTest extends TestCase
{
    // ── jam ──────────────────────────────────────────────────────────────

    #[DataProvider('hourCases')]
    public function test_hours_between(?string $a, ?string $b, ?float $expected): void
    {
        $this->assertSame($expected, B::hoursBetween($a, $b));
    }

    public static function hourCases(): array
    {
        return [
            'sembilan jam' => ['08:00', '17:00', 9.0], 'setengah jam' => ['09:00', '09:30', 0.5], 'detik diabaikan' => ['08:00:00', '16:00:00', 8.0],
            'seperempat' => ['08:00', '08:45', 0.75], 'selesai sebelum mulai' => ['17:00', '08:00', null], 'sama' => ['08:00', '08:00', null],
            'kosong' => ['', '09:00', null], 'null' => [null, null, null], 'bukan jam' => ['25:00', '26:00', null],
        ];
    }

    // ── contoh ESH ───────────────────────────────────────────────────────

    public function test_mandays_example_from_esh_32_hours_is_4_mandays(): void
    {
        $r = B::compute(['scheme' => 'mandays', 'rate' => 2700000.0, 'hours_per_manday' => 8.0], 32.0);
        $this->assertSame(4.0, $r['qty']);
        $this->assertSame(10800000.0, $r['gross']);
    }

    public function test_mandays_partial_day_and_rounding(): void
    {
        $r = B::compute(['scheme' => 'Mandays', 'rate' => 2400000.0, 'hours_per_manday' => 8.0], 13.0);
        $this->assertSame(1.63, $r['qty']);                       // 13 ÷ 8 = 1,625
        $this->assertSame(3900000.0, $r['gross']);                // bruto dari kuantitas tak dibulatkan
    }

    public function test_hourly_monthly_and_fixed_price(): void
    {
        $this->assertSame(['qty' => 10.5, 'gross' => 1050000.0], B::compute(['scheme' => 'hourly', 'rate' => 100000.0, 'hours_per_manday' => 8], 10.5));
        $this->assertSame(['qty' => 1.0, 'gross' => 27000000.0], B::compute(['scheme' => 'monthly', 'rate' => 27000000.0, 'hours_per_manday' => 8], 0, 1.0));
        $this->assertSame(['qty' => 0.5, 'gross' => 13500000.0], B::compute(['scheme' => 'monthly', 'rate' => 27000000.0, 'hours_per_manday' => 8], 0, 0.5));
        $this->assertSame(['qty' => 0.3, 'gross' => 15000000.0], B::compute(['scheme' => 'fixed_price', 'rate' => 50000000.0, 'hours_per_manday' => 8, 'milestone_pct' => 30], 0));
    }

    public function test_gross_is_null_without_rate_and_zero_hpm_falls_back_to_8(): void
    {
        $this->assertNull(B::compute(['scheme' => 'mandays', 'rate' => null, 'hours_per_manday' => 8], 8)['gross']);
        $this->assertSame(1.0, B::compute(['scheme' => 'mandays', 'rate' => 1.0, 'hours_per_manday' => 0], 8)['qty']);
    }

    // ── prorata bulan ────────────────────────────────────────────────────

    public function test_month_factor_full_month_is_one_for_every_rule(): void
    {
        $workdays = $this->workdays('2026-10-01', '2026-10-31');
        foreach (['working_days', 'calendar_days', 'none'] as $rule) {
            $this->assertSame(1.0, B::monthFactor($rule, '2026-01-01', null, 2026, 10, $workdays), $rule);
        }
    }

    public function test_month_factor_partial_month(): void
    {
        $workdays = $this->workdays('2026-10-01', '2026-10-31');   // 22 hari kerja (Okt 2026)
        // Kontrak mulai 16 Okt: 16 hari kalender dari 31; hari kerja 16–30 Okt = 11 dari 22.
        $this->assertSame(round(16 / 31, 4), B::monthFactor('calendar_days', '2026-10-16', null, 2026, 10));
        $this->assertSame(0.5, B::monthFactor('working_days', '2026-10-16', null, 2026, 10, $workdays));
        $this->assertSame(1.0, B::monthFactor('none', '2026-10-16', null, 2026, 10));
    }

    public function test_month_factor_contract_ending_inside_and_outside_month(): void
    {
        $this->assertSame(round(10 / 31, 4), B::monthFactor('calendar_days', '2026-01-01', '2026-10-10', 2026, 10));
        $this->assertSame(0.0, B::monthFactor('calendar_days', '2026-11-01', null, 2026, 10));
        $this->assertSame(0.0, B::monthFactor('none', '2026-01-01', '2026-09-30', 2026, 10));
        $this->assertSame(0.0, B::monthFactor('working_days', '2026-10-16', null, 2026, 10, []));
    }

    /** @return string[] */
    private function workdays(string $from, string $to): array
    {
        return \App\Support\Payroll\PayrollCalendar::workdays($from, $to, 5, []);
    }

    // ── status ───────────────────────────────────────────────────────────

    public function test_sheet_transitions(): void
    {
        $this->assertTrue(R::canTransition('draft', 'submitted'));
        $this->assertTrue(R::canTransition('returned', 'submitted'));
        $this->assertTrue(R::canTransition('submitted', 'approved'));
        $this->assertTrue(R::canTransition('submitted', 'returned'));
        $this->assertFalse(R::canTransition('draft', 'approved'));
        $this->assertFalse(R::canTransition('approved', 'returned'));
        $this->assertFalse(R::canTransition('approved', 'submitted'));
        $this->assertFalse(R::canTransition('bogus', 'draft'));
        $this->assertTrue(R::isEditable('draft'));
        $this->assertTrue(R::isEditable('returned'));
        $this->assertFalse(R::isEditable('submitted'));
        $this->assertFalse(R::isEditable('approved'));
    }

    public function test_invoice_transitions(): void
    {
        $this->assertTrue(R::canInvoiceTransition('uploaded', 'verified'));
        $this->assertTrue(R::canInvoiceTransition('uploaded', 'rejected'));
        $this->assertTrue(R::canInvoiceTransition('rejected', 'uploaded'));
        $this->assertTrue(R::canInvoiceTransition('verified', 'paid'));
        $this->assertFalse(R::canInvoiceTransition('uploaded', 'paid'));
        $this->assertFalse(R::canInvoiceTransition('paid', 'verified'));
        $this->assertFalse(R::canInvoiceTransition('verified', 'rejected'));
    }

    // ── nomor, tempo, kode ───────────────────────────────────────────────

    public function test_number_due_date_and_code(): void
    {
        $this->assertSame('EXT-TS/2026/10/0007', R::sheetNumber(2026, 10, 7));
        $this->assertSame('2026-10-24', R::dueDate('2026-10-10 09:00:00', 14));
        $this->assertSame('2026-10-10', R::dueDate('2026-10-10', 0));
        $this->assertSame(R::verificationCode(1, 'EXT-TS/2026/10/0001', 'k'), R::verificationCode(1, 'EXT-TS/2026/10/0001', 'k'));
        $this->assertNotSame(R::verificationCode(1, 'EXT-TS/2026/10/0001', 'k'), R::verificationCode(1, 'EXT-TS/2026/10/0001', 'x'));
        $this->assertMatchesRegularExpression('/^EXT-TS-1-[0-9a-f]{12}$/', R::verificationCode(1, 'A', 'k'));
        $this->assertSame(['2026-02-01', '2026-02-28'], R::periodBounds(2026, 2));
    }

    // ── baris ────────────────────────────────────────────────────────────

    private function entries(array $rows, string $today = '2026-10-20', ?string $cStart = '2026-09-01', ?string $cEnd = '2027-01-31', ?string $earliest = null): array
    {
        return R::normalizeEntries($rows, '2026-10-01', '2026-10-31', $cStart, $cEnd, $today, $earliest);
    }

    public function test_entries_hours_from_times_and_from_input_and_sorting(): void
    {
        [$clean, $err] = $this->entries([
            ['work_date' => '2026-10-02', 'start_time' => '08:00', 'end_time' => '17:00', 'activity' => 'Workshop', 'location' => 'onsite'],
            ['work_date' => '2026-10-01', 'hours' => '7,5', 'activity' => 'Config', 'project_label' => 'OTG'],
            ['work_date' => '', 'activity' => ''],                   // baris kosong dibuang
        ]);
        $this->assertSame([], $err);
        $this->assertCount(2, $clean);
        $this->assertSame('2026-10-01', $clean[0]['work_date']);
        $this->assertSame(7.5, $clean[0]['hours']);
        $this->assertSame(9.0, $clean[1]['hours']);
        $this->assertSame('ONSITE', $clean[1]['location']);
    }

    #[DataProvider('badEntries')]
    public function test_entries_rejects(array $row, string $needle, array $opts = []): void
    {
        [$clean, $err] = $this->entries([$row + ['activity' => 'x']], ...$opts);
        $this->assertSame([], $clean);
        $this->assertStringContainsString($needle, implode(' ', $err));
    }

    public static function badEntries(): array
    {
        return [
            'tanggal rusak' => [['work_date' => '2026-13-40', 'hours' => '8'], 'valid date'],
            'di luar bulan' => [['work_date' => '2026-09-30', 'hours' => '8'], 'inside the timesheet month'],
            'sebelum kontrak' => [['work_date' => '2026-10-05', 'hours' => '8'], 'outside the contract period', ['2026-10-20', '2026-10-10', '2027-01-31']],
            'sesudah kontrak' => [['work_date' => '2026-10-15', 'hours' => '8'], 'outside the contract period', ['2026-10-20', '2026-09-01', '2026-10-10']],
            'tanggal depan' => [['work_date' => '2026-10-25', 'hours' => '8'], 'future date'],
            'terlalu lama mundur' => [['work_date' => '2026-10-02', 'hours' => '8'], 'back-fill', ['2026-10-20', '2026-09-01', null, '2026-10-10']],
            'selesai sebelum mulai' => [['work_date' => '2026-10-02', 'start_time' => '17:00', 'end_time' => '08:00'], 'end time must be after'],
            'jam tak ada' => [['work_date' => '2026-10-02'], 'start and end time'],
            'jam 25' => [['work_date' => '2026-10-02', 'hours' => '25'], 'between 0 and 24'],
            'lokasi asing' => [['work_date' => '2026-10-02', 'hours' => '8', 'location' => 'MARS'], 'Onsite, Remote or Hybrid'],
        ];
    }

    public function test_entries_require_activity_and_cap_a_day_at_24_hours(): void
    {
        [, $err] = $this->entries([['work_date' => '2026-10-02', 'hours' => '8', 'activity' => '']]);
        $this->assertStringContainsString('describe the activity', implode(' ', $err));

        [$clean, $err] = $this->entries([
            ['work_date' => '2026-10-02', 'hours' => '16', 'activity' => 'a'],
            ['work_date' => '2026-10-02', 'hours' => '10', 'activity' => 'b'],
        ]);
        $this->assertCount(1, $clean);
        $this->assertStringContainsString('more than 24 hours', implode(' ', $err));
    }
}
