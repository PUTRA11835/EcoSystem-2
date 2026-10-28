---
key: guide-ds-manager
chapter: reference
order: 82
title: Guide for Delivery Support Manager
summary: Running support day to day: create and assign tickets, review mandays, follow SLA and manage Delivery Support.
requires: ["@role:Delivery Support Manager", "@role:Delivery Support Manager II"]
updated: 2026-10-28
---
This guide is for the Delivery Support Manager role. You run the support work day to day: who works on what, the quality of proposals and the health of the service.

## Who uses this

Delivery Support managers. Some actions, such as approving timesheets, belong to the head instead.

## Before you start

- Your menus follow your role. A button that is missing is not part of your role.
- Changing a ticket's status, priority or type, or removing a Delivery Support, affects reports and SLA. Make sure of the change first and leave an `Internal Note` with the reason.
- Customer credential information on a ticket must never be copied into chat, notes, email or other documents.

## Step by step

### A suggested routine

1. Open the `All Tickets` tab and look for tickets that are not moving or are close to their SLA limit.
2. Open `Ticket Validation` and handle incoming tickets: approve real requests, reject the rest with a reason.
3. Give owners to unassigned tickets. Check `Consultant Workload` first so the work is shared fairly. See [[ticket-assignment]].
4. Review proposals: `Review Mandays Proposal` for Customer Mandays and `Review Resolution Days` for the internal estimate. See [[ticket-mandays]].
5. Follow `SLA` › `SLA Report`, and open the SLA log of a ticket when you need to trace a gap.

### Create a ticket

![The ticket list with the status cards, the My Tickets and All Tickets tabs and the Create Ticket button](images/guide/ticket-list.png)

*Screenshot with sample data.*

1. Select `Create Ticket` in the ticket list. The form is titled `Create New Ticket`.

![The Create New Ticket form with customer, to, cc, subject, priority, ticket type, scale and additional info](images/guide/ticket-create-modal.png)

*Screenshot with sample data.*

2. Choose the `Customer`. `TO` and `CC` are optional.
3. Write the `Subject / Description`, then set the `Priority` and the `Ticket Type`. Set the `Scale` if it matters for SLA.
4. Under `Additional Info` you can add a contact `Name`, `No HP`, the `Module` and the `Client`. Add a `Message` and attachments if useful.
5. Select the create button at the bottom of the form. Never put passwords or credentials in the ticket.

### Manage Delivery Support

![The Delivery Support page with the summary cards and the list of support items](images/guide/delivery-support-list.png)

*Screenshot with sample data.*

1. Open `Delivery` › `Support`.
2. You can edit all sections: general information, approval, financial, team, customer PIC, activities, SLA, plan cost, documents and recons.
3. You can add a Delivery Support, remove one that is not used, and unlink a ticket that was linked wrongly. A ticket belongs to one Delivery Support, so link it again to the right one afterwards.

### Reports and calendar

1. Read `MD Validation`, `MD Recap`, `Diagram Report` and `Log Shifting` in `Reporting`.
2. Create events in `Calendar` › `Events`, for example for a team meeting.
3. Log your own time in `Calendar` › `Timesheets`. See [[calendar-timesheet]].

## What happens next

Decisions you make show in the ticket history and in the reports. Removing a Delivery Support or unlinking a ticket changes SLA and report figures.

## Common questions

**Who approves timesheets?** The head does. You can still log your own.

**A ticket is linked to the wrong Delivery Support.** Unlink it and link the right one. See [[ticket-assignment]].

## Related topics

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/staging/index.blade.php, resources/views/admin/sla/report.blade.php, resources/views/delivery/support/list/show.blade.php -->
