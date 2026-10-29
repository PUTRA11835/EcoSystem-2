---
key: ticket-teams-guest
chapter: work-service
order: 17
title: Joining a ticket chat as a guest
summary: For external consultants: accept the Eclectic invitation and open the ticket chat in the browser, without a Teams account.
requires: [tickets.inbox]
updated: 2026-10-28
---
This topic is for external consultants who work with an email outside Eclectic, for example a Gmail address. You join the ticket group chat as a guest of Eclectic Company. You do not need to install Teams or create a Microsoft account. A browser and access to your email are enough.

The first time takes about five minutes. Follow the steps in order. What the chat is for and how it stays in step with the ticket is explained in [[ticket-teams-chat]].

## Step 1. Open the invitation email

You receive an email from EC Support with the subject `Group chat Microsoft Teams:` followed by the chat name. It can arrive a few minutes after you were assigned to the ticket. If it is not in your inbox, look in the spam folder and mark it as not spam, so the next invitations arrive normally.

The first email has two buttons. Use them in order: first accept the invitation, then open the chat.

![The first invitation email from EC Support with the buttons Terima undangan Eclectic Company and Buka group chat di Teams](images/guide/guest-invite-email.png)

*Screenshot with sample data.*

## Step 2. Accept the invitation

Select `Terima undangan Eclectic Company`. A Microsoft sign-in page opens with your email address. Select `Send code` to receive a code by email.

![The Microsoft sign-in page showing the guest email address and the Send code button](images/guide/guest-signin.png)

*Screenshot with sample data.*

## Step 3. Get the verification code

Microsoft sends the email `Account verification code` from Eclectic Company (via Microsoft) to the same address. Open that email and note the code. The code works for 30 minutes only.

![The Account verification code email from Eclectic Company via Microsoft with the verification code](images/guide/guest-code-email.png)

*Screenshot with sample data.*

## Step 4. Enter the code

Go back to the sign-in page, type the code and continue.

## Step 5. Accept the permissions

Microsoft asks you to allow Eclectic Company to read your name and email address. Select `Accept`. If you are asked whether to stay signed in, choose yes only on your own device.

Steps 2 to 5 are needed only once. Next time you sign in with the email code only.

## Step 6. Open the group chat

Go back to the invitation email and select `Buka group chat di Teams`. If you are offered a choice, select `Continue on this browser`. This is the easiest way and works without installing anything.

## Step 7. Work in the chat

Teams opens in the browser with the ticket chat selected. The chat list shows only the ticket chats you are a member of. You can read the chat from the start, including the `Tiket baru` card and the messages from the customer, and reply like any other member.

![The ticket group chat open in Teams in the browser for a guest, with the Tiket baru card and a message from the customer](images/guide/guest-chat-browser.png)

*Screenshot with sample data.*

You can close the banner that offers the Teams desktop app. For the next tickets you receive a shorter email with only the button to open the chat.

> [!TIP]
> If the Teams desktop app says "We can't take you to that message because it's in a chat you're not in", the app is using your other account. Select your profile picture at the top right, switch to the organization `Eclectic Company (Guest)` and open the link again. If that organization is not listed, use Teams in the browser.

## Common questions

**I did not receive any email.** Check the spam folder first. Then make sure the email address in your EcoSystem profile is the one you use, and ask helpdesk to check it if not.

**The verification code does not work.** The code expires after 30 minutes. Ask for a new code on the same sign-in page and use the newest email.

**Do I need to install Teams?** No. Teams in the browser is enough. If you prefer the app, sign in there with the same email and switch to `Eclectic Company (Guest)`.

**I was assigned to a ticket but I am not in its chat yet.** A guest who is invited for the first time joins a few minutes later. If you are still missing after that, ask the ticket lead or helpdesk to add you in Teams.

## Related topics

- [[ticket-teams-chat]]

<!-- sources: app/Services/Teams/TeamsGuestService.php, app/Services/Teams/TeamsChatService.php, app/Console/Commands/AddPendingTeamsGuests.php, docs/power-automate-integration.md -->
