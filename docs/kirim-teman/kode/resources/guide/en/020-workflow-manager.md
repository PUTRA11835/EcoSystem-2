---
key: workflow-manager
chapter: reference
order: 20
title: Workflow for managers and approvers
summary: What a manager or approver does with the requests that wait for a decision.
requires: [general.approvals, "@approver"]
updated: 2026-10-27
---
If you are named as an approver, requests that need your decision are collected in `My Approvals`. This page shows the routine and points to the detailed topics.

## Who uses this

Managers, supervisors and anyone named as an approver on an approval workflow. You do not need an HR menu to approve requests sent to you.

## What waits for you

`My Approvals` is titled "Everything that waits for your decision, across all modules". It covers these modules:

- Overtime
- Reimbursement
- Purchase Request
- Cash Advance
- Cash Advance Report

Leave requests have their own approval inbox inside `Leave & Permit`, for people who have access to it. See [[leave-permit-management]].

## Step by step

1. Open `My Approvals` from the sidebar under `My Workspace`.
2. Read the list. Each row shows the `Module`, `Document No.`, `Requester`, `Your step` and `Waiting since`.
3. Click `Review` on a row to open the request.
4. Read the details and attachments, then choose `Approve` or `Reject`. Give a clear reason when you reject, because the requester will see it.
5. Repeat until the page says that nothing is waiting for you.

> [!TIP]
> Open `My Approvals` once a day. A request stays waiting until you act on it.

## What happens next

- If the workflow has more steps, the request moves to the next approver. After the last step it becomes `Approved`.
- A rejected request becomes `Rejected` and goes back to the requester.
- Finance and HR then process approved requests, for example reimbursements and overtime.
- You can also make your own requests like any employee. See [[workflow-employee]].

## Common questions

**I do not see `My Approvals`.** The menu appears only if you are named on an approval workflow or your role was given access. Ask your administrator. See [[approval-workflows]].

## Related topics

- [[my-approvals]]
- [[approval-workflows]]
- [[statuses-and-approvals]]
- [[workflow-employee]]

<!-- sources: resources/views/partials/sidebar.blade.php, resources/views/hr-general/approvals/index.blade.php, app/Services/Approval/ApprovalEngine.php -->
