---
key: external-timesheet-payment
chapter: external
order: 50
title: Timesheet & Payment
summary: Open and review consultant timesheets, verify invoices, record payments and set the approvers.
requires: [general.external.timesheet, general.external.timesheet.invoices, general.external.timesheet.settings]
updated: 2026-10-27
---
`Timesheet & Payment` follows a consultant's month from logged hours, to approval, to an invoice, to payment. It has three tabs: `Timesheets`, `Invoices & Payments` and `Settings`.

## Who uses this

HR and the approvers named in `Settings`. Consultants fill in their own timesheet in `My Timesheet & Invoice`, see [[my-timesheet-invoice]].

## Before you start

- Open `External` › `Timesheet & Payment`. You see the tabs your access allows.
- A timesheet belongs to a month covered by an `Active` consultant contract, see [[external-contracts]].
- Who approves or verifies is set in `Settings`. Approving, returning and verifying follow those approvers, not general permissions.
- Money amounts are shown only to people with permission to see them.

## Step by step

### Open a timesheet

1. In `Timesheets`, select `New Timesheet`.
2. Choose the `External consultant` and the `Month`. Only months covered by the consultant's contract and without a timesheet yet are offered.
3. Select `Create & open`.

Use this to fill hours on behalf of a consultant. The consultant sees the timesheet in `My Timesheet & Invoice`.

### Fill the hours

1. Select `Add row` and enter the date, project, start and end time, hours, location (`Onsite`, `Remote` or `Hybrid`) and activity. Start and end time fill the hours; without them type the hours.
2. Dates must be inside the month, inside the contract period and not in the future.
3. Select `Save draft`, then `Submit for approval`.

The sheet shows `Total hours`, `Billable quantity`, the rate and the gross amount. For a fixed-price contract you also enter the share of the price billed in this timesheet.

### Approve or return

1. Open a `Submitted` timesheet. If you are an approver you see `Approve timesheet` and `Return for correction`.
2. Select `Approve`, or type a `Reason (required)` and select `Return`.

The statuses are `Draft`, `Submitted`, `Returned` and `Approved`. Approval locks the timesheet and lets the consultant upload an invoice. A returned timesheet can be corrected and submitted again.

### Verify the invoice and record payment

1. Open `Invoices & Payments`. It lists approved timesheets and their invoices.
2. Open one and use `View invoice` to read the invoice. The invoice status is `Waiting verification`, `Verified`, `Rejected` or `Paid`.
3. Select `Verify invoice`, or type a reason and select `Reject`.
4. After payment, select `Mark as paid` and enter the `Payment date` and `Payment reference`.

A `History` list on the page records each step.

### Set the approvers

1. Open `Settings`.
2. Under the two stages, `Timesheet approval` and `Invoice verification`, select `Add step`. Name the step and choose roles or people.
3. For a special case, add an `Exception for a consultant` so other people approve that consultant.
4. In `Options`, decide whether an approver may act on their own timesheet (off by default) and set the `Back-fill limit (days)`, which is how far back a consultant may log a day (0 means no limit). Select `Save options`.

Anyone listed in a step may act on that stage. A change applies to timesheets submitted afterwards; timesheets already waiting keep the approvers they had when they were submitted.

## What happens next

After payment, the invoice shows `Paid`. The consultant sees every status in `My Timesheet & Invoice`.

## Common questions

**Why can't I approve?** You are not listed as an approver for that consultant, or the timesheet belongs to you and the option to approve your own is off.

**Why is no month offered?** The consultant has no Active contract for it, or already has a timesheet.

## Related topics

- [[my-timesheet-invoice]]
- [[external-contracts]]
- [[external-consultants]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/timesheet/index.blade.php, resources/views/hr-general/external/timesheet/components/sheet.blade.php, resources/views/hr-general/external/timesheet/settings.blade.php, app/Support/External/ExternalTimesheetRules.php -->
