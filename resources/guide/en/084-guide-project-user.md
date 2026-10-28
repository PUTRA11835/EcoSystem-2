---
key: guide-project-user
chapter: reference
order: 84
title: Guide for Delivery Project User
summary: The daily routine of a project consultant: follow your tickets, keep progress current, log time and check mandays.
requires: ["@role:Delivery Project User"]
updated: 2026-10-28
---
This guide is for the Delivery Project User role, the consultants who work on projects and the tickets linked to them. It shows a simple daily routine and points to the detailed topics.

## Who uses this

Consultants with the Delivery Project User role. Your menus are `Home`, `AI Assistant`, `AI Research`, `Ticket`, `My Tasks`, `Calendar` (`Events` and `Timesheets`) and `Reporting` › `MD Validation`. Menus you do not see are not part of your role.

## Before you start

- If a button described below is missing, it is not part of your role. Ask your Project Head or Helpdesk.
- A ticket can show customer credential information. Use it only for the work, and never copy it into chat, notes, email or other documents.
- Do not put credentials or customer personal data into the AI tools.

## Step by step

### Every day

1. Open `My Tasks`. The cards at the top show your `Active Tickets`, `Alloc MD`, `Remaining`, `Workload` and `Load Score`. Use the month, year and search boxes to find a ticket.

![The My Tasks page with the summary cards and the tickets where you are the lead](images/guide/my-tasks.png)

*Screenshot with sample data.*

2. Open `Ticket` to see all the tickets you may view. The cards at the top count them by status, and `Reset Filter` clears your filters.
3. Open a ticket to reply to the customer or write an `Internal Note`. An internal note is seen only by your team, and a reply is seen by the customer, so check which one you are using before you send.
4. Keep the status true to the work, for example `Wait Customer` while you wait for an answer. A correct status keeps the service level fair.
5. Log your hours in `Calendar` › `Timesheets` before the day ends. See [[calendar-timesheet]].

### Check your mandays

1. Open `Reporting` › `MD Validation`.
2. Pick the month. The cards show `Total Entries`, `Match` (consumed equals quota), `Less` (consumed is below quota) and `Over` (consumed is above quota).
3. Read the table by employee, date, ticket and customer to compare `MD Input`, `Quota MD` and `Remain`.
4. Select `Export MD` if you need the data in a spreadsheet.

### Plan your time

Use `Calendar` › `Events` for meetings, deadlines and tasks. See [[calendar-timesheet]].

## What happens next

Timesheets you submit go to your head for approval. Approved time feeds the workload and mandays reports that your managers read.

## Common questions

**A ticket is missing from `My Tasks`.** Only tickets where you are the ticket lead or a team member appear. Ask the ticket lead to add you.

**I cannot create a ticket or assign one.** Those actions belong to Helpdesk and heads.

**`MD Validation` shows no records.** There are no approved entries in the month you picked. Change the month.

## Related topics

- [[calendar-timesheet]]
- [[sign-in-and-security]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/task.blade.php, resources/views/reporting/reporting.blade.php, resources/views/calendar/timesheets.blade.php -->
