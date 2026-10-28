---
key: weekly-consolidation
chapter: reporting
order: 120
title: Weekly Consolidation
summary: Save a weekly recon of the open tickets of one or more modules, add notes per ticket and export it to Excel.
requires: [reporting.weekly-consolidation]
updated: 2026-10-28
---
Weekly Consolidation turns the open tickets of a module into a saved weekly recon that you can refresh, annotate and export.

## Who uses this

Module leads, Helpdesk and heads. A module lead can work only with the modules they lead. Helpdesk, heads and administrators can use all modules. Combining several modules, and editing or deleting a saved recon, depend on the permissions your administrator gave your role.

## Before you start

- A recon belongs to a period. Decide the start and end dates first, for example Monday to Sunday.
- A recon lists tickets of the chosen module or modules. It does not include tickets that do not match.

## Step by step

### Create a recon

1. Open `Reporting` › `Weekly Consolidation`.
2. Under `Module`, select your module (or `All Modules` if you may). Then select `View Tickets` to preview the open tickets. Nothing is saved yet.
3. In the yellow bar, set `Period Start` and `Period End`. Add a `Period Label` if you like, for example `Week 43, October 2026`.
4. Select `Generate Recon`. The recon is saved and the green bar confirms it.

![A saved recon with the green action bar and the ticket table](images/guide/weekly-consolidation.png)

*Screenshot with sample data.*

### Work with a saved recon

1. Read the table: `Last Update`, `Ticket`, `Description`, `Start Date`, `Type`, `Status`, `Module`, `Lead & Member`, `PIC`, `Progress`, `Deliverable` and `Notes`.
2. Select a ticket row to read its details, and add a note in `Notes` to explain the situation.
3. Select `Refresh` to bring in the latest ticket changes.
4. Select `Export Excel` to download the recon, and `Close` to leave it.
5. If your role allows it, use `Edit` to change the module or period and `Delete` to remove a recon.

### Find an old recon

`Recon History` at the top lists every recon with its module, period, ticket count and who generated it. Select `Open` to continue working or `Export` to download it.

## What happens next

The recon stays saved with its notes. Opening it again shows the current ticket data after you select `Refresh`.

## Common questions

**I cannot see my module.** You can choose only modules you lead. Ask your administrator if one is missing.

**`All Modules` is not offered.** Combining modules is a separate permission of your role.

**The numbers changed after a few days.** Select `Refresh` to update the recon, or create a new one.

<!-- sources: app/Http/Controllers/WeeklyConsolidationController.php, resources/views/reporting/weekly-consolidation.blade.php -->
