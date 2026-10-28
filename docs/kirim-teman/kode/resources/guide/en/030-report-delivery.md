---
key: report-delivery
chapter: reporting
order: 30
title: Billing, assignment and chart reports
summary: Read Collection Outlook, Consultant Assignment and Diagram Report.
requires: [reporting.collection-outlook, reporting.collection-outlook-support, reporting.consultant-assignment, reporting.diagram-report]
updated: 2026-10-27
---
These reports follow the money and the people on delivery work, and give a visual view of tickets. For filters and exports in general, see [[reports-overview]].

## Who uses this

Finance, account executives, delivery heads and managers. You see only the reports your role may open.

## The reports

### Collection Outlook

Under `Project Reports`. It shows the planned billing based on each project's Term Of Payment, by project and month.

1. Open `Reporting` › `Project Reports` › `Collection Outlook`.
2. Set `From` and `To` months, and optionally the `Account Executive`, then click `Apply`.
3. Read the cards `Total Outlook`, `Paid`, `Unpaid` and `Payment Terms`. Paid amounts are highlighted in green and unpaid amounts in red.
4. Read the table, with the columns `Customer`, `Project Name`, `TOP No.` and one column per month.
5. Open a billing entry to see `Term Of Payment Details`: the term information, the invoice dates and number, and the billing amount.
6. To record payment, click `Edit Status`, choose `Open`, `Paid` or `Delay`, fill `Paid Date` and `Invoice Number` (marked with an asterisk), and click `Save Changes`. Only people with the `Edit Payment Status` right see the edit button.
7. Click `Export Excel` to download.

The amounts and percentages of each term are managed in [[delivery]], not here.

### Collection Outlook (Support)

Under `Support Reports`. It works the same way but for support contracts, with `Support Name` instead of `Project Name`, and a `Type` filter in place of `Account Executive`.

### Consultant Assignment

Under `Project Reports`. It lists consultants assigned to delivery projects, taken from the `Team` of every project.

1. Open `Consultant Assignment`.
2. Read the cards: `Assignments`, `Consultants`, `Projects`, `Active`, `Upcoming`, `Ended`, `Internal`, `External / Vendor`, `Planned MD` and `Actual MD`. Click a card to filter.
3. Filter by `Consultant`, `ECI`, `Position`, `Project`, `IO Number`, `Customer`, `Project Category`, `Module`, `Project Role`, `Employee Type`, `Vendor` and `Period`. Click `Reset Filters` to clear.
4. Click `Expand All` to open every consultant's assignments, and use `Prev` and `Next` for more pages.
5. Click `Export` to download.

### Diagram Report

A page of ten charts about tickets, such as tickets per module, ticket types per month, change requests against other tickets, and closed tickets by month.

1. Open `Reporting` › `Diagram Report`.
2. Choose a `Customer` and a `Period` (`Start Date` and `End Date`), then click `Apply`.
3. Scroll through the charts, `Chart 1` to `Chart 10`. Click `Clear Filter` to return to all data.

## What happens next

The outlook reflects each term's status as soon as it is saved. Assignment figures follow changes in project teams.

## Common questions

**I cannot edit the payment status.** That right is given separately. Ask your administrator.

**A project is missing from `Collection Outlook`.** It only lists projects with a Term Of Payment inside the chosen month range.

## Related topics

- [[reports-overview]]
- [[report-ticketing]]
- [[delivery]]

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/collection-outlook.blade.php, resources/views/reporting/collection-outlook-support.blade.php, resources/views/reporting/consultant-assignment.blade.php, resources/views/reporting/diagram-report.blade.php -->
