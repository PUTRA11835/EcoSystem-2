---
key: report-log-shifting
chapter: reporting
order: 105
title: Log Shifting report
summary: Collect the SLA notes written between shifts for a date and time range, and export them to Excel.
requires: [reporting.log-shifting]
updated: 2026-10-28
---
Log Shifting is the handover log of the support team. It lists the SLA notes written on tickets, so the next shift knows what happened.

## Who uses this

Helpdesk, support heads, module leads and others with the `Reporting` › `Log Shifting` menu.

## Before you start

- The page labels are in Indonesian, as on screen: `Tampilkan` means show and `Dari Tanggal` and `Sampai Tanggal` mean from and to date.
- Only tickets that have at least one SLA note appear. Hidden tickets are left out.

## Step by step

![The Log Shifting page with the date range, the quick buttons and the list of SLA notes](images/guide/report-log-shifting.png)

*Screenshot with sample data.*

1. Open `Reporting` › `Log Shifting`.
2. Pick a quick range with `Hari Ini` (today), `Kemarin` (yesterday), `7 Hari Terakhir` (last 7 days) or `Bulan Ini` (this month). Or fill `Dari Tanggal` and `Sampai Tanggal`, and optionally the hours to narrow it to a shift.
3. Select `Tampilkan`. The page says how many notes were found for the period and lists them: `No Tiket`, `Deskripsi`, `Tanggal`, `Jam`, `SLA Note` and `PIC`. Select a row to open the ticket in a new tab.
4. Select `Export Excel` to download the same range as a report. `Reset Semua` clears the filters.
5. Under `Ringkasan per Tiket` you see every ticket that has ever had an SLA note. Select a row to read all its notes, without a date filter.

## What happens next

The export has the notes of the range you chose, ready to attach to a shift report.

## Common questions

**The table says `Belum ada data`.** Choose a date range and select `Tampilkan` first.

**The range shows no notes.** No SLA note was written in that range. Widen the dates or hours.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/log-shifting.blade.php -->
