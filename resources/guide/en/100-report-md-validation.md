---
key: report-md-validation
chapter: reporting
order: 100
title: MD Validation report
summary: Compare the mandays consumed in timesheets with the approved quota, ticket by ticket and person by person.
requires: [reporting.validation]
updated: 2026-10-28
---
MD Validation shows, for every timesheet entry on a ticket, how many mandays were used against the quota that was approved. It tells you quickly where time is over or under what was agreed.

## Who uses this

Anyone with the `Reporting` › `MD Validation` menu. Roles with the `View All Employees` permission (by default administrators and the Delivery Support Head) see the entries of everybody. Everyone else sees only their own entries.

## Before you start

- The quota comes from the latest approved mandays of each ticket, per person. Without an approved quota the row shows a dash.
- The page includes entries that are `Draft`, `Submitted` or `Approved`. Only approved time counts as final in other reports.

## Step by step

![The MD Validation report with the summary cards and the entry table](images/guide/report-md-validation.png)

*Screenshot with sample data.*

1. Open `Reporting` › `MD Validation`. The label at the top right shows the reporting period that is open now, for example `October 2026 (Open)`.
2. Read the cards: `Total Entries`, `Match` (consumed equals quota), `Less` (consumed is below quota) and `Over` (consumed is above quota).
3. Read the table: `Employee`, `Date`, `Ticket`, `Customer`, `MD Input`, `Quota MD`, `Remain`, `MD Status` and `Approval`. `Remain` subtracts the consumed days from the quota in date order, oldest entry first, so it shows how much is left at that point.
4. Narrow the list with the date range (`From` and `Apply`) and the search boxes in the column headers. Select a ticket number to open the ticket.
5. Select `Export MD` to download the list as a spreadsheet, if your role has the export permission. The export follows the same scope as the page.

## What happens next

Nothing changes in the data. If an entry is `Over`, talk to the ticket lead or the Head: more mandays may need to be proposed on the ticket.

## Common questions

**I only see my own entries.** Seeing everybody needs the `MD Validation: View All Employees` permission. Ask your administrator.

**`Quota MD` shows a dash.** The ticket has no approved mandays for that person yet.

**There is no `Export MD` button.** Your role does not have the export permission. Ask your administrator.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/reporting.blade.php -->
