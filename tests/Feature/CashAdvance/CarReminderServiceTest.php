<?php

namespace Tests\Feature\CashAdvance;

use App\Services\CashAdvance\CarReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The daily CAR reminder: who is reminded, on which day, only for advances the requester still has to report, and never twice in a day.
 * Reference setting: 10-day deadline, first reminder 3 days before. An advance approved on 1 Oct is due 11 Oct.
 */
class CarReminderServiceTest extends TestCase
{
    private const REQUESTER = 12;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not loaded (run: php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit).');
        }
        parent::setUp();

        Schema::create('cash_advance_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('car_due_days')->default(0);
            $t->boolean('car_reminders_enabled')->default(true);
            $t->unsignedTinyInteger('car_reminder_days_before')->default(3);
        });
        Schema::create('cash_advances', function (Blueprint $t) {
            $t->id();
            $t->string('request_no');
            $t->unsignedBigInteger('employee_id');
            $t->string('status');
            $t->string('settlement_status')->default('unreported');
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('type');
            $t->unsignedBigInteger('ticket_id')->nullable();
            $t->unsignedBigInteger('from_employee_id')->nullable();
            $t->string('from_name')->nullable();
            $t->text('preview')->nullable();
            $t->string('link')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamps();
        });

        $this->settings(10, true, 3);
    }

    private function settings(int $days, bool $enabled, int $before): void
    {
        \App\Models\CashAdvance\CashAdvanceSetting::forgetCache();   // the model keeps the row for the request; each case changes it
        DB::table('cash_advance_settings')->delete();
        DB::table('cash_advance_settings')->insert(['id' => 1, 'car_due_days' => $days, 'car_reminders_enabled' => $enabled, 'car_reminder_days_before' => $before]);
    }

    private function advance(array $extra = []): int
    {
        return DB::table('cash_advances')->insertGetId($extra + [
            'request_no' => 'CA/2026/10/00001', 'employee_id' => self::REQUESTER, 'status' => 'approved', 'settlement_status' => 'unreported',
            'completed_at' => '2026-10-01 14:30:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function remind(string $today, bool $dry = false): array
    {
        return app(CarReminderService::class)->run(CarbonImmutable::parse($today), $dry);
    }

    public function test_the_requester_is_reminded_three_days_before_on_the_day_and_when_late(): void
    {
        $this->advance();

        foreach (['2026-10-08' => 'upcoming', '2026-10-11' => 'due_today', '2026-10-12' => 'overdue'] as $day => $stage) {
            $result = $this->remind($day);
            $this->assertSame(1, $result['sent'], "$day should send one reminder");
            $this->assertSame($stage, $result['items'][0]['stage']);
        }

        $notes = DB::table('notifications')->where('type', 'car_reminder')->orderBy('id')->get();
        $this->assertCount(3, $notes);
        $this->assertSame(self::REQUESTER, (int) $notes[0]->employee_id);
        $this->assertStringContainsString('CA/2026/10/00001', $notes[0]->preview);
        $this->assertStringContainsString('overdue', $notes[2]->preview);
        $this->assertSame('/general/my-cash-advance', $notes[0]->link);
    }

    public function test_no_reminder_on_the_days_in_between(): void
    {
        $this->advance();

        foreach (['2026-10-03', '2026-10-09', '2026-10-10', '2026-10-13', '2026-10-17'] as $day) {
            $this->assertSame(0, $this->remind($day)['sent'], "$day is not a reminder day");
        }
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_running_twice_on_the_same_day_sends_one_reminder(): void
    {
        $this->advance();

        $first = $this->remind('2026-10-12');
        $second = $this->remind('2026-10-12');

        $this->assertSame(1, $first['sent']);
        $this->assertSame(0, $second['sent'], 'already reminded today');
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_only_an_advance_the_requester_still_has_to_report_is_reminded(): void
    {
        $this->advance(['request_no' => 'CA-A', 'settlement_status' => 'reporting']);   // a report is already with the reviewers
        $this->advance(['request_no' => 'CA-B', 'settlement_status' => 'settled']);
        $this->advance(['request_no' => 'CA-C', 'status' => 'submitted', 'completed_at' => null]);
        $this->advance(['request_no' => 'CA-D', 'status' => 'rejected']);
        $deleted = $this->advance(['request_no' => 'CA-E']);
        DB::table('cash_advances')->where('id', $deleted)->update(['deleted_at' => now()]);

        $result = $this->remind('2026-10-12');

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['checked'], 'none of them is a candidate');
    }

    public function test_nothing_is_sent_when_reminders_are_off_or_there_is_no_deadline(): void
    {
        $this->advance();

        $this->settings(10, false, 3);
        $this->assertSame(0, $this->remind('2026-10-12')['sent'], 'switched off');

        $this->settings(0, true, 3);
        $this->assertSame(0, $this->remind('2026-10-12')['sent'], 'deadline 0 = none');
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_the_first_reminder_day_follows_the_setting(): void
    {
        $this->advance();
        $this->settings(10, true, 5);

        $this->assertSame(0, $this->remind('2026-10-08')['sent'], '3 days left, but the early reminder is at 5');
        $this->assertSame(1, $this->remind('2026-10-06')['sent'], '5 days left');
    }

    public function test_a_dry_run_lists_the_reminders_but_sends_nothing(): void
    {
        $this->advance();

        $result = $this->remind('2026-10-12', true);

        $this->assertSame(1, $result['sent']);
        $this->assertCount(1, $result['items']);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_a_weekly_reminder_continues_while_overdue(): void
    {
        $this->advance();

        $this->assertSame(0, $this->remind('2026-10-15')['sent']);
        $this->assertSame(1, $this->remind('2026-10-18')['sent'], '7 days late');
        $this->assertSame(1, $this->remind('2026-10-25')['sent'], '14 days late');
    }
}
