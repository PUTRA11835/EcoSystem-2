---
key: reimbursement-management
chapter: finance
order: 40
title: Reimbursement Management
summary: Review, approve and pay employee reimbursements, and set the rules and approval steps for them.
requires: [general.reimbursement, general.settings.reimbursement, general.approval-workflow.reimbursement]
updated: 2026-10-27
---
Reimbursement Management is where HR, Finance and approvers handle the reimbursement requests that employees submit. It has three tabs: `Dashboard`, `Reimbursement Management` and `Settings`.

## Who uses this

Approvers, HR and Finance. You only see the tabs your role allows, and each button appears only when you have the matching access (create, export, import, approve, delete). If something is missing, ask an administrator.

## Before you start

- Employees submit their own requests in `My Workspace` › `Reimbursement` (see [[my-reimbursement]]).
- The approval steps must exist in `Settings` › `Approval Workflow`. See [[approval-workflows]].

## Step by step

### Find documents

1. Open `Reimbursement Management` › `Reimbursement Management`.
2. Read the cards: `Waiting for You`, `Pending Total`, `Approved`, `Awaiting Payment` and `Rejected`. Click `Waiting for You` or `Awaiting Payment` to filter the list; click it again to clear.
3. Choose a `Month`, or use the filter icons in the table header to search by document, requester, approver or status.
4. Rows that need attention are highlighted. Click `Reset Filter` to start again.

### Approve or reject

1. Open the document.
2. Check the items, receipts and supporting link under `Reimbursement Item Details`.
3. If it is your turn, click `Approve & Sign`, or click `Reject`, write the reason (at least 5 characters) and click `Confirm Rejection`.
4. The `Approval Trail` shows who acted and when.

### Record the payment

1. Open an approved document that shows `Awaiting payment`.
2. Under `Record payment`, enter `Paid on` and a `Transfer reference`. Add a note if needed.
3. Click `Mark as paid`. The document shows `Paid` with the date.

> [!WARNING]
> If the reimbursement is already part of a payroll period, the page warns you. It is then paid with salary. Record a payment here only if it was also paid separately, or it would be paid twice.

### Create, import and export

- `New RB` creates a document on behalf of an employee.
- `Import RB` loads many documents from a file template.
- `Monthly Export` downloads the filtered list. Without a date range it exports the current month. A single document can be exported to Excel or printed with `Print Form`.

### Delete a document

Open the document and click `Delete`, then give a reason. Deleted documents stay visible under the `Deleted` status filter. Deleting needs special access.

### Set the rules (Settings)

1. Open the `Settings` tab.
2. In `Approval Workflow`, add or arrange the steps and click `Save Workflow`.
3. In `Reimbursement Rules`, set limits such as the request window, receipt age, items and amounts per document, and whether a supporting link or receipt number is required. Click `Save Settings`.

A rule set to flag only highlights the document. A rule set to block refuses it. A value of 0 means no limit.

## What happens next

Approved documents wait for payment. If [[payroll]] includes approved reimbursements, they are paid with salary.

## Common questions

**Why can't I approve a document?** Only the person whose step is current can act. The page shows the current step.

**Do rule changes affect existing documents?** Rule changes apply to new requests. Documents already in progress keep their steps.

## Related topics

- [[my-reimbursement]]
- [[approval-workflows]]
- [[payroll]]
- [[my-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/ReimbursementController.php, app/Http/Controllers/HR_General/ReimbursementSettingController.php, resources/views/hr-general/reimbursement/*.blade.php, app/Models/Reimbursement/ReimbursementRequest.php -->
