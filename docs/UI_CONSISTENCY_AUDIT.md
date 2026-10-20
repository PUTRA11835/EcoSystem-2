# UI consistency audit — list screens (HR · GA · Legal · Finance · External · Management)

Scope: every Blade screen that renders a data table (147 screens; print / PDF documents excluded). Measured by scanning `resources/views`.
The conventions audited: header-funnel filters, the shared pagination footer, a `components/` folder for big screens, horizontal scroll on
wide tables, and an empty state.

## Convention (what a list screen does)

| Part | Use |
|---|---|
| Filters | the **header funnel** (`hr-general.recruitment.components.header-filter`) inside a hidden `data-filter-form` |
| Pagination | the shared footer: `@include('hr-general.recruitment.components.pagination', ['paginator' => $rows, 'form' => '<filter form id>', 'perPage' => $rows->perPage(), 'perPageOptions' => [10, 15, 25, 50]])` |
| Rows per page | controller calls `$this->pageSize()` (base `Controller`) instead of `paginate(25)`; the footer's select submits the filter form |
| Wide tables | wrapped in `overflow-x-auto` |
| Empty | `@forelse … @empty` row that says what is missing, and whether a filter caused it |
| Big screens | pieces live in a sibling `components/` folder |

## Done in this pass

* **Pagination** — nine HR / Finance list screens carried their own copy of the footer (or Laravel's default `->links()`): attendance
  corrections, attendance monthly recap, shifts, cash advance, cash advance reports, overtime review, purchase request, branches, and the KPI
  dashboard. They now use the shared footer, and "Rows per page" works on all of them. Custom-pagination screens: **9 → 0**.
* **KPI dashboard** — charts moved to `kpi/components/charts.blade.php`; a chart with no data (or without Chart.js, e.g. a blocked CDN) now
  shows a sentence instead of an empty frame and no longer stops the filters below it from working; the trend tabs dim while loading and
  report a failed load.
* **Horizontal scroll** — shift assignment and contract signing tables now scroll instead of clipping.
* New screens added in this work (payroll activity log, reimbursement payment, CAR overdue badge) follow the convention and live in `components/`.

## Findings that are NOT problems (checked)

* The "no horizontal scroll" and "no empty state" counts include static key/value tables (payroll simulation, a contract's remuneration,
  the column description on the reimbursement import page), configuration tables that always have rows (attendance sources, onboarding
  checklist) and printable records. Every wide data table in HR / Finance already scrolls.

## Still to do (not changed — larger, riskier moves)

1. **Filter bar → header funnel** (15 screens use their own GET form above the table): finance/bpjs/letters, finance/bpjs/report,
   finance/pph21/report, finance/pph21/settings, hr-general/attendance/{corrections, daily, monthly, shifts/index, shifts/assign},
   hr-general/cash-advance/{index, report-hr-index}, hr-general/overtime/review, hr-general/purchase-request/index, management/branches/index.
   Each needs its filters mapped to `header-filter` types, so they were left alone.
2. **50 screens over 400 lines with no `components/` folder** — mostly outside HR / Finance (ticket, delivery, reporting, master customer /
   employee sections, admin). The biggest: ticket/show (9,371 lines), delivery/project/projects/show (8,696), ticket/index (3,417),
   delivery/support/list/show (2,489), ai/research (1,902). Split them by section when they are next touched.
3. **Empty states** on the ~15 remaining screens that really list rows (admin failed jobs / SLA report, hidden tickets, ticket task,
   delivery plan-cost partials, management employee tabs).
