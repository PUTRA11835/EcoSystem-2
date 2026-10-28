---
key: contracts
chapter: human-capital
order: 50
title: Contracts
summary: Prepare employment contracts as Draft, complete the data to activate them, manage templates and run signing.
requires: [general.contracts, general.contracts.list, general.contracts.templates]
updated: 2026-10-27
---
The `Contract` menu is where HR prepares employment contracts for internal employees: from a Draft, to an Active contract, to a signed copy released to the employee.

## Who uses this

HR staff who prepare contracts, and Legal staff who run the signing. Employees and approvers sign from My Profile.

## Before you start

Open `Human Capital` › `Contract`. It has up to four tabs, depending on your access: `Dashboard`, `Contracts`, `Templates` and `Settings`.

- `Dashboard` shows contracts ending soon, the latest records, and totals by month, type and department.
- `Contracts` lists every contract with `Employee`, `Contract No.`, `Type`, `Department`, `Start`, `End` and `Status`. Use `View`, `Print`, `Edit` or `Manage` on a row.
- `Templates` holds the contract wording.
- `Settings` controls the contract types and position types offered on the form.

## Draft and Active

| Status | Meaning |
|---|---|
| `Draft` | Being prepared. You can save a Draft at any time, even when data is missing. Only a Draft can be deleted. |
| `Active` | The running contract. The wording is frozen. It needs complete employee data. |
| `Expired` | Set automatically when the end date has passed. |
| `Terminated` | Set by HR when the contract is ended early. |

### Complete the data first

On an employee's `Manage` page, the `Contract Readiness` card shows a percentage and the items still missing. By default you can only set a contract to `Active` when it reaches 100%. Internal employees need:

- Identity numbers: NIK, NPWP, BPJS Kesehatan and BPJS Ketenagakerjaan.
- Contact: mobile number, personal email, domicile address, city and province.
- Emergency contact name and relation.
- Bank name, account number and account holder name.
- Documents: KTP, Kartu Keluarga, CV and Ijazah. The NPWP card is optional.

If something is missing, the page lists each item. Click an item to open the right tab of the employee's profile in a new tab, so what you typed in the contract form is kept. `Complete Employee Data` opens the profile.

## Step by step

### Create a contract

1. Open `Contracts`, then select `Manage` on the employee's row (or search for the person).
2. Check `Contract Readiness`.
3. Select `Add Contract`.
4. Choose the `Contract type` and `Start date`, then either a `Contract duration` or an `End date`. Contract numbers are generated for you.
5. Optionally set the `Department`, `Position type`, `Position` and `Work location`. They follow the employee data when left empty.
6. Pick the `Template` or leave it on automatic: the template of the position type, else of the position, else the general one.
7. Choose the `Company signatory`.
8. Enter the `Contract basic salary` and `Allowances` if your access shows them.
9. Review the generated text in the editor and adjust it if needed.
10. Set `Document status` to `Draft` or, when ready, `Active`, then select `Save Contract`.

> [!NOTE]
> A fixed-term (PKWT) contract cannot run longer than five years.

### Manage templates

1. Open `Templates` and select a template, or create a new one.
2. Set `Template name`, `Status`, `Contract type`, and optionally `Position type` and `Position`.
3. Choose a default `Company signatory` and the `Approver` who signs after the employee.
4. Write the `Contract Text` like in a word processor.
5. Select `Create Template` or `Save Template`.

A system default template can be edited but not deleted. Letterheads and signers are managed in Document Identity, see [[letter-templates]].

### Sign a contract

Signing starts once a contract is not a Draft. The steps are `E-stamped`, `Employee signs`, `Approver signs`, `Print` and `Released`.

1. Open the contract document, download the PDF, apply the stamp, and upload the stamped copy.
2. Select `Send to employee`, set a deadline, choose the `Approver` and add a note. The employee is notified.
3. The employee reviews and signs every page from My Profile. You can open the `Signing record` at any time.
4. Select `Forward to approver`. The approver signs.
5. Print the approved contract, then select `Release to employee` so the employee can download it.

If a signer asks for a change, edit the contract, upload a new stamped copy and send it again. You can withdraw a contract from signing. The signing buttons need the contract signing permission.

## What happens next

An Active contract appears in the employee's contract history and counts for [[onboarding]]. When someone leaves, [[offboarding]] closes the running contract.

## Common questions

**Why can't I choose Active?** The employee data is not complete yet. Save as Draft and fill the missing items.

**Why is the salary hidden?** Salary values need a separate permission.

## Related topics

- [[employee-data]]
- [[letter-templates]]
- [[offboarding]]
- [[external-contracts]]

<!-- sources: routes/hr-general.php, config/hc_contract.php, app/Support/Contracts/ContractRules.php, app/Http/Controllers/HR_General/ContractController.php, resources/views/hr-general/contracts/show.blade.php, resources/views/hr-general/contracts/index.blade.php, resources/views/hr-general/contracts/components/signing-panel.blade.php, resources/views/hr-general/contracts/template-form.blade.php -->
