---
key: my-overtime
chapter: my-workspace
order: 40
title: Overtime
summary: Submit an overtime request, see who must approve it, and cancel it while it is still waiting.
requires: [general.my-overtime]
updated: 2026-10-27
---
The `Overtime` page is where you submit and track your own overtime. The page names its legal basis (Article 78 of the Manpower Law and Government Regulation No. 35 of 2021) so you know on what ground overtime is counted.

## Who uses this

Every employee who has the `Overtime` menu under `My Workspace`. Approvers review requests in `My Approvals` or in Overtime Management.

## Before you start

Know the date, the start and end time, and what you worked on. The page can compare your claim with your attendance record, so check `My Attendance` first.

## Step by step

### Submit overtime

1. Open `Overtime` from the sidebar. The cards show your `Submitted`, `Approved`, `Waiting Review` and `Approved Hours` for the month.
2. Select `Submit Overtime`.
3. Read the box `Your request will be reviewed by`. It lists the approval steps. If a red message says no approval step is configured, you cannot submit until HR sets one up.
4. Enter the `Overtime Date`.
5. Enter the `Start Time` and `End Time`. The form shows `Calculated duration`. If the end time is on the next day, it says so.
6. Check the attendance comparison that appears under the times. If your claim does not match your attendance, you can still submit, but the request is flagged for the reviewer.
7. Write the `Reason`. It must reach the minimum length shown in the placeholder.
8. Read `Current rules` on the right. They show whether future dates and passing midnight are allowed, how far back you may claim, and the daily cap. Going over a cap is flagged for review, not blocked.
9. Select `Submit Request` and confirm.

Several requests on the same day are allowed as long as the times do not overlap.

### Follow your requests

1. In the table `Overtime Requests`, read `Overtime No.`, `Date`, `Time`, `Duration` and `Status`.
2. Read the `Approval` column. Each step shows a colored dot (approved, rejected, skipped or waiting) with the approver's note.
3. If an approver changed your times, the row shows `Adjusted from` followed by the original times.

### Cancel a request

1. Find the request in the table.
2. Select `Cancel` and confirm. The button is shown only while the request is still waiting for the first reviewer.

## What happens next

When the request moves through its steps, the `Status` goes from `Submitted` to `In review` (or `Approved by` followed by the step name) and finally `Approved` or `Rejected`. You receive a Notification with the result. Rejected overtime cannot be edited; submit a new request. See [[statuses-and-approvals]].

## Common questions

**The `Cancel` button is gone.** An approver has already acted on the request. Contact HR.

**Why was my request flagged?** Typical reasons are a mismatch with attendance, a future date, a duration over the daily limit or a missing attendance record. A flag does not reject the request; it tells the reviewer to look closer.

## Related topics

- [[my-attendance]]
- [[my-approvals]]
- [[statuses-and-approvals]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyOvertimeController.php, resources/views/hr-general/overtime/my-overtime.blade.php, resources/views/hr-general/overtime/submit.blade.php, app/Models/Overtime/OvertimeRequest.php -->
