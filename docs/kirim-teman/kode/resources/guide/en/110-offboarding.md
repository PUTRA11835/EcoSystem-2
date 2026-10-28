---
key: offboarding
chapter: human-capital
order: 110
title: Offboarding
summary: Start an offboarding, work the checklist, calculate the final settlement, deactivate access and complete.
requires: [general.offboarding, general.offboarding.list, general.offboarding.settings]
updated: 2026-10-27
---
`Offboarding` guides HR through a person leaving: a checklist of tasks, a final settlement for employees, deactivation of their access on the effective date, and completion, which closes the contract.

## Who uses this

HR staff. The page has two tabs, `Offboardings` and `Settings`; you see the ones your role allows.

## Before you start

- Open `Human Capital` › `Offboarding`.
- If a banner says `Offboarding is switched off`, no new offboarding can be started until it is switched on in `Settings`. Existing records stay visible.
- The checklist is built from the task templates in `Settings`.

## Step by step

### Start an offboarding

1. Select `Start Offboarding`.
2. Choose the `Employee or consultant` and the `Offboarding type`. Employees can be `Resignation`, `End of PKWT`, `Retirement`, `Termination (PHK)`, `Mutual agreement` or `Death`. Consultants can be `End of engagement`, `Termination (PHK)` or `Mutual agreement`.
3. Enter the `Reason for the termination`, `Effective date`, `Last working day` and `Notes`.
4. Select `Start & open`.

The status is `In progress`. The page shows four steps: checklist, settlement, access, completed.

### Work the checklist

1. Under `Offboarding Checklist`, tick each task as `Done` and add a short note.
2. Watch `Offboarding Progress`: completed tasks, and overdue tasks if any.

### Calculate the final settlement

Employees only. A consultant has no settlement; closing the engagement, assets and access are handled by the checklist.

1. In `Final Settlement Calculation`, check `Offboarding type`, `Contract type` and `Effective date`.
2. Enter the `Offboarding reason (PHK code)`, `Last working day`, `Paid leave balance (days)`, `Separation pay`, `Other compensation` and, for retirement, the retirement benefit estimate.
3. Leave `Wage base (override)` empty to use the contract's basic salary plus allowances.
4. Select `Calculate & Save`.

The calculator can be switched off, or blocked until the legal basis entries have been reviewed. Both cases are explained on the page, with a link to `Settings` or to the legal register. See [[legal-basis]].

### Deactivate access

1. Select `Deactivate access`. It is available on the effective date, and after the other tasks are done when that rule is on.
2. Confirm. The person can no longer sign in; roles and history are kept.

If `Deactivate access automatically` is on in `Settings`, this happens by itself on the effective date.

For urgent cases, `Settings` has `Allow force deactivate`. When it is on and the access cannot yet be deactivated normally, a box appears for a reason (at least 5 characters, kept in the employee history). Select `Force deactivate` to deactivate before the date and without waiting for other tasks.

> [!WARNING]
> Deactivating access stops the person from signing in right away. Check the effective date and the checklist before you confirm.

### Complete or cancel

1. When all tasks are done and access is deactivated, select `Complete`. The running contract is closed as `Terminated`.
2. To stop an offboarding that has not deactivated access yet, use `Cancel offboarding` and give a reason.

### Settings

`Settings` has the switches `Offboarding`, `Final settlement calculator`, `Deactivate access automatically`, `Include external consultants`, `Require the other tasks first` and `Allow force deactivate`. It also sets the reminder days and the leave divisor, and lets you add, edit or delete checklist tasks (`Add task`). Changes to tasks do not affect offboardings already started.

## What happens next

Completed offboardings stay as records. The employee no longer signs in.

## Common questions

**Why is Deactivate access greyed out?** It unlocks on the effective date. It also waits for the other tasks when that rule is on.

## Related topics

- [[contracts]]
- [[employee-data]]
- [[inventory-assets]]

<!-- sources: routes/hr-offboarding.php, resources/views/hr-general/offboarding/index.blade.php, resources/views/hr-general/offboarding/show.blade.php, resources/views/hr-general/offboarding/settings.blade.php, app/Services/Offboarding/OffboardingService.php, app/Support/Offboarding -->
