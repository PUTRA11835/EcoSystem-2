---
key: ai-settings
chapter: administration
order: 132
title: AI Settings
summary: Choose the AI model and thinking effort behind each assistant, and understand what each choice costs.
requires: [control-center.ai-settings]
updated: 2026-10-28
---
AI Settings is the one place where an administrator decides which AI model powers each assistant in EcoSystem. Users never pick a model themselves: everyone gets the model configured here.

## Who uses this

Administrators, and any role given the `Control Center` › `AI Settings` menu in Menu Access.

## Before you start

- The model you choose decides which provider is billed and the price per token. A stronger model costs more on every message.
- A change applies to the next message straight away. There is no deployment and no way to undo for messages already sent.
- Read `Control Center` › `AI Usage` first if you want to see what the current choice costs.

## Step by step

![The AI Settings page with one card per assistant, each with a model and an effort list](images/guide/ai-settings.png)

*Screenshot with sample data.*

1. Open `Control Center` › `AI Settings`. Each assistant has its own card:
   - `AI Research`: looks things up on the web. An answer can also trigger billable web searches.
   - `AI Assistant`: answers from EcoSystem data only, using local tools.
   - `Ticket Analyzer`: one structured analysis per ticket reviewed in Ticket Validation.
   - `Ticket Q&A (Staging Validation)`: questions and answers while validating a ticket.
   - `AI Summarize (Detail Tiket)`: the summary on the ticket detail page.
   - `Word Report Generator (Struktur & Data)` and `Word Report Generator (Susun Dokumen)`: the two phases of the Word report. The second is the heaviest, so it has its own model.
2. In `Model`, pick a model. The price per 1 million tokens (input and output) is shown beside each name. Claude and GPT models are both offered, and the provider follows the model.
3. In `Thinking / Reasoning Effort`, pick how long the model thinks. A higher effort means deeper thinking and more tokens. The list depends on the model: Claude models offer `Off`, `Low`, `Medium`, `High`, `Xhigh` and `Max`, and GPT models offer `Off`, `Minimal`, `Low`, `Medium` and `High`.
4. Select `Save settings`. The page confirms that new chats use the settings immediately. `Cancel` leaves everything as it was.

### Why some models are missing from a card

Assistants that must search the web (`AI Research`, `Ticket Analyzer`, `Ticket Q&A`, `AI Summarize` and the Word report phases) only list models that have built-in web search. `AI Assistant` works only on EcoSystem data, so it also offers the smaller `Claude Haiku 4.5`.

## What happens next

The next message of each assistant uses the new model. Every save is recorded in the `Audit Log` under the module `AI Settings`, with the settings before and after, so you can trace who changed a cost-driving choice.

## Common questions

**I do not see a model I expected.** The card lists only models that fit that assistant, as explained above.

**The cost rose after I changed a model.** Check `AI Usage` for the model and feature, then move the assistant to a cheaper model or a lower effort.

**A user asks to choose their own model.** That is not possible by design. Change the assistant here instead.

**Does the change affect conversations in progress?** The next message uses the new settings. Earlier messages stay as they were.

<!-- sources: routes/web.php, app/Http/Controllers/AiSettingsController.php, app/Support/AiModelSettings.php, resources/views/admin/ai-settings.blade.php -->
