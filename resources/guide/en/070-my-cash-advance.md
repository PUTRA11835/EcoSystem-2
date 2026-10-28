---
key: my-cash-advance
chapter: my-workspace
order: 70
title: My Cash Advance
summary: Request a cash advance and then account for it with a Cash Advance Report before the deadline.
requires: [general.my-cash-advance, general.my-cash-advance-report]
updated: 2026-10-27
---
A Cash Advance (CA) is money the company gives you before you spend it. Once it is approved and used, you account for it with a Cash Advance Report (CAR). Both live on one page with two tabs: `My Cash Advance` and `My Cash Advance Report`.

## Who uses this

Every employee who has the `My Cash Advance` menu.

## Before you start

Know the amount, the purpose and which branch or project bears the cost. For the report, keep receipts and note each expense date and amount.

## Step by step

### Request a cash advance

1. Open `My Cash Advance` from the sidebar.
2. Select `+ Submit CA`.
3. If a message says the settings are not ready, wait until HR configures `Cash Advance Settings`.
4. Check `Document No.` (auto-generated on submit) and enter the `Date`. Use `Date (until)` if the advance covers a period.
5. Write the `Description`, one line that is printed on the document.
6. Choose the `Currency` and enter the `Amount`.
7. Add the `Detail URL` if you have a link with details. The field is marked when it is required.
8. Choose `Charged To` as a branch or a project, then choose the target in `Charged To — target`.
9. Choose the `Approver` if the form asks you to choose one. Add a note if useful.
10. Submit the form.

### Follow your cash advances

1. Read the cards `Waiting review`, `Outstanding` (approved, not yet reported) and `Reports overdue`.
2. In the table read `Document No.`, `CAR`, `Date`, `Description`, `Approver`, `Amount` and `Status`.
3. Select the document number for details. Print the document from there if needed.
4. Select `Cancel` if the button is shown. It appears only while your company allows cancelling and nobody has acted.

### Report what you spent

1. When a CA is approved, the `CAR` column shows `Create CAR`. A badge also shows the deadline, such as `Report due in 3 days` or `Report overdue`.
2. Select `Create CAR`. The page shows the document number and the advance amount you are settling.
3. Write the report description.
4. Add `Expense lines`. For each line enter the `Date`, `Description`, optional `Receipt No.`, `Amount`, `Charged To` and `Receipt link`. Select the add button for more lines.
5. Check the `Reported total`.
6. Choose the approver when asked, add `Additional Notes` and select `Submit`.

On the `My Cash Advance Report` tab, the panel `Waiting to be reported` lists approved advances that still need a report. The report table shows `Advance`, `Reported` and `Settlement`.

## What happens next

Your CA follows the approval steps: `Submitted`, `In review`, then `Approved`. After approval its report label moves through `Not reported`, `Reporting` and `Settled`. The settlement of a report is one of:

- `Refund`: you spent less than the advance, so you return the difference.
- `Claim`: you spent more, so the company owes you the difference.
- `Exact`: the amounts match.

If you cancel a report, its cash advance goes back to waiting for a report. See [[statuses-and-approvals]].

## Common questions

**There is no `Create CAR` button.** The CA is not yet approved, or a report already exists.

**I missed the deadline.** The badge turns red and `Reports overdue` counts it. File the report as soon as possible.

## Related topics

- [[my-reimbursement]]
- [[my-purchase-request]]
- [[statuses-and-approvals]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/cash-advance/my-cash-advance.blade.php, resources/views/hr-general/cash-advance/submit.blade.php, resources/views/hr-general/cash-advance/report-index.blade.php, resources/views/hr-general/cash-advance/report-submit.blade.php, resources/views/hr-general/cash-advance/components/car-due-badge.blade.php, app/Models/CashAdvance/CashAdvance.php, app/Models/CashAdvance/CashAdvanceReport.php -->
