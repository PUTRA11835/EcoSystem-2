---
key: guide-ec-user
chapter: reference
order: 90
title: Guide for EC User
summary: What the EC User role can do: see the home dashboard, read the calendar and timesheets, use AI Research, and manage your own account.
requires: ["@role:EC User"]
updated: 2026-10-28
---
This guide is for the EC User role. It is a basic role with a small set of menus. Many people hold it together with another role, and if you do you also see the guide for that role.

## Who uses this

Employees with the EC User role. Your menus are `Home`, `AI Research` and `Calendar` (`Events` and `Timesheets`), plus `Settings` and `User Guide`.

## Before you start

- Your role can read the calendar and your timesheets, but it has no button to create events or log time. This is how the role is set up, not a fault.
- Do not put credentials or customer personal data into AI Research.

## Step by step

### Start from Home

1. Open `Home` after you sign in. Use `Command Center` for pinned shortcuts and read the cards below it. See [[home-dashboard]].

![The Home dashboard of an EC User, with the cards and ticket overview](images/guide/home-dashboard.png)

*Screenshot with sample data.*

### Read the calendar

1. Open `Calendar` › `Events`. Use `Today`, `Month`, `Week` and `Day` to change the view, and the arrows to move between periods.
2. Select an event to read it.

### Check your timesheets

1. Open `Calendar` › `Timesheets`. The cards count your entries by status: `Total`, `Draft`, `Submitted`, `Approved` and `Rejected`.
2. Use the month label at the top right to see whether the period is open, and `Reset Filter` to clear your filters.
3. If you need to log time, ask your administrator to add the permission to your role. See [[calendar-timesheet]].

### Keep your account safe

Open `Settings` and the `Security` tab to change your password and set up `Two-factor authentication`. See [[sign-in-and-security]].

## What happens next

Events and timesheet entries show their latest state each time you open the page.

## Common questions

**I cannot create an event or a timesheet.** The EC User role does not include those buttons. Ask your administrator if you need them.

**I see fewer menus than a colleague.** Menus follow each person's role.

## Related topics

- [[home-dashboard]]
- [[calendar-timesheet]]
- [[sign-in-and-security]]

<!-- sources: routes/web.php, resources/views/home, resources/views/calendar/timesheets.blade.php, resources/views/calendar/events.blade.php -->
