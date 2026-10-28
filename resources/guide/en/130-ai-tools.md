---
key: ai-tools
chapter: my-workspace
order: 130
title: AI Assistant and AI Research
summary: Ask questions about your EcoSystem data with AI Assistant, or look things up on the web with AI Research.
requires: [ai-assistant, ai-research]
updated: 2026-10-27
---
The `AI Tools` section has two chat pages. `AI Assistant` answers questions about the data in EcoSystem. `AI Research` looks things up on the public web. Pick the one that fits your question.

## Who uses this

Employees whose role includes `AI Assistant`, `AI Research` or both. You see only the page you have access to.

## Before you start

| | AI Assistant | AI Research |
|---|---|---|
| Source | Your EcoSystem data (tickets, SLA, delivery projects and more) | The public web only |
| Saved history | No, the chat is not stored | Yes, saved conversations in a `History` list |
| Attachments | Up to 5 files of 10 MB each | PDF and images, within the limits shown on the page |

AI Assistant respects your access. When you lack permission for some data, it says so instead of showing it.

> [!WARNING]
> AI replies may be inaccurate. Always verify important information before you act on it. Do not paste confidential data into AI Research, because it works with the public web.

## Step by step

### Ask AI Assistant

1. Open `AI Assistant` from the sidebar. The page is called `EcoSystem Assistant`.
2. Type your question in the box (up to 4,000 characters) and send it. Long text you paste becomes an attachment that you can open from the chat.
3. To add files, select the attach button. You can attach PNG, JPEG, GIF or WEBP images, PDF files, and text or code files. Other file types are skipped and the reply says which ones.
4. Read the answer as it streams in. Use the copy button under a reply to copy it.
5. Select `Clear conversation` to start fresh. The conversation is kept only for a short time and is never saved as a record.

### Search the web with AI Research

1. Open `AI Research` from the sidebar. The page is called `Research Assistant`.
2. Type what you want to look up and send it. Sources used for a reply are listed with it.
3. Select `Limits & behaviour` at the bottom of the page to read the exact limits for message length, attachments per message and per conversation.
4. If a long answer stops early, select `Continue` under it to pick up where it ended.
5. Use the `History` panel to reopen a saved conversation, or `Start a new chat` to begin another one.

## What happens next

AI Assistant forgets the chat after a while, so copy anything you want to keep. AI Research keeps your conversations in `History` so you can come back to them.

## Common questions

**The assistant says it cannot show some data.** Your role does not have access to that data. See [[access-and-menus]].

**Which tool for a question about our tickets?** Use AI Assistant. AI Research cannot see EcoSystem data.

**A file was ignored.** Only supported file types can be read. The reply lists skipped files.

## Related topics

- [[navigating-ecosystem]]
- [[access-and-menus]]

<!-- sources: routes/web.php, app/Http/Controllers/AiAssistantController.php, app/Http/Controllers/AiResearchController.php, app/Services/Ai/AiChatService.php, resources/views/ai/assistant.blade.php, resources/views/ai/research.blade.php, resources/views/partials/sidebar.blade.php -->
