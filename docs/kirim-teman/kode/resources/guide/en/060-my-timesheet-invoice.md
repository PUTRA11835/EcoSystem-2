---
key: my-timesheet-invoice
chapter: external
order: 60
title: My Timesheet & Invoice
summary: Log your hours each month as an external consultant, submit them, then upload your invoice.
requires: [general.external.my-timesheet]
updated: 2026-10-27
---
`My Timesheet & Invoice` is the consultant's own page. You log the hours you worked for each month of your contract, submit them for approval, and upload your invoice once the timesheet is approved.

## Who uses this

External consultants. Internal staff use `My Timesheet` in the calendar instead.

## Before you start

- Open `My Timesheet & Invoice` from the sidebar.
- You need an active external contract. If you see `No active contract yet`, your timesheet is tied to your contract; when HR activates it, its months appear here.
- The page shows four steps: log hours, approval, invoice, payment.

## Step by step

### Start a month

1. Under `Months of your contract`, find the month you want.
2. Select the month. If it has no timesheet yet, selecting it starts one. A month that already has one shows its status.

### Log your hours

1. Select `Add row`.
2. Enter the date, the project (optional), the start and end time, the location and what you worked on. The hours are calculated from start and end time; without them, type the hours.
3. Repeat for each day you worked.
4. Select `Save draft` to keep your work.

Dates must be inside the month, inside your contract period and not in the future. If HR has set a back-fill limit, you can only log days up to that many days back. The page shows your `Total hours`, the billable quantity, and the estimated amount if you are allowed to see amounts.

### Submit for approval

1. Check the rows. For a fixed-price contract, enter the share of the price you bill in this timesheet.
2. Select `Submit for approval`.

The status becomes `Submitted`. You can no longer change the rows while it waits. If the approver returns it, the status is `Returned` with their reason. Correct the rows and submit again.

### Upload your invoice

1. When the timesheet is `Approved`, the invoice form opens.
2. Enter the `Invoice number`, `Invoice date` and `Amount`.
3. Attach the `Invoice (PDF)` and select `Upload invoice`.

The invoice status is `Waiting verification`, `Verified`, `Rejected` or `Paid`. If it is `Rejected`, read the reason and select `Upload corrected invoice`. When it is `Paid`, the payment date and reference are shown.

You can print the timesheet from the page, and view the invoice you uploaded.

## What happens next

The designated approver reviews your hours, then your invoice is verified and paid within the payment term of your contract. Every step is recorded in `History` on the page.

## Common questions

**Why is a month missing?** Months come from your contract period. Ask HR if your contract dates are wrong.

**I submitted by mistake.** Ask the approver to return it, then correct it.

**Who approves my timesheet?** HR sets the approvers for each consultant. The page shows who is waiting.

## Related topics

- [[external-timesheet-payment]]
- [[external-contracts]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/timesheet/my.blade.php, resources/views/hr-general/external/timesheet/components/sheet.blade.php, app/Support/External/ExternalTimesheetRules.php, resources/views/partials/sidebar.blade.php -->
