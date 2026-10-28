---
key: overtime-management
chapter: human-capital
order: 90
title: Overtime Management
summary: Review overtime requests against attendance, approve or reject them, export, and set the overtime rules.
requires: [general.overtime, general.settings.overtime]
updated: 2026-10-27
---
`Overtime Management` lists all overtime requests, shows them next to the attendance record, and lets approvers decide. Its `Settings` tab holds the rules that employees must follow when they submit.

## Who uses this

HR staff and the approvers named in the approval workflow. Employees submit overtime in My Overtime.

## Before you start

Open `Human Capital` › `Overtime Management`. It has two tabs, `Overtime Management` and `Settings`. The approval steps themselves (who approves, in what order) are set in the overtime approval workflow, see [[approval-workflows]].

## Step by step

### Find requests

1. Choose a quick period: `This week`, `This month` or `This year`, or set `From` and `To`.
2. Filter by `Search` (request number, employee or reason), `Status` (`All`, `Pending`, `Approved`, `Rejected`) and `Scope` (`Everyone` or `Waiting for me`).
3. Select `Apply`.
4. Read the cards: `Waiting for You`, `Pending Total`, `Approved`, `Rejected` and `Approved Hours`.

### Decide a request

1. In the table, read `Request`, `Employee`, `Date`, `Time`, `Claimed` and `Attendance`. The attendance column compares what was claimed with the check-in and check-out of that day, and shows `no record` or `not yet` when there is nothing to compare.
2. Read the `Reason` and the `Approval` column. A request that is waiting for you is marked `Your turn`. If it waits for someone else, the column says who.
3. Type a note. It is required when you reject.
4. Select `Approve` or `Reject`.

If the workflow has more than one step, the request goes to the next approver after your approval.

### Export to Excel

1. Select `Export Excel`.
2. Choose what is on screen now, a quick period, or `Choose a period…`.
3. In the dialog, pick the `Period` (month, week, year or a date range) and the `Status`.
4. Confirm to download the file.

### Change the overtime rules

In `Settings` (`Overtime Rules`) you can set:

- Dates: whether future dates are allowed, the backdate limit in days (0 means no limit) and the closed reporting period.
- Duration: whether overtime may end after midnight, a minimum in minutes, and a daily and weekly cap (0 means no cap). The minimum is flagged, not blocked.
- Approval: self-approval, whether approvers may adjust times, and a fallback approver role.
- Comparison and reason: the tolerance for an attendance mismatch in minutes and the minimum reason length.

Select `Save Settings` when you finish.

> [!WARNING]
> Rule changes apply to new requests from the moment you save. Check with HR leadership before enabling self-approval.

## What happens next

The employee sees the decision in My Overtime. Approved hours are summed in the `Approved Hours` card.

## Common questions

**Why can't I approve?** The request may be waiting for another approver. The row names the approver.

**Why does Attendance say no record?** The employee has no check-in for that date.

## Related topics

- [[attendance-management]]
- [[approval-workflows]]
- [[leave-permit-management]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/overtime/review.blade.php, resources/views/hr-general/overtime/settings.blade.php, resources/views/hr-general/overtime/components/hub-tabs.blade.php -->
