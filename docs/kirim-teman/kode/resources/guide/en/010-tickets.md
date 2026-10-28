---
key: tickets
chapter: work-service
order: 10
title: Tickets
summary: Find, create and work on support tickets, from the list to the ticket page.
requires: [tickets.inbox]
updated: 2026-10-27
---
The `Ticket` page (titled `Support Tickets`) lists every support request you are allowed to see. Open a ticket to reply, add notes, change its properties and follow its progress.

## Who uses this

Delivery Support users, Helpdesk, managers and heads, and administrators. The tabs, buttons and panels you see depend on your access. If something described here is missing, ask your administrator for access.

## Before you start

- Tickets that customers send by email or from the customer portal do not appear here until someone approves them in [[ticket-validation]].
- To create a ticket yourself, you need the `Create Ticket` button. It only appears if your role has that permission.

## Step by step

### Find a ticket

1. Open `Work & Service` › `Ticket`.
2. Pick a tab if you have more than one: `My Tickets`, `All Tickets`, `Unassigned` (shown as `Unassigned Ticket` for some roles) or `Ticket Modul`. `Ticket Modul` only appears if you are a module lead.
3. Select a status card at the top, such as `Open`, `In Progress`, `Wait Customer`, `Wait 3rd Party`, `Wait Confirm`, `Hold`, `Cancelled` or `Closed`, to filter the list. `Total` shows everything again.
4. Use the filter button in a column header to search by ticket number (`Tiket`), `Description`, date range (`Start Date`) or to sort.
5. Use `Prev` and `Next` below the cards to move between pages.

### Create a ticket

1. Click `Create Ticket`.
2. Select the `Customer`.
3. Enter the `Subject / Description`.
4. Choose the `Priority` (`Very High`, `High`, `Medium` or `Low`) and the `Ticket Type` (`Incident`, `Change Request`, `Service Request`, `EWA`, `RISE`, `Consult` or `Internal`).
5. Optionally choose a `Scale` (`Simple`, `Medium` or `Complex`), which is used for [[sla]]. Fill `To`, `CC`, `Additional Info`, a message and attachments if needed (up to 20 MB per file).
6. Click `Create Ticket` at the bottom of the form.

> [!NOTE]
> A ticket of type `Internal` is not shown to the customer.

### Work on a ticket

1. Click a row to open the ticket page.
2. Write in the reply box. Choose `Internal Note` for notes your team only sees, or `Send Reply` / `Send via Email` to answer the customer.
3. Open `Properties` on the right to change `Status`, `Priority`, `Scale`, `Ticket Type` and `PIC (In Charge)`, then click `Save All`. You see editable fields only if your role allows editing.
4. Use `Team Members` to add colleagues to the ticket.
5. If the ticket has no owner, click `Take This Ticket`, or use `Assign Ticket Lead` to give it to someone.
6. Use `Meeting` to schedule a meeting, and `Deliverable` › `View Documents` to see documents attached to the ticket.

### Propose mandays

Under `Mandays` you can propose `Customer Mandays` and `Resolution Days`. A proposal moves through the badges `Draft`, `Pending Review` (or `Pending Head`), `Approved`, and for resolution days `Rejected`. Reviewers use `Review Mandays Proposal` or `Review Resolution Days`. These buttons appear only for roles that propose or review.

## What happens next

- Status changes update the cards and the `Day on Close` count in the list.
- For `Incident`, `Change Request` and `Consult` tickets, the clock for [[sla]] pauses while the ticket waits on the customer, a third party or confirmation, or is on `Hold`.
- Users with the right access can click `Export` to download the filtered list as an Excel file.

## Common questions

**I cannot see a ticket I expected.** Check the active tab and status card, then clear any column filters. What you can see depends on your role.

**I do not see `Create Ticket`.** Your role does not include it. Ask your administrator.

## Related topics

- [[ticket-validation]]
- [[my-tasks-workload]]
- [[sla]]
- [[report-ticketing]]

<!-- sources: routes/web.php, app/Http/Controllers/TicketViewController.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/partials/sidebar.blade.php -->
