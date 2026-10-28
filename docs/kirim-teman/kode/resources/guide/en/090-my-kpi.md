---
key: my-kpi
chapter: my-workspace
order: 90
title: My KPI
summary: Fill in your monthly self-assessment, and score your team members if you are a lead or a peer reviewer.
requires: [general.my-kpi]
updated: 2026-10-27
---
`My KPI` is where you complete the KPI assessments assigned to you and see the results HR approves. What you see depends on your assignments: you may have a self-assessment only, or also assessments for your team.

## Who uses this

Every employee with the `My KPI` menu. Leads, peers and team members see extra tabs when they have assessments to give or receive.

## Before you start

Look at the cards at the top: `Current Period`, `Avg Score` (from approved evaluations), `Total Evals` and `Pending Assessments` (action due). Note the deadline of each assessment.

## Step by step

### Fill in your self-assessment

1. Open `My KPI` from the sidebar and choose the `Self-Assessment` tab.
2. If the amber box `Fill Self-Assessment Now` appears, select its button. Otherwise find the period in `Monthly Self-Assessments`.
3. Check `Period`, `Template`, `Score` and `Deadline` in the table. A late assessment is marked `Overdue`.
4. Select `Fill Self-Assessment`, score each indicator and add comments where the form asks.
5. Submit the form. The `Status` becomes `Self-Assessed` and the `Action` column then shows `View Details`.

### Revise after HR sends it back

1. If `Status` shows `Needs Revision`, select `Revise`.
2. Update your answers and submit again.

### Assess your team (leads)

1. Choose the `Lead Assessment` tab.
2. In the table `Team Member`, `Position`, `Template`, `Status`, `Score`, find the person and select `Evaluate`. Use `Revise` if it needs revision and `View` once submitted.
3. Past work is kept in `My Review History`, grouped by month.

### Give peer or upward feedback

1. Choose `My Team (Peer)` to score colleagues you were asked to rate this period.
2. Choose `Upward Assessment` to give feedback about your lead. Your submissions appear under `My Upward Submissions`.
3. Feedback about you is shown under `Peer Assessments About Me` and `Feedback From My Team` once it is published, with the number of raters and the average score.

## What happens next

An evaluation is complete when both your self-assessment and the lead's assessment are done. HR then approves it, and the score counts in `Avg Score`. Possible statuses are `Draft`, `Self-Assessed`, `Reviewed`, `Completed`, `Approved` and `Needs Revision`. See [[statuses-and-approvals]].

## Common questions

**I see no templates.** `No self-assessment templates have been assigned to you yet.` HR has not assigned a template to you for this period.

**A tab is missing.** Tabs appear only when you have a matching assignment or role.

## Related topics

- [[statuses-and-approvals]]
- [[my-approvals]]
- [[access-and-menus]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/MyKpiController.php, resources/views/hr-general/kpi/my-kpi.blade.php, app/Models/KpiEvaluation.php -->
