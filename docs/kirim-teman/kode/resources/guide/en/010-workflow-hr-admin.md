---
key: workflow-hr-admin
chapter: reference
order: 10
title: Workflow for HR admin
summary: The usual path of an employee record through HR work, from joining to pay and leaving.
requires: [general.onboarding, general.contracts, finance.payroll]
updated: 2026-10-27
---
This page shows how the main HR tasks connect, in the order they usually happen. Each step links to the topic with the detailed instructions.

## Who uses this

HR administrators and people who support them. You see the steps for the menus you can open.

## The path at a glance

| Stage | Where | What you do | Details |
|---|---|---|---|
| 1. Hire | `Human Capital` › `Recruitment`, `Offering Letter` | Run the selection process and send the offer | [[recruitment]], [[offering-letter]] |
| 2. Record | `Human Capital` › `Employee` | Create the employee and complete their data | [[employee-data]] |
| 3. Onboard | `Human Capital` › `Onboarding` | Follow the progress, then verify and lock the data | [[onboarding]] |
| 4. Contract | `Business & Legal` › `Contract` | Prepare, activate and sign the contract | [[contracts]] |
| 5. Daily work | `Human Capital` › `Attendance`, `Leave & Permit`, `Overtime` | Review attendance corrections, leave and overtime | [[attendance-management]], [[leave-permit-management]], [[overtime-management]] |
| 6. Pay | `Finance` › `Payroll`, `BPJS`, `PPh 21` | Calculate, generate slips and publish | [[payroll]], [[bpjs]], [[pph21]] |
| 7. Leave the company | `Human Capital` › `Offboarding` | Run the exit process | [[offboarding]] |

## Step by step

1. Complete the employee's data before anything else. A contract cannot be activated while required data is missing, and `Onboarding` shows what is still open.
2. In `Onboarding`, check the progress, then use `Verify & Lock`. A locked profile shows `View Only` to the employee. HR can unlock it, and a reason is required.
3. Prepare the contract from the employee's data. Drafts are flexible; activation needs the data to be complete.
4. During the month, handle attendance corrections, leave and overtime requests so they are settled before payroll.
5. In `Payroll`, work through the period: `Calculate`, then `Generate Slip`, then `Publish Payslip`. If the automatic option in Payroll `Settings` is on, finishing `Calculate` also numbers and publishes the slips, and employees see them in `Paystub` right away.
6. When someone leaves, start `Offboarding`.

> [!IMPORTANT]
> Payroll shows in `Settings` whether it is `Switched on`. If it is off, ask the person who is allowed to switch it on before you start a period.

## What happens next

Employees see their new data, contract and slips in their own workspace. See [[workflow-employee]] for their side.

## Common questions

**Where do I start with a new employee?** In `Employee`, then `Onboarding`.

**A step is missing from my sidebar.** Each menu has its own access. See [[access-and-menus]].

## Related topics

- [[workflow-finance]]
- [[workflow-employee]]
- [[approval-workflows]]
- [[faq]]

<!-- sources: resources/views/partials/sidebar.blade.php, resources/views/finance/payroll/settings.blade.php, docs/HRGA/05-LOG-SESI.md -->
