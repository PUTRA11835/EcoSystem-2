<?php

namespace Tests\Unit\CashAdvance;

use App\Support\CashAdvance\CarDueRules;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * When a Cash Advance Report (CAR) is due and overdue, and which day gets which reminder. Pure — no database.
 * Reference: approved on 1 Oct, 10-day deadline → due on 11 Oct (the due day itself is still on time).
 */
class CarDueRulesTest extends TestCase
{
    private function due(string $today, int $limit = 10, int $soon = 3, string $approved = '2026-10-01 14:30:00'): array
    {
        return CarDueRules::status(CarbonImmutable::parse($approved), $limit, CarbonImmutable::parse($today), $soon);
    }

    public function test_no_deadline_or_not_approved_means_no_state(): void
    {
        $this->assertSame('none', $this->due('2026-10-20', 0)['state'], 'deadline 0 = none');
        $this->assertSame('none', CarDueRules::status(null, 10, CarbonImmutable::parse('2026-10-20'))['state'], 'not approved yet');
    }

    public function test_the_due_date_is_the_approval_day_plus_the_deadline_ignoring_the_time_of_day(): void
    {
        $s = $this->due('2026-10-02');

        $this->assertSame('2026-10-11', $s['due_on']->toDateString());
        $this->assertSame(9, $s['days_left']);
    }

    public function test_on_track_then_due_soon_then_due_today_then_overdue(): void
    {
        $this->assertSame('on_track', $this->due('2026-10-07')['state'], '4 days left, window is 3');
        $this->assertSame('due_soon', $this->due('2026-10-08')['state'], '3 days left');
        $this->assertSame('due_soon', $this->due('2026-10-11')['state'], 'the due day itself is still on time');
        $this->assertSame(0, $this->due('2026-10-11')['days_left']);
        $this->assertSame('overdue', $this->due('2026-10-12')['state'], 'the day after is late');
        $this->assertSame(1, $this->due('2026-10-12')['days_late']);
        $this->assertSame(9, $this->due('2026-10-20')['days_late']);
    }

    public function test_the_reminder_window_follows_the_setting(): void
    {
        $this->assertSame('due_soon', $this->due('2026-10-06', 10, 5)['state'], '5 days left with a 5-day window');
        $this->assertSame('on_track', $this->due('2026-10-06', 10, 2)['state']);
    }

    public function test_the_reminders_go_out_on_the_right_days_only(): void
    {
        $stage = fn (string $today, int $soon = 3) => CarDueRules::reminderStage($this->due($today, 10, $soon), $soon);

        $this->assertNull($stage('2026-10-05'), 'far ahead');
        $this->assertSame('upcoming', $stage('2026-10-08'), 'exactly 3 days before');
        $this->assertNull($stage('2026-10-09'), '2 days left — already reminded on the 3rd day');
        $this->assertSame('due_today', $stage('2026-10-11'));
        $this->assertSame('overdue', $stage('2026-10-12'), 'the first day late');
        $this->assertNull($stage('2026-10-13'), 'not every day');
        $this->assertNull($stage('2026-10-17'));
        $this->assertSame('overdue', $stage('2026-10-18'), '7 days late');
        $this->assertSame('overdue', $stage('2026-10-25'), '14 days late');
    }

    public function test_a_zero_day_early_window_skips_the_upcoming_reminder(): void
    {
        $this->assertNull(CarDueRules::reminderStage($this->due('2026-10-10', 10, 0), 0), '1 day left, early reminder switched off');
        $this->assertSame('due_today', CarDueRules::reminderStage($this->due('2026-10-11', 10, 0), 0), 'the due-day reminder remains');
    }

    public function test_nothing_is_sent_without_a_deadline(): void
    {
        $this->assertNull(CarDueRules::reminderStage($this->due('2026-10-11', 0)));
    }
}
