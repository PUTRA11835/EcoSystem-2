---
key: delivery-types
chapter: administration
order: 131
title: Project Type, Support Type and Document Type
summary: Maintain the lists used in the Delivery Project form, the Delivery Support form and the ticket document form.
requires: [management.delivery.project, management.delivery.support, management.ticket.document-type]
updated: 2026-10-28
---
These three settings pages hold the choices people see in forms: the project types of a delivery project, the support types of a delivery support item, and the deliverable document types of a ticket.

## Who uses this

Administrators, and roles given `Management` › `Project Type`, `Support Type` or `Document Type` in Menu Access. You see only the pages your role has.

## Before you start

- The lists feed forms used by many people. Renaming or deactivating a value changes what everyone sees next time.
- Prefer `Inactive` over `Delete` when a value is only retired, so the choice stops appearing without removing the record.

## Step by step

### Project Type and Support Type

1. Open `Management` › `Project Type` (for the Delivery Project form) or `Management` › `Support Type` (for the Delivery Support form).
2. Use the status filter to show `Active` or `Inactive` values.
3. Select `Add Project Type` or `Add Support Type`, enter the name and an optional description, keep `Active` ticked, and select `Save`.
4. Use `Edit` to rename a value or switch it between active and inactive, and `Delete` to remove one.

### Document Type

![The Add Document Type window](images/guide/document-type-add.png)

*Screenshot with sample data.*

1. Open `Management` › `Document Type`. The list shows each type with its description, the ticket types that require it and whether it is `Mandatory` or `Optional`, and its status.
2. Select `Add Document Type`. Enter the `Document Type Name` (for example IR, RCA or CR Form) and a description.
3. Under `Ticket Type`, choose for every ticket type one of `Not required`, `Optional` or `Mandatory`. This is the default for tickets of that type.
4. Set the status to `Active` so the type appears in the ticket document list.
5. Select `Save`. Use `Edit` or `Delete` on a row later.

## What happens next

The new or changed values appear in the related form the next time someone opens it. Active document types show up on the ticket's `New Document` form and in the document list of the `Deliverable Documents` window. Every ticket starts with the default `Mandatory` and `Optional` choices of its ticket type, and a change to the default applies to all tickets of that type that were not changed by hand.

On a ticket, open `Deliverable Documents` and select `Edit Requirement` to change which documents are mandatory for that one ticket, then `Save`. Ticket members cannot do this. A ticket you changed shows `Customized for this ticket`; select `Reset to Default` to follow its ticket type again. Changing the ticket type also returns the ticket to the default of the new type.

## Common questions

**A new type is missing from the form.** Check that it is `Active`, then reload the form.

**I deleted a type by mistake.** Add it again with the same name. Deleting is not undone automatically.


<!-- sources: routes/web.php, app/Http/Controllers/DeliveryProjectTypeController.php, app/Http/Controllers/DeliverySupportTypeController.php, app/Http/Controllers/DeliverableDocumentTypeController.php -->
