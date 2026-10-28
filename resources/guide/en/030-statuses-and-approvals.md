---
key: statuses-and-approvals
chapter: getting-started
order: 30
title: Statuses and approvals
summary: What each status means, how a request moves through its approvers, and what to do after it is approved or rejected.
requires: []
updated: 2026-10-27
---
Most requests in EcoSystem (overtime, reimbursement, purchase, cash advance, leave and more) pass through one or more approvers. This topic explains the statuses you see and what to do next.

## Who uses this

Everyone who submits a Request, and everyone who is an Approver.

## How a request moves

1. You submit the request. It gets a document number and the status `Submitted`.
2. The first approver in the chain is notified. When that person approves, the request moves to the next step and shows as `In review`.
3. When the last step approves, the status becomes `Approved`.
4. If any approver rejects, the request becomes `Rejected` straight away. The remaining steps are skipped and you receive a Notification with the reason.

While a request is still open, some screens name the current step, for example `Waiting` followed by the step name, so you can see whose turn it is. The `Approval` column of `Overtime` also shows each step with its result.

## Who approves

Each module has an approval chain that HR or an administrator sets up. A step can be:

- a position (anyone holding that position can act),
- one or more named people,
- your direct supervisor, or the leader of your project (used by Overtime),
- a person you choose yourself from a list when you submit. The form then shows a required dropdown such as `— choose an approver —`.

Steps marked as fixed appear on the form as read-only so you can see who will sign.

## Statuses by module

| Module | Statuses you can see |
|---|---|
| Overtime | `Submitted`, `In review`, `Approved`, `Rejected`, `Cancelled` |
| Reimbursement | `Submitted`, `In review`, `Approved`, `Rejected` |
| Purchase Request | `Submitted`, `In review`, `Approved`, `Rejected`, `Cancelled` |
| Cash Advance and Cash Advance Report | `Submitted`, `In review`, `Approved`, `Rejected`, `Cancelled` |
| Leave & Permit | `Pending`, `Approved`, `Ask for Edit`, `Rejected` |
| Attendance correction | `Pending`, `Approved`, `Rejected`, `Cancelled` |
| Letter requests | `Pending`, `In Progress`, `Done`, `Rejected`, `Cancelled` |
| Timesheet | `Draft`, `Submitted`, `Approved`, `Rejected` |
| KPI | `Draft`, `Self-Assessed`, `Reviewed`, `Completed`, `Approved`, `Needs Revision` |

A Cash Advance also shows a second label for its report: `Not reported`, `Reporting` or `Settled`. A Cash Advance Report shows how it settles: `Refund`, `Claim` or `Exact`.

## What to do next

### After a request is approved

Nothing more is needed for the approval itself. Follow the module topic for the next real-world step, such as payment or the Cash Advance Report.

### After a request is rejected

1. Open the request and read the reason in the approval trail or notes.
2. Fix the cause, for example a missing receipt or a wrong date.
3. Submit a new request. Rejected Overtime, Reimbursement, Purchase Request and Cash Advance documents are not edited and resent; a new document is created.

For Leave & Permit, `Ask for Edit` means the reviewer wants a change: select `Edit` on the application, update it and send it again. For KPI, `Needs Revision` shows a `Revise` button.

### Cancelling

You can cancel only while the request has not been acted on, and only where the module allows it. `Reimbursement` cannot be cancelled by you; ask HR.

## As an Approver

Your pending items are collected in `My Approvals`. See [[my-approvals]]. When you reject, always write a clear reason, because the requester sees it.

## Common questions

**My request has been `Submitted` for days.** The first approver has not acted yet. Check who the approver is on the request and remind them.

**Can I change a request after submitting?** Generally no; only HR can change a submitted document. Cancel it if the module allows, or ask HR.

## Related topics

- [[my-approvals]]
- [[my-overtime]]
- [[my-reimbursement]]
- [[my-leave-permit]]
- [[navigating-ecosystem]]

<!-- sources: app/Services/Approval/ApprovalEngine.php, app/Services/Approval/ApproverResolver.php, app/Models/Overtime/OvertimeRequest.php, app/Models/Reimbursement/ReimbursementRequest.php, app/Models/PurchaseRequest/PurchaseRequest.php, app/Models/CashAdvance/CashAdvance.php, app/Models/CashAdvance/CashAdvanceReport.php, app/Models/KpiEvaluation.php, app/Models/Letters/LetterRequest.php, resources/views/hr-general/leave-permit/my-leave-permit.blade.php, resources/views/hr-general/attendance/my-attendance.blade.php, resources/views/calendar/timesheets.blade.php, app/Services/Reimbursement/ReimbursementService.php -->
