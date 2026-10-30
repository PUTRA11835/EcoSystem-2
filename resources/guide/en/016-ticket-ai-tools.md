---
key: ticket-ai-tools
chapter: work-service
order: 16
title: AI Summarize and Ask AI on a ticket
summary: Summarise a long ticket in three parts, or open an AI research chat about it, and use both safely.
requires: [ui.ticket.btn-ai-summarize, ui.ticket.btn-ai-research]
updated: 2026-10-28
---
Two buttons on the ticket page use AI to help you with a ticket: `AI Summarize` and `Ask AI (Research)`. Both are tools for you to think with. They do not change the ticket.

## Who uses this

Roles that your administrator gave the `AI Summarize Ticket` and `Ask AI (Research) on Ticket` permissions, for example consultants, module leads and heads. `Ask AI (Research)` is also limited to tickets you handle: you must be the ticket lead or a team member, unless you are an administrator or a role that approves in Ticket Validation.

## Before you start

- Never put credentials, passwords or customer personal data into an AI tool. A ticket can contain customer credential information: do not copy it into a question.
- AI can be wrong. Check the result against the ticket and the system before you send anything to a customer.
- You cannot choose the model. The administrator sets it in `AI Settings`.

## Step by step

### Summarise a ticket

1. Open the ticket. In the right-hand panel select `AI Summarize`. The panel is hidden on a narrow window, so widen the window or use a larger screen if you do not see it.
2. A window opens with three cards that fill in as the text arrives: `Issue`, `Resolution Steps` and `Conclusion`. Under `Resolution Steps`, `Documentation sources` lists the outside pages the AI actually opened.
3. Read each card and compare it with the conversation.
4. Select `Copy` to take the summary with you as plain Markdown, for a chat message, an email or a work log.
5. Close the window with the cross.

If the window shows a yellow notice, the answer reached its length limit. It is still shown and mostly useful, but it is not kept for reuse. If it shows a red box, the summary failed: close the window and try again later.

### Ask AI about a ticket

1. In the same panel select `Ask AI (Research)`.
2. A research chat opens for this ticket. Ask your question in plain words, for example how to approach an error message, and read the answer and its sources.
3. Use what you learn in your own reply or `Internal Note`. Do not paste the AI answer to the customer without checking it.

## What happens next

Neither button changes the ticket. What you do with the answer, such as a reply or a note, is up to you. Usage is counted in `AI Usage`, which administrators watch for cost.

## Common questions

**I do not see the buttons.** Your role does not have the permissions. For `Ask AI (Research)` you also need to be the ticket lead or a team member. Ask your administrator or the ticket lead.

**The summary changed when I opened it again.** It is kept for a short time, so an unchanged ticket shows the same summary quickly. When the ticket changes, for example a new message, a new status or a new member, a fresh summary is made. Use `Copy` if you need to keep one.

<!-- sources: resources/views/ticket/show.blade.php, app/Support/TicketTeamAccess.php, app/Services/Ai/AiTicketSummaryService.php -->
