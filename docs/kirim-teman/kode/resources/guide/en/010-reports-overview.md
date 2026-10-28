---
key: reports-overview
chapter: reporting
order: 10
title: Reporting overview
summary: Which reports exist, how they are grouped, and how to use their filters and exports.
requires: [reporting]
updated: 2026-10-27
---
The `Reporting` section gathers read-only reports on delivery projects, support and tickets. All of them work in a similar way: pick a period or filter, read the figures, and export if you need a file.

## Who uses this

Managers, delivery heads, finance and administrators. You see only the reports your role may open.

## The reports

| Group in the sidebar | Report | What it shows |
|---|---|---|
| `Project Reports` | `Collection Outlook` | Planned billing by project and month |
| `Project Reports` | `Consultant Assignment` | Which consultants are assigned to which projects |
| `Support Reports` | `MD Validation` | Timesheet mandays compared with quota |
| `Support Reports` | `MD Recap` | Approved mandays per employee and mode |
| `Support Reports` | `Collection Outlook (Support)` | Planned billing for support contracts |
| `Support Reports` | `Ticketing Overview` | Ticket counts per customer by status |
| `Support Reports` | `Ticket by Module` | Tickets grouped by module |
| `Support Reports` | `Log Shifting` | SLA messages per ticket |
| `Support Reports` | `Resolution Days` | Tickets whose resolution days are not yet approved |
| Alone | `Diagram Report` | Ten charts about tickets |

Details are in [[report-ticketing]] (ticket and timesheet reports) and [[report-delivery]] (billing, assignment and charts).

## Step by step

### Open and filter a report

1. Open `Reporting` in the sidebar and expand `Project Reports` or `Support Reports`.
2. Select the report. Wait for `Loading` to finish.
3. Set the filters. Common ones are a date range (`From` and `To`), a month and year, a customer, or a status. When a range is shown, `To` must be on or after `From`.
4. Use the summary cards at the top. In several reports, clicking a card filters the table below to that group.
5. Use the filter button on a column header to search or sort that column, and `Clear` or `Reset Filters` to remove filters.

### Export a report

1. Set the filters first. Check the period and filters on screen before you export.
2. Click the export button of that report, for example `Export MD`, `Export MD Recap`, `Export Excel` or `Export`.
3. Open the downloaded Excel file.

> [!TIP]
> If the table says there is no data, widen the date range or clear the status filter before assuming the report is empty.

## What happens next

Reports always show current data. Reopen or refresh the page to see changes made by colleagues.

## Common questions

**A report I need is not in the sidebar.** Each report has its own access. Ask your administrator.

**Can I change data in a report?** Mostly no. The exception is the payment status in the two `Collection Outlook` reports, which people with the right access can edit.

## Related topics

- [[report-ticketing]]
- [[report-delivery]]
- [[tickets]]
- [[sla]]

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/partials/sidebar.blade.php, resources/views/reporting/ -->
