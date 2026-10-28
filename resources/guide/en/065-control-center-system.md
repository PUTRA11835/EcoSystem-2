---
key: control-center-system
chapter: administration
order: 65
title: Control Center - System Tools
summary: Manage active sessions, failed background jobs, backups and exports, notification sounds and AI models.
requires: [control-center.sessions, control-center.failed-jobs, control-center.backup, control-center.sounds, control-center.ai-settings]
updated: 2026-10-27
---
This topic covers the system tools in `Control Center`: `Active Sessions`, `Failed Jobs`, `Backup & Export`, `Notif Sounds` and `AI Settings`. For the monitoring pages see [[control-center]].

## Who uses this

Administrators only. Each tool has its own access, so you see only the pages your role allows.

> [!WARNING]
> These tools act on the whole system and other people's data. Read the confirmation before you proceed. Most actions cannot be undone.

## Step by step

### Active Sessions

`Active Sessions` shows who is signed in now, with the user, browser or device, IP address and last activity.

1. Open `Control Center` › `Active Sessions`.
2. Click `Force Logout` on a row. A `Session Detail` window opens.
3. Confirm `Force Logout` to end that session. The person must sign in again and may lose unsaved work.
4. `Force Logout All Others` ends every session except yours.

Your own session and protected sessions cannot be ended from the row. Use this when an account may be misused or a device was lost.

### Failed Jobs

Some tasks run in the background, such as sending notifications or calculating a large payroll. `Failed Jobs` lists the ones that failed.

1. Open `Control Center` › `Failed Jobs`.
2. Read the `Queue`, `Failed At` and `Error` of a job. Open the row for the `Exception Detail`.
3. Click `Retry` to run one job again, or `Retry All`.
4. Click `Delete` to discard one job, or `Clear All` to discard all of them.

> [!IMPORTANT]
> Discarding a job cannot be undone. If the same job fails again after `Retry`, ask the technical team before retrying more.

### Backup & Export

1. Open `Control Center` › `Backup & Export`.
2. Click `Create Backup Now` to create a database backup. It appears under `Existing Backups`, where you can download or delete it.
3. Under the export sections (for example `Employee Data`, `Customer Data`, `Ticket Data`), export the data as a file.
4. To import, download the template, fill it in, drop the file in the import area and check the `Import Errors` list afterward. Rows that cannot be matched are skipped.

> [!WARNING]
> A backup file and exported data contain personal and company information. Store them safely and delete files you no longer need. An import changes real data, so check the template first and try a small file.

### Notif Sounds

1. Open `Control Center` › `Notif Sounds`.
2. Under `Upload New Sound`, enter a `Display Name`, choose an audio file (WAV, MP3, OGG or AAC, up to 3 MB) and upload it.
3. Use `Preview` to listen. Delete a sound you no longer need. The default sound is protected and cannot be deleted.

Uploaded sounds become available as notification alert options.

### AI Settings

1. Open `Control Center` › `AI Settings`.
2. For each assistant, choose the `Model` and the `Thinking / reasoning effort`.
3. Save the settings.

A different model changes the quality, speed and cost of the assistants for everyone. Change it only on an agreed decision.

## What happens next

Changes apply immediately to everyone. Ended sessions sign out at their next action.

## Common questions

**An employee cannot sign in after a force logout.** Force logout only ends the session. They can sign in again with their normal account.

**Why do I not see `AI Settings`?** It needs its own access, normally held only by the top administrator. Ask your administrator.

## Related topics

- [[control-center]]
- [[roles-and-menu-access]]
- [[ai-tools]]

<!-- sources: routes/web.php, resources/views/admin/sessions.blade.php, resources/views/admin/failed-jobs.blade.php, resources/views/admin/backup.blade.php, resources/views/admin/sounds.blade.php, resources/views/admin/ai-settings.blade.php -->
