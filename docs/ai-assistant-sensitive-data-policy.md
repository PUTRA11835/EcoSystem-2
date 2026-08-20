# EcoSystem Assistant — Sensitive Data Policy

## Status

**Enforced in code** as of the categories listed below, via
`TableAccess::SENSITIVE_TABLES` / `TableAccess::authorizeQuery()` in
[`app/Services/Ai/Tools/TableAccess.php`](../app/Services/Ai/Tools/TableAccess.php),
called from both `QueryDataTool::run()` and `AggregateDataTool::run()`
before any filter or aggregate is applied. All other tables (97 of the
database's 115) remain openly queryable by any authenticated employee —
that stays a deliberate, temporary choice for everything not called out
below.

Enforcement reuses the exact menu permission slugs that already gate the
equivalent human-facing pages (confirmed to exist in the live `menu` table,
not just seeded/theoretical), so an employee's AI access matches their UI
access for these tables:

| Table | Self-scope (own row only) | Full access |
|---|---|---|
| `employee_bank` | `my-profile.section.bank.view` | `employee.section.bank.view` |
| `employee_payment` | `my-profile.section.payment.view` | `employee.section.payment.view` |
| `employee_identification` | `my-profile.section.identification.view` | `employee.section.identification.view` |
| `customer_bank` | — | `customer.section.bank.view` |
| `customer_credential` | — | `customer.section.credential.view` |
| `customer_identification` | — | `customer.section.identification.view` |
| `login_activity` | — | `control-center.login-log` |
| `auth_users` | — | `control-center.login-log` |

Where a self-scope slug exists, an employee who has only that permission
still gets their own row: `authorizeQuery()` injects a forced
`employee_id = <current employee>` filter onto the query rather than
denying outright — the same "narrow instead of refuse" pattern
`GetTicketsTool` already uses for `ticket.all-tickets`. Verified via tinker:
an employee with only `my-profile.section.bank.view` querying
`employee_bank` with no filter gets back exactly their own row, never
anyone else's.

Two protections already exist in code today regardless of this policy —
they're technical safety floors, not business-data decisions, and stay in
place either way:

- **Excluded tables** (`TableAccess::EXCLUDED_TABLES` in
  [`app/Services/Ai/Tools/TableAccess.php`](../app/Services/Ai/Tools/TableAccess.php)):
  `sessions`, `personal_access_tokens`, `api_refresh_tokens`,
  `password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`,
  `failed_jobs`, `migrations`. These hold live session/auth tokens or
  framework plumbing, not business data — reading them is an
  account-takeover primitive, not a data-access question.
- **Secret column stripping** (`TableAccess::stripSecrets`): any column
  whose name contains `password`, `token`, or `secret` is removed from every
  returned row, on every table, regardless of what was asked for. Covers
  `auth_users.password` / `.remember_token` / `.cp_token` today and any
  future secret column added elsewhere.

Everything below is a **business-data** classification — data that's real,
legitimate, and currently reachable, but that a person's role should
plausibly gate.

## Not sensitive under this policy (explicitly, to avoid over-blocking)

Company-level financial/operational figures are **not** personal-privacy
sensitive and should stay broadly queryable: `delivery_projects.revenue` /
`.plan_cost` / `.gross_profit`, `delivery_project_costs`,
`delivery_project_payment_terms`, and the equivalent `delivery_support_*`
cost/payment tables. These are business analytics data (the kind of thing
"pemasukan bulan ini" already correctly answers), not an individual's
private information — don't fold them into a "financial = sensitive"
blanket rule.

## Sensitive categories

### 1. Financial account data (personal, not company)

| Table | Why |
|---|---|
| `employee_bank` | Employee bank account numbers |
| `customer_bank` | Customer bank account numbers |
| `employee_payment` | Individual payroll/salary figures |

**Rule:** not freely queryable. An employee should be able to reach their
own row (self-service, same as the existing profile pages); reaching
someone else's requires a specific permission, not just being logged in.

### 2. National/government identification

| Table | Why |
|---|---|
| `employee_identification` | NIK/KTP and similar ID numbers |
| `customer_identification` | Customer ID document numbers |

**Rule:** same shape as category 1 — self-service only by default, explicit
permission for anyone else's.

### 3. Stored external credentials

| Table | Why |
|---|---|
| `customer_credential` | Notes/credentials for customer-side systems (see `app/Models/CustomerCredential.php`) |

**Rule:** gated behind `customer.section.credential.view` — the same slug
that already gates the human-facing customer credential section (confirmed
present in the live `menu` table), rather than the unrelated and currently
unenforced `ticket.view-credential` slug this document originally
considered.

### 4. Security/audit trail

| Table | Why |
|---|---|
| `login_activity` | Per-employee login history (device/IP/time) |
| `auth_users` (non-secret columns: `email`, `phone`, `username`, `last_login_at`) | Account identity/activity metadata |

**Rule:** gate behind an admin-level permission (reuse
`control-center.login-log`, already used for the human-facing login log
page — `LoginLogController.php:166`).

## Borderline — worth a decision, not pre-judged here

These hold personal information but are lower-stakes than the categories
above; whether they need gating is a call for whoever owns this policy, not
assumed by this document:

- `employee_family` — dependents' personal data
- `employee_history`, `customer_history` — free-text audit/change notes,
  sensitivity depends on what people actually write in them
- `employee_attachment`, `customer_attachment` — filenames/metadata only
  (the tools never read file bytes), but names can be revealing

## Enforcement mechanism

Implemented as described in the Status section above. Extending it to a
newly-decided table from the borderline list just means adding one entry to
`TableAccess::SENSITIVE_TABLES` — no changes needed in the tools themselves.
