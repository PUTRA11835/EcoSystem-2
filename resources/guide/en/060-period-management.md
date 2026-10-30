---
key: period-management
chapter: work-service
order: 60
title: Period Management
summary: Open and close the monthly reporting period, control each team's part of it and decide late access requests.
requires: [rpmo.periods]
updated: 2026-10-28
---
Period Management controls the monthly reporting periods in which people can fill their timesheets. What you can do on the page depends on the permissions your role has in Menu Access.

## Who uses this

| Permission in Menu Access | What you can do | Given by default to |
|---|---|---|
| `Period Management` (the page) | Open the page: read-only view with the `Audit Log` | Several roles |
| `Manage Global` | Create a period, edit its dates, delete it, open and close it globally, force close a team's part, decide late access requests at the RPMO step | EC Administrator, Delivery RPMO Head |
| `Manage Project Domain` | Open and close the Project part, decide late access requests of the Project team at the first step | EC Administrator, Delivery Project Head |
| `Manage Support Domain` | Open and close the Support part, decide late access requests of the Support team at the first step | EC Administrator, Delivery Support Head |

An administrator can give or remove each permission for any role in `Control Center` › `Menu Access`. A person with both domain permissions gets buttons for both parts.

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

**I cannot see `New Period`.** Creating periods needs the `Manage Global` permission. Ask your administrator.

**`Close Period Globally` is grey.** The Project and Support parts must both be closed first.

**Someone cannot submit a closed period's timesheet.** If the case is special, they send `Request Late Access` from `Calendar` › `Timesheets`, and it must be approved by their Head and then by RPMO. The full flow is in [[late-access-request]].

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, app/Services/PeriodService.php, resources/views/rpmo/periods/index.blade.php -->
