---
key: guide-rpmo-head
chapter: reference
order: 83
title: Guide for Delivery RPMO Head
summary: The monthly routine of the RPMO Head: create and open the period, follow the teams, decide late access and close the period.
requires: ["@role:Delivery RPMO Head"]
updated: 2026-10-28
---
This guide is for the Delivery RPMO Head role. Your main job in EcoSystem is to run the monthly reporting period so that every team can log its timesheets on time.

## Who uses this

People who hold the Delivery RPMO Head role. If you also hold other roles, you see the guides of those roles too.

## Before you start

- Everything below happens in `RPMO` › `Period Management`. The full instructions are in [[period-management]].
- Your decisions affect what other people can submit. Read each request before you decide and write a clear note.
- Employee data and timesheets you see here are for your work only. Do not copy them into chat or email.

## Step by step

### Each month

1. **Create the next period** with `New Period`. Check the `Start Date` and `End Date`, which normally run from the 21st to the 20th.
2. **Open it globally** with `Open Period Globally`.
3. **Let the Heads open their part.** The Project Head and the Support Head open the `Project Domain` and `Support Domain` themselves, so their teams can log timesheets.
4. **Decide late access requests** in `Exception Requests`. A request reaches you after the Head has approved it and shows `Waiting for RPMO Approval`. Set the `Access Deadline`, write the `RPMO Notes`, then approve or reject.
5. **Close the period globally** with `Close Period Globally` once both the Project and the Support parts are closed.

### When something is urgent

- Use `Force Close Project` or `Force Close Support` to close a team's part that cannot wait. This cannot be undone.
- Use `Audit Log` to see who opened or closed what and when.

## What happens next

When you open a period, the teams can log timesheets for it. When you close it, nobody can add timesheets to it unless a late access request has been approved. The approvals in `Calendar` › `Timesheets` then continue with the Heads.

## Common questions

**Why can I not open the Project or Support part?** Those buttons belong to the Project Head and the Support Head. You open the period globally, and you can force close a part in urgent cases.

**`Close Period Globally` is grey.** Both parts must be closed first.

## Related topics

- [[period-management]]

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, resources/views/rpmo/periods/index.blade.php -->
