---
key: my-attendance
chapter: my-workspace
order: 20
title: My Attendance
summary: Check in and out, review your attendance history, and ask HR to correct a wrong or missing time.
requires: [general.my-attendance]
updated: 2026-10-27
---
`My Attendance` records your daily Check-in and Check-out from your own account and shows your history for the last 30 days.

## Who uses this

Every employee who has the `My Attendance` menu.

## Before you start

- Allow your browser to share your location when it asks. The system captures your device location only at the moment you press the button; nothing is tracked in the background.
- Use a secure (HTTPS) connection and keep the location service of your device switched on.

## Step by step

### Check in and check out

1. Open `My Attendance` from the sidebar. You can also use the `Check-in` button on the `Dashboard`.
2. Select `Check-in` when you start work. Wait for the message `Requesting location...` and then `Saving...`.
3. Select `Check-out` when you finish. The button is available only after you have checked in.
4. Check the cards `Check-in` and `Check-out`. They show the time, the source and the location result.

Check-in and check-out are recorded once per day. When both are filled, the page shows that today's attendance is complete.

> [!NOTE]
> Depending on your company's rules, a punch made outside the allowed location is either marked for HR review or refused. If a punch is refused, the message tells you why.

### Read your numbers

- The month cards show `Present This Month`, `Late`, `Work Hours` and `Overtime Hours`.
- Under each punch, `View on map` opens the recorded point. `GPS accuracy` is rated Good, Fair or Low, and `Technical details` holds the raw data.
- The `30-Day History` table lists `Date`, `In`, `Out`, `Work`, `Method`, `Location` and `Status` for each day.

### Request a correction

1. Select `Request correction`, or select `Correct` on a row in `30-Day History`.
2. Choose the `Date`. You can go back only as many days as the page shows under the field.
3. Enter `New check-in time`, `New check-out time`, or both.
4. Write the `Reason`, at least 10 characters, for example that you forgot to check out after a client visit.
5. Select `Submit correction`.

Only one pending correction is allowed per date. If you see a message that corrections are disabled, contact HR directly.

### Follow or cancel a correction

1. Scroll to `My Correction Requests`.
2. Read the `Status`: `Pending`, `Approved`, `Rejected` or `Cancelled`. HR may add a note.
3. Select `Cancel` on a `Pending` request if you no longer need it.

## What happens next

HR is notified of your correction. After HR approves it, your record is updated and the row in `30-Day History` shows `Correction` under `Method`. If HR rejects it, read the note and submit a new request if needed.

## Common questions

**The location fails.** The page explains the cause, such as permission denied or needing a secure connection. Allow location for the site in your browser settings and try again. If the page offers `Test location access`, use it and send the result to HR.

**I punched the wrong time.** Punches cannot be undone; use `Request correction`.

## Related topics

- [[statuses-and-approvals]]
- [[my-overtime]]
- [[my-leave-permit]]
- [[navigating-ecosystem]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/attendance/my-attendance.blade.php, app/Http/Controllers/HR_General/MyAttendanceController.php, app/Http/Controllers/HR_General/AttendanceCorrectionController.php, app/Models/Attendance/AttendanceSetting.php, resources/views/hr-general/dashboard/attendance.blade.php -->
