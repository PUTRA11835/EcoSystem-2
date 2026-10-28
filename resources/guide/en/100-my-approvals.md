---
key: my-approvals
chapter: my-workspace
order: 100
title: My Approvals
summary: See every document waiting for your decision across all modules, then approve or reject it.
requires: [general.approvals, "@approver"]
updated: 2026-10-27
---
`My Approvals` is your inbox for decisions. It collects Overtime, Reimbursement, Purchase Request, Cash Advance and Cash Advance Report documents that are waiting for you, so you do not need to visit each module.

## Who uses this

Anyone named as an Approver on an approval step: a department head, a director, a Finance officer, a supervisor, a project leader and so on. You do not need an HR menu to approve. The menu appears for you as soon as you are named, and you see only the documents you take part in.

## Before you start

Know the rules of the module you are approving. Read the whole document, including the items and attachments, before deciding.

## Step by step

### Open your inbox

1. Select `My Approvals` in the sidebar.
2. Read the cards. `Waiting for you` is the total, and one card per module shows its own count.
3. Select a card to show only that module. Select `Waiting for you` to show everything again.

### Review a document

1. In the table `Waiting for your decision`, read `Module`, `Document No.`, `Requester`, `Your step` and `Waiting since`.
2. Select `Review` in the `Action` column.
3. Read the details, the item list and the approval trail. Items marked as needing attention explain why they were flagged.

### Approve

1. On the document, find the box `Approval Actions`. It shows the current step.
2. Select `Approve` (it reads `Approve & Sign` on a reimbursement) and confirm if asked.

On an Overtime request your role may also let you adjust the times before approving. When you do, the requester sees that the times were adjusted.

### Reject

1. Select `Reject`.
2. Enter the reason in the note box. A reason is required, at least 5 characters on most documents.
3. Confirm the rejection with the button below the note box (`Confirm Rejection` on most documents).

> [!WARNING]
> A rejection ends the whole document. The remaining steps are skipped and the requester has to submit a new request.

## What happens next

After you approve, the document moves to the next step, or becomes `Approved` if yours was the last. The requester receives a Notification either way. The document leaves your inbox. If the box says the document is waiting for another step, it is not your turn yet.

Some positions are also given extra actions such as `Export`. These appear only when your role includes them.

## Common questions

**I cannot see `My Approvals`.** You are not named as an approver, and your role does not include the menu. Ask the person who manages the approval workflow.

**The inbox says `Nothing is waiting for you.`** You are all caught up.

**I clicked `Review` but cannot approve.** The document is waiting for another step, or an administrator rule prevents you from acting on it.

## Related topics

- [[statuses-and-approvals]]
- [[my-overtime]]
- [[my-reimbursement]]
- [[navigating-ecosystem]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/ApprovalInboxController.php, app/Services/Approval/ApprovalEngine.php, resources/views/hr-general/approvals/index.blade.php, resources/views/hr-general/approvals/overtime.blade.php, resources/views/hr-general/reimbursement/show.blade.php, app/Services/Reimbursement/ReimbursementService.php, resources/views/home/command-center.blade.php -->
