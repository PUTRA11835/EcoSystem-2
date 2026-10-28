---
key: my-tasks-workload
chapter: work-service
order: 30
title: My Tasks & Consultant Workload
summary: See the tickets assigned to you and how busy each consultant is.
requires: [ticket.my-tasks, ticket.consultant-workload]
updated: 2026-10-27
---
`My Tasks` lists the active tickets assigned to you. `Consultant Workload` shows how much work each consultant carries, so managers can balance assignments.

## Who uses this

Consultants and Delivery Support users use `My Tasks`. Managers, heads and coordinators use `Consultant Workload`. Each page appears in the sidebar only if you have access to it.

## Before you start

Workload is based on the mandays approved on tickets. Tickets without approved mandays do not add to the allocated days. See [[tickets]] for how mandays are proposed.

## Step by step

### Check your tasks

1. Open `Work & Service` › `My Tasks`.
2. Pick the `month` and `year` at the top to change the period.
3. Read the summary cards: `Active Tickets`, `Alloc MD`, `Remaining`, `Workload` and `Load Score`.
4. Type in the search box to find a ticket, subject or customer.
5. Click the `Status` column header to filter by status.
6. Check the table columns: `Ticket No.`, `Customer`, `Status`, `Priority`, `Module`, `Man Days`, `Workload (remain/alloc)`, `End Date` and `Progress`.

### Review consultant workload

1. Open `Work & Service` › `Consultant Workload`.
2. Click `Group Modul` to filter by module group, and `Reset` to clear it.
3. Use the column filters for `Consultant` (name or ECI), `Personnel Sub Area`, `Current Assignment` and `Module`.
4. Click `Expand All` or `Collapse All` to open or close every consultant's ticket list. You can also open one consultant at a time.
5. Click a column title such as `Tickets`, `Alloc Days`, `Remain`, `Workload` or `Load Score` to sort.

## How to read the numbers

| Item | Meaning |
|---|---|
| `Alloc Days` / `Alloc MD` | Mandays allocated to the consultant on active tickets |
| `Remain` / `Remaining` | Mandays still left to do |
| `Workload` | Remaining days divided by allocated days, shown as a bar |
| `Load Score` | Remaining days multiplied by (1 + 0.1 per ticket), so many small tickets weigh more |

## What happens next

The figures refresh whenever you open the page or change the month, year or filter. As mandays are used, the remaining days and the workload bar go down.

## Common questions

**A ticket I work on is not in `My Tasks`.** Only tickets assigned to you appear. Ask the ticket lead to add you as a team member on the ticket.

**Why does `Load Score` differ from `Workload`?** `Workload` compares remaining days with allocated days. `Load Score` also takes into account how many tickets are held.

## Related topics

- [[tickets]]
- [[ticket-validation]]
- [[sla]]

<!-- sources: routes/web.php, app/Http/Controllers/TaskController.php, app/Http/Controllers/ConsultantWorkloadController.php, resources/views/ticket/task.blade.php, resources/views/ticket/consultant-workload.blade.php -->
