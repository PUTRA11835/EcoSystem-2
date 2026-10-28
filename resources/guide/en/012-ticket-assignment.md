---
key: ticket-assignment
chapter: work-service
order: 12
title: Assigning tickets and managing the team
summary: Take a ticket, assign a ticket lead, add team members and link the ticket to one Delivery Support.
requires: [ticket.assign-pic, ticket.assign-delivery-support, ui.ticket.manage-members, ticket.take]
updated: 2026-10-28
---
Every ticket needs an owner. This topic shows how a ticket gets its ticket lead and team, and how it is tied to a Delivery Support.

## Who uses this

Helpdesk and heads who assign tickets, and consultants who take tickets themselves. Each button appears only if your role allows it.

## Before you start

- Open `Work & Service` › `Ticket`.
- Only people who are allowed to act as ticket lead or ticket member are offered in the lists. This is set by your administrator, and blocked accounts are never offered.

## Step by step

### Find tickets without an owner

1. Open the `Unassigned` tab (it appears as `Unassigned Ticket` for some roles).
2. Open a ticket that suits you or the team.

### Take a ticket yourself

1. Open the ticket.
2. Select `Take This Ticket`.

If the ticket is unclear or outside your skills, leave an `Internal Note` for Helpdesk instead of leaving it untouched.

### Assign a ticket lead

![A ticket page with the buttons Change Ticket Lead and Assign to Delivery Support in the right-hand panel](images/guide/ticket-detail.png)

*Screenshot with sample data.*

1. Open the ticket and select `Assign Ticket Lead`. When the ticket already has a lead, the button reads `Change Ticket Lead`.
2. Choose the person and confirm.

### Manage the team

1. Open `Team Members` on the ticket.
2. Add the colleagues who should work on it, or remove those who no longer should.
3. Save the change.

### Link the ticket to a Delivery Support

1. Select `Assign to Delivery Support`.
2. Choose the right Delivery Support of the customer.

A ticket belongs to **one** Delivery Support. If it was linked wrongly, remove the link first, then link the correct one.

> [!WARNING]
> `Hide Ticket` and `Delete Ticket` are only for some roles and affect everyone who sees the ticket. Use them only when you are sure you are allowed to.

## What happens next

The ticket appears in the `My Tickets` tab of its ticket lead and team members, and in `My Tasks`. Mandays proposals use the skills of the ticket lead and members, so keep the team accurate.

## Common questions

**A person is missing from the list.** They may not be allowed to act as ticket lead or member, or their account is blocked. Ask your administrator.

**I cannot change the Delivery Support.** Your role may not include `Assign to Delivery Support`. Ask Helpdesk or your head.

<!-- sources: routes/web.php, app/Http/Controllers/TicketController.php, resources/views/ticket/index.blade.php, resources/views/ticket/show.blade.php -->
