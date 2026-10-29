---
key: late-access-request
chapter: work-service
order: 61
title: Late Timesheet Access
summary: When you need late access, and how the request is decided in two steps by your Head and then RPMO.
requires: [calendar.timesheets, rpmo.periods]
updated: 2026-10-29
---
Late access is temporary permission to fill in a timesheet for a period that has already passed. It is granted per person and per period, through two approvals in order: your team's Head, then RPMO.

## Who uses this

| Role in the flow | What they do | Who |
|---|---|---|
| Requester | Asks for late access and writes the reason | Consultants in the Project and Support teams |
| Approval 1 | Approves or rejects requests from their own team | Holders of `Manage Project Domain` or `Manage Support Domain` (default: Delivery Project Head and Delivery Support Head) |
| Approval 2 | Approves with an access deadline, or rejects | Holders of `Manage Global` (default: Delivery RPMO Head) |

EC Administrator holds all three permissions. They can be changed in `Control Center` › `Menu Access`.

## When to use it

This feature is for special cases: you must fill in a timesheet for a period that has already passed and been closed. Normally timesheets can be logged only in an open period, meaning the running period and the one before it. Outside that the system refuses and tells you to send a late access request. Use it when:

- You forgot to fill in a timesheet and the period is closed.
- Old work can only be logged now, for example you received the activity data late.
- An old timesheet needs correcting or completing after its period passed.

The reason for the two-step flow: a closed period is the basis for reports and MD calculations. Reopening it changes numbers that are already in use, so the Head confirms the work is real, and RPMO decides how long the door stays open.

You do not need this while the period is still open. If the running or previous period is not open yet, just wait for RPMO and your team's Head to open it.

## Before you start

- Only members of the Project and Support teams use it. Other roles, for example external consultants, are not subject to this period restriction and are refused if they try to send a request.
- There is one request per person per period. While a request is waiting, or its access is still active, you cannot send a new one.
- A reason is required at every step: by the requester, by the Head and by RPMO.

## Step by step

### As a consultant (requester)

1. Open `Calendar` › `Timesheets` and select `Request Late Access`.
2. In `Select Period`, choose the period.
3. Write the reason in `Reason / Notes`. Explain what was missed and why. A clear reason speeds up the decision.
4. Select `Submit Request`.
5. Follow it in the `My History` tab. The status is `Pending Head`, then `Pending RPMO`. Once approved, the access deadline and remaining time are shown.
6. Fill in that period's timesheets before the deadline ends.

### As a Head (approval 1)

1. You are notified when a team member sends a request. Open `RPMO` › `Period Management` and select `Exception Requests`.
2. You see only requests from the team you manage that are waiting for the Head.
3. Read the reason. Check that the work is real and reasonable to log late.
4. Approve or reject, and write a note. If you reject, the requester reads that note.
5. If approved, the request moves to RPMO and the requester is notified.

### As RPMO (approval 2)

1. You are notified when a Head approves. Open `Exception Requests` in `Period Management`.
2. Read the requester's reason and the Head's note.
3. To approve, fill in `Access Deadline` (it must be in the future) and `RPMO Notes`, then select `Approve & Set Deadline`. Give enough time to finish the work, no more.
4. To reject, fill in the reason and select `Reject`.

## What happens next

- A rejection at either step ends the flow, and the requester receives the reason. They may send a new request for that period.
- Once RPMO approves, the requester can fill in that period, whether it is open or closed, until the access deadline.
- Access applies only to the person, period and team requested. After the deadline the period is closed to them again and a new request is needed.
- Each request keeps who approved it, when, and their note. All of it shows in the `Exception Requests` dialog.

## Common questions

**Last month's period is closed. Can I request late access?** Yes. Any closed period can be requested, including last month. Once RPMO approves, you can fill it in until the access deadline.

**The `Request Late Access` button is missing.** It appears only for consultants who fill in timesheets. Heads and RPMO do not request it, because they are the ones who decide.

**My request was approved by the Head but nothing moves.** It is RPMO's turn. The status is `Pending RPMO`.

**My access ran out before I finished.** Send a new request for the same period and explain why the time was not enough.

<!-- sources: app/Services/PeriodService.php, app/Http/Controllers/PeriodManagementController.php, app/Http/Controllers/TimesheetController.php, resources/views/calendar/timesheets.blade.php, resources/views/rpmo/periods/index.blade.php -->
