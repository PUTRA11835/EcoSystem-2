<?php

namespace Tests\Unit\Contracts;

use App\Support\Contracts\SigningRules as S;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Pure rules of the contract signing workflow: stages, allowed moves, wording for the employee, deadlines. */
class SigningRulesTest extends TestCase
{
    public function test_the_happy_path_visits_every_stage_in_order(): void
    {
        $stage = null;
        foreach ([
            [S::ACTION_SEND, S::WITH_EMPLOYEE],
            [S::ACTION_EMPLOYEE_SIGN, S::WITH_LEGAL],
            [S::ACTION_FORWARD, S::WITH_APPROVER],
            [S::ACTION_APPROVER_SIGN, S::APPROVED],
            [S::ACTION_RELEASE, S::RELEASED],
        ] as [$action, $expected]) {
            $this->assertTrue(S::can($stage, $action), "{$action} from " . var_export($stage, true));
            $stage = S::apply($stage, $action);
            $this->assertSame($expected, $stage);
        }
    }

    #[DataProvider('refused')]
    public function test_moves_that_are_not_allowed(?string $stage, string $action): void
    {
        $this->assertFalse(S::can($stage, $action));
        $this->assertSame($stage, S::apply($stage, $action), 'a refused move leaves the stage as it is');
    }

    public static function refused(): array
    {
        return [
            'sign before it is sent'        => [null, S::ACTION_EMPLOYEE_SIGN],
            'forward before the employee'   => [S::WITH_EMPLOYEE, S::ACTION_FORWARD],
            'approver before it is forwarded' => [S::WITH_LEGAL, S::ACTION_APPROVER_SIGN],
            'release before approval'       => [S::WITH_APPROVER, S::ACTION_RELEASE],
            'anything after release'        => [S::RELEASED, S::ACTION_SEND],
            'employee signs twice'          => [S::WITH_LEGAL, S::ACTION_EMPLOYEE_SIGN],
        ];
    }

    public function test_revision_returns_to_legal_and_can_be_sent_again(): void
    {
        $this->assertSame(S::REVISION, S::apply(S::WITH_EMPLOYEE, S::ACTION_REVISION));
        $this->assertSame(S::REVISION, S::apply(S::WITH_APPROVER, S::ACTION_REVISION));
        $this->assertSame(S::WITH_EMPLOYEE, S::apply(S::REVISION, S::ACTION_SEND));
    }

    public function test_cancel_puts_the_contract_back_outside_the_process(): void
    {
        foreach ([S::WITH_EMPLOYEE, S::WITH_LEGAL, S::WITH_APPROVER, S::APPROVED, S::REVISION] as $stage) {
            $this->assertTrue(S::can($stage, S::ACTION_CANCEL), $stage);
            $this->assertNull(S::apply($stage, S::ACTION_CANCEL), $stage);
        }
        $this->assertFalse(S::can(S::RELEASED, S::ACTION_CANCEL), 'a released contract is final');
    }

    public function test_what_the_employee_reads(): void
    {
        $this->assertSame('Awaiting your signature', S::employeeView(S::WITH_EMPLOYEE, '2026-10-30', '2026-10-20')['label']);
        $this->assertSame('sign', S::employeeView(S::WITH_EMPLOYEE, '2026-10-30', '2026-10-20')['action']);
        $this->assertSame('Awaiting your signature — overdue', S::employeeView(S::WITH_EMPLOYEE, '2026-10-01', '2026-10-20')['label']);

        foreach ([S::WITH_LEGAL, S::WITH_APPROVER, S::APPROVED] as $stage) {
            $this->assertSame('Signed by you', S::employeeView($stage)['label'], $stage);
            $this->assertNotSame('download', S::employeeView($stage)['action'], 'no download before Legal releases it');
        }
        $this->assertSame('download', S::employeeView(S::RELEASED)['action']);
        $this->assertNull(S::employeeView(S::REVISION)['action']);
        $this->assertSame('', S::employeeView(null)['label']);
    }

    public function test_deadline(): void
    {
        $this->assertSame(10, S::daysLeft('2026-10-30', '2026-10-20'));
        $this->assertSame(0, S::daysLeft('2026-10-20', '2026-10-20'));
        $this->assertSame(-3, S::daysLeft('2026-10-17', '2026-10-20'));
        $this->assertNull(S::daysLeft(null));

        $this->assertNull(S::deadlineError(null, '2026-10-20'));
        $this->assertNull(S::deadlineError('', '2026-10-20'));
        $this->assertNull(S::deadlineError('2026-10-20', '2026-10-20'));
        $this->assertStringContainsString('past', (string) S::deadlineError('2026-10-19', '2026-10-20'));
    }
}
