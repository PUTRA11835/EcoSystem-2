---
key: report-ticketing-overview
chapter: reporting
order: 103
title: Ticketing Overview report
summary: See each customer's tickets by status, with the customer's total mandays, and open the open tickets behind a number.
requires: [reporting.ticketing-overview]
updated: 2026-10-28
---
Ticketing Overview gives one row per customer, with how many tickets are open, in process, closed and in other states, next to the mandays the customer has bought.

## Who uses this

Anyone with the `Reporting` › `Ticketing Overview` menu, for example heads, helpdesk and directors.

## Before you start

- Tickets of type EWA and hidden tickets are not counted.
- `Total Mandays` is the sum of the mandays of all the customer's delivery projects.

## Step by step

![The Ticketing Overview report with one row per customer and the ticket counts by status](images/guide/report-ticketing-overview.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Ticketing Overview`.
2. Find a customer with the search box (`Cari nama customer...`).
3. Read each row: `Customer Name`, `Total Mandays` and the bars under `Ticket Status`: `Open`, `In Process`, `Close`, `Other` and `Wait Close`. The bar length compares the status with the others in that row.
4. Select the arrow in front of a customer to see its open tickets: `Tiket`, `Description`, `Status`, `Lead`, `Create Date` and `Day Not Close`. Select a ticket to open it.
5. Use the filters in the headers and `Clear sort` to reset the order.

## What happens next

The numbers follow the tickets live. Nothing in the data changes from this page.

## Common questions

**A customer has no open tickets.** The row then says there are no open tickets for that customer.

**A ticket is missing.** It may be an EWA ticket or a hidden ticket, which this report leaves out.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/ticketing-overview.blade.php -->
