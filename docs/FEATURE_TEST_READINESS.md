# Feature test readiness — what is still weak, and what to run before real use

Written from the code and from rendering the pages through the app (no browser). "Verified" means it was run; "Not verified" means nobody has
exercised it with real data or a real second user yet. Each section ends with the scenarios that must pass.

## 0. Test environment (fix first — it decides whether tests mean anything)

| Problem | Effect | Fix |
|---|---|---|
| This PHP has no `pdo_sqlite` loaded | The new test classes (ApprovalEngine, PayrollAudit, ReimbursementPayment, CarReminderService) **skip** under plain `php artisan test` | Enable `pdo_sqlite` / `sqlite3` in `php.ini` and in CI, or run `php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit` |
| `tests/Feature/ExampleTest` expects `/` to return 200 | Always fails (home redirects to login) — hides real failures | Delete it, or assert the 302 to login |
| No test runs against the real schema | Permissions, middleware and page flows are only checked by hand; the migrations are MySQL-specific and cannot run on SQLite | Add a dedicated MySQL test database (`APP_ENV=testing`) and write HTTP tests on it |
| Reminders need a scheduler | `cash-advance:car-reminders` only runs if the server has the `schedule:run` cron (every minute) | Confirm the cron exists on staging / production |
| Dev data is thin | 0 payroll periods; only Reimbursement has documents waiting, and the approver is the administrator | Create fixtures (one document per module, one outside approver) before testing |
| Nothing is queued | PDF payslips, bulk payroll, imports/exports and notifications all run inside the request | Not built yet (see section 9) |

## 1. Approvals (engine, My Approvals, approver review page)

Verified: engine rules on SQLite; inbox and review page render for the administrator; a person with no part in a document is redirected on the
review page, the inbox, the HR detail page and the export. Notification links now go to My Approvals (they used to point at HR pages an outside
approver cannot open); the Overtime list is now limited to "waiting for me" for someone who is only named on the workflow.

Not verified / weak:
- **No real outside approver has been tested.** The only pending documents are Reimbursement and the approver holds the HR menu.
- Purchase Request, Cash Advance, Cash Advance Report and Overtime review pages have never been rendered with a pending document.
- **"Direct manager" and "project leader" steps still do not work** for Purchase Request, Reimbursement, Cash Advance and CAR (their models return false; the UI says "not available yet"). Only Overtime resolves them. `ApproverResolver` exists but is not wired into those create paths.
- The **Edit page** (for approvers given the Edit box) still uses the HR layout, and **after a Delete** the redirect goes to the module list, which an outside approver cannot open.
- The inbox lists only what waits *now*; there is no "what I already decided" history.
- The self-approval / fallback-role / adjust-amount blocks are still copy-pasted across the four settings pages.
- A designated approver can reach `print` of a document they take part in; confirm that is wanted.

Scenarios: (1) an employee with only self-service access, named by position on a Purchase Request step → bell notification opens My Approvals → review → approve → next step moves on. (2) Same person tries `/general/purchase-request`, another employee's document URL, export and delete → all refused. (3) Reject without a note is refused. (4) Requester who is also the approver with self-approval off → refused with the fallback role named. (5) Locked period with "block everyone" → refused. (6) Give the position the "My Approvals" Edit box + module setting on → Edit works only on their own turn; revoke → gone.

## 2. Legal, Document Identity, sidebar moves

Verified: pages render for the administrator and for a Legal user; migrations run up/down/up.

Weak:
- **HO Legal Administrator still holds the full Letter Templates slug** (numbering, letter types, request types), which is what the split was meant to avoid. Untick it in Management → Roles if Legal should only have Document Identity.
- The Legal dashboard has no "external contracts ending soon"; "awaiting signature" counts every contract in the signing flow, not only Legal's.
- Cards depend on each viewer's own slugs: a Legal user with only the `legal` page sees an empty-state sentence.
- Contract moved under Legal and Inventory & Assets under Human Capital; the Menu Access screen groups changed too — check roles still show the right modules.
- Letterhead images are served by a route gated by the new letterheads slug **or** the old one; confirm contract PDFs render for a Legal user who has neither Letter Templates nor Letterheads View.

Scenarios: Legal user adds a letterhead and a signer → a contract uses them → PDF has the letterhead; Legal user cannot open numbering/letter types; HR still can; old `?section=signers` bookmark redirects.

## 3. Reimbursement payment

Verified: rules on SQLite (only approved, once, not in the future, reversal needs a reason, requester notified); list, filters and detail render.

