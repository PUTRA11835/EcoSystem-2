---
key: report-collection-outlook
chapter: reporting
order: 106
title: Collection Outlook reports
summary: See planned billing per month from the payment terms of projects and support items, and update their payment status.
requires: [reporting.collection-outlook, reporting.collection-outlook-support]
updated: 2026-10-28
---
Collection Outlook turns the Terms Of Payment of delivery projects into a month-by-month billing outlook. `Collection Outlook` covers projects, and `Collection Outlook (Support)` covers support items.

## Who uses this

Sales, project and support heads, administrators and directors with the `Reporting` › `Collection Outlook` or `Collection Outlook (Support)` menu.

## Before you start

- Each billing sits in the month of its estimated date. When that is empty, the paid date or the invoice date is used.
- Outlook amounts follow the payment terms kept in each project or support item. A missing amount usually means the term was not filled in there.
- The period is limited: from 3 years back to 15 years ahead, and at most 240 months in one view.

## Step by step

![The Collection Outlook report with the totals and the monthly table](images/guide/collection-outlook.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Collection Outlook` for projects, or `Collection Outlook (Support)` for support items.
2. Set `From` and `To` (month and year). Choose an `Account Executive` (`All AE` for everyone) on the project report, or a `Type` on the support report. Select `Apply`.
3. Read the cards: `Total Outlook`, `Paid`, `Invoiced`, `Not Invoiced` and `Payment Terms`.
4. In the table, each row is one payment term (`TOP No.`) of a project or support item, with a column per month. Green means paid, blue means invoiced and awaiting payment, and red means not invoiced yet.
5. Click an amount to open `Term Of Payment Details`: the billing amount, the term information (project, client, IO number, term name, percentage, revenue, requirements) and the invoice information.
6. Select `Export Excel` to download the view.

### Update a payment status

If your role may edit it, `Edit Payment Status` appears in the details window.

1. Choose the `Status`: `Open`, `Invoiced`, `Paid` or `Delay`.
2. `Invoiced` needs a `Submit Invoice Date` and an `Invoice Number`. `Paid` needs a `Paid Date`.
3. Save.

## What happens next

The change is saved on the payment term, so the project or support item shows the same status.

## Common questions

**The outlook shows Rp0 or dashes.** The terms have no amount or no date yet. Fill them in the project or support item.

**The period is refused.** It goes beyond the limits above. Choose a shorter range.

**I cannot edit the status.** Editing is a separate permission of your role.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/collection-outlook.blade.php, resources/views/reporting/collection-outlook-support.blade.php -->
