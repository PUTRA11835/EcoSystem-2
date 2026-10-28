---
key: holidays-branches
chapter: administration
order: 50
title: Holidays & Branches
summary: Maintain the company holiday calendar and the list of branches, including the attendance location of each branch.
requires: [management.holidays, management.branches]
updated: 2026-10-27
---
Holidays and Branches are company-wide master data. Holidays feed the calendar and date pickers. Branches are used by several menus, such as Attendance, Reimbursement, Purchase Request and Cash Advance. This topic is for HR and administrators.

## Who uses this

HR and administrators. The pages are `Management` › `Holidays` and `Management` › `Branches`. Add, edit and delete buttons appear only with the matching access. Without it you can still read the lists.

## Before you start

- For holidays, get the official list of national holidays and collective leave for the year.
- For a branch, know its code, address and, if attendance is checked by location, the coordinates of the site.

## Step by step

### Add a holiday

1. Open `Management` › `Holidays`.
2. Optionally filter the list by year with `All Years`.
3. Click `Add Holiday`.
4. Enter the `Name` and the `Date`, and choose the `Type`: `National Holiday`, `Collective Leave` or `Custom Holiday`.
5. Keep `Active (applied to calendar & date pickers)` ticked so the day is used.
6. Click `Save`.

To change or remove a holiday, use the action icons on its row and confirm. Untick `Active` to stop using a holiday without deleting it.

> [!IMPORTANT]
> Holidays apply to every employee, so changes affect calendars and date pickers for the whole company. Check the date and type before saving.

### Add a branch

1. Open `Management` › `Branches`.
2. Use `Search` and `Status` (`All`, `Active`, `Inactive`) to see the existing branches, so you do not add a duplicate.
3. Click `Add Branch`.
4. Enter the `Branch Code` (it must be unique), the `Branch Name`, address, city, province and phone.
5. For the attendance location, choose the point under `Choose Location on Map` (click the map or drag the pin) or fill in `Latitude` and `Longitude`. Set the `Geofence Radius (meters)`, the distance from the centre that is still considered on site.
6. Choose the `Geofence Policy`: follow the company setting, `Off` (location is not checked), `Flag` (record and mark when outside the radius) or `Enforce` (reject when outside the radius).
7. Tick `Head Office` if this is the head office. Only one branch can be the head office; choosing it clears the flag from the other branch.
8. Set `Status`. Inactive branches are excluded from attendance validation.
9. Save the branch.

### Edit or delete a branch

Use the edit icon on the row to change a branch. The delete icon removes it from the lists after confirmation. Attendance records already saved are kept.

## What happens next

Holidays appear in the calendars of everyone. Branches become available in the menus that use them, and the geofence settings apply to attendance check-in.

## Common questions

**Can two branches have the same code?** No, the code must be unique.

**An employee is marked outside the area.** Check the branch coordinates and radius, and the `Geofence Policy`. See [[attendance-management]].

## Related topics

- [[attendance-management]]
- [[my-attendance]]
- [[roles-and-menu-access]]

<!-- sources: routes/web.php, app/Http/Controllers/HolidayManagementController.php, app/Http/Controllers/Management/BranchController.php, resources/views/management/holidays/index.blade.php, resources/views/management/branches/index.blade.php, resources/views/management/branches/form.blade.php -->
