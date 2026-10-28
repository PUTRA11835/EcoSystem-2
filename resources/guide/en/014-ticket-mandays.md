---
key: ticket-mandays
chapter: work-service
order: 14
title: Customer Mandays and Resolution Days
summary: The two estimates on a ticket, who approves each one and how they move from draft to approved.
requires: [ticket.propose-mandays-customer, ticket.propose-mandays-resolution, ticket.review-mandays, ticket.head-mandays, ticket.approve-resolution-days]
updated: 2026-10-28
---
A ticket that needs effort estimates carries two different proposals. They are easy to mix up, so keep them apart.

## Who uses this

Ticket leads and consultants who propose, Helpdesk who reviews Customer Mandays, and heads who approve Resolution Days. The buttons you see depend on your role.

## Before you start

- Open the ticket and find the `Mandays` section.
- The modules you can choose in a proposal come from the skills of the ticket lead and the team members, so check that the ticket has the right team first.

| | Customer Mandays | Resolution Days |
|---|---|---|
| What it is | Days of work the **customer** agrees to | The internal estimate to resolve the ticket |
| Prepared by | The person in charge of the ticket | The person in charge of the ticket |
| Approved by | The customer, after Helpdesk review | The head |

## Step by step

### Customer Mandays

![A ticket page with the Mandays section: Propose Mandays, Propose Resolution Days, Review Mandays Proposal and Review Resolution Days](images/guide/ticket-detail.png)

*Screenshot with sample data.*

1. Select `Propose Mandays` and fill in the details. Save it as a `Draft` first.
2. Check the numbers and submit it. It then waits for review.
3. Helpdesk uses `Review Mandays Proposal` to check or adjust it and sends it to the customer by chat and email.
4. The customer approves through the link they receive, and the proposal becomes `Approved`.
5. Helpdesk can cancel a proposal. A revision creates a new version.

### Resolution Days

1. Select `Propose Resolution Days`, fill in the details and save the `Draft`.
2. Submit it. The status becomes `Pending Head`.
3. The head uses `Review Resolution Days` to approve it, or rejects it with a reason.

## What happens next

Approved days are used for the workload shown in `My Tasks` and in `Consultant Workload`, and for the quota in the `MD Validation` report. A rejected Resolution Days proposal is corrected and sent again.

## Common questions

**Which one do I need?** Use Customer Mandays for what the customer pays or agrees to, and Resolution Days for the internal plan.

**I cannot see the review buttons.** Reviewing and approving are only for Helpdesk and heads. Ask your administrator if you think you should have them.

<!-- sources: routes/web.php, app/Http/Controllers/MandaysController.php, resources/views/ticket/show.blade.php, resources/views/ticket/task.blade.php -->
