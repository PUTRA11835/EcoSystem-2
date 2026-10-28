---
key: control-center-security
chapter: administration
order: 62
title: Control Center - Security and platform pages
summary: Watch for suspicious activity, check scheduled tasks, follow AI usage and manage two-step verification and the seasonal theme.
requires: [control-center.security, control-center.schedule-monitor, control-center.ai-usage, control-center.two-factor-enforcement, control-center.seasonal-theme]
updated: 2026-10-28
---
These `Control Center` pages protect the system and keep it healthy. They complement the pages described in the monitoring and system tools topics, and they are meant for administrators only.

## Who uses this

Administrators. Each page has its own access, so you see only the pages your role allows.

> [!IMPORTANT]
> These pages show information about people's activity and about system costs. Use them only for administration and security, and do not share what you see.

## Before you start

- Know what you want to check: a person, a time range or a type of event. Narrow the lists with the filters first.

## Step by step

### Security Center

1. Open `Control Center` › `Security Center`.
2. Read the summary and the list of events. Repeated failed sign-ins, suspicious input and unusual access to sensitive data are flagged here.
3. If an account is locked because of repeated failed attempts, use `Unlock` after you are sure the owner is the one signing in.

### Schedule Monitor

1. Open `Control Center` › `Schedule Monitor`.
2. Read each scheduled task with its last run and status.
3. A task that shows `Never Run` has not run on this server. Check that the scheduler is running there.

### AI Usage

1. Open `Control Center` › `AI Usage`.
2. Choose the period and read the token use and the estimated cost per model, per feature and per user.
3. Enter the balance and the starting date to see the estimated remaining balance. It is an estimate: the provider does not report your balance to EcoSystem.

### Two-Factor Enforcement

1. Open `Control Center` › `Two-Factor Enforcement`.
2. Choose the roles that must use two-step verification.
3. Save. People in those roles are guided to set it up at their next sign-in.

### Seasonal Theme

1. Open `Control Center` › `Seasonal Theme`.
2. Choose the default seasonal theme for the dashboard and the sign-in page, and any sound for it.
3. Save. Individual users can still change their own appearance in `Settings`.

## What happens next

Changes apply to everyone. Unlocking an account lets the person sign in again at once.

## Common questions

**An account keeps getting locked.** Look at the events in `Security Center`. If the attempts are not from the owner, tell your security contact.

**Why is the AI balance different from the provider's console?** The figure in EcoSystem is an estimate from recorded usage. The provider's console is the official figure.

## Related topics

- [[sign-in-and-security]]

<!-- sources: routes/web.php, resources/views/admin/security-center.blade.php, resources/views/admin/schedule-monitor.blade.php, resources/views/admin/ai-usage.blade.php, resources/views/admin/two-factor-enforcement-settings.blade.php, resources/views/admin/seasonal-theme-settings.blade.php -->
