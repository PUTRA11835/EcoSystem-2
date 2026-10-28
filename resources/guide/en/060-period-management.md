---
key: period-management
chapter: work-service
order: 60
title: Period Management
summary: Open and close the monthly reporting period, control each team's part of it and decide late access requests.
requires: [rpmo.periods]
updated: 2026-10-28
---
Period Management controls the monthly reporting periods in which people can fill their timesheets. What you can do on the page depends on your role.

## Who uses this

| Role | What you can do |
|---|---|
| Delivery RPMO Head | Create a period, open and close it globally, force close a team's part, decide late access requests at the RPMO step, read the `Audit Log` |
| Delivery Project Head, Delivery Support Head | Open and close your own team's part (Project or Support), decide late access requests at the first step, read the `Audit Log` |
| EC Administrator | All of the above |
| Other roles that can open the page | Read-only view with the `Audit Log` |

## Before you start

- A period normally runs from the 21st of one month to the 20th of the next, as in the existing periods.
- A period has a global status (`Global (RPMO)`) and one status for each team (`Project Domain` and `Support Domain`). Each status is `Not Open`, `Open` or `Closed`.
- People can log timesheets only for a period that is open. When they miss the window they ask for late access, and you decide it here.

## Step by step

### Read the page

![The Period Management page with the active period, the global, project and support statuses and the period history](images/guide/period-management.png)

*Screenshot with sample data.*

1. Open `RPMO` › `Period Management`.
2. Read the `Active Period` card. It shows the date range and the status of `Global (RPMO)`, `Project Domain` and `Support Domain`.
3. Read `Period History` below it. Each row shows the period, its date range and the three statuses. The menu in the `Actions` column offers the actions your role may use.

### Create and open a period (Delivery RPMO Head)

1. Select `New Period`. The form is titled `Create New Period`.
2. Choose the `Year` and `Month`, then check the `Start Date` and `End Date`.
3. Confirm the form. The new period appears as a pending period with the status `Not Open`.
4. Select `Open Period Globally`. The status changes to `Open`.

### Open and close your team's part (Project Head and Support Head)

1. Find your team on the `Active Period` card.
2. Select `Open Project Period` or `Open Support Period` so your team can log timesheets.
3. When your team is done, select `Close Project Period` or `Close Support Period` and confirm.

### Close the period for everyone (Delivery RPMO Head)

1. Make sure both the Project and the Support parts are closed. The page reminds you that both must be closed first.
2. Select `Close Period Globally`. The button stays grey until both parts are closed.
3. Confirm.

### Force close a team's part (urgent cases)

1. Select `Force Close Project` or `Force Close Support`.
2. Read the warning in the `Force Close Domain` dialog and confirm.

> [!WARNING]
> Force close cannot be undone, and deleting a period with `Delete Period` removes it permanently. Use both only when you are sure.

### Decide late access requests

![The Late Access Requests dialog with a request waiting for RPMO approval, the access deadline and the RPMO notes](images/guide/period-management-requests.png)

*Screenshot with sample data.*

1. Select `Exception Requests` on the `Active Period` card.
2. Read each request: the employee, the period, the team, the reason and the status, for example `Waiting for RPMO Approval`.
3. When the Head has already decided, the dialog shows who approved and when.
4. As RPMO, enter the `Access Deadline` and the `RPMO Notes`, then select `Approve & Set Deadline`. Both are required when approving. Select `Reject` to refuse.

### Check what happened

Select `Audit Log` to see each action with the `Action`, `Actor` and `Timestamp` columns.

### Change the dates or delete a period

In `Period History`, open the menu in the `Actions` column of a row and choose `Edit Period Dates` or `Delete Period`. These appear only if your role allows them.

## What happens next

The employee is notified of the decision. Once both the Head and RPMO have approved, the employee can fill in the requested period until the access deadline you set. A closed period accepts no new timesheets without an approved late access request.

## Common questions

**I cannot see `New Period`.** Only the Delivery RPMO Head and administrators can create periods.

**`Close Period Globally` is grey.** The Project and Support parts must both be closed first.

**Someone cannot submit last month's timesheet.** The period is closed. They need to send `Request Late Access` from `Calendar` › `Timesheets`, and it must be approved by their Head and then by RPMO.

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, app/Services/PeriodService.php, resources/views/rpmo/periods/index.blade.php -->
