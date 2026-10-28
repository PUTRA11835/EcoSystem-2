---
key: rpmo
chapter: work-service
order: 60
title: RPMO Period Management
summary: Open and close reporting periods so timesheets can be entered, and handle late access requests.
requires: [rpmo, rpmo.overview, rpmo.periods]
updated: 2026-10-27
---
RPMO (Resource & Project Management Office) controls the monthly reporting periods in which delivery teams may enter timesheets. Today, the working feature of this menu is `Period Management`.

## Who uses this

RPMO staff and administrators manage periods globally. Project and Support heads open and close their own team's part of a period and decide on late access requests. Other users never see these controls.

## Before you start

- The `Overview` page is still a placeholder that says the module is under construction. It only links to `Period Management`.
- A period normally runs from the 21st of one month to the 20th of the next. You can change the dates when you create it.

## Step by step

### Create and open a period

1. Open `Work & Service` › `RPMO` › `Period Management`.
2. Click `New Period`.
3. Choose the `Year` and `Month`. Check or adjust `Start Date` and `End Date`.
4. Click `Create Period`. The new period is shown as `Pending Period` with a `Not Open` badge.
5. Click `Open Period Globally`. The badge changes to `Open`.

### Open and close a team's part

A period has two domains: `Project Domain` and `Support Domain`.

1. Find the domain you manage on the active period card.
2. Click `Open Project Period` or `Open Support Period` to let that team enter timesheets.
3. When the team is done, click `Close Project Period` or `Close Support Period`.
4. Confirm with `Yes, Close Domain`.

### Close a period for everyone

1. Make sure both domains are closed. The page reminds you that both must be closed first.
2. Click `Close Period Globally`, then confirm with `Yes, Close Period`.

If a domain must be closed immediately, RPMO or an administrator can use `Force Close Project` or `Force Close Support`. This cannot be undone.

### Handle late access requests

1. Click `Exception Requests` on the active period card.
2. Read each request in the `Late Access Requests` window, with the employee's notes.
3. Approve or reject it. When approving, set the `Access Deadline` and add your notes. The Head approves first; RPMO then approves and sets the deadline.

### Other actions

- Use the menu on a row in `Period History` to edit the dates or delete a period.
- Click `Audit Log` to see who opened, closed or changed a period and when.

## What happens next

- Team members can enter timesheets only for a period that is globally open and whose domain is open. Periods outside the current and previous month need an approved late access request.
- Employees and heads receive notifications about the request's progress.

## Common questions

**I cannot submit a timesheet for last month.** The period may be closed. Ask your Head to approve a late access request.

**I do not see the open and close buttons.** They appear only for RPMO, administrators and, for their own domain, the Heads.

## Related topics

- [[delivery]]
- [[my-calendar-timesheet]]
- [[report-delivery]]

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, app/Services/PeriodService.php, resources/views/rpmo/rpmo.blade.php, resources/views/rpmo/periods/index.blade.php -->
