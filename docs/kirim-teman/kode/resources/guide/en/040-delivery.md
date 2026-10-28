---
key: delivery
chapter: work-service
order: 40
title: Delivery (Project & Support)
summary: Manage delivery projects and support contracts, their planning, issues, risks and costs.
requires: [delivery, delivery.project, delivery.support]
updated: 2026-10-27
---
`Delivery` has two parts: `Project`, for implementation projects, and `Support`, for ongoing support contracts. Each has a list, a detail page and a planning view.

## Who uses this

Project owners, delivery heads, support managers and administrators. Which tabs, sections and buttons appear depends on your access, and each section has separate view, edit and manage rights.

## Before you start

Have the customer, IO number, contract dates and project owner ready before you add a new item.

## Step by step

### Browse projects

1. Open `Work & Service` › `Delivery` › `Project`. The page is titled `Project Delivery`.
2. Read the `Summary` cards: `Total Projects`, `Open`, `In Process`, `Closed`, `On Track`, `At Risk`, `At Critical` and `Ending ≤ 30 days`. Click a card to filter the list.
3. Type in the search box to find a project by IO number, customer, type, name, AE or owner.
4. Use the tabs `Project`, `Issues` and `Planning` at the top to move between the list, all project issues and project planning.

### Add a project

1. Click `Add New`.
2. Select the `Customer/Client` and `Project Owner`, and the `Project Type`.
3. Choose the `High Level Risk` (`Low`, `Moderate` or `High`).
4. Enter the `Contract Start Date`, `Contract End Date`, `IO/Number Order`, `Project Name` and `Description`.
5. Click `Save Project`. The `Add New` button is shown only if you may add projects.

### Work in a project

1. Click a project to open `Project Detail`.
2. Use the section tabs: `General`, `Delivery Info`, `Team`, `Documents`, `Issues`, `Risks`, `Location`, `Planning`, `WRICEF` and `Plan Cost`. You see only the sections you may view.
3. Edit a section where the edit option is available, for example to add a team member, an issue, a risk or a cost item.
4. Open the `Planning` section to build phases and activities. Switch between `Table View`, `Gantt Chart` and `S-Curve`, and use `Export` for PDF or Excel files.

### Close and reopen a project

1. Click `Close Project` at the top of the detail page and confirm.
2. The project becomes read-only until it is reopened.
3. To change it again, click `Reopen Project`.

> [!NOTE]
> These two buttons appear only for people with the right to close projects.

### Manage support contracts

1. Open `Work & Service` › `Delivery` › `Support`. The page is titled `Delivery Support`.
2. Use the summary cards (`Total Delivery Support`, `In Progress`, `Completed`, `Not Started`, `Ending ≤ 30 days`, `Past End Date`, `Upcoming`, `No Timeline`) and the column filters to narrow the list.
3. Click `Add Delivery Support` to create one, if you have access.
4. Click an item to open `Support Details` with the tabs `General`, `Approval`, `Financial`, `Team`, `Customer PIC`, `Activities`, `SLA` and `Plan Cost`.
5. Click `Open Planning` to plan phases and activities in `Table`, `Gantt` or `S-Curve` views. `Configure Phases` sets the phases to show.
6. Use `Documents` and `Deliverable Folder` for files, and `Updates & Notes` for progress notes.

## What happens next

- A project with `Closed` status cannot be edited until someone reopens it.
- The `SLA` tab of a support item links to the service levels described in [[sla]].
- Billing plans from projects and supports feed [[report-delivery]].

## Common questions

**I cannot edit a project.** It may be closed, or your role may have view access only.

**A section tab is missing.** Each section needs its own view permission. Ask your administrator.

## Related topics

- [[sla]]
- [[rpmo]]
- [[report-delivery]]
- [[tickets]]

<!-- sources: routes/web.php, routes/delivery-support.php, resources/views/delivery/project/projects/index.blade.php, resources/views/delivery/project/projects/show.blade.php, resources/views/delivery/project/projects/create.blade.php, resources/views/delivery/support/list/index.blade.php, resources/views/delivery/support/list/show.blade.php, resources/views/delivery/support/planning/index.blade.php -->
