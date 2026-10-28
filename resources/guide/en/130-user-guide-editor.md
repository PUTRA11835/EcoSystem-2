---
key: user-guide-editor
chapter: administration
order: 130
title: User Guide Editor
summary: Edit the topics employees read in the User Guide, add new ones, and restore the original text.
requires: [control-center.user-guide]
updated: 2026-10-28
---
The User Guide Editor lets an administrator change the manual without a deployment. The topics that ship with EcoSystem stay as the original, so you can always go back.

## Who uses this

Administrators, and any role given the `Control Center` › `User Guide Editor` menu in Menu Access.

## Before you start

- Employees see a topic only when it matches their menu access or role, as set in the topic's `Visible to`. Check that setting before you rely on a topic reaching someone.
- Never put passwords, credentials, or real customer or employee data in the guide.
- Edits apply to one language. Edit English and Indonesian separately.

## Step by step

### Find a topic

![The User Guide Editor list with one row per topic and its status](images/guide/user-guide-editor-list.png)

*Screenshot with sample data.*

1. Open `Control Center` › `User Guide Editor`.
2. Choose `English` or `Bahasa Indonesia`. Each row shows the title, the key and who can see it.
3. The label shows the state: `Default` (original text), `Edited` (you changed it), `Custom` (a topic you added), `default changed` (the original text changed after your edit, so review it) and `not translated` (no version in this language yet).
4. Select `View` to read the topic as an employee does, or `Edit` to change it.

### Edit a topic

![The edit form with the content box and the placement and visibility settings](images/guide/user-guide-editor-form.png)

*Screenshot with sample data.*

1. Change the `Title`, `Summary` and the content. Content is written in Markdown. Use `## Heading` for sections, and write a topic key between double square brackets to link another topic.
2. Open the `Preview` tab to see the result before you save.
3. Under `Placement`, choose the `Chapter` and the `Order in chapter`. Lower numbers come first.
4. Under `Visible to`, tick the menu access or roles that should see the topic. Leave everything unticked to show it to all employees. `Designated approvers` adds people who approve in a workflow.
5. Select `Save`. Readers see the change straight away.

### Add a topic

1. Select `New topic`, then enter a `Key` (lowercase letters, digits and hyphens, for example `my-new-topic`). The key cannot be changed later.
2. Fill the rest as above and save. The topic is shown with the `Custom` label.

### Restore the original

On an edited topic, select `Restore default` and confirm. Your edit is removed and the original text returns. On a custom topic the same button reads `Delete topic`.

## What happens next

Employees see the new text the next time they open the User Guide, and in the PDF. Every save is recorded in the `Audit Log` under the module `User Guide`, without the article text.

## Common questions

**My edit is not rejected but nobody sees it.** Check `Visible to`. A topic reaches only people with a matching menu access or role.

**The editor refuses my text.** The messages say why: em dashes and en dashes, raw HTML, external links, an image that is not an existing `images/guide` file, a link to a topic that does not exist, or an unknown menu or role.

**I want to add a picture.** Images are files kept with the application, in the `public/images/guide` folder, and cannot be uploaded here. Ask a developer to add the file, then refer to it with the usual Markdown image syntax and the path `images/guide` followed by the file name.

**The list says `default changed`.** The original text was updated after you edited. Compare it, or use `Restore default` to adopt the new original.

<!-- sources: routes/web.php, app/Http/Controllers/UserGuideAdminController.php, app/Services/Guide/GuideTopicEditor.php, resources/views/admin/user-guide/index.blade.php, resources/views/admin/user-guide/form.blade.php -->
