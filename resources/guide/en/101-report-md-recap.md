---
key: report-md-recap
chapter: reporting
order: 101
title: MD Recap report
summary: See approved mandays by employee, delivery and mode (onsite or remote), and export the recap.
requires: [reporting.md-recap]
updated: 2026-10-28
---
MD Recap adds up the approved timesheet days of each employee, grouped by delivery and by how the work was done: on site or remote.

## Who uses this

Anyone whose role has the `Reporting` › `MD Recap` menu in Menu Access, for example the Delivery Support Head and project heads. Access is decided only by Menu Access.

## Before you start

- Only approved timesheets are counted.
- A reporting period runs from the 21st of one month to the 20th of the next. Choosing a month and a year selects that period.

## Step by step

![The MD Recap report with the summary cards and the entries grouped by employee](images/guide/report-md-recap.png)

*Screenshot with sample data.*

1. Open `Reporting` › `MD Recap`. The label at the top shows the period that is open now.
2. Choose the `Month` and `Year`, or leave them on `All Months` and `All Years`.
3. Read the cards: `Total MD`, `Employees` (with approved timesheets), `OnSite MD` (field visits) and `Remote MD`.
4. Read the table. Each employee has a group, and under it the rows per delivery and mode (`OnSite` or `Remote`) with the `Mandays`. Use the `Name` and `Delivery` filters and the `Mode` list to narrow it.
5. Export what you need:
   - `Export MD Recap (Detailed)`: one row per entry.
   - `Export MD Recap (Summary)`: grouped by employee and mode.
   - `Export Resolution Days`: the list of tickets whose Resolution Days are not approved. This button appears only if your role also has the `Resolution Days` menu.

## What happens next

Exports use the filters you chose. Nothing changes in the data.

## Common questions

**A person is missing from the recap.** They have no approved timesheet in the period you chose.

**The month shows different dates than I expect.** A month here is a reporting period, from the 21st to the 20th.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/md-recap.blade.php -->
