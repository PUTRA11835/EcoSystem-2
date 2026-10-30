---
key: guide-helpdesk
chapter: reference
order: 86
title: Guide for Delivery Support Service Helpdesk
summary: The helpdesk routine: validate incoming tickets, route them, review customer mandays, tidy the ticket list and prepare shift and weekly reports.
requires: ["@role:Delivery Support Service Helpdesk"]
updated: 2026-10-28
---
This guide is for the Delivery Support Service Helpdesk role. You are the front door for customer requests: you check what comes in, send it to the right people and keep the ticket list in order.

## Who uses this

Helpdesk staff. Your menus include `Ticket`, `Ticket Validation`, `Consultant Workload`, `Delivery` › `Support`, `SLA` › `SLA Report`, `Management` › `Hidden Tickets` and several `Reporting` pages (`MD Validation`, `Ticketing Overview`, `Ticket by Modul`, `Log Shifting` and `Weekly Consolidation`).

## Before you start

- Tickets can hold customer credential information. Use it only for the work and never copy it into chat, notes, email or other documents.
- Your decisions decide who works on a customer request and what the customer is told. Read before you approve, and write a clear reason when you reject.

## Step by step

### Validate incoming tickets

New requests from customers wait in `Ticket Validation` until you decide. The number next to the menu shows how many are waiting. The full flow is in [[ticket-validation]].

1. Open `Ticket Validation`. The `Pending Validation` list shows requests with sender, subject, priority, channel and submit date. Use `View Rejected` to see the ones already rejected.
2. Select a request to read it. The top part shows `From`, `Date` and `Subject`, and the `AI Ticket Analyzer` can suggest a classification. Check its advice before you rely on it.

![The Incoming Ticket Details window with the classification fields and the Approve and Reject buttons](images/guide/ticket-validation.png)

*Screenshot with sample data.*

3. Fill `Ticket Classification`: `Type` and `Priority` are required, `Scale` is optional. Choose the `Delivery Support` the ticket belongs to. Add `Name`, `No HP` and `Module(s)` if they are missing.
4. Select `Approve` to turn it into a real ticket, or `Reject` and write a reason for the sender.

### Route and follow the ticket

1. Open `Ticket`, pick the ticket and assign a lead or team members. See [[ticket-assignment]].
2. Open `Consultant Workload` first to see who has room.
3. When a consultant proposes Customer Mandays, review and send it to the customer. See [[ticket-mandays]].

### Keep the ticket list tidy

1. To hide a ticket from the main list, use the hide button on the ticket and confirm. It is not deleted.
2. Open `Management` › `Hidden Tickets` to see hidden tickets. Select `Tampilkan` on a row to show it in the main list again.

### Prepare reports

1. `Log Shifting`: pick `Dari Tanggal` and `Sampai Tanggal` (and the hours if needed), or a quick range such as `Hari Ini` or `7 Hari Terakhir`. Select `Tampilkan` to load the SLA notes, then `Export Excel`.
2. `Weekly Consolidation`: save a weekly recon of open tickets per module. See [[weekly-consolidation]].
3. `Ticketing Overview`, `Ticket by Modul` and `MD Validation` show ticket and mandays figures. `SLA` › `SLA Report` shows response and resolution times.

## What happens next

An approved request becomes a ticket and the lead and team are notified. A rejected request returns to the sender with your reason.

## Common questions

**The number on `Ticket Validation` does not go down.** It counts requests still in `Pending Validation`. Approve or reject them.

**I cannot find a ticket in the main list.** It may be hidden. Check `Hidden Tickets`.

**The `Log Shifting` table is empty.** Pick a date range and select `Tampilkan` first.

## Related topics

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[weekly-consolidation]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/staging, resources/views/ticket/index.blade.php, resources/views/management/hidden-tickets/index.blade.php, resources/views/reporting/log-shifting.blade.php -->
