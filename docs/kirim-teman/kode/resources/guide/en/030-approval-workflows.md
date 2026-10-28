---
key: approval-workflows
chapter: administration
order: 30
title: Approval Workflows
summary: Define who approves Overtime, Reimbursement, Purchase Request, Cash Advance and Cash Advance Report, step by step.
requires: [general.approval-workflow.overtime, general.approval-workflow.reimbursement, general.approval-workflow.purchase-request, management.approval-workflow.cash-advance, management.approval-workflow.cash-advance-report]
updated: 2026-10-27
---
An approval workflow is the ordered list of steps a request passes through before it is `Approved`. Five request types have their own workflow: Overtime, Reimbursement, Purchase Request, Cash Advance (CA) and Cash Advance Report (CAR). This topic is for administrators.

## Who uses this

Administrators and the people who own each process (HR, Finance, GA). You see a workflow only if your role has access to it.

## Before you start

- Roles and positions must already exist. See [[roles-and-menu-access]].
- Each person who approves also needs access to the approval page of that request type, or they cannot act on it.

## Where each workflow lives

The editor looks the same everywhere. You open it from the `Settings` tab of the module:

| Request type | Open |
|---|---|
| Overtime | `Overtime Management` › `Settings` |
| Reimbursement | `Reimbursement Management` › `Settings` |
| Purchase Request | `Purchase Management` › `Settings` |
| Cash Advance and Cash Advance Report | `Cash Advance Management` › `Settings` |

## Step by step

### Edit the steps

1. Open the `Approval Workflow` section in the module's `Settings`.
2. Read the steps from top to bottom. They run in that order, and one person per step is enough.
3. For each step, set the columns that apply: `Step name`, what the step signs as (`Signs as`, for Reimbursement), the `Approver` source (Overtime), the `Position`, and the `Name` of specific people.
4. Leave `Name` empty to let everyone who holds the position act on the step. Choose names to limit the step to those people.
5. Use the arrows to move a step, `Active` to switch it on or off, and `Del` to remove it. Click `Add Step` to add one.
6. Click `Save Workflow`. Changes apply together; unsaved rows are marked `Unsaved`.

For Overtime, `Approver` can be `Fixed position / names`, `Employee's supervisor` (taken from the employee's direct supervision data) or `Project leader`. For the last two, the position becomes the fallback.

### Mandatory steps

`Mandatory` checked means the person on that row always acts and the requester only sees it. Unchecked means the requester chooses from the people listed when submitting.

### Apply new steps to documents in progress

When you add a step, a checkbox `Also apply new steps to … in progress` appears. Tick it only if documents already waiting should also pass the new step.

> [!WARNING]
> Documents in progress keep the steps they started with unless you tick that option. Approved or rejected documents are never touched. A workflow needs at least one step, and every step needs a name or role and a position or a name, otherwise it cannot be saved.

## What happens next

New requests follow the saved steps. Approvers see requests that wait for them in `My Approvals`, and the request page shows the current step and who acted.

## Common questions

**A request is stuck on a step.** Check who holds that step's position or name, and that the person has access to the approval page.

**Can the requester approve their own request?** Only where the module's rules allow self-approval, and it is always flagged.

## Related topics

- [[my-approvals]]
- [[reimbursement-management]]
- [[purchase-management]]
- [[cash-advance-management]]
- [[statuses-and-approvals]]

<!-- sources: resources/views/hr-general/partials/approval-workflow/editor.blade.php, resources/views/hr-general/partials/approval-workflow/row.blade.php, resources/views/hr-general/*/components/step-editor.blade.php, routes/hr-general.php, app/Models/Reimbursement/ReimbursementApprovalStep.php -->
