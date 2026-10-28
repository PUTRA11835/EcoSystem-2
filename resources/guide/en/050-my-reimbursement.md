---
key: my-reimbursement
chapter: my-workspace
order: 50
title: My Reimbursement
summary: Claim expenses you already paid, attach a supporting link, and follow the claim until it is approved.
requires: [general.my-reimbursement]
updated: 2026-10-27
---
Use `My Reimbursement` to claim expenses you already paid with your own money. The claim is verified by the approvers before it is paid.

## Who uses this

Every employee who has the `My Reimbursement` menu.

## Before you start

- Collect your receipts and put them in a shared folder or file, then copy the link. The link must be readable by whoever verifies the claim; the system cannot check that for you.
- Know the branch each expense belongs to, the receipt date and the amount.

## Step by step

### Submit a reimbursement

1. Open `My Reimbursement` from the sidebar and select `Submit Reimbursement`.
2. If a red message says no approval step is configured, stop. HR must set one up first.
3. Check `Document No.`. It reads `Generated on submit`.
4. Enter the `Request Date` and choose the `Currency` (IDR is the default).
5. Enter a `Reimbursement Title`: one line that summarises the whole claim, for example operational expenses for this month.
6. Paste the `Supporting Document` link. If the field is marked required, you cannot submit without it, and the page may list the accepted link hosts.
7. Fill in the item table. For each item enter the `Description`, the receipt date (and an end date for a multi-day receipt), the `Branch`, the `Receipt No.` and the `Amount`.
8. Select `Add More Reimbursement` to add another item, or the remove icon to delete one.
9. In the signature area, choose the approver for any step that asks you to pick one. Fixed steps are shown read-only.
10. Read `Current rules`. They show the backdating limit, the number of items allowed, the maximum total and whether a receipt number is required.
11. Select `Submit Request`.

> [!NOTE]
> Going over the maximum total is either refused or flagged for the reviewer, depending on the rule shown under `Current rules`.

### Follow your claims

1. In the table `Reimbursement Requests`, read `Document No.`, `Date`, `Summary`, `Item`, `Amount` and `Status`.
2. Select the document number to expand its items, with `Description`, `Category`, `Receipt No.`, `Receipt Date` and `Amount`.
3. Open the details icon to see the approval trail, or the print icon to print the document.

## What happens next

The claim moves from `Submitted` to `In review`, and the status text names the step it is waiting for. A rejection ends the claim and the reason is in the approval trail. See [[statuses-and-approvals]].

> [!IMPORTANT]
> You cannot cancel or edit a submitted claim. Only HR can change it. If you made a mistake, contact HR before the claim is approved.

## Common questions

**I cannot submit.** Check that every item has a branch, an amount and a description, and that any required receipt number or link is filled.

**The link was refused.** The company allows links from specific sites only. The accepted sites are listed under the field.

## Related topics

- [[statuses-and-approvals]]
- [[my-cash-advance]]
- [[my-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyReimbursementController.php, resources/views/hr-general/reimbursement/my-reimbursement.blade.php, resources/views/hr-general/reimbursement/submit.blade.php, resources/views/hr-general/reimbursement/show.blade.php, resources/views/hr-general/reimbursement/components/item-rows.blade.php, app/Models/Reimbursement/ReimbursementRequest.php -->
