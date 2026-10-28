---
key: letter-templates
chapter: human-capital
order: 60
title: Letter Templates
summary: Handle employee letter requests, create letters, keep the register and set up numbering.
requires: [general.letters.dashboard, general.letters.requests, general.letters.register, general.letters.compose, general.letter-templates]
updated: 2026-10-27
---
`Letter Templates` is the HR hub for company letters: it receives employee requests, creates letters from templates, records them in a register and holds the numbering rules.

## Who uses this

HR staff who issue letters such as employment certificates, references and assignment letters. Employees ask for letters in My Letter Requests.

## Before you start

Open `Human Capital` › `Letter Templates`. It has up to five tabs: `Dashboard`, `Requests`, `Letter Register`, `Create Letter` and `Settings`. Each tab has its own access, so you may see only some of them.

Signers and letterheads (the page a letter is printed on) are managed in Document Identity. A letter can only be signed by a registered signer who has a signature saved.

## Step by step

### Process an employee request

1. Open `Requests`. The `Employee Letter Requests` table shows the employee, the letter, `Needed by` and `Status`.
2. Select the process action on a request. For a standard type the letter is generated from its template; for another type you start from a template and adjust the wording.
3. Check the letter, then sign it with the signatory's saved signature.
4. Select `Complete & Send` to email the signed letter and finish the request.

To refuse, select `Reject the request` and give a `Reason (shown to the employee)`. Request statuses are `Pending`, `In Progress`, `Done`, `Rejected` and `Cancelled`. If an email failed, use `Resend the failed email`.

### Create a letter yourself

1. Open `Create Letter`.
2. Choose the `Letter Language`, then a `Letter Template`, or start a custom letter from any template under `Start from`.
3. Pick the `Employee`. Name, position, department and join date are filled from the employee data.
4. Fill in the fields that template asks for, for example the purpose of an employment certificate.
5. Check `Letter Date`, `Letter Number` (empty means generated), `Signer Role`, `Signer` and the `Send To (email)` address.
6. Save to generate the letter.

### Use the register

1. Open `Letter Register`.
2. Switch between all, incoming and outgoing letters. Each letter shows `Number`, `Date`, `To / From`, `Subject`, `Source`, `Code`, `Status` and `Delivery`.
3. Open a letter as PDF, or edit its delivery details and upload the final file.
4. To record a letter that did not come from the system, use the buttons for an incoming or outgoing letter and fill in the details.

Letter statuses are `Draft`, `Signed`, `Sent`, `Logged`, `Received` and `Void`. Voiding a letter keeps its number in the register, with the reason.

### Set up numbering and types

In `Settings`, work through the steps: `Numbering & Codes` (the number format for outgoing letters and for incoming agenda numbers, company name and signing city), `Letter Types` (code and default language), `Template Designs`, and `Employee Requests` (what employees may ask for). `Signers` and `Letterheads` appear for people with access to Document Identity.

## What happens next

Employees see the status of their requests in My Letter Requests. The `Dashboard` tab shows requests waiting for HR and the number of letters issued this year by type.

## Common questions

**Why is a template missing in Create Letter?** Templates are switched on in `Settings`. Offering letters and contracts are written in their own menus.

**Where do consultant letters go?** They are created in the External menu, see [[external-letters]].

## Related topics

- [[external-letters]]
- [[offering-letter]]
- [[contracts]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/letters/requests.blade.php, resources/views/hr-general/letters/compose.blade.php, resources/views/hr-general/letters/register.blade.php, resources/views/hr-general/letters/settings.blade.php, app/Models/Letters/Letter.php, app/Models/Letters/LetterRequest.php -->
