---
key: attendance-management
chapter: human-capital
order: 70
title: Attendance Management
summary: Review daily and monthly attendance, decide correction requests, and set shifts and location rules.
requires: [general.attendance, general.attendance.correction, general.settings.attendance, general.settings.shifts]
updated: 2026-10-27
---
`Attendance` gives HR the recap of everyone's check-in and check-out, a place to decide correction requests, and the settings that control shifts and location checks.

## Who uses this

HR staff who monitor attendance. Employees record their own attendance in My Attendance.

## Before you start

Open `Human Capital` › `Attendance`. The hub has up to five tabs: `Daily Recap`, `Monthly Recap`, `Corrections`, `Shifts` and `Settings`. You see the tabs your role allows.

## Step by step

### Read the daily recap

1. Open `Daily Recap`.
2. Choose the `Date`, then optionally a `Department`, a `Status` and a `Search` term (name, employee code, position or department). Select `Apply`.
3. Read the cards: `Recorded`, `Present`, `Late`, `Absent`, `Sick` and `Leave / Permit`.
4. In the table, check `Shift`, `Check-in`, `Check-out`, the locations, `Late`, `Early Leave`, `Work` and `Overtime`. A row marked `Corrected` has an approved correction.
5. Select `Export Excel` to download the day.

### Read the monthly recap

1. Open `Monthly Recap`.
2. Search by `Employee Name` and choose the `Month` and `Location`.
3. Read one row per employee and one column per day. Hover a day to see the in and out times and late minutes. The summary cards show `Total Employees`, `Work Days`, `Average Present` and `Complete Days`.

### Decide a correction request

1. Open `Corrections`. Cards show `Pending Review`, `Approved` and `Rejected`.
2. Set `Status` to `Pending` to see the requests waiting for you.
3. Compare `Current Data` with `Proposed Correction`, and read the `Reason`.
4. Type an HR note in the field provided. The note is optional to approve but required to reject.
5. Select `Approve` or `Reject`. After an approval the attendance is updated and the previous times are kept on record.

### Manage shifts

1. Open `Shifts` and select `Add Shift`.
2. Set the shift name, working hours, late tolerance, break and working days.
3. Use the assign action on a shift to choose the employees on it.

A shift can be the default, overnight, and active or inactive.

### Change the attendance rules

In `Settings` you can adjust:

- `Geofence Mode`: `Off` (no location check), `Flag` (attendance outside the radius is recorded and marked for HR review, recommended) or `Enforce` (attendance outside the radius is rejected), plus the `Accuracy Threshold (meters)`.
- `Source & Default Hours`: the maximum active shifts per employee, the default check-in and check-out, late tolerance and the hours after which a forgotten check-out is closed automatically.
- `Corrections`: whether employees may submit their own corrections and the `Correction Window (days)`.
- `Attendance Sources`: the sources that attendance can come from.

> [!IMPORTANT]
> Check-in and check-out are recorded once a day. Corrections are the only way to change a time.

## What happens next

Approved corrections update the recaps. Employees see the decision and your note in My Attendance. Overtime requests are compared with the attendance record, see [[overtime-management]].

## Common questions

**Why is someone Late?** The check-in was later than the shift start plus the late tolerance.

**Can employees submit corrections?** Only when the setting is on; otherwise HR adjusts the times.

## Related topics

- [[overtime-management]]
- [[leave-permit-management]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/attendance/daily.blade.php, resources/views/hr-general/attendance/monthly.blade.php, resources/views/hr-general/attendance/corrections.blade.php, resources/views/hr-general/attendance/settings.blade.php, resources/views/hr-general/attendance/shifts/index.blade.php, resources/views/hr-general/attendance/components/hub-tabs.blade.php -->
