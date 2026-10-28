---
key: guide-sales
chapter: reference
order: 89
title: Guide for Sales and Marketing roles
summary: How sales and marketing staff follow projects, customers and the billing outlook, and what is read only for them.
requires: ["@role:Sales Operation Account Executive", "@role:Sales Operation Director", "@role:Sales Operation User", "@role:Sales Marketing User", "@role:Sales Marketing Manager"]
updated: 2026-10-28
---
This guide is for the Sales Operation and Sales Marketing roles. You follow the projects of your customers, check customer data and keep an eye on billing.

## Who uses this

Sales Operation Account Executive, Sales Operation Director, Sales Operation User, Sales Marketing User and Sales Marketing Manager. Your menus include `Home`, `Calendar` › `Events`, `Master` › `Business Partner` and `Delivery` › `Project`. Most of these roles also have `Reporting` › `Collection Outlook` and `Collection Outlook (Support)`, and Sales Operation User also has `Business Dev`. Menus you do not see are not part of your role.

## Before you start

- Project and customer data includes prices, contacts and credential information. Use it only for your work, and never copy it into chat, email or other documents.
- Much of the project data is read only for sales. If a field or button is missing, it is not part of your role.

## Step by step

### Follow your projects

1. Open `Delivery` › `Project`. The cards at the top count projects by status and schedule, and the table lists them. Click a card to filter the list, or select `Export Excel` to download it.
2. Select a project to open it. The tabs are `General`, `Delivery Info`, `Team`, `Documents`, `Issues`, `Risks`, `Location`, `Planning` and `Plan Cost`.

![A project detail page where General Information is marked Read only](images/guide/project-detail-readonly.png)

*Screenshot with sample data.*

3. `General` is read only. Other tabs can be edited depending on your role: for example `Delivery Info` and `Documents` for most sales roles, and `Location` for the Account Executive. A tab you cannot edit has no edit button.
4. Use `Create Folder` to create the project's OneDrive folder before you upload documents.

### Check billing: Collection Outlook

![The Collection Outlook report with the summary cards and the monthly table](images/guide/collection-outlook.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Collection Outlook` for projects, or `Collection Outlook (Support)` for support items.
2. Set `From` and `To` (month and year), choose an `Account Executive` or `All AE`, then select `Apply`.
3. Read the cards: `Total Outlook`, `Paid`, `Invoiced`, `Not Invoiced` and `Payment Terms`.
4. In the table, each payment term sits in the month of its estimated date. Click an amount to see its payment term and invoice details. Green means paid, blue means invoiced and awaiting payment, and red means not invoiced yet.
5. Select `Export Excel` to download the view.

### Look up a customer

1. Open `Master` › `Business Partner`.
2. Use `Status`, `Type`, `Business Partner` (search by email or company name) and `Customer Group`, then `Apply`. `Reset` clears them, and `View Grouping` shows customers by group.
3. The `Actions` column holds the buttons to open a partner. If it is empty for you, your role can read the list but not open a partner. Ask your administrator if you need that.

### Plan your time

Use `Calendar` › `Events` for meetings and reminders. See [[calendar-timesheet]].

## What happens next

Changes you save on the editable tabs appear for everyone who can open the project. The `Collection Outlook` figures follow the payment terms kept in each project or support item.

## Common questions

**I cannot add a project or a customer.** Creating them is for administrators and project administrators.

**A payment term is missing from `Collection Outlook`.** The term needs an estimated date, a paid date or an invoice date to appear in a month. Ask the project owner to fill it in.

**`Collection Outlook` is not in my menu.** It is not part of your role.

## Related topics

- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/delivery/project, resources/views/reporting/collection-outlook.blade.php, resources/views/master -->
