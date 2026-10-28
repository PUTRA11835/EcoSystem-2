---
key: ticket-validation
chapter: work-service
order: 20
title: Ticket Validation
summary: Review tickets sent by customers and approve them into real tickets, or reject them with a reason.
requires: [tickets.staging]
updated: 2026-10-27
---
Tickets that customers send by email or from the customer portal wait on the `Ticket Validation` page (titled `Incoming Ticket Validation`). You check each one, classify it, and either approve it into a real ticket or reject it.

## Who uses this

Delivery Support users, Helpdesk, heads and administrators who are allowed to validate. The `Approve` and `Reject` buttons only appear if your role has those permissions.

## Before you start

- A number badge next to `Ticket Validation` in the sidebar shows how many tickets are waiting.
- Know the ticket type, priority and, if the customer has one, which delivery support should handle it.

## Step by step

### Review incoming tickets

1. Open `Work & Service` › `Ticket Validation`.
2. Check the three counters: `Pending`, `Approved` and `Rejected`.
3. Use the status filter at the top. It starts on `Pending Validation`; choose `All Status`, `Approved` or `Rejected` to see others.
4. Click `Refresh` to reload. The list also refreshes by itself every 30 seconds and the page checks for new emails every minute.
5. Click `Validate` on a pending row. For approved or rejected rows the button reads `Detail`.

The list shows `Customer / Sender`, `Description / Subject`, `Priority`, `Channel` (`Email` or `Web`), `Status` and `Submit Date`.

### Approve a ticket

1. In the detail window, read the message and any attachments.
2. Optionally read the `AI Ticket Analyzer` panel. It gives an overview, suggested steps and suggested assignees. Click `Apply suggestion` to copy its suggestions into the form.
3. Under `Ticket Classification`, choose the `Type` and `Priority`. Both are required.
4. Choose a `Scale` if you want one.
5. Choose the `Delivery Support`. It is required when the customer has delivery supports registered; you can only pick one that belongs to that customer.
6. If the email went to a parent customer, choose the right customer in `For customer`.
7. Fill `Additional Info` (`Name`, `No HP`, `Module`, `Client`) if useful.
8. Click `Approve`.

### Reject a ticket

1. Click `Reject` in the detail window.
2. Enter the `Reason for Rejection`. It is required.
3. Click `Confirm Rejection`.

To review rejected tickets later, click `View Rejected` on the page. That page is titled `Rejected Tickets`; use its search box to find one and `Back to Ticket Validation` to return.

## What happens next

- Approving creates a ticket in [[tickets]] with status `Open`. The approved row then shows the new ticket number, and the customer is notified that the ticket was validated.
- Rejecting marks the row `Rejected` and notifies the customer with your reason.
- The response time in [[sla]] is measured from the moment the ticket arrived until it was validated.

## Common questions

**The Approve button is missing.** Your role may only view this page. Ask your administrator for access.

**It says delivery support is required.** The customer has delivery supports registered, so pick one from the list.

## Related topics

- [[tickets]]
- [[sla]]
- [[report-ticketing]]

<!-- sources: routes/web.php, app/Http/Controllers/StagingTicketController.php, resources/views/staging/index.blade.php, resources/views/staging/rejected.blade.php, app/Services/SlaService.php -->
