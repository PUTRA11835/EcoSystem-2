---
key: my-profile
chapter: my-workspace
order: 10
title: My Profile
summary: Review and complete your personal data, track your data readiness, and understand when HR has locked your profile.
requires: [my-profile.section.basic_data, my-profile.section.address, my-profile.section.identification, my-profile.section.bank, my-profile.section.attachment]
updated: 2026-10-27
---
`My Profile` holds your personal data: identity, address, bank account, documents and more. Keeping it complete helps HR process your contract, payroll and BPJS correctly.

## Who uses this

Every employee. The tabs you see depend on your access, and external consultants see a shorter set of tabs.

## Before you start

Have your ID card, tax number, bank account details and supporting documents ready. Fields marked with a red asterisk are required.

## Step by step

### Open and read your profile

1. Select `My Profile` in the sidebar, or open it from the menu under your name.
2. Read the header card with your photo or initials and your main details.
3. Choose a tab such as `Basic Data`, `Address`, `Identification`, `Family`, `Education`, `Qualification`, `Contract`, `Bank Account`, `Basic Payment` or `Attachment`.

### Fill in what is missing

1. Check the `Data readiness` card at the top. It shows the percentage complete and groups the missing items under Personal data, Payroll, BPJS and Contract.
2. Select a group, then select a missing item. EcoSystem opens the right tab and highlights the field.
3. Enter the data.
4. Select `Save Changes`. The `Data readiness` card updates after you save.

An item marked `HR` can only be filled by HR. A group showing `With HR` has nothing left for you to do.

### Add a profile photo and signature

1. Open the `Basic Data` tab and scroll to `Profile photo`.
2. Select `Choose photo` and pick a JPG, PNG or WEBP file of up to 4 MB.
3. Move and zoom the picture to fit the circle, then save. Select `Remove photo` to delete it.
4. In the `Signature` box, draw your signature or upload an image (PNG or JPG, up to 1 MB). Only you can add or change it; HR can remove it.

Your photo then appears in the top bar.

### View your contract and pay recap

- The `Contract` tab shows your contract history and a read-only salary recap. HR manages these.
- `Basic Payment` shows a read-only recap of your recent paystubs. Download them from `Paystub` (see [[my-paystub]]).

### Sign or review a contract

When HR sends you a contract to sign, the `Contract` tab shows its signing stage with a link to open it, and you may receive a reminder Notification. Open the contract, read it and follow the on-screen options to sign or to ask for a change. Signing uses the signature saved in your profile, so add it first (see above).

## When HR locks your profile

After HR verifies your data, the page shows the banner `Your profile is verified and locked by HR.` `Basic Data`, `Address`, `Identification`, `Bank Account` and `HR Profile` become `View Only`. To change anything, contact HR and ask them to unlock your profile.

> [!IMPORTANT]
> Check your bank account and tax details carefully before HR verifies them. After the lock you cannot correct them yourself.

## Common questions

**A tab is missing.** Your access does not include it. See [[access-and-menus]].

**Why is a field greyed out?** The tab is `View Only`, either by your role or because HR locked the profile.

**How do I change my password?** Open `Settings` › `Security`. See [[navigating-ecosystem]].

## Related topics

- [[access-and-menus]]
- [[navigating-ecosystem]]
- [[my-paystub]]

<!-- sources: routes/web.php, app/Http/Controllers/ProfileController.php, app/Http/Controllers/SettingsController.php, resources/views/master/employee/show.blade.php, resources/views/master/employee/sections/hrprofile.blade.php, resources/views/master/employee/sections/basicdata.blade.php, resources/views/master/employee/sections/payment-recap.blade.php, app/Http/Controllers/MyContractController.php -->
