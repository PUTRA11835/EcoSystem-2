---
key: calendar-timesheet
chapter: my-workspace
order: 115
title: Calendar and Timesheets
summary: See events on the calendar, log your working hours against a ticket, send them for approval and ask for late access.
requires: [calendar.events, calendar.timesheets]
updated: 2026-10-28
---
`Calendar` shows the events you may see. `Timesheets` is where you log your working hours and send them for approval.

## Who uses this

Employees who have `Events` or `Timesheets` under `Calendar`. Heads and approvers use the same timesheet page to review their team.

## Before you start

- Know the ticket you worked on, the date, the start and end time and what you did.
- A button appears only if your role allows it. If `Create Event` or `Create Timesheet` is missing, ask your administrator.

## Step by step

### Read the calendar

![The Events Calendar page for October with Today, month navigation and the Month, Week and Day views](images/guide/calendar-events.png)

*Screenshot with sample data.*

1. Open `Calendar` › `Events`. The page is titled `Events Calendar`.
2. Use the arrows to move between months, or select `Today` to come back to the current date.
3. Switch between `Month`, `Week` and `Day`.
4. Select an event to read its details.

### Create an event

![The Create New Event form with title, type, description, dates, times and location](images/guide/calendar-create-event.png)

*Screenshot with sample data.*

1. Select `Create Event`. The form is titled `Create New Event`.
2. Enter the `Event Title` and choose the `Event Type`: `Meeting`, `Task`, `Deadline`, `Urgent` or `Reminder`.
3. Enter the `Start Date` and `Start Time`. Add the `End Date` and `End Time` if needed, or tick `All day event`.
4. Add a `Description` and a `Location` if useful.
5. Select `Save Event`.

### Log your working hours

![The Timesheets page with the period, summary cards, the Create Timesheet button and the list of entries with their status](images/guide/timesheets.png)

*Screenshot with sample data.*

1. Open `Calendar` › `Timesheets`. The chip next to the buttons shows the reporting period, for example `October 2026`, and whether it is `Open`.
2. Select `Create Timesheet`. The form is titled `Log Working Hours`.

![The Log Working Hours form with type, dates, time, ticket, MD consumed, activity and notes](images/guide/timesheets-log-modal.png)

*Screenshot with sample data.*

3. Choose the `Type`, then check the `Submit Date`. The `Reporting Period` is filled in for you.
4. Choose the start and end `Time`. The form shows the `Duration`. The end time must be later than the start time.
5. Choose the `Ticket`. The `Customer`, `Quota MD` and `Remaining` boxes fill in from it.
6. Set the `Activity Date`, which is when the work actually happened.
7. Enter `MD Consumed` and tick `On Site` if you worked at the customer's site.
8. Describe the work in `Activity`. `Notes` is optional.
9. Select `Save Timesheet`. The entry starts as `Draft`, and you can still edit or delete it.

### Send for approval

1. Select the draft entries in the list.
2. Select `Submit Timesheets` and confirm.
3. The status becomes `Submitted`. After that you cannot edit the entry yourself.

The cards above the list count your entries as `Draft`, `Submitted`, `Approved` and `Rejected`, and show `Total Quota MD` and `Total MD Consumed`.

### Ask for late access

1. If a period is closed, select `Request Late Access`.
2. Choose the period and write the reason.
3. Send the request. It is reviewed by your Head first and then by RPMO.

> [!NOTE]
> If you are allowed to approve timesheets, you also see `Approve` and `Reject`, including for several entries at once. Always write a clear reason when you reject.

## What happens next

A rejected entry shows the reason: correct it and submit again. A late access request shows `Pending Head`, then `Pending RPMO`. Once both approve, you can fill in that period until its deadline.

## Common questions

**I cannot create a timesheet for last month.** The period is probably closed. Use `Request Late Access`.

**Can I change a submitted timesheet?** No. Ask the person who approves it to reject it, then correct it.

**Why are `Quota MD` and `Remaining` empty?** They fill in after you choose a ticket.

<!-- sources: routes/web.php, app/Http/Controllers/CalendarController.php, resources/views/calendar/events.blade.php, resources/views/calendar/timesheets.blade.php -->
