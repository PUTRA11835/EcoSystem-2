---
key: bpjs
chapter: finance
order: 20
title: BPJS
summary: Maintain BPJS rates and wage caps, review contributions per payroll period and issue deactivation letters.
requires: [finance.bpjs, finance.bpjs.settings, finance.bpjs.report, finance.bpjs.letters]
updated: 2026-10-27
---
The BPJS page holds the rates that [[payroll]] uses for BPJS Health and BPJS Employment, a report of contributions per payroll period, and a tool to create BPJS deactivation letters.

## Who uses this

Finance and payroll staff. The page has three tabs: `Settings`, `Report` and `Letters`. You only see the tabs your role allows.

## Before you start

- Employees need their BPJS details filled in on `Master` › `Employee`, and BPJS switched on in their compensation data. Otherwise they are left out of the calculation.
- For letters, employees need a BPJS Health number in their `Identification` data.

## Step by step

### Update the rates (Settings)

1. Open `Commercial & Finance` › `BPJS` › `Settings`.
2. Read `Active rates and new settings`. The form starts with the rates that apply today.
3. Enter the `Effective date` of the new version.
4. Update the `BPJS Health` rates (`Employer (%)`, `Employee (%)`, `Minimum base (IDR)`, `Cap (IDR)`) and the `BPJS Employment` rates (JHT, JP, JKK and JKM, and the `JP cap (IDR)`).
5. Use the two switches `BPJS Health calculated in payroll` and `BPJS Employment calculated in payroll` to include or exclude each group.
6. Add `Notes` explaining the change, then click `Save settings`.
7. Check that a new line appears under `Settings history`.

> [!IMPORTANT]
> Saving creates a new version. Old versions stay in `Settings history` and are not edited. A payroll period uses the latest version whose effective date is on or before the period, so choose the effective date carefully. A minimum base of 0 means no minimum is applied.

Use `BPJS base simulation` (`Sample wage base (IDR)`, `As of date`, `Extra family members`, then `Preview`) to see the contributions before relying on a version.

### Review contributions (Report)

1. Open the `Report` tab.
2. Choose the `Payroll period`.
3. Read the totals: `Employees`, `Total BPJS base`, `Employee deduction` and `Company contribution`.
4. Check the per-employee table. Click `Export Excel` to download it.

### Create deactivation letters (Letters)

1. Open the `Letters` tab.
2. Use `Search employee`, `Branch` and `Status` and click `Filter` to find employees who have a BPJS Health number.
3. Select the employees to include.
4. Under `Letter parameters`, fill in the `Letter date`, `Signer name`, `Signer position`, contact, `Staff name` and `City`.
5. Click `Generate`. A two-page PDF is created: the statement letter and the attachment list. It is also registered in the Letter Register with its own number.

If a letter was made by mistake, click `Void` in `Generated letters` and give a reason. The number stays used.

## What happens next

Payroll applies the BPJS version that matches each period. The `Report` tab fills as payroll periods are calculated.

## Common questions

**Why is an employee missing from the report?** BPJS may be off for that employee, or the period has not been calculated yet.

**Can I change an old rate?** No. Add a new version with a new effective date.

## Related topics

- [[payroll]]
- [[pph21]]
- [[legal-basis]]
- [[employee-data]]

<!-- sources: routes/finance.php, app/Http/Controllers/Finance/BpjsSettingsController.php, app/Http/Controllers/Finance/FinanceReportController.php, app/Http/Controllers/Finance/BpjsLetterController.php, resources/views/finance/bpjs/*.blade.php -->
