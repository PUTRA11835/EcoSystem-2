---
key: guide-project-admin
chapter: reference
order: 88
title: Guide for Delivery Project Administrator
summary: What a project administrator maintains: projects and support items, business partners, employee records, module groups and the consultant overview.
requires: ["@role:Delivery Project Administrator"]
updated: 2026-10-28
---
This guide is for the Delivery Project Administrator role. You keep the master data and the delivery records tidy so the project team can work.

## Who uses this

Delivery Project Administrators. Your menus include `Delivery` › `Project` and `Support`, `Master` (`Employee` and `Business Partner`), `Management` (`Module Group` and the employee settings), `Consultant Workload`, `Reporting` (`Collection Outlook`, `Collection Outlook (Support)` and `Consultant Assignment`) and `Calendar`.

## Before you start

- You work with personal data of employees and with customer contact and credential information. Use it only for your task and never copy it into chat, email or other documents.
- Master data feeds many other pages. A wrong code or name spreads, so check before you save.

## Step by step

### Projects and support items

1. Open `Delivery` › `Project` and select `Add New` to create a delivery project. Fill the form and save, then open the project to complete its tabs.
2. Open `Delivery` › `Support` and select `Add Delivery Support` to create a support item.
3. Select a row to open an item. Keep `Team`, `Documents` and the other tabs current, and use `Create Folder` for the OneDrive folder before you upload documents.

### Business partners

![The Create Business Partner window with general data, standard address and organizational data](images/guide/business-partner-create.png)

*Screenshot with sample data.*

1. Open `Master` › `Business Partner`. Use the filters (`Status`, `Type`, `Business Partner`, `Customer Group`) then `Apply`, or `Reset`.
2. Select `Create Business Partner`. Choose the `Type`, enter a `Customer Code` (up to 4 letters or digits) and the `Company Name`.
3. Fill `Email Domain` if inbox emails from that domain should become tickets of this customer.
4. Choose the `AE (Account Executive)` from the employees with a Sales role, then save. Add contacts and other details in the partner's detail page.
5. `View Grouping` shows customers grouped by their customer group.

### Employee records

![The Employee Management list](images/guide/employee-list.png)

*Screenshot with sample data.*

1. Open `Master` › `Employee`. Select `Create Employee` to add someone, or `Export Excel` to download the list.
2. Use `Manage Dropdown Data` to maintain the choices used in employee forms.
3. Open an employee to update the sections of their record that your role may edit.

### Module groups

![The Module Group list with the Add Group button](images/guide/module-group.png)

*Screenshot with sample data.*

1. Open `Management` › `Module Group`. A group collects related modules, for example Logistic.
2. Select `Add Group`, enter the `Group Name` and `Description`, tick the `Modules` and save.
3. Use `Edit` to change a group and `Delete` to remove one that is not used.

### Consultants and billing

1. `Consultant Workload` shows who is busy. `Reporting` › `Consultant Assignment` lists each consultant's projects, taken from the project `Team` tabs.
2. `Collection Outlook` and `Collection Outlook (Support)` show expected billing per month. Pick the period and select `Apply`, and `Export Excel` to download.

## What happens next

Saved records appear on the pages that use them. For example a new business partner becomes available as a customer when you create a project.

## Common questions

**I cannot create a business partner or an employee.** The buttons appear only when your role has the permission. Ask your administrator.

**A customer code is rejected.** A code allows at most 4 letters or digits. Shorten it and remove other characters.

## Related topics

- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/delivery/project, resources/views/delivery/support, resources/views/master, resources/views/management/module-groups, resources/views/reporting/consultant-assignment.blade.php -->
