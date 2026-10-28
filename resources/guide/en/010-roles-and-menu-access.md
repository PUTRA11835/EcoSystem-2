---
key: roles-and-menu-access
chapter: administration
order: 10
title: Roles & Menu Access
summary: Create roles, decide which menus each role can see and what it can do there, and review access changes.
requires: [management.roles, management.permissions]
updated: 2026-10-27
---
What each person sees and can do in EcoSystem comes from their roles. A role is a named set of menu access. An employee can have more than one role and gets the combined access of all of them. This topic is for administrators.

## Who uses this

Administrators who manage access. The `Management` menu has `Role` and `Menu List`.

> [!WARNING]
> Access changes affect real people at once. Work carefully, change one role at a time and write a reason when asked. Do not remove access from the role you use yourself.

## Before you start

- Decide which job each role stands for, for example HR, Finance or Manager.
- Know which pages each role needs. For each page you can allow `View`, and then `Create`, `Edit` and `Delete`. Some pages also have special actions such as `Approve` or `Export`.
- `Global ESS` items (the employee self-service pages) are always on for every employee and cannot be switched off.

## Step by step

### Create or edit a role

1. Open `Management` › `Role`.
2. Click `Add Role`, enter the `Name` and a `Description`, and click `Save`.
3. The list shows the number of `Members` for each role.

### Set what a role can access

1. In the role list, open `Menu access` for the role.
2. Search for a page, tab, action or name in the search box, or filter by `Granted`, `Not granted` or `Changed`.
3. Tick `View` to let the role open a page. Then tick `Create`, `Edit`, `Delete` or the special actions that the role may use. Turning `View` off also removes the actions of that row.
4. Tabs are listed one by one under their page, so a role can get only some tabs.
5. Click `Review & Save`, read what will change, add an optional reason, and confirm. Nothing is saved before this step.

Useful tools on the same page:
- `Compare…` shows the difference between two roles.
- `Copy from role…` adds the access of another role to your draft with `Add selected to draft`.
- `Grant View to shown` and `Revoke from shown` change all rows currently shown by the search or filter.
- `History` lists every saved change with its reason. `Undo` reverses one.
- `Discard` clears your unsaved draft.

> [!IMPORTANT]
> Sensitive items ask for an extra confirmation (`I understand and confirm`). Read them before you continue.

### See who holds a menu (Menu List)

1. Open `Management` › `Menu List`.
2. Find a menu and open its `Access` view to see which roles hold it.
3. Grant or remove access for several roles at once, enter a reason, and `Save changes` after the `Review changes` step.
4. `History` shows earlier changes to that menu.

### Give a person a role

Roles are assigned to each employee from the employee data (`Master` › `Employee`). A person can hold several roles.

## What happens next

When a role's access is saved, the people in that role get the new menus. Their menu list is refreshed the next time the page loads. If someone still sees the old menus, ask them to sign out and in again; at the latest the old list expires after about an hour.

## Common questions

**A person says a menu is missing.** Check that one of their roles has `View` for it. See [[access-and-menus]].

**Can I undo a change?** Yes, use `History` and `Undo` on the role's `Menu access` page.

## Related topics

- [[access-and-menus]]
- [[ess-settings]]
- [[approval-workflows]]
- [[control-center]]

<!-- sources: routes/web.php, routes/api.php, app/Http/Controllers/RoleController.php, app/Http/Controllers/Management/RoleMenuAccessController.php, app/Services/Permissions/RoleMenuAccessService.php, app/Http/Middleware/ShareMenuPermissions.php, resources/views/management/roles/index.blade.php, resources/views/management/roles/menu-access.blade.php, resources/views/management/permissions/index.blade.php -->
