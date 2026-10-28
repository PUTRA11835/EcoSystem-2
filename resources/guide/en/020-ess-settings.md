---
key: ess-settings
chapter: administration
order: 20
title: ESS Settings
summary: Show or hide employee self-service menu items for everyone, and group them into sidebar dropdowns.
requires: [management.ess-settings]
updated: 2026-10-27
---
ESS Settings controls the self-service menu items (such as My Attendance or Paystub) that every employee sees in the sidebar. You can switch an item off for the whole company and fold related items into a named dropdown. This topic is for administrators.

## Who uses this

Administrators. The page is `Management` › `ESS Settings`.

> [!WARNING]
> These switches are global. Turning an item off hides it from every employee, whatever their role. To control who sees a page, use [[roles-and-menu-access]] instead.

## Before you start

- Know which self-service items your company uses. Switch off only items that nobody should use yet.
- Menu access and these switches work together: a person sees a self-service item only if it is switched on here and their access allows it.

## Step by step

### Show or hide items

1. Open `Management` › `ESS Settings`.
2. Find the item in the list. Each item has a switch.
3. Switch it on to show it, or off to hide it. `Select All` and `Deselect All` change every switch at once.
4. Click `Save Changes`.

### Group items into a dropdown

1. In `Menu Groups`, click `Create Group`.
2. Enter the `Group name`, for example "My Activity", and an `Icon` (a Font Awesome class such as `fas fa-briefcase`).
3. Click `Add`. Use `Save Name` to rename it later, or the delete icon to remove it.
4. For each item you want in the group, choose the group in that item's row.
5. Click `Save Changes`.

Items that you do not place in a group stay in the sidebar as they are. A few items cannot be grouped because they already have their own dropdown or are always shown (for example the calendar items and Logout).

## What happens next

The sidebar of every employee updates the next time they load a page.

## Common questions

**An employee cannot find My Attendance.** Check that the item is switched on here and that the person's role has access. See [[access-and-menus]].

**Does deleting a group delete its items?** No. The items return to the normal list in the sidebar.

## Related topics

- [[roles-and-menu-access]]
- [[access-and-menus]]
- [[navigating-ecosystem]]

<!-- sources: routes/web.php, app/Http/Controllers/Management/EssSettingsController.php, resources/views/management/ess-settings/index.blade.php, resources/views/partials/sidebar.blade.php -->
