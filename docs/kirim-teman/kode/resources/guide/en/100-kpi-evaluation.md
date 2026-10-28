---
key: kpi-evaluation
chapter: human-capital
order: 100
title: KPI Evaluation
summary: Monitor monthly KPI coverage, create and review evaluations, and manage assessment templates.
requires: [general.kpi-evaluation, general.kpi-evaluation.list, general.kpi-evaluation.templates, general.kpi-evaluation.teams]
updated: 2026-10-27
---
`KPI Evaluation` is the HR side of KPI: see who has an evaluation for the month, review scores from supervisors, approve results, and keep the assessment templates.

## Who uses this

HR staff. Employees and leads do their own parts in My KPI, see [[my-kpi]].

## Before you start

Open `Human Capital` › `KPI Evaluation`. Depending on your access you see the dashboard, an evaluation list, `Manage Templates` and the `Lead & Project` reporting lines. Buttons for creating, approving or exporting appear only with those permissions.

## Step by step

### Read the dashboard

1. Choose the month in the period picker at the top.
2. Read the tiles: `Visible Employees`, `Not Created`, `Draft`, `Submitted` and `Approved`.
3. Use the funnels on `Employee`, `Position`, `Supervisor`, `Template` and `Status` to narrow the list.
4. Select `Export` to download the month as a CSV file.

### Create evaluations

1. For an employee who is `Not Created`, create the evaluation and pick the template.
2. Or select `Re-sync` to match everyone to the templates that target them for the period.

HR can also change the deadline or the template of an evaluation, and delete an evaluation that is still a draft or was rejected.

### Review and approve

1. Open an evaluation to see the employee, the template, the indicators and the weights.
2. The supervisor scores each indicator; HR can read the answers, the actual results and the final score. Each weighted score is the rating divided by the top of the scale, multiplied by the indicator weight.
3. If the scores are not acceptable, select `Request Revision` and write what must change (required).
4. If they are, select `Approve Evaluation`, add optional HR notes, and confirm. Once approved, the result becomes visible to the employee.

An evaluation moves through `Draft`, `Self-Assessed`, `Reviewed`, `Completed`, then `Approved`, or `Needs Revision` when HR sends it back. HR can approve only after both the self-assessment and the supervisor assessment are done.

Self-assessments are shown as view only: HR does not score or approve them. For an upward assessment, individual raters stay anonymous and only the published average is shown to the supervisor.

### Manage assessment templates

1. Select `Manage Templates`.
2. Filter by type: `Self-Assessment`, `Lead Assessment`, `Peer Assessment` or `Upward Assessment`.
3. Select `New Template` or edit an existing one in the full-page form, then save.

### Check reporting lines

`Lead & Project` shows each employee's project and leader. It is read only: the leader comes from the project manager or from the supervisor in the employee data, so change it in [[employee-data]].

## What happens next

Employees open the approved result in My KPI.

## Common questions

**Why is an employee not listed?** The list shows only the employees you may see.

## Related topics

- [[my-kpi]]
- [[employee-data]]

<!-- sources: routes/hr-general.php, resources/views/hr-general/kpi/dashboard.blade.php, resources/views/hr-general/kpi/review.blade.php, resources/views/hr-general/kpi/templates.blade.php, resources/views/hr-general/kpi/lead-project.blade.php, app/Models/KpiEvaluation.php -->
