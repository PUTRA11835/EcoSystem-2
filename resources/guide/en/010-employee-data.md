---
key: employee-data
chapter: human-capital
order: 10
title: Employee Data
summary: Find, add and maintain employee records, and see which profiles HR has locked.
requires: [master.employee]
updated: 2026-10-28
---
Use `Employee Data` to look up any employee, add a new one, and open the full profile with its tabs (basic data, address, identity, bank and more).

## Who uses this

HR staff who maintain the employee master data. What you can do on this page depends on your access: everyone with the menu can search and export, while adding employees and the action menu appear only with the matching permissions.

## Before you start

- Open `Human Capital` › `Employee Data`.
- To add an employee, have the employee ID (ECI), a work email and a first password ready.
- If you do not see `Create Employee` or the action menu in the `Actions` column, ask your administrator for the permission.

## Step by step

### Find an employee

1. Type a name or ECI in the search box at the top of the list. Press `/` to jump to it.
2. Use the funnel icon in a column header to filter by `Position`, `Module`, `Employee Group`, `Division`, `Department`, `Home Base`, `Status` or `Profile`.
3. Click a summary card (`Total employees`, `Active`, `Blocked`, `Internship`, `Profile locked`) to filter the list in one click.
4. Select `Reset filters` to clear everything.
5. Select `Export Excel` to download the list.

The `Profile` column shows `Locked` when HR has verified and locked the profile (see [[onboarding]]).

### Add an employee

1. Select `Create Employee`.
2. Fill in `General Data`: `Employee` (the ECI), `Password`, `Confirm Password`, `First Name`, `Nick Name` and `Work Email`. Fields marked with a red star are required.
3. Fill in `Standard Address` and `Organizational Data` (`Position`, `Employee Group`, `Division`, `Home Base` and so on).
4. Select `Save`.

> [!NOTE]
> External consultants are added from `External` › `External Consultants` instead. See [[external-consultants]].

### Open and update a profile

1. Select an employee's row.
2. Choose a tab, for example `Basic Data`, `Address`, `Identification`, `Family`, `Education`, `Qualification`, `Contract`, `Bank Account`, `Basic Payment` or `Attachment`. You only see the tabs your access allows.
3. Edit the fields and save inside the tab.

A banner at the top tells you when the profile is locked. The employee cannot change a locked profile, but HR can. To open it for the employee again, use `Unlock` on the [[onboarding]] page.

### Account actions

If you see the action button on a row, it offers:

- `Change Password`: set a new password for the person.
- `Change ECI`: rename the employee ID; you confirm with your own password.
- `Change Role`: tick the roles the person should have.
- `Delete`: remove the employee after a confirmation.

## What happens next

The data you save feeds Onboarding progress, [[contracts]] readiness and, where enabled, payroll. Missing items are reported there, so keep identity, bank and contract documents current.

## Common questions

**Why is a tab missing?** Tabs follow your access. Consultants also do not have `Family`, `Contract` or `Basic Payment` tabs.

**Can I change a locked profile?** Yes, as HR. The employee sees the sections as View Only until the profile is unlocked.

## Related topics

- [[onboarding]]
- [[contracts]]
- [[external-consultants]]

<!-- sources: routes/web.php, resources/views/master/employee/index.blade.php, resources/views/master/employee/show.blade.php, resources/views/partials/sidebar.blade.php -->
