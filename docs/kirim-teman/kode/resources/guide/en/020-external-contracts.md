---
key: external-contracts
chapter: external
order: 20
title: External Contract
summary: Prepare a consultant's contract as Draft, complete the data to activate it, and print the document.
requires: [general.external.contracts]
updated: 2026-10-27
---
`External Contract` is where HR prepares the contract of an external consultant: the engagement terms, rate, work placement, assignment and signer, ending in a printable document.

## Who uses this

HR staff who manage consultant contracts. Adding, editing and deleting need the matching permissions; the rate needs its own.

## Before you start

- Open `External` › `External Contract`. It has two tabs, `Contracts` and `Settings`.
- Add the consultant first and complete their profile, see [[external-consultants]].
- The contract types, position types and work placements offered on the form are managed in the `Settings` tab, see [[external-contract-settings]].

## Draft and Active

You can prepare a contract as `Draft` at any time. A contract can be set to `Active` only when the consultant's data is complete. In the `Status` list, `Active` is marked `(needs complete consultant data)` until then. The text of the contract is frozen once it is `Active`. `Expired` follows the end date by itself, and only a Draft can be deleted.

The `Contract readiness` card on the consultant's page shows the percentage and what is `Still missing`. Consultants are checked on: ID card number (NIK), mobile number, personal email, domicile address with city and province, bank name, account number and account holder name, and the KTP and Kartu Keluarga documents. Click a missing item to open the right tab in a new tab, so your form is not lost.

## Step by step

### Start a contract

1. Open `Contracts` and select `Add External Contract`.
2. Under `External consultant`, search and select the person, then confirm. You are taken to the form for that consultant.

You can also reach the same page from the consultant's profile with `Manage External Contract`.

### Fill in the form

1. Check the `Consultant summary` and `Contract readiness` at the top.
2. In `New external contract`, leave `Template` on automatic to use the template of the position type, or the default external template. Or choose one.
3. Check the `Contract number`, which is generated; correct it only if needed.
4. Choose the `Contract type`, which decides how the consultant is paid. The `Billing unit` follows it, and you can change it.
5. Set the `Status` to `Draft`, or `Active` when allowed.
6. Choose a `Position type` and enter the `Position / role`.
7. Enter `Start`, `End` (optional) and `Signing date`.
8. Enter the `Rate` and `Currency`. The rate field is hidden if you do not have the special rate permission; saving never changes a rate you cannot see. An administrator can grant the permission.
9. Fill in `Work volume`, `Hours per manday`, `Monthly proration` and `Payment term (days)`.
10. Pick the `Work placement`. Branches from the branch list are offered, plus other places added in Settings, or choose `Other (type it)…` and write it.
11. Enter the `Assignment` (project or scope of work). If left empty, it follows the consultant's engagement.
12. Choose the `Company signatory`.
13. Add `Notes` for internal use.
14. Generate the text, read it in the editor and adjust if needed. Select `Save contract`.

> [!NOTE]
> Only the designated signers can be chosen as signatory. If a required signer is not available, a warning explains it; ask your administrator to register that person as a signer.

### Edit, print or delete

In `Contract history`, each contract shows its number, type, period, rate and status. Use `View` to read the document, `Print` to print or save as PDF, `Edit` to change the form and `Delete` to remove a Draft.

## What happens next

An `Active` contract defines the months for which the consultant can fill a timesheet, see [[external-timesheet-payment]].

## Common questions

**Why can't I choose Active?** The consultant's data is not complete. Fix the listed items first.

**Why is the rate shown as dots?** You do not have permission to see rates.

## Related topics

- [[external-consultants]]
- [[external-contract-settings]]
- [[external-timesheet-payment]]
- [[external-letters]]

<!-- sources: routes/hr-external.php, resources/views/hr-general/external/contracts/index.blade.php, resources/views/hr-general/external/contracts/manage.blade.php, resources/views/hr-general/external/components/signer-warning.blade.php, config/hc_contract.php, config/hc_external.php, app/Http/Controllers/HR_General/ExternalContractController.php -->
