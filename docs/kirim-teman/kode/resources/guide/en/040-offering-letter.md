---
key: offering-letter
chapter: human-capital
order: 40
title: Offering Letter
summary: Write, sign and send an offering letter, then create the employee when it is accepted.
requires: [general.offering-letter, general.recruitment.offers, general.recruitment.offers.settings]
updated: 2026-10-27
---
`Offering Letter` turns a candidate at the offer stage into a signed letter, sends it, and records the decision. Accepting creates the employee account.

## Who uses this

HR staff who prepare offers and manage compensation components.

## Before you start

- Open `Human Capital` › `Offering Letter`. It has two tabs: `Offering Letter Table` and `Offering Settings`.
- The signatory must be an employee whose signature is saved in their profile. Without it the letter cannot be signed.
- Set up the numbering and compensation components first in `Offering Settings` (see below).

## Step by step

### Write a letter

1. In `Offering Letter Table`, select `Add Offering Letter`.
2. Choose the `Letter Language` and check `Offer Date` and `Letter No.`.
3. Pick the candidate under `Candidate Data`. Only candidates at the `Offer` stage are listed, and picking one fills in name, email, phone and position. You can also enter the details by hand.
4. Fill in `Job Description`, `Benefits` and `Start Date`. The start date becomes the join date when the offer is accepted.
5. Under `Compensation`, choose the `Salary Type` (gross or nett) and enter an amount for each component. `Total Compensation` and the `Base Salary Percentage` update as you type.
6. Pick the `Signatory`, add `Notes` and select `Save`.

> [!NOTE]
> A base salary below the minimum percentage is flagged while you write, but it does not block saving.

### Sign, send and record the decision

1. Select the sign action on the row to sign with the signatory's saved signature. Status becomes `Signed`.
2. Download the PDF to check it.
3. Select `Send to candidate`, review the `Subject` and `Message`, tick the confirmation and send. Status becomes `Sent`.
4. When the candidate answers, select `Candidate accepted` or `Candidate rejected`.

The statuses are `Draft`, `Signed`, `Sent`, `Accepted` and `Rejected`. Only a letter that is signed can be sent.

### Accept and create the employee

1. In `Offer Accepted — Create Employee`, enter the `Employee ID (ECI)`, `Full Name`, `Login Email`, `Home Base` and `Default Password` (at least 8 characters).
2. Check the `Join Date`, filled from the letter's start date.
3. Select `Accept & Create Employee`.

The default password is emailed to the candidate with their username; it works only until they set their own.

### Offering Settings

Set the `Letter Number Format`, digits of the running number, `Signing City`, `Days to Respond` and the minimum base salary percentage. Add compensation components with their English names, whether they count as base salary or allowance, and an optional default.

## What happens next

The new employee appears in [[employee-data]] and in [[onboarding]], and the candidate becomes `Hired`.

## Common questions

**Why can't I sign?** Pick the signatory from the employee list and make sure they have a signature in their profile.

**What if the signatory has no signature?** The system tells you the signature is missing. Ask that person to add it in their profile, then sign the letter.

## Related topics

- [[recruitment]]
- [[onboarding]]
- [[contracts]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/offering/index.blade.php, resources/views/hr-general/offering/components/offer-modal.blade.php, resources/views/hr-general/offering/settings.blade.php, app/Http/Controllers/HR_General/RecruitmentOfferController.php, app/Models/Recruitment/Offer.php -->
