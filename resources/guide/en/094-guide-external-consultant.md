---
key: guide-external-consultant
chapter: reference
order: 94
title: Guide for External Consultant
summary: The routine of an external consultant: work your tickets, propose mandays and log your time.
requires: ["@role:External Consultant"]
updated: 2026-10-28
---
This guide is for the External Consultant role, for consultants who work on customer tickets without being part of the permanent team.

## Who uses this

External consultants. Your menus are `Home`, `AI Research`, `Ticket` and `Calendar` (`Events` and `Timesheets`), plus `Settings` and `User Guide`.

## Before you start

- A ticket can show customer credential information. Use it only for the work and never copy it into chat, notes, email or other documents. Do not put it into the AI tools either.
- Your menus are fewer than those of staff. If a button is missing, it is not part of your role.

## Step by step

### Every day

1. Open `Ticket`. The cards at the top count tickets by status, and `Reset Filter` clears your filters. Pick a ticket to open it.
2. Reply to the customer or write an `Internal Note` when there is progress. An internal note is seen only by the team, and a reply is seen by the customer, so check which one you use.
3. Keep the status true to the work, for example `Wait Customer` while you wait for an answer.
4. `AI Summarize` and the AI research button on a ticket can help with a long conversation.

### Propose mandays

1. Open the `Mandays` section of the ticket and choose `Propose Mandays` or `Propose Resolution Days`.
2. Save the draft, check it and submit it. See [[ticket-mandays]].

### Log your time

![The Timesheets page with the entries and their status](images/guide/timesheets.png)

*Screenshot with sample data.*

1. Open `Calendar` › `Timesheets`. Select `Create Timesheet` to add an entry.
2. Use the filter `All`, `Project`, `Support` or `Office` to look at one kind of work.
3. If the period is closed, use `Request Late Access`. See [[calendar-timesheet]].

## What happens next

Timesheets you submit go to an approver. Approved time feeds the reports your managers read. Mandays proposals go to Helpdesk or the head for review.

## Common questions

**A ticket is missing from my list.** You see the tickets your access allows. Ask the ticket lead to add you as a member.

**I cannot create a ticket or assign one.** Those actions belong to Helpdesk and heads.

## Related topics

- [[ticket-mandays]]
- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php, resources/views/calendar/timesheets.blade.php -->
