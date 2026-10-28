---
key: leave-permit-management
chapter: human-capital
order: 80
title: Leave & Permit Management
summary: Review leave and permit applications, manage leave types and quotas, and read reports.
requires: [hr_general.leave_permit]
updated: 2026-10-27
---
`Leave & Permit` is the HR page where applications are reviewed, leave types and quotas are managed, and reports are read. Employees apply from My Leave & Permit.

## Who uses this

HR staff and administrators who handle leave and permit.

## Before you start

Open `Human Capital` › `Leave & Permit`. The page can have four tabs, depending on your access: `Approval Inbox`, `Master Data Leave Type`, `All Employee Quotas` and `Reports & Analytics`. A counter on the page shows how many requests are pending.

## Step by step

### Review an application

1. Open `Approval Inbox`. Use the funnels on `Employee`, `Type` and `Status` to narrow the list.
2. Check `Period`, `Total Days` and `Quota Limit Check`, which tells you whether the request is within the remaining quota.
3. Select the application to open `Application Review Details`. Read the reason and open the supporting document if there is one.
4. Type your remarks in `HR Notes / Feedback`. They are required when you reject.
5. Select `Approve`, `Reject` or `Ask for Edit`. With `Ask for Edit` the employee is asked to revise the request.

The status filter offers `All Statuses`, `Pending Only`, `Approved`, `Revision Requested` and `Rejected`. The `Application Activity Log` shows each step.

> [!NOTE]
> If you also have edit access, `Edit Details (HR Override)` lets you correct the details of an application.

### Log leave on behalf of an employee

1. Select `Log Leave / Permit` at the top of the page.
2. Choose the employee and the leave type.
3. Enter the dates. The days are previewed and checked against the quota.
4. Submit the form.

### Manage leave types

1. Open `Master Data Leave Type`.
2. Select `Add New Leave Type`, or edit an existing one.
3. Fill in `Code`, `Category`, `Leave Type Name` and `Default Quota (Days)`. Set `Min Service Period`, `Paid Status`, `Gender Target`, whether a file attachment is required, and a description.
4. Save. Each type can be switched between `Active` and `Nonactive`.

### Check quotas and reports

- `All Employee Quotas` lists every employee's quota. Filter by employee name or ECI and open a row for detail.
- `Reports & Analytics` shows totals such as approved days taken and rejected or revised requests, with tables per employee and per leave type.

You can use `Export` on the page to download applications.

## What happens next

The employee sees the decision in My Leave & Permit. Approved leave shows as `Leave / Permit` in the attendance recap, see [[attendance-management]].

## Common questions

**Why can't I approve?** You need edit access on the Approval Inbox tab. Without it you can only read.

**What does a quota warning mean?** The requested days exceed the remaining balance of that leave type.

## Related topics

- [[attendance-management]]
- [[overtime-management]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/leave-permit/index.blade.php, resources/views/hr-general/leave-permit/components/modal-review.blade.php, resources/views/hr-general/leave-permit/components/modal-type-form.blade.php -->
