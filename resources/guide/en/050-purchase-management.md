---
key: purchase-management
chapter: finance
order: 50
title: Purchase Management
summary: Process purchase requests and purchase orders, and set the rules, units, cost centers and approval steps.
requires: [general.purchase-request, general.purchase-order, general.settings.purchase-request, general.approval-workflow.purchase-request]
updated: 2026-10-27
---
Purchase Management is where approvers and Finance handle Purchase Requests submitted by employees and the Purchase Orders that follow them. It has three tabs: `Purchase Requests`, `Purchase Orders` and `Settings`.

## Who uses this

Approvers, Finance, GA and administrators. You only see the tabs your role allows, and buttons such as `New PR` or `New Purchase Order` appear only with the matching access.

## Before you start

- Employees raise their own requests in `My Workspace` › `Purchase Request` (see [[my-purchase-request]]).
- The approval steps are kept in `Settings` › `Approval Workflow` (see [[approval-workflows]]).

## Step by step

### Process Purchase Requests

1. Open `Purchase Management` › `Purchase Requests`.
2. Use `Scope`, `Month`, `Status` and `Search`, then `Apply`, to find a document. Rows that need attention are highlighted.
3. Open the document and read the items, quantities and cost center.
4. If it is your turn, approve it, or reject it with a written reason.
5. The status moves through `Submitted`, `In review`, `Approved` or `Rejected`. A request becomes `Cancelled` when the requester cancels it, if the rules allow it.

Other actions in the list: `Edit`, `Print`, `Export Excel`, `Monthly Export`, and `Delete`. `New PR` creates a request on behalf of an employee.

### Create a Purchase Order

1. Open `Purchase Orders` and click `New Purchase Order`.
2. Select an approved request in the PR number field, or leave it empty to make an order manually (depending on the rule in `Settings`).
3. Fill in the `Supplier`, the purchase date, the `Invoice No.` and `Invoice Date`, the `Delivered Date` and `Notes`.
4. Add the `Items` with unit price, discount and tax.
5. Save the order. Its status starts as `Submitted`.
6. An approver opens the order and uses `Approval Actions` to approve or reject it. `Print Form` prints the order.

### Set up rules (Settings)

1. Open the `Settings` tab.
2. In `Approval Workflow`, add or arrange steps and click `Save Workflow`.
3. In `Purchase Request Rules`, review dates and backdating, items and quantities, allowed units, cost centers, who may cancel, and the signatures printed on the form.
4. Save the changes.

> [!NOTE]
> Rule changes apply to new documents. Documents already in progress keep the steps they started with unless you tick the option to apply new steps to them.

## What happens next

An approved Purchase Request can be the basis for a Purchase Order. Approved or rejected documents cannot be edited as normal requests; deletion needs special access and keeps a record.

## Common questions

**Why don't I see `Purchase Orders`?** That tab needs its own access. Ask an administrator.

**Can a requester cancel?** Only if `Requesters may cancel their own request` is on in `Settings`.

## Related topics

- [[my-purchase-request]]
- [[approval-workflows]]
- [[my-approvals]]
- [[workflow-finance]]

<!-- sources: routes/hr-general.php, app/Http/Controllers/HR_General/PurchaseRequestController.php, app/Http/Controllers/HR_General/PurchaseOrderController.php, resources/views/hr-general/purchase-request/*.blade.php, resources/views/hr-general/purchase-order/*.blade.php, app/Models/PurchaseRequest/PurchaseRequest.php -->
