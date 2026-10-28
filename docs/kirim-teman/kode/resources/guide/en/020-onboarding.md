---
key: onboarding
chapter: human-capital
order: 20
title: Onboarding
summary: Track how complete each employee's master data is, then verify and lock the profile.
requires: [general.onboarding]
updated: 2026-10-27
---
`Onboarding` shows how much of each employee's required data is filled in and lets HR verify and lock a profile once it is complete. Nothing is typed on this page: progress follows the data saved in `Employee Data` and in My Profile.

## Who uses this

HR staff who check new and existing employees. Employees see their own progress as a `Data readiness` card on My Profile.

## Before you start

- Open `Human Capital` › `Onboarding`.
- Locking and unlocking are separate permissions. If you do not see `Verify & Lock`, `Lock all ready` or the unlock box, ask your administrator.

## Step by step

### Read the overview

1. Check the `Onboarding Summary` on the left: percentage, `Employees`, `In progress`, `Complete` and `Items left`.
2. In the `Employee Onboarding` table, read `Join date`, `Status`, `Type` and `Progress`. Amber tags under the bar show which group still has missing items (for example `Payroll · 2`).
3. Use the funnels to filter by lock state (`Ready to lock`, `Locked`), `Type`, progress and the group that is missing items.

The required items are grouped as `Profile`, `Payroll`, `BPJS` and `Contract`. Internal employees are checked on personal data, contact, ID card number, tax ID, bank account, BPJS numbers, an active contract and a join date. External consultants are checked only on contact, address, ID card, tax ID and bank account.

### Open the checklist

1. Select `Open Checklist` on a row.
2. Read the `Checklist`. Each item is `Complete` or `Missing`, with a hint on how to fill it.
3. Select `Fill in` on a missing item. The employee's profile opens on the right tab. Items for the contract and join date are set by HR.

### Verify and lock a profile

1. Open the employee's checklist when all items are `Complete`. The card `Profile lock` shows `Ready to lock`.
2. Select `Verify & Lock` and confirm.
3. The status changes to `Locked`. The employee can no longer change `Basic Data`, `Address`, `Identification`, `Bank Account` or `HR Profile`; those sections show as View Only in My Profile. HR can still edit them.

To lock many profiles at once, select `Lock all ready` on the list. It locks every complete profile within the current filter after you confirm.

### Unlock a profile

1. Open the locked employee's checklist.
2. In `Profile lock`, type a reason in `Reason for unlocking (required)`. It must be at least 5 characters.
3. Select `Unlock profile`.

> [!WARNING]
> The employee can edit the sections again once unlocked. Lock the profile again after the change is done.

### Join date tools

When the missing-item filter is set to the join date, optional tools appear to type join dates for several people, import a list of ECI and date pairs (preview first, then `Apply`), or send reminders.

## What happens next

A locked profile shows the `Locked` badge on `Employee Data`. Complete data also allows contracts to be set to Active, see [[contracts]].

## Common questions

**Why can't I lock?** The `Verify & Lock` button is disabled until every required item is complete.

**Who can see my progress?** You see your own on My Profile; HR sees everyone's here.

## Related topics

- [[employee-data]]
- [[contracts]]
- [[offboarding]]

<!-- sources: routes/hr-general.php, config/hc_onboarding.php, resources/views/hr-general/onboarding/index.blade.php, resources/views/hr-general/onboarding/show.blade.php, resources/views/hr-general/onboarding/components/join-date-tools.blade.php, resources/views/master/employee/show.blade.php -->
