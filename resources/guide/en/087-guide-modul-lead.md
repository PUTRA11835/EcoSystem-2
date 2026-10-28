---
key: guide-modul-lead
chapter: reference
order: 87
title: Guide for Modul Lead
summary: What a module lead does: follow the tickets of your module, keep the team moving, and report weekly and by chart.
requires: ["@role:Modul Lead"]
updated: 2026-10-28
---
This guide is for the Modul Lead role. You lead one or more SAP or support modules and keep their tickets moving.

## Who uses this

Employees who lead a module. Your menus include `Ticket`, `My Tasks`, `Consultant Workload`, `Calendar` (`Events` and `Timesheets`), `Reporting` (`Log Shifting`, `Diagram Report` and `Weekly Consolidation`) and `Master` › `Employee`.

## Before you start

- Some pages show only the modules you lead. For example, in `Weekly Consolidation` you can work only with your own modules.
- Tickets can hold customer credential information. Never copy it into chat, notes, email or other documents.

## Step by step

### Every day

1. Open `My Tasks` for the tickets where you are the lead.
2. Open `Ticket` and follow the tickets of your module. Reply to the customer or leave an `Internal Note` when there is progress.
3. Open `Consultant Workload` to see who in your module has room for more work.
4. Log your own hours in `Calendar` › `Timesheets`. See [[calendar-timesheet]].

### Every week

1. Open `Reporting` › `Weekly Consolidation`, pick your module and create the weekly recon. See [[weekly-consolidation]].
2. Add a short note per ticket so the recon explains what is happening, then use `Export Excel` to share it.

### Study the trend

1. Open `Reporting` › `Diagram Report`. It shows ten charts of ticket data.
2. Use the `Customer` and `Period` filters at the top. The charts stay empty until you set a start date and an end date in `Period`.

### Shift notes

`Reporting` › `Log Shifting` lists the SLA notes between shifts. Pick a date range, select `Tampilkan`, and `Export Excel` if you need it.

## What happens next

Your weekly recon becomes a saved record that you and others with access can reopen and export. Charts update from live ticket data.

## Common questions

**I cannot choose some modules in `Weekly Consolidation`.** You can choose only modules you lead. Ask your administrator if one is missing.

**The `Diagram Report` charts say to select a date.** Set both dates in `Period`.

## Related topics

- [[weekly-consolidation]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, app/Http/Controllers/WeeklyConsolidationController.php, resources/views/reporting/diagram-report.blade.php, resources/views/reporting/log-shifting.blade.php, resources/views/ticket/task.blade.php -->
