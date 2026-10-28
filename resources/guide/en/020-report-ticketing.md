---
key: report-ticketing
chapter: reporting
order: 20
title: Ticket and timesheet reports
summary: What each Support report shows and how to read it.
requires: [reporting.ticketing-overview, reporting.ticket-by-module, reporting.resolution-days, reporting.validation, reporting.md-recap, reporting.log-shifting]
updated: 2026-10-27
---
The `Support Reports` group covers tickets, SLA messages and the mandays logged in timesheets. This topic explains the purpose of each report. For shared steps such as filters and exports, see [[reports-overview]].

## Who uses this

Delivery and support managers, heads, helpdesk and finance. You see only the reports your role may open.

## The reports

### Ticketing Overview

Shows `Customer Tickets`: one row per customer with `Total Mandays` and the number of tickets by `Ticket Status` (`Open`, `In Process`, `Close`, `Other`, `Wait Close`). Use the search box to find a customer. Open a customer to see its tickets, which you can filter by ticket number, description, status and lead, and sort by `Create Date` or `Day Not Close`.

### Ticket by Module

The page is titled `Ticket by Modul`. It groups tickets by module in cards. Filter by ticket number, description, `Status`, `Ticket Lead` and `Modul`, then use `Expand All` or `Collapse All`. The columns include `Created At` and `Day not Close`. Click `Export` for an Excel file, and `Clear Filters` to reset.

### Resolution Days

Lists tickets whose resolution days are not yet approved. The cards `Total Unapproved`, `None` (never proposed) and `Pending Head` (awaiting approval) filter the table. Click a row to open `Review Resolution Days`, edit `Approved Days` and `Approve Add.`, and click `Save Approval`. `Open Ticket` takes you to the ticket. Reviewing needs the right access.

### MD Validation

Titled `MD Validation` with the subtitle `Timesheet Report`. It compares the mandays consumed in timesheets with the quota. The cards show `Total Entries`, `Match` (consumed equals quota), `Less` (below quota) and `Over` (above quota); click one to filter. Other filters are `Employee`, `Date`, `Ticket`, `Customer`, `MD Status` and `Approval` (`Approved`, `Submitted` or `Draft`). The columns `MD Input`, `Quota MD` and `Remain` show the numbers. Click `Export MD` to download.

### MD Recap

Shows approved mandays per employee and mode. Choose a month and year (or `All Months` and `All Years`). The cards give `Total MD`, `Employees`, `OnSite MD` and `Remote MD`. Filter by `Name` and `Mode` (`OnSite` or `Remote`). Use `Export MD Recap`, or `Export Resolution Days` for the resolution days file.

### Log Shifting

Titled `Log Shifting`, with the subtitle that it summarises SLA messages per ticket. Filter by ticket number, description, `PIC` and `Created At`. Open a ticket to see each message with `Date`, `Time`, `SLA Message` and `PIC`.

## What happens next

Figures change as tickets are updated and timesheets are approved. Reload the report to see the latest numbers.

## Common questions

**`MD Validation` shows `Over` for a ticket.** More mandays were logged than the quota allows. Check the entries with the ticket lead.

**`MD Recap` is empty.** Only approved timesheets count, so check the month and year.

## Related topics

- [[reports-overview]]
- [[report-delivery]]
- [[tickets]]
- [[sla]]

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/ticketing-overview.blade.php, resources/views/reporting/ticket-by-module.blade.php, resources/views/reporting/resolution-days.blade.php, resources/views/reporting/reporting.blade.php, resources/views/reporting/md-recap.blade.php, resources/views/reporting/log-shifting.blade.php -->
