---
key: guide-rpmo-admin
chapter: reference
order: 92
title: Guide for Delivery RPMO Administrator
summary: What an RPMO administrator maintains: delivery records, master data, reports and the consultant overview.
requires: ["@role:Delivery RPMO Administrator"]
updated: 2026-10-28
---
This guide is for the Delivery RPMO Administrator role. You support the RPMO office with records and reports.

## Who uses this

Delivery RPMO Administrators. Your menus include `Calendar`, `Reporting`, `Master` (`Employee` and `Business Partner`), `Delivery` (`Project` and `Support`), `SLA` and `RPMO`.

## Before you start

- You work with personal data and contract information. Use it only for your task and never copy it into chat or email.
- Running the monthly reporting periods needs the `Manage Global` permission, which the Delivery RPMO Head has by default. Without it you can read `Period Management`.

## Step by step

### Delivery records

1. Open `Delivery` › `Project` or `Delivery` › `Support`. Use `Add New` or `Add Delivery Support` to create an item, and select a row to maintain its tabs.
2. Use `Export Excel` to download a list.

### Master data

1. Open `Master` › `Employee` and use `Create Employee` to add a person. `Export Excel` downloads the list.
2. Open `Master` › `Business Partner` and use `Create Business Partner` to add a customer. Enter a `Customer Code` of up to 4 letters or digits and the `Company Name`.

### Reports

1. `Reporting` › `Collection Outlook` and `Collection Outlook (Support)` show expected billing per month.
2. `Reporting` › `Ticket by Modul`, `Ticketing Overview` and `Diagram Report` show ticket figures.
3. `Reporting` › `Consultant Assignment` and `Resource Timeline` show where consultants are placed. See [[resource-timeline]].
4. `Reporting` › `Customer MD` lists change requests and customer mandays. See [[customer-md]].
5. `SLA` › `SLA Report` shows response and resolution times.

### Time

Use `Calendar` for events and `Timesheets`. See [[calendar-timesheet]].

### RPMO

`RPMO` opens the module page. It states that the module is still under construction and points to `Period Management`.

## What happens next

Records you save appear on the pages that use them. Reports update from live data.

## Common questions

**I cannot open or close a reporting period.** `Period Management` shows you the periods and the `Audit Log`. Changing them needs the `Manage Global` permission, which the Delivery RPMO Head has by default. Ask your administrator.

## Related topics

- [[customer-md]]
- [[resource-timeline]]
- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/rpmo/rpmo.blade.php, resources/views/delivery, resources/views/master, resources/views/reporting -->
