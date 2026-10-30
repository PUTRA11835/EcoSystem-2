---
key: guide-ds-user
chapter: reference
order: 80
title: Guide for Delivery Support User
summary: The daily routine of a support consultant: take tickets, work them, propose mandays and log time.
requires: ["@role:Delivery Support User"]
updated: 2026-10-28
---
This guide is for the Delivery Support User role, the consultants who work on customer tickets. It shows a simple daily routine and points to the detailed topics.

## Who uses this

Consultants with the Delivery Support User role. What you can do depends on your role: for example, you can take tickets and propose mandays, but you do not create tickets or assign others.

## Before you start

- Your menus follow your role. If a button below is missing, it is not part of your role. Ask Helpdesk or your head.
- You may see customer credential information on a ticket. Use it only for the work, and never copy it into chat, notes, email or other documents.

## Step by step

### Every day

1. Open `My Tasks` to see the work assigned to you. The cards at the top show your `Active Tickets`, `Alloc MD`, `Remaining`, `Workload` and `Load Score`.

![The My Tasks page with the summary cards and the list of tickets where you are the lead](images/guide/my-tasks.png)

*Screenshot with sample data.*


2. Open `Work & Service` › `Ticket` and the `My Tickets` tab. Reply to the customer or write an `Internal Note` when there is progress.
3. Update the progress of a ticket with `Edit Progress` in `My Tasks`, and say clearly what is done and what is next.
4. Log your hours in `Calendar` › `Timesheets` before you finish the day. See [[calendar-timesheet]].

### Take a new ticket

1. Open the `Unassigned` tab and pick a ticket that fits your skills.
2. Select `Take This Ticket`. See [[ticket-assignment]].

### Work on a ticket

- `Internal Note` is seen only by your team. A reply to the customer is seen by the customer, so check which one you are using before you send.
- Set the status to match reality, for example `Wait Customer` while you wait for the customer. A correct status keeps the SLA fair.
- Use `Team Members` to bring in a colleague who should help.
- `AI Summarize` and `Ask AI` can summarise a long conversation. Never put credentials into them. See [[ticket-ai-tools]].

### Propose mandays

1. Open the `Mandays` section and choose `Propose Mandays` or `Propose Resolution Days`.
2. Save the `Draft`, check it, then submit it.
3. Helpdesk reviews Customer Mandays and the head approves Resolution Days. See [[ticket-mandays]].

## What happens next

Your approved days feed your workload and the reports your managers read. Timesheets you submit go to your head for approval.

## Common questions

**I cannot create a ticket or assign one.** Those actions belong to Helpdesk and heads.

**A ticket I work on is missing from `My Tasks`.** Only tickets where you are the ticket lead or a team member appear. Ask the ticket lead to add you in `Team Members`.

## Related topics

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/ticket/task.blade.php, resources/views/calendar/timesheets.blade.php -->
