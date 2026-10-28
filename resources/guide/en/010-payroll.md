---
key: payroll
chapter: finance
order: 10
title: Payroll
summary: Create a payroll period, calculate pay, generate and publish payslips, then lock the period.
requires: [finance.payroll, finance.payroll.periods, finance.payroll.settings, finance.payroll.simulation]
updated: 2026-10-27
---
Payroll turns each employee's salary components, attendance, leave, overtime and approved reimbursements into a monthly payslip, with BPJS and PPh 21 included. You work through one period at a time: create it, calculate it, review it, issue the payslips and lock it.

## Who uses this

Finance and payroll staff. The page has three tabs: `Periods`, `Simulation` and `Settings`. You only see the tabs your role allows. If a button is missing, ask an administrator for the right access.

## Before you start

- Check that each employee has salary components, tax status (PTKP) and BPJS details in `Master` › `Employee` (the `Compensation` and `Contract` tabs). Payroll can only use data that is filled in.
- Make sure the rates are correct in [[bpjs]] and [[pph21]].
- Approve or reject overtime, leave, attendance corrections and reimbursements of the period first. Payroll counts only approved items.

> [!IMPORTANT]
> Payroll is switched off until someone with the "Switch payroll on/off" permission switches it on in `Payroll` › `Settings` (`Payroll module`, `Switch payroll on`). While it is off, you can read data, use `Settings` and `Simulation`, but you cannot create, calculate or lock periods. A banner on the page tells you when it is off. Confirm the salary, PTKP, BPJS and PPh 21 data are correct before switching it on.

## Step by step

### Create a period

1. Open `Commercial & Finance` › `Payroll` › `Periods`.
2. In `Create period`, enter the `Name`, `Start`, `End` and `Pay date`.
3. Check the `Attendance period start` and `Attendance period end`. Attendance, leave and overtime are read from this range.
4. Optionally enter a `Cut-off date (optional)`. Workdays after it are treated as fully present and corrected in the next payroll.
5. Click `Create period`. The period appears in the list with the status `Open`.

A period cannot be longer than 62 days and cannot overlap another period.

### Calculate

1. Open the period with `Open` in the list.
2. Click `Calculate`. After the first run the button is called `Recalculate`.
3. Read the totals at the top and any warnings from the calculation.
4. Click `Detail` on an employee row to see the breakdown, or `Recalculate` to recalculate only that employee.
5. To switch BPJS Health, BPJS Employment or PPh 21 on or off for one person in this period, change the checkboxes in the employee row and click `Apply & Recalculate`.

### Add a correction

If an earlier period paid too little or too much, use `Payroll corrections` › `Add correction`. Choose the employee and the `Type` (`Underpaid`, `Overpaid` or `Adjustment`), enter the amount and a reason, then click `Save correction`. Click `Recalculate` to apply it. You can `Remove` a correction while the period is open.

### Generate and publish payslips

1. Click `Generate Slip`. Each employee gets a payslip number and a verification code. Slips already generated do not change.
2. Click `Publish Payslip`. Employees now see the payslip in `My Workspace` › `Paystub`, where they can download the PDF.

You can open a payslip from the employee row (`Slip ID` or `Slip EN` for the PDF language).

### Lock the period

1. Review every amount.
2. Click `Lock`, then confirm.
3. The status changes to `Locked`. After the period is locked, the `Bank Transfer` file becomes available.

> [!WARNING]
> A locked period can never be recalculated, corrected or deleted. Lock only when every amount has been reviewed.

## Export

`Export Excel` downloads the period's payroll. `Bank Transfer` downloads the transfer file, only after `Lock`. Employees without a bank account are left out; a warning on the page tells you how many.

## Simulation

`Simulation` lets you test a pay scenario by typing values, without saving a payroll run. It uses the same BPJS, tax and deduction formulas as real payroll. Use `Run simulation` to see the result.

## Settings

In `Settings` you set the rules used by every period, for example work schedule and proration, absence deduction, late penalty, paid sick/leave/permit days, overtime basis, rounding, cut-off and the payslip signers (HR and Finance). Save with `Save payroll settings`. Two switches are worth knowing:

- `Require approvals before calculating`: refuses `Calculate` while requests of the period still wait for approval.
- `Publish payslips automatically after calculating`: after `Calculate`, payslips are numbered and published without extra clicks. Leave it off if you want to review first.

Items marked "not connected yet" (timesheet allowances, loan deduction) are saved but do not affect payslips.

## What happens next

Published payslips appear in each employee's `Paystub`. Totals feed the BPJS and PPh 21 reports.

## Common questions

**Why can't I click Calculate?** Payroll may be switched off, you may lack edit access, or the period is already `Locked`.

**Can I delete a period?** Only an `Open` period without generated payslips.

## Related topics

- [[bpjs]]
- [[pph21]]
- [[my-paystub]]
- [[employee-data]]
- [[workflow-finance]]

<!-- sources: routes/finance.php, app/Http/Controllers/Finance/PayrollController.php, app/Http/Controllers/Finance/PayrollSettingsController.php, app/Services/Payroll/PayrollPeriodService.php, app/Support/Payroll/PayrollPeriodRules.php, resources/views/finance/payroll/*.blade.php -->
