---
key: report-resolution-days
chapter: reporting
order: 102
title: Resolution Days report
summary: List the tickets whose resolution days were never proposed or still wait for Head approval.
requires: [reporting.resolution-days]
updated: 2026-10-28
---
Resolution Days is a to-do list for the Head: every ticket in the company whose resolution days are not approved yet.

## Who uses this

Anyone whose role has the `Reporting` › `Resolution Days` menu in Menu Access, for example the Delivery Support Head. Access is decided only by Menu Access. The page shows tickets of the whole company, so give the menu with care.

## Before you start

- Hidden tickets are left out.
- A ticket appears here while its resolution days are `None` (never proposed) or `Pending Head` (proposed, waiting for approval).

## Step by step

![The Resolution Days report with the summary cards and the list of tickets](images/guide/report-resolution-days.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Resolution Days`.
2. Read the cards: `Total Unapproved` (None plus Pending Head), `None` (never proposed) and `Pending Head` (awaiting approval).
3. Read the table: `Ticket`, `Description`, `Customer`, `PIC`, `Status`, `Proposed By`, `Proposed MD` and `Proposed At`. Pending Head tickets come first, newest proposal on top.
4. Filter by ticket, customer or PIC with the search boxes in the headers.
5. Select a row to open `Review Resolution Days`. It shows the proposal per person: `Days`, additional days (`Add.`), notes, and the approved figures, with totals.
6. Approve or reject on the ticket itself, in its `Mandays` section.

## What happens next

When the Head approves a proposal, the ticket leaves this list. Its days become the quota that MD Validation compares with timesheets.

## Common questions

**A ticket stays at `None`.** Nobody proposed resolution days for it yet. Ask the ticket lead to propose them.

**The list is long.** Every ticket without approved days is here. Filter by customer or PIC and work through it.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/resolution-days.blade.php -->
