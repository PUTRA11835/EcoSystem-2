<?php

namespace App\Support\CashAdvance;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * One column of the printed Cash Advance / Report: Accounting, Cashier or Approved by.
 *
 * The name comes from the workflow, not from a setting: the person who actually acted on the step whose Actor is that column
 * (the last one, in a multi-step flow). Before anyone acted, a step naming exactly ONE person prints that name greyed out so the reader
 * knows who is awaited; with several candidates the column stays empty — guessing one of them would print the wrong person.
 * Skipped steps (a cancelled or rejected document) are ignored.
 *
 * Documents from before Accounting / Cashier became workflow actors have no such step; they keep the signer that was set in the
 * old settings, so an old printout does not lose its name.
 */
class SignatureColumn
{
    /**
     * @param  Collection  $approvals   the document's approvals (CashAdvanceApproval / CashAdvanceReportApproval)
     * @param  callable    $nameOf      fn (?Employee): string
     * @return array{title: string, name: string, pending: bool}
     */
    public static function build(Collection $approvals, string $actor, string $title, string $approvedStatus, string $skippedStatus, callable $nameOf, ?Employee $legacy = null): array
    {
        $steps = $approvals->where('actor_role', $actor)->where('status', '!=', $skippedStatus);

        if ($steps->isEmpty()) {
            $name = $legacy ? $nameOf($legacy) : '';

            return ['title' => $title, 'name' => $name, 'pending' => $name === ''];
        }

        $acted = $steps->where('status', $approvedStatus)->sortByDesc('order_seq')->first();
        if ($acted) {
            return ['title' => $title, 'name' => $nameOf($acted->actor), 'pending' => false];
        }

        $ids = array_map('intval', (array) ($steps->sortBy('order_seq')->first()->approver_employee_ids ?? []));
        $name = count($ids) === 1 ? $nameOf(Employee::with('basicData')->find($ids[0])) : '';

        return ['title' => $title, 'name' => $name, 'pending' => true];
    }
}
