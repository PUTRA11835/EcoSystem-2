---
key: my-calendar-timesheet
chapter: my-workspace
order: 110
title: Calendar and My Timesheet
summary: See company events and holidays, keep a personal agenda, and log your working hours in My Timesheet.
requires: [calendar, calendar.events, calendar.timesheets]
updated: 2026-10-27
---
`Calendar` shows company events and your own agenda. `My Timesheet` is where you log your working hours and send them for approval. Both are under `My Workspace`.

## Who uses this

Every employee who sees `Calendar` and `My Timesheet`. Heads and approvers use the same timesheet page to review their team's timesheets.

## Step by step

### Read the calendar

1. Open `Calendar` from the sidebar. The page is titled `Events Calendar`.
2. Choose the `Month`, `Week` or `Day` view. Use the arrows to move and `Today` to return to the current date.
3. Select an event to open `Event Details`. The `Event Types` list explains the colors.

Company events and holidays are set by HR. They appear on every employee's calendar.

### Add something to your agenda

1. Select `Add to My Agenda`. People who may publish company events see `Create Event` instead.
2. Enter the `Event Title`.
3. Choose the type: `Meeting`, `Task`, `Deadline`, `Urgent` or `Reminder`.
4. In `Visible to`, keep `Only me (my agenda)`. Company-wide events are available only to people allowed to publish them.
5. Add a `Description`, switch on `All day event` if needed, and fill in the start and `End Date` and `End Time`, and the `Location`.
6. Select `Save Event`.

To change or remove an event, open it and select `Edit` or `Delete`. You can change only your own agenda items.

### Log your hours in My Timesheet

1. Open `My Timesheet` from the sidebar.
2. Select `Create Timesheet`. The form is titled `Log Working Hours`.
3. Choose the type (`Project`, `Support` or `Office`) and fill in the date and schedule.
4. Describe the work in `Description`. Add `Notes` if useful.
5. Save the entry. It stays a `Draft`, and you can still edit it.
6. Select one or more entries in the table and use the submit button, then confirm. Once submitted, you cannot edit them until they are reviewed.

Use the `All`, `Project`, `Support` and `Office` tabs, and the filters for `Period`, `Status` and `Activity Type`, to find entries. `Export Timesheet` downloads your list.

### Request late access

If a period is closed and you still need to submit:

1. Select `Request Late Access`.
2. Choose the period under `Select Period`.
3. Explain why under `Reason / Notes`.
4. Select `Submit Request`. Your Head and RPMO review it, and the periods you may submit to are listed in the dialog.

## What happens next

Submitted timesheets go to the person who approves them. Statuses are `Draft`, `Submitted`, `Approved` and `Rejected`. If a timesheet is rejected, correct it and submit again. You receive Notifications for late access decisions. See [[statuses-and-approvals]].

## Common questions

**I cannot create a timesheet for last month.** The period is closed. Use `Request Late Access`.

**Can others see my agenda?** Items set to `Only me (my agenda)` are visible only to you.

## Related topics

- [[my-leave-permit]]
- [[my-attendance]]
- [[statuses-and-approvals]]

<!-- sources: routes/web.php, app/Http/Controllers/CalendarController.php, resources/views/calendar/events.blade.php, resources/views/calendar/timesheets.blade.php, resources/views/partials/sidebar.blade.php -->
