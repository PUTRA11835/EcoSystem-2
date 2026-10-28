---
key: ticket-teams-chat
chapter: work-service
order: 16
title: Ticket group chat in Microsoft Teams
summary: Why you are suddenly added to a Teams group chat, what the chat is for and how it stays in step with the ticket.
requires: [tickets.inbox]
updated: 2026-10-28
---
Every validated ticket now gets its own group chat in Microsoft Teams. EcoSystem creates the chat and adds the people who work on the ticket. So if a new group chat shows up in your Teams without anyone telling you, it is a ticket chat and you were added on purpose.

## Who uses this

Everyone who works on tickets: consultants, ticket leads, ticket members, Module Leads, the Delivery Support team of the ticket and helpdesk. External consultants who use an email outside Eclectic, for example Gmail, join as a guest without a Teams account. The steps are in [[ticket-teams-guest]].

## Before you start

- The chat is named after the ticket: the ticket number, a space, a hyphen and the subject, for example `26109001 - Error posting GR in MIGO`. Use the number to find the ticket in `Ticket`.
- The chat is for the internal team only. The customer is never in it and never sees it, even though the conversation with the customer is copied into the chat for the team to read.
- One ticket has one chat. Do not create a second group for the same ticket by hand.

> [!IMPORTANT]
> Messages in the chat are copied into the ticket as internal notes. Never write passwords, customer credentials or other secrets in the chat, just as you would not write them in a ticket note.

## When you are added

You are added automatically, without a separate request, in one of these moments:

1. **The ticket is validated.** When helpdesk approves the ticket in `Ticket Validation`, the chat is created. It starts with the Module Lead of the ticket module and the Delivery Support team of the ticket (Delivery Owner, Support Manager, CO PM and Support Admin), plus a few fixed members.
2. **You are assigned to the ticket.** When someone makes you the ticket lead, or adds you with `Add Member`, you are added to the chat of that ticket.

The new chat appears at the top of your chat list, named after the ticket.

![The Teams chat list with a new ticket group chat at the top, named with the ticket number and subject](images/guide/teams-new-chat.png)

*Screenshot with sample data.*

The first message in the chat is the `Tiket baru` card with the ticket details and the `Buka di EcoSystem` button that opens the ticket. Below it, the Module Lead is @mentioned and asked to follow up on the ticket.

![The Tiket baru card in a ticket group chat with the ticket details and the Buka di EcoSystem button](images/guide/teams-ticket-card.png)

*Screenshot with sample data.*

If you are the one mentioned, Teams shows the notification `EC Support @mentioned you` with the chat name.

![The Teams notification EC Support @mentioned you with the ticket chat name](images/guide/teams-mention-toast.png)

*Screenshot with sample data.*

If you are added to a chat that is already running, Teams shows a line that you were added and that the whole chat history was shared. You can read the chat from the start, so you can catch up on the discussion.

![A ticket group chat with a message from the customer followed by the line that a member was added and the chat history was shared](images/guide/teams-member-added.png)

*Screenshot with sample data.*

## How the chat and the ticket stay in step

**1. An internal note is written in EcoSystem.** On the ticket, select `Internal Note`, write the message and send it.

![A ticket conversation in EcoSystem with an internal note that was just sent](images/guide/ticket-internal-note.png)

*Screenshot with sample data.*

**2. Chat members get a notification.** Teams shows a notification from EC Support with the name of the note writer.

![A Teams notification from EC Support with the name of the internal note writer and EcoSystem](images/guide/teams-note-toast.png)

*Screenshot with sample data.*

**3. The note appears in the chat.** The first line of the message has the writer's name and `EcoSystem`, followed by the note.

![A ticket group chat with a message from the customer and an internal note from EcoSystem that starts with the writer's name and EcoSystem](images/guide/teams-chat-sync.png)

*Screenshot with sample data.*

**4. Reply directly in Teams.** You can also attach a file.

![A reply typed in Teams below the internal note, with an Excel file attached](images/guide/teams-chat-reply.png)

*Screenshot with sample data.*

**5. The reply reaches the ticket.** Within about a minute, the reply from Teams shows on the ticket as an internal note under your name. An attached file shows as a link.

![A ticket conversation with the reply from Teams as an internal note under the writer's name, with a file link](images/guide/ticket-teams-notes.png)

*Screenshot with sample data.*

| What happens | It shows up here |
|---|---|
| You write an `Internal Note` on the ticket in EcoSystem | In the Teams chat, as a message that starts with your name and `EcoSystem` |
| You write a message in the Teams chat | On the ticket, as an internal note under your name, usually within a minute |
| The customer sends an email or a reply to the ticket | In the Teams chat, as a message that starts with `From Customer` and the customer email address. For the team to read only |
| You reply to the customer with `Send via Email` | In the Teams chat, as a message that starts with your name and `Reply to Customer`. A reply whose email failed is not copied |
| You attach an image | On the other side as an image |
| You attach a file (PDF, Excel, document) | On the other side as a link to the file |
| You schedule a meeting from the ticket | In the Teams chat, as a meeting announcement for the team |

What is **not** copied:

- System and SLA messages.
- Edits and deletions. If you change or delete a message on one side, the copy on the other side stays as it was. Write a correction as a new message instead.
- The quote when you reply to a specific message in Teams. The reply reaches the ticket as a normal note without the quote.

> [!TIP]
> A `From Customer` message in the chat is only a copy. Replying to it in Teams sends nothing to the customer, the reply goes to the ticket as an internal note. To answer the customer, write the reply on the ticket in EcoSystem.

Once the ticket is `Closed` or `Cancelled`, new chat messages are no longer copied into the ticket.

## What happens next

Work on the ticket as usual. Discuss in the chat or write internal notes in EcoSystem, because both end up in the ticket history. Replies to the customer are still written on the ticket in EcoSystem.

## Common questions

**I was added to a group chat I do not recognize.** Check the number at the start of the chat name and open that ticket in `Ticket`. You are in the chat because you are the ticket lead, a ticket member, the Module Lead or part of the Delivery Support team.

**I was assigned to a ticket but I am not in its chat.** The chat is created only when the ticket is validated. If you are still missing after that, ask the ticket lead or helpdesk to add you in Teams.

**Can I add someone to the chat myself?** Yes, every member can add people and rename the chat. A chat holds at most 20 people.

**I was removed from the ticket but I am still in the chat.** Removing a member from the ticket does not remove them from the chat. You can leave the chat yourself in Teams.

**My message in Teams does not appear on the ticket.** Wait a minute, then reload the ticket. Messages are not copied when the ticket is closed or cancelled.

## Related topics

- [[ticket-teams-guest]]

<!-- sources: app/Services/Teams/TeamsChatService.php, app/Services/Teams/TeamsGuestService.php, app/Services/Teams/TeamsInboundService.php, app/Services/Teams/TeamsOutboxService.php, app/Services/PowerAutomateService.php, docs/teams-chat-sync-design.md, docs/teams-sync-parity-design.md, docs/power-automate-integration.md -->
