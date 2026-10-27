<?php

namespace Tests\Unit\CashAdvance;

use App\Models\CashAdvance\CashAdvanceApproval;
use App\Models\CashAdvance\CashAdvanceApprovalStep;
use App\Models\Employee;
use App\Support\CashAdvance\SignatureColumn;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** Kolom tanda tangan cetakan CA/CAR: nama diambil dari alur persetujuan, bukan dari setelan. */
class SignatureColumnTest extends TestCase
{
    private function approval(string $actor, string $status, int $seq, ?string $who = null, array $ids = []): object
    {
        return (object) ['actor_role' => $actor, 'status' => $status, 'order_seq' => $seq, 'approver_employee_ids' => $ids,
            'actor' => $who === null ? null : (object) ['name' => $who]];
    }

    private function column(Collection $approvals, string $actor, ?Employee $legacy = null): array
    {
        return SignatureColumn::build($approvals, $actor, 'Accounting', CashAdvanceApproval::STATUS_APPROVED, CashAdvanceApproval::STATUS_SKIPPED,
            fn ($e) => $e?->name ?? '—', $legacy);
    }

    public function test_the_person_who_acted_is_printed(): void
    {
        $approvals = collect([$this->approval(CashAdvanceApprovalStep::ACTOR_ACCOUNTING, CashAdvanceApproval::STATUS_APPROVED, 1, 'Dewi')]);

        $this->assertSame(['title' => 'Accounting', 'name' => 'Dewi', 'pending' => false], $this->column($approvals, CashAdvanceApprovalStep::ACTOR_ACCOUNTING));
    }

    public function test_the_last_one_to_act_wins(): void
    {
        $approvals = collect([
            $this->approval(CashAdvanceApprovalStep::ACTOR_APPROVER, CashAdvanceApproval::STATUS_APPROVED, 2, 'First'),
            $this->approval(CashAdvanceApprovalStep::ACTOR_APPROVER, CashAdvanceApproval::STATUS_APPROVED, 3, 'Last'),
        ]);

        $this->assertSame('Last', $this->column($approvals, CashAdvanceApprovalStep::ACTOR_APPROVER)['name']);
    }

    public function test_a_step_with_several_candidates_stays_empty_until_someone_acts(): void
    {
        $approvals = collect([$this->approval(CashAdvanceApprovalStep::ACTOR_CASHIER, 'pending', 2, null, [5, 6])]);

        $this->assertSame(['title' => 'Accounting', 'name' => '', 'pending' => true], $this->column($approvals, CashAdvanceApprovalStep::ACTOR_CASHIER));
    }

    public function test_skipped_steps_are_ignored(): void
    {
        $approvals = collect([$this->approval(CashAdvanceApprovalStep::ACTOR_CASHIER, CashAdvanceApproval::STATUS_SKIPPED, 2, 'Nobody')]);

        $this->assertSame('', $this->column($approvals, CashAdvanceApprovalStep::ACTOR_CASHIER)['name']);
    }

    public function test_an_old_document_without_the_step_keeps_the_signer_of_the_old_settings(): void
    {
        $legacy = new Employee();
        $legacy->name = 'Old Signer';

        $column = $this->column(collect(), CashAdvanceApprovalStep::ACTOR_ACCOUNTING, $legacy);

        $this->assertSame(['title' => 'Accounting', 'name' => 'Old Signer', 'pending' => false], $column);
    }

    public function test_accounting_and_cashier_are_workflow_actors_that_do_not_finish_the_document(): void
    {
        $this->assertContains('accounting', CashAdvanceApprovalStep::ACTOR_ROLES);
        $this->assertContains('cashier', CashAdvanceApprovalStep::ACTOR_ROLES);
        $this->assertSame('Cashier', CashAdvanceApprovalStep::ACTOR_LABELS['cashier']);
    }
}
