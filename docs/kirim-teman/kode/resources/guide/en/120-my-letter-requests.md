---
key: my-letter-requests
chapter: my-workspace
order: 120
title: My Letter Requests
summary: Ask HR for a letter such as an employment certificate, follow the request and download the finished letter.
requires: [general.my-letter-requests]
updated: 2026-10-27
---
Use `My Letter Requests` to ask HR for a letter: an employment certificate, a reference, or another letter you need. When HR approves, you download the letter here and it is also emailed to you.

## Who uses this

Every employee who has the `My Letter Requests` menu.

## Before you start

Know which letter you need, why you need it and the date by which you need it.

## Step by step

### Request a letter

1. Open `My Letter Requests` from the sidebar.
2. Select `Request a Letter`. If you do not see the button, your role cannot create requests; ask an administrator.
3. Choose the `Letter` from the list. If your company allows it, you can also choose `Other — a letter not on this list`, then describe it under `Which letter do you need?` (HR writes it as a custom letter).
4. Choose the `Language` of the letter. The default follows the letter type.
5. Enter the `Purpose`, for example visa application or bank loan.
6. Pick `Needed by` if you have a deadline. It cannot be a past date.
7. Add `Notes for HR` if needed.
8. Select `Send Request`.

### Follow your requests

1. In the table `My Requests`, read `Letter`, `Purpose`, `Asked on`, `Needed by` and `Status`.
2. The `Status` is `Pending`, `In Progress`, `Done`, `Rejected` or `Cancelled`. For a rejected request the reason is shown.

### Change or cancel a request

1. A request can be changed or cancelled until HR starts on it, which means while it is `Pending`.
2. Use the edit icon to open `Change Request`, update it and save.
3. Use the cancel icon (`Cancel the request`) and confirm to withdraw it.

### Download your letter

1. When the request is `Done` (shown as `Approved — ready` when only the letter is ready), select `Download the letter`.
2. Letters that HR issued about you and sent to you are also listed under `Letters About Me`, each with a `Download` button.

## What happens next

HR receives your request, works on it (`In Progress`) and completes it. The finished letter is emailed to you when the email is sent. See [[statuses-and-approvals]].

## Common questions

**The letter I need is not in the list.** Use `Other — a letter not on this list` if it is offered, or ask HR directly.

**I cannot edit my request.** HR has already started on it. Contact HR.

## Related topics

- [[statuses-and-approvals]]
- [[my-profile]]
- [[access-and-menus]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyLetterRequestController.php, resources/views/hr-general/letters/my-requests.blade.php, app/Models/Letters/LetterRequest.php -->
