---
key: customer-md
chapter: reporting
order: 121
title: Customer MD report
summary: See every Change Request and every ticket with a Customer Mandays proposal in one list, and export it.
requires: [reporting.customer-md]
updated: 2026-10-28
---
Customer MD lists the tickets that carry customer mandays: all tickets of type Change Request and all tickets with an active Customer Mandays proposal.

## Who uses this

Delivery operation leaders, project and support heads, and administrators whose access includes `Reporting` › `Customer MD`.

## Before you start

- The report shows data of all customers you may see. Do not copy it into chat or email.
- It reads ticket data. To change a proposal, open the ticket itself.

## Step by step

1. Open `Reporting` › `Customer MD`.

![The Customer MD report with the list of tickets](images/guide/customer-md.png)

*Screenshot with sample data.*

2. Read the columns: `No Tiket`, `Description`, `Type`, `Customer`, `Delivery`, `Ticket Lead`, `Customer MD Status`, `Customer Mandays` and `Created At`.
3. Narrow the list with the filter and sort controls in the column headers, for example by customer, delivery or status.
4. Use `Show` to choose how many rows appear per page, and the page buttons to move on.
5. Select a ticket number to open the ticket.
6. Select `Export` to download the list as a spreadsheet.

## What happens next

The export contains the filtered view you see at that moment. Nothing in the ticket changes.

## Common questions

**`Customer MD Status` shows `None`.** The ticket is a Change Request that has no Customer Mandays proposal yet.

**A ticket I expect is missing.** It must be a Change Request or have an active Customer Mandays proposal. Check the filters too.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/customer-md.blade.php -->
