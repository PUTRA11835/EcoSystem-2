# EcoSystem Assistant — Sensitive Data Policy

## Status

**Enforced in code** as of the categories listed below, via
`TableAccess::SENSITIVE_TABLES` / `TableAccess::authorizeQuery()` in
[`app/Services/Ai/Tools/TableAccess.php`](../app/Services/Ai/Tools/TableAccess.php),
called from both `QueryDataTool::run()` and `AggregateDataTool::run()`
before any filter or aggregate is applied. All other tables (105 of the
database's 117) remain openly queryable by any authenticated employee —
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
| `employee_contract` | `my-profile.section.contract.view` | `employee.section.contract.view` |
| `employee_family` | `my-profile.section.family.view` | `employee.section.family.view` |
| `employee_attachment` | `my-profile.section.attachment.view` | `employee.section.attachment.view` |
| `customer_bank` | — | `customer.section.bank.view` |
| `customer_credential` | — | `customer.section.credential.view` |
| `customer_identification` | — | `customer.section.identification.view` |
| `customer_history` | — | `customer.section.history.view` |
| `customer_attachment` | — | `customer.section.attachment.view` |
| `login_activity` | — | `control-center.login-log` |
| `auth_users` | — | `control-center.login-log` |

Where a self-scope slug exists, an employee who has only that permission
still gets their own row: `authorizeQuery()` injects a forced
`employee_id = <current employee>` filter onto the query rather than
denying outright — the same "narrow instead of refuse" pattern
`GetTicketsTool` already uses for `ticket.all-tickets`. Verified via tinker:
an employee with only `my-profile.section.bank.view` querying
`employee_bank` with no filter gets back exactly their own row, never
anyone else's (re-verified 2026-09-14 for `employee_contract` too — same
mechanism, same result: `where employee_id = ?` gets injected, no code
changed to make that happen).

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
  returned row, on every table, regardless of what was asked for — including
  tables that already passed `authorizeQuery()`. Covers `auth_users.password`
  / `.remember_token` / `.cp_token` today and any future secret column added
  elsewhere. **Do not add business-data words here** (e.g. `salary`): this
  list runs unconditionally, even on rows the current employee is explicitly
  authorized to see, so a needle like `salary` doesn't add a second layer of
  protection on top of `SENSITIVE_TABLES` — it silently defeats the
  permission that table-level gate just granted. Verified via tinker
  (2026-09-14): with `salary` in the needle list, an employee holding
  `employee.section.contract.view` — meant to see contract salary data —
  got the `salary` key stripped from every `employee_contract` row anyway.
  Reverted; the column-name list stays limited to values that are NEVER
  legitimate to return regardless of who's asking (a login credential isn't
  business data gated by role — it's a credential, full stop). A column like
  `employee_contract.salary` is protected by putting its *table* in
  `SENSITIVE_TABLES`, not by adding its *name* here.

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
| `employee_contract` | Contract-level `salary` figure — found via a full schema audit (2026-09-14, `information_schema.COLUMNS` search for salary-shaped column names); it was never listed here or in the borderline section below, just openly queryable like any other table until this pass. Same category as `employee_payment`, just a different table holding the number. |

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

Two independent AI code paths check this slug, both against the same
`Employee::hasMenuPermission()` call, so they can't drift apart:
`TableAccess::authorizeQuery()` (query/aggregate tools, described above) and
`CustomerCredential::contextNotesFor()` (`app/Models/CustomerCredential.php`)
— used by `AiTicketAnalyzerService` and `AiTicketQaService` to fold a
customer's credential notes into the Ticket Analyzer's prompt/Q&A context
during staging-ticket validation, when the analyzing employee has the
permission.

### 4. Security/audit trail

| Table | Why |
|---|---|
| `login_activity` | Per-employee login history (device/IP/time) |
| `auth_users` (non-secret columns: `email`, `phone`, `username`, `last_login_at`) | Account identity/activity metadata |

**Rule:** gate behind an admin-level permission (reuse
`control-center.login-log`, already used for the human-facing login log
page — `LoginLogController.php:166`).

### 5. Personal data and revealing metadata (resolved 2026-09-14)

| Table | Why |
|---|---|
| `employee_family` | Dependents' personal data (name, birth date, relationship — people who never consented to an AI reading their records) |
| `employee_attachment` | The tools never read file bytes, only row metadata — but `file_name`/`document_title` can itself be revealing (e.g. an ID scan or a medical certificate filename) |
| `customer_history` | Free-text change/audit notes — same shape as `customer_credential`, which already needed an explicit gate rather than being left open because content is unpredictable |
| `customer_attachment` | Same reasoning as `employee_attachment` |

**Rule:** `employee_family`/`employee_attachment` follow category 1's shape
(self-service via `my-profile.section.*`, explicit permission for anyone
else's); `customer_history`/`customer_attachment` follow category 3's shape
(no self-scope concept for customer-side data — gated behind
`customer.section.{key}.view`, same slug as the equivalent human-facing
tab).

## Deliberately not gated

- **`employee_history`** — same shape as `employee_family`/`customer_history`
  (an `action`/`description`/`performed_by`/`performed_at` audit-log table),
  but unlike everything else in this document, it has **no human-facing page
  at all** — a codebase-wide search turns up zero controller/view usage of
  `App\Models\EmployeeHistory` outside the model itself. Every gate in this
  policy reuses the slug that already protects the equivalent human-facing
  screen, specifically so AI access can never exceed UI access; attaching a
  plausible-sounding slug to a table with no screen to match would break
  that invariant instead of upholding it (the slug would gate nothing real,
  and a future person reading `SENSITIVE_TABLES` would reasonably assume it
  does). Left in the same "open by default" bucket as every other ungated
  table. **If `employee_history` ever gets a real page**, build its
  permission slug alongside that page (matching the
  `employee.section.{key}.view`/`.update` pattern the others use) and add an
  entry here in the same pass — don't gate the table first and invent the
  page's permission around it after the fact.

## Enforcement mechanism

Implemented as described in the Status section above. Extending it to a
newly-decided table just means adding one entry to
`TableAccess::SENSITIVE_TABLES` — no changes needed in the tools themselves.

## Tool-call audit trail (added 2026-09-14)

`AuditLog::logAiPrompt()` (called by the controller before the reply stream
starts) only ever recorded the employee's question text — never which
table(s) the tool loop actually read to answer it. That left no way to
answer "did this conversation ever touch `employee_bank`" after the fact,
only "what did this employee type."

`AiChatService::runTools()` now writes one `Log::info('AI assistant tool
call', …)` line per tool call (all three outcomes: success, denied by
`TableAccess`, and unknown-tool/exception), carrying `employee_id`,
`conversation_id`, `tool`, `table` (when applicable), `status`, and
`response_bytes` — a size signal, not the row content itself, so this trail
doesn't become a second copy of whatever sensitive data it exists to help
audit. Verified via tinker (reflection call into the private method) against
all three outcomes; entries land in `storage/logs/laravel.log` (the app's
`stack`→`single` channel).
