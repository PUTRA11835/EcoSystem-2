---
key: guide-operation-director
chapter: reference
order: 91
title: Guide for Delivery Operation Director
summary: What a delivery operation director follows: projects, support, consultants, billing, SLA and the management reports.
requires: ["@role:Delivery Operation Director"]
updated: 2026-10-28
---
This guide is for the Delivery Operation Director role. You oversee delivery as a whole: projects, support, people and results.

## Who uses this

Delivery Operation Directors. You have the widest set of menus among the delivery roles, including `Ticket`, `Consultant Workload`, `Calendar`, many `Reporting` pages, `Master` (`Employee` and `Business Partner`), `Delivery` (`Project` and `Support`), `SLA` and `RPMO`.

## Before you start

- You can see revenue, cost, contracts and personal data. Use it only for your work and never copy it into chat or email.
- Closing or deleting a project affects many people. Read the details first.

## Step by step

### Watch the delivery

1. Open `Delivery` › `Project` and `Delivery` › `Support`. The cards show how many items are open, at risk or ending soon, and a click filters the list. You can create items with `Add New`, and close or delete a project from its page.
2. Open `Ticket` for the support picture and `SLA` › `SLA Report` for response and resolution times.
3. Open `Consultant Workload` to see who is busy.

### Read the reports

1. `Reporting` › `Ticketing Overview`, `Ticket by Modul` and `MD Validation` show ticket and mandays figures. `Diagram Report` shows ticket data as charts. `Log Shifting` lists SLA notes between shifts.
2. `Reporting` › `Collection Outlook` and `Collection Outlook (Support)` show expected billing per month.
3. `Reporting` › `Customer MD` lists change requests and customer mandays. See [[customer-md]].
4. `Reporting` › `Consultant Assignment` and `Resource Timeline` show where consultants are placed. See [[resource-timeline]].
5. `Reporting` › `Weekly Consolidation` holds the weekly recon of open tickets per module. See [[weekly-consolidation]].

### Time and people

Use `Calendar` for events and `Timesheets`. See [[calendar-timesheet]]. `Master` › `Employee` and `Business Partner` hold the master data.

### RPMO

`RPMO` shows the module page, which points to `Period Management`.

## What happens next

Decisions you take in projects and settings apply to everyone who works with those records. Report figures update from live data.

## Common questions

**A menu opens a page that says `Forbidden`.** Your role has the menu, but not the permission for that page. Ask your administrator, and tell them the menu name.

**I cannot find a report.** Only reports in your `Reporting` menu are part of your role.

## Related topics

- [[customer-md]]
- [[resource-timeline]]
- [[weekly-consolidation]]
- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/delivery, resources/views/reporting, resources/views/rpmo/rpmo.blade.php -->
