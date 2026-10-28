---
key: control-center
chapter: administration
order: 60
title: Control Center - Monitoring and Logs
summary: Check system health and review who signed in, what was accessed and what was created, changed or deleted.
requires: [control-center, control-center.overview, control-center.activity-log, control-center.login-log, control-center.audit-log]
updated: 2026-10-27
---
Control Center is the administrator area of EcoSystem. This topic covers the monitoring pages: `Overview`, `Activity Log`, `Login Log` and `Audit Log`. System tools such as sessions, backups and AI models are in [[control-center-system]].

## Who uses this

Administrators only. Each page has its own access, so you see only the pages your role allows under `Control Center`. The `Overview` page is limited to the top administrator role.

> [!IMPORTANT]
> These pages show information about people's activity. Use them only for administration, security or audit, and do not share what you see with people who should not have it.

## Before you start

- Know what you are looking for: a person, a date range or a kind of action. The pages have filters, so narrow the list first.

## Step by step

### Check the Overview

1. Open `Control Center` › `Overview`.
2. Read the summary cards, for example `System Health`, `Queue`, `Failed Jobs` and the number of active sessions.
3. Use the shortcut cards to open `Activity Log`, `Backup & Export`, `Notification Sounds`, `AI Settings` and the other tools.

If the queue card reports errors, open `Failed Jobs` (see [[control-center-system]]).

### Review the Activity Log

The `Activity Log` records sign-ins, sign-outs and access attempts.

1. Open `Control Center` › `Activity Log`.
2. Set `Date From` and `Date To`, a status (`Success` or `Failed`), a user type or a search word, then click `Apply`.
3. Read the cards (`Total Records`, `Successful Logins`, `Failed Attempts`, `Logouts`) and the table with the device, browser, IP address and location.
4. Click `Clear` to remove the filters.

A burst of failed attempts for one account can mean someone is guessing a password. Tell your security contact.

### Review the Login Log

1. Open `Control Center` › `Login Log`.
2. Search for a person or choose a status.
3. Read each employee's latest login and logout, with the time (WIB) and device.

### Review the Audit Log

The `Audit Log` records create, update and delete actions across modules, with the values before and after.

1. Open `Control Center` › `Audit Log`.
2. Choose a module (for example `Ticket`, `Customer`, `Employee`), an event (`Created`, `Updated`, `Deleted`) and a date range, then click `Apply`.
3. Open a record to see the changed fields: `Field`, `Before` and `After`. For a deleted record you see `Value Before Deletion`.

> [!NOTE]
> The logs are for viewing. You cannot change or delete log records from these pages.

## What happens next

Findings from the logs are handled outside these pages, for example by correcting data in the module, changing access in [[roles-and-menu-access]] or ending a session in [[control-center-system]].

## Common questions

**Why don't I see `Overview`?** It is limited to the top administrator role.

**An action I expected is missing from the Audit Log.** The log covers the modules listed in its module filter only.

## Related topics

- [[control-center-system]]
- [[roles-and-menu-access]]
- [[access-and-menus]]

<!-- sources: routes/web.php, resources/views/admin/index.blade.php, resources/views/admin/activity-log.blade.php, resources/views/admin/login-log.blade.php, resources/views/admin/audit-log.blade.php, resources/views/partials/sidebar.blade.php -->
