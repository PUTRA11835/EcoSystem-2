---
key: cash-advance-management
chapter: finance
order: 60
title: Cash Advance Management
summary: Approve cash advances (CA) and their settlement reports (CAR), follow overdue reports and set the rules.
requires: [general.cash-advance, general.cash-advance-report, general.cash-advance-dashboard, management.cash-advance-settings, management.approval-workflow.cash-advance, management.approval-workflow.cash-advance-report]
updated: 2026-10-27
---
A Cash Advance (CA) is money given to an employee before spending. After spending, the employee files a Cash Advance Report (CAR) to settle it. Cash Advance Management is where approvers and Finance process both documents. It has four tabs: `Dashboard`, `Cash Advance (CA)`, `Cash Advance Report (CAR)` and `Settings`.

## Who uses this

Approvers, Finance and administrators. You only see the tabs your role allows, and each button needs matching access.

## Before you start

- Employees request their own advances in `My Workspace` › `Cash Advance` and report them in `Cash Advance Report` (see [[my-cash-advance]]).
- The approval steps for CA and CAR are separate. See [[approval-workflows]].

## Step by step

### Read the Dashboard

The `Dashboard` shows where CA and CAR stand and what needs you: `CA waiting for you`, `CAR waiting for you`, `Oldest outstanding`, a `Last 6 Months` view, and the money to settle: `To collect (refund)` when the employee spent less than received, and `To pay (claim)` when the employee spent more.

### Approve or reject a CA

1. Open `Cash Advance (CA)`.
2. Open the document and check the amount, purpose, cost center and approval timeline.
3. If it is your turn, click `Approve`, or click `Reject…` and give a reason.
4. Use `New CA` to create an advance on behalf of an employee, if you have access.

A CA moves through `Submitted`, `In review`, `Approved`, `Rejected` or `Cancelled`. Separately, an approved CA has a settlement status: `Not reported`, `Reporting` or `Settled`.

### Process a CAR

1. Open `Cash Advance Report (CAR)`.
2. If a box shows cash advances waiting to be reported, use `+ New CAR` next to the right advance to start a report for it.
3. Open a report and click `Approve`, or `Reject…` with a reason.
4. Approving the final step settles the cash advance it belongs to and closes it.

### Follow late reports

Each approved CA has a reporting deadline. A report filed after the due day is flagged `Late report`, and the advance shows as `Overdue` in the lists. A daily reminder is sent to the requester before and after the deadline.

### Set the rules (Settings)

1. Open the `Settings` tab.
2. Under `Cash Advance Rules`, review limits such as the maximum backdating, the minimum and maximum amount and what happens over the maximum, allowed currencies, minimum description length, cost center types and the locked accounting period.
3. Under `Settlement Rules (CAR)`, set the `Reporting deadline (days)` (0 means no deadline) and the first reminder.
4. Click `Save settings`.
5. The same page also holds the approval workflows for CA and CAR. Each is shown only if you have access to it. Click `Save Workflow` after changing steps.

## What happens next

Settled advances are closed. A refund or claim amount appears on the `Dashboard` until it is handled.

## Common questions

**Can I change approval steps for documents already in progress?** Steps are copied to each document when it is created, so a change applies to new documents, unless you tick the option to apply new steps to documents in progress.

**Why don't I see a tab?** Each tab has its own access. Ask an administrator.

## Related topics

- [[my-cash-advance]]
- [[approval-workflows]]
- [[my-approvals]]
- [[workflow-finance]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/CashAdvance*.php, resources/views/hr-general/cash-advance/*.blade.php, app/Models/CashAdvance/CashAdvance.php -->
