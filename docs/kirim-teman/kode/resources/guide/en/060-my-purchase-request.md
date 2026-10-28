---
key: my-purchase-request
chapter: my-workspace
order: 60
title: My Purchase Request
summary: Request goods or services that have not been bought yet, follow the approval and cancel while it is allowed.
requires: [general.my-purchase-request]
updated: 2026-10-27
---
Use `My Purchase Request` to ask the company to buy goods or services for you or your team. It is for items that have not been bought yet. For expenses you already paid, use [[my-reimbursement]].

## Who uses this

Every employee who has the `My Purchase Request` menu.

## Before you start

Know what you need, the quantity and unit, when you will use it, and which branch or project should bear the cost.

## Step by step

### Submit a purchase request

1. Open `My Purchase Request` from the sidebar and select `Submit Purchase Request`.
2. If a red message says no approval step is configured, stop. HR must set one up first.
3. Check `Document No.`. It reads `Generated on submit`.
4. Enter the `Request Date`.
5. Write the `Request Summary` in one line. Describe what the request is for, not only the first item.
6. Fill in the item table. For each item enter the `Description`, the `Period`, the `Qty`, the `Unit`, the `Use Date`, and `Charged To` (a branch or a project).
7. Select `Add Item Row` to add more items, or the remove icon to delete one.
8. Add `Notes` if the approver should know something. This is optional.
9. In `Requester & Approver`, select an approver for each step that asks you to choose, from the list `— choose an approver —`.
10. Read `Current rules`. They show whether future or past dates are allowed, the item limit, the maximum quantity per item, whether `Use Date`, `Period` and cost center are required, and whether you may cancel.
11. Select `Submit Request` and confirm.

### Follow your requests

1. In the table `Purchase Requests`, read `Document No.`, `Date`, `Summary`, `Item`, `Qty` and `Status`.
2. Select a document number to expand its items.
3. Use the details icon for the approval trail and the print icon to print.

### Cancel a request

1. Find the request. The `Cancel` button appears only when your company allows requesters to cancel and the request is still `Submitted`.
2. Select `Cancel` and confirm. You can cancel until the first approver acts.

> [!WARNING]
> A cancelled request cannot be restored. You must submit a new request.

## What happens next

The request moves from `Submitted` to `In review` as approvers act, then to `Approved` or `Rejected`. You are notified of the result. If the page says `Once submitted, only HR can change a request`, ask HR for changes. See [[statuses-and-approvals]].

## Common questions

**Why is `Use Date` or `Period` flagged?** When these are optional but empty, the request is flagged for the approver, not refused.

**Can I mix several branches or projects?** Yes. Choose `Charged To` per item; the document then shows a mixed cost center.

## Related topics

- [[my-reimbursement]]
- [[my-cash-advance]]
- [[statuses-and-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyPurchaseRequestController.php, resources/views/hr-general/purchase-request/my-purchase-request.blade.php, resources/views/hr-general/purchase-request/submit.blade.php, resources/views/hr-general/purchase-request/components/item-rows.blade.php, app/Models/PurchaseRequest/PurchaseRequest.php -->
