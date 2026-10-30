---
key: report-ticket-by-module
chapter: reporting
order: 104
title: Ticket by Modul report
summary: See every ticket grouped by module, including the tickets that have no module, and export the list.
requires: [reporting.ticket-by-module]
updated: 2026-10-28
---
Ticket by Modul groups all tickets under the module they belong to. It helps module leads and heads see where the work sits.

## Who uses this

Anyone with the `Reporting` › `Ticket by Modul` menu.

## Before you start

- Each ticket is shown under its main module.
- Tickets without a module are collected in a group called `No Modul Assign`, which is always last.

## Step by step

![The Ticket by Modul report with the ticket groups and the filters](images/guide/report-ticket-by-module.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Ticket by Modul`. The line under the filters states how many modules and tickets are shown.
2. Narrow the list with the filters: `No Tiket`, `Description`, `Status`, `Ticket Lead` and `Modul`.
3. Use `Expand All` and `Collapse All` to open or close every group, or select a group to open it.
4. Read each group's table: `No Tiket`, `Description`, `Status`, `Ticket Lead`, `Modul`, `Created At` and `Day Not Close`. Select a ticket number to open it.
5. Select `Export` to download the list.

## What happens next

The export contains the tickets as filtered at that moment.

## Common questions

**Most tickets are under `No Modul Assign`.** Their module was never filled in. Ask the ticket lead to set it.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/ticket-by-module.blade.php -->
