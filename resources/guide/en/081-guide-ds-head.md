---
key: guide-ds-head
chapter: reference
order: 81
title: Guide for Delivery Support Head
summary: What a support head decides and watches: approvals, assignments, SLA, reports and reporting periods.
requires: ["@role:Delivery Support Head"]
updated: 2026-10-28
---
This guide is for the Delivery Support Head role. You oversee the support service and decide the things that need approval.

## Who uses this

Delivery Support heads, and others whose role includes reporting periods or the Resolution Days report. Some points may not apply to your role.

## Before you start

- Your decisions affect billing and team workload. Read the details before you approve, and write a clear reason when you reject.
- You can see sensitive data about customers and employees. Use it only when needed and do not copy it into chat or email. Customer credential information on a ticket must never be copied elsewhere.

## Step by step

### Decisions waiting for you

1. **Resolution Days**: open the ticket with status `Pending Head` and approve it, or reject it with a reason. See [[ticket-mandays]].
2. **Timesheets**: in `Calendar` › `Timesheets`, approve or reject your team's entries, also several at once. See [[calendar-timesheet]].
3. **Late access**: a request waits at `Pending Head` before it goes to RPMO.
4. **Mandays proposals**: review, edit, send to the customer or cancel them from the ticket.
5. **Incoming tickets**: in `Ticket Validation`, approve real requests and reject the rest with a reason.

### Keep an eye on the work

1. Open `Consultant Workload` before you assign work, to see who is busy.
2. Use the `All Tickets` tab and `My Tasks` to follow tickets that are stuck.
3. Read the reports in `Reporting`: `Resolution Days`, `MD Recap`, `MD Validation`, `Ticketing Overview`, `Log Shifting` and `Weekly Consolidation`.
4. Check `SLA` › `SLA Report` for response and resolution times.

### Manage Delivery Support

![The Delivery Support page with the summary cards and the list of support items](images/guide/delivery-support-list.png)

*Screenshot with sample data.*

1. Open `Delivery` › `Support`.
2. Work on the sections you may edit: general information, approval, financial, team, customer PIC, activities, SLA, plan cost, documents and recons.
3. Add a new Delivery Support or remove one that is no longer used, if your role allows it.

### Reporting periods

1. Open `RPMO` › `Period Management`.
2. Open or close the support part of a period for your team, and decide on late access requests. See [[period-management]].

## What happens next

Approved items move on to the next step, and the person who asked is notified. Rejected items return to the person with your reason.

## Common questions

**A ticket is stuck at `Pending Head`.** It is waiting for you. Open it and decide.

**I cannot see a button described here.** It is not part of your role. Ask your administrator.

## Related topics

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/calendar/timesheets.blade.php, resources/views/rpmo/periods/index.blade.php, resources/views/reporting/reporting.blade.php -->
