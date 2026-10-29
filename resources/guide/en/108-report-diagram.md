---
key: report-diagram
chapter: reporting
order: 108
title: Diagram Report
summary: Ten charts of ticket data that follow one customer and period filter.
requires: [reporting.diagram-report]
updated: 2026-10-28
---
Diagram Report shows ticket analytics as ten charts that all follow the same customer and period filters.

## Who uses this

Anyone with the `Reporting` › `Diagram Report` menu, for example module leads, directors and administrators.

## Before you start

- The charts stay empty until you set both a start date and an end date in `Period`.
- The data is live: it reads the current tickets.

## Step by step

![The Diagram Report with a period selected, showing the first charts](images/guide/report-diagram.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Diagram Report`.
2. Choose a `Customer`, or keep `All Customers`.
3. Open `Period`, set `Start Date` and `End Date`, and select `Apply`. The chosen range appears on the button and under the filters. `Clear Filter` removes it.
4. Scroll through the ten charts:
   - Chart 1: ticket quantity per month, from the start to the current period.
   - Chart 2: tickets per module, by ticket kind.
   - Chart 3: ticket type per month, as a table.
   - Chart 4: ticket type per module.
   - Chart 5: ticket type by module, as a table with totals.
   - Chart 6: ticket count per module.
   - Chart 7: ticket count by type.
   - Chart 8: Change Request versus other tickets, by open or closed status.
   - Chart 9: Change Request and other tickets per month.
   - Chart 10: closed tickets per month.

## What happens next

All charts update together when you change the customer or the period.

## Common questions

**The charts say to select a date.** Set both `Start Date` and `End Date` in `Period`, then select `Apply`.

**A chart is empty after I chose a period.** No tickets match that customer and range. Widen the dates or choose `All Customers`.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/diagram-report.blade.php -->
