---
key: word-report-generator
chapter: reporting
order: 123
title: Word Report Generator
summary: Produce a Word report from a customer template and real EcoSystem data by describing what you need in a chat.
requires: [word-report-generator]
updated: 2026-10-28
---
Word Report Generator creates a report that keeps the layout of a Word template and fills it with real data from EcoSystem. You pick a template, describe the report in a chat and download the result.

## Who uses this

Administrators, and any role your administrator gives the `Word Report Generator` menu.

## Before you start

- Templates are not uploaded on this page. They live in the template library of each customer, in the `Report Templates` tab of the customer's detail page. Ask the owner of that customer to add the template you need.
- The report uses real data. Check who may receive the file before you share it.
- Never put credentials or personal data into the chat.

## Step by step

1. Open `Word Report Generator`. The page has three parts: `History` on the left, the preview (`Pratinjau Laporan`) in the middle and `Chat` on the right.
2. In `Chat`, open `Pilih Template` and choose the template. The line `Template` shows what is selected.
3. Write what you need, for example the customer and the period, then select `Send`.
4. Follow the progress in the preview: `Baca Struktur` (reading the template), `Ambil Data` (collecting data) and `Susun Dokumen` (building the file).
5. When the report is ready, check it in the preview and download it as `.docx` or `.pdf`.

### When something stops

- If the assistant needs more detail (`AI butuh klarifikasi`), answer its question in the chat so it can continue.
- If it pauses because the AI service is busy (`Dijeda sementara`), the finished progress is kept. Continue it when the button appears.
- If it fails (`Gagal generate laporan`), use the retry button shown, or send a clearer instruction.

### Use an earlier report

`History` lists the reports you created. Select one to open it again in the preview.

## What happens next

The file follows the layout of your template, with the data filled in. The report stays in `History` so you can open and download it again.

## Common questions

**The preview says no report is selected.** Pick a template and send an instruction first, or select an item in `History`.

**There is no template to choose.** The customer has no template in its `Report Templates` tab yet.

**The report has wrong numbers.** Say what is wrong in the chat so it can be rebuilt, and compare with the source report.

<!-- sources: routes/web.php, app/Http/Controllers/ReportGeneratorController.php, resources/views/reports/generate.blade.php -->
