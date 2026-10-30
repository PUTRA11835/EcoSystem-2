---
key: user-guide-editor
chapter: administration
order: 66
title: User Guide Editor
summary: Edit the text of this guide, add your own topics, choose who sees each topic, and restore the original version.
requires: [control-center.user-guide]
updated: 2026-10-28
---
The User Guide Editor lets an administrator change what employees read in the User Guide without a new release. This topic explains how to edit a topic, add a new one, decide who can see it, and go back to the original text.

## Who uses this

Administrators who maintain the guide. By default only the top administrator has `User Guide Editor`; other roles get it through `Menu Access`.

## Before you start

- Open `Control Center` › `User Guide Editor`. Choose `English` or `Bahasa Indonesia` first: each language is edited separately.
- Each topic shows a status:
  - `Default`: the original text that comes with the application.
  - `Edited`: you changed an original topic. Employees read your version.
  - `Custom`: a topic you created yourself.
- A topic marked `default changed` was edited by you, and the original text has been updated since. Open it and check whether your version is still right.

> [!IMPORTANT]
> Employees read your changes immediately. Never put passwords, real names, salaries or other personal data in the guide.

## Step by step

### Edit a topic

1. In the list, click the topic title or `Edit`.
2. Change the `Title` and `Summary`, then edit the text in the `Visual` tab. It looks like the guide itself, so what you see is what employees read. Use the toolbar above the text:
   - The style list sets `Paragraph`, `Heading` (a section) or `Subheading`.
   - `B`, `I` and strikethrough format words. Select text first.
   - `Screen name` marks a menu, button or field name exactly as it appears on screen. It is shown in a grey box.
   - The list buttons make bulleted or numbered lists. Press `Tab` inside a list to indent an item and `Shift` + `Tab` to move it back. Press `Enter` on an empty item to leave the list.
   - `Call-out` turns the paragraph where your cursor is into a `Note`, `Tip`, `Important` or `Warning` box. Press `Enter` twice on the last empty line to leave the box.
   - `Table` inserts a table. While your cursor is in a table, a bar appears to add or remove rows and columns. Press `Tab` to move to the next cell.
   - `Topic link` links the selected words (or inserts the title) to another topic.
   - Undo and redo are the curved arrow buttons, or `Ctrl` + `Z` and `Ctrl` + `Y`.
3. Use the tabs above the text when you need them: `Markdown` shows the same text in plain form for advanced edits, and `Preview` shows exactly how the guide will display the topic.
4. Click `Save` (or press `Ctrl` + `S`). The page you return to shows the new text, and `View` opens the guide at that topic. If you leave the page with unsaved changes, the browser asks you to confirm.

### Choose who sees a topic

1. In `Visible to`, tick the `Menu access` items, the `Role` names or `Designated approvers` that should see the topic. An employee needs any one of the ticked choices.
2. Leave everything unticked to show the topic to every employee.

### Place a topic

1. Under `Placement`, choose the `Chapter`.
2. Set `Order in chapter`. Lower numbers come first.

### Add a new topic

1. Click `New topic`.
2. Enter a `Key` (lowercase letters, digits and hyphens; it cannot be changed later), the `Title`, `Summary` and `Content (Markdown)`.
3. Choose the chapter and who can see it, then `Save`.

### Link topics and add pictures

- To link to another topic, write its key between two opening and two closing square brackets. The `Writing tips` box in the editor shows the exact form.
- A picture is a file that a developer places in the guide images folder of the application, so you cannot upload pictures in the editor. Once the file is there, the `Writing tips` box shows how to add it to a topic. Every picture needs a short description for screen readers.

### Restore the original

1. Open an `Edited` topic and click `Restore default`, then confirm.
2. Employees read the original text again. On a `Custom` topic the same button is `Delete topic` and removes it.

## What happens next

Employees see the change at once, in the guide, in the search and in the downloaded PDF. Each save is recorded in `Audit Log` under the module `User Guide` (the article text itself is not recorded). The date shown as `Updated` becomes the day of your save.

## Common questions

**The editor refuses to save.** Read the red messages under the fields. Typical causes: a long dash (use a comma or colon), a web address, raw HTML, a picture file that does not exist, a link to a topic that does not exist, or a `Key` that is already used.

**A topic exists in English but not in Indonesian.** Employees reading Indonesian see the English text with a note. Open the topic under `Bahasa Indonesia` and save a translation.

**I do not see `User Guide Editor` in the menu.** It needs its own access. Ask your administrator.

## Related topics

- [[control-center]]
- [[roles-and-menu-access]]
- [[welcome]]

<!-- sources: routes/web.php, app/Http/Controllers/UserGuideAdminController.php, resources/views/admin/user-guide/index.blade.php, resources/views/admin/user-guide/form.blade.php, app/Services/Guide/GuideContentRules.php -->
