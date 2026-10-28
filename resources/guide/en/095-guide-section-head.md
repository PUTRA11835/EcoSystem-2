---
key: guide-section-head
chapter: reference
order: 95
title: Guide for Delivery Support Section Head
summary: What a support section head does: assign and review tickets, approve mandays, manage support items and read the login log.
requires: ["@role:Delivery Support Section Head"]
updated: 2026-10-28
---
This guide is for the Delivery Support Section Head role. You lead a section of the support team and take decisions on its tickets.

## Who uses this

Delivery Support Section Heads. Your menus include `Ticket`, `Consultant Workload`, `Calendar`, `Reporting` (`Ticketing Overview` and `Ticket by Modul`), `Master` (`Employee` and `Business Partner`), `Delivery` › `Support`, `SLA` › `SLA Report` and `Control Center` › `Login Log`.

## Before you start

- Your decisions affect billing and workload. Read the details before you approve, and write a clear reason when you reject.
- The `Login Log` and master data hold personal information. Use it only when needed.

## Step by step

### Tickets

1. Open `Ticket` and the tab you need. Assign the lead and team members, or take a ticket yourself. See [[ticket-assignment]].
2. Open `Consultant Workload` before you assign work, to see who is busy.
3. Review Customer Mandays proposals and approve Resolution Days. See [[ticket-mandays]].
4. Hide a ticket from the main list, or delete one that was created by mistake, with the buttons on the ticket.

### Support items

1. Open `Delivery` › `Support`.
2. Work on the sections your role may edit: general information, approval, financial, team, activities, SLA, plan cost and documents.

### Reports and time

1. `Reporting` › `Ticketing Overview` and `Ticket by Modul` show ticket figures. `SLA` › `SLA Report` shows response and resolution times.
2. Use `Calendar` for events and `Timesheets`. See [[calendar-timesheet]].

### Login Log

`Control Center` › `Login Log` lists the latest sign-in and sign-out of each employee, with device, IP address and place. Use `Last Login` to see who signed in recently.

## What happens next

Approved items move on and the person who asked is notified. Rejected items return to them with your reason.

## Common questions

**A menu opens a page that says `Forbidden`.** Your role has the menu, but not the permission for that page, for example `Ticket Validation`, `Overview`, `Activity Log` or `Active Sessions`. Ask your administrator.

**I cannot see a button described here.** It is not part of your role.

## Related topics

- [[ticket-assignment]]
- [[ticket-mandays]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, resources/views/ticket/index.blade.php, resources/views/delivery/support, resources/views/admin/login-log.blade.php -->
