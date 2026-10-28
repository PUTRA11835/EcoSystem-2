---
key: resource-timeline
chapter: reporting
order: 122
title: Resource Timeline
summary: See where every consultant is placed day by day, and record or change a consultant's assignment to a customer.
requires: [reporting.resource-timeline]
updated: 2026-10-28
---
Resource Timeline is a calendar grid of your consultants. Each row is a consultant and each column is a day, so you can see who is placed at which customer and when.

## Who uses this

Delivery operation leaders, RPMO and administrators whose access includes `Reporting` › `Resource Timeline`.

## Before you start

- Assignments are recorded by customer code, so know the code of the customer first.
- Changing a timeline changes what everyone with access sees, so check the dates before you save.

## Step by step

### Read the grid

1. Open `Reporting` › `Resource Timeline`.
2. Choose `Monthly` or `Yearly`, then the month and year.
3. Narrow the rows with `Search customer code` and the `Home Base` filter (`Jakarta`, `Yogyakarta`, `Surabaya` or `Others`).
4. Read each row: `Consultant`, `Module`, `Status` and one cell per day. Weekend days are shaded.

### Record an assignment

1. Click a consultant's row, then `Create Timeline`.
2. Check the `Consultant`, then set `Start Date`, `End Date` and `Customer Code`.
3. Select `Save`.

### Change or remove an assignment

1. Click the consultant's row. `Existing entries` lists the dates already assigned.
2. Remove an entry with its delete icon, then create the correct one.

## What happens next

The grid shows the new assignment on the days you chose, for everyone who can open the page.

## Common questions

**The grid is empty.** No consultant matches the filters, or no one is set up for the month. Clear the filters first.

**I cannot find a customer code.** Ask the owner of the customer data. Do not invent a code.

<!-- sources: routes/web.php, app/Http/Controllers/ResourceTimelineController.php, resources/views/reporting/resource-timeline.blade.php -->
