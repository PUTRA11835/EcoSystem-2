---
key: report-consultant-assignment
chapter: reporting
order: 107
title: Consultant Assignment report
summary: See which consultant is placed on which delivery project, for how long, and how far they are used.
requires: [reporting.consultant-assignment]
updated: 2026-10-28
---
Consultant Assignment collects the team members of every delivery project into one overview per consultant.

## Who uses this

Anyone with the `Reporting` › `Consultant Assignment` menu, for example project heads, administrators and directors.

## Before you start

- The data comes from the `Team` tab of each delivery project. To change an assignment, change it in the project.
- A person on several projects has one group with a row per assignment.

## Step by step

![The Consultant Assignment report with the summary cards and the list of consultants](images/guide/report-consultant-assignment.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Consultant Assignment`.
2. Read the cards: `Assignments`, `Consultants`, `Projects`, `Active`, `Upcoming`, `Ended`, `Internal`, `External / Vendor`, `Planned MD` and `Actual MD`.
3. Search by consultant, project or customer, or use the filters in the headers: consultant, position, project, IO number, customer, project category, module, project role, employee type and vendor.
4. Use the period filter (`From`, `To`) to show the assignments that overlap a range, and `Sort by start date` to order them. Select `Apply`.
5. Select a consultant, or `Expand All`, to see each assignment: `Duration`, `Status`, `Utilization` and `Notes`.
6. Select `Export` to download the list with the current filters, and `Reset Filters` to start over.

## What happens next

The export has exactly what the page shows with your filters. Nothing changes in the projects.

## Common questions

**A consultant is missing.** They are not a team member of any delivery project.

**An assignment shows `Inactive employee`.** The person is no longer active in the employee data.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/consultant-assignment.blade.php -->
