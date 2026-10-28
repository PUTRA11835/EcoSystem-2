---
key: legal-basis
chapter: business-legal
order: 10
title: Legal Basis
summary: Keep the register of laws and regulations behind payroll, BPJS, overtime, contract and offboarding rules.
requires: [legal, finance.legal-basis, finance.legal-basis.settings]
updated: 2026-10-27
---
Legal Basis is a register of the laws and regulations behind the calculation rules in EcoSystem: PPh 21, BPJS, Overtime, Contract (PKWT) and Offboarding. For each rule it records the regulation, the date it came into force, when it was last amended and whether someone has reviewed it. The register documents the legal basis. It does not change the formulas or amounts in [[payroll]].

## Who uses this

Legal, HR and Finance reviewers, and administrators. The page has three tabs: `Dashboard`, `Register` and `Settings`. You only see the tabs your role allows.

## Before you start

- Decide who reviews the rules. Marking a rule `Reviewed` is a statement that a responsible person has checked it.

## Step by step

### Read the register

1. Open `Business & Legal` › `Legal Basis` › `Register`.
2. Filter by module if you want only one area, for example BPJS.
3. Read the columns `Regulation`, `In force since`, `Last amended`, `Status` and `Confidence`.
4. Use the source link, when there is one, to open the official text.

Each entry has one of three statuses: `Reviewed`, `Needs review` or `Superseded` (replaced by a newer version and no longer used for calculations).

### Review an entry

1. Check the rule against the official source.
2. Click `Mark reviewed` on the entry. To accept every rule of a module at once, use `Accept review (all)` on that module.
3. Click `Needs review` to send an entry back for checking.

### Add or update a rule

1. Click `Add rule or new version`.
2. Fill in the rule key, `Module`, `Regulation type`, `Number`, year, `Title / subject`, `Article(s)`, `In force since`, and your `Confidence`. Add the official source link and notes when you have them.
3. Save. The entry is added as `Needs review`.

A new effective date creates a new version. The older version stays in the history. Use `Supersede` to retire a version and `Restore` to bring it back as `Needs review`.

> [!IMPORTANT]
> Editing an existing entry sets it back to `Needs review`, because the review applied to the old content. The calculation data field changes calculations that read the rule, so change it only with Legal or Finance approval.

### Choose how modules use the register (Settings)

1. Open the `Settings` tab.
2. Use `Show legal basis information across the app` to show or hide the legal-basis panels and warnings everywhere.
3. For each module, tick `Show panel` and `Warn` as needed, and choose a mode: `Draft mode` (the module runs now and is flagged as draft) or `Enforced` (the module is blocked until its rules are `Reviewed`). The Contract module has no enforced mode.
4. Click `Save switches`. Changes take effect immediately.

> [!WARNING]
> `Enforced` can stop payroll calculation or other features until the listed rules are reviewed. Check the "pending" column first.

## What happens next

Where enabled, other pages such as Payroll show a legal-basis panel or a warning about rules that are not yet reviewed.

## Common questions

**Does changing the register change payroll amounts?** No. Payroll reads its own settings. The register records the legal basis.

**Why do I see only some tabs?** Each tab has its own access.

## Related topics

- [[payroll]]
- [[bpjs]]
- [[pph21]]
- [[contracts]]
- [[offboarding]]

<!-- sources: routes/hr-offboarding.php, routes/web.php, app/Http/Controllers/Finance/LegalBasisController.php, app/Http/Controllers/LegalDashboardController.php, app/Services/Legal/LegalBasisService.php, resources/views/legal/*.blade.php, resources/views/finance/legal-basis/settings.blade.php -->
