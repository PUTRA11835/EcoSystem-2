---
key: external-contract-settings
chapter: external
order: 30
title: External Contract Settings
summary: Manage the contract types, position types and work placements offered on the external contract form.
requires: [general.external.contracts.settings]
updated: 2026-10-27
---
`External Contract Settings` controls the dropdown choices on the external contract form. Keep them tidy so HR picks the right option every time.

## Who uses this

HR staff or administrators who maintain external contract options.

## Before you start

Open `External` › `External Contract` and select the `Settings` tab. Buttons to add, edit, reorder or delete appear only with the matching permissions.

The page has four lists: `Contract types`, `Position types`, `Branches` and `Other work placements`.

## Step by step

### Contract types

`Contract types` define how a consultant is paid. The terms of the contract (rate unit, hours per manday, proration) follow the built-in type.

1. Select `Add contract type`.
2. Enter the `Name`, for example `Probation`.
3. Under `Behaves like`, choose the built-in type to copy: it keeps the same numbering, date rules and templates.
4. Add an optional `Description` as a hint for the person choosing, and select `Add contract type`.

### Position types

`Position types` describe the kind of position a contract is for, for example Consultant. A template written for a position type is picked automatically for contracts of that type.

1. Select `Add position type`.
2. Enter the name and select `Add position type`.
3. You can also add several at once with the bulk option.

### Work placement

`Branches` lists every active branch from `Management` › `Branches`. They are offered as work placements automatically. To add, rename or switch off a branch, change it in the branch list.

For places that are not a branch, such as Client site or Remote:

1. Under `Other work placements`, select `Add work placement`.
2. Enter the `Name` and an optional `Description`, and add it.
3. Mark one placement as the default with the star to preselect it on new contracts.

### Reorder, rename, hide or delete

In each list you can:

- Move an option earlier or later on the form with the arrows.
- Rename it and edit its description.
- Switch `On form` off to hide it without deleting it.
- Set the `Default` option.
- Delete an option. The list says `In use, cannot be deleted` when contracts or templates still use it, and built-in types cannot be deleted.

The `Used by` column shows how many contracts and templates use an option.

## What happens next

The choices appear immediately on the external contract form, see [[external-contracts]]. The same lists for internal contracts live in the `Settings` tab of the Contract menu, see [[contracts]].

## Common questions

**Why can't I delete a type?** It is in use or built in. Hide it with `On form` instead.

**Why is a branch missing from Work placement?** Only active branches are offered.

## Related topics

- [[external-contracts]]
- [[contracts]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/contracts/settings.blade.php, resources/views/hr-general/contracts/components/settings-options.blade.php, app/Http/Controllers/HR_General/ContractSettingController.php, app/Services/Contracts/ContractOptionService.php -->
