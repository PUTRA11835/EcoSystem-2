---
key: guide-project-head
chapter: reference
order: 85
title: Guide for Delivery Project Head
summary: What a project head runs and watches: delivery projects, consultant workload, reports, SLA and the project part of each reporting period.
requires: ["@role:Delivery Project Head"]
updated: 2026-10-28
---
This guide is for the Delivery Project Head role. You oversee delivery projects and the people working on them.

## Who uses this

Delivery Project Heads. Your menus include `Delivery` › `Project` and `Support`, `Consultant Workload`, several `Reporting` pages, `SLA` › `SLA Report`, `Master` (`Employee` and `Business Partner`) and `RPMO` › `Period Management`. Some points may not apply if your administrator changed your access.

## Before you start

- Project data includes revenue, cost and customer contacts. Use it only for your work and do not copy it into chat or email.
- Never put customer credentials or passwords into project notes or documents.

## Step by step

### Manage delivery projects

![The Project Delivery page with the summary cards and the list of projects](images/guide/delivery-project-list.png)

*Screenshot with sample data.*

1. Open `Delivery` › `Project`. The cards at the top count projects by status and schedule: `Total Projects`, `Open`, `In Process`, `Closed`, `On Track`, `At Risk`, `At Critical` and `Ending ≤ 30 days`. Click a card to filter the list.
2. Use `Add New` to create a project, or `Export Excel` to download the list. Select a row to open it.

![A project detail page with its tabs and the Create Folder, Close Project and Delete Project buttons](images/guide/delivery-project-detail.png)

*Screenshot with sample data.*

3. In the project, work through the tabs: `General`, `Delivery Info`, `Team`, `Documents`, `Issues`, `Risks`, `Location`, `Planning`, `WRICEF` and `Plan Cost`.
4. Use `Create Folder` to create the OneDrive folder for the project before you upload documents.
5. Record problems in `Issues` with `Add Issue`, and risks in `Risks` with `Add Risk`.
6. Use `Close Project` when the work is finished. `Delete Project` removes the project, so use it only for a project created by mistake.

### Watch the people

1. Open `Consultant Workload` before you plan work, to see who is busy.
2. Open `Reporting` › `Consultant Assignment` to see where each consultant is placed.

### Read the reports

Your `Reporting` menu holds `MD Validation`, `MD Recap`, `Collection Outlook`, `Collection Outlook (Support)`, `Ticketing Overview`, `Ticket by Modul` and `Consultant Assignment`. Check `SLA` › `SLA Report` for response and resolution times.

### Approve time

In `Calendar` › `Timesheets`, review the time your team logged and approve or reject it, if your role allows. See [[calendar-timesheet]].

### Reporting periods

1. Open `RPMO` › `Period Management`.
2. Open or close the `Project Domain` part of a period for your team, and decide late access requests at the first step. See [[period-management]].

## What happens next

Approved items move on and the person who asked is notified. A late access request you approve goes to RPMO for the final decision.

## Common questions

**I cannot see a project.** The list shows what your access allows. Ask your administrator if a project should be there.

**A button is missing in a project.** It is not part of your role.

**I cannot open the Support part of a period.** That part belongs to the Support Head.

## Related topics

- [[period-management]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/delivery/project/projects/index.blade.php, resources/views/delivery/project, resources/views/reporting, resources/views/rpmo/periods/index.blade.php -->
