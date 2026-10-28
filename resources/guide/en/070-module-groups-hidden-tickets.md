---
key: module-groups-hidden-tickets
chapter: administration
order: 70
title: Hidden Tickets & Module Groups
summary: Bring hidden tickets back to the main list, and group related ticket modules.
requires: [management.hidden-tickets, management.module-groups]
updated: 2026-10-27
---
Two small administration pages support ticketing: `Hidden Tickets`, where tickets that were hidden from the main list can be shown again, and `Module Group`, where related modules are grouped. This topic is for administrators.

## Who uses this

Administrators of the ticketing process. Each page has its own access.

## Step by step

### Show a hidden ticket again

1. Open `Management` › `Hidden Tickets`.
2. Read the list. It shows the ticket number, description, `Customer`, `Ticket Lead`, `Priority`, `Status` and the creation date. If it says no hidden tickets, everything is already shown in the main list.
3. Select the ticket you want to bring back.
4. In the confirmation window, check the ticket and click the show-again button.

> [!IMPORTANT]
> After a ticket is shown again, it appears in the main list for everyone who is allowed to see it. Check that it should be visible first.

### Group modules (Module Group)

A module group collects related modules under one name, for example a group "Logistik" holding several modules.

1. Open the `Module Group` page. It lists `Group Name`, `Description`, the `Modules` in the group and the `Status`.
2. Click `Add Group`.
3. Enter the `Group Name` and a description, select the modules that belong to it, and set the status to `Active` or `Inactive`.
4. Click `Save`.
5. To change or remove a group, use the action icons on its row and confirm.

> [!NOTE]
> The `Module Group` page is not listed in the `Management` menu in the current version. It is available only to people who have its access, so ask your administrator if you need it.

## What happens next

A shown ticket returns to the main ticket list. A saved module group can be used to organize modules, for example in the consultant workload view.

## Common questions

**Who can hide a ticket?** Hiding is done from the ticket itself by people with that access. This page only brings tickets back.


## Related topics

- [[tickets]]
- [[roles-and-menu-access]]

<!-- sources: routes/web.php, routes/api.php, app/Http/Controllers/HiddenTicketController.php, app/Http/Controllers/ModuleGroupController.php, app/Http/Controllers/TicketController.php, resources/views/management/hidden-tickets/index.blade.php, resources/views/management/module-groups/index.blade.php -->