Weak:
- **Double payment with payroll.** Payroll can add an approved reimbursement to a payslip. Now: a claim already marked paid is excluded from payroll, and the payment form warns when the claim is already in a payroll run. There is still **no automatic "paid via payroll"** status — a claim paid through salary stays "awaiting payment" until Finance records it.
- No payment batch (mark many as paid), no payment export for Finance, and no report of what was paid in a month.
- Reversal keeps its reason only in the payment note (no separate history table).
- Existing approved documents all show "awaiting payment" — Finance must mark the ones already paid, or they will look unpaid.
- Notification type titles (`reimbursement_paid`, `reimbursement_payment_reversed`) use the default title.

Scenarios: approve → awaiting → pay with date + reference → paid shows for requester; pay twice refused; future date refused; reverse with reason; a claim paid here is not picked up by the next payroll calculation; a claim already in payroll shows the warning.

## 4. Cash advance report (CAR) overdue and reminders

Verified: day rules (pure tests), the service on SQLite (one message per day, only unreported advances, off switch, dry run), pages render.

Weak:
- Reminders go to the requester only: **no escalation** to a supervisor or Finance for long-overdue reports.
- A report waiting for review (`reporting`) is not reminded — intended, but a *rejected* report returning to "unreported" must be checked to restart the reminders.
- Dates use the server time zone and `completed_at` (approval) — confirm the time zone and that an approval late in the day counts the way the business expects.
- Settings link: the deadline field is shared with the older "late report" flag; changing it changes both.
- Real delivery relies on the scheduler cron (section 0).

Scenarios: approve a CA with a 3-day deadline in a test system → dry run shows the reminder on day −3, 0, +1, +7; submit the CAR → reminders stop; switch reminders off → none; overdue card on both lists matches the badges.

## 5. Payroll (lock, audit trail, bank file)

Verified: lock/close rules, audit rows and the trail surviving a deleted period (SQLite); BPJS and PPh 21 already have their own test files; the activity log renders.

Weak:
- Lock is one-way: **no reopen**, and no separate "close period" step beyond lock.
- The bank file is exported even when employees have **no bank account** (the count is logged, but the file still contains the lines); it can be downloaded before the period is locked.
- The audit trail covers period operations, **not** payroll settings changes or switching the module on/off (the generic model audit may cover settings — not checked), and there is no cross-period view.
- The engine's attendance/overtime/timesheet inputs are still read independently by each module (the planned source switches and one pipeline are **not built**).
- No test covers the full calculate → lock → generate → publish chain on a real schema.

Scenarios: create period → calculate → add correction → change BPJS switch → recalculate → lock → generate → publish → bank file; every step appears in the activity log with the right actor; locked period refuses every change; period with a missing bank account is flagged before paying.

## 6. Consistency work (pagination, KPI dashboard)

Verified: the nine screens render with the shared footer; the KPI page renders with a chart component. **Not verified in a browser** — rows-per-page behaviour with active filters, the empty/failed chart states, and mobile layout need eyes.

Weak: 15 screens still use a filter bar instead of the header funnel; 50 large screens have no `components/` folder; ~15 real lists lack an empty state (see `docs/UI_CONSISTENCY_AUDIT.md`).

## 7. Cleanup that was done (check nothing relied on it)

Removed: the unused Breeze auth controllers, the Breeze scaffold under `delivery/project` (components, layouts, profile), the old `profile/edit` page, the employee `detail` section, an orphaned planning partial, the routes `ticket/create`, `planning/{id}/gantt` and `planning/{id}/scurve` (all returned an error because their views did not exist), and the unrouted `DeliveryProjectPlanController`. Fixed: the delivery planning PDF exports pointed at views that did not exist. Verify: planning table / Gantt / S-curve PDF downloads, the ticket list, My Profile, and Master → Employee all open.

## 8. Reverted

The timesheet rework was reverted (menu, pages, tables, payroll deduction, attendance hook); only the calendar `is_public` change was kept. A backup of the removed files and of the dropped tables is in the session scratchpad, not in the repository.

## 9. Not built yet (from the original review list)

1. One approval engine is only half done: the shared settings block and supervisor / project-leader steps (section 1).
2. Payroll data flow: per-source switches for attendance, overtime and timesheet, and the attendance → timesheet → overtime → payroll order.
3. Background processing (queues) for payslip PDFs, bulk payroll, imports/exports and notifications.
4. HTTP-level feature tests on a real schema (section 0).
5. Timesheet adapter for the external system (design only; the feature was reverted).
6. My Assets page, asset handover / return / damage reports, goods received for Purchase Request and Purchase Order.
