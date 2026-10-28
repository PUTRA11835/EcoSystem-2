---
key: external-consultants
chapter: external
order: 10
title: External Consultants
summary: Add external consultants, find them, and maintain their profile and engagement.
requires: [general.external.consultants]
updated: 2026-10-27
---
`External Consultants` lists the external consultants of the company, with their engagement, vendor, scheme and period. From here you add a consultant and open the profile to complete the data.

## Who uses this

HR and administrators who manage external consultants. Which buttons and tabs you see depends on your access.

## Before you start

- Open `External` › `External Consultants`.
- The `Add consultant` button appears only if you have the Create permission. If you do not see it, ask your administrator for access.
- Decide the consultant ID (ECI) before you start. It is typed by hand, not generated.

## Step by step

### Find a consultant

1. Click a summary card to filter: `Total consultants`, `Active`, `Mandays`, `Monthly` or `Other / not set`.
2. Type in the search box: name, consultant ID, vendor, client or assignment.
3. Read the columns `Consultant`, `Vendor / Assignment`, `Scheme`, `Period`, `Rate` and `Status`. The `Rate` column appears only if you have permission to see rates.
4. Select `Refresh` to reload the list.

The period shows `No period`, `Upcoming`, `In period` or `Ended`, based on the engagement dates.

### Add a consultant

1. Select `Add consultant`.
2. Type the `Consultant ID (ECI)`. A side panel shows the numbering so you can pick a free number: the next free ID, the latest used IDs and unused numbers in between. Select `Use next` to fill the next free ID. The format is the letter E, the two-digit year and a three-digit number, for example E26060.
3. Enter the `Work email`, `First name` and `Nick name`. These are required, as are `Password` and `Confirm password` (at least 6 characters).
4. Optionally fill in `Last name`, `Mobile phone`, `Title`, `Gender`, `Religion`, `Marital status`, `Birth date` and `Birth place`. You can also do this later.
5. Select `Create consultant`.

The system checks that the ID and nick name are not already used. You then continue on the consultant's profile page.

### Complete the profile

1. Select the consultant to open the summary, then select `Edit consultant`.
2. Work through the tabs you can see. Consultants have fewer tabs than internal employees: `Basic Data`, `Address`, `Identification`, `Education`, `Qualification`, `Bank Account` and `Attachment`. There are no family, contract or payment tabs.
3. In `Basic Data`, fill in the `Engagement` block: `Engagement scheme`, `Vendor / Partner`, `Client / Principal company`, `Assignment / Role`, `Managed by`, `Start date` and `End date`. The `Rate` and `Currency` fields show only to people with rate permission. Select `Save engagement`.
4. The `Emergency Contact` card in `Basic Data` (`Name`, `Relationship`, `Phone`) is optional for consultants. It does not block contracts.

Access to each tab is granted separately from the HR staff tabs, so you may see only some of them.

> [!IMPORTANT]
> The ID card (KTP), tax ID and bank account data are needed before a contract can be set to Active. See [[external-contracts]].

### Open related pages

On the summary page, `Manage External Contract` opens the contracts of this consultant. `Quick Actions` link to the consultant letters you may use, see [[external-letters]]. The summary also shows `Identity & Biodata`, `Contact & Address`, `Payment Account` and `Documents & Attachments`.

## What happens next

The engagement data is read by [[external-contracts]] and by [[external-timesheet-payment]]. Consultants are also checked in [[onboarding]] on contact, ID, tax ID and bank items.

## Common questions

**Why is the rate hidden?** Rates are sensitive and need their own permission.

**Can I change the ECI later?** Ask your administrator. The ECI is also the consultant's sign-in name.

## Related topics

- [[external-contracts]]
- [[external-timesheet-payment]]
- [[external-letters]]
- [[onboarding]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/consultants/index.blade.php, resources/views/hr-general/external/consultants/show.blade.php, resources/views/hr-general/external/consultants/components/list-table.blade.php, resources/views/master/employee/sections/hrprofile.blade.php, resources/views/master/employee/sections/emergency-card.blade.php, resources/views/master/employee/show.blade.php, app/Http/Controllers/HR_General/ExternalConsultantController.php -->
