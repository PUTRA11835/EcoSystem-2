---
key: workflow-finance
chapter: reference
order: 40
title: Workflow for Finance
summary: How Finance handles payroll, reimbursements, purchases and cash advances from request to payment.
requires: [finance.payroll, general.reimbursement, general.purchase-request]
updated: 2026-10-27
---
Finance receives approved requests from employees and runs the monthly payroll. This page shows how those jobs fit together and links to the detailed topics.

## Who uses this

Finance staff and anyone who manages payroll, reimbursements, purchases or cash advances. You see only the menus your role includes.

## The monthly rhythm

| Job | Where | What you do | Details |
|---|---|---|---|
| Payroll | `Finance` › `Payroll` | Create a period, calculate, generate slips, publish | [[payroll]] |
| BPJS | `Finance` › `BPJS` | Review contribution reports and letters | [[bpjs]] |
| PPh 21 | `Finance` › `PPh 21` | Review tax settings and reports | [[pph21]] |
| Reimbursement | `Finance` › `Reimbursement Management` | Process approved claims | [[reimbursement-management]] |
| Purchasing | `Finance` › `Purchase Management` | Process approved purchase requests and orders | [[purchase-management]] |
| Cash Advance | `Finance` › `Cash Advance Management` | Process cash advances and their reports | [[cash-advance-management]] |

## Step by step

1. Before the month ends, check that attendance, leave and overtime are settled. Payroll reads them from the attendance period you set when you create the period.
2. In `Payroll`, click `Create period` and enter the attendance period start and end.
3. Open the period and click `Calculate` (or `Recalculate` after a change). Review the figures per employee.
4. Click `Generate Slip` to give each employee a payslip number. Employees cannot see slips yet.
5. Click `Publish Payslip`. Employees now see their slip in `Paystub` and can download it as PDF.
6. If the automatic option in Payroll `Settings` is on, `Calculate` also generates and publishes the slips, so check the figures first.
7. Watch for the warning about employees with take-home pay but no bank account. They are left out of the bank transfer file until you add their bank details or pay them separately.
8. During the month, work through approved reimbursements, purchase requests and cash advances in their management pages.

> [!IMPORTANT]
> Published payslips are visible to employees at once. Review the calculation before you publish.

## What happens next

Employees read their slips in `Paystub`. Requests you finish move to their final status and the requester is informed. See [[workflow-employee]] for the employee side.

## Common questions

**The payroll buttons are missing.** Each action has its own access. Ask your administrator.

**Where do requests come from?** Employees submit them and approvers decide in `My Approvals`. See [[workflow-manager]].

## Related topics

- [[payroll]]
- [[workflow-hr-admin]]
- [[workflow-manager]]
- [[faq]]

<!-- sources: resources/views/finance/payroll/index.blade.php, resources/views/finance/payroll/show.blade.php, resources/views/finance/payroll/settings.blade.php, resources/views/partials/sidebar.blade.php -->
