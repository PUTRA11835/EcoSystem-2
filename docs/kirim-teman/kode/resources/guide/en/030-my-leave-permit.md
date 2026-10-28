---
key: my-leave-permit
chapter: my-workspace
order: 30
title: My Leave & Permit
summary: Check your leave and permit quotas, apply for leave, and follow or edit your applications.
requires: [ess.my_leave_permit]
updated: 2026-10-27
---
`My Leave & Permit` shows how much leave and permit quota you have, lets you apply for it, and keeps the history of your applications.

## Who uses this

Every employee who has the `My Leave & Permit` menu. HR reviews the applications.

## Before you start

- Know the leave type, the dates and the reason.
- Some types need an attachment, such as a doctor's note. Prepare a PDF, JPG, PNG or DOC file of up to 1 MB.

## Step by step

### Check your quota

1. Open `My Leave & Permit` from the sidebar.
2. Pick the `Year` at the top if you need another year.
3. Read the cards `Total Quota Allocated`, `Quota Used (Approved)`, `Pending Approvals` and `Remaining Quota`.
4. Read the table. For each `Leave / Permit Type` it shows the `Category`, the `Quota Rule` (`Annual Quota`, `Resets Monthly` or `Event-based`), and `Allocated`, `Used`, `Pending` and `Remaining`.

You see only the leave types available to you, for example types that apply to your gender.

### Apply for leave or permit

1. Select `Apply Leave / Permit`.
2. Choose the `Leave / Permit Type`. A hint shows your remaining quota for that type.
3. Choose `Full Day` or `Half-Day Leave (0.5 Day)`. For a half day, state whether you will continue working via WFH or not for the rest of the day.
4. Pick the `Start Date` and `End Date`. You cannot pick a date in the past.
5. Write the `Reason`.
6. Upload an `Attachment` if the type requires it. The form marks it as required.
7. Select the submit button.

> [!IMPORTANT]
> The form blocks the application when the requested days are more than your remaining quota for that type.

### Follow your applications

1. Scroll to your application history.
2. Filter by status with the `All Statuses` list.
3. Read the `Status`: `Pending`, `Approved`, `Ask for Edit` or `Rejected`.
4. Select `View Details` to see the reason, HR notes and the activity log.

### Edit an application

1. Find an application that is `Pending` or `Ask for Edit`.
2. Select `Edit`, change the details and save. The title shows the application number.

## What happens next

HR reviews your application. `Approved` uses your quota. `Ask for Edit` means HR needs a change: read the `HR Notes / Feedback`, edit and resend it. `Rejected` closes the application; apply again if needed. See [[statuses-and-approvals]].

## Common questions

**A leave type I expect is not in the list.** It may be inactive or not available for your profile. Ask HR.

**Why can't I submit?** Check the dates, that the reason is filled, that the attachment is under 1 MB and that your quota is enough.

## Related topics

- [[statuses-and-approvals]]
- [[my-attendance]]
- [[my-calendar-timesheet]]

<!-- sources: routes/web.php, app/Http/Controllers/HR/LeavePermitController.php, resources/views/hr-general/leave-permit/my-leave-permit.blade.php, resources/views/hr-general/leave-permit/components/modal-apply.blade.php, resources/views/hr-general/leave-permit/components/modal-review.blade.php -->
